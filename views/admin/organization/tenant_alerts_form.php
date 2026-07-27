<?php
declare(strict_types=1);

if (!empty($isBackOfficeShell)) {
    require base_path('views/partials/ath_tenant_alerts_form.php');
    return;
}

use App\Support\TenantAlertVisuals;

/** @var array<string, mixed>|null $tenantAlert */
/** @var string $formAction */
/** @var string $formMethod */
$row = $tenantAlert;
$isEdit = $row !== null;
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

$isAthShell = !empty($isBackOfficeShell);

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
    'training' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>',
    'recruitment' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/></svg>',
    'security' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>',
];
$currentFeatures = \App\Support\TenantAlertFeatures::decodeJson($row['features_json'] ?? null);
?>
<style>
.ta-kind-card:has(input:checked),
.ta-icon-card:has(input:checked) {
    border-color: #059669 !important;
    background: #ecfdf5 !important;
    box-shadow: 0 0 0 1px rgba(5, 150, 105, 0.35);
}
.ta-icon-card svg { width: 1.25rem; height: 1.25rem; }
.ta-preview {
    border-radius: 0.85rem;
    border: 1px solid #e2e8f0;
    overflow: hidden;
    background: #fff;
}
.ta-preview__banner {
    display: block;
    width: 100%;
    max-height: 9rem;
    object-fit: cover;
    background: #0f172a;
}
.ta-preview__body {
    display: flex;
    gap: 0.75rem;
    padding: 0.85rem 1rem;
    align-items: flex-start;
}
.ta-preview__thumb {
    width: 3.25rem;
    height: 3.25rem;
    border-radius: 0.65rem;
    object-fit: cover;
    flex-shrink: 0;
    border: 1px solid #e2e8f0;
}
.ta-preview__icon {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 0.65rem;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    color: #fff;
}
.ta-preview__icon svg { width: 1.25rem; height: 1.25rem; }
</style>
<div class="min-h-0 flex-1 bg-slate-50<?= $isAthShell ? ' ta-alerts-form--ath' : '' ?>">
    <div class="w-full px-4 sm:px-5 lg:px-6 py-4 sm:py-5 space-y-5">

        <?php if ($isAthShell): ?>
        <div class="flex flex-wrap gap-2 ath-rise">
            <a href="<?= url('back-office/alerts') ?>" class="ath-btn">← Liste des annonces</a>
            <a href="<?= url('back-office/alerts/create') ?>" class="ath-btn<?= !$isEdit ? ' ath-btn--solid' : '' ?>">Nouvelle annonce</a>
        </div>
        <?php endif; ?>

        <header class="ta-alerts__hero relative overflow-hidden rounded-xl border border-emerald-200/90 bg-gradient-to-br from-emerald-50/90 via-white to-slate-50 shadow-sm">
            <div class="absolute inset-y-0 left-0 w-1 bg-emerald-600" aria-hidden="true"></div>
            <div class="relative px-4 sm:px-6 py-5 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.22em] text-emerald-800/90">Back-office · Communauté</p>
                    <h1 class="mt-1.5 text-2xl font-black tracking-tight text-slate-900"><?= $isEdit ? 'Modifier l’annonce' : 'Nouvelle annonce' ?></h1>
                    <p class="mt-2 text-sm text-slate-600 leading-relaxed max-w-xl">
                        Type, couleur, icône, image et bannière — le bandeau s’adapte à votre message.
                    </p>
                </div>
                <a href="<?= url('back-office/alerts') ?>" class="shrink-0 inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">← Liste des annonces</a>
            </div>
        </header>

        <?php $s = \App\Core\Session::getFlash('success'); $e = \App\Core\Session::getFlash('error'); ?>
        <?php if ($s): ?>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800" role="status"><?= htmlspecialchars($s) ?></div>
        <?php endif; ?>
        <?php if ($e): ?>
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800" role="alert"><?= htmlspecialchars($e) ?></div>
        <?php endif; ?>

        <form method="<?= htmlspecialchars($formMethod) ?>" action="<?= htmlspecialchars($formAction) ?>" enctype="multipart/form-data" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" id="ta-alert-form">
            <?= \App\Core\Csrf::field() ?>

            <div class="border-b border-slate-100 bg-slate-50/80 px-5 sm:px-6 py-4">
                <h2 class="text-sm font-black uppercase tracking-[0.12em] text-slate-800">Type &amp; apparence</h2>
            </div>
            <div class="p-5 sm:p-6 space-y-6">
                <fieldset>
                    <legend class="block text-sm font-semibold text-slate-800 mb-3">Type d’annonce</legend>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" role="radiogroup" aria-label="Type d’annonce">
                        <?php foreach ($kindOptions as $value => $meta): ?>
                            <label class="ta-kind-card flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-white p-3.5 shadow-sm transition hover:border-emerald-300">
                                <input type="radio" name="kind" value="<?= htmlspecialchars($value) ?>" class="mt-1 border-slate-300 text-emerald-600 focus:ring-emerald-500" data-default-color="<?= htmlspecialchars($meta['color']) ?>" <?= $currentKind === $value ? 'checked' : '' ?>>
                                <span class="min-w-0">
                                    <span class="flex items-center gap-2">
                                        <span class="inline-block h-2.5 w-2.5 rounded-full shrink-0" style="background:<?= htmlspecialchars($meta['color']) ?>"></span>
                                        <span class="font-semibold text-slate-900"><?= htmlspecialchars($meta['label']) ?></span>
                                    </span>
                                    <span class="mt-0.5 block text-xs text-slate-500 leading-relaxed"><?= htmlspecialchars($meta['hint']) ?></span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <?php
                $tenantDisplayOptions = \App\Support\AlertDisplayStyle::tenantOptionsWithMeta();
                $currentTenantDisplay = \App\Support\AlertDisplayStyle::sanitizeTenant(
                    isset($row['display_style']) ? (string) $row['display_style'] : null
                );
                ?>
                <fieldset>
                    <legend class="block text-sm font-semibold text-slate-800 mb-3">Emplacement d’affichage</legend>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" role="radiogroup" aria-label="Emplacement d’affichage">
                        <?php foreach ($tenantDisplayOptions as $value => $meta): ?>
                            <label class="ta-kind-card flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-white p-3.5 shadow-sm transition hover:border-emerald-300">
                                <input type="radio" name="display_style" value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" class="mt-1 border-slate-300 text-emerald-600 focus:ring-emerald-500" <?= $currentTenantDisplay === $value ? 'checked' : '' ?>>
                                <span class="min-w-0">
                                    <span class="font-semibold text-slate-900"><?= htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="mt-0.5 block text-xs text-slate-500 leading-relaxed"><?= htmlspecialchars($meta['hint'], ENT_QUOTES, 'UTF-8') ?></span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="ta-accent" class="block text-sm font-semibold text-slate-800 mb-1.5">Couleur d’accent</label>
                        <div class="flex items-center gap-3">
                            <input id="ta-accent" type="color" name="accent_color" value="<?= htmlspecialchars($currentColor) ?>" class="h-11 w-14 rounded-lg border border-slate-300 cursor-pointer bg-white">
                            <p class="text-xs text-slate-500 leading-relaxed">Bordure et icône du bandeau. Vous pouvez garder la couleur du type ou la personnaliser.</p>
                        </div>
                    </div>
                    <div>
                        <p class="block text-sm font-semibold text-slate-800 mb-1.5">Aperçu rapide</p>
                        <div class="ta-preview" id="ta-live-preview">
                            <?php if ($bannerUrl): ?>
                                <img src="<?= htmlspecialchars($bannerUrl) ?>" alt="" class="ta-preview__banner" id="ta-preview-banner">
                            <?php else: ?>
                                <img src="" alt="" class="ta-preview__banner hidden" id="ta-preview-banner">
                            <?php endif; ?>
                            <div class="ta-preview__body" id="ta-preview-strip" style="border-left:4px solid <?= htmlspecialchars($currentColor) ?>">
                                <?php if ($imageUrl): ?>
                                    <img src="<?= htmlspecialchars($imageUrl) ?>" alt="" class="ta-preview__thumb" id="ta-preview-image">
                                <?php else: ?>
                                    <img src="" alt="" class="ta-preview__thumb hidden" id="ta-preview-image">
                                <?php endif; ?>
                                <div class="ta-preview__icon" id="ta-preview-icon" style="background:<?= htmlspecialchars($currentColor) ?>"><?= $iconSvg[$currentIcon] ?? $iconSvg['info'] ?></div>
                                <div class="min-w-0">
                                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-500" id="ta-preview-kind"><?= htmlspecialchars($kindOptions[$currentKind]['label']) ?></p>
                                    <p class="text-sm font-bold text-slate-900" id="ta-preview-title"><?= htmlspecialchars((string) ($row['title'] ?? 'Titre de l’annonce')) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <fieldset>
                    <legend class="block text-sm font-semibold text-slate-800 mb-3">Icône</legend>
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-2" role="radiogroup" aria-label="Icône">
                        <?php foreach ($iconLabels as $ikey => $ilabel): ?>
                            <label class="ta-icon-card flex cursor-pointer flex-col items-center gap-1.5 rounded-xl border border-slate-200 bg-white p-2.5 text-center shadow-sm transition hover:border-emerald-300">
                                <input type="radio" name="icon_key" value="<?= htmlspecialchars($ikey) ?>" class="sr-only" <?= $currentIcon === $ikey ? 'checked' : '' ?>>
                                <span class="text-slate-700"><?= $iconSvg[$ikey] ?? $iconSvg['info'] ?></span>
                                <span class="text-[10px] font-bold uppercase tracking-wide text-slate-600"><?= htmlspecialchars($ilabel) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
            </div>

            <div class="border-y border-slate-100 bg-slate-50/80 px-5 sm:px-6 py-4">
                <h2 class="text-sm font-black uppercase tracking-[0.12em] text-slate-800">Contenu</h2>
            </div>
            <div class="p-5 sm:p-6 space-y-5">
                <div>
                    <label for="ta-title" class="block text-sm font-semibold text-slate-800 mb-1.5">Titre</label>
                    <input id="ta-title" type="text" name="title" required maxlength="255" value="<?= htmlspecialchars((string) ($row['title'] ?? '')) ?>"
                        class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none"
                        placeholder="Ex. Maintenance du forum ce week-end">
                </div>
                <div>
                    <label for="ta-body" class="block text-sm font-semibold text-slate-800 mb-1.5">Message <span class="font-normal text-slate-500">(facultatif)</span></label>
                    <textarea id="ta-body" name="body" rows="4"
                        class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none"
                        placeholder="Précisez le contexte pour vos membres…"><?= htmlspecialchars((string) ($row['body'] ?? '')) ?></textarea>
                </div>
            </div>

            <div class="border-y border-slate-100 bg-slate-50/80 px-5 sm:px-6 py-4">
                <h2 class="text-sm font-black uppercase tracking-[0.12em] text-slate-800">Images</h2>
                <p class="mt-0.5 text-xs text-slate-500">JPG, PNG ou WebP — 12 Mo max. La bannière s’affiche en haut du bandeau ; l’image en vignette à côté du texte.</p>
            </div>
            <div class="p-5 sm:p-6 grid gap-6 sm:grid-cols-2">
                <div>
                    <label for="ta-image" class="block text-sm font-semibold text-slate-800 mb-1.5">Image / vignette</label>
                    <?php if ($imageUrl): ?>
                        <img src="<?= htmlspecialchars($imageUrl) ?>" alt="" class="mb-3 h-24 w-24 rounded-xl object-cover border border-slate-200">
                        <label class="mb-2 flex items-center gap-2 text-xs font-semibold text-slate-600">
                            <input type="checkbox" name="remove_image" value="1" class="rounded border-slate-300 text-rose-600"> Retirer l’image actuelle
                        </label>
                    <?php endif; ?>
                    <input id="ta-image" type="file" name="image_file" accept="image/jpeg,image/png,image/webp"
                        class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-3 file:py-2 file:text-xs file:font-bold file:uppercase file:tracking-wide file:text-emerald-900 hover:file:bg-emerald-100">
                </div>
                <div>
                    <label for="ta-banner" class="block text-sm font-semibold text-slate-800 mb-1.5">Bannière</label>
                    <?php if ($bannerUrl): ?>
                        <img src="<?= htmlspecialchars($bannerUrl) ?>" alt="" class="mb-3 h-24 w-full max-w-sm rounded-xl object-cover border border-slate-200">
                        <label class="mb-2 flex items-center gap-2 text-xs font-semibold text-slate-600">
                            <input type="checkbox" name="remove_banner" value="1" class="rounded border-slate-300 text-rose-600"> Retirer la bannière actuelle
                        </label>
                    <?php endif; ?>
                    <input id="ta-banner" type="file" name="banner_file" accept="image/jpeg,image/png,image/webp"
                        class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-3 file:py-2 file:text-xs file:font-bold file:uppercase file:tracking-wide file:text-emerald-900 hover:file:bg-emerald-100">
                </div>
            </div>

            <div class="border-y border-slate-100 bg-slate-50/80 px-5 sm:px-6 py-4">
                <h2 class="text-sm font-black uppercase tracking-[0.12em] text-slate-800">Action</h2>
                <p class="mt-0.5 text-xs text-slate-500">Bouton optionnel vers une page du portail ou un site externe.</p>
            </div>
            <div class="p-5 sm:p-6 space-y-5">
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="ta-cta-label" class="block text-sm font-semibold text-slate-800 mb-1.5">Libellé du bouton</label>
                        <input id="ta-cta-label" type="text" name="cta_label" value="<?= htmlspecialchars((string) ($row['cta_label'] ?? '')) ?>"
                            class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none"
                            placeholder="Ex. En savoir plus">
                    </div>
                    <div>
                        <label for="ta-cta-url" class="block text-sm font-semibold text-slate-800 mb-1.5">Adresse du lien</label>
                        <input id="ta-cta-url" type="text" name="cta_url" value="<?= htmlspecialchars((string) ($row['cta_url'] ?? '')) ?>"
                            class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none"
                            placeholder="Page du forum, formations…">
                    </div>
                </div>
                <div>
                    <label for="ta-coupon" class="block text-sm font-semibold text-slate-800 mb-1.5">Code avantage <span class="font-normal text-slate-500">(facultatif)</span></label>
                    <input id="ta-coupon" type="text" name="coupon_code" maxlength="64" value="<?= htmlspecialchars((string) ($row['coupon_code'] ?? '')) ?>"
                        class="w-full max-w-xs rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none"
                        placeholder="Ex. BIENVENUE2026">
                </div>
            </div>

            <div class="border-y border-slate-100 bg-slate-50/80 px-5 sm:px-6 py-4">
                <h2 class="text-sm font-black uppercase tracking-[0.12em] text-slate-800">Options</h2>
                <p class="mt-0.5 text-xs text-slate-500">Comportement de l’annonce une fois publiée.</p>
            </div>
            <div class="p-5 sm:p-6 space-y-3">
                <?php foreach (\App\Support\TenantAlertFeatures::definitions() as $featureKey => $featureMeta): ?>
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-slate-50/60 px-4 py-3">
                        <input type="hidden" name="feature_<?= htmlspecialchars($featureKey, ENT_QUOTES, 'UTF-8') ?>" value="0">
                        <input type="checkbox" name="feature_<?= htmlspecialchars($featureKey, ENT_QUOTES, 'UTF-8') ?>" value="1" class="mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" <?= !empty($currentFeatures[$featureKey]) ? 'checked' : '' ?>>
                        <span>
                            <span class="block text-sm font-semibold text-slate-800"><?= htmlspecialchars($featureMeta['label'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="block text-xs text-slate-500 mt-0.5"><?= htmlspecialchars($featureMeta['hint'], ENT_QUOTES, 'UTF-8') ?></span>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="border-y border-slate-100 bg-slate-50/80 px-5 sm:px-6 py-4">
                <h2 class="text-sm font-black uppercase tracking-[0.12em] text-slate-800">Diffusion</h2>
            </div>
            <div class="p-5 sm:p-6 space-y-5">
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="ta-starts" class="block text-sm font-semibold text-slate-800 mb-1.5">Début <span class="font-normal text-slate-500">(facultatif)</span></label>
                        <input id="ta-starts" type="datetime-local" name="starts_at" value="<?= htmlspecialchars($dt(isset($row['starts_at']) ? (string) $row['starts_at'] : null)) ?>"
                            class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none">
                    </div>
                    <div>
                        <label for="ta-ends" class="block text-sm font-semibold text-slate-800 mb-1.5">Fin <span class="font-normal text-slate-500">(facultatif)</span></label>
                        <input id="ta-ends" type="datetime-local" name="ends_at" value="<?= htmlspecialchars($dt(isset($row['ends_at']) ? (string) $row['ends_at'] : null)) ?>"
                            class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none">
                    </div>
                </div>
                <div>
                    <label for="ta-order" class="block text-sm font-semibold text-slate-800 mb-1.5">Ordre d’affichage</label>
                    <input id="ta-order" type="number" name="sort_order" value="<?= (int) ($row['sort_order'] ?? 0) ?>"
                        class="w-32 rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none">
                    <p class="mt-1.5 text-xs text-slate-500">Les plus petits numéros apparaissent en premier.</p>
                </div>
                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-slate-50/60 px-4 py-3">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" id="ta_is_active" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" <?= ($row === null || !empty($row['is_active'])) ? 'checked' : '' ?>>
                    <span>
                        <span class="block text-sm font-semibold text-slate-800">Annonce active</span>
                        <span class="block text-xs text-slate-500 mt-0.5">Décochez pour la masquer sans la supprimer.</span>
                    </span>
                </label>
            </div>

            <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 px-5 sm:px-6 py-4 bg-white">
                <button type="submit" class="inline-flex items-center rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-emerald-700">
                    <?= $isEdit ? 'Enregistrer les modifications' : 'Créer l’annonce' ?>
                </button>
                <a href="<?= url('back-office/alerts') ?>" class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Annuler</a>
            </div>
        </form>
    </div>
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
    var url = URL.createObjectURL(input.files[0]);
    imgEl.src = url;
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
  if (imageInput) imageInput.addEventListener('change', function () { previewFile(imageInput, previewImage); });
  if (bannerInput) bannerInput.addEventListener('change', function () { previewFile(bannerInput, previewBanner); });

  syncColor(accent ? accent.value : null);
  syncKind();
  syncIcon();
})();
</script>
