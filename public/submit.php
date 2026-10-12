<?php

declare(strict_types=1);

use Workshop\FormGuard;
use Workshop\Text;

/** @var Workshop\App $app */
$app = require dirname(__DIR__) . '/src/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    $app->redirect('/#submit');
}

$now = $app->requestTime();
$gate = $app->gate();
$uploads = $app->uploads();
$contact = $app->event['contact']['email'];
$show = static function (array $values, array $errors, int $status) use ($app): never {
    $app->page('submit', ['pageTitle' => 'Submit your paper', 'values' => $values, 'errors' => $errors, 'formPage' => true], $status);
    exit;
};

// Bodies above post_max_size arrive with empty $_POST and $_FILES.
if ($_POST === [] && $_FILES === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $show([], ['paper' => $uploads->tooLargeMessage()], 413);
}

if (!$gate->accepts($now)) {
    $show([], [], 403);
}

[$values, $errors] = $app->form()->validate($_POST);
$guardError = $app->guard()->check($_POST[FormGuard::TOKEN] ?? null, $_POST[FormGuard::HONEYPOT] ?? null, $now->getTimestamp());
if ($guardError !== null) {
    $errors['_form'] = $guardError;
}
$fileError = $uploads->validate($_FILES['paper'] ?? null);
if ($fileError !== null) {
    $errors['paper'] = $fileError;
}
if ($errors !== []) {
    $show($values, $errors, 422);
}

$repo = $app->submissions();
$file = $_FILES['paper'];

// A reload or double click re-sends exactly the same: show the existing confirmation instead of a new version.
$duplicate = $repo->findRecentDuplicate($values, (string) hash_file('sha256', $file['tmp_name']), $now->getTimestamp());
if ($duplicate !== null) {
    $app->page('submitted', ['pageTitle' => 'Submission received', 'submission' => $duplicate, 'mailSent' => (bool) $duplicate['mail_sent']]);
    exit;
}

// Only uploads that would be stored count towards the limit.
if (!$app->rateLimiter()->allow('submit', $app->clientIp(), 20, 3600, $now->getTimestamp())) {
    $show($values, ['_form' => "Too many submissions from your network. Please try again in an hour or e-mail your paper to $contact."], 429);
}

// Safety net against someone filling the disk: far above what a one-day workshop receives.
$free = @disk_free_space($app->storage);
if ($repo->count() >= (int) ($app->event['submission']['max_uploads_total'] ?? 400) || ($free !== false && $free < 500 * 1048576)) {
    error_log('Submission refused: upload ceiling reached or disk almost full');
    $show($values, ['_form' => "We cannot accept uploads right now. Please e-mail your paper to $contact."], 503);
}

$stored = $uploads->store($file);
try {
    $submission = $repo->create($values, $stored + ['original' => Text::filename($file['name'] ?? '')], $app->consentVersion(), $now->getTimestamp());
} catch (Throwable $e) {
    @unlink($uploads->path($stored['stored']));
    throw $e;
}

$mailer = $app->mailer();
$view = $app->view();
$shortTitle = $app->event['event']['short_title'];

$mailSent = $mailer->send(
    $submission['email'],
    sprintf('%s: submission received (%s)', $shortTitle, $submission['public_id']),
    $view->render('mail/confirmation', ['submission' => $submission]),
    $app->replyTo()
);
if ($mailSent) {
    $repo->markMailed($submission['public_id']);
}
foreach ($app->notifyRecipients() as $organiser) {
    $mailer->send($organiser, sprintf('%s: new submission %s', $shortTitle, $submission['public_id']), $view->render('mail/notify', ['submission' => $submission]));
}

$app->page('submitted', ['pageTitle' => 'Submission received', 'submission' => $submission, 'mailSent' => $mailSent]);
