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

$currentKind = (string) ($row['kind'] ?? 'info');
$kindOptions = TenantAlertVisuals::kindsForTenantForm($isEdit ? $currentKind : null);
if (!isset($kindOptions[$currentKind])) {
    $currentKind = 'info';
}
$iconLabels = TenantAlertVisuals::iconLabels();
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

$mainKinds = array_values(array_filter(['info', 'notice', 'event', 'urgent'], static fn (string $k): bool => isset($kindOptions[$k])));
if (!in_array($currentKind, $mainKinds, true)) {
    $mainKinds[] = $currentKind;
}
$otherKinds = array_values(array_diff(array_keys($kindOptions), $mainKinds));
$mainPlacements = array_values(array_filter([AlertDisplayStyle::CLASSIC, AlertDisplayStyle::IMPORTANT, AlertDisplayStyle::POPUP], static fn (string $k): bool => isset($displayOptions[$k])));
if (!in_array($currentDisplay, $mainPlacements, true) && isset($displayOptions[$currentDisplay])) {
    $mainPlacements[] = $currentDisplay;
}
$otherPlacements = array_values(array_diff(array_keys($displayOptions), $mainPlacements));
$placementShort = [
    AlertDisplayStyle::CLASSIC => ['Dans les annonces', 'Une carte sous « Bonjour » sur le tableau de bord. Le bon choix la plupart du temps.'],
    AlertDisplayStyle::IMPORTANT => ['Barre en haut du site', 'Barre jaune sur toutes les pages, pour ce qui ne peut pas attendre.'],
    AlertDisplayStyle::POPUP => ['Fenêtre à l’arrivée', 'S’ouvre une fois par membre sur le tableau de bord.'],
];
/* Familles de couleur du bandeau du tableau de bord (views/partials/announce_tiles.php). */
$tileKindMap = ['security' => 'urgent', 'maintenance' => 'notice', 'event' => 'novelty', 'training' => 'novelty', 'recruitment' => 'novelty'];
$startsValue = $dt(isset($row['starts_at']) ? (string) $row['starts_at'] : null);
$endsValue = $dt(isset($row['ends_at']) ? (string) $row['ends_at'] : null);
$hasAdvancedValues = $imageUrl || $bannerUrl || trim((string) ($row['coupon_code'] ?? '')) !== '' || $currentIcon !== 'auto' || (int) ($row['sort_order'] ?? 0) !== 0 || in_array($currentDisplay, $otherPlacements, true);

$previewToneMap = [
    'urgent' => 'error', 'security' => 'error', 'discount' => 'warning', 'maintenance' => 'warning',
    'novelty' => 'info', 'event' => 'info', 'notice' => 'info', 'training' => 'info',
    'recruitment' => 'info', 'info' => 'info',
];
$previewTone = $previewToneMap[$currentKind] ?? 'info';

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
?>

<div class="bo-ta bo-ta-form bo-ta2 ath-rise">
    <div class="ath-users-filters ath-rise">
        <a href="<?= $h(url('back-office/alerts')) ?>" class="ath-btn">← Liste des annonces</a>
    </div>

    <form method="<?= $h((string) ($formMethod ?? 'post')) ?>" action="<?= $h((string) ($formAction ?? '')) ?>" enctype="multipart/form-data" class="bo-ta2__layout" id="ta-alert-form">
        <?= \App\Core\Csrf::field() ?>
        <div class="bo-ta2__main">

            <section class="ath-card bo-ta2__step">
                <h2 class="bo-ta2__step-title"><span class="bo-ta2__num">1</span> Le message</h2>
                <label class="bo-ta2__field" for="ta-title">
                    <span class="bo-ta2__label">Titre</span>
                    <input id="ta-title" type="text" name="title" required maxlength="255" value="<?= $h((string) ($row['title'] ?? '')) ?>" placeholder="Ex. OP Tempête samedi 20 h 30">
                    <small class="bo-ta2__hint">Court et concret : on le lit en une seconde.</small>
                </label>
                <label class="bo-ta2__field" for="ta-body">
                    <span class="bo-ta2__label">Détails <span class="bo-ta-form__opt">(facultatif)</span></span>
                    <textarea id="ta-body" name="body" rows="3" placeholder="Briefing à 20 h sur le TeamSpeak, tenue désert."><?= $h((string) ($row['body'] ?? '')) ?></textarea>
                </label>
                <div class="bo-ta2__row">
                    <label class="bo-ta2__field" for="ta-cta-label">
                        <span class="bo-ta2__label">Texte du bouton <span class="bo-ta-form__opt">(facultatif)</span></span>
                        <input id="ta-cta-label" type="text" name="cta_label" value="<?= $h((string) ($row['cta_label'] ?? '')) ?>" placeholder="S’inscrire">
                    </label>
                    <label class="bo-ta2__field" for="ta-cta-url">
                        <span class="bo-ta2__label">Lien du bouton</span>
                        <input id="ta-cta-url" type="text" name="cta_url" value="<?= $h((string) ($row['cta_url'] ?? '')) ?>" placeholder="Page de l’événement, d’une formation…">
                    </label>
                </div>
            </section>

            <section class="ath-card bo-ta2__step">
                <h2 class="bo-ta2__step-title"><span class="bo-ta2__num">2</span> Son importance</h2>
                <div class="bo-ta2__choices" role="radiogroup" aria-label="Type d’annonce">
                    <?php foreach ($mainKinds as $value): $meta = $kindOptions[$value]; ?>
                    <label class="bo-ta2__choice bo-ta2__choice--<?= $h($tileKindMap[$value] ?? $value) ?>">
                        <input type="radio" name="kind" value="<?= $h($value) ?>" data-default-color="<?= $h($meta['color']) ?>" <?= $currentKind === $value ? 'checked' : '' ?>>
                        <span class="bo-ta2__choice-title"><?= $h($meta['label']) ?></span>
                        <span class="bo-ta2__choice-hint"><?= $h($meta['hint']) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <?php if ($otherKinds !== []): ?>
                <details class="bo-ta2__more"<?= in_array($currentKind, $otherKinds, true) ? ' open' : '' ?>>
                    <summary>Autres types (<?= count($otherKinds) ?>)</summary>
                    <div class="bo-ta2__choices bo-ta2__choices--small" role="radiogroup" aria-label="Autres types d’annonce">
                        <?php foreach ($otherKinds as $value): $meta = $kindOptions[$value]; ?>
                        <label class="bo-ta2__choice bo-ta2__choice--<?= $h($tileKindMap[$value] ?? $value) ?>">
                            <input type="radio" name="kind" value="<?= $h($value) ?>" data-default-color="<?= $h($meta['color']) ?>" <?= $currentKind === $value ? 'checked' : '' ?>>
                            <span class="bo-ta2__choice-title"><?= $h($meta['label']) ?></span>
                            <span class="bo-ta2__choice-hint"><?= $h($meta['hint']) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </details>
                <?php endif; ?>
            </section>

            <section class="ath-card bo-ta2__step">
                <h2 class="bo-ta2__step-title"><span class="bo-ta2__num">3</span> Où et combien de temps</h2>
                <div class="bo-ta2__choices bo-ta2__choices--3" role="radiogroup" aria-label="Emplacement d’affichage">
                    <?php foreach ($mainPlacements as $value): $meta = $displayOptions[$value]; $short = $placementShort[$value] ?? [$meta['label'], $meta['hint']]; ?>
                    <label class="bo-ta2__choice">
                        <input type="radio" name="display_style" value="<?= $h($value) ?>" <?= $currentDisplay === $value ? 'checked' : '' ?>>
                        <span class="bo-ta2__choice-title"><?= $h($short[0]) ?></span>
                        <span class="bo-ta2__choice-hint"><?= $h($short[1]) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <div class="bo-ta2__durations" role="group" aria-label="Durée d’affichage">
                    <span class="bo-ta2__label">Afficher</span>
                    <button type="button" class="bo-ta2__pill" data-ta-duration="0">Jusqu’à ce que je la retire</button>
                    <button type="button" class="bo-ta2__pill" data-ta-duration="24">24 heures</button>
                    <button type="button" class="bo-ta2__pill" data-ta-duration="72">3 jours</button>
                    <button type="button" class="bo-ta2__pill" data-ta-duration="168">1 semaine</button>
                </div>
                <div class="bo-ta2__row">
                    <label class="bo-ta2__field" for="ta-starts">
                        <span class="bo-ta2__label">À partir du <span class="bo-ta-form__opt">(vide = tout de suite)</span></span>
                        <input id="ta-starts" type="datetime-local" name="starts_at" value="<?= $h($startsValue) ?>">
                    </label>
                    <label class="bo-ta2__field" for="ta-ends">
                        <span class="bo-ta2__label">Jusqu’au <span class="bo-ta-form__opt">(vide = sans fin)</span></span>
                        <input id="ta-ends" type="datetime-local" name="ends_at" value="<?= $h($endsValue) ?>">
                    </label>
                </div>
                <div class="bo-ta2__toggles">
                    <label class="bo-ta2__toggle">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" id="ta_is_active" <?= ($row === null || !empty($row['is_active'])) ? 'checked' : '' ?>>
                        <span>En ligne</span>
                    </label>
                    <?php foreach (TenantAlertFeatures::definitions() as $fkey => $fmeta): ?>
                    <label class="bo-ta2__toggle" title="<?= $h($fmeta['hint']) ?>">
                        <input type="hidden" name="feature_<?= $h($fkey) ?>" value="0">
                        <input type="checkbox" name="feature_<?= $h($fkey) ?>" value="1" <?= !empty($currentFeatures[$fkey]) ? 'checked' : '' ?>>
                        <span><?= $h($fmeta['label']) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </section>

            <details class="ath-card bo-ta2__step bo-ta2__advanced"<?= $hasAdvancedValues ? ' open' : '' ?>>
                <summary class="bo-ta2__step-title">Options avancées <span class="bo-ta-form__opt">(images, icône, couleur, autres emplacements…)</span></summary>

                <?php if ($otherPlacements !== []): ?>
                <fieldset class="bo-ta-form__fieldset">
                    <legend class="bo-ta2__label">Autres emplacements</legend>
                    <div class="bo-ta2__choices bo-ta2__choices--small" role="radiogroup" aria-label="Autres emplacements">
                        <?php foreach ($otherPlacements as $value): $meta = $displayOptions[$value]; ?>
                        <label class="bo-ta2__choice">
                            <input type="radio" name="display_style" value="<?= $h($value) ?>" <?= $currentDisplay === $value ? 'checked' : '' ?>>
                            <span class="bo-ta2__choice-title"><?= $h($meta['label']) ?></span>
                            <span class="bo-ta2__choice-hint"><?= $h($meta['hint']) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <?php endif; ?>

                <fieldset class="bo-ta-form__fieldset">
                    <legend class="bo-ta2__label">Icône</legend>
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

                <div class="bo-ta2__row">
                    <label class="bo-ta2__field" for="ta-accent">
                        <span class="bo-ta2__label">Couleur d’accent</span>
                        <input id="ta-accent" type="color" name="accent_color" value="<?= $h($currentColor) ?>" class="bo-ta-form__color">
                    </label>
                    <label class="bo-ta2__field" for="ta-coupon">
                        <span class="bo-ta2__label">Code avantage</span>
                        <input id="ta-coupon" type="text" name="coupon_code" maxlength="64" value="<?= $h((string) ($row['coupon_code'] ?? '')) ?>" placeholder="Ex. BIENVENUE2026">
                    </label>
                    <label class="bo-ta2__field" for="ta-order">
                        <span class="bo-ta2__label">Ordre d’affichage</span>
                        <input id="ta-order" type="number" name="sort_order" value="<?= (int) ($row['sort_order'] ?? 0) ?>" class="bo-ta-form__order">
                    </label>
                </div>

                <p class="bo-ta2__hint">Images : JPG, PNG ou WebP, 12 Mo max.</p>
                <div class="bo-ta2__row">
                    <div class="bo-ta2__field">
                        <label class="bo-ta2__label" for="ta-image">Vignette</label>
                        <?php if ($imageUrl): ?>
                        <img src="<?= $h($imageUrl) ?>" alt="" class="bo-ta-form__thumb">
                        <label class="ath-users-filters__check"><input type="checkbox" name="remove_image" value="1"> Retirer l’image actuelle</label>
                        <?php endif; ?>
                        <input id="ta-image" type="file" name="image_file" accept="image/jpeg,image/png,image/webp">
                    </div>
                    <div class="bo-ta2__field">
                        <label class="bo-ta2__label" for="ta-banner">Bannière</label>
                        <?php if ($bannerUrl): ?>
                        <img src="<?= $h($bannerUrl) ?>" alt="" class="bo-ta-form__banner-thumb">
                        <label class="ath-users-filters__check"><input type="checkbox" name="remove_banner" value="1"> Retirer la bannière actuelle</label>
                        <?php endif; ?>
                        <input id="ta-banner" type="file" name="banner_file" accept="image/jpeg,image/png,image/webp">
                    </div>
                </div>
            </details>
        </div>

        <aside class="bo-ta2__aside" aria-label="Aperçu">
            <p class="bo-ta2__aside-kicker">Aperçu en direct</p>
            <p class="bo-ta2__aside-where" id="ta-pv-where">Tableau de bord des membres, sous « Bonjour ».</p>

            <div class="bo-ta2__pv" id="ta-live-preview" aria-live="polite">
                <div class="bo-ta2__pv-bar" id="ta-pv-bar" hidden>
                    <strong id="ta-pv-bar-title"></strong> <span id="ta-pv-bar-cta"></span>
                </div>
                <div class="bo-ta2__pv-stage" id="ta-pv-stage">
                    <div class="dash-announce is-open bo-ta2__pv-dash">
                        <div class="dash-announce__grid" style="grid-template-columns:1fr">
                            <div class="dash-announce-tile" id="ta-pv-tile">
                                <span class="dash-announce-tile__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path id="ta-pv-icon" d="M12 16v-4m0-4h.01M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0Z"/></svg></span>
                                <span class="dash-announce-tile__panel">
                                    <span class="dash-announce-tile__meta"><span class="dash-announce-tile__kind" id="ta-preview-kind"></span></span>
                                    <span class="dash-announce-tile__title" id="ta-preview-title"></span>
                                    <span class="dash-announce-tile__body" id="ta-preview-body"></span>
                                    <span class="dash-announce-tile__cta" id="ta-preview-cta"></span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <p class="bo-ta2__aside-when" id="ta-pv-when"></p>

            <div class="bo-ta2__submit">
                <button type="submit" class="ath-btn ath-btn--solid"><?= $isEdit ? 'Enregistrer les modifications' : 'Publier l’annonce' ?></button>
                <a href="<?= $h(url('back-office/alerts')) ?>" class="ath-btn">Annuler</a>
            </div>
        </aside>
    </form>
</div>
<script>
(function () {
  var form = document.getElementById('ta-alert-form');
  if (!form) return;
  var $ = function (id) { return document.getElementById(id); };
  var kindLabels = <?= json_encode(array_map(static fn ($m) => $m['label'], $kindOptions), JSON_UNESCAPED_UNICODE) ?>;
  var tileKinds = <?= json_encode($tileKindMap, JSON_UNESCAPED_UNICODE) ?>;
  var icons = {
    urgent: 'M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z',
    notice: 'M12 17v5M9 10.8V4h6v6.8l2.6 3.2H6.4L9 10.8Z',
    novelty: 'm12 3 2.4 5.6L20 9.3l-4.3 3.9 1.3 5.8L12 16l-5 3 1.3-5.8L4 9.3l5.6-.7L12 3Z',
    discount: 'M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8ZM7.5 7.5h.01',
    info: 'M12 16v-4m0-4h.01M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0Z'
  };
  var whereText = {
    classic: 'Tableau de bord des membres, sous « Bonjour ».',
    important: 'Barre jaune en haut de toutes les pages du site.',
    popup: 'Fenêtre ouverte une fois par membre en arrivant sur le tableau de bord.',
    activity_feed: 'Dans « Mon activité » de chaque membre.',
    members_only: 'Espace membre uniquement, jamais sur les pages publiques.',
    back_office: 'Visible seulement des responsables, dans le back-office.'
  };
  var pad = function (n) { return (n < 10 ? '0' : '') + n; };
  var toLocal = function (d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes()); };
  var fmt = function (v) {
    if (!v) return '';
    var d = new Date(v);
    if (isNaN(d.getTime())) return '';
    return d.toLocaleDateString('fr-FR', { weekday: 'short', day: 'numeric', month: 'short' }) + ' à ' + pad(d.getHours()) + ' h ' + pad(d.getMinutes());
  };

  function render() {
    var kindEl = form.querySelector('input[name="kind"]:checked');
    var kind = kindEl ? kindEl.value : 'info';
    var tile = tileKinds[kind] || kind;
    if (!icons[tile]) tile = 'info';
    var place = (form.querySelector('input[name="display_style"]:checked') || {}).value || 'classic';
    var title = $('ta-title').value.trim() || 'Titre de l’annonce';
    var body = $('ta-body').value.trim();
    var cta = $('ta-cta-label').value.trim();
    var url = $('ta-cta-url').value.trim();

    $('ta-pv-tile').className = 'dash-announce-tile dash-announce-tile--' + tile;
    $('ta-pv-icon').setAttribute('d', icons[tile]);
    $('ta-preview-kind').textContent = kindLabels[kind] || 'Information';
    $('ta-preview-title').textContent = title;
    $('ta-preview-body').textContent = body;
    $('ta-preview-body').hidden = body === '';
    $('ta-preview-cta').textContent = (cta || (url ? 'Ouvrir' : '')) + ((cta || url) ? ' →' : '');
    $('ta-preview-cta').hidden = !(cta || url);

    var isBar = place === 'important';
    $('ta-pv-bar').hidden = !isBar;
    $('ta-pv-stage').hidden = isBar;
    $('ta-pv-bar-title').textContent = title;
    $('ta-pv-bar-cta').textContent = cta ? cta + ' →' : '';
    $('ta-live-preview').classList.toggle('is-popup', place === 'popup');
    $('ta-pv-where').textContent = whereText[place] || whereText.classic;

    var s = fmt($('ta-starts').value), e = fmt($('ta-ends').value);
    var active = $('ta_is_active').checked;
    $('ta-pv-when').textContent = !active ? 'Hors ligne : personne ne la voit pour l’instant.'
      : (s ? 'Visible à partir du ' + s : 'Visible dès la publication') + (e ? ', jusqu’au ' + e + '.' : ', sans date de fin.');
  }

  form.querySelectorAll('[data-ta-duration]').forEach(function (b) {
    b.addEventListener('click', function () {
      var hours = parseInt(b.getAttribute('data-ta-duration'), 10);
      if (!hours) { $('ta-ends').value = ''; render(); return; }
      var start = $('ta-starts').value ? new Date($('ta-starts').value) : new Date();
      if (isNaN(start.getTime())) start = new Date();
      $('ta-ends').value = toLocal(new Date(start.getTime() + hours * 3600000));
      render();
    });
  });
  form.addEventListener('input', render);
  form.addEventListener('change', render);
  render();
})();
</script>
