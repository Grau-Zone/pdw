<?php

declare(strict_types=1);

use Workshop\SubmissionRepository;
use Workshop\Text;

/** @var Workshop\App $app */
$app = require dirname(__DIR__, 2) . '/src/bootstrap.php';

$auth = $app->auth();
$auth->start();
$app->noStore();
if ($auth->user() === null) {
    $app->redirect('/admin/');
}
// Release the session lock so a slow download does not block other admin pages.
session_write_close();

$id = (string) ($_GET['id'] ?? '');
$row = SubmissionRepository::isPublicId($id) ? $app->submissions()->find($id) : null;
$path = $row !== null ? $app->uploads()->path($row['file_stored']) : null;
if ($path === null || !is_file($path)) {
    $app->sendHeaders(404, true, 'text/plain; charset=utf-8');
    exit("Not found.\n");
}

$name = sprintf('%s_%s_%s.pdf', $row['public_id'], Text::slug($row['name'], 30), Text::slug($row['title'], 40));
$app->sendHeaders(200, true, 'application/pdf');
header('Content-Disposition: attachment; filename="' . $name . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
