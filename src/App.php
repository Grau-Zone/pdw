<?php

declare(strict_types=1);

namespace Workshop;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Loads the configuration once and hands out the services the entry
 * scripts in public/ need.
 *
 * config/event.php – content, dates and rules (same on every server)
 * config/env.php   – secrets and per-server settings (never in git)
 */
final class App
{
    private ?PDO $pdo = null;
    private ?View $view = null;
    private ?AdminAuth $auth = null;

    private function __construct(
        public readonly string $root,
        public readonly array $event,
        public readonly array $env,
        public readonly DateTimeZone $tz,
        public readonly string $storage,
    ) {
    }

    public static function boot(): self
    {
        $root = dirname(__DIR__);
        $configDir = getenv('WORKSHOP_CONFIG_DIR') ?: $root . '/config';
        if (!is_file($configDir . '/env.php')) {
            http_response_code(500);
            exit("Configuration missing: create config/env.php (see README).\n");
        }

        $event = require $configDir . '/event.php';
        $env = require $configDir . '/env.php';
        $storage = rtrim((string) ($env['storage_dir'] ?? $root . '/storage'), '/\\');
        foreach (['uploads', 'db', 'logs', 'sessions', 'tmp'] as $dir) {
            if (!is_dir("$storage/$dir")) {
                mkdir("$storage/$dir", 0750, true);
            }
        }

        error_reporting(E_ALL);
        ini_set('display_errors', !empty($env['debug']) ? '1' : '0');
        ini_set('log_errors', '1');
        ini_set('error_log', $storage . '/logs/php-error.log');

        $app = new self($root, $event, $env, new DateTimeZone($event['timezone']), $storage);
        set_exception_handler([$app, 'handleException']);

        return $app;
    }

    // ---------------------------------------------------------------- time

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', $this->tz);
    }

    /** When the request reached PHP. The grace period absorbs slow uploads around the deadline. */
    public function requestTime(): DateTimeImmutable
    {
        return (new DateTimeImmutable('@' . (int) ($_SERVER['REQUEST_TIME'] ?? time())))->setTimezone($this->tz);
    }

    // ------------------------------------------------------------- services

    /** On the live site (draft off) the form only opens once the privacy texts are approved. */
    public function submissionsSwitchedOn(): bool
    {
        // Strict true: a typo like 'false' (a string) must not open the form.
        return ($this->event['submission']['enabled'] ?? false) === true
            && (($this->event['draft'] ?? false) === true || ($this->event['privacy']['approved'] ?? false) === true);
    }

    public function gate(): DeadlineGate
    {
        return DeadlineGate::fromConfig($this->event['submission'], $this->tz, $this->submissionsSwitchedOn());
    }

    public function form(): SubmissionForm
    {
        $s = $this->event['submission'];

        return new SubmissionForm((int) $s['abstract_max_chars'], (bool) $s['abstract_required']);
    }

    public function uploads(): PdfUpload
    {
        return new PdfUpload($this->storage . '/uploads', (int) round((float) $this->event['submission']['max_file_mb'] * 1048576));
    }

    public function guard(): FormGuard
    {
        return new FormGuard($this->secret());
    }

    public function db(): PDO
    {
        return $this->pdo ??= Database::open($this->storage . '/db/workshop.sqlite');
    }

    public function submissions(): SubmissionRepository
    {
        return new SubmissionRepository($this->db());
    }

    public function rateLimiter(): RateLimiter
    {
        return new RateLimiter($this->db(), $this->secret());
    }

    public function mailer(): Mailer
    {
        return new Mailer($this->env['mail'] ?? [], $this->storage . '/mail-outbox');
    }

    public function auth(): AdminAuth
    {
        return $this->auth ??= new AdminAuth($this->env['admins'] ?? [], $this->isHttps(), $this->storage . '/sessions');
    }

    public function view(): View
    {
        return $this->view ??= new View($this->root . '/templates', ['app' => $this, 'event' => $this->event]);
    }

    // -------------------------------------------------------------- settings

    public function secret(): string
    {
        $secret = (string) ($this->env['app_secret'] ?? '');
        if (strlen($secret) < 32) {
            throw new RuntimeException('config/env.php: app_secret must be at least 32 characters');
        }

        return $secret;
    }

    public function consentVersion(): string
    {
        return (string) ($this->event['privacy']['version'] ?? 'unversioned');
    }

    public function replyTo(): string
    {
        return (string) ($this->env['mail']['reply_to'] ?? '');
    }

    /** @return string[] */
    public function notifyRecipients(): array
    {
        return array_values(array_filter(
            (array) ($this->env['mail']['notify'] ?? []),
            static fn ($address): bool => is_string($address) && filter_var($address, FILTER_VALIDATE_EMAIL) !== false
        ));
    }

    public function clientIp(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function isHttps(): bool
    {
        return str_starts_with((string) ($this->env['base_url'] ?? ''), 'https://');
    }

    /** Path on this site, honouring a sub-directory in base_url. */
    public function url(string $path = '/'): string
    {
        $base = rtrim((string) parse_url((string) ($this->env['base_url'] ?? ''), PHP_URL_PATH), '/');

        return $base . '/' . ltrim($path, '/');
    }

    public function absoluteUrl(string $path = '/'): string
    {
        return rtrim((string) $this->env['base_url'], '/') . '/' . ltrim($path, '/');
    }

    // ------------------------------------------------------------- responses

    public function page(string $template, array $vars = [], int $status = 200, bool $noindex = false): void
    {
        $this->sendHeaders($status, $noindex);
        $content = $this->view()->render($template, $vars);
        echo $this->view()->render('layout', $vars + ['content' => $content, 'noindex' => $noindex || !empty($this->event['draft'])]);
    }

    public function sendHeaders(int $status = 200, bool $noindex = false, string $contentType = 'text/html; charset=utf-8'): void
    {
        http_response_code($status);
        header('Content-Type: ' . $contentType);
        header("Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self'; font-src 'self'; img-src 'self' data:; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: no-referrer');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
        if ($this->isHttps()) {
            header('Strict-Transport-Security: max-age=31536000');
        }
        if ($noindex || !empty($this->event['draft'])) {
            header('X-Robots-Tag: noindex, nofollow');
        }
    }

    public function noStore(): void
    {
        header('Cache-Control: no-store, max-age=0');
    }

    public function redirect(string $path): never
    {
        header('Location: ' . $this->url($path), true, 303);
        exit;
    }

    public function handleException(Throwable $e): void
    {
        error_log((string) $e);
        if (!headers_sent()) {
            $this->sendHeaders(500, true);
        }
        try {
            echo $this->view()->render('layout', [
                'pageTitle' => 'Error',
                'noindex' => true,
                'content' => $this->view()->render('error', ['debug' => !empty($this->env['debug']) ? (string) $e : null]),
            ]);
        } catch (Throwable) {
            echo 'Something went wrong. Please try again later.';
        }
    }
}
