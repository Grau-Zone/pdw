<?php
/**
 * @var Workshop\App $app
 * @var Workshop\View $view
 * @var string $csrf
 * @var string|null $error
 */
?>
<section class="rg-section">
    <div class="rg-container ws-narrow">
        <div class="rg-glass rg-card ws-formcard">
            <div class="rg-eyebrow">Organisers</div>
            <h1 class="rg-h2 rg-mt-1">Organiser login</h1>
            <?php if ($error !== null): ?>
                <div class="rg-note rg-note--error rg-mt-3" role="alert">
                    <?= $view->render('partials/icon', ['name' => 'error']) ?>
                    <p><?= e($error) ?></p>
                </div>
            <?php endif; ?>
            <form class="ws-form rg-mt-3" method="post" action="<?= e($app->url('/admin/')) ?>">
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <div class="rg-field">
                    <label class="rg-field__label" for="a-user">Username</label>
                    <input class="rg-input" id="a-user" name="user" type="text" autocomplete="username" required>
                </div>
                <div class="rg-field">
                    <label class="rg-field__label" for="a-pass">Password</label>
                    <input class="rg-input" id="a-pass" name="password" type="password" autocomplete="current-password" required>
                </div>
                <div class="rg-actions">
                    <button class="rg-btn rg-btn--primary" type="submit">Log in →</button>
                </div>
            </form>
        </div>
    </div>
</section>
