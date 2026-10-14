<?php

/*
 * Per-server settings and secrets. Copy to config/env.php and fill in.
 * env.php is never committed and never shared by e-mail or chat.
 * Locally, "php bin/init-local.php" creates one for you.
 */

return [
    'base_url' => 'https://example.ch',                         // no trailing slash
    'debug' => false,                                           // never true on the live site

    // At least 32 random characters: php -r "echo bin2hex(random_bytes(32));"
    'app_secret' => '',

    // Organiser accounts: username => hash from "php bin/hash-password.php"
    'admins' => [
        // 'leon' => '$2y$12$...',
    ],

    'mail' => [
        'transport' => 'smtp',                                  // 'smtp' live, 'log' writes to storage/mail-outbox/
        'host' => 'mail.infomaniak.com',                        // check the provider's SMTP settings
        'port' => 587,                                          // STARTTLS
        'username' => 'noreply@example.ch',
        'password' => '',
        'from_address' => 'noreply@example.ch',                 // an address on the event domain, never @unisg.ch
        'from_name' => 'HSG Paper Development Workshop',
        'reply_to' => 'workshop@example.ch',                    // function mailbox, not a personal address
        'notify' => [],                                         // organisers who get a short note per submission
    ],

    // Optional: keep uploads and the database somewhere else.
    // 'storage_dir' => '/home/clients/.../storage',
];
