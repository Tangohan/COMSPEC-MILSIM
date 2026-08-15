<?php
/** @var list<array{id:string,label:string,center:array{0:float,1:float},zoom:int,blurb:string}> $regions */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$regions = is_array($regions ?? null) ? $regions : [];
?>
<div class="jnet-theatre">
    <aside class="jnet-theatre__list" aria-label="Secteurs">
        <?php foreach ($regions as $i => $r): ?>
            <button type="button"
                    class="jnet-theatre__btn<?= $i === 0 ? ' is-active' : '' ?>"
                    data-jnet-region="<?= $h((string) ($r['id'] ?? '')) ?>">
                <strong><?= $h((string) ($r['label'] ?? '')) ?></strong>
                <span><?= $h((string) ($r['blurb'] ?? '')) ?></span>
            </button>
        <?php endforeach; ?>
    </aside>
    <div class="jnet-map">
        <div id="jnet-map-root" role="img" aria-label="Carte du théâtre"></div>
        <div class="jnet-map__hint" id="jnet-map-hint">Sélectionnez un secteur · survol / clic</div>
    </div>
</div>
<script type="application/json" id="jnet-regions-data"><?= json_encode($regions, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?></script>
