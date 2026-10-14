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
$venue = $event['venue'];
$faculty = $event['faculty'];
$about = $event['about'];
// The running band only repeats facts that are on the page anyway (decorative, hidden from screen readers).
$tickerItems = ['Data Platforms and Ecosystems', 'Data Governance', 'Feedback from senior faculty', 'Panel with the senior faculty', 'Non-archival', $ev['fee']];
$tracks = $event['tracks'];
?>
<section class="hero" aria-labelledby="title" data-hero>
    <canvas class="hero__field" data-field aria-hidden="true"></canvas>
    <div class="wrap grid hero__grid">
        <div class="hero__copy">
            <h1 class="hero__title" id="title"><?= e($ev['title']) ?></h1>
            <p class="hero__when"><span class="nowrap"><?= e($eventDay->format('l j F Y')) ?></span> · St.Gallen · <span class="nowrap"><?= e($ev['time']) ?></span></p>
            <p class="hero__statement"><?= e($ev['tagline']) ?><?php if (!empty($ev['tagline_more'])): ?> <span><?= e($ev['tagline_more']) ?></span><?php endif; ?></p>
            <div class="hero__links">
                <a class="arrowlink" href="#programme">Programme<?= icon('arrow') ?></a>
                <a class="arrowlink" href="#about">What to submit<?= icon('arrow') ?></a>
            </div>
        </div>
        <div class="hero__tile">
            <?= $view->render('partials/deadline-tile') ?>
        </div>
    </div>
</section>

<?php // The moving text stays hidden from screen readers; the pause button (WCAG 2.2.2) does not. fx/scroll.js
      // unhides the button only while the band runs, so without the script the band is static and has no button. ?>
<div class="ticker" data-ticker>
    <div class="ticker__view" aria-hidden="true">
        <div class="ticker__track">
            <?php for ($round = 0; $round < 3; $round++): ?>
                <?php foreach ($tickerItems as $item): ?><span class="ticker__item"><?= e($item) ?></span><?php endforeach; ?>
            <?php endfor; ?>
        </div>
    </div>
    <button class="ticker__toggle" type="button" aria-label="Pause animations" data-label-play="Play animations" hidden><?= icon('pause', 'icon ticker__pause') ?><?= icon('play', 'icon ticker__play') ?></button>
</div>

<section id="about" class="section" aria-labelledby="about-title">
    <div class="wrap">
        <div class="grid shead">
            <h2 id="about-title">About the workshop</h2>
            <p class="shead__fact"><?= e($ev['format']) ?> · <?= e($ev['language']) ?> · <?= e($ev['fee']) ?></p>
        </div>
        <?php if ($about !== []): ?>
            <p class="statement" data-fill><?= e($about[0]) ?></p>
        <?php endif; ?>
        <div class="grid split mt-5">
            <div class="split__main">
                <?php foreach (array_slice($about, 1) as $paragraph): ?>
                    <p class="lead"><?= e($paragraph) ?></p>
                <?php endforeach; ?>
                <h3 class="subhead mt-4">Tracks</h3>
                <dl class="index">
                    <?php foreach ($tracks as $track): ?>
                        <?php [$trackNo, $trackTopic] = array_pad(explode(': ', $track['name'], 2), 2, ''); ?>
                        <div><dt class="index__key"><?= e($trackNo) ?></dt><dd><?= e($trackTopic !== '' ? $trackTopic : $trackNo) ?><br><span class="small">Chairs: <?= e($track['chair']) ?></span></dd></div>
                    <?php endforeach; ?>
                </dl>
            </div>
            <div class="split__side">
                <h3 class="subhead">What to submit</h3>
                <ul class="index index--plain">
                    <?php foreach ($sub['what_to_submit'] as $item): ?>
                        <li><?= e($item) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</section>

<section id="dates" class="section section--band" aria-labelledby="dates-title">
    <div class="wrap">
        <div class="grid shead">
            <h2 id="dates-title">Dates</h2>
            <p class="shead__fact">All times are Zurich time.</p>
        </div>
        <ol class="dates">
            <li class="grid is-key">
                <span class="dates__date"><?= e($deadline->format('j M')) ?></span>
                <span class="dates__what">Submission deadline</span>
                <span class="dates__detail"><?= e(Dates::day($deadline)) ?>, <?= e(Dates::timeWithZone($deadline)) ?></span>
            </li>
            <li class="grid">
                <span class="dates__date"><?= e($notification->format('j M')) ?></span>
                <span class="dates__what">Notification</span>
                <span class="dates__detail"><?= e(Dates::day($notification)) ?>. We let every author know whether the paper is accepted.</span>
            </li>
            <li class="grid">
                <span class="dates__date"><?= e($eventDay->format('j M')) ?></span>
                <span class="dates__what">Workshop</span>
                <span class="dates__detail"><?= e(Dates::day($eventDay)) ?>, <?= e($ev['time']) ?> · <?= e($venue['name']) ?></span>
            </li>
        </ol>
    </div>
</section>

<section id="programme" class="section" aria-labelledby="programme-title">
    <div class="wrap">
        <div class="grid shead">
            <h2 id="programme-title">Programme</h2>
        </div>
        <?php if (!empty($event['programme_note'])): ?>
            <p class="lead"><?= e($event['programme_note']) ?></p>
        <?php endif; ?>
        <div class="mt-4">
            <?= $view->render('partials/programme') ?>
        </div>
    </div>
</section>

<section id="faculty" class="section section--deep on-deep" aria-labelledby="faculty-title" data-cut data-spot>
    <div class="wrap">
        <div class="grid shead">
            <h2 id="faculty-title">Senior faculty</h2>
            <p class="shead__fact">Feedback on every paper in a small group.</p>
        </div>
        <ul class="people">
            <?php foreach ($faculty as $person): ?>
                <li class="grid person">
                    <h3 class="person__name">
                        <?php if (!empty($person['photo'])): ?>
                            <img class="person__photo" src="<?= e($app->url('/assets/img/' . basename($person['photo']))) ?>" alt="" width="88" height="88">
                        <?php endif; ?>
                        <?= e($person['name']) ?>
                    </h3>
                    <p class="person__aff"><?= e($person['affiliation']) ?></p>
                    <?php if (($person['role'] ?? '') !== '' && $person['role'] !== 'Senior faculty'): ?>
                        <p class="person__role"><?= e($person['role']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($person['bio'])): ?><p class="person__bio"><?= e($person['bio']) ?></p><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>

<section id="venue" class="section" aria-labelledby="venue-title">
    <div class="wrap">
        <div class="grid shead">
            <h2 id="venue-title">Venue</h2>
            <p class="shead__fact">St.Gallen, Switzerland</p>
        </div>
        <div class="grid split">
            <div class="split__main">
                <p class="lead"><?= e($venue['travel']) ?></p>
                <?php if (!empty($venue['map_url'])): ?>
                    <p class="mt-3"><a class="arrowlink" href="<?= e($venue['map_url']) ?>" rel="noopener noreferrer">Open map<?= icon('external') ?></a></p>
                <?php endif; ?>
            </div>
            <dl class="split__side index">
                <div><dt class="index__key">Place</dt><dd><?= e($venue['name']) ?><br><?= e($venue['room']) ?></dd></div>
                <div><dt class="index__key">Address</dt><dd><?= e($venue['address']) ?></dd></div>
                <div><dt class="index__key">Contact</dt><dd><a href="mailto:<?= e($event['contact']['email']) ?>"><?= e($event['contact']['email']) ?></a></dd></div>
                <div><dt class="index__key">Organiser</dt><dd><?= e($event['contact']['people']) ?></dd></div>
            </dl>
        </div>
    </div>
</section>

<section id="submit" class="section section--submit on-green" aria-labelledby="submit-title" data-cut data-spot>
    <div class="wrap">
        <div class="grid shead">
            <h2 id="submit-title">Submit your paper</h2>
            <p class="shead__fact">No account needed.</p>
        </div>
        <?= $view->render('partials/submission', ['values' => [], 'errors' => [], 'compact' => true]) ?>
    </div>
</section>
