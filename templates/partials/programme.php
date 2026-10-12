<?php
/**
 * Programme in the format of the CHAIS PDW Bern: joint items, breaks and
 * blocks of parallel paper tracks (see 'programme' in config/event.php).
 *
 * @var array $event
 */

$tracks = $event['tracks'];
$range = static fn (array $item): string => ($item['end'] ?? '') !== '' ? $item['time'] . '–' . $item['end'] : $item['time'];
?>
<ol class="ws-agenda">
    <?php foreach ($event['programme'] as $item): ?>
        <?php $kind = $item['kind'] ?? 'plenary'; ?>
        <li class="ws-agenda__item ws-agenda__item--<?= e($kind) ?>">
            <div class="ws-agenda__row">
                <time class="ws-agenda__time"><?= e($range($item)) ?></time>
                <div>
                    <div class="ws-agenda__title"><?= e($item['title']) ?></div>
                    <?php if (!empty($item['detail'])): ?><div class="ws-agenda__text"><?= e($item['detail']) ?></div><?php endif; ?>
                </div>
            </div>

            <?php if ($kind === 'papers'): ?>
                <div class="rg-grid rg-grid--2 ws-tracks">
                    <?php foreach ($item['slots'] as $trackId => $slots): ?>
                        <?php $track = $tracks[$trackId]; ?>
                        <section class="ws-track" aria-label="<?= e($track['name'] . ', ' . $item['title']) ?>">
                            <header class="ws-track__head">
                                <div class="ws-track__name"><?= e($track['name']) ?></div>
                                <div class="rg-small"><?= e($track['room']) ?> · <?= e($track['chair']) ?></div>
                            </header>
                            <ol class="ws-slots">
                                <?php foreach ($slots as $slot): ?>
                                    <li class="ws-slot">
                                        <time class="rg-label"><?= e($slot['time'] . '–' . $slot['end']) ?></time>
                                        <?php if ($slot['title'] !== ''): ?>
                                            <div class="ws-slot__title"><?= e($slot['title']) ?></div>
                                        <?php else: ?>
                                            <div class="ws-slot__title ws-tba">Paper title TBA</div>
                                        <?php endif; ?>
                                        <?php if ($slot['presenter'] !== ''): ?>
                                            <div class="rg-small"><?= e($slot['presenter']) ?><?= $slot['affiliation'] !== '' ? ' (' . e($slot['affiliation']) . ')' : '' ?></div>
                                        <?php else: ?>
                                            <div class="rg-small ws-tba">Presenter TBA</div>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        </section>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ol>
