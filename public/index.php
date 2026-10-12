<?php

declare(strict_types=1);

/** @var Workshop\App $app */
$app = require dirname(__DIR__) . '/src/bootstrap.php';

$app->page('home');
