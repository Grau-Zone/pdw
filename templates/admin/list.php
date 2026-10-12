<?php
/**
 * @var Workshop\App $app
 * @var Workshop\View $view
 * @var array $rows
 * @var string $user
 * @var string $csrf
 */

use Workshop\DeadlineGate;
use Workshop\Dates;
use Workshop\Text;

$gate = $app->gate();
$now = $app->now();
$state = $gate->state($now);
$inGrace = $state === DeadlineGate::CLOSED && $gate->accepts($now);
$latestCount = count(array_filter($rows, static fn (array $r): bool => (bool) $r['is_latest']));
$stateLabel = [
    DeadlineGate::OPEN => 'open',
    DeadlineGate::CLOSED => 'closed',
    DeadlineGate::NOT_OPEN => 'not open yet',
    DeadlineGate::DISABLED => 'switched off',
][$state];
// mailto: needs URL encoding so that "?" or "&" in an address cannot add Bcc/Subject fields.
$mailto = static fn (string $email): string => 'mailto:' . str_replace('%40', '@', rawurlencode($email));
?>
<section class="rg-section">
    <div class="rg-container">
        <div class="ws-admin-bar">
            <div>
                <div class="rg-eyebrow">Organisers</div>
                <h1 class="rg-h2 rg-mt-1">Submissions</h1>
            </div>
            <form method="post" action="<?= e($app->url('/admin/')) ?>">
                <input type="hidden" name="action" value="logout">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <span class="rg-small">Logged in as <?= e($user) ?></span>
                <button class="rg-btn rg-btn--ghost ws-btn-small" type="submit">Log out</button>
            </form>
        </div>

        <div class="rg-stats rg-mt-3">
            <div><div class="rg-stat__value"><?= $latestCount ?></div><div class="rg-stat__label">papers</div></div>
            <div><div class="rg-stat__value"><?= count($rows) ?></div><div class="rg-stat__label">uploads incl. earlier versions</div></div>
            <div><div class="rg-stat__value"><?= e($stateLabel) ?></div><div class="rg-stat__label">deadline <?= e(Dates::withZone($gate->deadline())) ?></div></div>
        </div>

        <?php if ($inGrace): ?>
            <div class="rg-note rg-note--warn rg-mt-3">
                <?= $view->render('partials/icon', ['name' => 'warn']) ?>
                <p>Closed for visitors, but late uploads are still accepted until <?= e($gate->deadline()->modify('+' . (int) $app->event['submission']['grace_minutes'] . ' minutes')->format('H:i:s')) ?>. Export only after that, otherwise late versions are missing.</p>
            </div>
        <?php endif; ?>

        <div class="rg-actions rg-mt-3">
            <a class="rg-btn rg-btn--primary ws-btn-small" href="<?= e($app->url('/admin/export.php?format=zip')) ?>">Download all papers (ZIP, latest versions)</a>
            <a class="rg-btn rg-btn--ghost ws-btn-small" href="<?= e($app->url('/admin/export.php?format=csv')) ?>">Export list (CSV)</a>
        </div>
        <p class="rg-small rg-mt-2">Confidential: do not forward this data beyond the workshop faculty, and do not upload papers to AI tools.</p>

        <?php if ($rows === []): ?>
            <div class="rg-note rg-note--ok rg-mt-3">
                <?= $view->render('partials/icon', ['name' => 'ok']) ?>
                <p>No submissions yet.</p>
            </div>
        <?php else: ?>
            <div class="rg-card rg-mt-3">
                <div class="rg-table-wrap">
                    <table class="rg-table">
                        <thead>
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">Received</th>
                            <th scope="col">Name</th>
                            <th scope="col">Affiliation</th>
                            <th scope="col">Title</th>
                            <th scope="col">Programme</th>
                            <th scope="col">Paper</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr<?= $r['is_latest'] ? '' : ' class="ws-superseded"' ?>>
                                <td>
                                    <code><?= e($r['public_id']) ?></code>
                                    <?php if (!$r['is_latest']): ?><br><span class="rg-tag">replaced</span>
                                    <?php elseif ((int) $r['version'] > 1): ?><br><span class="rg-tag">v<?= (int) $r['version'] ?></span><?php endif; ?>
                                    <?php if ((int) $r['version'] > 1 && $r['name'] !== $r['first_name']): ?><br><span class="rg-tag ws-tag-warn" title="Version 1 was submitted as: <?= e($r['first_name']) ?>">name differs from v1</span><?php endif; ?>
                                </td>
                                <td><?= e((new DateTimeImmutable('@' . $r['created_at']))->setTimezone($app->tz)->format('d.m.Y H:i')) ?></td>
                                <td>
                                    <?= e($r['name']) ?><br><a href="<?= e($mailto($r['email'])) ?>"><?= e($r['email']) ?></a>
                                    <?php if (!$r['mail_sent']): ?><br><span class="rg-tag ws-tag-warn">no confirmation sent</span><?php endif; ?>
                                </td>
                                <td><?= e($r['affiliation']) ?></td>
                                <td>
                                    <strong><?= e($r['title']) ?></strong>
                                    <?php if ($r['coauthors'] !== ''): ?><br><span class="rg-small">with <?= e($r['coauthors']) ?></span><?php endif; ?>
                                    <?php if ($r['keywords'] !== ''): ?><br><span class="rg-small"><?= e($r['keywords']) ?></span><?php endif; ?>
                                    <?php if ($r['abstract'] !== '' || $r['feedback'] !== ''): ?>
                                        <details>
                                            <summary>Abstract and feedback wishes</summary>
                                            <?php if ($r['abstract'] !== ''): ?><p class="ws-pre"><?= e($r['abstract']) ?></p><?php endif; ?>
                                            <?php if ($r['feedback'] !== ''): ?><p class="ws-pre"><em>Feedback wanted:</em> <?= e($r['feedback']) ?></p><?php endif; ?>
                                        </details>
                                    <?php endif; ?>
                                </td>
                                <td><?= $r['consent_programme'] ? 'yes' : 'no' ?></td>
                                <td><a href="<?= e($app->url('/admin/download.php?id=' . rawurlencode($r['public_id']))) ?>">PDF</a><br><span class="rg-small"><?= e(Text::fileSize((int) $r['file_size'])) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <p class="rg-small rg-mt-3">The server sees your network address as <code><?= e($app->clientIp()) ?></code>. If this is not your own address (e.g. always the same internal one), tell the developer – the spam limits rely on it.</p>
    </div>
</section>
