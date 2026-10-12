<?php

declare(strict_types=1);

/** Escape a value for HTML text and attribute context. */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

/** Inline line icon (decorative, 24px grid, drawn in currentColor). */
function icon(string $name, string $class = 'icon'): string
{
    $paths = [
        'arrow' => 'M4 12h15M13 6l6 6-6 6',
        'external' => 'M7 17 17 7M8 7h9v9',
        'menu' => 'M3 7h18M3 12h18M3 17h18',
        'close' => 'M6 6l12 12M18 6 6 18',
        'warn' => 'M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z',
        'ok' => 'M20 6 9 17l-5-5',
        'error' => 'M12 8v5M12 16h.01M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z',
        'file' => 'M14 3H6v18h12V7l-4-4ZM14 3v4h4',
        'pause' => 'M9 5v14M15 5v14',
        'play' => 'M7.5 5v14l11-7Z',
    ];

    return '<svg class="' . e($class) . '" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"'
        . ' stroke-linecap="square" stroke-linejoin="miter" aria-hidden="true" focusable="false"><path d="' . ($paths[$name] ?? $paths['arrow']) . '"/></svg>';
}
