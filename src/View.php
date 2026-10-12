<?php

declare(strict_types=1);

namespace Workshop;

use Throwable;

/** Plain PHP templates from templates/. Escape every value with e(). */
final class View
{
    public function __construct(private readonly string $dir, private array $shared = [])
    {
        $this->shared['view'] = $this;
    }

    public function render(string $template, array $vars = []): string
    {
        $file = $this->dir . '/' . $template . '.php';
        ob_start();
        try {
            (static function (string $__file, array $__vars): void {
                extract($__vars, EXTR_SKIP);
                require $__file;
            })($file, array_merge($this->shared, $vars));
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }
}
