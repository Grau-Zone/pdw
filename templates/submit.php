<?php
/**
 * @var Workshop\View $view
 * @var array<string, mixed> $values
 * @var array<string, string> $errors
 */
?>
<section class="rg-section">
    <div class="rg-container">
        <div class="rg-glass rg-card ws-formcard">
            <div class="rg-eyebrow">Call for papers</div>
            <h1 class="rg-h2 rg-mt-1">Submit your <span class="rg-hl">paper</span></h1>
            <div class="rg-mt-3">
                <?= $view->render('partials/submission', ['values' => $values, 'errors' => $errors]) ?>
            </div>
        </div>
    </div>
</section>
