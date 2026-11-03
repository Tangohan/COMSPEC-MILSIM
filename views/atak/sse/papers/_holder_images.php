<?php
declare(strict_types=1);
/**
 * Panneau « Photo et signature du titulaire » (fiche identité, spécimen d'un papier) : images actuelles et, avec les
 * droits de l'atelier, envoi (photo recadrée, photo de la personne SSE, signature) et retrait. Jamais en invité.
 *
 * @var array<string,mixed> $identity
 * @var list<array{id:int,url:string,label:string}> $personPhotos
 * @var bool $canEditImages
 * @var int $imagesDocId papier d'où vient la demande (retour), 0 sinon
 */
$h = $h ?? static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$imgPhoto = ($identity['photo_path'] ?? null) !== null ? (string) url((string) $identity['photo_path']) : null;
$imgSign = ($identity['signature_path'] ?? null) !== null ? (string) url((string) $identity['signature_path']) : null;
$imagesDocId = (int) ($imagesDocId ?? 0);
$sseExtraStyles = ($sseExtraStyles ?? '') . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-id-workshop.css')) . '">';
$sseExtraScripts = ($sseExtraScripts ?? '') . '<script src="' . $h(asset_url('assets/js/sse-id-images.js')) . '"></script>';
?>
<section class="panel" style="margin-top:10px" id="images">
    <div class="panel-header"><div class="panel-title"><span class="panel-index">IMG</span> Photo et signature du titulaire</div><div class="panel-meta">Affichées sur chaque papier, sous le filigrane SIMULATION</div></div>
    <div class="panel-body">
        <div class="idi-current">
            <figure class="idi-current__photo">
                <?php if ($imgPhoto !== null): ?><img src="<?= $h($imgPhoto) ?>" alt="Photo du titulaire"><?php else: ?><span class="muted">Pas de photo : silhouette imprimée</span><?php endif; ?>
            </figure>
            <figure class="idi-current__sign">
                <?php if ($imgSign !== null): ?><img src="<?= $h($imgSign) ?>" alt="Signature du titulaire"><?php else: ?><span class="muted">Pas de signature importée : signature écrite</span><?php endif; ?>
            </figure>
        </div>
        <?php if (!empty($canEditImages)): ?>
            <form method="post" enctype="multipart/form-data" action="<?= $h(url('atak/sse/papiers/identites/' . (int) $identity['id'] . '/images')) ?>" class="idi-form">
                <?= \App\Core\Csrf::field() ?>
                <?php if ($imagesDocId > 0): ?><input type="hidden" name="document_id" value="<?= $imagesDocId ?>"><?php endif; ?>
                <?php $photoDis = ''; $hasPhoto = $imgPhoto !== null; require __DIR__ . '/_photo_input.php'; ?>
                <div>
                    <label for="idi-signature">Signature (image PNG, JPG ou WebP, fond transparent conseillé, <?= (int) \App\Services\Sse\SseIdImageService::maxMb('signature') ?> Mo maximum)</label>
                    <input id="idi-signature" type="file" name="signature" accept="image/png,image/jpeg,image/webp">
                    <?php if ($imgSign !== null): ?><label class="idw-check"><input type="checkbox" name="signature_remove" value="1"> Retirer la signature importée</label><?php endif; ?>
                </div>
                <div class="idp-actions"><button class="btn" type="submit">Enregistrer les images</button></div>
            </form>
            <?php require __DIR__ . '/_photo_guide.php'; ?>
        <?php endif; ?>
    </div>
</section>
