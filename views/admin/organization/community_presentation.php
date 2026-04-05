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
$err = \App\Core\Session::getFlash('error');
$ok = \App\Core\Session::getFlash('success');
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
?>
<div class="max-w-3xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-2">Fiche registre &amp; contact</h1>
    <p class="text-slate-600 text-sm mb-2">Contenu public sur la page <code class="bg-slate-100 px-1 rounded">/c/<?= htmlspecialchars((string) ($tenant['slug'] ?? '')) ?></code> et dans le catalogue <a href="<?= htmlspecialchars(url('communities')) ?>" class="text-emerald-700 underline">Registre</a>.</p>
    <p class="text-slate-500 text-xs mb-8"><a href="<?= htmlspecialchars(url('back-office/community')) ?>" class="underline">Identité &amp; code rejoindre</a></p>

    <?php if ($err): ?><p class="text-red-600 text-sm mb-4"><?= htmlspecialchars($err) ?></p><?php endif; ?>
    <?php if ($ok): ?><p class="text-emerald-700 text-sm mb-4"><?= htmlspecialchars($ok) ?></p><?php endif; ?>

    <form method="post" action="<?= htmlspecialchars(url('back-office/community/presentation')) ?>" class="space-y-10">
        <?= \App\Core\Csrf::field() ?>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
            <h2 class="text-sm font-black uppercase tracking-widest text-slate-900">Visibilité</h2>
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="hidden" name="registry_listed" value="0">
                <input type="checkbox" name="registry_listed" value="1" class="mt-1" <?= (!array_key_exists('registry_listed', $c) || !empty($c['registry_listed'])) ? 'checked' : '' ?>>
                <span class="text-sm text-slate-700">Apparaître dans le registre public (<code class="text-xs">/communities</code>)</span>
            </label>
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="hidden" name="forum_members_only" value="0">
                <input type="checkbox" name="forum_members_only" value="1" class="mt-1" <?= !empty($c['forum_members_only']) ? 'checked' : '' ?>>
                <span class="text-sm text-slate-700">Forum réservé aux membres (masquer le bouton « Accéder au forum » pour les visiteurs non membres)</span>
            </label>
        </section>

        <section class="rounded-2xl border border-emerald-200 bg-emerald-50/40 p-6 shadow-sm space-y-4">
            <h2 class="text-sm font-black uppercase tracking-widest text-slate-900">Fiche publique vitrine</h2>
            <p class="text-xs text-slate-600">Mise en page étendue sur <code class="text-xs bg-white px-1 rounded">/c/<?= htmlspecialchars((string) ($tenant['slug'] ?? '')) ?></code> (hero, stats, ORBAT, roster opt-in).</p>
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
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
            <h2 class="text-sm font-black uppercase tracking-widest text-slate-900">Inscription publique</h2>
            <p class="text-xs text-slate-500">Page <code class="bg-slate-100 px-1 rounded">/c/<?= htmlspecialchars((string) ($tenant['slug'] ?? '')) ?>/enlistment</code></p>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Mode du formulaire de candidature</label>
                <select name="registration_mode" class="w-full max-w-md rounded border border-slate-300 px-3 py-2 text-sm">
                    <option value="milsim" <?= $registrationMode === 'milsim' ? 'selected' : '' ?>>MilSim complet (dossier détaillé, CV, disponibilités, etc.)</option>
                    <option value="simple" <?= $registrationMode === 'simple' ? 'selected' : '' ?>>Mode simple (champs réduits, onboarding rapide)</option>
                </select>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
            <h2 class="text-sm font-black uppercase tracking-widest text-slate-900">Jeu &amp; technique</h2>
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
                <p class="text-xs font-bold text-slate-700 mb-2">Tags catalogue (registre public)</p>
                <p class="text-[11px] text-slate-500 mb-2">Affichés sur <code class="text-xs">/communities</code> lorsque l’unité est listée. Choisissez ce qui décrit le mieux votre communauté.</p>
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
                <summary class="cursor-pointer px-4 py-3 text-xs font-bold text-slate-600 [&::-webkit-details-marker]:hidden">Import JSON (expert) — surcharge des champs</summary>
                <div class="border-t border-slate-200 p-4">
                    <p class="text-[11px] text-slate-500 mb-2">Optionnel. Même clés que l’éditeur visuel ; utile pour copier-coller une config. Peut compléter <code class="text-xs">widget</code> et <code class="text-xs">options</code>.</p>
                    <textarea name="em_fields_json" rows="10" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs font-mono"><?= htmlspecialchars($emFieldsJson) ?></textarea>
                </div>
            </details>
        </section>

        <button type="submit" class="px-6 py-3 bg-slate-900 text-white text-sm font-bold rounded-xl hover:bg-emerald-700">Enregistrer</button>
    </form>

    <p class="mt-10"><a href="<?= htmlspecialchars(url('back-office')) ?>" class="text-sm text-slate-600 underline">Retour administration organisation</a></p>
</div>
<script>
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
</script>
