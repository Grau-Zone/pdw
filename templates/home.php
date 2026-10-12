<?php
/**
 * @var Workshop\App $app
 * @var Workshop\View $view
 * @var array $event
 */

use Workshop\Dates;

$ev = $event['event'];
$sub = $event['submission'];
$gate = $app->gate();
$deadline = $gate->deadline();
$eventDay = Dates::parse($ev['date'], $app->tz);
$notification = Dates::parse($sub['notification_date'], $app->tz);
$maxMb = rtrim(rtrim(number_format((float) $sub['max_file_mb'], 1), '0'), '.');

// Title with one highlighted part; both pieces are escaped before they are combined.
$titleHtml = e($ev['title']);
$highlight = e((string) ($ev['title_highlight'] ?? ''));
if ($highlight !== '' && ($pos = strpos($titleHtml, $highlight)) !== false) {
    $titleHtml = substr_replace($titleHtml, '<span class="rg-hl">' . $highlight . '</span>', $pos, strlen($highlight));
}

$initials = static function (string $name): string {
    $parts = array_values(array_filter(preg_split('/\s+/u', (string) preg_replace('/\b(Prof|Dr|PD)\.?\s*/u', '', $name)) ?: []));
    $first = mb_substr($parts[0] ?? '', 0, 1);
    $last = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';

    return mb_strtoupper($first . $last);
};
?>
<section class="rg-section ws-hero">
    <div class="rg-container">
        <div class="rg-eyebrow rg-fade-up"><span class="rg-dot" aria-hidden="true"></span><?= e(Dates::day($eventDay)) ?> · St.Gallen</div>
        <h1 class="rg-h1 rg-mt-2 rg-fade-up rg-fade-up--2"><?= $titleHtml ?></h1>
        <p class="rg-lead rg-mt-3 rg-fade-up rg-fade-up--2"><?= e($ev['tagline']) ?></p>
        <div class="rg-actions rg-mt-4 rg-fade-up rg-fade-up--3">
            <a class="rg-btn rg-btn--primary" href="#submit">Submit your paper →</a>
            <a class="rg-btn rg-btn--ghost" href="#programme">View programme</a>
        </div>
        <div class="rg-stats rg-mt-4 rg-fade-up rg-fade-up--3">
            <div><div class="rg-stat__value"><?= e($eventDay->format('j.n.')) ?></div><div class="rg-stat__label">Seminar · <?= e($ev['time']) ?></div></div>
            <div><div class="rg-stat__value"><?= e($deadline->format('j.n.')) ?></div><div class="rg-stat__label">Deadline · <?= e($deadline->format('H:i T')) ?></div></div>
            <div><div class="rg-stat__value"><?= e($maxMb) ?> MB</div><div class="rg-stat__label">PDF, max.</div></div>
            <div><div class="rg-stat__value"><?= e($ev['format']) ?></div><div class="rg-stat__label"><?= e($ev['language']) ?> · <?= e($ev['fee']) ?></div></div>
        </div>
    </div>
</section>

<section id="about" class="rg-section">
    <div class="rg-container rg-grid rg-grid--2 ws-split">
        <div>
            <div class="rg-eyebrow">About</div>
            <h2 class="rg-h2 rg-mt-1">About the <span class="rg-hl">seminar</span></h2>
            <?php foreach ($event['about'] as $paragraph): ?>
                <p class="rg-lead rg-mt-2"><?= e($paragraph) ?></p>
            <?php endforeach; ?>
        </div>
        <div class="rg-card rg-card--accent">
            <div class="rg-label">What to submit</div>
            <ul class="ws-list rg-mt-2">
                <?php foreach ($sub['what_to_submit'] as $item): ?>
                    <li><?= e($item) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</section>

<section id="dates" class="rg-section">
    <div class="rg-container">
        <div class="rg-eyebrow">Important dates</div>
        <h2 class="rg-h2 rg-mt-1">Three dates <span class="rg-hl">to remember</span></h2>
        <div class="rg-grid rg-grid--3 rg-mt-3">
            <article class="rg-card rg-card--accent">
                <div class="rg-label">Submission deadline</div>
                <h3 class="rg-card__title rg-mt-1"><?= e(Dates::day($deadline)) ?></h3>
                <p class="rg-card__lead"><?= e(Dates::timeWithZone($deadline)) ?></p>
            </article>
            <article class="rg-card">
                <div class="rg-label">Notification</div>
                <h3 class="rg-card__title rg-mt-1"><?= e(Dates::day($notification)) ?></h3>
                <p class="rg-card__lead">We let every author know whether the paper is accepted.</p>
            </article>
            <article class="rg-card">
                <div class="rg-label">Seminar</div>
                <h3 class="rg-card__title rg-mt-1"><?= e(Dates::day($eventDay)) ?></h3>
                <p class="rg-card__lead"><?= e($ev['time']) ?> · <?= e($event['venue']['name']) ?></p>
            </article>
        </div>
    </div>
</section>

<section id="how" class="rg-section">
    <div class="rg-container rg-grid rg-grid--2 ws-split">
        <div>
            <div class="rg-eyebrow">How it works</div>
            <h2 class="rg-h2 rg-mt-1">Four steps to <span class="rg-hl">take part</span></h2>
            <p class="rg-lead rg-mt-2">You submit directly on this page – no account needed. Until the deadline you can replace your paper as often as you like.</p>
        </div>
        <ol class="rg-steps">
            <li><div class="rg-steps__title">Prepare your paper</div><div class="rg-steps__text">One PDF file, max. <?= e($maxMb) ?> MB</div></li>
            <li><div class="rg-steps__title">Fill in the form</div><div class="rg-steps__text">Name, e-mail, affiliation, title and abstract</div></li>
            <li><div class="rg-steps__title">Submit before the deadline</div><div class="rg-steps__text"><?= e($deadline->format('j F')) ?>, <?= e(Dates::timeWithZone($deadline)) ?></div></li>
            <li><div class="rg-steps__title">Get your confirmation</div><div class="rg-steps__text">On screen and by e-mail, with your submission ID</div></li>
        </ol>
    </div>
</section>

<section id="programme" class="rg-section">
    <div class="rg-container">
        <div class="rg-eyebrow">Programme</div>
        <h2 class="rg-h2 rg-mt-1">The day at a <span class="rg-hl">glance</span></h2>
        <?php if (!empty($event['programme_note'])): ?>
            <p class="rg-lead rg-mt-2"><?= e($event['programme_note']) ?></p>
        <?php endif; ?>
        <div class="rg-card rg-mt-3">
            <?= $view->render('partials/programme') ?>
        </div>
    </div>
</section>

<section id="faculty" class="rg-section">
    <div class="rg-container">
        <div class="rg-eyebrow">Faculty</div>
        <h2 class="rg-h2 rg-mt-1">Who gives <span class="rg-hl">feedback</span></h2>
        <div class="rg-grid rg-grid--3 rg-mt-3">
            <?php foreach ($event['faculty'] as $person): ?>
                <article class="rg-card ws-person">
                    <?php if (!empty($person['photo'])): ?>
                        <img class="ws-avatar" src="<?= e($app->url('/assets/img/' . basename($person['photo']))) ?>" alt="" width="56" height="56">
                    <?php else: ?>
                        <span class="ws-avatar" aria-hidden="true"><?= e($initials($person['name'])) ?></span>
                    <?php endif; ?>
                    <div>
                        <div class="rg-label"><?= e($person['role']) ?></div>
                        <h3 class="rg-card__title rg-mt-1"><?= e($person['name']) ?></h3>
                        <p class="rg-card__lead"><?= e($person['affiliation']) ?></p>
                        <?php if (!empty($person['bio'])): ?><p class="rg-small"><?= e($person['bio']) ?></p><?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section id="venue" class="rg-section">
    <div class="rg-container rg-grid rg-grid--2 ws-split">
        <div>
            <div class="rg-eyebrow">Venue</div>
            <h2 class="rg-h2 rg-mt-1">St.Gallen, <span class="rg-hl">Switzerland</span></h2>
            <p class="rg-lead rg-mt-2"><?= e($event['venue']['travel']) ?></p>
        </div>
        <div class="rg-card">
            <div class="rg-label">Address</div>
            <h3 class="rg-card__title rg-mt-1"><?= e($event['venue']['name']) ?></h3>
            <p class="rg-card__lead"><?= e($event['venue']['room']) ?><br><?= e($event['venue']['address']) ?></p>
            <?php if (!empty($event['venue']['map_url'])): ?>
                <a class="rg-btn rg-btn--ghost ws-btn-small" href="<?= e($event['venue']['map_url']) ?>" rel="noopener noreferrer">Open map</a>
            <?php endif; ?>
            <hr class="rg-divider">
            <div class="rg-label">Contact</div>
            <p class="rg-mt-1"><a href="mailto:<?= e($event['contact']['email']) ?>"><?= e($event['contact']['email']) ?></a></p>
            <p class="rg-small"><?= e($event['contact']['people']) ?></p>
        </div>
    </div>
</section>

<section id="submit" class="rg-section">
    <div class="rg-container">
        <div class="rg-glass rg-card ws-formcard">
            <div class="rg-eyebrow">Call for papers</div>
            <h2 class="rg-h2 rg-mt-1">Submit your <span class="rg-hl">paper</span></h2>
            <div class="rg-mt-3">
                <?= $view->render('partials/submission', ['values' => [], 'errors' => []]) ?>
            </div>
        </div>
    </div>
</section>
