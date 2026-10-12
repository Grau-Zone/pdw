<?php
/**
 * Submission status and form. Used on the home page and when a submission
 * comes back with errors.
 *
 * @var Workshop\App $app
 * @var Workshop\View $view
 * @var array $event
 * @var array<string, mixed> $values
 * @var array<string, string> $errors
 */

use Workshop\DeadlineGate;
use Workshop\Dates;
use Workshop\FormGuard;

$gate = $app->gate();
$now = $app->now();
$state = $gate->state($now);
// A form sent back with errors during the (unadvertised) grace period may still be corrected.
$showForm = $state === DeadlineGate::OPEN || ($errors !== [] && $gate->accepts($now));
$uploads = $app->uploads();
$fields = $app->form()->fields();
$contact = $event['contact']['email'];
$maxMb = rtrim(rtrim(number_format($uploads->maxBytes() / 1048576, 1), '0'), '.');

// Single-line fields side by side where it reads naturally; long texts full width.
$layout = [['name', 'email'], ['affiliation'], ['title'], ['coauthors', 'keywords'], ['abstract'], ['feedback']];

$describedBy = static function (string $name, bool $hint) use ($errors): string {
    $ids = array_filter([$hint ? 'hint-' . $name : '', isset($errors[$name]) ? 'err-' . $name : '']);

    return ($ids === [] ? '' : ' aria-describedby="' . e(implode(' ', $ids)) . '"') . (isset($errors[$name]) ? ' aria-invalid="true"' : '');
};
$error = static function (string $name) use ($errors): string {
    return isset($errors[$name]) ? '<span class="rg-field__error" id="err-' . e($name) . '">' . e($errors[$name]) . '</span>' : '';
};
$field = static function (string $name) use ($fields, $values, $describedBy, $error): string {
    [$label, $max, $required, $multiline, $hint] = $fields[$name];
    $id = 'f-' . $name;
    $attrs = ' id="' . e($id) . '" name="' . e($name) . '" maxlength="' . $max . '"' . ($required ? ' required' : '') . $describedBy($name, $hint !== '');
    $value = (string) ($values[$name] ?? '');

    $html = '<div class="rg-field"><label class="rg-field__label" for="' . e($id) . '">' . e($label)
        . ($required ? ' <span class="ws-req" aria-hidden="true">*</span>' : '') . '</label>';
    if ($multiline) {
        $html .= '<textarea class="rg-textarea"' . $attrs . ' rows="' . ($name === 'abstract' ? 8 : 4) . '">' . e($value) . '</textarea>';
    } else {
        $autocomplete = ['name' => 'name', 'email' => 'email', 'affiliation' => 'organization'][$name] ?? 'off';
        $html .= '<input class="rg-input" type="' . ($name === 'email' ? 'email' : 'text') . '"' . $attrs
            . ' value="' . e($value) . '" autocomplete="' . $autocomplete . '">';
    }
    if ($hint !== '') {
        $html .= '<span class="rg-field__hint" id="hint-' . e($name) . '">' . e($hint) . '</span>';
    }

    return $html . $error($name) . '</div>';
};
$checkbox = static function (string $name, string $labelHtml, bool $required) use ($values, $describedBy, $error): string {
    return '<label class="rg-check"><input type="checkbox" id="f-' . e($name) . '" name="' . e($name) . '" value="1"'
        . ($required ? ' required' : '') . (!empty($values[$name]) ? ' checked' : '') . $describedBy($name, false) . '>'
        . '<span>' . $labelHtml . ($required ? ' <span class="ws-req" aria-hidden="true">*</span>' : '') . '</span></label>'
        . $error($name);
};
?>
<?php if (!$showForm): ?>
    <div class="rg-note rg-note--warn">
        <?= $view->render('partials/icon', ['name' => 'warn']) ?>
        <div>
            <?php if ($state === DeadlineGate::CLOSED): ?>
                <p><strong>The submission deadline has passed.</strong> Submissions closed on <?= e(Dates::withZone($gate->deadline())) ?>.</p>
            <?php elseif ($state === DeadlineGate::NOT_OPEN && $gate->opensAt() !== null): ?>
                <p><strong>Submissions open on <?= e(Dates::withZone($gate->opensAt())) ?>.</strong></p>
            <?php else: ?>
                <p><strong>Submissions are not open yet.</strong> Please check back soon.</p>
            <?php endif; ?>
            <p>Questions? Write to <a href="mailto:<?= e($contact) ?>"><?= e($contact) ?></a>.</p>
        </div>
    </div>
<?php else: ?>
    <p class="rg-card__lead">Deadline: <strong><?= e(Dates::withZone($gate->deadline())) ?></strong>.
        To update your submission, simply submit again with the same e-mail address before the deadline – the latest version counts.</p>

    <?php if ($errors !== []): ?>
        <div class="rg-note rg-note--error ws-mb" role="alert" tabindex="-1" id="form-errors">
            <?= $view->render('partials/icon', ['name' => 'error']) ?>
            <div>
                <p><strong>Please check the highlighted fields.</strong> <?= isset($errors['_form']) ? e($errors['_form']) : '' ?></p>
                <?php if (!isset($errors['paper'])): ?><p>For security reasons your PDF has to be attached again.</p><?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <form class="ws-form" method="post" action="<?= e($app->url('/submit.php')) ?>" enctype="multipart/form-data"
          data-submission data-max-bytes="<?= e((string) $uploads->maxBytes()) ?>">
        <input type="hidden" name="<?= FormGuard::TOKEN ?>" value="<?= e($app->guard()->issue($now->getTimestamp())) ?>">
        <div class="ws-hp" aria-hidden="true">
            <label for="f-hp">Leave this field empty</label>
            <input type="text" id="f-hp" name="<?= FormGuard::HONEYPOT ?>" tabindex="-1" autocomplete="off">
        </div>

        <?php foreach ($layout as $group): ?>
            <?php if (count($group) > 1): ?>
                <div class="rg-grid rg-grid--2"><?= implode('', array_map($field, $group)) ?></div>
            <?php else: ?>
                <?= $field($group[0]) ?>
            <?php endif; ?>
        <?php endforeach; ?>

        <div class="rg-field">
            <label class="rg-field__label" for="f-paper">Paper (PDF) <span class="ws-req" aria-hidden="true">*</span></label>
            <input class="rg-file" id="f-paper" name="paper" type="file" accept="application/pdf,.pdf" required<?= $describedBy('paper', true) ?>>
            <span class="rg-field__hint" id="hint-paper">PDF only, max. <?= e($maxMb) ?> MB. A new submission with the same e-mail address replaces the earlier one.</span>
            <?= $error('paper') ?>
        </div>

        <fieldset class="ws-consents">
            <legend class="rg-field__label">Consent</legend>
            <?= $checkbox('consent_privacy', 'I have read the <a href="' . e($app->url('/privacy.php')) . '" target="_blank" rel="noopener">privacy notice</a>.', true) ?>
            <?= $checkbox('consent_share', 'I agree that my paper is shared confidentially with the workshop faculty and participants, for the purpose of the workshop only.', true) ?>
            <?= $checkbox('consent_programme', 'Optional: if my paper is accepted, my name, affiliation and paper title may be listed in the public programme.', false) ?>
        </fieldset>

        <div class="rg-actions">
            <button class="rg-btn rg-btn--primary" type="submit">Submit paper →</button>
        </div>
        <p class="rg-small">You will see a confirmation with your submission ID on the next page and receive it by e-mail.</p>
    </form>
<?php endif; ?>
