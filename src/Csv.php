<?php

declare(strict_types=1);

namespace Workshop;

/** CSV for Excel (Swiss/German locale: semicolon, UTF-8 with BOM). */
final class Csv
{
    public const BOM = "\xEF\xBB\xBF";

    /**
     * Cells starting with = + - @ or a control char would run as formulas in
     * Excel. An Excel set to comma as separator also splits inside values, so
     * a trigger right after a comma or semicolon is neutralised too.
     */
    public static function cell(mixed $value): string
    {
        $value = (string) $value;
        if ($value !== '' && preg_match('/^[=+\-@\t\r]/', $value) === 1) {
            $value = "'" . $value;
        }
        $value = (string) preg_replace('/([,;]\s*)([=+\-@])/u', "$1'$2", $value);

        return '"' . str_replace('"', '""', $value) . '"';
    }

    public static function row(array $cells): string
    {
        return implode(';', array_map([self::class, 'cell'], $cells)) . "\r\n";
    }
}
