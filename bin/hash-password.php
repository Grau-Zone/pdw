<?php

declare(strict_types=1);

/*
 * Creates a password hash for an organiser account in config/env.php.
 * Usage: php bin/hash-password.php   (then type the password and press Enter)
 * Use a long password from a password manager; hand it over via the password manager, not by e-mail.
 */

fwrite(STDERR, "Password (min. 12 characters): ");
$password = rtrim((string) fgets(STDIN), "\r\n");

if (mb_strlen($password) < 12) {
    fwrite(STDERR, "Too short – use at least 12 characters.\n");
    exit(1);
}

echo password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), "\n";
