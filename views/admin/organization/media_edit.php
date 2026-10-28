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

$isPublished = $status === CommunityMediaDetails::STATUS_PUBLISHED;
$isArchived = $status === CommunityMediaDetails::STATUS_ARCHIVED;
$isOnPublicPage = !empty($item['show_on_public_page']);
$isLive = $isPublished && $isOnPublicPage;

$statusTone = match ($status) {
    CommunityMediaDetails::STATUS_PUBLISHED => 'ath-tag--ok',
    CommunityMediaDetails::STATUS_ARCHIVED => 'ath-tag--neut',
    default => 'ath-tag--warn',
};

// Bandeau d’état : le plus visible possible tant que le média n’est pas en ligne.
if ($isLive) {
    $banner = ['tone' => 'live', 'title' => 'En ligne sur la vitrine publique', 'text' => 'Ce média est publié et visible par tous les visiteurs de la page publique.'];
} elseif ($isArchived) {
    $banner = ['tone' => 'archived', 'title' => 'Archivé : ce média n’est plus visible', 'text' => 'Il a été retiré de la page publique. Repassez-le en « Publié » pour le remettre en ligne.'];
} elseif ($isPublished) {
    $banner = ['tone' => 'hidden', 'title' => 'Publié, mais masqué de la page publique', 'text' => 'Le média est publié mais la case « Afficher sur la page publique » est décochée : les visiteurs ne le voient pas.'];
} else {
    $banner = ['tone' => 'draft', 'title' => 'Brouillon : ce média n’est pas publié', 'text' => 'Il n’apparaît nulle part sur la vitrine publique tant qu’il reste en brouillon. Publiez-le quand il est prêt.'];
}

$fileSize = (int) ($item['file_size'] ?? 0);
$sizeLabel = $fileSize > 0
    ? ($fileSize >= 1048576 ? number_format($fileSize / 1048576, 1, ',', ' ') . ' Mo' : max(1, (int) round($fileSize / 1024)) . ' Ko')
    : null;
$width = (int) ($item['width'] ?? 0);
$height = (int) ($item['height'] ?? 0);
$createdAt = (string) ($item['created_at'] ?? '');
$createdTs = $createdAt !== '' ? strtotime($createdAt) : false;
$facts = array_values(array_filter([
    $kindLabels[$kind] ?? 'Média',
    $width > 0 && $height > 0 ? $width . ' × ' . $height . ' px' : null,
    $sizeLabel,
    $createdTs ? 'Ajouté le ' . date('d/m/Y', $createdTs) : null,
]));
$views = (int) ($item['views_count'] ?? 0);
$likes = (int) ($item['likes_count'] ?? 0);

$steps = [
    ['label' => 'Brouillon', 'done' => true],
    ['label' => 'Publié', 'done' => $isPublished],
    ['label' => 'Sur la vitrine', 'done' => $isLive],
];
$statusHelp = [
    CommunityMediaDetails::STATUS_DRAFT => 'Visible seulement en back-office',
    CommunityMediaDetails::STATUS_PUBLISHED => 'Peut apparaître sur la vitrine',
    CommunityMediaDetails::STATUS_ARCHIVED => 'Retiré, conservé en bibliothèque',
];
?>
<?php if ($flashError): ?>
<p class="ath-flash ath-flash--err" role="alert"><?= $h((string) $flashError) ?></p>
<?php endif; ?>
<?php if ($flashSuccess): ?>
<p class="ath-flash ath-flash--ok" role="status"><?= $h((string) $flashSuccess) ?></p>
<?php endif; ?>

<div class="mde">
    <section class="mde-banner mde-banner--<?= $h($banner['tone']) ?> ath-rise" role="<?= $isLive ? 'status' : 'alert' ?>">
        <span class="mde-banner__icon" aria-hidden="true">
            <?php if ($isLive): ?>
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
            <?php elseif ($isArchived): ?>
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="5" rx="1"/><path d="M5 9v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V9M10 13h4"/></svg>
            <?php elseif ($isPublished): ?>
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3l18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.4 5.2A9.8 9.8 0 0 1 12 5c5 0 9 4.5 10 7-.4 1-1.2 2.3-2.4 3.5M6.1 6.2C4 7.6 2.6 9.8 2 12c1 2.5 5 7 10 7 1.7 0 3.3-.5 4.6-1.3"/></svg>
            <?php else: ?>
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0zM12 9v4M12 17h.01"/></svg>
            <?php endif; ?>
        </span>
        <div class="mde-banner__body">
            <p class="mde-banner__title"><?= $h($banner['title']) ?></p>
            <p class="mde-banner__text"><?= $h($banner['text']) ?></p>
        </div>
        <?php if (!$isLive && $canUpload && $canPublish): ?>
        <button type="submit" form="bo-media-edit-form" name="publish_now" value="1" class="mde-banner__cta">
            <?= $isPublished ? 'Afficher sur la page publique' : 'Publier maintenant' ?>
        </button>
        <?php elseif (!$isLive): ?>
        <span class="mde-banner__note">La publication demande le droit correspondant.</span>
        <?php endif; ?>
    </section>

    <header class="mde-head ath-rise">
        <div class="mde-head__thumb" aria-hidden="true">
            <?php if ($kind === CommunityMediaDetails::KIND_IMAGE && $publicMediaUrl): ?>
            <img src="<?= $h((string) $publicMediaUrl) ?>" alt="" loading="lazy">
            <?php else: ?>
            <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="15" height="14" rx="2"/><path d="m17 10 5-3v10l-5-3"/></svg>
            <?php endif; ?>
        </div>
        <div class="mde-head__main">
            <div class="mde-head__tags">
                <span class="ath-tag <?= $statusTone ?>"><?= $h($statusLabels[$status] ?? 'Brouillon') ?></span>
                <?php if ($isOnPublicPage): ?>
                <span class="ath-tag ath-tag--ok">Page publique</span>
                <?php endif; ?>
                <?php if (!empty($item['is_hero'])): ?>
                <span class="ath-tag ath-tag--warn">Mis en avant</span>
                <?php endif; ?>
            </div>
            <h2 class="mde-head__title"><?= $h($title !== '' ? $title : 'Média sans titre') ?></h2>
            <p class="mde-head__facts"><?php foreach ($facts as $i => $fact): ?><?= $i > 0 ? '<span aria-hidden="true">·</span>' : '' ?><span><?= $h((string) $fact) ?></span><?php endforeach; ?></p>
            <ol class="mde-steps" aria-label="Cycle de publication">
                <?php foreach ($steps as $step): ?>
                <li class="mde-steps__item<?= $step['done'] ? ' is-done' : '' ?>"><span class="mde-steps__dot" aria-hidden="true"></span><?= $h($step['label']) ?></li>
                <?php endforeach; ?>
            </ol>
        </div>
        <div class="mde-head__side">
            <div class="mde-head__stats">
                <div><strong><?= $views ?></strong><span>vues</span></div>
                <div><strong><?= $likes ?></strong><span>j’aime</span></div>
            </div>
            <div class="mde-head__actions">
                <a href="<?= $h(url('back-office/media')) ?>" class="ath-btn">Retour à la bibliothèque</a>
            </div>
        </div>
    </header>

    <div class="mde-layout">
        <section class="mde-card mde-preview">
            <div class="mde-card__head">
                <span class="mde-card__title">Aperçu</span>
                <?php if ($kind === CommunityMediaDetails::KIND_IMAGE && $publicMediaUrl): ?>
                <span class="mde-card__meta">Atelier de floutage</span>
                <?php endif; ?>
            </div>
            <?php if ($kind === CommunityMediaDetails::KIND_IMAGE && $publicMediaUrl): ?>
            <div class="mde-preview__stage">
                <div class="bo-media__blur-stage" id="bo-media-blur-stage">
                    <img src="<?= $h((string) $publicMediaUrl) ?>" alt="Aperçu du média" id="bo-media-preview-img" class="bo-media__preview-img" data-img-fallback="media" data-img-label="Aperçu du média indisponible">
                    <canvas id="bo-media-blur-canvas" class="bo-media__blur-canvas" aria-hidden="true"></canvas>
                </div>
            </div>
            <div class="mde-preview__tools">
                <p class="mde-preview__hint">Cliquez-glissez sur l’image pour ajouter une zone de flou. Double-cliquez sur une zone pour la retirer.</p>
                <div class="mde-preview__buttons">
                    <button type="button" class="ath-btn" id="bo-media-clear-blur">Effacer les zones</button>
                    <button type="button" class="ath-btn" id="bo-media-detect-faces">Détecter les visages</button>
                </div>
                <p class="mde-preview__hint" id="bo-media-face-hint">La détection de visage utilise l’outil du navigateur lorsqu’il est disponible. Sinon, dessinez les zones à la main.</p>
            </div>
            <?php elseif ($kind === CommunityMediaDetails::KIND_SHORT_VIDEO && $publicMediaUrl): ?>
            <div class="mde-preview__stage mde-preview__stage--video">
                <video src="<?= $h((string) $publicMediaUrl) ?>" controls playsinline></video>
            </div>
            <?php elseif ($kind === CommunityMediaDetails::KIND_LONG_VIDEO && $embedUrl): ?>
            <div class="mde-preview__stage mde-preview__stage--embed">
                <iframe src="<?= $h((string) $embedUrl) ?>" title="Aperçu vidéo" allowfullscreen loading="lazy"></iframe>
            </div>
            <?php else: ?>
            <div class="mde-preview__empty">
                <p class="mde-preview__empty-title">Aperçu indisponible</p>
                <p class="mde-preview__hint">Ce média ne peut pas être prévisualisé pour le moment.</p>
            </div>
            <?php endif; ?>
        </section>

        <?php if ($canUpload): ?>
        <div class="mde-side">
            <form method="post" action="<?= $h(url('back-office/media/' . $id)) ?>" class="mde-form" id="bo-media-edit-form">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                <input type="hidden" name="blur_regions_json" id="bo-media-regions" value="<?= $h($regionsJson) ?>">

                <section class="mde-card">
                    <div class="mde-card__head"><span class="mde-card__title">Publication</span></div>
                    <div class="mde-card__body">
                        <?php if ($canPublish): ?>
                        <div class="mde-status" role="radiogroup" aria-label="Statut">
                            <?php foreach ($statusLabels as $statusKey => $statusLabel): ?>
                            <label class="mde-status__opt mde-status__opt--<?= $h((string) $statusKey) ?>">
                                <input type="radio" name="status" value="<?= $h((string) $statusKey) ?>"<?= $status === (string) $statusKey ? ' checked' : '' ?>>
                                <span class="mde-status__label"><?= $h((string) $statusLabel) ?></span>
                                <span class="mde-status__help"><?= $h($statusHelp[$statusKey] ?? '') ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <p class="mde-readonly"><span class="ath-tag <?= $statusTone ?>"><?= $h($statusLabels[$status] ?? 'Brouillon') ?></span> La publication sur la page publique demande le droit correspondant.</p>
                        <input type="hidden" name="status" value="<?= $h($status) ?>">
                        <?php endif; ?>
                        <div class="mde-toggles">
                            <label class="mde-toggle">
                                <input type="checkbox" name="show_on_public_page" value="1"<?= $isOnPublicPage ? ' checked' : '' ?><?= $canPublish ? '' : ' disabled' ?>>
                                <span class="mde-toggle__track" aria-hidden="true"></span>
                                <span class="mde-toggle__text"><strong>Afficher sur la page publique</strong><em>Le média apparaît dans la galerie de la vitrine.</em></span>
                            </label>
                            <label class="mde-toggle">
                                <input type="checkbox" name="is_hero" value="1"<?= !empty($item['is_hero']) ? ' checked' : '' ?><?= $canPublish ? '' : ' disabled' ?>>
                                <span class="mde-toggle__track" aria-hidden="true"></span>
                                <span class="mde-toggle__text"><strong>Mettre en avant en tête de vitrine</strong><em>Affiché en premier, en grand format.</em></span>
                            </label>
                        </div>
                        <label class="ath-field mde-field--short">
                            <span class="ath-field__label">Ordre d’affichage</span>
                            <input type="number" name="sort_order" value="<?= (int) ($item['sort_order'] ?? 0) ?>" class="ath-field__input">
                        </label>
                    </div>
                </section>

                <section class="mde-card">
                    <div class="mde-card__head"><span class="mde-card__title">Contenu</span></div>
                    <div class="mde-card__body">
                        <label class="ath-field">
                            <span class="ath-field__label">Titre *</span>
                            <input type="text" name="title" maxlength="180" value="<?= $h($title) ?>" required class="ath-field__input">
                        </label>
                        <label class="ath-field">
                            <span class="ath-field__label">Légende</span>
                            <textarea name="caption" rows="3" class="ath-field__textarea"><?= $h((string) ($item['caption'] ?? '')) ?></textarea>
                        </label>
                        <div class="mde-card__row">
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
                            <?php if ($kind === CommunityMediaDetails::KIND_IMAGE): ?>
                            <label class="ath-field">
                                <span class="ath-field__label">Floutage</span>
                                <select name="blur_mode" id="bo-media-blur-mode" class="ath-field__select">
                                    <?php foreach ($blurModeLabels as $modeKey => $modeLabel): ?>
                                    <option value="<?= $h((string) $modeKey) ?>"<?= ((string) ($item['blur_mode'] ?? 'none')) === (string) $modeKey ? ' selected' : '' ?>><?= $h((string) $modeLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <?php endif; ?>
                        </div>
                        <?php if ($kind !== CommunityMediaDetails::KIND_IMAGE): ?>
                        <input type="hidden" name="blur_mode" value="none">
                        <?php endif; ?>
                        <?php if ($kind === CommunityMediaDetails::KIND_LONG_VIDEO): ?>
                        <label class="ath-field">
                            <span class="ath-field__label">Lien de la vidéo longue *</span>
                            <input type="url" name="external_url" value="<?= $h((string) ($item['external_url'] ?? '')) ?>" required class="ath-field__input">
                        </label>
                        <?php endif; ?>
                    </div>
                </section>

                <div class="mde-savebar">
                    <span class="mde-savebar__state">
                        <span class="ath-tag <?= $statusTone ?>"><?= $h($statusLabels[$status] ?? 'Brouillon') ?></span>
                        <?= $isLive ? 'Visible sur la vitrine' : 'Non visible sur la vitrine' ?>
                    </span>
                    <button type="submit" class="ath-btn ath-btn--solid">Enregistrer</button>
                </div>
            </form>

            <details class="mde-danger">
                <summary>Supprimer ce média</summary>
                <p class="mde-danger__text">
                    Le fichier et ses réglages sont supprimés sans possibilité de rétablissement.
                    Le média disparaît aussitôt de la page publique.
                </p>
                <form method="post" action="<?= $h(url('back-office/media/' . $id . '/delete')) ?>"
                      onsubmit="return confirm('Supprimer définitivement ce média ?');">
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                    <button type="submit" class="ath-row-action ath-row-action--danger">Supprimer définitivement</button>
                </form>
            </details>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($kind === CommunityMediaDetails::KIND_IMAGE && $publicMediaUrl): ?>
<script src="<?= $h(asset_url('assets/js/community-media-blur.js')) ?>"></script>
<?php endif; ?>
