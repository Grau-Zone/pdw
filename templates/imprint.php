<?php
/**
 * @var array $event
 */

$i = $event['imprint'];
?>
<section class="rg-section">
    <div class="rg-container ws-narrow ws-prose">
        <div class="rg-eyebrow">Legal</div>
        <h1 class="rg-h2 rg-mt-1">Imprint</h1>
        <div class="rg-grid rg-grid--2 rg-mt-3">
            <div class="rg-card">
                <div class="rg-label">Publisher</div>
                <p class="rg-mt-1"><?= e($i['publisher']) ?><br><?= e($i['address']) ?></p>
            </div>
            <div class="rg-card">
                <div class="rg-label">Responsible for content</div>
                <p class="rg-mt-1"><?= e($i['responsible']) ?><br><a href="mailto:<?= e($i['email']) ?>"><?= e($i['email']) ?></a></p>
            </div>
        </div>
    </div>
</section>
