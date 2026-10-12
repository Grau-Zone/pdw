<?php
/**
 * The green deadline tile in the hero. It follows the server-side deadline
 * gate, so it never invites a submission the server would refuse.
 *
 * @var Workshop\App $app
 * @var array $event
 */

use Workshop\DeadlineGate;
use Workshop\Dates;

$gate = $app->gate();
$now = $app->now();
$state = $gate->state($now);
$deadline = $gate->deadline();
// Also when the form is switched off: a deadline in the past is never "open soon".
$closed = $state === DeadlineGate::CLOSED || $now > $deadline;
$confirmed = ($event['submission']['deadline_confirmed'] ?? false) === true;
?>
<div class="tile on-green<?= $closed ? ' tile--closed' : '' ?>" data-spot>
    <p class="tile__label">
        <?= $closed ? 'Submissions closed' : 'Submission deadline' ?>
        <?php if (!$confirmed && !$closed): ?><span class="chip">Provisional</span><?php endif; ?>
    </p>
    <p class="tile__day"><?= e($deadline->format('l')) ?></p>
    <p class="tile__date"><?= e($deadline->format('j M')) ?></p>
    <p class="tile__time"><?= e(Dates::timeWithZone($deadline)) ?></p>
    <?php if ($state === DeadlineGate::OPEN): ?>
        <?php
        // Rendered on the server so it is right without JavaScript; fx/kinetic.js makes it tick
        // once a minute. Whole minutes, rounded up (as the script does), so 0 m is reached
        // exactly at the deadline. Days, hours and minutes only: no figure changes every second.
        $left = max(0, $deadline->getTimestamp() - $now->getTimestamp());
        $minutes = intdiv($left + 59, 60);
        $parts = [
            'd' => [intdiv($minutes, 1440), 'day', 'days'],
            'h' => [intdiv($minutes % 1440, 60), 'hour', 'hours'],
            'm' => [$minutes % 60, 'minute', 'minutes'],
        ];
        // Seen: "18 d 09 h 15 m". Heard: "18 days 9 hours 15 minutes".
        ?>
        <p class="countdown" data-countdown data-deadline="<?= e($deadline->format(DATE_ATOM)) ?>" role="timer" aria-label="Time left until the deadline">
            <?php foreach ($parts as $unit => [$value, $one, $many]): ?>
                <span class="countdown__part"><span class="countdown__n" data-cd="<?= $unit ?>"><span class="sr-only"><?= $value ?></span><span aria-hidden="true"><?= $unit === 'd' ? $value : sprintf('%02d', $value) ?></span></span><span class="countdown__u" aria-hidden="true"><?= $unit ?></span><span class="sr-only"> <?= $value === 1 ? $one : $many ?></span></span>
            <?php endforeach; ?>
        </p>
        <a class="btn btn--light" href="#submit" data-magnetic>Submit your paper<?= icon('arrow') ?></a>
    <?php elseif (!$closed): ?>
        <p class="tile__note"><?= $state === DeadlineGate::NOT_OPEN && $gate->opensAt() !== null
            ? 'Submissions open on ' . e(Dates::withZone($gate->opensAt())) . '.'
            : 'Submissions open soon.' ?></p>
    <?php endif; ?>
</div>
