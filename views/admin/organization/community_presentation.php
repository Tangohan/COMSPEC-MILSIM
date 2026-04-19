<?php
declare(strict_types=1);

/** @var array $tenant */
/** @var array<string, mixed> $community */
$c = $community ?? [];
$badgeLabels = \App\Services\Community\TenantCommunityProfileService::badgeLabels();
$registryTagLabels = \App\Services\Community\TenantCommunityProfileService::registryTagLabels();
$selectedBadges = is_array($c['style_badges'] ?? null) ? $c['style_badges'] : [];
$selectedRegistryTags = is_array($c['registry_tags'] ?? null) ? $c['registry_tags'] : [];
$military = is_array($c['military_sections'] ?? null) ? $c['military_sections'] : [];
if ($military === []) {
    $military = [
        ['label' => 'PRIMO', 'title' => '', 'body' => ''],
        ['label' => 'SECUNDO', 'title' => '', 'body' => ''],
    ];
}
$registryCoverUrl = $registryCoverUrl ?? null;
$navOpsImageUrl = $navOpsImageUrl ?? null;
$navResImageUrl = $navResImageUrl ?? null;
$portalNav = is_array($c['portal_nav'] ?? null) ? $c['portal_nav'] : [];
$navAccents = \App\Services\Community\TenantCommunityProfileService::allowedNavAccents();
$navStyles = \App\Services\Community\TenantCommunityProfileService::allowedNavSubmenuStyles();
$navOps = is_array($portalNav['operations'] ?? null) ? $portalNav['operations'] : [];
$navRes = is_array($portalNav['resources'] ?? null) ? $portalNav['resources'] : [];
$navOpsAccent = in_array(($navOps['accent'] ?? 'sky'), $navAccents, true) ? (string) $navOps['accent'] : 'sky';
$navResAccent = in_array(($navRes['accent'] ?? 'amber'), $navAccents, true) ? (string) $navRes['accent'] : 'amber';
$navOpsStyle = in_array(($navOps['submenu_style'] ?? 'cards'), $navStyles, true) ? (string) ($navOps['submenu_style'] ?? 'cards') : 'cards';
$navResStyle = in_array(($navRes['submenu_style'] ?? 'minimal'), $navStyles, true) ? (string) ($navRes['submenu_style'] ?? 'minimal') : 'minimal';
$navOpsImageEnabled = !array_key_exists('image_enabled', $navOps) || !empty($navOps['image_enabled']);
$navResImageEnabled = !array_key_exists('image_enabled', $navRes) || !empty($navRes['image_enabled']);
$presentationMode = ($c['presentation_mode'] ?? 'simple') === 'military' ? 'military' : 'simple';
$registrationMode = ($c['registration_mode'] ?? 'milsim') === 'simple' ? 'simple' : 'milsim';
$em = \App\Services\Community\EnlistmentMilsimPackService::forCommunity($c);
$emRoeLines = implode("\n", is_array($em['roe_items'] ?? null) ? $em['roe_items'] : []);
$emPreambleStatus = implode("\n", is_array($em['preamble_status_lines'] ?? null) ? $em['preamble_status_lines'] : []);
$emFieldsJson = json_encode($em['fields'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
if ($emFieldsJson === false) {
    $emFieldsJson = '{}';
}
$cmdChain = is_array($c['public_command_chain'] ?? null) ? $c['public_command_chain'] : [];
while (count($cmdChain) < 3) {
    $cmdChain[] = ['role_label' => '', 'display_name' => '', 'hint' => ''];
}
$pm = is_array($c['public_modules'] ?? null) ? $c['public_modules'] : [];
$sm = is_array($c['public_stats_manual'] ?? null) ? $c['public_stats_manual'] : [];
$publicLayoutSel = ($c['public_page_layout'] ?? 'legacy') === 'showcase' ? 'showcase' : 'legacy';
$publicAudienceSel = ($c['public_audience'] ?? 'unit') === 'platform' ? 'platform' : 'unit';
$regionBadgesLines = implode("\n", is_array($c['public_region_badges'] ?? null) ? $c['public_region_badges'] : []);
$specialtiesLines = implode("\n", is_array($c['public_specialties'] ?? null) ? $c['public_specialties'] : []);
$statsModeSel = ($c['public_stats_mode'] ?? 'manual') === 'computed' ? 'computed' : 'manual';
$hasMilitaryContent = false;
foreach ($military as $sec) {
    if (!is_array($sec)) {
        continue;
    }
    if (trim((string) ($sec['title'] ?? '')) !== '' || trim((string) ($sec['body'] ?? '')) !== '') {
        $hasMilitaryContent = true;
        break;
    }
}
$profileChecklist = [
    'Profil' => [
        'Nom de communauté' => trim((string) ($tenant['name'] ?? '')) !== '',
        'Sous-titre public (hero)' => trim((string) ($c['public_hero_subtitle'] ?? '')) !== '',
        'Présentation (texte ou sections)' => trim((string) ($c['simple_body'] ?? '')) !== '' || $hasMilitaryContent,
        'Attentes recrutement' => trim((string) ($c['expectations'] ?? '')) !== '',
        'Code communauté' => trim((string) ($tenant['community_code'] ?? '')) !== '',
    ],
    'Visuel' => [
        'Image registre' => $registryCoverUrl !== null && trim((string) $registryCoverUrl) !== '',
        'Badges registre / style de jeu' => $selectedBadges !== [] || $selectedRegistryTags !== [],
        'Doctrine / accès' => trim((string) ($c['public_doctrine'] ?? '')) !== '' || trim((string) ($c['public_access_label'] ?? '')) !== '',
    ],
    'Liens' => [
        'Canal Discord' => trim((string) ($c['contact_discord_url'] ?? '')) !== '',
        'E-mail de contact' => trim((string) ($c['contact_email'] ?? '')) !== '',
        'Formulaire contact public' => !empty($c['contact_form_enabled']),
    ],
    'Événements' => [
        'Module événements activé (vitrine)' => !empty($pm['events']),
        'Mission publique renseignée' => trim((string) ($c['public_mission'] ?? '')) !== '',
    ],
];
$profileChecklistDone = 0;
foreach ($profileChecklist as $groupItems) {
    foreach ($groupItems as $isDone) {
        if ($isDone) {
            $profileChecklistDone++;
        }
    }
}
$profileChecklistTotal = 0;
foreach ($profileChecklist as $groupItems) {
    $profileChecklistTotal += count($groupItems);
}
$profileChecklistPercent = $profileChecklistTotal > 0 ? (int) round(($profileChecklistDone / $profileChecklistTotal) * 100) : 0;
?>
<div class="max-w-6xl mx-auto px-4 sm:px-6 py-10 lg:py-12">
    <header class="mb-6 lg:mb-8">
        <h1 class="text-2xl lg:text-3xl font-black text-slate-900 mb-2">Fiche registre &amp; contact</h1>
        <p class="text-slate-600 text-sm max-w-3xl">Ce que vous saisissez ici alimente la <strong class="font-semibold text-slate-800">page publique de votre communauté</strong> et, si vous le souhaitez, votre <a href="<?= htmlspecialchars(url('communities')) ?>" class="text-emerald-700 font-semibold underline decoration-emerald-200 hover:decoration-emerald-600">carte dans le registre des unités</a>.</p>
        <p class="text-slate-500 text-xs mt-2"><a href="<?= htmlspecialchars(url('back-office/community')) ?>" class="underline hover:text-slate-800">← Identité &amp; code rejoindre</a></p>
    </header>

    <section class="mb-8 rounded-2xl border border-emerald-200 bg-emerald-50/60 p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-[11px] font-black uppercase tracking-[0.22em] text-emerald-700">Complétion profil public</p>
                <h2 class="mt-1 text-lg font-black tracking-tight text-slate-900"><?= $profileChecklistDone ?>/<?= $profileChecklistTotal ?> éléments renseignés</h2>
                <p class="mt-1 text-sm text-slate-600">Recommandations automatiques pour améliorer la conversion visiteur → candidat/membre.</p>
            </div>
            <span class="inline-flex items-center rounded-full border border-emerald-300 bg-white px-3 py-1 text-xs font-black uppercase tracking-[0.18em] text-emerald-800"><?= $profileChecklistPercent ?>%</span>
        </div>
        <div class="mt-4 grid gap-3 lg:grid-cols-2">
            <?php foreach ($profileChecklist as $groupLabel => $items): ?>
                <?php
                $groupDone = 0;
                foreach ($items as $itemOk) {
                    if ($itemOk) {
                        $groupDone++;
                    }
                }
                ?>
                <div class="rounded-xl border border-emerald-100 bg-white p-3">
                    <p class="text-[11px] font-black uppercase tracking-[0.2em] text-emerald-800"><?= htmlspecialchars($groupLabel) ?> · <?= $groupDone ?>/<?= count($items) ?></p>
                    <ul class="mt-2 space-y-1.5">
                        <?php foreach ($items as $label => $isDone): ?>
                            <li class="flex items-center gap-2 rounded-lg border <?= $isDone ? 'border-emerald-200 bg-emerald-50/50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-900' ?> px-2.5 py-1.5 text-xs">
                                <span aria-hidden="true"><?= $isDone ? '✅' : '⚠️' ?></span>
                                <span><?= htmlspecialchars($label) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <nav class="cp-presentation-tabs sticky top-0 z-20 -mx-4 sm:-mx-6 px-4 sm:px-6 py-3 mb-8 border-y border-slate-200/90 bg-slate-50/95 backdrop-blur-md shadow-sm" aria-label="Parties de la fiche">
        <p class="text-[0.65rem] font-black uppercase tracking-[0.2em] text-slate-400 mb-2 lg:hidden">Navigation</p>
        <div class="flex flex-wrap gap-2" role="tablist">
            <button type="button" role="tab" data-cp-tab="visibilite" aria-selected="true" class="cp-tab-btn inline-flex items-center rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs sm:text-sm font-bold text-slate-700 shadow-sm transition hover:border-emerald-300 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40">Registre &amp; accès</button>
            <button type="button" role="tab" data-cp-tab="vitrine" aria-selected="false" class="cp-tab-btn inline-flex items-center rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs sm:text-sm font-bold text-slate-700 shadow-sm transition hover:border-emerald-300 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40">Page publique</button>
            <button type="button" role="tab" data-cp-tab="profil" aria-selected="false" class="cp-tab-btn inline-flex items-center rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs sm:text-sm font-bold text-slate-700 shadow-sm transition hover:border-emerald-300 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40">Inscription &amp; profil jeu</button>
            <button type="button" role="tab" data-cp-tab="textes" aria-selected="false" class="cp-tab-btn inline-flex items-center rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs sm:text-sm font-bold text-slate-700 shadow-sm transition hover:border-emerald-300 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40">Textes &amp; contact</button>
            <button type="button" role="tab" data-cp-tab="candidature" aria-selected="false" class="cp-tab-btn inline-flex items-center rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs sm:text-sm font-bold text-slate-700 shadow-sm transition hover:border-emerald-300 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40">Formulaire candidature</button>
        </div>
    </nav>

    <form id="community-presentation-form" method="post" enctype="multipart/form-data" action="<?= htmlspecialchars(url('back-office/community/presentation')) ?>" class="pb-28">
        <?= \App\Core\Csrf::field() ?>

        <div class="cp-panel space-y-8 lg:space-y-10" data-cp-panel="visibilite" id="cp-panel-visibilite">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
            <h2 class="text-sm font-black uppercase tracking-widest text-slate-900">Visibilité</h2>
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="hidden" name="registry_listed" value="0">
                <input type="checkbox" name="registry_listed" value="1" class="mt-1" <?= (!array_key_exists('registry_listed', $c) || !empty($c['registry_listed'])) ? 'checked' : '' ?>>
                <span class="text-sm text-slate-700">Faire apparaître notre communauté dans la liste publique du registre des unités</span>
            </label>
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="hidden" name="forum_members_only" value="0">
                <input type="checkbox" name="forum_members_only" value="1" class="mt-1" <?= !empty($c['forum_members_only']) ? 'checked' : '' ?>>
                <span class="text-sm text-slate-700">Forum réservé aux membres (masquer le bouton « Accéder au forum » pour les visiteurs non membres)</span>
            </label>
            <?php $slugHint = trim((string) ($tenant['slug'] ?? '')); ?>
            <div class="rounded-xl border border-slate-200 bg-slate-50/90 px-4 py-4 text-sm text-slate-700 space-y-4">
                <div>
                    <p class="font-semibold text-slate-900">Image d’en-tête sur la carte du registre (facultatif)</p>
                    <p class="mt-1 text-xs text-slate-600 leading-relaxed">
                        Une photo <strong class="font-semibold text-slate-800">paysage</strong> (type bandeau) donne le meilleur rendu sur la carte du registre des unités.
                        Formats acceptés : JPG, PNG ou WebP — jusqu’à 3&nbsp;Mo.
                        <?php if ($slugHint === ''): ?>
                        Définissez d’abord l’identifiant public de votre communauté dans
                        <a class="font-semibold text-emerald-700 underline" href="<?= htmlspecialchars(url('back-office/community'), ENT_QUOTES, 'UTF-8') ?>">Identité &amp; code rejoindre</a>
                        pour activer l’envoi.
                        <?php endif; ?>
                    </p>
                </div>
                <?php if ($registryCoverUrl): ?>
                <div class="rounded-lg border border-slate-200 bg-white p-2 overflow-hidden">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-2">Aperçu actuel</p>
                    <img src="<?= htmlspecialchars($registryCoverUrl, ENT_QUOTES, 'UTF-8') ?>" alt="" class="w-full max-h-40 object-cover rounded-md">
                </div>
                <?php endif; ?>
                <div class="space-y-2">
                    <label for="registry_cover" class="block text-xs font-bold text-slate-700">Choisir une image</label>
                    <input type="file" name="registry_cover" id="registry_cover" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
                           class="block w-full max-w-md text-xs text-slate-700 file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-700 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-white hover:file:bg-emerald-800 disabled:opacity-50 disabled:pointer-events-none"
                           <?= $slugHint === '' ? 'disabled aria-disabled="true" title="Identifiant public requis"' : '' ?>>
                </div>
                <?php if ($registryCoverUrl): ?>
                <label class="flex items-start gap-3 cursor-pointer text-xs text-slate-700">
                    <input type="hidden" name="remove_registry_cover" value="0">
                    <input type="checkbox" name="remove_registry_cover" value="1" class="mt-0.5">
                    <span>Retirer l’image et revenir au fond coloré automatique sur la carte du registre</span>
                </label>
                <?php endif; ?>
            </div>
        </section>

        <section class="rounded-2xl border border-sky-100 bg-sky-50/40 p-6 shadow-sm space-y-5">
            <h2 class="text-sm font-black uppercase tracking-widest text-slate-900">Navigation portail — Opérations &amp; Ressources</h2>
            <p class="text-xs text-slate-600">Personnalisez les sous-menus (style visuel), l’accent couleur et les images du panneau latéral pour rendre la navigation plus vivante.</p>
            <?php foreach ([
                'operations' => ['label' => 'Opérations', 'accent' => $navOpsAccent, 'style' => $navOpsStyle, 'imageUrl' => $navOpsImageUrl, 'imageEnabled' => $navOpsImageEnabled],
                'resources' => ['label' => 'Ressources', 'accent' => $navResAccent, 'style' => $navResStyle, 'imageUrl' => $navResImageUrl, 'imageEnabled' => $navResImageEnabled],
            ] as $slot => $cfg): ?>
                <div class="rounded-xl border border-slate-200 bg-white p-4 space-y-4">
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-800"><?= htmlspecialchars($cfg['label']) ?></h3>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Couleur d’accent</label>
                            <select name="nav_<?= htmlspecialchars($slot) ?>_accent" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                                <?php foreach ($navAccents as $accent): ?>
                                    <option value="<?= htmlspecialchars($accent) ?>" <?= $cfg['accent'] === $accent ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($accent)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Style sous-menu</label>
                            <select name="nav_<?= htmlspecialchars($slot) ?>_submenu_style" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                                <option value="standard" <?= $cfg['style'] === 'standard' ? 'selected' : '' ?>>Standard</option>
                                <option value="cards" <?= $cfg['style'] === 'cards' ? 'selected' : '' ?>>Cartes</option>
                                <option value="minimal" <?= $cfg['style'] === 'minimal' ? 'selected' : '' ?>>Minimal</option>
                            </select>
                        </div>
                    </div>
                    <label class="flex items-start gap-3 text-xs text-slate-700">
                        <input type="hidden" name="nav_<?= htmlspecialchars($slot) ?>_image_enabled" value="0">
                        <input type="checkbox" name="nav_<?= htmlspecialchars($slot) ?>_image_enabled" value="1" class="mt-0.5" <?= !empty($cfg['imageEnabled']) ? 'checked' : '' ?>>
                        <span>Afficher l’image dans le panneau latéral du menu.</span>
                    </label>
                    <?php if (!empty($cfg['imageUrl'])): ?>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-2">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-2">Aperçu image actuelle</p>
                            <img src="<?= htmlspecialchars((string) $cfg['imageUrl']) ?>" alt="" class="w-full max-h-36 object-cover rounded-md">
                        </div>
                    <?php endif; ?>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Image personnalisée (JPG, PNG, WebP — 3 Mo max)</label>
                        <input type="file" name="nav_<?= htmlspecialchars($slot) ?>_image" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
                               class="block w-full max-w-md text-xs text-slate-700 file:mr-3 file:rounded-lg file:border-0 file:bg-sky-700 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-white hover:file:bg-sky-800">
                    </div>
                    <?php if (!empty($cfg['imageUrl'])): ?>
                        <label class="flex items-start gap-3 text-xs text-slate-700">
                            <input type="hidden" name="remove_nav_<?= htmlspecialchars($slot) ?>_image" value="0">
                            <input type="checkbox" name="remove_nav_<?= htmlspecialchars($slot) ?>_image" value="1" class="mt-0.5">
                            <span>Retirer l’image personnalisée de ce menu.</span>
                        </label>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </section>
        </div>

        <div class="cp-panel hidden space-y-8 lg:space-y-10" data-cp-panel="vitrine" id="cp-panel-vitrine">
        <section class="rounded-2xl border border-emerald-200 bg-emerald-50/40 p-6 shadow-sm space-y-6">
            <div class="border-b border-emerald-200/80 pb-4">
                <h2 class="text-sm font-black uppercase tracking-widest text-slate-900">Fiche publique vitrine</h2>
                <p class="text-xs text-slate-600 mt-1">Bandeau d’accueil, chiffres clés, encadrement affiché et liste des membres (si activée) sur votre page publique.</p>
            </div>

            <div class="rounded-xl border border-emerald-100 bg-white p-5 shadow-sm space-y-4">
                <h3 class="text-xs font-black uppercase tracking-wider text-emerald-900">Modèle &amp; type de fiche</h3>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Modèle de page</label>
                <select name="public_page_layout" class="w-full max-w-md rounded border border-slate-300 px-3 py-2 text-sm">
                    <option value="legacy" <?= $publicLayoutSel === 'legacy' ? 'selected' : '' ?>>Classique (carte compacte)</option>
                    <option value="showcase" <?= $publicLayoutSel === 'showcase' ? 'selected' : '' ?>>Vitrine (pleine page)</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Type de fiche</label>
                <select name="public_audience" class="w-full max-w-md rounded border border-slate-300 px-3 py-2 text-sm">
                    <option value="unit" <?= $publicAudienceSel === 'unit' ? 'selected' : '' ?>>Unité / communauté de jeu (recrutement, code, forum)</option>
                    <option value="platform" <?= $publicAudienceSel === 'platform' ? 'selected' : '' ?>>Plateforme / outil (portail, emphase recrutement réduite)</option>
                </select>
                <p class="mt-1 text-[11px] text-slate-500">Pour les portails système, choisissez « Plateforme » et préférez le modèle <strong>Vitrine</strong> pour la bonne mise en page.</p>
            </div>
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="hidden" name="public_recruitment_badge_open" value="0">
                <input type="checkbox" name="public_recruitment_badge_open" value="1" class="mt-1" <?= !empty($c['public_recruitment_badge_open']) ? 'checked' : '' ?>>
                <span class="text-sm text-slate-700">Afficher le badge « Recrutement ouvert » (hero vitrine)</span>
            </label>
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="hidden" name="public_roster_enabled" value="0">
                <input type="checkbox" name="public_roster_enabled" value="1" class="mt-1" <?= !empty($c['public_roster_enabled']) ? 'checked' : '' ?>>
                <span class="text-sm text-slate-700">Activer le tableau roster public (membres avec opt-in)</span>
            </label>
            </div>

            <div class="rounded-xl border border-emerald-100 bg-white p-5 shadow-sm space-y-4">
                <h3 class="text-xs font-black uppercase tracking-wider text-emerald-900">Bandeau &amp; repères visuels</h3>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Sous-titre / accroche (hero)</label>
                <textarea name="public_hero_subtitle" rows="3" maxlength="600" class="w-full rounded border border-slate-300 px-3 py-2 text-sm"><?= htmlspecialchars((string) ($c['public_hero_subtitle'] ?? '')) ?></textarea>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Badges libres (une ligne = un badge, ex. FR / OTAN)</label>
                <textarea name="public_region_badges" rows="3" class="w-full rounded border border-slate-300 px-3 py-2 text-sm font-mono"><?= htmlspecialchars($regionBadgesLines) ?></textarea>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Doctrine (court)</label>
                    <input type="text" name="public_doctrine" value="<?= htmlspecialchars((string) ($c['public_doctrine'] ?? '')) ?>" maxlength="200" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Libellé accès</label>
                    <input type="text" name="public_access_label" value="<?= htmlspecialchars((string) ($c['public_access_label'] ?? '')) ?>" maxlength="120" class="w-full rounded border border-slate-300 px-3 py-2 text-sm" placeholder="Public + validation">
                </div>
            </div>
            </div>

            <div class="rounded-xl border border-emerald-100 bg-white p-5 shadow-sm space-y-4">
                <h3 class="text-xs font-black uppercase tracking-wider text-emerald-900">Blocs visibles &amp; textes du corps de page</h3>
            <div>
                <p class="text-xs font-bold text-slate-700 mb-2">Modules affichés (vitrine)</p>
                <div class="flex flex-wrap gap-3">
                    <?php foreach (['forum' => 'Forum', 'documents' => 'Documents', 'events' => 'Événements', 'roster' => 'Roster', 'training' => 'Formations', 'analytics' => 'Analytique'] as $mk => $ml): ?>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="public_mod_<?= htmlspecialchars($mk) ?>" value="1" <?= !empty($pm[$mk]) ? 'checked' : '' ?>>
                        <?= htmlspecialchars($ml) ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Mission publique</label>
                <textarea name="public_mission" rows="4" maxlength="4000" class="w-full rounded border border-slate-300 px-3 py-2 text-sm"><?= htmlspecialchars((string) ($c['public_mission'] ?? '')) ?></textarea>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Spécialités (une par ligne)</label>
                <textarea name="public_specialties" rows="4" class="w-full rounded border border-slate-300 px-3 py-2 text-sm"><?= htmlspecialchars($specialtiesLines) ?></textarea>
            </div>
            </div>

            <div class="rounded-xl border border-emerald-100 bg-white p-5 shadow-sm space-y-4">
                <h3 class="text-xs font-black uppercase tracking-wider text-emerald-900">Vue d’ensemble chiffrée</h3>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Statistiques (vue d’ensemble)</label>
                <select name="public_stats_mode" class="w-full max-w-md rounded border border-slate-300 px-3 py-2 text-sm mb-3">
                    <option value="manual" <?= $statsModeSel === 'manual' ? 'selected' : '' ?>>Saisie manuelle</option>
                    <option value="computed" <?= $statsModeSel === 'computed' ? 'selected' : '' ?>>Calcul automatique (effectifs / unités / activité)</option>
                </select>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="text-[11px] text-slate-500">Effectif</label>
                        <input type="text" name="public_stats_effectif" value="<?= htmlspecialchars((string) ($sm['effectif'] ?? '')) ?>" maxlength="12" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="text-[11px] text-slate-500">Unités</label>
                        <input type="text" name="public_stats_unites" value="<?= htmlspecialchars((string) ($sm['unites'] ?? '')) ?>" maxlength="12" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="text-[11px] text-slate-500">Activité % (manuel)</label>
                        <input type="text" name="public_stats_activite" value="<?= htmlspecialchars((string) ($sm['activite_percent'] ?? '')) ?>" maxlength="12" class="w-full rounded border border-slate-300 px-3 py-2 text-sm" placeholder="92">
                    </div>
                    <div>
                        <label class="text-[11px] text-slate-500">Théâtre (manuel)</label>
                        <input type="text" name="public_stats_theatre" value="<?= htmlspecialchars((string) ($sm['theatre'] ?? '')) ?>" maxlength="120" class="w-full rounded border border-slate-300 px-3 py-2 text-sm" placeholder="Europe">
                    </div>
                </div>
            </div>
            </div>

            <div class="rounded-xl border border-emerald-100 bg-white p-5 shadow-sm space-y-4">
                <h3 class="text-xs font-black uppercase tracking-wider text-emerald-900">Encadrement affiché sur la fiche</h3>
            <div>
                <p class="text-xs font-bold text-slate-700 mb-2">Chaîne de commandement (affichage libre)</p>
                <?php foreach ($cmdChain as $ci => $crow): ?>
                    <?php if (!is_array($crow)) { continue; } ?>
                <div class="grid gap-2 sm:grid-cols-3 mb-3 rounded-lg border border-slate-200 bg-white p-3">
                    <input type="text" name="cmd_role_label[]" value="<?= htmlspecialchars((string) ($crow['role_label'] ?? '')) ?>" placeholder="Rôle" class="rounded border border-slate-300 px-2 py-1 text-sm">
                    <input type="text" name="cmd_display_name[]" value="<?= htmlspecialchars((string) ($crow['display_name'] ?? '')) ?>" placeholder="Nom affiché" class="rounded border border-slate-300 px-2 py-1 text-sm">
                    <input type="text" name="cmd_hint[]" value="<?= htmlspecialchars((string) ($crow['hint'] ?? '')) ?>" placeholder="Sous-titre" class="rounded border border-slate-300 px-2 py-1 text-sm sm:col-span-1">
                </div>
                <?php endforeach; ?>
            </div>
            </div>
        </section>
        </div>

        <div class="cp-panel hidden space-y-8 lg:space-y-10" data-cp-panel="profil" id="cp-panel-profil">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
            <h2 class="text-sm font-black uppercase tracking-widest text-slate-900">Inscription publique</h2>
            <p class="text-xs text-slate-500">Formulaire de candidature ouvert depuis votre page publique (section recrutement).</p>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Mode du formulaire de candidature</label>
                <select name="registration_mode" class="w-full max-w-md rounded border border-slate-300 px-3 py-2 text-sm">
                    <option value="milsim" <?= $registrationMode === 'milsim' ? 'selected' : '' ?>>MilSim complet (dossier détaillé, CV, disponibilités, etc.)</option>
                    <option value="simple" <?= $registrationMode === 'simple' ? 'selected' : '' ?>>Mode simple (champs réduits, onboarding rapide)</option>
                </select>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-slate-50/50 p-6 shadow-sm space-y-4">
            <h2 class="text-sm font-black uppercase tracking-widest text-slate-900">Jeu &amp; matériel</h2>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Jeu (titre court)</label>
                <input type="text" name="game_label" value="<?= htmlspecialchars((string) ($c['game_label'] ?? '')) ?>" maxlength="120" class="w-full rounded border border-slate-300 px-3 py-2 text-sm" placeholder="Arma 3, Squad…">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Mods principaux</label>
                <textarea name="main_mods" rows="4" class="w-full rounded border border-slate-300 px-3 py-2 text-sm font-mono" placeholder="Liste ou description"><?= htmlspecialchars((string) ($c['main_mods'] ?? '')) ?></textarea>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Taille modpack (Go, indicatif)</label>
                <input type="text" name="modpack_size_gb" value="<?= htmlspecialchars((string) ($c['modpack_size_gb'] ?? '')) ?>" maxlength="32" class="w-full max-w-xs rounded border border-slate-300 px-3 py-2 text-sm" placeholder="ex. 45">
            </div>
            <div>
                <p class="text-xs font-bold text-slate-700 mb-2">Style de jeu</p>
                <div class="flex flex-wrap gap-3">
                    <?php foreach ($badgeLabels as $slug => $label): ?>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" name="style_badges[]" value="<?= htmlspecialchars($slug) ?>" <?= in_array($slug, $selectedBadges, true) ? 'checked' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div>
                <p class="text-xs font-bold text-slate-700 mb-2">Pastilles dans le registre des unités</p>
                <p class="text-[11px] text-slate-500 mb-2">Visibles sur votre carte lorsque la communauté est affichée dans le registre. Cochez ce qui correspond le mieux à votre univers.</p>
                <div class="flex flex-wrap gap-3">
                    <?php foreach ($registryTagLabels as $slug => $label): ?>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" name="registry_tags[]" value="<?= htmlspecialchars($slug) ?>" <?= in_array($slug, $selectedRegistryTags, true) ? 'checked' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        </div>

        <div class="cp-panel hidden space-y-8 lg:space-y-10" data-cp-panel="textes" id="cp-panel-textes">
        <section class="rounded-2xl border border-indigo-100 bg-gradient-to-br from-white via-indigo-50/30 to-white p-6 shadow-md space-y-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-black uppercase tracking-widest text-slate-900">Présentation</h2>
                    <p class="text-xs text-slate-500 mt-1 max-w-xl">Contenu affiché sur votre fiche publique : mode texte libre ou sections étiquetées (type PRIMO / SECUNDO).</p>
                </div>
                <div class="flex flex-wrap gap-3 text-sm">
                    <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 shadow-sm cursor-pointer hover:border-indigo-300">
                        <input type="radio" name="presentation_mode" value="simple" <?= $presentationMode === 'simple' ? 'checked' : '' ?>>
                        Texte libre
                    </label>
                    <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 shadow-sm cursor-pointer hover:border-indigo-300">
                        <input type="radio" name="presentation_mode" value="military" <?= $presentationMode === 'military' ? 'checked' : '' ?>>
                        Sections étiquetées
                    </label>
                </div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <label class="block text-xs font-bold text-slate-700 mb-2">Texte simple (mode libre)</label>
                <textarea name="simple_body" rows="8" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm leading-relaxed focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100"><?= htmlspecialchars((string) ($c['simple_body'] ?? '')) ?></textarea>
            </div>
            <div class="space-y-4" id="military-sections">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-xs font-bold text-slate-800">Sections étiquetées (mode militaire)</p>
                    <button type="button" id="add-military-section" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white shadow hover:bg-emerald-700">+ Section</button>
                </div>
                <?php foreach ($military as $i => $sec): ?>
                    <?php if (!is_array($sec)) { continue; } ?>
                    <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-4 space-y-3 shadow-sm">
                        <div class="grid gap-2 sm:grid-cols-3">
                            <input type="text" name="military_label[]" value="<?= htmlspecialchars((string) ($sec['label'] ?? '')) ?>" maxlength="32" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-mono" placeholder="Étiquette (ex. PRIMO)">
                            <input type="text" name="military_title[]" value="<?= htmlspecialchars((string) ($sec['title'] ?? '')) ?>" maxlength="200" class="rounded-lg border border-slate-300 px-3 py-2 text-sm sm:col-span-2" placeholder="Titre de section">
                        </div>
                        <textarea name="military_body[]" rows="4" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Contenu"><?= htmlspecialchars((string) ($sec['body'] ?? '')) ?></textarea>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <label class="block text-xs font-bold text-slate-700 mb-2">Attentes vis-à-vis des joueurs</label>
                <textarea name="expectations" rows="4" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm"><?= htmlspecialchars((string) ($c['expectations'] ?? '')) ?></textarea>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
            <h2 class="text-sm font-black uppercase tracking-widest text-slate-900">Contact public</h2>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Introduction (optionnel)</label>
                <input type="text" name="contact_intro" value="<?= htmlspecialchars((string) ($c['contact_intro'] ?? '')) ?>" maxlength="500" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">URL Discord (https://…)</label>
                <input type="url" name="contact_discord_url" value="<?= htmlspecialchars((string) ($c['contact_discord_url'] ?? '')) ?>" maxlength="500" class="w-full rounded border border-slate-300 px-3 py-2 text-sm" placeholder="https://discord.gg/…">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">E-mail affiché &amp; destinataire formulaire</label>
                <input type="email" name="contact_email" value="<?= htmlspecialchars((string) ($c['contact_email'] ?? '')) ?>" maxlength="255" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
            </div>
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="hidden" name="contact_form_enabled" value="0">
                <input type="checkbox" name="contact_form_enabled" value="1" class="mt-1" <?= !empty($c['contact_form_enabled']) ? 'checked' : '' ?>>
                <span class="text-sm text-slate-700">Activer le formulaire « nous écrire » sur la fiche publique (nécessite un e-mail valide ci-dessus)</span>
            </label>
        </section>
        </div>

        <div class="cp-panel hidden space-y-8 lg:space-y-10" data-cp-panel="candidature" id="cp-panel-candidature">
        <section id="pack-milsim-editor" class="rounded-2xl border border-emerald-100 bg-gradient-to-b from-white to-emerald-50/20 p-6 shadow-md space-y-4 scroll-mt-24">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between border-b border-emerald-100 pb-4">
                <div>
                    <h2 class="text-sm font-black uppercase tracking-widest text-slate-900">Pack MilSim — formulaire /enlistment</h2>
                    <p class="text-xs text-slate-500 mt-1 max-w-2xl">Affiché lorsque l’inscription est en <strong>MilSim complet</strong>. Ajustez le préambule, les champs (type de contrôle), les ROE et les textes latéraux.</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-700 max-w-xs shadow-sm">
                    <p class="font-bold text-slate-900 mb-1">Barre de navigation</p>
                    <p class="leading-relaxed">La marque affichée est fixée par la plateforme : <strong class="text-emerald-800"><?= htmlspecialchars(\App\Services\Community\EnlistmentMilsimPackService::PLATFORM_NAV_BRAND) ?></strong>. Elle n’est pas modifiable ici (cohérence Athena).</p>
                </div>
            </div>

            <details open class="group rounded-2xl border border-slate-200 bg-white shadow-sm">
                <summary class="cursor-pointer list-none rounded-t-2xl px-4 py-3 text-sm font-bold text-slate-900 hover:bg-slate-50 [&::-webkit-details-marker]:hidden">
                    <span class="inline-flex items-center gap-2"><span class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-600 text-xs font-black text-white">1</span> Préambule &amp; portail</span>
                </summary>
                <div class="space-y-4 border-t border-slate-100 p-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Lettre logo (préambule)</label>
                    <input type="text" name="em_logo_letter" value="<?= htmlspecialchars(mb_substr((string) ($em['logo_letter'] ?? 'F'), 0, 3)) ?>" maxlength="3" class="w-full max-w-[6rem] rounded-lg border border-slate-300 px-3 py-2 text-sm font-mono">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Filigrane (arrière-plan)</label>
                    <input type="text" name="em_watermark" value="<?= htmlspecialchars((string) ($em['watermark'] ?? '')) ?>" maxlength="40" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Titre portail (préambule)</label>
                <input type="text" name="em_portal_title" value="<?= htmlspecialchars((string) ($em['portal_title'] ?? '')) ?>" maxlength="200" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Sous-titre portail</label>
                <input type="text" name="em_portal_subtitle" value="<?= htmlspecialchars((string) ($em['portal_subtitle'] ?? '')) ?>" maxlength="400" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Titre préambule (grand)</label>
                <input type="text" name="em_preamble_title" value="<?= htmlspecialchars((string) ($em['preamble_title'] ?? '')) ?>" maxlength="200" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Texte d’introduction</label>
                <textarea name="em_preamble_lead" rows="3" maxlength="2000" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><?= htmlspecialchars((string) ($em['preamble_lead'] ?? '')) ?></textarea>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Bloc statut (une ligne par ligne)</label>
                <textarea name="em_preamble_status" rows="4" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-mono"><?= htmlspecialchars($emPreambleStatus) ?></textarea>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Bouton d’entrée</label>
                    <input type="text" name="em_preamble_cta" value="<?= htmlspecialchars((string) ($em['preamble_cta'] ?? '')) ?>" maxlength="120" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Pied préambule</label>
                    <input type="text" name="em_preamble_footer" value="<?= htmlspecialchars((string) ($em['preamble_footer'] ?? '')) ?>" maxlength="600" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
            </div>
                </div>
            </details>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Badge « classifié »</label>
                <input type="text" name="em_classified_badge" value="<?= htmlspecialchars((string) ($em['classified_badge'] ?? '')) ?>" maxlength="40" class="w-full max-w-md rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>

            <details open class="rounded-2xl border border-emerald-200 bg-white shadow-sm">
                <summary class="cursor-pointer list-none rounded-t-2xl px-4 py-3 text-sm font-bold text-slate-900 hover:bg-emerald-50/50 [&::-webkit-details-marker]:hidden">
                    <span class="inline-flex items-center gap-2"><span class="flex h-7 w-7 items-center justify-center rounded-lg bg-slate-900 text-xs font-black text-white">2</span> Champs du dossier candidat</span>
                </summary>
                <div class="border-t border-emerald-100 p-4 space-y-2">
                    <p class="text-xs text-slate-600 mb-3">Pour chaque champ : libellé, aide, <strong>type de contrôle</strong> (texte, zone, liste, oui/non). Les options s’affichent une par ligne pour les listes.</p>
                    <?php
                    $fieldsData = is_array($em['fields'] ?? null) ? $em['fields'] : [];
                    $inputPrefix = 'em_fld';
                    include base_path('views/partials/milsim_pack_fields_editor.php');
                    ?>
                </div>
            </details>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Préfixe progression (sidebar)</label>
                    <input type="text" name="em_progress_prefix" value="<?= htmlspecialchars((string) ($em['progress_prefix'] ?? '')) ?>" maxlength="80" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Titre bloc session</label>
                    <input type="text" name="em_session_block_title" value="<?= htmlspecialchars((string) ($em['session_block_title'] ?? '')) ?>" maxlength="120" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Titre ROE + règles (une par ligne)</label>
                <input type="text" name="em_roe_title" value="<?= htmlspecialchars((string) ($em['roe_title'] ?? '')) ?>" maxlength="160" class="w-full rounded border border-slate-300 px-3 py-2 text-sm mb-2">
                <textarea name="em_roe_lines" rows="5" class="w-full rounded border border-slate-300 px-3 py-2 text-sm"><?= htmlspecialchars($emRoeLines) ?></textarea>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Document control</label>
                    <input type="text" name="em_doc_control" value="<?= htmlspecialchars((string) ($em['doc_control'] ?? '')) ?>" maxlength="120" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Préfixe titre candidature</label>
                    <input type="text" name="em_candidate_prefix" value="<?= htmlspecialchars((string) ($em['candidate_prefix'] ?? '')) ?>" maxlength="80" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">File d’attente (libellé)</label>
                    <input type="text" name="em_queue_label" value="<?= htmlspecialchars((string) ($em['queue_label'] ?? '')) ?>" maxlength="120" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Libellé « Réf. »</label>
                    <input type="text" name="em_ref_label" value="<?= htmlspecialchars((string) ($em['ref_label'] ?? '')) ?>" maxlength="40" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Sécurité (sidebar)</label>
                    <input type="text" name="em_security_label" value="<?= htmlspecialchars((string) ($em['security_label'] ?? '')) ?>" maxlength="40" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Note opérationnelle — titre</label>
                <input type="text" name="em_op_note_title" value="<?= htmlspecialchars((string) ($em['op_note_title'] ?? '')) ?>" maxlength="160" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Colonne 1 (texte)</label>
                    <textarea name="em_op_col1" rows="3" class="w-full rounded border border-slate-300 px-3 py-2 text-sm"><?= htmlspecialchars((string) ($em['op_col1'] ?? '')) ?></textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Avertissement IA (rouge)</label>
                    <input type="text" name="em_op_ai_warning" value="<?= htmlspecialchars((string) ($em['op_ai_warning'] ?? '')) ?>" maxlength="600" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Colonne 2</label>
                <textarea name="em_op_col2" rows="2" class="w-full rounded border border-slate-300 px-3 py-2 text-sm"><?= htmlspecialchars((string) ($em['op_col2'] ?? '')) ?></textarea>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Note d’archive (encadré gris)</label>
                <textarea name="em_archive_note" rows="2" maxlength="1200" class="w-full rounded border border-slate-300 px-3 py-2 text-sm"><?= htmlspecialchars((string) ($em['archive_note'] ?? '')) ?></textarea>
            </div>
            <div class="space-y-3">
                <p class="text-xs font-bold text-slate-800">Titres des blocs du formulaire (sections I à IV + entête)</p>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Titre section 0 (mode de candidature)</label>
                    <input type="text" name="em_section_0" value="<?= htmlspecialchars((string) ($em['section_0'] ?? '')) ?>" maxlength="200" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div class="grid gap-2 sm:grid-cols-2">
                    <?php for ($si = 1; $si <= 4; $si++): ?>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Titre section <?= $si ?></label>
                        <input type="text" name="em_section_<?= $si ?>" value="<?= htmlspecialchars((string) ($em['section_' . $si] ?? '')) ?>" maxlength="200" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Questions engagement (lignes)</label>
                <input type="text" name="em_commitment_q13" value="<?= htmlspecialchars((string) ($em['commitment_q13'] ?? '')) ?>" maxlength="400" class="w-full rounded border border-slate-300 px-3 py-2 text-sm mb-2" placeholder="Question 13">
                <input type="text" name="em_availability_q15" value="<?= htmlspecialchars((string) ($em['availability_q15'] ?? '')) ?>" maxlength="400" class="w-full rounded border border-slate-300 px-3 py-2 text-sm" placeholder="Question 15">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Case confirmation IA</label>
                <input type="text" name="em_ai_checkbox" value="<?= htmlspecialchars((string) ($em['ai_checkbox'] ?? '')) ?>" maxlength="400" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Bouton envoi</label>
                    <input type="text" name="em_submit_button" value="<?= htmlspecialchars((string) ($em['submit_button'] ?? '')) ?>" maxlength="120" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Pied bouton</label>
                    <input type="text" name="em_submit_footer" value="<?= htmlspecialchars((string) ($em['submit_footer'] ?? '')) ?>" maxlength="200" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                </div>
            </div>
            <details class="rounded-2xl border border-dashed border-slate-300 bg-slate-50/80">
                <summary class="cursor-pointer px-4 py-3 text-xs font-bold text-slate-600 [&::-webkit-details-marker]:hidden">Import structuré (expert) — surcharge des champs</summary>
                <div class="border-t border-slate-200 p-4">
                    <p class="text-[11px] text-slate-500 mb-2">Optionnel. Reprend les mêmes réglages que l’éditeur visuel ; pratique pour coller une configuration fournie par l’équipe technique (widgets et options avancées).</p>
                    <textarea name="em_fields_json" rows="10" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs font-mono"><?= htmlspecialchars($emFieldsJson) ?></textarea>
                </div>
            </details>
        </section>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-4 pt-4 border-t border-slate-200">
            <p class="text-xs text-slate-500 max-w-md">Les changements s’appliquent à toute la fiche. Utilisez le bouton fixe en bas à droite pour enregistrer avec le récapitulatif.</p>
            <button type="submit" class="px-6 py-3 bg-slate-900 text-white text-sm font-bold rounded-xl hover:bg-emerald-700 shadow-md">Enregistrer (direct)</button>
        </div>
    </form>

    <div id="presentation-save-dock" class="fixed bottom-0 left-0 right-0 z-40 pointer-events-none">
        <div class="mx-auto max-w-6xl px-4 pb-4 pt-2 flex justify-end pointer-events-auto">
            <button type="button" id="presentation-open-recap" class="inline-flex items-center gap-2 rounded-2xl bg-slate-900 px-5 py-3.5 text-sm font-black uppercase tracking-wider text-white shadow-lg shadow-slate-900/25 ring-1 ring-white/10 transition hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400">
                <svg class="h-4 w-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                Enregistrer
            </button>
        </div>
    </div>

    <div id="presentation-recap-modal" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true" aria-labelledby="presentation-recap-title">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" data-recap-close="1" tabindex="-1"></div>
        <div class="absolute inset-x-4 top-[8vh] mx-auto max-h-[min(84vh,640px)] w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 id="presentation-recap-title" class="text-lg font-black text-slate-900">Récapitulatif avant enregistrement</h2>
                <p class="mt-1 text-xs text-slate-500">Vérifiez les changements détectés depuis l’ouverture de la page.</p>
            </div>
            <div id="presentation-recap-body" class="max-h-[min(50vh,320px)] overflow-y-auto px-5 py-4 text-sm text-slate-700"></div>
            <p id="presentation-recap-empty" class="hidden px-5 text-sm text-slate-500">Aucune modification détectée par rapport au chargement de la page.</p>
            <div class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 bg-slate-50 px-5 py-4">
                <button type="button" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-slate-700 hover:bg-slate-100" data-recap-close="1">Annuler</button>
                <button type="button" id="presentation-confirm-submit" class="rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-black uppercase tracking-wider text-white shadow-sm hover:bg-emerald-700">Confirmer l’enregistrement</button>
            </div>
        </div>
    </div>

    <p class="mt-10"><a href="<?= htmlspecialchars(url('back-office')) ?>" class="text-sm text-slate-600 underline">Retour administration organisation</a></p>
</div>
<script>
(function () {
    var form = document.getElementById('community-presentation-form');
    if (form) {
        function cssEsc(s) {
            if (typeof CSS !== 'undefined' && typeof CSS.escape === 'function') return CSS.escape(s);
            return String(s).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
        }
        function formToState(f) {
            var fd = new FormData(f);
            var o = {};
            fd.forEach(function (v, k) {
                if (o[k] === undefined) o[k] = [];
                o[k].push(String(v));
            });
            Object.keys(o).forEach(function (k) { o[k].sort(); });
            return o;
        }
        var initialState = formToState(form);
        var recapBypass = false;

        function truncate(s, n) {
            s = String(s || '');
            if (s.length <= n) return s;
            return s.slice(0, n - 1) + '…';
        }
        function fieldLabel(el) {
            if (!el || !el.name) return el && el.name ? el.name : '';
            if (el.id) {
                var lb = form.querySelector('label[for="' + cssEsc(el.id) + '"]');
                if (lb) return truncate(lb.textContent.replace(/\s+/g, ' ').trim(), 100);
            }
            var p = el.closest('label');
            if (p) return truncate(p.textContent.replace(/\s+/g, ' ').trim(), 100);
            var sec = el.closest('section');
            var h2 = sec ? sec.querySelector('h2') : null;
            var secTitle = h2 ? h2.textContent.trim() : 'Formulaire';
            return secTitle + ' — ' + el.name;
        }
        function getRepresentativeElement(name) {
            var list = form.querySelectorAll('[name="' + cssEsc(name) + '"]');
            return list.length ? list[0] : null;
        }

        var modal = document.getElementById('presentation-recap-modal');
        var bodyEl = document.getElementById('presentation-recap-body');
        var emptyEl = document.getElementById('presentation-recap-empty');
        var btnOpen = document.getElementById('presentation-open-recap');
        var btnConfirm = document.getElementById('presentation-confirm-submit');

        function openModal() {
            var cur = formToState(form);
            var keys = Object.keys(initialState).concat(Object.keys(cur)).filter(function (v, i, a) { return a.indexOf(v) === i; });
            var changes = [];
            keys.forEach(function (k) {
                var a = JSON.stringify(initialState[k] || []);
                var b = JSON.stringify(cur[k] || []);
                if (a !== b) changes.push(k);
            });
            bodyEl.innerHTML = '';
            if (changes.length === 0) {
                emptyEl.classList.remove('hidden');
            } else {
                emptyEl.classList.add('hidden');
                var ul = document.createElement('ul');
                ul.className = 'space-y-3';
                changes.forEach(function (name) {
                    var el = getRepresentativeElement(name);
                    var lab = fieldLabel(el);
                    var before = truncate((initialState[name] || []).join(', ') || '—', 120);
                    var after = truncate((cur[name] || []).join(', ') || '—', 120);
                    var li = document.createElement('li');
                    li.className = 'rounded-lg border border-slate-100 bg-slate-50/80 px-3 py-2';
                    var pTitle = document.createElement('p');
                    pTitle.className = 'font-bold text-slate-900';
                    pTitle.textContent = lab;
                    var pBefore = document.createElement('p');
                    pBefore.className = 'mt-1 text-xs text-slate-600';
                    pBefore.appendChild(document.createTextNode('Avant : '));
                    pBefore.appendChild(document.createTextNode(before));
                    var pAfter = document.createElement('p');
                    pAfter.className = 'mt-0.5 text-xs text-emerald-800';
                    pAfter.appendChild(document.createTextNode('Après : '));
                    pAfter.appendChild(document.createTextNode(after));
                    li.appendChild(pTitle);
                    li.appendChild(pBefore);
                    li.appendChild(pAfter);
                    ul.appendChild(li);
                });
                bodyEl.appendChild(ul);
            }
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            if (btnConfirm) btnConfirm.focus();
        }
        function closeModal() {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }
        form.addEventListener('submit', function (e) {
            if (recapBypass) {
                recapBypass = false;
                return;
            }
            e.preventDefault();
            openModal();
        });
        if (btnOpen) btnOpen.addEventListener('click', function () { openModal(); });
        modal.querySelectorAll('[data-recap-close]').forEach(function (el) {
            el.addEventListener('click', closeModal);
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) closeModal();
        });
        if (btnConfirm) btnConfirm.addEventListener('click', function () {
            if (typeof form.reportValidity === 'function' && !form.reportValidity()) return;
            closeModal();
            if (typeof form.requestSubmit === 'function') {
                recapBypass = true;
                form.requestSubmit();
            } else {
                form.submit();
            }
        });
    }
})();
(function () {
    var container = document.getElementById('military-sections');
    var btn = document.getElementById('add-military-section');
    if (!container || !btn) return;
    btn.addEventListener('click', function () {
        var n = container.querySelectorAll('textarea[name="military_body[]"]').length + 1;
        var wrap = document.createElement('div');
        wrap.className = 'rounded-xl border border-slate-100 bg-slate-50 p-4 space-y-2 military-section-row';
        wrap.innerHTML =
            '<div class="grid gap-2 sm:grid-cols-3">' +
            '<input type="text" name="military_label[]" value="POINT ' + n + '" maxlength="32" class="rounded border border-slate-300 px-2 py-1 text-xs font-mono" placeholder="PRIMO">' +
            '<input type="text" name="military_title[]" value="" maxlength="200" class="rounded border border-slate-300 px-2 py-1 text-sm sm:col-span-2" placeholder="Titre">' +
            '</div>' +
            '<textarea name="military_body[]" rows="4" class="w-full rounded border border-slate-300 px-3 py-2 text-sm" placeholder="Contenu"></textarea>';
        container.appendChild(wrap);
    });
})();
(function () {
    var tabs = document.querySelectorAll('[data-cp-tab]');
    var panels = document.querySelectorAll('[data-cp-panel]');
    if (!tabs.length || !panels.length) return;
    var order = ['visibilite', 'vitrine', 'profil', 'textes', 'candidature'];
    function show(id) {
        if (order.indexOf(id) === -1) id = 'visibilite';
        panels.forEach(function (p) {
            var on = p.getAttribute('data-cp-panel') === id;
            p.classList.toggle('hidden', !on);
        });
        tabs.forEach(function (t) {
            var on = t.getAttribute('data-cp-tab') === id;
            t.setAttribute('aria-selected', on ? 'true' : 'false');
            t.classList.toggle('bg-emerald-50', on);
            t.classList.toggle('border-emerald-500', on);
            t.classList.toggle('text-emerald-950', on);
            t.classList.toggle('shadow', on);
            t.classList.toggle('ring-1', on);
            t.classList.toggle('ring-emerald-500/30', on);
        });
        try {
            if (history.replaceState) {
                history.replaceState(null, '', '#' + id);
            } else {
                location.hash = id;
            }
        } catch (e) {}
        var navEl = document.querySelector('.cp-presentation-tabs');
        if (navEl && typeof navEl.scrollIntoView === 'function') {
            navEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } else {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }
    tabs.forEach(function (t) {
        t.addEventListener('click', function () {
            show(String(t.getAttribute('data-cp-tab') || ''));
        });
    });
    window.addEventListener('hashchange', function () {
        var h = (location.hash || '').replace(/^#/, '');
        if (order.indexOf(h) !== -1) show(h);
    });
    var initial = (location.hash || '').replace(/^#/, '');
    show(order.indexOf(initial) !== -1 ? initial : 'visibilite');
})();
</script>
