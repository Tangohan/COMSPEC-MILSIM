<?php
declare(strict_types=1);
/**
 * Bouton « Télécharger » d'un papier du registre (pièce complète) : PDF, images PNG (ZIP ou une face) et impression.
 * Posé dans la barre d'actions de l'en-tête, à côté de « Liaisons ». Détail ouvert/fermé natif (<details>) : fonctionne sans JS,
 * l'impression seule passe par sse-papier-telecharger.js.
 *
 * @var array<string, mixed> $dl SsePaperDownloadService::plan() + base
 * @var \Closure $h échappement HTML
 */
$pdlBase = 'atak/sse/papiers/documents/' . (int) $doc['id'] . '/telecharger/';
$pdlFaces = (array) $dl['faces'];
$pdlN = count($pdlFaces);
$pdlMm = number_format((float) $dl['size_mm'][0], 1, ',', '') . ' × ' . number_format((float) $dl['size_mm'][1], 1, ',', '') . ' mm';
$pdlPx = (int) $dl['size_px'][0] . ' × ' . (int) $dl['size_px'][1] . ' px';
$pdlFacesText = implode(' · ', array_values($pdlFaces));
?>
<details class="pdl-menu" data-pdl-menu>
    <summary class="pdl-menu__btn" aria-haspopup="true">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 4v11"/><path d="M7 11l5 5 5-5"/><path d="M5 20h14"/></svg>
        <span>Télécharger</span>
    </summary>
    <div class="pdl-menu__pop" role="group" aria-label="Télécharger ce papier">
        <p class="pdl-menu__lead"><?= $h($pdlFacesText) ?> · <?= $h($pdlMm) ?> · <?= $h($pdlPx) ?></p>
        <a class="pdl-item" href="<?= $h(url($pdlBase . 'pdf')) ?>">
            <b>Pièce complète (PDF)</b>
            <span>A4, toutes les faces à l’échelle réelle</span>
        </a>
        <a class="pdl-item" href="<?= $h(url($pdlBase . 'zip')) ?>">
            <b>Images (ZIP)</b>
            <span>Un PNG par face, 300 dpi</span>
        </a>
        <div class="pdl-item pdl-item--faces">
            <b>Une seule image (PNG)</b>
            <span class="pdl-faces">
                <?php foreach ($pdlFaces as $key => $label): ?>
                    <a class="pdl-chip" href="<?= $h(url($pdlBase . 'png?face=' . rawurlencode((string) $key))) ?>"><?= $h($label) ?></a>
                <?php endforeach; ?>
            </span>
        </div>
        <button type="button" class="pdl-item" data-pdl-print>
            <b>Imprimer</b>
            <span>Toutes les faces à l’échelle réelle</span>
        </button>
        <?php foreach ((array) $dl['warnings'] as $w): ?>
            <p class="pdl-menu__warn"><?= $h($w) ?></p>
        <?php endforeach; ?>
    </div>
</details>
