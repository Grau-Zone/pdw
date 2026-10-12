<?php
/**
 * @var Workshop\View $view
 * @var array $event
 * @var string|null $debug
 */
?>
<section class="rg-section">
    <div class="rg-container ws-narrow">
        <div class="rg-eyebrow">Error</div>
        <h1 class="rg-h2 rg-mt-1">Something went <span class="rg-hl">wrong</span></h1>
        <div class="rg-note rg-note--error rg-mt-3">
            <?= $view->render('partials/icon', ['name' => 'error']) ?>
            <p>Sorry, there was a problem on our side. Please try again in a few minutes. If it keeps happening, write to
                <a href="mailto:<?= e($event['contact']['email']) ?>"><?= e($event['contact']['email']) ?></a>.</p>
        </div>
        <?php if ($debug !== null): ?>
            <pre class="ws-debug rg-mt-3"><?= e($debug) ?></pre>
        <?php endif; ?>
    </div>
</section>
