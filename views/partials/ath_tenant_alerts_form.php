<?php
declare(strict_types=1);

use App\Support\AlertDisplayStyle;
use App\Support\TenantAlertFeatures;
use App\Support\TenantAlertVisuals;

/** @var array<string, mixed>|null $tenantAlert */
$row = $tenantAlert;
$isEdit = $row !== null;
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$dt = static function (?string $sqlDt): string {
    if ($sqlDt === null || $sqlDt === '') {
        return '';
    }
    $t = strtotime($sqlDt);

    return $t ? date('Y-m-d\TH:i', $t) : '';
};

$kindOptions = TenantAlertVisuals::kinds();
$iconLabels = TenantAlertVisuals::iconLabels();
$currentKind = (string) ($row['kind'] ?? 'info');
if (!isset($kindOptions[$currentKind])) {
    $currentKind = 'info';
}
$currentIcon = trim((string) ($row['icon_key'] ?? ''));
if ($currentIcon === '' || !isset($iconLabels[$currentIcon])) {
    $currentIcon = 'auto';
}
$currentColor = TenantAlertVisuals::sanitizeHexColor((string) ($row['accent_color'] ?? ''))
    ?? TenantAlertVisuals::defaultColorForKind($currentKind);
$imageUrl = TenantAlertVisuals::publicUrl(isset($row['image_path']) ? (string) $row['image_path'] : null);
$bannerUrl = TenantAlertVisuals::publicUrl(isset($row['banner_path']) ? (string) $row['banner_path'] : null);
$currentFeatures = TenantAlertFeatures::decodeJson($row['features_json'] ?? null);
$displayOptions = AlertDisplayStyle::tenantOptionsWithMeta();
$currentDisplay = AlertDisplayStyle::sanitizeTenant(isset($row['display_style']) ? (string) $row['display_style'] : null);

$iconSvg = [
    'auto' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h10M4 18h14"/></svg>',
    'info' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
    'star' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>',
    'tag' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>',
    'alert' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>',
    'megaphone' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>',
    'calendar' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>',
    'wrench' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
    'shield' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>',
    'flag' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"/></svg>',
    'graduation' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>',
    'users' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/></svg>',
];

$success = \App\Core\Session::getFlash('success');
$error = \App\Core\Session::getFlash('error');
?>

<div class="bo-ta bo-ta-form ath-rise">
    <div class="ath-users-filters ath-rise">
        <a href="<?= $h(url('back-office/alerts')) ?>" class="ath-btn">← Liste des annonces</a>
    </div>

    <?php if ($success): ?>
    <div class="bo-settings-flash bo-settings-flash--ok ath-rise" role="status"><?= $h((string) $success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="bo-settings-flash bo-settings-flash--err ath-rise" role="alert"><?= $h((string) $error) ?></div>
    <?php endif; ?>

    <form method="<?= $h((string) ($formMethod ?? 'post')) ?>" action="<?= $h((string) ($formAction ?? '')) ?>" enctype="multipart/form-data" class="ath-card bo-ta-form__card" id="ta-alert-form">
        <?= \App\Core\Csrf::field() ?>

        <div class="bo-ta-form__section">
            <h2 class="ath-section-title">Type &amp; apparence</h2>
            <fieldset class="bo-ta-form__fieldset">
                <legend class="ath-users-filters__label">Type d’annonce</legend>
                <div class="bo-ta-form__radio-grid" role="radiogroup" aria-label="Type d’annonce">
                    <?php foreach ($kindOptions as $value => $meta): ?>
                    <label class="bo-ta-form__radio-card">
                        <input type="radio" name="kind" value="<?= $h($value) ?>" data-default-color="<?= $h($meta['color']) ?>" <?= $currentKind === $value ? 'checked' : '' ?>>
                        <span class="bo-ta-form__radio-dot" style="background:<?= $h($meta['color']) ?>"></span>
                        <span class="bo-ta-form__radio-copy">
                            <span class="bo-ta-form__radio-title"><?= $h($meta['label']) ?></span>
                            <span class="bo-ta-form__radio-hint"><?= $h($meta['hint']) ?></span>
                        </span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <fieldset class="bo-ta-form__fieldset">
                <legend class="ath-users-filters__label">Emplacement d’affichage</legend>
                <div class="bo-ta-form__radio-grid bo-ta-form__radio-grid--2" role="radiogroup" aria-label="Emplacement d’affichage">
                    <?php foreach ($displayOptions as $value => $meta): ?>
                    <label class="bo-ta-form__radio-card">
                        <input type="radio" name="display_style" value="<?= $h($value) ?>" <?= $currentDisplay === $value ? 'checked' : '' ?>>
                        <span class="bo-ta-form__radio-copy">
                            <span class="bo-ta-form__radio-title"><?= $h($meta['label']) ?></span>
                            <span class="bo-ta-form__radio-hint"><?= $h($meta['hint']) ?></span>
                        </span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <div class="bo-ta-form__preview-row">
                <label class="ath-users-filters__label" for="ta-accent">Couleur d’accent
                    <input id="ta-accent" type="color" name="accent_color" value="<?= $h($currentColor) ?>" class="bo-ta-form__color">
                </label>
                <div class="bo-ta-preview" id="ta-live-preview">
                    <?php if ($bannerUrl): ?>
                    <img src="<?= $h($bannerUrl) ?>" alt="" class="bo-ta-preview__banner" id="ta-preview-banner">
                    <?php else: ?>
                    <img src="" alt="" class="bo-ta-preview__banner hidden" id="ta-preview-banner">
                    <?php endif; ?>
                    <div class="bo-ta-preview__body" id="ta-preview-strip" style="border-left:4px solid <?= $h($currentColor) ?>">
                        <div class="bo-ta-preview__icon" id="ta-preview-icon" style="background:<?= $h($currentColor) ?>"><?= $iconSvg[$currentIcon] ?? $iconSvg['info'] ?></div>
                        <div>
                            <p class="bo-ta-preview__kind" id="ta-preview-kind"><?= $h($kindOptions[$currentKind]['label']) ?></p>
                            <p class="bo-ta-preview__title" id="ta-preview-title"><?= $h((string) ($row['title'] ?? 'Titre de l’annonce')) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <fieldset class="bo-ta-form__fieldset">
                <legend class="ath-users-filters__label">Icône</legend>
                <div class="bo-ta-form__icon-grid" role="radiogroup" aria-label="Icône">
                    <?php foreach ($iconLabels as $ikey => $ilabel): ?>
                    <label class="bo-ta-form__icon-card">
                        <input type="radio" name="icon_key" value="<?= $h($ikey) ?>" class="sr-only" <?= $currentIcon === $ikey ? 'checked' : '' ?>>
                        <span class="bo-ta-form__icon-svg"><?= $iconSvg[$ikey] ?? $iconSvg['info'] ?></span>
                        <span class="bo-ta-form__icon-label"><?= $h($ilabel) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
        </div>

        <div class="bo-ta-form__section">
            <h2 class="ath-section-title">Contenu</h2>
            <label class="ath-users-filters__label" for="ta-title">Titre
                <input id="ta-title" type="text" name="title" required maxlength="255" value="<?= $h((string) ($row['title'] ?? '')) ?>" placeholder="Ex. Maintenance du forum ce week-end">
            </label>
            <label class="ath-users-filters__label" for="ta-body">Message <span class="bo-ta-form__opt">(facultatif)</span>
                <textarea id="ta-body" name="body" rows="4" placeholder="Précisez le contexte pour vos membres…"><?= $h((string) ($row['body'] ?? '')) ?></textarea>
            </label>
        </div>

        <div class="bo-ta-form__section">
            <h2 class="ath-section-title">Images</h2>
            <p class="ath-body">JPG, PNG ou WebP — 12 Mo max.</p>
            <div class="bo-ta-form__grid-2">
                <div>
                    <label class="ath-users-filters__label" for="ta-image">Image / vignette</label>
                    <?php if ($imageUrl): ?>
                    <img src="<?= $h($imageUrl) ?>" alt="" class="bo-ta-form__thumb">
                    <label class="ath-users-filters__check"><input type="checkbox" name="remove_image" value="1"> Retirer l’image actuelle</label>
                    <?php endif; ?>
                    <input id="ta-image" type="file" name="image_file" accept="image/jpeg,image/png,image/webp">
                </div>
                <div>
                    <label class="ath-users-filters__label" for="ta-banner">Bannière</label>
                    <?php if ($bannerUrl): ?>
                    <img src="<?= $h($bannerUrl) ?>" alt="" class="bo-ta-form__banner-thumb">
                    <label class="ath-users-filters__check"><input type="checkbox" name="remove_banner" value="1"> Retirer la bannière actuelle</label>
                    <?php endif; ?>
                    <input id="ta-banner" type="file" name="banner_file" accept="image/jpeg,image/png,image/webp">
                </div>
            </div>
        </div>

        <div class="bo-ta-form__section">
            <h2 class="ath-section-title">Action</h2>
            <div class="bo-ta-form__grid-2">
                <label class="ath-users-filters__label" for="ta-cta-label">Libellé du bouton
                    <input id="ta-cta-label" type="text" name="cta_label" value="<?= $h((string) ($row['cta_label'] ?? '')) ?>" placeholder="Ex. En savoir plus">
                </label>
                <label class="ath-users-filters__label" for="ta-cta-url">Adresse du lien
                    <input id="ta-cta-url" type="text" name="cta_url" value="<?= $h((string) ($row['cta_url'] ?? '')) ?>" placeholder="Page du forum, formations…">
                </label>
            </div>
            <label class="ath-users-filters__label" for="ta-coupon">Code avantage <span class="bo-ta-form__opt">(facultatif)</span>
                <input id="ta-coupon" type="text" name="coupon_code" maxlength="64" value="<?= $h((string) ($row['coupon_code'] ?? '')) ?>" placeholder="Ex. BIENVENUE2026">
            </label>
        </div>

        <div class="bo-ta-form__section">
            <h2 class="ath-section-title">Options</h2>
            <div class="bo-ta-form__features">
                <?php foreach (TenantAlertFeatures::definitions() as $fkey => $fmeta): ?>
                <label class="bo-ta-form__feature">
                    <input type="hidden" name="feature_<?= $h($fkey) ?>" value="0">
                    <input type="checkbox" name="feature_<?= $h($fkey) ?>" value="1" <?= !empty($currentFeatures[$fkey]) ? 'checked' : '' ?>>
                    <span class="bo-ta-form__feature-copy">
                        <span class="bo-ta-form__feature-title"><?= $h($fmeta['label']) ?></span>
                        <span class="bo-ta-form__feature-hint"><?= $h($fmeta['hint']) ?></span>
                    </span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="bo-ta-form__section">
            <h2 class="ath-section-title">Diffusion</h2>
            <div class="bo-ta-form__grid-2">
                <label class="ath-users-filters__label" for="ta-starts">Début <span class="bo-ta-form__opt">(facultatif)</span>
                    <input id="ta-starts" type="datetime-local" name="starts_at" value="<?= $h($dt(isset($row['starts_at']) ? (string) $row['starts_at'] : null)) ?>">
                </label>
                <label class="ath-users-filters__label" for="ta-ends">Fin <span class="bo-ta-form__opt">(facultatif)</span>
                    <input id="ta-ends" type="datetime-local" name="ends_at" value="<?= $h($dt(isset($row['ends_at']) ? (string) $row['ends_at'] : null)) ?>">
                </label>
            </div>
            <label class="ath-users-filters__label" for="ta-order">Ordre d’affichage
                <input id="ta-order" type="number" name="sort_order" value="<?= (int) ($row['sort_order'] ?? 0) ?>" class="bo-ta-form__order">
            </label>
            <label class="ath-users-filters__check">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" id="ta_is_active" <?= ($row === null || !empty($row['is_active'])) ? 'checked' : '' ?>>
                Annonce active
            </label>
        </div>

        <div class="bo-ta-form__actions">
            <button type="submit" class="ath-btn ath-btn--solid"><?= $isEdit ? 'Enregistrer les modifications' : 'Créer l’annonce' ?></button>
            <a href="<?= $h(url('back-office/alerts')) ?>" class="ath-btn">Annuler</a>
        </div>
    </form>
</div>
<script>
(function () {
  var form = document.getElementById('ta-alert-form');
  if (!form) return;
  var accent = document.getElementById('ta-accent');
  var title = document.getElementById('ta-title');
  var previewTitle = document.getElementById('ta-preview-title');
  var previewKind = document.getElementById('ta-preview-kind');
  var previewStrip = document.getElementById('ta-preview-strip');
  var previewIcon = document.getElementById('ta-preview-icon');
  var previewImage = document.getElementById('ta-preview-image');
  var previewBanner = document.getElementById('ta-preview-banner');
  var iconSvgs = <?= json_encode($iconSvg, JSON_UNESCAPED_UNICODE) ?>;
  var kindLabels = <?= json_encode(array_map(static fn ($m) => $m['label'], $kindOptions), JSON_UNESCAPED_UNICODE) ?>;

  function syncColor(c) {
    if (!c) return;
    if (previewStrip) previewStrip.style.borderLeftColor = c;
    if (previewIcon) previewIcon.style.background = c;
  }
  function syncKind() {
    var checked = form.querySelector('input[name="kind"]:checked');
    if (!checked) return;
    if (previewKind) previewKind.textContent = kindLabels[checked.value] || 'Annonce';
    var def = checked.getAttribute('data-default-color');
    if (accent && def && !accent.dataset.userTouched) {
      accent.value = def;
      syncColor(def);
    }
  }
  function syncIcon() {
    var checked = form.querySelector('input[name="icon_key"]:checked');
    var key = checked ? checked.value : 'auto';
    if (key === 'auto') {
      var kind = form.querySelector('input[name="kind"]:checked');
      key = kind ? kind.value : 'info';
      if (!iconSvgs[key]) key = 'info';
    }
    if (previewIcon) previewIcon.innerHTML = iconSvgs[key] || iconSvgs.info;
  }
  function previewFile(input, imgEl) {
    if (!input || !imgEl || !input.files || !input.files[0]) return;
    imgEl.src = URL.createObjectURL(input.files[0]);
    imgEl.classList.remove('hidden');
  }

  form.querySelectorAll('input[name="kind"]').forEach(function (el) {
    el.addEventListener('change', function () { syncKind(); syncIcon(); });
  });
  form.querySelectorAll('input[name="icon_key"]').forEach(function (el) {
    el.addEventListener('change', syncIcon);
  });
  if (accent) {
    accent.addEventListener('input', function () {
      accent.dataset.userTouched = '1';
      syncColor(accent.value);
    });
  }
  if (title && previewTitle) {
    title.addEventListener('input', function () {
      previewTitle.textContent = title.value.trim() || 'Titre de l’annonce';
    });
  }
  var imageInput = document.getElementById('ta-image');
  var bannerInput = document.getElementById('ta-banner');
  if (imageInput && previewImage) imageInput.addEventListener('change', function () { previewFile(imageInput, previewImage); });
  if (bannerInput && previewBanner) bannerInput.addEventListener('change', function () { previewFile(bannerInput, previewBanner); });

  syncColor(accent ? accent.value : null);
  syncKind();
  syncIcon();
})();
</script>
