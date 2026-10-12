<?php
/**
 * @var array $event
 */

$i = $event['imprint'];
?>
<section class="page">
    <div class="wrap narrow">
        <h1 class="page__title">Imprint</h1>
        <dl class="index mt-4">
            <div><dt class="index__key">Publisher</dt><dd><?= e($i['publisher']) ?><br><?= e($i['address']) ?></dd></div>
            <div><dt class="index__key">Responsible</dt><dd><?= e($i['responsible']) ?><br><a href="mailto:<?= e($i['email']) ?>"><?= e($i['email']) ?></a></dd></div>
        </dl>
    </div>
</section>
