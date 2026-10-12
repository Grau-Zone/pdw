<?php

declare(strict_types=1);

namespace Workshop;

use PDO;

final class Database
{
    public static function open(string $file): PDO
    {
        $isNew = !is_file($file);
        $pdo = new PDO('sqlite:' . $file, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA busy_timeout = 5000');
        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS submissions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                public_id TEXT NOT NULL UNIQUE,
                created_at INTEGER NOT NULL,
                version INTEGER NOT NULL,
                name TEXT NOT NULL,
                email TEXT NOT NULL,
                email_norm TEXT NOT NULL,
                affiliation TEXT NOT NULL,
                title TEXT NOT NULL,
                coauthors TEXT NOT NULL DEFAULT '',
                keywords TEXT NOT NULL DEFAULT '',
                abstract TEXT NOT NULL DEFAULT '',
                feedback TEXT NOT NULL DEFAULT '',
                consent_privacy INTEGER NOT NULL,
                consent_share INTEGER NOT NULL,
                consent_programme INTEGER NOT NULL,
                consent_version TEXT NOT NULL,
                file_stored TEXT NOT NULL,
                file_original TEXT NOT NULL,
                file_size INTEGER NOT NULL,
                file_sha256 TEXT NOT NULL,
                mail_sent INTEGER NOT NULL DEFAULT 0
            );
            CREATE INDEX IF NOT EXISTS submissions_email ON submissions (email_norm, version);
            CREATE TABLE IF NOT EXISTS hits (
                bucket TEXT NOT NULL,
                at INTEGER NOT NULL
            );
            CREATE INDEX IF NOT EXISTS hits_bucket ON hits (bucket, at);
            SQL);
        if ($isNew) {
            @chmod($file, 0640);
        }

        return $pdo;
    }
}
