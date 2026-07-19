<?php
declare(strict_types=1);

use App\Core\Csrf;
use App\Support\CommunityMediaDetails;

/** @var array<string,mixed> $mediaItem */
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
$regionsJson = json_encode($blurRegions, JSON_UNESCAPED_UNICODE);
if ($regionsJson === false) {
    $regionsJson = '[]';
}
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
<div class="bo-media">
  <header class="bo-media__hero">
    <div class="bo-media__hero-inner">
      <div>
        <p class="bo-media__back"><a href="<?= htmlspecialchars(url('back-office/media'), ENT_QUOTES, 'UTF-8') ?>">← Bibliothèque</a></p>
        <p class="bo-media__eyebrow"><?= htmlspecialchars($kindLabels[$kind] ?? 'Média') ?></p>
        <h1 class="bo-media__title"><?= htmlspecialchars((string) ($item['title'] ?? 'Média')) ?></h1>
        <div class="bo-media__badges bo-media__hero-badges">
          <span class="bo-media__badge <?= $kindBadgeClass ?>"><?= htmlspecialchars($kindLabels[$kind] ?? 'Média') ?></span>
          <span class="bo-media__badge <?= $statusBadgeClass ?>"><?= htmlspecialchars($statusLabels[$status] ?? 'Brouillon') ?></span>
          <?php if (!empty($item['show_on_public_page'])): ?>
          <span class="bo-media__badge bo-media__badge--public">Page publique</span>
          <?php endif; ?>
          <?php if (!empty($item['is_hero'])): ?>
          <span class="bo-media__badge bo-media__badge--hero">Mis en avant</span>
          <?php endif; ?>
        </div>
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

    <div class="bo-media__edit-layout">
      <div class="bo-media__preview-wrap">
        <?php if ($kind === CommunityMediaDetails::KIND_IMAGE && $publicMediaUrl): ?>
        <div class="bo-media__blur-stage" id="bo-media-blur-stage">
          <img src="<?= htmlspecialchars((string) $publicMediaUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Aperçu du média" id="bo-media-preview-img" class="bo-media__preview-img" data-img-fallback="media" data-img-label="Aperçu du média indisponible">
          <canvas id="bo-media-blur-canvas" class="bo-media__blur-canvas" aria-hidden="true"></canvas>
        </div>
        <p class="bo-media__hint bo-media__preview-hint">Cliquez-glissez sur l’image pour ajouter une zone de flou. Double-clic sur une zone pour la retirer.</p>
        <div class="bo-media__blur-actions">
          <button type="button" class="bo-media__btn bo-media__btn--ghost" id="bo-media-clear-blur">Effacer les zones</button>
          <button type="button" class="bo-media__btn bo-media__btn--ghost" id="bo-media-detect-faces">Détecter les visages</button>
        </div>
        <p class="bo-media__hint" id="bo-media-face-hint">La détection de visage utilise l’outil du navigateur lorsqu’il est disponible. Sinon, dessinez les zones manuellement.</p>
        <?php elseif ($kind === CommunityMediaDetails::KIND_SHORT_VIDEO && $publicMediaUrl): ?>
        <video class="bo-media__preview-video" src="<?= htmlspecialchars((string) $publicMediaUrl, ENT_QUOTES, 'UTF-8') ?>" controls playsinline></video>
        <?php elseif ($kind === CommunityMediaDetails::KIND_LONG_VIDEO && $embedUrl): ?>
        <div class="bo-media__embed">
          <iframe src="<?= htmlspecialchars((string) $embedUrl, ENT_QUOTES, 'UTF-8') ?>" title="Aperçu vidéo" allowfullscreen loading="lazy"></iframe>
        </div>
        <?php else: ?>
        <div class="bo-media__empty">
          <strong>Aperçu indisponible</strong>
          <span>Ce média ne peut pas être prévisualisé pour le moment.</span>
        </div>
        <?php endif; ?>
      </div>

      <?php if ($canUpload): ?>
      <div class="bo-media__edit-side">
        <form method="post" action="<?= htmlspecialchars(url('back-office/media/' . $id), ENT_QUOTES, 'UTF-8') ?>" class="bo-media__form bo-media__panel" id="bo-media-edit-form">
          <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="blur_regions_json" id="bo-media-regions" value="<?= htmlspecialchars($regionsJson, ENT_QUOTES, 'UTF-8') ?>">

          <label class="bo-media__label">
            <span>Titre</span>
            <input type="text" name="title" maxlength="180" value="<?= htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
          </label>
          <label class="bo-media__label">
            <span>Légende</span>
            <textarea name="caption" rows="3"><?= htmlspecialchars((string) ($item['caption'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
          </label>
          <label class="bo-media__label">
            <span>Collection</span>
            <select name="collection_id">
              <option value="0">Sans collection</option>
              <?php foreach ($mediaCollections as $c): ?>
              <option value="<?= (int) ($c['id'] ?? 0) ?>" <?= (int) ($item['collection_id'] ?? 0) === (int) ($c['id'] ?? 0) ? 'selected' : '' ?>>
                <?= htmlspecialchars((string) ($c['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
              </option>
              <?php endforeach; ?>
            </select>
          </label>

          <?php if ($kind === CommunityMediaDetails::KIND_LONG_VIDEO): ?>
          <label class="bo-media__label">
            <span>Lien de la vidéo longue</span>
            <input type="url" name="external_url" value="<?= htmlspecialchars((string) ($item['external_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
          </label>
          <?php endif; ?>

          <?php if ($kind === CommunityMediaDetails::KIND_IMAGE): ?>
          <label class="bo-media__label">
            <span>Floutage</span>
            <select name="blur_mode" id="bo-media-blur-mode">
              <?php foreach ($blurModeLabels as $bm => $bl): ?>
              <option value="<?= htmlspecialchars($bm, ENT_QUOTES, 'UTF-8') ?>" <?= ((string) ($item['blur_mode'] ?? 'none')) === $bm ? 'selected' : '' ?>><?= htmlspecialchars($bl) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <?php else: ?>
          <input type="hidden" name="blur_mode" value="none">
          <?php endif; ?>

          <label class="bo-media__label">
            <span>Statut</span>
            <select name="status" <?= $canPublish ? '' : 'disabled' ?>>
              <?php foreach ($statusLabels as $sk => $sl): ?>
              <option value="<?= htmlspecialchars($sk, ENT_QUOTES, 'UTF-8') ?>" <?= ((string) ($item['status'] ?? 'draft')) === $sk ? 'selected' : '' ?>><?= htmlspecialchars($sl) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if (!$canPublish): ?>
            <input type="hidden" name="status" value="<?= htmlspecialchars((string) ($item['status'] ?? 'draft'), ENT_QUOTES, 'UTF-8') ?>">
            <span class="bo-media__hint">La publication sur la page publique nécessite le droit correspondant.</span>
            <?php endif; ?>
          </label>

          <label class="bo-media__check">
            <input type="checkbox" name="show_on_public_page" value="1" <?= !empty($item['show_on_public_page']) ? 'checked' : '' ?> <?= $canPublish ? '' : 'disabled' ?>>
            <span>Afficher sur la page publique</span>
          </label>
          <label class="bo-media__check">
            <input type="checkbox" name="is_hero" value="1" <?= !empty($item['is_hero']) ? 'checked' : '' ?> <?= $canPublish ? '' : 'disabled' ?>>
            <span>Mettre en avant dans le hero</span>
          </label>
          <label class="bo-media__label">
            <span>Ordre d’affichage</span>
            <input type="number" name="sort_order" value="<?= (int) ($item['sort_order'] ?? 0) ?>">
          </label>

          <button type="submit" class="bo-media__btn">Enregistrer</button>
        </form>

        <form method="post" action="<?= htmlspecialchars(url('back-office/media/' . $id . '/delete'), ENT_QUOTES, 'UTF-8') ?>" class="bo-media__danger" onsubmit="return confirm('Supprimer définitivement ce média ?');">
          <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
          <p class="bo-media__danger-label">Zone sensible</p>
          <button type="submit" class="bo-media__btn-text">Supprimer ce média</button>
        </form>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php if ($kind === CommunityMediaDetails::KIND_IMAGE && $publicMediaUrl): ?>
<script src="<?= htmlspecialchars(asset_url('assets/js/community-media-blur.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endif; ?>
