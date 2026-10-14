<?php

declare(strict_types=1);

/*
 * Local development setup: creates config/env.php with a random secret,
 * a local admin account and mails written to storage/mail-outbox/.
 * Usage: php bin/init-local.php
 */

$root = dirname(__DIR__);
$envFile = $root . '/config/env.php';

foreach (['uploads', 'db', 'logs', 'sessions', 'tmp', 'mail-outbox'] as $dir) {
    if (!is_dir("$root/storage/$dir")) {
        mkdir("$root/storage/$dir", 0750, true);
    }
}

if (is_file($envFile)) {
    echo "config/env.php already exists – nothing changed.\n";
    exit(0);
}

$password = rtrim(strtr(base64_encode(random_bytes(12)), '+/', 'xy'), '=');
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
$secret = bin2hex(random_bytes(32));

$content = <<<PHP
<?php

// LOCAL DEVELOPMENT ONLY – created by bin/init-local.php. Never use on a server.
// Local admin login at http://localhost:8080/admin/ – user "admin", password "$password"

return [
    'base_url' => 'http://localhost:8080',
    'debug' => true,
    'app_secret' => '$secret',
    'admins' => [
        'admin' => '$hash',
    ],
    'mail' => [
        'transport' => 'log',
        'from_address' => 'noreply@example.org',
        'from_name' => 'HSG Paper Development Workshop (local)',
        'reply_to' => 'workshop@example.org',
        'notify' => ['organiser@example.org'],
    ],
];

PHP;

file_put_contents($envFile, $content);
echo "Created config/env.php – the local admin login is noted at the top of that file.\n";
