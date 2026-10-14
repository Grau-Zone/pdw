<?php

declare(strict_types=1);

namespace Workshop;

use finfo;
use InvalidArgumentException;
use RuntimeException;

/**
 * Validates and stores uploaded papers. Files end up outside the web root
 * under a random name; the client's file name is never used as a path.
 */
final class PdfUpload
{
    public function __construct(
        private readonly string $dir,
        private readonly int $maxBytes,
        // Tests pass false because their files do not come from a real HTTP upload.
        private readonly bool $requireHttpUpload = true,
    ) {
    }

    /** @return string|null message for the user, or null if the file is acceptable */
    public function validate(mixed $file): ?string
    {
        if (!is_array($file) || !isset($file['error'], $file['tmp_name']) || !is_int($file['error'])) {
            return 'Please attach your extended abstract as a PDF file.';
        }

        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                return 'Please attach your extended abstract as a PDF file.';
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return $this->tooLargeMessage();
            case UPLOAD_ERR_PARTIAL:
                return 'The upload was interrupted. Please try again.';
            default:
                return 'The file could not be uploaded because of a server problem. Please try again or contact us.';
        }

        $tmp = (string) $file['tmp_name'];
        if ($this->requireHttpUpload && !is_uploaded_file($tmp)) {
            return 'The file could not be uploaded. Please try again.';
        }

        $size = @filesize($tmp);
        if ($size === false || $size === 0) {
            return 'The uploaded file is empty.';
        }
        if ($size > $this->maxBytes) {
            return $this->tooLargeMessage();
        }

        $head = (string) file_get_contents($tmp, false, null, 0, 5);
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if ($head !== '%PDF-' || $mime !== 'application/pdf') {
            return 'Only PDF files are accepted. Please export your extended abstract as PDF and upload it again.';
        }

        return null;
    }

    /**
     * Moves a validated upload into storage.
     *
     * @return array{stored: string, size: int, sha256: string}
     */
    public function store(array $file): array
    {
        $name = bin2hex(random_bytes(16)) . '.pdf';
        $target = $this->dir . DIRECTORY_SEPARATOR . $name;
        $tmp = (string) $file['tmp_name'];

        $moved = $this->requireHttpUpload ? move_uploaded_file($tmp, $target) : rename($tmp, $target);
        if (!$moved) {
            @unlink($target); // a failed copy (e.g. disk full) can leave a partial file
            throw new RuntimeException('Could not move upload into storage');
        }
        @chmod($target, 0640);

        return ['stored' => $name, 'size' => (int) filesize($target), 'sha256' => (string) hash_file('sha256', $target)];
    }

    public static function isStoredName(string $name): bool
    {
        return preg_match('/^[a-f0-9]{32}\.pdf$/D', $name) === 1;
    }

    public function path(string $stored): string
    {
        if (!self::isStoredName($stored)) {
            throw new InvalidArgumentException('Invalid stored file name');
        }

        return $this->dir . DIRECTORY_SEPARATOR . $stored;
    }

    public function maxBytes(): int
    {
        return $this->maxBytes;
    }

    public function tooLargeMessage(): string
    {
        return sprintf('The file is too large. The maximum size is %s MB.', rtrim(rtrim(number_format($this->maxBytes / 1048576, 1), '0'), '.'));
    }
}
