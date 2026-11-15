<?php
declare(strict_types=1);
/**
 * Aperçu d'un papier du registre : une image par face, l'image exacte du téléchargement (SsePaperRenderer). Avec JS, des
 * onglets ne montrent qu'une face ; sans JS, toutes les faces se suivent. À l'impression : toutes les faces à l'échelle réelle.
 *
 * @var array<string, mixed> $dl SsePaperDownloadService::plan() + base
 * @var array<string, mixed> $doc
 * @var \Closure $h échappement HTML
 */
$pvFaces = (array) $dl['faces'];
[$pvW, $pvH] = $dl['size_mm'];
$pvRatio = number_format($pvW / $pvH, 5, '.', '');
$pvMm = number_format((float) $pvW, 1, ',', '') . ' × ' . number_format((float) $pvH, 1, ',', '') . ' mm';
$pvFirst = (string) array_key_first($pvFaces);
?>
<section class="panel pdl-preview" data-pdl-preview>
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">01</span> Aperçu</div>
        <div class="panel-meta"><?= count($pvFaces) ?> face(s) · <?= $h($pvMm) ?></div>
    </div>
    <div class="panel-body">
        <p class="muted pdl-desc">Chaque face ci-dessous est l’image qui sera téléchargée, imprimée ou mise en PDF.</p>
        <?php if (count($pvFaces) > 1): ?>
            <div class="pdl-tabs" role="tablist" aria-label="Faces du papier" hidden>
                <?php foreach ($pvFaces as $key => $label): ?>
                    <button type="button" role="tab" class="pdl-tab" id="pdl-tab-<?= $h($key) ?>" aria-controls="pdl-face-<?= $h($key) ?>" aria-selected="<?= $key === $pvFirst ? 'true' : 'false' ?>" tabindex="<?= $key === $pvFirst ? '0' : '-1' ?>" data-pdl-tab="<?= $h($key) ?>"><?= $h($label) ?></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="pdl-stage">
            <?php foreach ($pvFaces as $key => $label): ?>
                <figure class="pdl-face" id="pdl-face-<?= $h($key) ?>" data-pdl-face="<?= $h($key) ?>" role="tabpanel" aria-label="<?= $h($label) ?>" style="--pdl-w: <?= $h(number_format((float) $pvW, 1, '.', '')) ?>mm;">
                    <a class="pdl-zoom" href="<?= $h(url($dl['base'] . '/face/' . rawurlencode((string) $key))) ?>" target="_blank" rel="noopener" aria-label="Ouvrir cette face en grand">
                        <img src="<?= $h(url($dl['base'] . '/face/' . rawurlencode((string) $key))) ?>" alt="<?= $h($label) ?>" width="<?= (int) round($pvW * 10) ?>" height="<?= (int) round($pvH * 10) ?>" decoding="async">
                    </a>
                    <figcaption><?= $h($label) ?></figcaption>
                </figure>
            <?php endforeach; ?>
        </div>
        <?php foreach ((array) $dl['warnings'] as $w): ?>
            <p class="pdl-warn"><?= $h($w) ?></p>
        <?php endforeach; ?>
    </div>
</section>
