<?php
declare(strict_types=1);

/** @var array $tenant */
/** @var array<string, mixed> $community */
/** @var array<string, mixed> $branding */
/** @var array<string, mixed> $orgSettings */
/** @var list<string> $orgTimezoneOptions */
/** @var string|null $registryCoverUrl */
/** @var string|null $navOpsImageUrl */
/** @var string|null $navResImageUrl */
/** @var string $orgSettingsFormAction */
/** @var array<string, mixed> $integrations */

$c = $community ?? [];
$i = $integrations ?? [];
$b = $branding ?? [];
$settingsRoot = is_array($orgSettings ?? null) ? $orgSettings : [];
$formAction = (string) ($orgSettingsFormAction ?? url('back-office/community'));
$zones = is_array($orgTimezoneOptions ?? null) ? $orgTimezoneOptions : \DateTimeZone::listIdentifiers();
$currentTz = (string) ($settingsRoot['timezone'] ?? 'Europe/Paris');
if ($currentTz === '' || !in_array($currentTz, $zones, true)) {
    $currentTz = 'Europe/Paris';
}

$logoUrl = trim((string) ($b['logo_url'] ?? ''));
if ($logoUrl === '') {
    $logoUrl = trim((string) ($tenant['logo_url'] ?? ''));
}
$bannerUrl = trim((string) ($b['banner_url'] ?? ''));
$faviconUrl = trim((string) ($b['favicon_url'] ?? ''));
$coverUrl = trim((string) ($registryCoverUrl ?? ''));
$navOpsUrl = trim((string) ($navOpsImageUrl ?? ''));
$navResUrl = trim((string) ($navResImageUrl ?? ''));
$primaryColor = trim((string) ($b['primary_color'] ?? '')) ?: '#059669';
$accentColor = trim((string) ($b['accent_color'] ?? '')) ?: '#0f172a';

$pm = is_array($c['public_modules'] ?? null) ? $c['public_modules'] : [];
$registrationModeRaw = (string) ($c['registration_mode'] ?? 'milsim');
$registrationMode = in_array($registrationModeRaw, ['simple', 'discord'], true) ? $registrationModeRaw : 'milsim';
$locale = strtolower((string) ($c['default_locale'] ?? 'fr'));
if ($locale === 'fr-fr') {
    $locale = 'fr';
}
if ($locale === 'en-us') {
    $locale = 'en';
}
if (!in_array($locale, ['fr', 'en'], true)) {
    $locale = 'fr';
}
$orbatVis = (string) ($c['orbat_visibility'] ?? 'members');
if (!in_array($orbatVis, ['public', 'members', 'command'], true)) {
    $orbatVis = 'members';
}
$slugHint = trim((string) ($tenant['slug'] ?? ''));
$publicPageUrl = $slugHint !== '' ? url('c/' . rawurlencode($slugHint)) : '';

$err = \App\Core\Session::getFlash('error');
$ok = \App\Core\Session::getFlash('success');

$completion = [
    'Nom affiché' => trim((string) ($tenant['name'] ?? '')) !== '',
    'Adresse publique' => $slugHint !== '',
    'Logo' => $logoUrl !== '',
    'Image registre' => $coverUrl !== '',
    'E-mail de contact' => trim((string) ($c['contact_email'] ?? '')) !== '',
    'Fuseau horaire' => $currentTz !== '',
];
$completionDone = count(array_filter($completion));
$completionTotal = count($completion);
$completionPct = $completionTotal > 0 ? (int) round(($completionDone / $completionTotal) * 100) : 0;
?>
<div class="min-h-0 flex-1 bg-slate-50">
<div class="max-w-6xl mx-auto px-4 sm:px-6 py-10 lg:py-12 space-y-8">
    <header class="relative overflow-hidden rounded-2xl border border-emerald-200/80 bg-gradient-to-br from-emerald-50/90 via-white to-slate-50 shadow-sm">
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,_var(--tw-gradient-stops))] from-emerald-100/50 via-transparent to-transparent pointer-events-none" aria-hidden="true"></div>
        <div class="relative px-5 sm:px-8 py-7 lg:py-8 flex flex-col lg:flex-row lg:items-start lg:justify-between gap-6">
            <div class="min-w-0 flex-1">
                <p class="text-[11px] font-black uppercase tracking-[0.22em] text-emerald-800/90">Back-office · Communauté</p>
                <h1 class="mt-2 text-2xl lg:text-3xl font-black tracking-tight text-slate-900">Paramètres de la communauté</h1>
                <p class="mt-2 text-sm text-slate-600 max-w-2xl leading-relaxed">
                    Identité, images, contact, fuseau, accès et modules visibles sur votre page publique — tout au même endroit.
                    Pour les textes détaillés de la vitrine et le formulaire de candidature complet, ouvrez la
                    <a href="<?= htmlspecialchars(url('back-office/community/presentation'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-emerald-800 underline decoration-emerald-300 hover:text-emerald-950">page d’accueil publique</a>.
                </p>
                <div class="mt-5 flex flex-wrap gap-3">
                    <?php if ($publicPageUrl !== ''): ?>
                        <a href="<?= htmlspecialchars($publicPageUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-800 shadow-sm hover:bg-slate-50">Voir la page publique</a>
                    <?php endif; ?>
                    <a href="<?= htmlspecialchars(url('back-office/community/presentation'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-950 hover:bg-emerald-100">Vitrine &amp; candidature</a>
                    <a href="<?= htmlspecialchars(url('back-office/configuration-initiale'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Assistant de démarrage</a>
                    <a href="<?= htmlspecialchars(url('back-office'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Retour back-office</a>
                </div>
            </div>
            <div class="shrink-0 w-full lg:w-64 rounded-xl border border-slate-200/80 bg-white/90 p-4 shadow-sm">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-2">Profil renseigné</p>
                <p class="text-3xl font-black text-slate-900"><?= $completionPct ?>%</p>
                <p class="mt-1 text-xs text-slate-600"><?= $completionDone ?>/<?= $completionTotal ?> éléments essentiels</p>
                <div class="mt-3 h-2 rounded-full bg-slate-100 overflow-hidden">
                    <div class="h-full rounded-full bg-emerald-500 transition-all" style="width:<?= $completionPct ?>%"></div>
                </div>
            </div>
        </div>
    </header>

    <?php if ($err): ?><div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800"><?= htmlspecialchars((string) $err, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($ok): ?><div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800"><?= htmlspecialchars((string) $ok, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Checklist</p>
        <div class="mt-3 flex flex-wrap gap-2">
            <?php foreach ($completion as $label => $isDone): ?>
                <span class="inline-flex items-center gap-1.5 rounded-full border <?= $isDone ? 'border-emerald-300 bg-emerald-50 text-emerald-900' : 'border-amber-200 bg-amber-50 text-amber-950' ?> px-2.5 py-1 text-[11px] font-semibold">
                    <span aria-hidden="true"><?= $isDone ? '✓' : '!' ?></span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                </span>
            <?php endforeach; ?>
        </div>
    </section>

    <nav class="org-tabs sticky top-0 z-20 -mx-4 sm:-mx-6 px-4 sm:px-6 py-3 border-y border-slate-200/90 bg-slate-50/95 backdrop-blur-md shadow-sm" aria-label="Sections des paramètres">
        <div class="flex flex-wrap gap-2" role="tablist">
            <?php
            $tabs = [
                'identite' => 'Identité',
                'images' => 'Images & marque',
                'contact' => 'Contact',
                'acces' => 'Accès & fuseau',
                'options' => 'Modules & options',
            ];
            $first = true;
            foreach ($tabs as $tid => $tlabel):
            ?>
            <button type="button" role="tab" data-org-tab="<?= htmlspecialchars($tid, ENT_QUOTES, 'UTF-8') ?>" aria-selected="<?= $first ? 'true' : 'false' ?>" class="org-tab-btn inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs sm:text-sm font-bold text-slate-700 shadow-sm transition hover:border-emerald-300 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40"><?= htmlspecialchars($tlabel, ENT_QUOTES, 'UTF-8') ?></button>
            <?php $first = false; endforeach; ?>
        </div>
    </nav>

    <form method="post" enctype="multipart/form-data" action="<?= htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8') ?>" class="pb-16 space-y-8">
        <?= \App\Core\Csrf::field() ?>

        <div class="org-panel space-y-6" data-org-panel="identite" id="org-panel-identite">
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <?php
                $displayNamePreview = trim((string) ($tenant['name'] ?? ''));
                $gamePreview = trim((string) ($c['game_label'] ?? ''));
                $codePreview = trim((string) ($tenant['community_code'] ?? ''));
                $welcomePreview = trim((string) ($c['welcome_text'] ?? ''));
                ?>
                <div class="relative border-b border-slate-100 bg-gradient-to-br from-slate-900 via-slate-900 to-emerald-950 px-6 py-7 sm:px-8 sm:py-8 text-white overflow-hidden">
                    <div class="pointer-events-none absolute inset-0 opacity-40" aria-hidden="true" style="background:radial-gradient(ellipse at 85% 20%, rgba(16,185,129,0.35), transparent 55%),radial-gradient(ellipse at 10% 90%, rgba(52,211,153,0.12), transparent 45%);"></div>
                    <div class="relative flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                        <div class="min-w-0">
                            <p class="text-[10px] font-black uppercase tracking-[0.22em] text-emerald-300/90">Aperçu fiche publique</p>
                            <p id="org-preview-name" class="mt-2 text-2xl sm:text-3xl font-black tracking-tight truncate"><?= $displayNamePreview !== '' ? htmlspecialchars($displayNamePreview, ENT_QUOTES, 'UTF-8') : 'Nom de la communauté' ?></p>
                            <p id="org-preview-game" class="mt-1 text-sm text-emerald-100/80 <?= $gamePreview === '' ? 'hidden' : '' ?>"><?= htmlspecialchars($gamePreview, ENT_QUOTES, 'UTF-8') ?></p>
                            <?php if ($publicPageUrl !== ''): ?>
                            <a id="org-preview-url" href="<?= htmlspecialchars($publicPageUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="mt-3 inline-flex max-w-full items-center gap-2 rounded-lg border border-white/15 bg-white/10 px-3 py-1.5 text-xs font-semibold text-emerald-50 backdrop-blur-sm hover:bg-white/15">
                                <span class="truncate"><?= htmlspecialchars(preg_replace('#^https?://#', '', $publicPageUrl) ?? $publicPageUrl, ENT_QUOTES, 'UTF-8') ?></span>
                                <span aria-hidden="true" class="shrink-0 opacity-70">↗</span>
                            </a>
                            <?php else: ?>
                            <p id="org-preview-url" class="mt-3 text-xs text-emerald-100/60">Définissez une adresse courte pour obtenir le lien public.</p>
                            <?php endif; ?>
                        </div>
                        <div class="flex flex-wrap gap-2 shrink-0">
                            <span id="org-preview-code" class="inline-flex items-center rounded-full border border-white/20 bg-black/20 px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-white <?= $codePreview === '' ? 'hidden' : '' ?>">
                                Code · <span id="org-preview-code-val"><?= htmlspecialchars($codePreview, ENT_QUOTES, 'UTF-8') ?></span>
                            </span>
                            <span class="inline-flex items-center rounded-full border border-emerald-400/30 bg-emerald-500/15 px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-emerald-200">Identité</span>
                        </div>
                    </div>
                </div>

                <div class="p-6 sm:p-8 space-y-8">
                    <div>
                        <h2 class="text-sm font-black uppercase tracking-widest text-slate-900">Identité</h2>
                        <p class="mt-1.5 text-sm text-slate-600 leading-relaxed max-w-2xl">Ces informations apparaissent sur votre page publique, dans le registre des unités et lorsqu’un membre rejoint la communauté.</p>
                    </div>

                    <div class="space-y-5">
                        <div class="flex items-center gap-3">
                            <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-100 text-[11px] font-black text-emerald-900">1</span>
                            <h3 class="text-xs font-black uppercase tracking-[0.14em] text-slate-800">Nom &amp; adresse</h3>
                        </div>
                        <div class="grid gap-5 lg:grid-cols-2">
                            <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-4 sm:p-5 space-y-1.5">
                                <label class="block text-xs font-bold text-slate-700" for="tenant_name">Nom affiché</label>
                                <input id="tenant_name" type="text" name="tenant_name" value="<?= htmlspecialchars((string) ($tenant['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" maxlength="255" required data-org-live="name" class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-semibold text-slate-900 shadow-sm focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100" placeholder="Ex. Compagnie Alpha">
                                <p class="text-[11px] text-slate-500">Nom vu par les visiteurs et les membres.</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-4 sm:p-5 space-y-1.5">
                                <label class="block text-xs font-bold text-slate-700" for="tenant_slug">Adresse courte de la page publique</label>
                                <div class="flex overflow-hidden rounded-xl border border-slate-300 bg-white shadow-sm focus-within:border-emerald-400 focus-within:ring-2 focus-within:ring-emerald-100">
                                    <span class="hidden sm:inline-flex items-center border-r border-slate-200 bg-slate-100 px-3 text-[11px] font-semibold text-slate-500 select-none">…/c/</span>
                                    <input id="tenant_slug" type="text" name="tenant_slug" value="<?= htmlspecialchars($slugHint, ENT_QUOTES, 'UTF-8') ?>" maxlength="50" required pattern="[a-z0-9]([-a-z0-9]*[a-z0-9])?" data-org-live="slug" class="min-w-0 flex-1 border-0 bg-transparent px-3.5 py-2.5 text-sm font-mono lowercase text-slate-900 focus:ring-0" placeholder="mon-unite">
                                </div>
                                <p class="text-[11px] text-slate-500">Lettres minuscules, chiffres et tirets. Si vous la changez, mettez à jour les liens déjà partagés.</p>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-5 border-t border-slate-100 pt-8">
                        <div class="flex items-center gap-3">
                            <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-100 text-[11px] font-black text-emerald-900">2</span>
                            <h3 class="text-xs font-black uppercase tracking-[0.14em] text-slate-800">Rejoindre &amp; contexte</h3>
                        </div>
                        <div class="grid gap-5 lg:grid-cols-2">
                            <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-4 sm:p-5 space-y-1.5">
                                <label class="block text-xs font-bold text-slate-700" for="community_code">Code pour rejoindre <span class="font-medium text-slate-400">(facultatif)</span></label>
                                <input id="community_code" type="text" name="community_code" value="<?= htmlspecialchars((string) ($tenant['community_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" maxlength="64" data-org-live="code" class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm uppercase font-mono tracking-wide shadow-sm focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100" placeholder="MON-UNIT">
                                <p class="text-[11px] text-slate-500">Utile sur la page « Rejoindre ». Laissez vide pour le retirer.</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-4 sm:p-5 space-y-1.5">
                                <label class="block text-xs font-bold text-slate-700" for="game_label">Jeu ou plateforme <span class="font-medium text-slate-400">(facultatif)</span></label>
                                <input id="game_label" type="text" name="game_label" value="<?= htmlspecialchars((string) ($c['game_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" maxlength="120" data-org-live="game" class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100" placeholder="Ex. Arma 3">
                                <p class="text-[11px] text-slate-500">Affiché sur la fiche publique et dans le registre.</p>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-5 border-t border-slate-100 pt-8">
                        <div class="flex items-center gap-3">
                            <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-100 text-[11px] font-black text-emerald-900">3</span>
                            <h3 class="text-xs font-black uppercase tracking-[0.14em] text-slate-800">Message d’accueil</h3>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-4 sm:p-5 space-y-2">
                            <label class="block text-xs font-bold text-slate-700" for="welcome_text">Texte court sur la page publique</label>
                            <textarea id="welcome_text" name="welcome_text" rows="4" maxlength="500" data-org-live="welcome" class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-3 text-sm leading-relaxed shadow-sm focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100" placeholder="Présentez votre unité en quelques phrases…"><?= htmlspecialchars((string) ($c['welcome_text'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-[11px] text-slate-500">Visible dès l’arrivée sur votre page communauté.</p>
                                <p class="text-[11px] font-semibold tabular-nums text-slate-400"><span id="org-welcome-count"><?= mb_strlen($welcomePreview) ?></span>/500</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div class="org-panel hidden space-y-6" data-org-panel="images" id="org-panel-images">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm space-y-8">
                <div>
                    <h2 class="text-sm font-black uppercase tracking-widest text-slate-900">Images &amp; marque</h2>
                    <p class="mt-1 text-xs text-slate-500 leading-relaxed">JPG, PNG ou WebP — jusqu’à 12&nbsp;Mo. Choisissez un fichier : l’aperçu se met à jour immédiatement.</p>
                    <?php if ($slugHint === ''): ?>
                        <p class="mt-2 text-xs font-semibold text-amber-800">Renseignez d’abord l’adresse courte dans Identité pour pouvoir envoyer des images.</p>
                    <?php endif; ?>
                </div>

                <div class="grid gap-6 lg:grid-cols-3">
                    <?php
                    $imageSlots = [
                        [
                            'field' => 'org_logo', 'remove' => 'remove_org_logo', 'title' => 'Logo',
                            'help' => 'Carte registre et page d’accueil. Carré recommandé, fond transparent accepté.',
                            'url' => $logoUrl, 'shape' => 'rounded-2xl', 'ratio' => 'aspect-square',
                        ],
                        [
                            'field' => 'org_banner', 'remove' => 'remove_org_banner', 'title' => 'Bannière',
                            'help' => 'Bandeau large pour les affichages mettant en avant votre organisation (12 Mo max).',
                            'url' => $bannerUrl, 'shape' => 'rounded-xl', 'ratio' => 'aspect-[16/6]',
                        ],
                        [
                            'field' => 'org_favicon', 'remove' => 'remove_org_favicon', 'title' => 'Icône navigateur',
                            'help' => 'Petite icône carrée dans l’onglet du navigateur.',
                            'url' => $faviconUrl, 'shape' => 'rounded-lg', 'ratio' => 'aspect-square',
                        ],
                    ];
                    foreach ($imageSlots as $slot):
                    ?>
                    <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-4 space-y-3">
                        <p class="text-xs font-black uppercase tracking-wider text-slate-800"><?= htmlspecialchars($slot['title'], ENT_QUOTES, 'UTF-8') ?></p>
                        <div class="<?= $slot['ratio'] ?> w-full overflow-hidden <?= $slot['shape'] ?> border border-slate-200 bg-white flex items-center justify-center">
                            <img data-org-preview="<?= htmlspecialchars($slot['field'], ENT_QUOTES, 'UTF-8') ?>" src="<?= $slot['url'] !== '' ? htmlspecialchars($slot['url'], ENT_QUOTES, 'UTF-8') : 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==' ?>" alt="" class="<?= $slot['url'] !== '' ? '' : 'hidden' ?> h-full w-full object-contain">
                            <span data-org-placeholder="<?= htmlspecialchars($slot['field'], ENT_QUOTES, 'UTF-8') ?>" class="<?= $slot['url'] !== '' ? 'hidden' : '' ?> text-[11px] font-semibold text-slate-400">Aucune image</span>
                        </div>
                        <p class="text-[11px] text-slate-500 leading-relaxed"><?= htmlspecialchars($slot['help'], ENT_QUOTES, 'UTF-8') ?></p>
                        <input type="file" name="<?= htmlspecialchars($slot['field'], ENT_QUOTES, 'UTF-8') ?>" data-org-input="<?= htmlspecialchars($slot['field'], ENT_QUOTES, 'UTF-8') ?>" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
                               class="block w-full text-xs text-slate-700 file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-700 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-white hover:file:bg-emerald-800 disabled:opacity-50"
                               <?= $slugHint === '' ? 'disabled' : '' ?>>
                        <?php if ($slot['url'] !== ''): ?>
                        <label class="flex items-start gap-2 text-[11px] text-slate-700 cursor-pointer">
                            <input type="hidden" name="<?= htmlspecialchars($slot['remove'], ENT_QUOTES, 'UTF-8') ?>" value="0">
                            <input type="checkbox" name="<?= htmlspecialchars($slot['remove'], ENT_QUOTES, 'UTF-8') ?>" value="1" class="mt-0.5 rounded border-slate-300 text-emerald-700">
                            <span>Retirer cette image</span>
                        </label>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="border-t border-slate-100 pt-8 space-y-6">
                    <div>
                        <h3 class="text-xs font-black uppercase tracking-wider text-slate-800">Images de navigation &amp; registre</h3>
                        <p class="mt-1 text-[11px] text-slate-500">Visuels utilisés sur la carte du registre et les menus Opérations / Ressources.</p>
                    </div>
                    <div class="grid gap-6 lg:grid-cols-3">
                        <?php
                        $extraSlots = [
                            ['field' => 'registry_cover', 'remove' => 'remove_registry_cover', 'title' => 'Carte du registre', 'help' => 'Image de couverture de votre fiche dans le registre des unités.', 'url' => $coverUrl],
                            ['field' => 'nav_operations', 'remove' => 'remove_nav_operations', 'title' => 'Menu Opérations', 'help' => 'Visuel du menu Opérations sur la navigation publique.', 'url' => $navOpsUrl],
                            ['field' => 'nav_resources', 'remove' => 'remove_nav_resources', 'title' => 'Menu Ressources', 'help' => 'Visuel du menu Ressources sur la navigation publique.', 'url' => $navResUrl],
                        ];
                        foreach ($extraSlots as $slot):
                        ?>
                        <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-4 space-y-3">
                            <p class="text-xs font-black uppercase tracking-wider text-slate-800"><?= htmlspecialchars($slot['title'], ENT_QUOTES, 'UTF-8') ?></p>
                            <div class="aspect-[16/9] w-full overflow-hidden rounded-xl border border-slate-200 bg-white flex items-center justify-center">
                                <img data-org-preview="<?= htmlspecialchars($slot['field'], ENT_QUOTES, 'UTF-8') ?>" src="<?= $slot['url'] !== '' ? htmlspecialchars($slot['url'], ENT_QUOTES, 'UTF-8') : 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==' ?>" alt="" class="<?= $slot['url'] !== '' ? '' : 'hidden' ?> h-full w-full object-cover">
                                <span data-org-placeholder="<?= htmlspecialchars($slot['field'], ENT_QUOTES, 'UTF-8') ?>" class="<?= $slot['url'] !== '' ? 'hidden' : '' ?> text-[11px] font-semibold text-slate-400">Aucune image</span>
                            </div>
                            <p class="text-[11px] text-slate-500 leading-relaxed"><?= htmlspecialchars($slot['help'], ENT_QUOTES, 'UTF-8') ?></p>
                            <input type="file" name="<?= htmlspecialchars($slot['field'], ENT_QUOTES, 'UTF-8') ?>" data-org-input="<?= htmlspecialchars($slot['field'], ENT_QUOTES, 'UTF-8') ?>" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
                                   class="block w-full text-xs text-slate-700 file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-700 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-white hover:file:bg-emerald-800 disabled:opacity-50"
                                   <?= $slugHint === '' ? 'disabled' : '' ?>>
                            <?php if ($slot['url'] !== ''): ?>
                            <label class="flex items-start gap-2 text-[11px] text-slate-700 cursor-pointer">
                                <input type="hidden" name="<?= htmlspecialchars($slot['remove'], ENT_QUOTES, 'UTF-8') ?>" value="0">
                                <input type="checkbox" name="<?= htmlspecialchars($slot['remove'], ENT_QUOTES, 'UTF-8') ?>" value="1" class="mt-0.5 rounded border-slate-300 text-emerald-700">
                                <span>Retirer cette image</span>
                            </label>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="grid gap-5 sm:grid-cols-2 max-w-xl border-t border-slate-100 pt-8">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">Couleur principale</label>
                        <div class="flex items-center gap-3">
                            <input type="color" name="primary_color" value="<?= htmlspecialchars($primaryColor, ENT_QUOTES, 'UTF-8') ?>" class="h-11 w-14 rounded-lg border border-slate-300 cursor-pointer">
                            <span class="text-xs font-mono text-slate-500"><?= htmlspecialchars($primaryColor, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">Couleur d’accent</label>
                        <div class="flex items-center gap-3">
                            <input type="color" name="accent_color" value="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" class="h-11 w-14 rounded-lg border border-slate-300 cursor-pointer">
                            <span class="text-xs font-mono text-slate-500"><?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div class="org-panel hidden space-y-6" data-org-panel="contact" id="org-panel-contact">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm space-y-5">
                <div>
                    <h2 class="text-sm font-black uppercase tracking-widest text-slate-900">Coordonnées de contact</h2>
                    <p class="mt-1 text-xs text-slate-500">Affichées sur votre fiche publique et utilisées pour le formulaire « nous écrire ».</p>
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5" for="contact_email">E-mail de contact</label>
                        <input id="contact_email" type="email" name="contact_email" value="<?= htmlspecialchars((string) ($c['contact_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" maxlength="255" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5" for="contact_discord_url">Lien Discord</label>
                        <input id="contact_discord_url" type="url" name="contact_discord_url" value="<?= htmlspecialchars((string) ($c['contact_discord_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" maxlength="500" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100" placeholder="https://discord.gg/…">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5" for="contact_intro">Message d’introduction (facultatif)</label>
                    <input id="contact_intro" type="text" name="contact_intro" value="<?= htmlspecialchars((string) ($c['contact_intro'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" maxlength="500" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100">
                </div>
                <label class="flex items-start gap-3 cursor-pointer rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-3">
                    <input type="hidden" name="contact_form_enabled" value="0">
                    <input type="checkbox" name="contact_form_enabled" value="1" class="mt-1 rounded border-slate-300 text-emerald-700" <?= !empty($c['contact_form_enabled']) ? 'checked' : '' ?>>
                    <span class="text-sm text-slate-700 leading-relaxed">Activer le formulaire « nous écrire » sur la fiche publique (nécessite un e-mail valide)</span>
                </label>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm space-y-5">
                <div>
                    <h2 class="text-sm font-black uppercase tracking-widest text-slate-900">Relais Discord</h2>
                    <p class="mt-1 text-xs text-slate-500">Les annonces publiées depuis « Annonces &amp; alertes » sont relayées automatiquement vers ce salon, en plus d’apparaître sur Athena.</p>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5" for="discord_webhook_url">URL du webhook Discord</label>
                    <input id="discord_webhook_url" type="url" name="discord_webhook_url" value="<?= htmlspecialchars((string) ($i['discord_webhook_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" maxlength="500" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm font-mono focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100" placeholder="https://discord.com/api/webhooks/…">
                    <p class="mt-1.5 text-xs text-slate-500">Dans Discord : Paramètres du salon → Intégrations → Webhooks → Nouveau webhook → Copier l’URL. Laissez vide pour désactiver le relais.</p>
                </div>
            </section>
        </div>

        <div class="org-panel hidden space-y-6" data-org-panel="acces" id="org-panel-acces">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm space-y-6">
                <div>
                    <h2 class="text-sm font-black uppercase tracking-widest text-slate-900">Accès, langue &amp; fuseau</h2>
                    <p class="mt-1 text-xs text-slate-500">Réglages utilisés pour le planning, le recrutement et l’affichage de l’organisation.</p>
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5" for="timezone">Fuseau horaire</label>
                        <select id="timezone" name="timezone" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100">
                            <?php foreach ($zones as $z): ?>
                                <option value="<?= htmlspecialchars($z, ENT_QUOTES, 'UTF-8') ?>" <?= $z === $currentTz ? 'selected' : '' ?>><?= htmlspecialchars($z, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-[11px] text-slate-500 mt-1.5">Aligné sur les événements, échéances et journaux.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5" for="default_locale">Langue de référence</label>
                        <select id="default_locale" name="default_locale" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100">
                            <option value="fr" <?= $locale === 'fr' ? 'selected' : '' ?>>Français</option>
                            <option value="en" <?= $locale === 'en' ? 'selected' : '' ?>>English</option>
                        </select>
                    </div>
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5" for="orbat_visibility">Visibilité de l’organisation (ORBAT)</label>
                        <select id="orbat_visibility" name="orbat_visibility" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100">
                            <option value="public" <?= $orbatVis === 'public' ? 'selected' : '' ?>>Visible par tous les visiteurs</option>
                            <option value="members" <?= $orbatVis === 'members' ? 'selected' : '' ?>>Réservée aux membres</option>
                            <option value="command" <?= $orbatVis === 'command' ? 'selected' : '' ?>>Réservée au commandement</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5" for="registration_mode">Mode du formulaire de candidature</label>
                        <select id="registration_mode" name="registration_mode" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100">
                            <option value="milsim" <?= $registrationMode === 'milsim' ? 'selected' : '' ?>>MilSim complet (dossier détaillé)</option>
                            <option value="simple" <?= $registrationMode === 'simple' ? 'selected' : '' ?>>Mode simple (champs réduits)</option>
                            <option value="discord" <?= $registrationMode === 'discord' ? 'selected' : '' ?>>Recrutement via Discord (pseudo + questions custom)</option>
                        </select>
                        <?php if ($registrationMode === 'discord'): ?>
                        <p class="mt-1.5 text-xs text-slate-500">
                            <a href="<?= htmlspecialchars(url('back-office/recruitments/discord-questions'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-indigo-700 underline">Configurer les questions du formulaire Discord →</a>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="space-y-3">
                    <label class="flex items-start gap-3 cursor-pointer rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-3">
                        <input type="hidden" name="community_locked" value="0">
                        <input type="checkbox" name="community_locked" value="1" class="mt-1 rounded border-slate-300 text-emerald-700" <?= !empty($c['community_locked']) ? 'checked' : '' ?>>
                        <span class="text-sm text-slate-700 leading-relaxed"><strong class="font-semibold text-slate-900">Fermer le recrutement</strong> — les nouvelles candidatures ne sont plus acceptées.</span>
                    </label>
                    <label class="flex items-start gap-3 cursor-pointer rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-3">
                        <input type="hidden" name="require_ai_ack" value="0">
                        <input type="checkbox" name="require_ai_ack" value="1" class="mt-1 rounded border-slate-300 text-emerald-700" <?= !array_key_exists('require_ai_ack', $c) || !empty($c['require_ai_ack']) ? 'checked' : '' ?>>
                        <span class="text-sm text-slate-700 leading-relaxed">Exiger l’accusé de réception des règles avant dépôt d’une candidature</span>
                    </label>
                    <label class="flex items-start gap-3 cursor-pointer rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-3">
                        <input type="hidden" name="public_recruitment_badge_open" value="0">
                        <input type="checkbox" name="public_recruitment_badge_open" value="1" class="mt-1 rounded border-slate-300 text-emerald-700" <?= !empty($c['public_recruitment_badge_open']) ? 'checked' : '' ?>>
                        <span class="text-sm text-slate-700 leading-relaxed">Afficher le badge « recrutement ouvert » sur la fiche publique</span>
                    </label>
                </div>
            </section>
        </div>

        <div class="org-panel hidden space-y-6" data-org-panel="options" id="org-panel-options">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm space-y-5">
                <div>
                    <h2 class="text-sm font-black uppercase tracking-widest text-slate-900">Options générales</h2>
                </div>
                <label class="flex items-start gap-3 cursor-pointer rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-3">
                    <input type="hidden" name="registry_listed" value="0">
                    <input type="checkbox" name="registry_listed" value="1" class="mt-1 rounded border-slate-300 text-emerald-700" <?= (!array_key_exists('registry_listed', $c) || !empty($c['registry_listed'])) ? 'checked' : '' ?>>
                    <span class="text-sm text-slate-700 leading-relaxed">Faire apparaître la communauté dans le registre public des unités</span>
                </label>
                <label class="flex items-start gap-3 cursor-pointer rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-3">
                    <input type="hidden" name="forum_members_only" value="0">
                    <input type="checkbox" name="forum_members_only" value="1" class="mt-1 rounded border-slate-300 text-emerald-700" <?= !empty($c['forum_members_only']) ? 'checked' : '' ?>>
                    <span class="text-sm text-slate-700 leading-relaxed">Forum réservé aux membres (masquer l’accès aux visiteurs)</span>
                </label>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm space-y-5">
                <div>
                    <h2 class="text-sm font-black uppercase tracking-widest text-slate-900">Fonctionnalités sur la fiche publique</h2>
                    <p class="mt-1 text-xs text-slate-500">Modules mis en avant sur votre page de présentation.</p>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <?php foreach (['forum' => 'Forum', 'documents' => 'Documents', 'events' => 'Événements', 'roster' => 'Effectifs', 'training' => 'Formations', 'analytics' => 'Statistiques'] as $mk => $ml): ?>
                    <label class="inline-flex items-center gap-2.5 text-sm text-slate-700 rounded-xl border border-slate-200 bg-slate-50/40 px-3.5 py-3 cursor-pointer hover:border-emerald-300">
                        <input type="checkbox" name="public_mod_<?= htmlspecialchars($mk, ENT_QUOTES, 'UTF-8') ?>" value="1" class="rounded border-slate-300 text-emerald-700" <?= !empty($pm[$mk]) ? 'checked' : '' ?>>
                        <?= htmlspecialchars($ml, ENT_QUOTES, 'UTF-8') ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-slate-50/70 p-6 sm:p-8 space-y-3">
                <h2 class="text-sm font-black uppercase tracking-widest text-slate-900">Aller plus loin</h2>
                <ul class="space-y-2 text-sm">
                    <li><a href="<?= htmlspecialchars(url('back-office/community/presentation'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-emerald-800 underline decoration-emerald-200 hover:decoration-emerald-600">Page d’accueil publique</a> — textes détaillés, sections, formulaire de candidature.</li>
                    <li><a href="<?= htmlspecialchars(url('back-office/recruitments/settings'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-emerald-800 underline decoration-emerald-200 hover:decoration-emerald-600">Réglages recrutement</a> — délais de réponse et flux d’instruction.</li>
                    <li><a href="<?= htmlspecialchars(url('back-office/integrations'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-emerald-800 underline decoration-emerald-200 hover:decoration-emerald-600">Intégrations</a> — accès externes.</li>
                    <li><a href="<?= htmlspecialchars(url('back-office/alerts'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-emerald-800 underline decoration-emerald-200 hover:decoration-emerald-600">Annonces &amp; alertes</a> — messages aux membres.</li>
                    <li><a href="<?= htmlspecialchars(url('back-office/configuration'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-emerald-800 underline decoration-emerald-200 hover:decoration-emerald-600">Configuration avancée</a> — rôles affichés et suivi roleplay.</li>
                </ul>
            </section>
        </div>

        <div class="sticky bottom-0 mt-2 flex justify-end border-t border-slate-200 bg-white/95 backdrop-blur-md px-1 py-4">
            <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-slate-900 px-6 py-3.5 text-sm font-black uppercase tracking-wider text-white shadow-lg shadow-slate-900/25 ring-1 ring-white/10 transition hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400">
                <svg class="h-4 w-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                Enregistrer les paramètres
            </button>
        </div>
    </form>
</div>
</div>
<script>
(function () {
    var tabs = document.querySelectorAll('[data-org-tab]');
    var panels = document.querySelectorAll('[data-org-panel]');
    if (!tabs.length || !panels.length) return;
    var order = ['identite', 'images', 'contact', 'acces', 'options'];
    function show(id) {
        if (order.indexOf(id) === -1) id = 'identite';
        panels.forEach(function (p) {
            p.classList.toggle('hidden', p.getAttribute('data-org-panel') !== id);
        });
        tabs.forEach(function (t) {
            var on = t.getAttribute('data-org-tab') === id;
            t.setAttribute('aria-selected', on ? 'true' : 'false');
            t.classList.toggle('bg-emerald-50', on);
            t.classList.toggle('border-emerald-500', on);
            t.classList.toggle('text-emerald-950', on);
            t.classList.toggle('shadow', on);
        });
        try {
            if (history.replaceState) history.replaceState(null, '', '#' + id);
        } catch (e) {}
    }
    tabs.forEach(function (t) {
        t.addEventListener('click', function () { show(String(t.getAttribute('data-org-tab') || '')); });
    });
    var initial = (location.hash || '').replace(/^#/, '');
    show(order.indexOf(initial) !== -1 ? initial : 'identite');
})();
(function () {
    var nameEl = document.getElementById('org-preview-name');
    var gameEl = document.getElementById('org-preview-game');
    var codeWrap = document.getElementById('org-preview-code');
    var codeVal = document.getElementById('org-preview-code-val');
    var welcomeCount = document.getElementById('org-welcome-count');
    var urlBase = <?= json_encode(rtrim(url('c/'), '/') . '/', JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP) ?>;
    var urlEl = document.getElementById('org-preview-url');

    function bindLive(attr, handler) {
        var input = document.querySelector('[data-org-live="' + attr + '"]');
        if (!input) return;
        var run = function () { handler(input); };
        input.addEventListener('input', run);
        input.addEventListener('change', run);
    }

    bindLive('name', function (input) {
        if (!nameEl) return;
        var v = (input.value || '').trim();
        nameEl.textContent = v !== '' ? v : 'Nom de la communauté';
    });
    bindLive('game', function (input) {
        if (!gameEl) return;
        var v = (input.value || '').trim();
        gameEl.textContent = v;
        gameEl.classList.toggle('hidden', v === '');
    });
    bindLive('code', function (input) {
        if (!codeWrap || !codeVal) return;
        var v = (input.value || '').trim().toUpperCase();
        codeVal.textContent = v;
        codeWrap.classList.toggle('hidden', v === '');
    });
    bindLive('welcome', function (input) {
        if (!welcomeCount) return;
        welcomeCount.textContent = String((input.value || '').length);
    });
    bindLive('slug', function (input) {
        if (!urlEl || urlEl.tagName !== 'A') return;
        var slug = (input.value || '').trim().toLowerCase();
        if (slug === '') return;
        var full = urlBase + encodeURIComponent(slug);
        urlEl.href = full;
        urlEl.querySelector('span') && (urlEl.querySelector('span').textContent = full.replace(/^https?:\/\//, ''));
    });
})();
(function () {
    document.querySelectorAll('[data-org-input]').forEach(function (input) {
        input.addEventListener('change', function () {
            var key = input.getAttribute('data-org-input');
            var img = document.querySelector('img[data-org-preview="' + key + '"]');
            var placeholder = document.querySelector('[data-org-placeholder="' + key + '"]');
            var file = input.files && input.files[0];
            if (!file || !img) return;
            var reader = new FileReader();
            reader.onload = function (e) {
                img.src = String(e.target.result);
                img.classList.remove('hidden');
                if (placeholder) placeholder.classList.add('hidden');
            };
            reader.readAsDataURL(file);
        });
    });
})();
</script>
