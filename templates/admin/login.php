<?php
/**
 * @var Workshop\App $app
 * @var Workshop\View $view
 * @var string $csrf
 * @var string|null $error
 */
?>
<section class="page">
    <div class="wrap narrow">
        <h1 class="page__title">Organiser login</h1>
        <?php if ($error !== null): ?>
            <div class="note note--error mt-4" role="alert">
                <?= $view->render('partials/icon', ['name' => 'error']) ?>
                <p><?= e($error) ?></p>
            </div>
        <?php endif; ?>
        <form class="form mt-4" method="post" action="<?= e($app->url('/admin/')) ?>">
            <input type="hidden" name="action" value="login">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <div class="field">
                <label class="field__label" for="a-user">Username</label>
                <input class="input" id="a-user" name="user" type="text" autocomplete="username" required>
            </div>
            <div class="field">
                <label class="field__label" for="a-pass">Password</label>
                <input class="input" id="a-pass" name="password" type="password" autocomplete="current-password" required>
            </div>
            <div class="actions">
                <button class="btn btn--primary" type="submit">Log in<?= icon('arrow') ?></button>
            </div>
        </form>
    </div>
</section>
