<?php

declare(strict_types=1);

namespace Workshop;

use PDO;
use Throwable;

/**
 * Every upload is kept as its own row. Re-submitting with the same e-mail
 * address creates a new version; the latest version per address counts.
 */
final class SubmissionRepository
{
    // Unambiguous characters only (no 0/O, 1/I/L) – people read these IDs out loud.
    private const ID_ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';
    private const CONTENT_FIELDS = ['name', 'email', 'affiliation', 'title', 'coauthors', 'keywords', 'abstract', 'feedback'];
    private const CONSENT_FIELDS = ['consent_privacy', 'consent_share', 'consent_programme'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function isPublicId(string $id): bool
    {
        return preg_match('/^S-[2-9A-HJKMNP-Z]{6}$/D', $id) === 1;
    }

    /**
     * A double click or a reload sends exactly the same thing again: same file
     * and identical form values as the person's latest version, a few minutes
     * apart. Anything that differs (a corrected title, a withdrawn consent) is
     * a new version.
     */
    public function findRecentDuplicate(array $values, string $sha256, int $now, int $windowSeconds = 600): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM submissions WHERE email_norm = ? ORDER BY version DESC LIMIT 1');
        $stmt->execute([self::normaliseEmail($values['email'])]);
        $latest = $stmt->fetch() ?: null;
        $stmt->closeCursor();

        if ($latest === null || $latest['file_sha256'] !== $sha256 || (int) $latest['created_at'] < $now - $windowSeconds) {
            return null;
        }
        foreach (self::CONTENT_FIELDS as $field) {
            if ((string) $latest[$field] !== (string) $values[$field]) {
                return null;
            }
        }
        foreach (self::CONSENT_FIELDS as $field) {
            if ((int) $latest[$field] !== (int) $values[$field]) {
                return null;
            }
        }

        return $latest;
    }

    public function count(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM submissions')->fetchColumn();
    }

    /**
     * @param array<string, mixed> $values validated form values
     * @param array{stored: string, size: int, sha256: string, original: string} $file
     */
    public function create(array $values, array $file, string $consentVersion, int $now): array
    {
        $emailNorm = self::normaliseEmail($values['email']);

        $this->pdo->exec('BEGIN IMMEDIATE');
        try {
            $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(version), 0) FROM submissions WHERE email_norm = ?');
            $stmt->execute([$emailNorm]);
            $version = (int) $stmt->fetchColumn() + 1;
            $publicId = $this->newPublicId();

            $this->pdo->prepare(
                'INSERT INTO submissions (public_id, created_at, version, name, email, email_norm, affiliation, title,
                    coauthors, keywords, abstract, feedback, consent_privacy, consent_share, consent_programme,
                    consent_version, file_stored, file_original, file_size, file_sha256)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $publicId, $now, $version, $values['name'], $values['email'], $emailNorm, $values['affiliation'],
                $values['title'], $values['coauthors'], $values['keywords'], $values['abstract'], $values['feedback'],
                (int) $values['consent_privacy'], (int) $values['consent_share'], (int) $values['consent_programme'],
                $consentVersion, $file['stored'], $file['original'], $file['size'], $file['sha256'],
            ]);
            $this->pdo->exec('COMMIT');
        } catch (Throwable $e) {
            $this->pdo->exec('ROLLBACK');
            throw $e;
        }

        return $this->find($publicId) ?? [];
    }

    public function find(string $publicId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM submissions WHERE public_id = ?');
        $stmt->execute([$publicId]);

        return $stmt->fetch() ?: null;
    }

    /**
     * All versions, newest first, each flagged whether it is the latest of its
     * submitter, plus the name used for version 1 (to spot overwrites by others).
     */
    public function all(): array
    {
        return $this->pdo->query(
            'SELECT s.*,
                (s.version = (SELECT MAX(t.version) FROM submissions t WHERE t.email_norm = s.email_norm)) AS is_latest,
                (SELECT f.name FROM submissions f WHERE f.email_norm = s.email_norm ORDER BY f.version LIMIT 1) AS first_name
             FROM submissions s ORDER BY s.created_at DESC, s.id DESC'
        )->fetchAll();
    }

    /** Only the version that counts, oldest first. */
    public function latest(): array
    {
        return array_reverse(array_values(array_filter($this->all(), static fn (array $row): bool => (bool) $row['is_latest'])));
    }

    public function markMailed(string $publicId): void
    {
        $this->pdo->prepare('UPDATE submissions SET mail_sent = 1 WHERE public_id = ?')->execute([$publicId]);
    }

    public static function normaliseEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private function newPublicId(): string
    {
        $exists = $this->pdo->prepare('SELECT 1 FROM submissions WHERE public_id = ?');
        do {
            $id = 'S-';
            for ($i = 0; $i < 6; $i++) {
                $id .= self::ID_ALPHABET[random_int(0, strlen(self::ID_ALPHABET) - 1)];
            }
            $exists->execute([$id]);
        } while ($exists->fetchColumn() !== false);

        return $id;
    }
}
