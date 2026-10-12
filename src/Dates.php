<?php

declare(strict_types=1);

namespace Workshop;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class Dates
{
    /**
     * Parse "YYYY-MM-DD HH:MM" or "YYYY-MM-DD" as local time in $tz.
     * Fails loudly on typos such as 2026-02-30 instead of silently rolling over.
     */
    public static function parse(string $value, DateTimeZone $tz): DateTimeImmutable
    {
        foreach (['!Y-m-d H:i', '!Y-m-d'] as $format) {
            $dt = DateTimeImmutable::createFromFormat($format, trim($value), $tz);
            $errors = DateTimeImmutable::getLastErrors();
            $clean = $errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0);
            if ($dt !== false && $clean) {
                return $dt;
            }
        }

        throw new InvalidArgumentException("Invalid date '$value' – expected YYYY-MM-DD or YYYY-MM-DD HH:MM");
    }

    public static function hasTime(string $value): bool
    {
        return strlen(trim($value)) > 10;
    }

    /** "Friday, 13 November 2026" */
    public static function day(DateTimeImmutable $dt): string
    {
        return $dt->format('l, j F Y');
    }

    /** "Friday, 30 October 2026, 23:59 Zurich time (CET, UTC+01:00)" */
    public static function withZone(DateTimeImmutable $dt): string
    {
        return self::day($dt) . ', ' . self::timeWithZone($dt);
    }

    /** "23:59 Zurich time (CET, UTC+01:00)" */
    public static function timeWithZone(DateTimeImmutable $dt): string
    {
        $name = $dt->getTimezone()->getName();
        $city = str_replace('_', ' ', substr((string) strrchr('/' . $name, '/'), 1));

        return sprintf('%s %s time (%s, UTC%s)', $dt->format('H:i'), $city, $dt->format('T'), $dt->format('P'));
    }
}
