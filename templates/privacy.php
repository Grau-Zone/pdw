<?php
/**
 * DRAFT STRUCTURE ONLY – the wording has to come from / be approved by the
 * HSG data protection office (datenschutz@unisg.ch), ideally based on the
 * HSG template for event registrations. Values come from config/event.php.
 *
 * @var Workshop\App $app
 * @var Workshop\View $view
 * @var array $event
 */

use Workshop\Dates;

$p = $event['privacy'];
$deleteAfter = Dates::day(Dates::parse($p['delete_after'], $app->tz));
?>
<section class="page">
    <div class="wrap">
        <div class="prose">
        <h1 class="page__title">Privacy notice</h1>
        <?php if (empty($p['approved'])): ?>
            <div class="note note--warn mt-4">
                <?= $view->render('partials/icon', ['name' => 'warn']) ?>
                <p>Draft – this text has not yet been approved by the HSG data protection office.</p>
            </div>
        <?php endif; ?>

        <h2>Who is responsible</h2>
        <p><?= e($p['controller']) ?>. Data protection officer: <a href="mailto:<?= e($p['dpo_email']) ?>"><?= e($p['dpo_email']) ?></a>.</p>

        <h2>What we collect and why</h2>
        <?php if ($app->submissionsByEmail()): ?>
            <p>When you send us your extended abstract by e-mail, we process your name, e-mail address, affiliation, the extended abstract and the information in your e-mail, in order to organise the workshop: to select papers, to assign them to tracks, sessions and faculty, and to contact you about the workshop. Submissions are not uploaded to this website; they are received in the University of St.Gallen mailbox <?= e((string) $event['submission']['email']) ?> and shared with the senior faculty by e-mail. [TBA: HSG e-mail service, where it stores data and who has access.]</p>
        <?php else: ?>
            <p>When you submit a paper we process your name, e-mail address, affiliation, the paper and the information you enter in the form, in order to organise the workshop: to select papers, to assign them to tracks, sessions and faculty, and to contact you about the workshop.</p>
        <?php endif; ?>
        <p>[TBA: legal basis under the St.Gallen data protection act / university act; GDPR wording if applicable.]</p>

        <h2>Who receives your data</h2>
        <p><?= e($p['recipients']) ?></p>
        <p>If you agree separately<?= $app->submissionsByEmail() ? ' (in your e-mail)' : '' ?>, your name, affiliation and paper title are listed in the public programme. Without this consent nothing about you is published.</p>

        <h2>Hosting and technical data</h2>
        <p>The website is hosted by <?= e($p['hosting']) ?>. It uses no cookies for visitors, no analytics and no third-party services (fonts are served from this site; no external scripts or captchas). The web server keeps standard access logs for security purposes [TBA: retention period of the hosting provider].</p>
        <?php if (!$app->submissionsByEmail()): ?>
            <p>To prevent abuse, the number of submissions per network address is limited; addresses are stored only as a keyed hash and deleted after 24 hours.</p>
        <?php endif; ?>

        <h2>How long we keep your data</h2>
        <p>All submissions and personal data are deleted by <?= e($deleteAfter) ?> at the latest<?= $app->submissionsByEmail() ? ', including the submission e-mails in the mailbox and the copies shared with the faculty' : '' ?>.</p>

        <h2>Your rights</h2>
        <p>You can ask for access to, correction or deletion of your data, and withdraw your consent at any time, by writing to <a href="mailto:<?= e($event['contact']['email']) ?>"><?= e($event['contact']['email']) ?></a>. [TBA: right to complain to the supervisory authority.]</p>

        <p class="small mt-4">Version <?= e($p['version']) ?></p>
        </div>
    </div>
</section>
