<?php
/**
 * Programme in the format of the CHAIS PDW Bern: joint items, breaks and
 * blocks of parallel paper tracks (see 'programme' in config/event.php).
 * Until papers are selected a paper session is one line per track; once
 * titles are filled in, it becomes a table (time × track) that stays three
 * columns wide on a phone.
 *
 * @var Workshop\App $app
 * @var array $event
 */

use Workshop\Dates;

$tracks = $event['tracks'];
$notification = Dates::parse($event['submission']['notification_date'], $app->tz);
$range = static fn (string $from, string $to): string => $to !== ''
    ? '<span class="nowrap">' . e($from) . '–</span><span class="nowrap">' . e($to) . '</span>'
    : e($from);
$hasPapers = static function (array $slots): bool {
    foreach ($slots as $trackSlots) {
        foreach ($trackSlots as $slot) {
            if ($slot['title'] !== '' || $slot['presenter'] !== '') {
                return true;
            }
        }
    }

    return false;
};
?>
<ol class="agenda">
    <?php foreach ($event['programme'] as $item): ?>
        <?php $kind = $item['kind'] ?? 'plenary'; ?>
        <li class="is-<?= e($kind) ?>">
            <p class="agenda__time"><?= $range($item['time'], $item['end'] ?? '') ?></p>
            <div>
                <p class="agenda__title"><?= e($item['title']) ?></p>
                <?php if (!empty($item['detail'])): ?><p class="agenda__detail"><?= e($item['detail']) ?></p><?php endif; ?>

                <?php if ($kind === 'papers' && !$hasPapers($item['slots'])): ?>
                    <ul class="tracks">
                        <?php foreach ($item['slots'] as $trackId => $trackSlots): ?>
                            <li>
                                <span><?= e($tracks[$trackId]['name']) ?><span class="tracks__sep"> · </span><span class="tracks__meta">Chairs: <?= e($tracks[$trackId]['chair']) ?></span></span>
                                <span class="tracks__count"><?= e($tracks[$trackId]['room']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="tracks__note">Paper titles follow after the notification on <?= e($notification->format('j F')) ?>.</p>
                <?php elseif ($kind === 'papers'): ?>
                    <?php
                    $trackIds = array_keys($item['slots']);
                    $rows = max(array_map('count', $item['slots']));
                    ?>
                    <table class="sessions">
                        <caption class="sr-only"><?= e($item['title']) ?>: parallel tracks</caption>
                        <thead>
                        <tr>
                            <th scope="col" class="sessions__time"><span class="sr-only">Time</span></th>
                            <?php foreach ($trackIds as $trackId): ?>
                                <th scope="col"><?= e($tracks[$trackId]['name']) ?><span><?= e($tracks[$trackId]['room']) ?> · <?= e($tracks[$trackId]['chair']) ?></span></th>
                            <?php endforeach; ?>
                        </tr>
                        </thead>
                        <tbody>
                        <?php for ($i = 0; $i < $rows; $i++): ?>
                            <?php $first = $item['slots'][$trackIds[0]][$i] ?? null; ?>
                            <tr>
                                <th scope="row" class="sessions__time"><?= $first !== null ? $range($first['time'], $first['end']) : '' ?></th>
                                <?php foreach ($trackIds as $trackId): ?>
                                    <?php $slot = $item['slots'][$trackId][$i] ?? null; ?>
                                    <td>
                                        <?php if ($slot === null): ?>
                                            <span class="tba">–</span>
                                        <?php else: ?>
                                            <span class="slot__title<?= $slot['title'] === '' ? ' tba' : '' ?>"><?= e($slot['title'] !== '' ? $slot['title'] : 'Paper title TBA') ?></span>
                                            <span class="slot__who"><?= e($slot['presenter'] !== '' ? $slot['presenter'] . ($slot['affiliation'] !== '' ? ' (' . $slot['affiliation'] . ')' : '') : 'Presenter TBA') ?></span>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endfor; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </li>
    <?php endforeach; ?>
</ol>
