<?php
/**
 * @var Workshop\View $view
 * @var array $event
 * @var string|null $debug
 */
?>
<section class="page">
    <div class="wrap narrow">
        <h1 class="page__title">Something went wrong</h1>
        <div class="note note--error mt-4">
            <?= $view->render('partials/icon', ['name' => 'error']) ?>
            <p>Sorry, there was a problem on our side. Please try again in a few minutes. If it keeps happening, write to
                <a href="mailto:<?= e($event['contact']['email']) ?>"><?= e($event['contact']['email']) ?></a>.</p>
        </div>
        <?php if ($debug !== null): ?>
            <pre class="debug"><?= e($debug) ?></pre>
        <?php endif; ?>
    </div>
</section>
