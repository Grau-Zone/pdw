<?php

declare(strict_types=1);

namespace Workshop;

/**
 * Session login for the few organisers who may see submissions.
 * Accounts live in config/env.php as username => password hash.
 */
final class AdminAuth
{
    private const IDLE_SECONDS = 7200;
    private const ABSOLUTE_SECONDS = 43200;

    public function __construct(
        private readonly array $admins,
        private readonly bool $https,
        private readonly string $sessionDir,
    ) {
    }

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        if (!is_dir($this->sessionDir)) {
            mkdir($this->sessionDir, 0750, true);
        }
        session_save_path($this->sessionDir);
        session_name($this->https ? '__Host-ws_admin' : 'ws_admin');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $this->https,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', (string) self::ABSOLUTE_SECONDS);
        session_start();

        $now = time();
        $user = $_SESSION['user'] ?? null;
        $expired = $user !== null && (
            $now - (int) ($_SESSION['seen'] ?? 0) > self::IDLE_SECONDS
            || $now - (int) ($_SESSION['since'] ?? 0) > self::ABSOLUTE_SECONDS
            || !isset($this->admins[$user])
            // A new password hash in env.php ends all sessions of that account.
            || !hash_equals(self::fingerprint($this->admins[$user]), (string) ($_SESSION['key'] ?? ''))
        );
        if ($expired) {
            $this->logout();
            session_start();
        } elseif ($user !== null) {
            $_SESSION['seen'] = $now;
        }
    }

    public function user(): ?string
    {
        $user = $_SESSION['user'] ?? null;

        return is_string($user) && isset($this->admins[$user]) ? $user : null;
    }

    public function login(mixed $user, mixed $password): bool
    {
        if (!is_string($user) || !is_string($password) || $password === '') {
            return false;
        }
        // Verify against a real (random, unusable) hash for unknown users so timing does not reveal valid names.
        $hash = $this->admins[$user] ?? '$2y$12$HhQcck4rk8iCsD9Kfyva7u1Z3RXMHi.st8CTEUHqBa7v7B3EPjn7C';
        if (!password_verify($password, $hash) || !isset($this->admins[$user])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION = [
            'user' => $user,
            'key' => self::fingerprint($hash),
            'since' => time(),
            'seen' => time(),
            'csrf' => bin2hex(random_bytes(32)),
        ];

        return true;
    }

    private static function fingerprint(string $hash): string
    {
        return hash('sha256', 'admin-session|' . $hash);
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
            session_destroy();
        }
    }

    public function csrfToken(): string
    {
        if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf'];
    }

    public function checkCsrf(mixed $token): bool
    {
        return is_string($token) && isset($_SESSION['csrf']) && is_string($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
    }
}
