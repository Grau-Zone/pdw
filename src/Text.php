<?php

declare(strict_types=1);

namespace Workshop;

use Normalizer;

final class Text
{
    // Bidirectional overrides can make "fdp.exe" display as "exe.pdf" – never needed in names or titles.
    private const BIDI_CONTROLS = '/[\x{202A}-\x{202E}\x{2066}-\x{2069}]/u';

    /**
     * Normalise user input: valid UTF-8 only, NFC, no control characters.
     * Single-line values get collapsed whitespace; multi-line values keep
     * line breaks (at most one empty line in a row).
     */
    public static function clean(mixed $value, bool $multiline = false): string
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
            return '';
        }
        if (class_exists(Normalizer::class)) {
            $value = Normalizer::normalize($value, Normalizer::FORM_C) ?: $value;
        }
        $value = (string) preg_replace(self::BIDI_CONTROLS, '', $value);
        $value = str_replace(["\r\n", "\r", "\u{2028}", "\u{2029}"], "\n", $value);

        if ($multiline) {
            $value = (string) preg_replace('/[^\P{Cc}\n\t]/u', '', $value);
            $value = (string) preg_replace('/[ \t]+\n/u', "\n", $value);
            $value = (string) preg_replace('/\n{3,}/u', "\n\n", $value);
        } else {
            $value = (string) preg_replace('/\p{Cc}/u', ' ', $value);
            $value = (string) preg_replace('/\s+/u', ' ', $value);
        }

        return trim($value);
    }

    /** Client-supplied file name, kept for display only (never used as a path). */
    public static function filename(mixed $value): string
    {
        $name = self::clean(is_string($value) ? basename(str_replace('\\', '/', $value)) : '');

        return mb_substr($name !== '' ? $name : 'paper.pdf', 0, 200);
    }

    /**
     * For user text that we send out by e-mail to an unverified address:
     * links are removed so the site cannot be used to mail phishing links.
     */
    public static function defang(string $value, int $max = 200): string
    {
        $value = (string) preg_replace('~\b(?:https?://|www\.)\S+~iu', '[link removed]', $value);

        return mb_strlen($value) > $max ? mb_substr($value, 0, $max - 1) . '…' : $value;
    }

    /** "340 KB", "2.4 MB" */
    public static function fileSize(int $bytes): string
    {
        return $bytes < 1048576
            ? max(1, (int) round($bytes / 1024)) . ' KB'
            : number_format($bytes / 1048576, 1) . ' MB';
    }

    /** ASCII slug for download file names. */
    public static function slug(string $value, int $max = 40): string
    {
        if (function_exists('transliterator_transliterate')) {
            $value = transliterator_transliterate('Any-Latin; Latin-ASCII', $value) ?: $value;
        }
        $value = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', $value));
        $value = trim(substr($value, 0, $max), '-');

        return $value !== '' ? $value : 'x';
    }
}
