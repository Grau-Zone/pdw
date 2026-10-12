<?php

declare(strict_types=1);

/*
 * Minimal test runner – no framework needed.
 * Each tests/*Test.php returns an array of name => closure; a closure fails by throwing.
 * Usage: php tests/run.php
 */

require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/assert.php';

$total = 0;
$failed = 0;
foreach (glob(__DIR__ . '/*Test.php') as $file) {
    $suite = basename($file, '.php');
    foreach (require $file as $name => $test) {
        $total++;
        try {
            $test();
            echo "  ok    $suite › $name\n";
        } catch (Throwable $e) {
            $failed++;
            echo "  FAIL  $suite › $name\n        " . $e->getMessage() . "\n";
        }
    }
}

echo "\n$total tests, $failed failed\n";
exit($failed === 0 ? 0 : 1);
