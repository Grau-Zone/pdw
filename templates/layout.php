<?php
/**
 * @var Workshop\App $app
 * @var array $event
 * @var string $content
 * @var bool $noindex
 * @var string|null $pageTitle
 */
$siteTitle = $event['event']['title'];
$fullTitle = isset($pageTitle) ? $pageTitle . ' – ' . $siteTitle : $siteTitle;
$description = $event['event']['tagline'];
$asset = static function (string $file) use ($app): string {
    return e($app->url('/assets/' . $file)) . '?v=' . e((string) @filemtime($app->root . '/public/assets/' . $file));
};
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($fullTitle) ?></title>
    <meta name="description" content="<?= e($description) ?>">
    <meta name="theme-color" content="#030a06">
    <?php if ($noindex): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= e($siteTitle) ?>">
    <meta property="og:description" content="<?= e($description) ?>">
    <meta property="og:url" content="<?= e($app->absoluteUrl('/')) ?>">
    <link rel="icon" href="<?= $asset('favicon.svg') ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= $asset('fonts.css') ?>">
    <link rel="stylesheet" href="<?= $asset('radar-gruen.css') ?>">
    <link rel="stylesheet" href="<?= $asset('style.css') ?>">
    <script src="<?= $asset('app.js') ?>" defer></script>
</head>
<body>
<div class="rg-bg" aria-hidden="true"><div class="rg-bg-grid"></div></div>
<a class="ws-skip" href="#main">Skip to content</a>
<?php if (!empty($event['draft'])): ?>
    <div class="rg-banner" role="note">Draft · content not final · please do not submit real papers yet</div>
<?php endif; ?>
<header class="rg-nav">
    <div class="rg-container rg-nav__inner">
        <a class="rg-brand" href="<?= e($app->url('/')) ?>">
            <span class="rg-dot ws-brand-dot" aria-hidden="true"></span>
            <span>
                <span class="rg-brand__name"><?= e($event['event']['short_title']) ?></span>
                <span class="rg-brand__sub">University of St.Gallen</span>
            </span>
        </a>
        <nav class="rg-nav__links" aria-label="Main">
            <a href="<?= e($app->url('/#about')) ?>">About</a>
            <a href="<?= e($app->url('/#programme')) ?>">Programme</a>
            <a href="<?= e($app->url('/#venue')) ?>">Venue</a>
            <a class="rg-btn rg-btn--soft" href="<?= e($app->url('/#submit')) ?>">Submit paper →</a>
        </nav>
    </div>
</header>
<main id="main">
<?= $content ?>
</main>
<footer class="rg-footer">
    <div class="rg-container rg-footer__inner">
        <div class="rg-footer__links">
            <a href="mailto:<?= e($event['contact']['email']) ?>"><?= e($event['contact']['email']) ?></a>
            <a href="<?= e($app->url('/privacy.php')) ?>">Privacy notice</a>
            <a href="<?= e($app->url('/imprint.php')) ?>">Imprint</a>
        </div>
        <div class="rg-footer__copy"><?= e($event['event']['host']) ?> · Hosted in Switzerland · no cookies, no tracking</div>
    </div>
</footer>
</body>
</html>
