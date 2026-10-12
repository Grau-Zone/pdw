<?php

declare(strict_types=1);

/*
 * Pre-flight check before going live. Run it on the server (SSH):
 *   php bin/check-env.php
 * Note: the CLI may use different PHP limits than the web server. The
 * upload limits that count are the ones in public/.user.ini.
 */

use Workshop\App;
use Workshop\Dates;
use Workshop\DeadlineGate;

require dirname(__DIR__) . '/vendor/autoload.php';

$app = App::boot();
$event = $app->event;
$env = $app->env;
$problems = 0;
$report = static function (bool $ok, string $message, bool $warnOnly = false) use (&$problems): void {
    echo ($ok ? '  ok    ' : ($warnOnly ? '  WARN  ' : '  FAIL  ')) . $message . "\n";
    if (!$ok && !$warnOnly) {
        $problems++;
    }
};
$bytes = static function (string $value): int {
    $value = trim($value);
    $unit = strtolower(substr($value, -1));
    $number = (float) $value;

    return (int) match ($unit) {
        'g' => $number * 1073741824,
        'm' => $number * 1048576,
        'k' => $number * 1024,
        default => $number,
    };
};

echo "PHP\n";
$report(PHP_VERSION_ID >= 80200, 'PHP ' . PHP_VERSION . ' (needs 8.2+)');
foreach (['fileinfo', 'mbstring', 'pdo_sqlite', 'zip', 'openssl'] as $ext) {
    $report(extension_loaded($ext), "extension $ext");
}
$report(extension_loaded('intl'), 'extension intl (nicer file names, optional)', true);

echo "\nUpload limits (from public/.user.ini – the web server uses these)\n";
$ini = @parse_ini_file($app->root . '/public/.user.ini') ?: [];
$maxFile = (int) round((float) $event['submission']['max_file_mb'] * 1048576);
$report(isset($ini['upload_max_filesize']) && $bytes((string) $ini['upload_max_filesize']) > $maxFile, 'upload_max_filesize above max_file_mb');
$report(isset($ini['post_max_size']) && $bytes((string) $ini['post_max_size']) > $bytes((string) ($ini['upload_max_filesize'] ?? '0')), 'post_max_size above upload_max_filesize');

echo "\nStorage\n";
foreach (['uploads', 'db', 'logs', 'sessions', 'tmp'] as $dir) {
    $path = $app->storage . '/' . $dir;
    if (!is_dir($path)) {
        @mkdir($path, 0750, true);
    }
    $report(is_dir($path) && is_writable($path), "storage/$dir writable");
}
$public = realpath($app->root . '/public');
$storage = realpath($app->storage);
$report($storage !== false && $public !== false && !str_starts_with($storage, $public), 'storage is outside the web root');

echo "\nSecrets and mail\n";
$report(strlen((string) ($env['app_secret'] ?? '')) >= 32, 'app_secret set (32+ characters)');
$report(!empty($env['admins']), 'at least one admin account');
$transport = $env['mail']['transport'] ?? 'log';
$report($transport === 'smtp', "mail transport is '$transport'", true);
if ($transport === 'smtp') {
    foreach (['host', 'username', 'password', 'from_address'] as $key) {
        $report(!empty($env['mail'][$key]), "mail.$key set");
    }
    $report(!str_ends_with(strtolower((string) ($env['mail']['from_address'] ?? '')), '@unisg.ch'), 'sender is not @unisg.ch (would land in quarantine)');
}
$report($app->replyTo() !== '' && !str_contains($app->replyTo(), 'example.'), 'reply_to set', true);
$report($app->notifyRecipients() !== [], 'organiser notification recipients set', true);

echo "\nConfig types (a quoted 'false' would count as true)\n";
foreach (['draft' => $event['draft'] ?? null, 'submission.enabled' => $event['submission']['enabled'] ?? null, 'privacy.approved' => $event['privacy']['approved'] ?? null] as $key => $value) {
    $report(is_bool($value), "$key is true or false (without quotes)");
}
$report(is_int($event['submission']['grace_minutes'] ?? null) && $event['submission']['grace_minutes'] >= 0, 'submission.grace_minutes is a whole number');
$report(is_int($event['submission']['max_uploads_total'] ?? null), 'submission.max_uploads_total is a whole number');

echo "\nLive-site settings\n";
$host = (string) parse_url((string) ($env['base_url'] ?? ''), PHP_URL_HOST);
$isLocal = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
// On a real server these are blocking: without https the admin cookie is not marked secure.
$report($app->isHttps(), 'base_url uses https', $isLocal);
$report(empty($env['debug']), 'debug is off', $isLocal);
$report(empty($event['draft']), 'draft mode is off', true);
$report(($event['privacy']['approved'] ?? false) === true, 'privacy texts approved', true);

echo "\nContent still marked TBA\n";
$tba = [];
$walk = static function (array $values, string $prefix) use (&$walk, &$tba): void {
    foreach ($values as $key => $value) {
        $path = $prefix . $key;
        if (is_array($value)) {
            $walk($value, $path . '.');
        } elseif (is_string($value) && (str_contains($value, 'TBA') || str_contains($value, 'example.org'))) {
            $tba[] = $path;
        }
    }
};
$walk($event, '');
$report($tba === [], $tba === [] ? 'none' : count($tba) . " values:\n          " . implode("\n          ", $tba), true);

echo "\nDeadline\n";
$gate = $app->gate();
echo '  deadline: ' . Dates::withZone($gate->deadline()) . "\n";
echo '  state now: ' . $gate->state($app->now()) . "\n";
$report($gate->state($app->now()) !== DeadlineGate::DISABLED, 'submissions switched on (needs enabled + approved privacy texts or draft mode)', true);

echo $problems === 0 ? "\nNo blocking problems.\n" : "\n$problems blocking problem(s).\n";
exit($problems === 0 ? 0 : 1);
