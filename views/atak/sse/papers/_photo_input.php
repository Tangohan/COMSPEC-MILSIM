<?php
declare(strict_types=1);
/**
 * Champs « photo du titulaire » (atelier, fiche identité, spécimen d'un papier) : envoi d'une image avec recadrage
 * dans le navigateur (35 × 45 ou carré, sse-id-images.js), reprise d'une photo de la personne SSE, retrait.
 * Le site recadre aussi au centre et retire les métadonnées (SseIdImageService).
 *
 * @var string $photoDis attribut « disabled » ou ''
 * @var list<array{id:int,url:string,label:string}> $personPhotos
 * @var bool $hasPhoto
 */
$h = $h ?? static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$photoDis = $photoDis ?? '';
$personPhotos = $personPhotos ?? [];
$photoMaxMb = \App\Services\Sse\SseIdImageService::maxMb('photo');
?>
<div class="idi-photo-field">
    <label for="idi-photo">Photo du titulaire (PNG, JPG ou WebP, <?= (int) $photoMaxMb ?> Mo maximum)</label>
    <input id="idi-photo" type="file" name="photo" accept="image/png,image/jpeg,image/webp" data-crop data-bind-photo<?= $photoDis ?>>
    <div class="idi-crop" data-crop-panel hidden>
        <canvas width="280" height="360" data-crop-canvas aria-label="Recadrage de la photo"></canvas>
        <div class="idi-crop__tools">
            <label>Format
                <select data-crop-ratio>
                    <option value="35x45">Photo d’identité 35 × 45</option>
                    <option value="1x1">Carré</option>
                </select>
            </label>
            <label>Zoom <input type="range" min="1" max="4" step="0.01" value="1" data-crop-zoom></label>
            <p class="idw-note">Faites glisser l’image pour placer le visage au centre, haut des épaules visible.</p>
            <button type="button" class="btn btn--ghost" data-crop-apply>Appliquer le recadrage</button>
            <span class="muted" data-crop-done hidden>Recadrage appliqué.</span>
        </div>
    </div>
    <?php if ($personPhotos !== []): ?>
        <fieldset class="idi-pick">
            <legend>Ou reprendre une photo de la personne SSE</legend>
            <label class="idi-pick__item"><input type="radio" name="photo_existing" value="0" checked<?= $photoDis ?>> <span>Aucune</span></label>
            <?php foreach ($personPhotos as $p): ?>
                <label class="idi-pick__item">
                    <input type="radio" name="photo_existing" value="<?= (int) $p['id'] ?>"<?= $photoDis ?>>
                    <img src="<?= $h($p['url']) ?>" alt="" loading="lazy"><span translate="no"><?= $h($p['label']) ?></span>
                </label>
            <?php endforeach; ?>
        </fieldset>
    <?php endif; ?>
    <?php if (!empty($hasPhoto)): ?><label class="idw-check"><input type="checkbox" name="photo_remove" value="1"<?= $photoDis ?>> Retirer la photo (silhouette)</label><?php endif; ?>
</div>
