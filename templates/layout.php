<?php
/**
 * @var Workshop\App $app
 * @var array $event
 * @var string $content
 * @var bool $noindex
 * @var string|null $pageTitle
 */

use Workshop\DeadlineGate;

$siteTitle = $event['event']['title'];
$fullTitle = isset($pageTitle) ? $pageTitle . ' – ' . $siteTitle : $siteTitle;
$description = $event['event']['tagline'];
$asset = static function (string $file) use ($app): string {
    return e($app->url('/assets/' . $file)) . '?v=' . e((string) @filemtime($app->root . '/public/assets/' . $file));
};
// The header must render even with a broken deadline in the config (e.g. on the error page).
try {
    $gate = $app->gate();
    $open = $gate->state($app->now()) === DeadlineGate::OPEN;
    $deadlineShort = $gate->deadline()->format('j M');
} catch (Throwable) {
    $open = false;
    $deadlineShort = '';
}
// On the form page itself the call to action must not leave the page (and the entered data).
$submitHref = !empty($formPage) ? '#submit-title' : $app->url('/#submit');
$sections = ['about' => 'About', 'dates' => 'Dates', 'programme' => 'Programme', 'faculty' => 'Faculty', 'venue' => 'Venue'];
$contact = $event['contact']['email'];
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($fullTitle) ?></title>
    <meta name="description" content="<?= e($description) ?>">
    <meta name="theme-color" content="#ffffff">
    <?php if ($noindex): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= e($siteTitle) ?>">
    <meta property="og:description" content="<?= e($description) ?>">
    <meta property="og:url" content="<?= e($app->absoluteUrl('/')) ?>">
    <link rel="icon" href="<?= $asset('favicon.svg') ?>" type="image/svg+xml">
    <link rel="preload" href="<?= e($app->url('/assets/fonts/source-sans-3-latin-300-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?= e($app->url('/assets/fonts/source-sans-3-latin-400-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= $asset('fonts.css') ?>">
    <link rel="stylesheet" href="<?= $asset('site.css') ?>">
    <?php // Effects: each module is optional; the page is complete without them. ?>
    <?php foreach (['field', 'scroll', 'kinetic'] as $fx): ?>
        <link rel="stylesheet" href="<?= $asset('fx/' . $fx . '.css') ?>">
    <?php endforeach; ?>
    <script src="<?= $asset('app.js') ?>" defer></script>
    <?php foreach (['field', 'scroll', 'kinetic'] as $fx): ?>
        <script src="<?= $asset('fx/' . $fx . '.js') ?>" defer></script>
    <?php endforeach; ?>
</head>
<body>
<a class="skip" href="#main">Skip to content</a>
<?php if (!empty($event['draft'])): ?>
    <div class="draftbar" role="note">Draft · content not final · please do not submit real papers yet</div>
<?php else: ?>
    <div class="utilbar">
        <div class="wrap">
            <span><?= e($event['event']['host']) ?></span>
            <a href="https://iwi.unisg.ch/" rel="noopener noreferrer">iwi.unisg.ch<?= icon('external') ?></a>
        </div>
    </div>
<?php endif; ?>
<header class="bar">
    <div class="wrap bar__inner">
        <a class="brand" href="<?= e($app->url('/')) ?>">
            <img class="brand__logo" src="<?= $asset('img/hsg-logo.svg') ?>" alt="University of St.Gallen" width="187" height="40">
            <span class="brand__name"><?= e($event['event']['short_title']) ?></span>
        </a>
        <nav class="nav" aria-label="Main">
            <?php foreach ($sections as $id => $label): ?>
                <a href="<?= e($app->url('/#' . $id)) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <?php if ($open): ?>
            <a class="btn btn--primary btn--small bar__cta" href="<?= e($submitHref) ?>" data-magnetic>
                <span class="hide-sm">Submit by <?= e($deadlineShort) ?></span><span class="show-sm">Submit</span><?= icon('arrow') ?>
            </a>
        <?php endif; ?>
        <details class="menu" data-menu>
            <summary aria-label="Menu"><?= icon('menu', 'icon i-menu') ?><?= icon('close', 'icon i-close') ?></summary>
            <nav class="menu__sheet" aria-label="Sections">
                <?php foreach ($sections as $id => $label): ?>
                    <a href="<?= e($app->url('/#' . $id)) ?>"><?= e($label) ?><?= icon('arrow') ?></a>
                <?php endforeach; ?>
                <?php if ($open): ?>
                    <a class="btn btn--light" href="<?= e($submitHref) ?>">Submit your paper<?= icon('arrow') ?></a>
                <?php else: ?>
                    <a href="<?= e($submitHref) ?>">Submission<?= icon('arrow') ?></a>
                <?php endif; ?>
            </nav>
        </details>
    </div>
</header>
<main id="main">
<?= $content ?>
</main>
<footer class="footer on-deep" data-spot>
    <div class="wrap">
        <div class="grid footer__grid">
            <p class="footer__claim"><?= e($siteTitle) ?><span><?= e($event['event']['host']) ?></span></p>
            <div class="footer__col">
                <h2>Contact</h2>
                <ul>
                    <li><a href="mailto:<?= e($contact) ?>"><?= e($contact) ?></a></li>
                </ul>
            </div>
            <div class="footer__col">
                <h2>Legal</h2>
                <ul>
                    <li><a href="<?= e($app->url('/privacy.php')) ?>">Privacy notice</a></li>
                    <li><a href="<?= e($app->url('/imprint.php')) ?>">Imprint</a></li>
                </ul>
            </div>
            <div class="footer__col">
                <h2>Institute</h2>
                <ul>
                    <li><a href="https://iwi.unisg.ch/" rel="noopener noreferrer">iwi.unisg.ch<?= icon('external') ?></a></li>
                    <li><a href="https://www.unisg.ch/" rel="noopener noreferrer">unisg.ch<?= icon('external') ?></a></li>
                </ul>
            </div>
        </div>
        <div class="footer__base">
            <span>Hosted in Switzerland · no cookies, no tracking</span>
            <span>University of St.Gallen (HSG)</span>
        </div>
    </div>
</footer>
</body>
</html>
