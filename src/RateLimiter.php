<?php

declare(strict_types=1);

namespace Workshop;

use PDO;
use PDOException;
use Throwable;

/**
 * Sliding-window limit per client network. IP addresses are stored only as
 * keyed hashes and every entry is dropped after 24 hours.
 */
final class RateLimiter
{
    public function __construct(private readonly PDO $pdo, private readonly string $secret)
    {
    }

    /** Records an attempt; false once $limit attempts were made within $windowSeconds. */
    public function allow(string $scope, string $clientIp, int $limit, int $windowSeconds, int $now): bool
    {
        $bucket = $scope . ':' . substr(hash_hmac('sha256', self::network($clientIp), $this->secret), 0, 32);

        try {
            // One write transaction: check and insert are atomic, and SQLite's busy timeout applies.
            $this->pdo->exec('BEGIN IMMEDIATE');
            try {
                $this->pdo->prepare('DELETE FROM hits WHERE at < ?')->execute([$now - 86400]);
                $count = $this->pdo->prepare('SELECT COUNT(*) FROM hits WHERE bucket = ? AND at > ?');
                $count->execute([$bucket, $now - $windowSeconds]);
                $hits = (int) $count->fetchColumn();
                $count->closeCursor();

                $allowed = $hits < $limit;
                if ($allowed) {
                    $this->pdo->prepare('INSERT INTO hits (bucket, at) VALUES (?, ?)')->execute([$bucket, $now]);
                }
                $this->pdo->exec('COMMIT');
            } catch (Throwable $e) {
                $this->pdo->exec('ROLLBACK');
                throw $e;
            }
        } catch (PDOException $e) {
            // A busy database must never block a real submission on the deadline evening.
            error_log('Rate limiter unavailable, allowing request: ' . $e->getMessage());

            return true;
        }

        return $allowed;
    }

    /** IPv6 clients usually control a whole /64, so they count as one network. */
    public static function network(string $ip): string
    {
        $binary = @inet_pton($ip);
        if ($binary === false || strlen($binary) !== 16) {
            return $ip;
        }
        if (str_starts_with($binary, str_repeat("\0", 10) . "\xff\xff")) {
            return (string) inet_ntop(substr($binary, 12));
        }

        return bin2hex(substr($binary, 0, 8)) . '::/64';
    }
}
