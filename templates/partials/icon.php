<?php
/**
 * Small inline icons for notes (decorative).
 *
 * @var string $name warn | ok | error
 */
$paths = [
    'warn' => 'M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z',
    'ok' => 'M20 6 9 17l-5-5',
    'error' => 'M12 8v5M12 16h.01M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z',
];
?>
<svg class="rg-note__icon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="<?= $paths[$name] ?? $paths['warn'] ?>"/></svg>
