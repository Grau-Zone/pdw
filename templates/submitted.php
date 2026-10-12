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
<section class="rg-section">
    <div class="rg-container ws-narrow">
        <div class="rg-eyebrow">Submission received</div>
        <h1 class="rg-h2 rg-mt-1">Thank you – your paper has been <span class="rg-hl">received</span></h1>

        <div class="rg-codebox rg-mt-4" role="status">
            <div class="rg-label">Your submission ID</div>
            <div class="rg-codebox__value rg-mt-1"><?= e($submission['public_id']) ?></div>
            <p class="rg-small rg-mt-1">Please keep it for any questions. It is also in your confirmation e-mail.</p>
        </div>

        <div class="rg-card rg-mt-4">
            <dl class="ws-summary">
                <div><dt>Title</dt><dd><?= e($submission['title']) ?></dd></div>
                <div><dt>Name</dt><dd><?= e($submission['name']) ?></dd></div>
                <div><dt>E-mail</dt><dd><?= e($submission['email']) ?></dd></div>
                <div><dt>File</dt><dd><?= e($submission['file_original']) ?> (<?= e(Text::fileSize((int) $submission['file_size'])) ?>)</dd></div>
                <div><dt>Received</dt><dd><?= e(Dates::withZone($received)) ?></dd></div>
            </dl>
        </div>

        <?php if ($mailSent): ?>
            <div class="rg-note rg-note--ok rg-mt-3">
                <?= $view->render('partials/icon', ['name' => 'ok']) ?>
                <p>We have sent a confirmation to <strong><?= e($submission['email']) ?></strong>. If it does not arrive within an hour, please check your spam folder.</p>
            </div>
        <?php else: ?>
            <div class="rg-note rg-note--warn rg-mt-3">
                <?= $view->render('partials/icon', ['name' => 'warn']) ?>
                <p>Your submission is saved, but we could not send the confirmation e-mail. This page is your confirmation – you may want to take a screenshot. If in doubt, write to <a href="mailto:<?= e($contact) ?>"><?= e($contact) ?></a>.</p>
            </div>
        <?php endif; ?>

        <p class="rg-lead rg-mt-3">We will let you know by <?= e(Dates::day(Dates::parse($event['submission']['notification_date'], $app->tz))) ?>. To update your paper before the deadline, simply <a href="<?= e($app->url('/#submit')) ?>">submit again</a> with the same e-mail address.</p>
    </div>
</section>
