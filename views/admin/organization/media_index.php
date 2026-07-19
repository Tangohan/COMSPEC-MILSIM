<?php
declare(strict_types=1);

use App\Core\Csrf;
use App\Support\CommunityMediaDetails;

/** @var list<array<string,mixed>> $mediaItems */
/** @var list<array<string,mixed>> $mediaCollections */
/** @var array<string,string> $kindLabels */
/** @var array<string,string> $statusLabels */
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
?>
<div class="bo-media">
  <header class="bo-media__hero">
    <div class="bo-media__hero-inner">
      <div>
        <p class="bo-media__eyebrow">Bibliothèque</p>
        <h1 class="bo-media__title">Médias de la communauté</h1>
        <p class="bo-media__lead">Images, vidéos courtes et liens vers des vidéos longues pour alimenter la page publique.</p>
      </div>
      <div class="bo-media__hero-actions">
        <a class="bo-media__btn bo-media__btn--ghost" href="<?= htmlspecialchars(url('back-office/community/presentation'), ENT_QUOTES, 'UTF-8') ?>">Vitrine publique</a>
      </div>
    </div>
  </header>

  <div class="bo-media__deck">
    <?php if ($flashSuccess): ?>
    <p class="bo-media__flash bo-media__flash--ok"><?= htmlspecialchars($flashSuccess) ?></p>
    <?php endif; ?>
    <?php if ($flashError): ?>
    <p class="bo-media__flash bo-media__flash--err"><?= htmlspecialchars($flashError) ?></p>
    <?php endif; ?>

    <?php if ($canUpload || $canManageCollections): ?>
    <div class="bo-media__grid">
      <?php if ($canUpload): ?>
      <section class="bo-media__panel" aria-labelledby="bo-media-add">
        <div class="bo-media__panel-head">
          <h2 id="bo-media-add" class="bo-media__panel-title">Ajouter un média</h2>
        </div>
        <form method="post" action="<?= htmlspecialchars(url('back-office/media'), ENT_QUOTES, 'UTF-8') ?>" enctype="multipart/form-data" class="bo-media__form" id="bo-media-add-form">
          <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
          <label class="bo-media__label">
            <span>Type de contenu</span>
            <select name="media_kind" id="bo-media-kind" required>
              <?php foreach ($kindLabels as $k => $lab): ?>
              <option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($lab) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="bo-media__label">
            <span>Titre</span>
            <input type="text" name="title" maxlength="180" placeholder="Ex. Briefing terrain — session mars">
          </label>
          <label class="bo-media__label">
            <span>Légende (optionnel)</span>
            <textarea name="caption" rows="2" placeholder="Courte description visible sur la page publique"></textarea>
          </label>
          <label class="bo-media__label">
            <span>Collection</span>
            <select name="collection_id">
              <option value="0">Sans collection</option>
              <?php foreach ($mediaCollections as $c): ?>
              <option value="<?= (int) ($c['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($c['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="bo-media__label" id="bo-media-file-wrap">
            <span>Fichier (image ou vidéo courte)</span>
            <input type="file" name="media_file" id="bo-media-file" accept="image/jpeg,image/png,image/webp,video/mp4,video/webm">
            <span class="bo-media__hint">Images jusqu’à 8 Mo · vidéos courtes jusqu’à 80 Mo (idéalement moins de 90 secondes)</span>
          </label>
          <label class="bo-media__label" id="bo-media-url-wrap" hidden>
            <span>Lien de la vidéo longue</span>
            <input type="url" name="external_url" id="bo-media-url" placeholder="https://www.youtube.com/watch?v=…">
            <span class="bo-media__hint">YouTube, Vimeo ou lien sécurisé — hébergement externe recommandé pour les longues durées</span>
          </label>
          <button type="submit" class="bo-media__btn">Ajouter à la bibliothèque</button>
        </form>
      </section>
      <?php endif; ?>

      <?php if ($canManageCollections): ?>
      <section class="bo-media__panel" aria-labelledby="bo-media-col">
        <div class="bo-media__panel-head">
          <h2 id="bo-media-col" class="bo-media__panel-title">Collections</h2>
        </div>
        <form method="post" action="<?= htmlspecialchars(url('back-office/media/collections'), ENT_QUOTES, 'UTF-8') ?>" class="bo-media__form">
          <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
          <label class="bo-media__label">
            <span>Nom de la collection</span>
            <input type="text" name="title" maxlength="180" required placeholder="Ex. Saison 2026">
          </label>
          <label class="bo-media__label">
            <span>Description</span>
            <textarea name="description" rows="2"></textarea>
          </label>
          <label class="bo-media__check">
            <input type="checkbox" name="is_public" value="1">
            <span>Visible sur la page publique</span>
          </label>
          <button type="submit" class="bo-media__btn bo-media__btn--ghost">Créer la collection</button>
        </form>
        <?php if ($mediaCollections !== []): ?>
        <ul class="bo-media__col-list">
          <?php foreach ($mediaCollections as $c): ?>
          <li>
            <div>
              <strong><?= htmlspecialchars((string) ($c['title'] ?? '')) ?></strong>
              <span><?= (int) ($c['items_count'] ?? 0) ?> média(s)<?= !empty($c['is_public']) ? ' · visible sur la page publique' : '' ?></span>
            </div>
            <form method="post" action="<?= htmlspecialchars(url('back-office/media/collections/' . (int) ($c['id'] ?? 0) . '/delete'), ENT_QUOTES, 'UTF-8') ?>" onsubmit="return confirm('Supprimer cette collection ?');">
              <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
              <button type="submit" class="bo-media__btn-text">Supprimer</button>
            </form>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </section>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <section class="bo-media__panel bo-media__panel--wide" aria-labelledby="bo-media-lib">
      <div class="bo-media__panel-head">
        <h2 id="bo-media-lib" class="bo-media__panel-title">Bibliothèque</h2>
        <?php if ($itemCount > 0): ?>
        <p class="bo-media__panel-meta"><?= $itemCount ?> média<?= $itemCount > 1 ? 's' : '' ?></p>
        <?php endif; ?>
      </div>
      <?php if ($mediaItems === []): ?>
      <div class="bo-media__empty">
        <div class="bo-media__empty-icon" aria-hidden="true">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z" /></svg>
        </div>
        <strong>Aucun média pour le moment</strong>
        <span>Ajoutez une image ou une vidéo pour commencer à enrichir la page publique de votre communauté.</span>
      </div>
      <?php else: ?>
      <div class="bo-media__cards">
        <?php foreach ($mediaItems as $item): ?>
          <?php
            $kind = (string) ($item['media_kind'] ?? 'image');
            $status = (string) ($item['status'] ?? 'draft');
            $thumb = CommunityMediaDetails::publicUrl(isset($item['storage_path']) ? (string) $item['storage_path'] : null);
            $cid = (int) ($item['collection_id'] ?? 0);
            $kindBadgeClass = 'bo-media__badge--kind';
            if ($kind === CommunityMediaDetails::KIND_SHORT_VIDEO) {
                $kindBadgeClass = 'bo-media__badge--kind-video';
            } elseif ($kind === CommunityMediaDetails::KIND_LONG_VIDEO) {
                $kindBadgeClass = 'bo-media__badge--kind-long';
            }
            $statusBadgeClass = 'bo-media__badge--draft';
            if ($status === CommunityMediaDetails::STATUS_PUBLISHED) {
                $statusBadgeClass = 'bo-media__badge--published';
            } elseif ($status === CommunityMediaDetails::STATUS_ARCHIVED) {
                $statusBadgeClass = 'bo-media__badge--archived';
            }
          ?>
        <article class="bo-media__card">
          <div class="bo-media__thumb">
            <?php if ($kind === CommunityMediaDetails::KIND_IMAGE && $thumb): ?>
            <img src="<?= htmlspecialchars($thumb, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars(trim((string) ($item['title'] ?? '')) !== '' ? (string) $item['title'] : 'Aperçu du média', ENT_QUOTES, 'UTF-8') ?>" data-img-fallback="media" data-img-label="Aperçu du média indisponible">
            <?php elseif ($kind === CommunityMediaDetails::KIND_SHORT_VIDEO && $thumb): ?>
            <video src="<?= htmlspecialchars($thumb, ENT_QUOTES, 'UTF-8') ?>" muted playsinline></video>
            <?php else: ?>
            <span class="bo-media__thumb-fallback"><?= htmlspecialchars($kindLabels[$kind] ?? 'Média') ?></span>
            <?php endif; ?>
          </div>
          <div class="bo-media__card-body">
            <div class="bo-media__badges">
              <span class="bo-media__badge <?= $kindBadgeClass ?>"><?= htmlspecialchars($kindLabels[$kind] ?? 'Média') ?></span>
              <span class="bo-media__badge <?= $statusBadgeClass ?>"><?= htmlspecialchars($statusLabels[$status] ?? 'Brouillon') ?></span>
              <?php if (!empty($item['show_on_public_page'])): ?>
              <span class="bo-media__badge bo-media__badge--public">Page publique</span>
              <?php endif; ?>
              <?php if (!empty($item['is_hero'])): ?>
              <span class="bo-media__badge bo-media__badge--hero">Mis en avant</span>
              <?php endif; ?>
            </div>
            <h3 class="bo-media__card-title"><?= htmlspecialchars((string) ($item['title'] ?? '')) ?></h3>
            <?php if ($cid > 0 && isset($colById[$cid])): ?>
            <p class="bo-media__card-meta"><?= htmlspecialchars($colById[$cid]) ?></p>
            <?php endif; ?>
            <a class="bo-media__card-action" href="<?= htmlspecialchars(url('back-office/media/' . (int) ($item['id'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>">Ouvrir</a>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </section>
  </div>
</div>
<script>
(function () {
  var kind = document.getElementById('bo-media-kind');
  var fileWrap = document.getElementById('bo-media-file-wrap');
  var urlWrap = document.getElementById('bo-media-url-wrap');
  var file = document.getElementById('bo-media-file');
  var url = document.getElementById('bo-media-url');
  if (!kind || !fileWrap || !urlWrap) return;
  function sync() {
    var long = kind.value === 'long_video';
    fileWrap.hidden = long;
    urlWrap.hidden = !long;
    if (file) file.required = !long;
    if (url) url.required = long;
  }
  kind.addEventListener('change', sync);
  sync();
})();
</script>
