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
        <p>When you submit a paper we process your name, e-mail address, affiliation, the paper and the information you enter in the form, in order to organise the seminar: to select papers, to assign them to sessions and faculty, and to contact you about the seminar.</p>
        <p>[TBA: legal basis under the St.Gallen data protection act / university act; GDPR wording if applicable.]</p>

        <h2>Who receives your data</h2>
        <p><?= e($p['recipients']) ?></p>
        <p>If you agree separately, your name, affiliation and paper title are listed in the public programme. Without this consent nothing about you is published.</p>

        <h2>Hosting and technical data</h2>
        <p>The website is hosted by <?= e($p['hosting']) ?>. It uses no cookies for visitors, no analytics and no third-party services (fonts are served from this site; no external scripts or captchas). The web server keeps standard access logs for security purposes [TBA: retention period of the hosting provider].</p>
        <p>To prevent abuse, the number of submissions per network address is limited; addresses are stored only as a keyed hash and deleted after 24 hours.</p>

        <h2>How long we keep your data</h2>
        <p>All submissions and personal data are deleted by <?= e($deleteAfter) ?> at the latest.</p>

        <h2>Your rights</h2>
        <p>You can ask for access to, correction or deletion of your data, and withdraw your consent at any time, by writing to <a href="mailto:<?= e($event['contact']['email']) ?>"><?= e($event['contact']['email']) ?></a>. [TBA: right to complain to the supervisory authority.]</p>

        <p class="small mt-4">Version <?= e($p['version']) ?></p>
        </div>
    </div>
</section>
