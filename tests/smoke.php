<?php

declare(strict_types=1);

/*
 * End-to-end check: starts the PHP built-in server with a throw-away config
 * and storage, then submits, downloads and exports like a real user would.
 * Usage: php tests/smoke.php
 */

use Workshop\FormGuard;

require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/assert.php';

const SECRET = 'smoke-secret-smoke-secret-smoke-secret-0123456789';
const ADMIN_PASSWORD = 'smoke-test-password';

$root = dirname(__DIR__);

function make_config(string $root, array $top = [], array $submission = []): string
{
    $dir = temp_dir();
    $event = "<?php\n\$c = require " . var_export($root . '/config/event.php', true) . ";\n"
        . '$c = array_replace($c, ' . var_export($top, true) . ");\n"
        . '$c[\'submission\'] = ' . var_export($submission, true) . " + \$c['submission'];\n"
        . "return \$c;\n";
    file_put_contents("$dir/event.php", $event);
    file_put_contents("$dir/env.php", "<?php\nreturn " . var_export([
        'base_url' => 'http://127.0.0.1',
        'debug' => false,
        'app_secret' => SECRET,
        'admins' => ['smoke' => password_hash(ADMIN_PASSWORD, PASSWORD_BCRYPT, ['cost' => 4])],
        'mail' => ['transport' => 'log', 'from_address' => 'noreply@example.org', 'reply_to' => 'reply@example.org', 'notify' => ['organiser@example.org']],
        'storage_dir' => "$dir/storage",
    ], true) . ";\n");

    return $dir;
}

/** @return array{0: resource, 1: string} */
function start_server(string $root, string $configDir): array
{
    $probe = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) substr((string) strrchr(stream_socket_get_name($probe, false), ':'), 1);
    fclose($probe);

    $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
    $env = getenv();
    $env['WORKSHOP_CONFIG_DIR'] = $configDir;
    $proc = proc_open(
        [PHP_BINARY, '-S', "127.0.0.1:$port", '-t', "$root/public", '-d', 'upload_max_filesize=12M', '-d', 'post_max_size=16M', '-d', 'display_errors=stderr'],
        [1 => ['file', $null, 'w'], 2 => ['file', $null, 'w']],
        $pipes,
        $root,
        $env
    );
    for ($i = 0; $i < 100; $i++) {
        $socket = @fsockopen('127.0.0.1', $port);
        if ($socket !== false) {
            fclose($socket);
            break;
        }
        usleep(100000);
    }

    return [$proc, "http://127.0.0.1:$port"];
}

/** @return array{status: int, headers: string, body: string} */
function http(string $method, string $url, array $fields = [], array &$cookies = []): array
{
    $ch = curl_init($url);
    $headers = $cookies === [] ? [] : ['Cookie: ' . implode('; ', array_map(static fn ($k, $v) => "$k=$v", array_keys($cookies), $cookies))];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    }
    $raw = (string) curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $head = substr($raw, 0, $headerSize);
    if (preg_match_all('/^Set-Cookie:\s*([^=;\s]+)=([^;\r\n]*)/mi', $head, $m, PREG_SET_ORDER)) {
        foreach ($m as [, $name, $value]) {
            $cookies[$name] = $value;
        }
    }

    return ['status' => $status, 'headers' => $head, 'body' => substr($raw, $headerSize)];
}

function file_with(string $content): CURLFile
{
    $path = temp_dir() . '/upload.pdf';
    file_put_contents($path, $content);

    return new CURLFile($path, 'application/pdf', 'My Paper.pdf');
}

function submission(array $override = []): array
{
    return $override + [
        FormGuard::TOKEN => (new FormGuard(SECRET))->issue(time() - 10),
        FormGuard::HONEYPOT => '',
        'name' => 'Anna Muster',
        'email' => 'anna@example.org',
        'affiliation' => 'University of St.Gallen',
        'title' => 'A <script>alert(1)</script> study',
        'abstract' => 'An abstract.',
        'consent_privacy' => '1',
        'consent_share' => '1',
        'paper' => file_with(minimal_pdf()),
    ];
}

$checks = [];
$check = static function (string $name, bool $ok, string $detail = '') use (&$checks): void {
    $checks[] = $ok;
    echo ($ok ? '  ok    ' : '  FAIL  ') . $name . ($ok || $detail === '' ? '' : "\n        $detail") . "\n";
};

// The upload form is one of two submission methods; these checks run it explicitly.
const FORM = ['method' => 'form'];

// ------------------------------------------------------------------ open
$config = make_config($root, [], FORM);
[$server, $base] = start_server($root, $config);
$storage = "$config/storage";

try {
    $home = http('GET', "$base/");
    $check('home page renders', $home['status'] === 200 && str_contains($home['body'], 'data-submission'), "status {$home['status']}");
    $check('strict CSP header is sent', str_contains($home['headers'], "Content-Security-Policy: default-src 'none'"));
    $check('no external scripts, styles or images', preg_match('/<(script|link|img)\b[^>]*(src|href)="https?:/i', $home['body']) === 0);
    $check('GET submit.php redirects to the form', http('GET', "$base/submit.php")['status'] === 303);

    $ok = http('POST', "$base/submit.php", submission());
    preg_match('/S-[2-9A-HJKMNP-Z]{6}/', $ok['body'], $id);
    $id = $id[0] ?? '';
    $check('valid submission is accepted', $ok['status'] === 200 && $id !== '', "status {$ok['status']}");
    $check('title is HTML-escaped on the confirmation page', !str_contains($ok['body'], '<script>alert(1)'));
    $check('PDF is stored outside public/', count(glob("$storage/uploads/*.pdf")) === 1);
    $mails = glob("$storage/mail-outbox/*.eml");
    $check('confirmation and organiser mail are sent', count($mails) === 2);
    $check('confirmation mail contains the ID', $id !== '' && str_contains(implode('', array_map('file_get_contents', $mails)), $id));

    $again = http('POST', "$base/submit.php", submission());
    $check('reload with the same file is not a new version', str_contains($again['body'], $id) && count(glob("$storage/uploads/*.pdf")) === 1);

    $corrected = http('POST', "$base/submit.php", submission(['title' => 'A corrected title']));
    $check('correction with the same file becomes a new version', $corrected['status'] === 200
        && str_contains($corrected['body'], 'A corrected title') && !str_contains($corrected['body'], $id)
        && count(glob("$storage/uploads/*.pdf")) === 2);
    $check('page does not reveal that the address submitted before', !str_contains($corrected['body'], 'replaces'));

    $fake = http('POST', "$base/submit.php", submission(['paper' => file_with("<html>not a pdf</html>")]));
    $check('fake PDF is rejected', $fake['status'] === 422 && str_contains($fake['body'], 'Only PDF files'), "status {$fake['status']}");

    $bot = http('POST', "$base/submit.php", submission([FormGuard::HONEYPOT => 'http://spam.example']));
    $check('honeypot is rejected', $bot['status'] === 422);

    $fast = http('POST', "$base/submit.php", submission([FormGuard::TOKEN => (new FormGuard(SECRET))->issue(time())]));
    $check('instant submit is rejected', $fast['status'] === 422 && str_contains($fast['body'], 'very fast'));

    $big = http('POST', "$base/submit.php", submission(['paper' => file_with(minimal_pdf() . str_repeat('x', 11 * 1048576))]));
    $check('file above max_file_mb is rejected', $big['status'] === 422 && str_contains($big['body'], 'too large'), "status {$big['status']}");

    $huge = http('POST', "$base/submit.php", submission(['paper' => file_with(str_repeat('x', 17 * 1048576))]));
    $check('body above post_max_size gets a clear message', $huge['status'] === 413 && str_contains($huge['body'], 'too large'), "status {$huge['status']}");

    // -------------------------------------------------------------- admin
    $jar = [];
    $check('download needs a login', http('GET', "$base/admin/download.php?id=$id", [], $jar)['status'] === 303);
    $login = http('GET', "$base/admin/", [], $jar);
    preg_match('/name="csrf" value="([a-f0-9]+)"/', $login['body'], $csrf);
    $check('login form is shown', $login['status'] === 200 && isset($csrf[1]));

    $wrong = http('POST', "$base/admin/", ['action' => 'login', 'csrf' => $csrf[1] ?? '', 'user' => 'smoke', 'password' => 'nope'], $jar);
    $check('wrong password is rejected', $wrong['status'] === 401);
    $noCsrf = http('POST', "$base/admin/", ['action' => 'login', 'csrf' => 'x', 'user' => 'smoke', 'password' => ADMIN_PASSWORD], $jar);
    $check('login without CSRF token is rejected', $noCsrf['status'] === 401);
    $right = http('POST', "$base/admin/", ['action' => 'login', 'csrf' => $csrf[1] ?? '', 'user' => 'smoke', 'password' => ADMIN_PASSWORD], $jar);
    $check('correct login redirects', $right['status'] === 303);

    $list = http('GET', "$base/admin/", [], $jar);
    $check('list shows the submission', $list['status'] === 200 && str_contains($list['body'], $id));
    $check('admin page is not cached', str_contains($list['headers'], 'no-store'));

    $pdf = http('GET', "$base/admin/download.php?id=$id", [], $jar);
    $check('PDF download works', $pdf['status'] === 200 && str_starts_with($pdf['body'], '%PDF-') && str_contains($pdf['headers'], 'attachment'));
    $check('path traversal in download is refused', http('GET', "$base/admin/download.php?id=../../config/env.php", [], $jar)['status'] === 404);

    $csv = http('GET', "$base/admin/export.php?format=csv", [], $jar);
    $check('CSV export works', $csv['status'] === 200 && str_starts_with($csv['body'], "\xEF\xBB\xBF") && str_contains($csv['body'], $id));
    $zip = http('GET', "$base/admin/export.php?format=zip", [], $jar);
    $check('ZIP export works', $zip['status'] === 200 && str_starts_with($zip['body'], 'PK'));

    preg_match('/name="csrf" value="([a-f0-9]+)"/', $list['body'], $csrf2);
    http('POST', "$base/admin/", ['action' => 'logout', 'csrf' => $csrf2[1] ?? ''], $jar);
    $check('logout ends the session', str_contains(http('GET', "$base/admin/", [], $jar)['body'], 'Organiser login'));
} finally {
    proc_terminate($server);
}

// ---------------------------------------------------------------- closed
$config = make_config($root, [], ['deadline' => '2020-01-01 00:00'] + FORM);
[$server, $base] = start_server($root, $config);
try {
    $check('after the deadline the page says so', str_contains(http('GET', "$base/")['body'], 'deadline has passed'));
    $late = http('POST', "$base/submit.php", submission());
    $check('after the deadline submissions are refused', $late['status'] === 403 && count(glob("$config/storage/uploads/*.pdf") ?: []) === 0, "status {$late['status']}");
} finally {
    proc_terminate($server);
}

// ------------------------------------- live site without approved privacy texts
$config = make_config($root, ['draft' => false], FORM);
[$server, $base] = start_server($root, $config);
try {
    $check('live site without approval shows no form', !str_contains(http('GET', "$base/")['body'], 'data-submission'));
    $check('live site without approval refuses submissions', http('POST', "$base/submit.php", submission())['status'] === 403);
} finally {
    proc_terminate($server);
}

// ------------------------------------------------- submissions by e-mail
$config = make_config($root, [], ['method' => 'email', 'email' => 'submit@example.org', 'email_subject' => 'PDW test']);
[$server, $base] = start_server($root, $config);
try {
    $home = http('GET', "$base/")['body'];
    $check('e-mail mode shows the mailto link instead of the form', str_contains($home, 'href="mailto:submit@example.org?subject=PDW%20test"') && !str_contains($home, 'data-submission'));
    $post = http('POST', "$base/submit.php", submission());
    $check('e-mail mode stores no upload', $post['status'] === 303 && count(glob("$config/storage/uploads/*.pdf") ?: []) === 0, "status {$post['status']}");
    $check('e-mail mode privacy text has no rate-limit note', !str_contains(http('GET', "$base/privacy.php")['body'], 'per network address'));
} finally {
    proc_terminate($server);
}

$config = make_config($root, [], ['method' => 'email', 'email' => 'submit@example.org', 'deadline' => '2020-01-01 00:00']);
[$server, $base] = start_server($root, $config);
try {
    $home = http('GET', "$base/")['body'];
    $check('e-mail mode after the deadline: closed, no mailto', str_contains($home, 'deadline has passed') && !str_contains($home, 'mailto:submit@example.org'));
} finally {
    proc_terminate($server);
}

$config = make_config($root, [], ['method' => 'email', 'email' => 'submit@example ']);
[$server, $base] = start_server($root, $config);
try {
    $home = http('GET', "$base/")['body'];
    $check('a broken submission address shows neither the form nor a mailto', !str_contains($home, 'data-submission') && !str_contains($home, 'mailto:submit@'));
} finally {
    proc_terminate($server);
}

$config = make_config($root, ['draft' => false], ['method' => 'email', 'email' => 'submit@example.org']);
[$server, $base] = start_server($root, $config);
try {
    $check('e-mail mode on the live site waits for approved privacy texts', !str_contains(http('GET', "$base/")['body'], 'mailto:submit@example.org'));
} finally {
    proc_terminate($server);
}

$failed = count(array_filter($checks, static fn (bool $ok): bool => !$ok));
echo "\n" . count($checks) . " checks, $failed failed\n";
exit($failed === 0 ? 0 : 1);
