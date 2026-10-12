<?php

declare(strict_types=1);

/** @var Workshop\App $app */
$app = require dirname(__DIR__, 2) . '/src/bootstrap.php';

$auth = $app->auth();
$auth->start();
$app->noStore();

$error = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!$auth->checkCsrf($_POST['csrf'] ?? null)) {
        $error = 'Your session has expired. Please try again.';
    } elseif ($action === 'logout') {
        $auth->logout();
        $app->redirect('/admin/');
    } elseif ($action === 'login') {
        if (!$app->rateLimiter()->allow('login', $app->clientIp(), 10, 900, time())) {
            $error = 'Too many login attempts. Please wait 15 minutes.';
        } elseif ($auth->login($_POST['user'] ?? null, $_POST['password'] ?? null)) {
            $app->redirect('/admin/');
        } else {
            $error = 'Wrong username or password.';
        }
    }
}

$user = $auth->user();
if ($user === null) {
    $app->page('admin/login', ['pageTitle' => 'Organiser login', 'csrf' => $auth->csrfToken(), 'error' => $error], $error === null ? 200 : 401, true);
    exit;
}

$app->page('admin/list', [
    'pageTitle' => 'Submissions',
    'rows' => $app->submissions()->all(),
    'user' => $user,
    'csrf' => $auth->csrfToken(),
], 200, true);
