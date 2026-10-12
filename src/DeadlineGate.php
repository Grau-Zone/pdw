<?php

declare(strict_types=1);

namespace Workshop;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Decides on the server whether submissions are accepted. The browser never
 * decides: a stale form or a manipulated clock cannot get past this check.
 */
final class DeadlineGate
{
    public const DISABLED = 'disabled';
    public const NOT_OPEN = 'not_open';
    public const OPEN = 'open';
    public const CLOSED = 'closed';

    public function __construct(
        private readonly bool $switchedOn,
        private readonly ?DateTimeImmutable $opensAt,
        private readonly DateTimeImmutable $deadline,
        private readonly int $graceSeconds,
    ) {
    }

    public static function fromConfig(array $submission, DateTimeZone $tz, bool $switchedOn): self
    {
        $raw = (string) $submission['deadline'];
        $deadline = Dates::parse($raw, $tz);
        // "23:59" means until 23:59:59; a bare date means until the end of that day.
        $deadline = Dates::hasTime($raw)
            ? $deadline->setTime((int) $deadline->format('H'), (int) $deadline->format('i'), 59)
            : $deadline->setTime(23, 59, 59);

        $opensAt = empty($submission['opens_at']) ? null : Dates::parse((string) $submission['opens_at'], $tz);

        return new self($switchedOn, $opensAt, $deadline, max(0, (int) ($submission['grace_minutes'] ?? 0)) * 60);
    }

    public function deadline(): DateTimeImmutable
    {
        return $this->deadline;
    }

    public function opensAt(): ?DateTimeImmutable
    {
        return $this->opensAt;
    }

    /** What visitors see. The grace period is deliberately not advertised. */
    public function state(DateTimeImmutable $now): string
    {
        if (!$this->switchedOn) {
            return self::DISABLED;
        }
        if ($this->opensAt !== null && $now < $this->opensAt) {
            return self::NOT_OPEN;
        }

        return $now <= $this->deadline ? self::OPEN : self::CLOSED;
    }

    /** Whether a submission received at $now is accepted (deadline plus grace period). */
    public function accepts(DateTimeImmutable $now): bool
    {
        if (!$this->switchedOn || ($this->opensAt !== null && $now < $this->opensAt)) {
            return false;
        }

        return $now->getTimestamp() <= $this->deadline->getTimestamp() + $this->graceSeconds;
    }
}
