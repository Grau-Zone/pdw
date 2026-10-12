<?php
/**
 * @var Workshop\App $app
 * @var Workshop\View $view
 * @var array $event
 * @var array $submission
 * @var bool $mailSent
 */

use Workshop\Dates;
use Workshop\Text;

$received = (new DateTimeImmutable('@' . $submission['created_at']))->setTimezone($app->tz);
$contact = $event['contact']['email'];
?>
<section class="page">
    <div class="wrap narrow">
        <h1 class="page__title">Thank you – your paper has been received</h1>

        <div class="receipt on-green" role="status">
            <p class="receipt__label">Your submission ID</p>
            <p class="receipt__id"><?= e($submission['public_id']) ?></p>
            <p class="receipt__hint">Please keep it for any questions. It is also in your confirmation e-mail.</p>
        </div>

        <dl class="index mt-4">
            <div><dt class="index__key">Title</dt><dd><?= e($submission['title']) ?></dd></div>
            <div><dt class="index__key">Name</dt><dd><?= e($submission['name']) ?></dd></div>
            <div><dt class="index__key">E-mail</dt><dd><?= e($submission['email']) ?></dd></div>
            <div><dt class="index__key">File</dt><dd><?= e($submission['file_original']) ?> (<?= e(Text::fileSize((int) $submission['file_size'])) ?>)</dd></div>
            <div><dt class="index__key">Received</dt><dd><?= e(Dates::withZone($received)) ?></dd></div>
        </dl>

        <?php if ($mailSent): ?>
            <div class="note note--ok mt-4">
                <?= $view->render('partials/icon', ['name' => 'ok']) ?>
                <p>We have sent a confirmation to <strong><?= e($submission['email']) ?></strong>. If it does not arrive within an hour, please check your spam folder.</p>
            </div>
        <?php else: ?>
            <div class="note note--warn mt-4">
                <?= $view->render('partials/icon', ['name' => 'warn']) ?>
                <p>Your submission is saved, but we could not send the confirmation e-mail. This page is your confirmation – you may want to take a screenshot. If in doubt, write to <a href="mailto:<?= e($contact) ?>"><?= e($contact) ?></a>.</p>
            </div>
        <?php endif; ?>

        <p class="lead mt-4">We will let you know by <?= e(Dates::day(Dates::parse($event['submission']['notification_date'], $app->tz))) ?>. To update your paper before the deadline, simply <a href="<?= e($app->url('/#submit')) ?>">submit again</a> with the same e-mail address.</p>
    </div>
</section>
