<?php
/**
 * @var Workshop\View $view
 * @var array<string, mixed> $values
 * @var array<string, string> $errors
 */
?>
<section class="section section--submit section--first on-green" aria-labelledby="submit-title">
    <div class="wrap">
        <div class="shead">
            <h1 id="submit-title" class="page__title">Submit your paper</h1>
        </div>
        <?= $view->render('partials/submission', ['values' => $values, 'errors' => $errors]) ?>
    </div>
</section>
