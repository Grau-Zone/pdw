<?php

declare(strict_types=1);

function same(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(trim($message . ' – expected ' . var_export($expected, true) . ', got ' . var_export($actual, true), ' –'));
    }
}

function truthy(mixed $value, string $message): void
{
    if (!$value) {
        throw new RuntimeException($message);
    }
}

/** Temporary directory that is removed at the end of the test run. */
function temp_dir(): string
{
    $dir = sys_get_temp_dir() . '/ws-test-' . bin2hex(random_bytes(6));
    mkdir($dir, 0700, true);
    register_shutdown_function(static function () use ($dir): void {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    });

    return $dir;
}

/** A tiny but valid one-page PDF. */
function minimal_pdf(): string
{
    return "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
        . "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
}
