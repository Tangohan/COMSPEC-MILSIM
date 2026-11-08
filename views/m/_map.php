<?php

declare(strict_types=1);
/**
 * Carte Overwatch du téléphone (/m/carte, /m/briefs) : même carte et mêmes calques que l'Overwatch, barre d'outils
 * flottante en bas (recentrer, calques, zoom, plein écran) qui laisse la carte visible.
 *
 * @var bool|null $mapCanAdd bouton « + Marqueur » (carte)
 * @var string|null $mapMeLabel libellé du bouton de recentrage
 * @var bool|null $mapInset carte en encart (briefs air)
 */
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$layers = [
    'units' => 'Amis',
    'markers' => 'Marqueurs',
    'shapes' => 'Tracés et zones',
    'air' => 'Aéronefs',
    'jtac' => 'Appuis 9-LINE / 5-LINE',
];
?>
    <section class="mm-mapwrap<?= !empty($mapInset) ? ' mm-mapwrap--inset' : '' ?>" data-mapwrap>
      <div class="mm-map" id="mm-map" aria-label="Carte"></div>
      <div class="mm-mapchips">
        <div class="mm-mapchip" data-my-grid><span>Position en attente du jeu</span></div>
        <div class="mm-mapchip mm-mapchip--warn" data-map-net hidden></div>
      </div>
      <div class="mm-mapbar" data-map-hint hidden>Touchez la carte pour placer le marqueur <button type="button" class="mm-btn" data-map-cancel>ANNULER</button></div>
      <div class="mm-layers" data-layers-panel hidden>
        <strong>Calques</strong>
        <?php foreach ($layers as $key => $label): ?>
          <label class="mm-layer"><input type="checkbox" data-layer="<?= $h($key) ?>" checked> <span><?= $h($label) ?></span></label>
        <?php endforeach; ?>
      </div>
      <nav class="mm-maptools" aria-label="Outils de la carte">
        <button type="button" class="mm-tool" data-map-me><?= $h($mapMeLabel ?? 'Moi') ?></button>
        <button type="button" class="mm-tool" data-map-layers aria-expanded="false">Calques</button>
        <?php if (!empty($mapCanAdd)): ?><button type="button" class="mm-tool mm-tool--add" data-map-add>+ Marqueur</button><?php endif; ?>
        <span class="mm-tool__zoom">
          <button type="button" class="mm-tool" data-map-zoom="-1" aria-label="Dézoomer">−</button>
          <button type="button" class="mm-tool" data-map-zoom="1" aria-label="Zoomer">+</button>
        </span>
        <button type="button" class="mm-tool mm-tool--full" data-map-full aria-pressed="false" aria-label="Plein écran" title="Plein écran">⤢</button>
      </nav>
    </section>
