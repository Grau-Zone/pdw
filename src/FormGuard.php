<?php

declare(strict_types=1);

namespace Workshop;

/**
 * Spam protection without third-party services: a signed timestamp (forms
 * sent back within seconds are bots, very old ones are stale) plus a
 * honeypot field that people never see.
 */
final class FormGuard
{
    public const TOKEN = 'ft';
    public const HONEYPOT = 'website';

    public function __construct(
        private readonly string $secret,
        private readonly int $minSeconds = 3,
        private readonly int $maxSeconds = 172800,
    ) {
    }

    public function issue(int $now): string
    {
        return $now . '.' . $this->sign((string) $now);
    }

    /** @return string|null message for the user, or null if the request looks human */
    public function check(mixed $token, mixed $honeypot, int $now): ?string
    {
        $generic = 'Your submission could not be processed. Please reload the page and try again.';

        if (!is_string($token) || preg_match('/^(\d{1,12})\.([a-f0-9]{32})$/D', $token, $m) !== 1) {
            return $generic;
        }
        if (!hash_equals($this->sign($m[1]), $m[2])) {
            return $generic;
        }
        if (is_string($honeypot) && $honeypot !== '') {
            return $generic;
        }

        $age = $now - (int) $m[1];
        if ($age < $this->minSeconds) {
            return 'That was very fast. Please wait a moment and submit again.';
        }
        if ($age > $this->maxSeconds) {
            return 'This form has expired. Please reload the page and submit again.';
        }

        return null;
    }

    private function sign(string $payload): string
    {
        return substr(hash_hmac('sha256', 'submission-form|' . $payload, $this->secret), 0, 32);
    }
}
