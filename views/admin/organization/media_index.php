<?php
declare(strict_types=1);

/**
 * Bibliothèque de médias — charte ATHENA.
 *
 * L’en-tête de page est rendu par la coque back-office. La bibliothèque reste une galerie :
 * les vignettes portent l’information, un tableau les perdrait.
 *
 * @var list<array<string,mixed>> $mediaItems
 * @var list<array<string,mixed>> $mediaCollections
 * @var array<string,string> $kindLabels
 * @var array<string,string> $statusLabels
 * @var bool $canUpload
 * @var bool $canManageCollections
 */

use App\Core\Csrf;
use App\Support\CommunityMediaDetails;

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$mediaItems = is_array($mediaItems ?? null) ? $mediaItems : [];
$mediaCollections = is_array($mediaCollections ?? null) ? $mediaCollections : [];
$kindLabels = is_array($kindLabels ?? null) ? $kindLabels : CommunityMediaDetails::kindLabels();
$statusLabels = is_array($statusLabels ?? null) ? $statusLabels : CommunityMediaDetails::statusLabels();
$canUpload = !empty($canUpload);
$canManageCollections = !empty($canManageCollections);
$flashSuccess = \App\Core\Session::getFlash('success');
$flashError = \App\Core\Session::getFlash('error');
$csrf = Csrf::token();

$colById = [];
foreach ($mediaCollections as $c) {
    $colById[(int) ($c['id'] ?? 0)] = (string) ($c['title'] ?? '');
}

$itemCount = count($mediaItems);
$publishedCount = 0;
$publicCount = 0;
$heroCount = 0;
foreach ($mediaItems as $item) {
    if ((string) ($item['status'] ?? '') === CommunityMediaDetails::STATUS_PUBLISHED) {
        $publishedCount++;
    }
    if (!empty($item['show_on_public_page'])) {
        $publicCount++;
    }
    if (!empty($item['is_hero'])) {
        $heroCount++;
    }
}
$pctOf = static fn (int $n): string => $itemCount > 0 ? (string) (int) round($n / $itemCount * 100) . '%' : '0%';

/** Tonalité d’étiquette pour un statut de média. */
$statusTone = static function (string $status): string {
    return match ($status) {
        CommunityMediaDetails::STATUS_PUBLISHED => 'ath-tag--ok',
        CommunityMediaDetails::STATUS_ARCHIVED => 'ath-tag--neut',
        default => 'ath-tag--warn',
    };
};
?>
<?php if ($flashError): ?>
<p class="ath-flash ath-flash--err" role="alert"><?= $h((string) $flashError) ?></p>
<?php endif; ?>
<?php if ($flashSuccess): ?>
<p class="ath-flash ath-flash--ok" role="status"><?= $h((string) $flashSuccess) ?></p>
<?php endif; ?>

<div class="ath-form__actions" style="border-top:0;margin:0 0 16px;padding-top:0;">
    <a href="<?= $h(url('back-office/community/presentation')) ?>" class="ath-btn">Vitrine publique</a>
</div>

<?php
$athKpis = [
    ['label' => 'MÉDIAS', 'value' => (string) $itemCount, 'delta' => '', 'tone' => '#1e4f80', 'pct' => $itemCount > 0 ? '100%' : '0%', 'note' => 'dans la bibliothèque'],
    ['label' => 'PUBLIÉS', 'value' => (string) $publishedCount, 'delta' => '', 'tone' => '#0b8a5c', 'pct' => $pctOf($publishedCount), 'note' => 'exploitables'],
    ['label' => 'SUR LA PAGE PUBLIQUE', 'value' => (string) $publicCount, 'delta' => '', 'tone' => '#0b8a5c', 'pct' => $pctOf($publicCount), 'note' => 'visibles des visiteurs'],
    ['label' => 'MIS EN AVANT', 'value' => (string) $heroCount, 'delta' => '', 'tone' => $heroCount > 0 ? '#c98a12' : '#8c979b', 'pct' => $pctOf($heroCount), 'note' => 'en tête de vitrine'],
    ['label' => 'COLLECTIONS', 'value' => (string) count($mediaCollections), 'delta' => '', 'tone' => '#1e4f80', 'pct' => '100%', 'note' => 'regroupements'],
];
require base_path('views/partials/ath_kpis.php');
?>

<?php if ($canUpload || $canManageCollections): ?>
<div class="ath-columns ath-rise" style="margin-bottom:16px;">
    <?php if ($canUpload): ?>
    <form method="post" action="<?= $h(url('back-office/media')) ?>" enctype="multipart/form-data" class="ath-form" id="bo-media-add-form">
        <div class="ath-form__head">
            <span class="ath-form__title">Ajouter un média</span>
        </div>
        <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
        <div class="ath-form__grid ath-form__grid--wide">
            <label class="ath-field">
                <span class="ath-field__label">Type de contenu</span>
                <select name="media_kind" id="bo-media-kind" required class="ath-field__select">
                    <?php foreach ($kindLabels as $key => $label): ?>
                    <option value="<?= $h((string) $key) ?>"><?= $h((string) $label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="ath-field">
                <span class="ath-field__label">Titre</span>
                <input type="text" name="title" maxlength="180" class="ath-field__input" placeholder="Briefing terrain — session mars">
            </label>
            <label class="ath-field">
                <span class="ath-field__label">Légende</span>
                <textarea name="caption" rows="2" class="ath-field__textarea" placeholder="Courte description visible sur la page publique"></textarea>
            </label>
            <label class="ath-field">
                <span class="ath-field__label">Collection</span>
                <select name="collection_id" class="ath-field__select">
                    <option value="0">Sans collection</option>
                    <?php foreach ($mediaCollections as $c): ?>
                    <option value="<?= (int) ($c['id'] ?? 0) ?>"><?= $h((string) ($c['title'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="ath-field" id="bo-media-file-wrap">
                <span class="ath-field__label">Fichier</span>
                <input type="file" name="media_file" id="bo-media-file" class="ath-field__input" style="height:auto;padding:7px 10px;" accept="image/jpeg,image/png,image/webp,video/mp4,video/webm">
                <span class="ath-field__help">Images jusqu’à 8 Mo, vidéos courtes jusqu’à 80 Mo — idéalement sous 90 secondes.</span>
            </label>
            <label class="ath-field" id="bo-media-url-wrap" hidden>
                <span class="ath-field__label">Lien de la vidéo longue</span>
                <input type="url" name="external_url" id="bo-media-url" class="ath-field__input" placeholder="https://www.youtube.com/watch?v=…">
                <span class="ath-field__help">Hébergement externe recommandé pour les longues durées.</span>
            </label>
        </div>
        <div class="ath-form__actions">
            <button type="submit" class="ath-btn ath-btn--solid">Ajouter à la bibliothèque</button>
        </div>
    </form>
    <?php endif; ?>

    <?php if ($canManageCollections): ?>
    <div>
        <form method="post" action="<?= $h(url('back-office/media/collections')) ?>" class="ath-form">
            <div class="ath-form__head">
                <span class="ath-form__title">Nouvelle collection</span>
                <span class="ath-form__hint">Regroupe des médias pour la vitrine.</span>
            </div>
            <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
            <div class="ath-form__grid ath-form__grid--wide">
                <label class="ath-field">
                    <span class="ath-field__label">Titre *</span>
                    <input type="text" name="title" maxlength="180" required class="ath-field__input" placeholder="Saison 2026">
                </label>
                <label class="ath-field">
                    <span class="ath-field__label">Description</span>
                    <textarea name="description" rows="2" class="ath-field__textarea"></textarea>
                </label>
            </div>
            <div class="ath-check-grid" style="margin-top:11px;">
                <label class="ath-check">
                    <input type="checkbox" name="is_public" value="1">
                    <span>Visible sur la page publique</span>
                </label>
            </div>
            <div class="ath-form__actions">
                <button type="submit" class="ath-btn">Créer la collection</button>
            </div>
        </form>

        <?php if ($mediaCollections !== []): ?>
        <ul class="ath-list">
            <?php foreach ($mediaCollections as $c): ?>
                <?php $collectionId = (int) ($c['id'] ?? 0); ?>
            <li>
                <div style="min-width:0;">
                    <span class="ath-list__name"><?= $h((string) ($c['title'] ?? '')) ?></span>
                    <span class="ath-list__meta">
                        <?= (int) ($c['items_count'] ?? 0) ?> média<?= (int) ($c['items_count'] ?? 0) > 1 ? 's' : '' ?><?= !empty($c['is_public']) ? ' · visible sur la page publique' : '' ?>
                    </span>
                </div>
                <form method="post" action="<?= $h(url('back-office/media/collections/' . $collectionId . '/delete')) ?>"
                      onsubmit="return confirm('Supprimer cette collection ? Les médias qu’elle contient ne sont pas supprimés.');">
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                    <button type="submit" class="ath-row-action ath-row-action--danger">Supprimer</button>
                </form>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<h2 class="ath-section-title">Bibliothèque<?= $itemCount > 0 ? ' — ' . $itemCount . ' média' . ($itemCount > 1 ? 's' : '') : '' ?></h2>

<?php if ($mediaItems === []): ?>
<div class="ath-card" style="padding:20px 22px;">
    <p class="ath-item__name" style="margin:0 0 5px;">Aucun média pour le moment</p>
    <p class="ath-panel__lead" style="margin:0;">
        Ajoutez une image ou une vidéo pour commencer à enrichir la page publique de votre communauté.
    </p>
</div>
<?php else: ?>
<div class="ath-gallery ath-rise">
    <?php foreach ($mediaItems as $item): ?>
        <?php
        $kind = (string) ($item['media_kind'] ?? 'image');
        $status = (string) ($item['status'] ?? '');
        $thumb = CommunityMediaDetails::publicUrl(isset($item['storage_path']) ? (string) $item['storage_path'] : null);
        $collectionId = (int) ($item['collection_id'] ?? 0);
        $title = trim((string) ($item['title'] ?? ''));
        ?>
    <article class="ath-media">
        <div class="ath-media__thumb">
            <?php if ($kind === CommunityMediaDetails::KIND_IMAGE && $thumb): ?>
            <img src="<?= $h($thumb) ?>" alt="<?= $h($title !== '' ? $title : 'Aperçu du média') ?>" data-img-fallback="media" data-img-label="Aperçu du média indisponible" loading="lazy">
            <?php elseif ($kind === CommunityMediaDetails::KIND_SHORT_VIDEO && $thumb): ?>
            <video src="<?= $h($thumb) ?>" muted playsinline preload="metadata"></video>
            <?php else: ?>
            <span class="ath-media__thumb-fallback"><?= $h($kindLabels[$kind] ?? 'Média') ?></span>
            <?php endif; ?>
        </div>
        <div class="ath-media__body">
            <div class="ath-media__badges">
                <span class="ath-tag ath-tag--info"><?= $h($kindLabels[$kind] ?? 'Média') ?></span>
                <span class="ath-tag <?= $statusTone($status) ?>"><?= $h($statusLabels[$status] ?? 'Brouillon') ?></span>
                <?php if (!empty($item['show_on_public_page'])): ?>
                <span class="ath-tag ath-tag--ok">Page publique</span>
                <?php endif; ?>
                <?php if (!empty($item['is_hero'])): ?>
                <span class="ath-tag ath-tag--warn">Mis en avant</span>
                <?php endif; ?>
            </div>
            <h3 class="ath-media__title"><?= $h($title !== '' ? $title : 'Média sans titre') ?></h3>
            <?php if ($collectionId > 0 && isset($colById[$collectionId])): ?>
            <p class="ath-media__meta"><?= $h($colById[$collectionId]) ?></p>
            <?php endif; ?>
            <a class="ath-media__action" href="<?= $h(url('back-office/media/' . (int) ($item['id'] ?? 0))) ?>">Ouvrir la fiche →</a>
        </div>
    </article>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
/* Une vidéo longue est référencée par un lien, pas téléversée : les deux champs
   s’excluent, et l’obligation de saisie suit le type choisi. */
(function () {
  var kind = document.getElementById('bo-media-kind');
  var fileWrap = document.getElementById('bo-media-file-wrap');
  var urlWrap = document.getElementById('bo-media-url-wrap');
  var file = document.getElementById('bo-media-file');
  var url = document.getElementById('bo-media-url');
  if (!kind || !fileWrap || !urlWrap) return;
  var sync = function () {
    var isLongVideo = kind.value === 'long_video';
    fileWrap.hidden = isLongVideo;
    urlWrap.hidden = !isLongVideo;
    if (file) file.required = !isLongVideo;
    if (url) url.required = isLongVideo;
  };
  kind.addEventListener('change', sync);
  sync();
})();
</script>
