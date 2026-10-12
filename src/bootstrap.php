<?php

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    exit("Dependencies missing: run \"composer install --no-dev\" (see README).\n");
}
require $autoload;

return Workshop\App::boot();
