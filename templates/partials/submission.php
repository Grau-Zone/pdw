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
 * @var bool|null $compact  true on the home page, where the hero and the dates already state the deadline
 */

use Workshop\DeadlineGate;
use Workshop\Dates;
use Workshop\FormGuard;

$compact ??= false;
$gate = $app->gate();
$now = $app->now();
$state = $gate->state($now);
$deadline = $gate->deadline();
$past = $now > $deadline;
// A form sent back with errors during the (unadvertised) grace period may still be corrected.
$showForm = $state === DeadlineGate::OPEN || ($errors !== [] && $gate->accepts($now));
$uploads = $app->uploads();
$fields = $app->form()->fields();
$contact = $event['contact']['email'];
$maxMb = rtrim(rtrim(number_format($uploads->maxBytes() / 1048576, 1), '0'), '.');
$fieldErrors = array_diff_key($errors, ['_form' => true]);

// Single-line fields side by side where it reads naturally; long texts full width.
$layout = [['name', 'email'], ['affiliation'], ['title'], ['coauthors', 'keywords'], ['abstract'], ['feedback']];

$describedBy = static function (string $name, bool $hint) use ($errors): string {
    $ids = array_filter([$hint ? 'hint-' . $name : '', isset($errors[$name]) ? 'err-' . $name : '']);

    return ($ids === [] ? '' : ' aria-describedby="' . e(implode(' ', $ids)) . '"') . (isset($errors[$name]) ? ' aria-invalid="true"' : '');
};
$error = static function (string $name) use ($errors): string {
    return isset($errors[$name]) ? '<span class="field__error" id="err-' . e($name) . '">' . e($errors[$name]) . '</span>' : '';
};
$field = static function (string $name) use ($fields, $values, $describedBy, $error): string {
    [$label, $max, $required, $multiline, $hint] = $fields[$name];
    $id = 'f-' . $name;
    $attrs = ' id="' . e($id) . '" name="' . e($name) . '" maxlength="' . $max . '"' . ($required ? ' required' : '') . $describedBy($name, $hint !== '');
    $value = (string) ($values[$name] ?? '');

    $html = '<div class="field"><label class="field__label" for="' . e($id) . '">' . e($label)
        . ($required ? ' <span class="req" aria-hidden="true">*</span>' : '') . '</label>';
    if ($multiline) {
        $html .= '<textarea class="textarea"' . $attrs . ' rows="' . ($name === 'abstract' ? 8 : 4) . '">' . e($value) . '</textarea>';
    } else {
        $autocomplete = ['name' => 'name', 'email' => 'email', 'affiliation' => 'organization'][$name] ?? 'off';
        $html .= '<input class="input" type="' . ($name === 'email' ? 'email' : 'text') . '"' . $attrs
            . ' value="' . e($value) . '" autocomplete="' . $autocomplete . '">';
    }
    if ($hint !== '') {
        $html .= '<span class="field__hint" id="hint-' . e($name) . '">' . e($hint) . '</span>';
    }

    return $html . $error($name) . '</div>';
};
$checkbox = static function (string $name, string $labelHtml, bool $required) use ($values, $describedBy, $error): string {
    return '<label class="check"><input type="checkbox" id="f-' . e($name) . '" name="' . e($name) . '" value="1"'
        . ($required ? ' required' : '') . (!empty($values[$name]) ? ' checked' : '') . $describedBy($name, false) . '>'
        . '<span>' . $labelHtml . ($required ? ' <span class="req" aria-hidden="true">*</span>' : '') . '</span></label>'
        . $error($name);
};
$paperIds = 'hint-paper paper-check' . (isset($errors['paper']) ? ' err-paper' : '');
?>
<div class="grid submit-grid">
    <div class="submit-grid__form panel">
        <?php if ($app->submissionsByEmail() && $state === DeadlineGate::OPEN): ?>
            <?php $mailto = $app->submissionMailto(); $address = (string) $event['submission']['email']; ?>
            <p class="form__intro">Send your extended abstract by e-mail to</p>
            <p class="mailbox"><a href="<?= e($mailto) ?>"><?= e($address) ?></a></p>
            <div class="actions mt-3">
                <a class="btn btn--primary" href="<?= e($mailto) ?>" data-magnetic>Write the e-mail<?= icon('arrow') ?></a>
            </div>
            <dl class="index mt-4">
                <div><dt class="index__key">Deadline</dt><dd><?= e(Dates::withZone($deadline)) ?></dd></div>
                <div><dt class="index__key">Attachment</dt><dd>One PDF file<?= !empty($event['submission']['abstract_template']['name']) ? ', structured with the ' . e($event['submission']['abstract_template']['name']) : '' ?> (see “What to submit” above)</dd></div>
                <?php if (($event['submission']['email_subject'] ?? '') !== ''): ?>
                    <div><dt class="index__key">Subject</dt><dd><?= e($event['submission']['email_subject']) ?></dd></div>
                <?php endif; ?>
                <div><dt class="index__key">In the e-mail</dt><dd>Your name and affiliation, the paper title and co-authors, your preferred track, and whether your name, affiliation and paper title may be listed in the public programme if the paper is accepted.</dd></div>
                <div><dt class="index__key">Changes</dt><dd>To update your extended abstract before the deadline, simply send it again; the latest e-mail counts.</dd></div>
            </dl>
            <p class="small mt-3">By sending your extended abstract you agree that it is shared confidentially with the workshop faculty and participants, for the purpose of the workshop only. See the <a href="<?= e($app->url('/privacy.php')) ?>">privacy notice</a>.</p>
        <?php elseif (!$showForm || $app->submissionsByEmail()): ?>
            <div class="note note--warn">
                <?= $view->render('partials/icon', ['name' => 'warn']) ?>
                <div>
                    <?php if ($state === DeadlineGate::CLOSED || $past): ?>
                        <p><strong>The submission deadline has passed.</strong> Submissions closed on <?= e(Dates::withZone($deadline)) ?>.</p>
                    <?php elseif ($state === DeadlineGate::NOT_OPEN && $gate->opensAt() !== null): ?>
                        <p><strong>Submissions open on <?= e(Dates::withZone($gate->opensAt())) ?>.</strong></p>
                    <?php else: ?>
                        <p><strong>Submissions are not open yet.</strong> Please check back soon.</p>
                    <?php endif; ?>
                    <p>Questions? Write to <a href="mailto:<?= e($contact) ?>"><?= e($contact) ?></a>.</p>
                </div>
            </div>
        <?php else: ?>
            <?php if (!$compact): ?>
                <p class="form__intro">Deadline: <strong><?= e(Dates::withZone($deadline)) ?></strong>.</p>
            <?php endif; ?>

            <?php if ($errors !== []): ?>
                <div class="note note--error<?= $compact ? '' : ' mt-3' ?>" role="alert" tabindex="-1" id="form-errors">
                    <?= $view->render('partials/icon', ['name' => 'error']) ?>
                    <div>
                        <?php if (isset($errors['_form'])): ?><p><strong><?= e($errors['_form']) ?></strong></p><?php endif; ?>
                        <?php if ($fieldErrors !== []): ?><p>Please check the highlighted fields.</p><?php endif; ?>
                        <?php if (!isset($errors['paper'])): ?><p>For security reasons your PDF has to be attached again.</p><?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <form class="form<?= $compact && $errors === [] ? '' : ' mt-4' ?>" method="post" action="<?= e($app->url('/submit.php')) ?>" enctype="multipart/form-data"
                  data-submission data-max-bytes="<?= e((string) $uploads->maxBytes()) ?>">
                <input type="hidden" name="<?= FormGuard::TOKEN ?>" value="<?= e($app->guard()->issue($now->getTimestamp())) ?>">
                <div class="honeypot" aria-hidden="true">
                    <label for="f-hp">Leave this field empty</label>
                    <input type="text" id="f-hp" name="<?= FormGuard::HONEYPOT ?>" tabindex="-1" autocomplete="off">
                </div>

                <?php foreach ($layout as $group): ?>
                    <?php if (count($group) > 1): ?>
                        <div class="pair"><?= implode('', array_map($field, $group)) ?></div>
                    <?php else: ?>
                        <?= $field($group[0]) ?>
                    <?php endif; ?>
                <?php endforeach; ?>

                <div class="field">
                    <label class="field__label" id="f-paper-label" for="f-paper">Extended abstract (PDF) <span class="req" aria-hidden="true">*</span></label>
                    <div class="file" data-file>
                        <input id="f-paper" name="paper" type="file" accept="application/pdf,.pdf" required
                               aria-labelledby="f-paper-label f-paper-btn" aria-describedby="<?= e($paperIds) ?>"<?= isset($errors['paper']) ? ' aria-invalid="true"' : '' ?>>
                        <span class="file__btn" id="f-paper-btn" aria-hidden="true"><?= icon('file') ?>Choose PDF</span>
                        <span class="file__name" data-file-name aria-live="polite">No file chosen</span>
                    </div>
                    <span class="field__hint" id="hint-paper">PDF only, max. <?= e($maxMb) ?> MB.</span>
                    <span class="field__error" id="paper-check" data-file-error aria-live="polite"></span>
                    <?= $error('paper') ?>
                </div>

                <fieldset class="consents">
                    <legend>Consent</legend>
                    <?= $checkbox('consent_privacy', 'I have read the <a href="' . e($app->url('/privacy.php')) . '" target="_blank" rel="noopener">privacy notice</a>.', true) ?>
                    <?= $checkbox('consent_share', 'I agree that my paper is shared confidentially with the workshop faculty and participants, for the purpose of the workshop only.', true) ?>
                    <?= $checkbox('consent_programme', 'Optional: if my paper is accepted, my name, affiliation and paper title may be listed in the public programme.', false) ?>
                </fieldset>

                <div class="actions">
                    <button class="btn btn--primary" type="submit" data-magnetic><span data-label>Submit paper</span><?= icon('arrow') ?></button>
                </div>
                <p class="small">You will see a confirmation with your submission ID on the next page and receive it by e-mail.</p>
            </form>
        <?php endif; ?>
    </div>
</div>
