<?php
declare(strict_types=1);

/**
 * Fiche d’un média — charte ATHENA.
 *
 * L’en-tête de page est rendu par la coque back-office.
 *
 * Exception assumée : l’atelier de floutage conserve ses classes `bo-media__blur-*` et ses
 * identifiants. Le script `community-media-blur.js` les cible, et leur mise en forme est de
 * la géométrie fonctionnelle (superposition exacte du canevas sur l’image), pas de la
 * décoration : la reprendre en `ath-*` casserait le tracé des zones sans rien apporter.
 *
 * @var array<string,mixed> $mediaItem
 * @var list<array<string,mixed>> $mediaCollections
 * @var array<string,string> $kindLabels
 * @var array<string,string> $statusLabels
 * @var array<string,string> $blurModeLabels
 * @var list<array<string,mixed>> $blurRegions
 * @var bool $canUpload
 * @var bool $canPublish
 * @var string|null $publicMediaUrl
 * @var string|null $embedUrl
 */

use App\Core\Csrf;
use App\Support\CommunityMediaDetails;

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$item = is_array($mediaItem ?? null) ? $mediaItem : [];
$mediaCollections = is_array($mediaCollections ?? null) ? $mediaCollections : [];
$kindLabels = is_array($kindLabels ?? null) ? $kindLabels : CommunityMediaDetails::kindLabels();
$statusLabels = is_array($statusLabels ?? null) ? $statusLabels : CommunityMediaDetails::statusLabels();
$blurModeLabels = is_array($blurModeLabels ?? null) ? $blurModeLabels : CommunityMediaDetails::blurModeLabels();
$blurRegions = is_array($blurRegions ?? null) ? $blurRegions : [];
$canUpload = !empty($canUpload);
$canPublish = !empty($canPublish);
$publicMediaUrl = $publicMediaUrl ?? null;
$embedUrl = $embedUrl ?? null;
$flashSuccess = \App\Core\Session::getFlash('success');
$flashError = \App\Core\Session::getFlash('error');
$csrf = Csrf::token();

$id = (int) ($item['id'] ?? 0);
$kind = (string) ($item['media_kind'] ?? 'image');
$status = (string) ($item['status'] ?? 'draft');
$title = trim((string) ($item['title'] ?? ''));

$regionsJson = json_encode($blurRegions, JSON_UNESCAPED_UNICODE);
if ($regionsJson === false) {
    $regionsJson = '[]';
}

$statusTone = match ($status) {
    CommunityMediaDetails::STATUS_PUBLISHED => 'ath-tag--ok',
    CommunityMediaDetails::STATUS_ARCHIVED => 'ath-tag--neut',
    default => 'ath-tag--warn',
};
?>
<?php if ($flashError): ?>
<p class="ath-flash ath-flash--err" role="alert"><?= $h((string) $flashError) ?></p>
<?php endif; ?>
<?php if ($flashSuccess): ?>
<p class="ath-flash ath-flash--ok" role="status"><?= $h((string) $flashSuccess) ?></p>
<?php endif; ?>

<div class="ath-item ath-rise" style="margin-bottom:16px;">
    <div class="ath-item__head">
        <div style="min-width:0;">
            <p class="ath-item__name"><?= $h($title !== '' ? $title : 'Média sans titre') ?></p>
            <p class="ath-item__meta"><?= $h($kindLabels[$kind] ?? 'Média') ?></p>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:6px;">
            <span class="ath-tag <?= $statusTone ?>"><?= $h($statusLabels[$status] ?? 'Brouillon') ?></span>
            <?php if (!empty($item['show_on_public_page'])): ?>
            <span class="ath-tag ath-tag--ok">Page publique</span>
            <?php endif; ?>
            <?php if (!empty($item['is_hero'])): ?>
            <span class="ath-tag ath-tag--warn">Mis en avant</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="ath-item__actions">
        <a href="<?= $h(url('back-office/media')) ?>" class="ath-btn">Retour à la bibliothèque</a>
        <a href="<?= $h(url('back-office/community/presentation')) ?>" class="ath-btn">Vitrine publique</a>
    </div>
</div>

<div class="ath-columns">
    <div class="ath-card" style="padding:14px 16px;">
        <p class="ath-field__label" style="margin-bottom:9px;">Aperçu</p>
        <?php if ($kind === CommunityMediaDetails::KIND_IMAGE && $publicMediaUrl): ?>
        <div class="bo-media__blur-stage" id="bo-media-blur-stage">
            <img src="<?= $h((string) $publicMediaUrl) ?>" alt="Aperçu du média" id="bo-media-preview-img" class="bo-media__preview-img" data-img-fallback="media" data-img-label="Aperçu du média indisponible">
            <canvas id="bo-media-blur-canvas" class="bo-media__blur-canvas" aria-hidden="true"></canvas>
        </div>
        <p class="ath-field__help" style="margin-top:9px;">
            Cliquez-glissez sur l’image pour ajouter une zone de flou. Double-cliquez sur une zone pour la retirer.
        </p>
        <div class="ath-form__actions" style="border-top:0;padding-top:9px;">
            <button type="button" class="ath-btn" id="bo-media-clear-blur">Effacer les zones</button>
            <button type="button" class="ath-btn" id="bo-media-detect-faces">Détecter les visages</button>
        </div>
        <p class="ath-field__help" id="bo-media-face-hint">
            La détection de visage utilise l’outil du navigateur lorsqu’il est disponible. Sinon, dessinez les zones à la main.
        </p>
        <?php elseif ($kind === CommunityMediaDetails::KIND_SHORT_VIDEO && $publicMediaUrl): ?>
        <video src="<?= $h((string) $publicMediaUrl) ?>" controls playsinline style="width:100%;display:block;background:#000;"></video>
        <?php elseif ($kind === CommunityMediaDetails::KIND_LONG_VIDEO && $embedUrl): ?>
        <div style="position:relative;padding-top:56.25%;">
            <iframe src="<?= $h((string) $embedUrl) ?>" title="Aperçu vidéo" allowfullscreen loading="lazy"
                    style="position:absolute;inset:0;width:100%;height:100%;border:0;"></iframe>
        </div>
        <?php else: ?>
        <p class="ath-item__name" style="margin:0 0 4px;">Aperçu indisponible</p>
        <p class="ath-panel__lead" style="margin:0;">Ce média ne peut pas être prévisualisé pour le moment.</p>
        <?php endif; ?>
    </div>

    <?php if ($canUpload): ?>
    <div>
        <form method="post" action="<?= $h(url('back-office/media/' . $id)) ?>" class="ath-form" id="bo-media-edit-form">
            <div class="ath-form__head">
                <span class="ath-form__title">Réglages du média</span>
            </div>
            <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
            <input type="hidden" name="blur_regions_json" id="bo-media-regions" value="<?= $h($regionsJson) ?>">
            <div class="ath-form__grid ath-form__grid--wide">
                <label class="ath-field">
                    <span class="ath-field__label">Titre *</span>
                    <input type="text" name="title" maxlength="180" value="<?= $h($title) ?>" required class="ath-field__input">
                </label>
                <label class="ath-field">
                    <span class="ath-field__label">Légende</span>
                    <textarea name="caption" rows="3" class="ath-field__textarea"><?= $h((string) ($item['caption'] ?? '')) ?></textarea>
                </label>
                <label class="ath-field">
                    <span class="ath-field__label">Collection</span>
                    <select name="collection_id" class="ath-field__select">
                        <option value="0">Sans collection</option>
                        <?php foreach ($mediaCollections as $c): ?>
                            <?php $collectionId = (int) ($c['id'] ?? 0); ?>
                        <option value="<?= $collectionId ?>"<?= ((int) ($item['collection_id'] ?? 0)) === $collectionId ? ' selected' : '' ?>><?= $h((string) ($c['title'] ?? '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php if ($kind === CommunityMediaDetails::KIND_LONG_VIDEO): ?>
                <label class="ath-field">
                    <span class="ath-field__label">Lien de la vidéo longue *</span>
                    <input type="url" name="external_url" value="<?= $h((string) ($item['external_url'] ?? '')) ?>" required class="ath-field__input">
                </label>
                <?php endif; ?>
                <?php if ($kind === CommunityMediaDetails::KIND_IMAGE): ?>
                <label class="ath-field">
                    <span class="ath-field__label">Floutage</span>
                    <select name="blur_mode" id="bo-media-blur-mode" class="ath-field__select">
                        <?php foreach ($blurModeLabels as $modeKey => $modeLabel): ?>
                        <option value="<?= $h((string) $modeKey) ?>"<?= ((string) ($item['blur_mode'] ?? 'none')) === (string) $modeKey ? ' selected' : '' ?>><?= $h((string) $modeLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php else: ?>
                <input type="hidden" name="blur_mode" value="none">
                <?php endif; ?>
                <label class="ath-field">
                    <span class="ath-field__label">Statut</span>
                    <?php if ($canPublish): ?>
                    <select name="status" class="ath-field__select">
                        <?php foreach ($statusLabels as $statusKey => $statusLabel): ?>
                        <option value="<?= $h((string) $statusKey) ?>"<?= $status === (string) $statusKey ? ' selected' : '' ?>><?= $h((string) $statusLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php else: ?>
                    <input type="text" value="<?= $h($statusLabels[$status] ?? 'Brouillon') ?>" class="ath-field__input" readonly>
                    <input type="hidden" name="status" value="<?= $h($status) ?>">
                    <span class="ath-field__help">La publication sur la page publique demande le droit correspondant.</span>
                    <?php endif; ?>
                </label>
                <label class="ath-field">
                    <span class="ath-field__label">Ordre d’affichage</span>
                    <input type="number" name="sort_order" value="<?= (int) ($item['sort_order'] ?? 0) ?>" class="ath-field__input">
                </label>
            </div>
            <div class="ath-check-grid" style="margin-top:12px;">
                <label class="ath-check">
                    <input type="checkbox" name="show_on_public_page" value="1"<?= !empty($item['show_on_public_page']) ? ' checked' : '' ?><?= $canPublish ? '' : ' disabled' ?>>
                    <span>Afficher sur la page publique</span>
                </label>
                <label class="ath-check">
                    <input type="checkbox" name="is_hero" value="1"<?= !empty($item['is_hero']) ? ' checked' : '' ?><?= $canPublish ? '' : ' disabled' ?>>
                    <span>Mettre en avant en tête de vitrine</span>
                </label>
            </div>
            <div class="ath-form__actions">
                <button type="submit" class="ath-btn ath-btn--solid">Enregistrer</button>
            </div>
        </form>

        <div class="ath-warn">
            <p class="ath-warn__title">Suppression définitive</p>
            <p class="ath-warn__text">
                Le fichier et ses réglages sont supprimés sans possibilité de rétablissement.
                Le média disparaît aussitôt de la page publique.
            </p>
            <form method="post" action="<?= $h(url('back-office/media/' . $id . '/delete')) ?>" style="margin-top:11px;"
                  onsubmit="return confirm('Supprimer définitivement ce média ?');">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                <button type="submit" class="ath-row-action ath-row-action--danger">Supprimer ce média</button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if ($kind === CommunityMediaDetails::KIND_IMAGE && $publicMediaUrl): ?>
<script src="<?= $h(asset_url('assets/js/community-media-blur.js')) ?>"></script>
<?php endif; ?>
