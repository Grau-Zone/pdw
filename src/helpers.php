<?php

declare(strict_types=1);

/** Escape a value for HTML text and attribute context. */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}
