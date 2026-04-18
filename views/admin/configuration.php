<?php
declare(strict_types=1);

$units = $units ?? [];
$grades = $grades ?? [];
$matriculeConfig = $matriculeConfig ?? null;
$adminPanels = $adminPanels ?? [];
$tenant = $tenant ?? [];
$community = is_array($community ?? null) ? $community : [];
$onboardingHealth = $onboardingHealth ?? ['gaps' => [], 'needs_recovery' => false];
$enlistmentCounts = $enlistmentCounts ?? [];
$publicCommunityUrl = (string) ($publicCommunityUrl ?? '');
$settings = is_array($settings ?? null) ? $settings : [];

$gate = \App\Core\Gate::getInstance();
$canDocs = $gate->allows('documents.upload') || $gate->allows('admin.access');
$canTraining = $gate->allows('training.manage') || $gate->allows('training.assign') || $gate->allows('admin.access');

$nUnits = count($units);
$nGrades = count($grades);
$nPanels = count($adminPanels);
$pendingEnlist = (int) ($enlistmentCounts['submitted'] ?? 0);

$orbatVis = (string) ($community['orbat_visibility'] ?? 'members');
$orbatLabel = match ($orbatVis) {
    'public' => 'Public',
    'command' => 'Commandement',
    default => 'Membres uniquement',
};
$layoutPub = ($community['public_page_layout'] ?? 'legacy') === 'showcase' ? 'Vitrine' : 'Classique';
$locale = (string) ($community['default_locale'] ?? ($tenant['default_locale'] ?? 'fr-FR'));
$gradeSystem = trim((string) ($settings['grade_system_code'] ?? ''));
$gradeSystemLabel = $gradeSystem !== '' ? $gradeSystem : '—';

$card = static function (string $href, string $title, string $desc, string $accentClass = 'border-slate-200 hover:border-blue-300 hover:bg-blue-50/30'): void {
    ?>
    <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" class="group flex flex-col rounded-xl border bg-white p-5 shadow-sm transition-all <?= htmlspecialchars($accentClass, ENT_QUOTES, 'UTF-8') ?>">
        <span class="text-sm font-bold text-slate-900 group-hover:text-blue-900"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></span>
        <span class="mt-1.5 text-xs text-slate-600 leading-relaxed"><?= htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') ?></span>
        <span class="mt-3 text-xs font-semibold text-blue-700 opacity-0 group-hover:opacity-100 transition-opacity">Ouvrir →</span>
    </a>
    <?php
};
?>
<div class="bg-slate-50 min-h-[calc(100vh-3.5rem)]">
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10 lg:py-12">
    <header class="mb-10 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500 mb-2">Organisation</p>
            <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Configuration</h1>
            <p class="mt-2 text-slate-600 text-sm max-w-2xl leading-relaxed">
                Centre de réglages : identité, page publique, structure, référentiels, recrutement, accès et contenu. Les modifications détaillées se font dans chaque module.
            </p>
        </div>
        <a href="<?= url('back-office') ?>" class="shrink-0 inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">← Back-office</a>
    </header>

    <?php if (\App\Core\Session::get('success')): ?>
    <p class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"><?= htmlspecialchars(\App\Core\Session::get('success')) ?></p>
    <?php \App\Core\Session::forget('success'); endif; ?>
    <?php if (\App\Core\Session::get('error')): ?>
    <p class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900"><?= htmlspecialchars(\App\Core\Session::get('error')) ?></p>
    <?php \App\Core\Session::forget('error'); endif; ?>

    <?php
    $appDebug = (bool) ($appDebug ?? config('app.debug', false));
    ?>
    <?php if ($appDebug): ?>
    <section class="mb-10 rounded-2xl border border-amber-400/80 bg-amber-50/95 p-6 shadow-sm">
        <h2 class="text-sm font-black uppercase tracking-widest text-amber-950">Debug tenant (APP_DEBUG)</h2>
        <p class="mt-2 text-sm text-amber-950/90 leading-relaxed">
            Synchronise les comptes liés aux <strong>candidatures acceptées</strong> (<code class="rounded bg-amber-100/80 px-1 text-xs">submitter_user_id</code>) :
            passage en <strong>membre actif</strong>, e-mail considéré comme vérifié, rôle <strong>« Période d’essai » (probation)</strong> — sinon <strong>Visiteur (invite)</strong> — sinon <strong>Opérateur (member)</strong>,
            et affectation <strong>Recrue</strong> sur la <strong>première unité</strong> de l’ORBAT (si une unité existe).
        </p>
        <form method="post" action="<?= htmlspecialchars(url('back-office/configuration/debug-recruit-sync'), ENT_QUOTES, 'UTF-8') ?>" class="mt-4 flex flex-wrap items-center gap-3">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-amber-900 px-4 py-2.5 text-sm font-bold text-white hover:bg-amber-950">
                Forcer synchro recrues / affectations
            </button>
            <span class="text-xs text-amber-900/80">Réservé au développement — ne pas activer APP_DEBUG en production.</span>
        </form>
    </section>
    <?php endif; ?>

    <?php if (!empty($onboardingHealth['needs_recovery'])): ?>
    <div class="mb-8 rounded-2xl border border-amber-200 bg-amber-50/90 p-5 shadow-sm">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="text-sm font-bold text-amber-950">Rattrapage configuration recommandé</h2>
                <ul class="mt-2 list-disc pl-5 text-sm text-amber-950/90 space-y-1">
                    <?php foreach ($onboardingHealth['gaps'] as $gap): ?>
                    <li><?= htmlspecialchars((string) $gap, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <a href="<?= url('back-office/onboarding-recovery') ?>" class="shrink-0 inline-flex items-center justify-center rounded-lg bg-amber-900 px-4 py-2.5 text-sm font-bold text-white hover:bg-amber-950">Assistant de rattrapage</a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Synthèse -->
    <section class="mb-10 grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Unités (aperçu)</p>
            <p class="mt-1 text-2xl font-black text-slate-900 tabular-nums"><?= $nUnits ?></p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Grades</p>
            <p class="mt-1 text-2xl font-black text-slate-900 tabular-nums"><?= $nGrades ?></p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Candidatures en attente</p>
            <p class="mt-1 text-2xl font-black <?= $pendingEnlist > 0 ? 'text-amber-700' : 'text-slate-900' ?> tabular-nums"><?= $pendingEnlist ?></p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Panneaux fiche perso.</p>
            <p class="mt-1 text-2xl font-black text-slate-900 tabular-nums"><?= $nPanels ?></p>
        </div>
    </section>

    <!-- Paramètres lus (référence rapide) -->
    <section class="mb-10 rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-4">
            <h2 class="text-sm font-bold text-slate-900">Paramètres actuels (aperçu)</h2>
            <p class="text-xs text-slate-500 mt-0.5">Résumé issu de l’identité communauté et des réglages publics. Édition dans les écrans dédiés ci-dessous.</p>
        </div>
        <dl class="grid sm:grid-cols-2 divide-y sm:divide-y-0 sm:divide-x divide-slate-100">
            <div class="p-5 space-y-3">
                <div class="flex justify-between gap-3 text-sm">
                    <dt class="text-slate-500">Nom affiché</dt>
                    <dd class="font-semibold text-slate-900 text-right truncate max-w-[55%]" title="<?= htmlspecialchars((string) ($tenant['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($tenant['name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></dd>
                </div>
                <div class="flex justify-between gap-3 text-sm">
                    <dt class="text-slate-500">Adresse courte du lien</dt>
                    <dd class="font-mono text-xs text-slate-800 text-right"><?= htmlspecialchars((string) ($tenant['slug'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></dd>
                </div>
                <div class="flex justify-between gap-3 text-sm">
                    <dt class="text-slate-500">Réf. grades (système)</dt>
                    <dd class="font-mono text-xs text-right"><?= htmlspecialchars($gradeSystemLabel, ENT_QUOTES, 'UTF-8') ?></dd>
                </div>
            </div>
            <div class="p-5 space-y-3">
                <div class="flex justify-between gap-3 text-sm">
                    <dt class="text-slate-500">Page publique</dt>
                    <dd class="font-semibold text-slate-900"><?= htmlspecialchars($layoutPub, ENT_QUOTES, 'UTF-8') ?></dd>
                </div>
                <div class="flex justify-between gap-3 text-sm">
                    <dt class="text-slate-500">Visibilité ORBAT</dt>
                    <dd class="font-semibold text-slate-900"><?= htmlspecialchars($orbatLabel, ENT_QUOTES, 'UTF-8') ?></dd>
                </div>
                <div class="flex justify-between gap-3 text-sm">
                    <dt class="text-slate-500">Locale</dt>
                    <dd class="font-mono text-xs"><?= htmlspecialchars($locale, ENT_QUOTES, 'UTF-8') ?></dd>
                </div>
            </div>
        </dl>
        <?php if ($publicCommunityUrl !== ''): ?>
        <div class="px-5 py-3 bg-slate-50 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2">
            <span class="text-xs text-slate-600">Page publique</span>
            <a href="<?= htmlspecialchars($publicCommunityUrl, ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-semibold text-blue-700 hover:underline break-all"><?= htmlspecialchars($publicCommunityUrl, ENT_QUOTES, 'UTF-8') ?></a>
        </div>
        <?php endif; ?>
    </section>

    <div class="space-y-10">
        <section>
            <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-4">Identité &amp; page publique</h2>
            <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
                <?php $card(url('back-office/community'), 'Identité & code rejoindre', 'Nom, slug URL, code communauté pour rejoindre l’unité.'); ?>
                <?php $card(url('back-office/community/presentation'), 'Fiche registre & vitrine', 'Textes publics, modèle vitrine/classique, ORBAT, recrutement, forum visiteurs.'); ?>
                <?php $card(url('back-office/alerts'), 'Alertes & annonces', 'Bandeaux et messages pour les membres connectés.'); ?>
            </div>
        </section>

        <section>
            <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-4">Structure, grades &amp; personnel</h2>
            <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
                <?php $card(url('back-office/groups'), 'Unités & ORBAT', 'Hiérarchie, groupes, sections, équipes.'); ?>
                <?php $card(url('back-office/teams'), 'Équipes', 'Équipes tactiques et composition.'); ?>
                <?php $card(url('back-office/referentiels/grades'), 'Référentiel grades', 'Rangs, codes OTAN, libellés pour les fiches.'); ?>
                <?php $card(url('back-office/personnel-job-roles'), 'Rôles métier & fiches', 'Rôles de poste, affectations et champs dossier.'); ?>
            </div>
        </section>

        <section>
            <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-4">Accès, recrutement &amp; rôles</h2>
            <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
                <?php $card(url('back-office/users'), 'Utilisateurs', 'Comptes, désactivation, fiches liées.'); ?>
                <?php $card(url('back-office/invitations'), 'Invitations', 'Inviter par e-mail avec rôle proposé.'); ?>
                <?php $card(url('back-office/roles'), 'Rôles & permissions', 'Liste des rôles et détail des droits.'); ?>
                <?php $card(url('back-office/roles-functions'), 'Rôles & fonctions (toile)', 'Catalogue FR/US, graphe, unités, templates.'); ?>
                <?php $card(url('back-office/roles/presets'), 'Profils de permissions', 'Appliquer un jeu complet de droits à un rôle en une fois (hors droits plateforme).'); ?>
                <?php $card(url('back-office/recruitments'), 'Candidatures', 'Dossiers, décisions, file d’attente.' . ($pendingEnlist > 0 ? ' (' . $pendingEnlist . ' en attente)' : '')); ?>
                <?php $card(url('back-office/recruitments/settings'), 'Paramètres SLA (recrutement)', 'Configuration du délai interne sans action et suivi des dossiers bloqués.', 'border-sky-200/80 hover:border-sky-400 hover:bg-sky-50/40'); ?>
                <?php $card(url('back-office/recruitments/messages-prefaits'), 'Messages préfaits (recrutement)', 'Modèles de commentaires internes pour traiter les candidatures.', 'border-emerald-200/80 hover:border-emerald-400 hover:bg-emerald-50/40'); ?>
                <?php $card(url('back-office/positions'), 'Postes organisationnels', 'Intitulés de fonction et affectations, distincts des rôles et habilitations.'); ?>
                <?php $card(url('back-office/roleplay-followup'), 'Suivi roleplay', 'Pilotage tutorat, timeline dossiers et avancement individuel.'); ?>
            </div>
        </section>

        <?php if ($gate->allows('admin.organization') || $gate->allows('admin.access')): ?>
        <?php
        $memberChooseRole = !empty($community['member_can_choose_display_role']);
        $badgesMode = (string) ($community['display_badges_mode'] ?? 'primary_only');
        if ($badgesMode !== 'multi') {
            $badgesMode = 'primary_only';
        }
        $orgRoleLabels = (string) ($community['organization_role_labels'] ?? 'fr');
        if ($orgRoleLabels !== 'en') {
            $orgRoleLabels = 'fr';
        }
        ?>
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-sm font-bold text-slate-900">Affichage des rôles (membres)</h2>
            <p class="mt-1 text-xs text-slate-600 max-w-2xl">Autorisez les titulaires à choisir quel rôle organisation apparaît en priorité sur le forum et dans le portail, et définissez si plusieurs badges peuvent être affichés à terme.</p>
            <form method="post" action="<?= htmlspecialchars(url('back-office/configuration/member-role-display'), ENT_QUOTES, 'UTF-8') ?>" class="mt-5 space-y-4 max-w-2xl">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="member_can_choose_display_role" value="0">
                <label class="flex items-start gap-3 text-sm text-slate-800 cursor-pointer">
                    <input type="checkbox" name="member_can_choose_display_role" value="1" class="mt-1 rounded border-slate-300" <?= $memberChooseRole ? 'checked' : '' ?>>
                    <span>Permettre à chaque membre de choisir le rôle affiché en priorité (parmi ses rôles organisation).</span>
                </label>
                <div>
                    <label for="display_badges_mode" class="block text-xs font-semibold text-slate-700 mb-1">Affichage des badges</label>
                    <select id="display_badges_mode" name="display_badges_mode" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <option value="primary_only" <?= $badgesMode === 'primary_only' ? 'selected' : '' ?>>Un seul badge principal</option>
                        <option value="multi" <?= $badgesMode === 'multi' ? 'selected' : '' ?>>Plusieurs badges (quand l’interface le proposera)</option>
                    </select>
                </div>
                <div>
                    <label for="organization_role_labels" class="block text-xs font-semibold text-slate-700 mb-1">Langue des intitulés de rôles (administration)</label>
                    <p class="text-xs text-slate-500 mb-2">Invitations, attribution des comptes et listes déroulantes du back-office : libellés en français ou en anglais (doctrine américaine), sans modifier les droits attachés aux rôles.</p>
                    <select id="organization_role_labels" name="organization_role_labels" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <option value="fr" <?= $orgRoleLabels === 'fr' ? 'selected' : '' ?>>Français</option>
                        <option value="en" <?= $orgRoleLabels === 'en' ? 'selected' : '' ?>>Anglais (doctrine américaine)</option>
                    </select>
                </div>
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-bold text-white hover:bg-slate-800">Enregistrer</button>
            </form>
        </section>
        <?php endif; ?>

        <?php if ($gate->allows('admin.organization') || $gate->allows('admin.access')): ?>
        <?php
        $rpCfg = is_array($community['roleplay_followup'] ?? null) ? $community['roleplay_followup'] : [];
        $rpStages = is_array($rpCfg['stages'] ?? null) ? $rpCfg['stages'] : ['Pré-qualification', 'Tutorat', 'Validation', 'Intégration active'];
        $rpTracks = is_array($rpCfg['recruitment_tracks'] ?? null) ? $rpCfg['recruitment_tracks'] : ['Infanterie', 'Support', 'Commandement'];
        $rpEligibility = is_array($rpCfg['eligibility'] ?? null) ? $rpCfg['eligibility'] : [];
        ?>
        <section class="rounded-2xl border border-emerald-200 bg-white p-6 shadow-sm">
            <h2 class="text-sm font-bold text-slate-900">Back-office roleplay — suivi individuel</h2>
            <p class="mt-1 text-xs text-slate-600 max-w-3xl">Active une section optionnelle dans les dossiers personnel : tuteur, timeline opérationnelle (entretien, visite médicale, rotation), avancement et filière de recrutement. Tous les paramètres sont isolés par tenant.</p>
            <form method="post" action="<?= htmlspecialchars(url('back-office/configuration/roleplay-followup'), ENT_QUOTES, 'UTF-8') ?>" class="mt-5 space-y-4 max-w-3xl">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                <label class="flex items-start gap-3 text-sm text-slate-800 cursor-pointer">
                    <input type="checkbox" name="rp_followup_enabled" value="1" class="mt-1 rounded border-slate-300" <?= !empty($rpCfg['enabled']) ? 'checked' : '' ?>>
                    <span>Activer la section roleplay back-office (affichée sur les fiches et formulaires dossier).</span>
                </label>
                <label class="flex items-start gap-3 text-sm text-slate-800 cursor-pointer">
                    <input type="checkbox" name="rp_followup_optional" value="1" class="mt-1 rounded border-slate-300" <?= !empty($rpCfg['optional']) ? 'checked' : '' ?>>
                    <span>Section facultative (ne bloque pas la complétude globale si les champs roleplay ne sont pas renseignés).</span>
                </label>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="rp_followup_stages" class="block text-xs font-semibold text-slate-700 mb-1">Étapes d’avancement (une ligne = une étape)</label>
                        <textarea id="rp_followup_stages" name="rp_followup_stages" rows="5" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><?= htmlspecialchars(implode("\n", array_map(static fn ($v) => trim((string) $v), $rpStages))) ?></textarea>
                    </div>
                    <div>
                        <label for="rp_followup_tracks" class="block text-xs font-semibold text-slate-700 mb-1">Filières recrutement (une ligne = une filière)</label>
                        <textarea id="rp_followup_tracks" name="rp_followup_tracks" rows="5" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><?= htmlspecialchars(implode("\n", array_map(static fn ($v) => trim((string) $v), $rpTracks))) ?></textarea>
                    </div>
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="rp_eligibility_min_completeness" class="block text-xs font-semibold text-slate-700 mb-1">Éligibilité — complétude minimum (%)</label>
                        <input type="number" min="0" max="100" id="rp_eligibility_min_completeness" name="rp_eligibility_min_completeness" value="<?= (int) ($rpEligibility['min_completeness'] ?? 50) ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="rp_eligibility_min_readiness" class="block text-xs font-semibold text-slate-700 mb-1">Éligibilité — disponibilité minimum (%)</label>
                        <input type="number" min="0" max="100" id="rp_eligibility_min_readiness" name="rp_eligibility_min_readiness" value="<?= (int) ($rpEligibility['min_readiness'] ?? 30) ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    </div>
                </div>
                <div class="flex flex-wrap gap-4 text-sm text-slate-800">
                    <label class="inline-flex items-center gap-2"><input type="checkbox" name="rp_eligibility_require_unit" value="1" class="rounded border-slate-300" <?= !empty($rpEligibility['require_unit']) ? 'checked' : '' ?>> Unité obligatoire</label>
                    <label class="inline-flex items-center gap-2"><input type="checkbox" name="rp_eligibility_require_callsign" value="1" class="rounded border-slate-300" <?= !empty($rpEligibility['require_callsign']) ? 'checked' : '' ?>> Callsign obligatoire</label>
                    <label class="inline-flex items-center gap-2"><input type="checkbox" name="rp_eligibility_require_tutor" value="1" class="rounded border-slate-300" <?= !empty($rpEligibility['require_tutor']) ? 'checked' : '' ?>> Tuteur obligatoire</label>
                </div>
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-bold text-white hover:bg-slate-800">Enregistrer la configuration roleplay</button>
            </form>
        </section>
        <?php endif; ?>

        <section>
            <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-4">Contenu, événements &amp; mesure</h2>
            <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
                <?php $card(url('back-office/categories'), 'Catégories forum', 'Organisation des espaces de discussion.'); ?>
                <?php $card(url('back-office/events'), 'Événements &amp; pointage', 'Séances, présences, rappels.'); ?>
                <?php $card(url('back-office/analytics'), 'Analytics', 'Indicateurs d’usage de la communauté.'); ?>
                <?php if ($canDocs): ?>
                <?php $card(url('documents/gestion'), 'Documents', 'Bibliothèque et dépôt selon vos droits.'); ?>
                <?php endif; ?>
                <?php if ($canTraining): ?>
                <?php $card(url('back-office/ressources/training'), 'Formations (LMS)', 'Parcours, inscriptions, certificats.'); ?>
                <?php endif; ?>
            </div>
        </section>

        <section>
            <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-4">Modération &amp; conformité</h2>
            <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
                <?php if ($gate->allows('admin.members.moderate')): ?>
                <?php $card(url('back-office/moderation'), 'Restrictions membres', 'Limitations d’activité dans la communauté (formations, documents, etc.).'); ?>
                <?php endif; ?>
                <?php $card(url('back-office/audit'), 'Journal d’activité', 'Traçabilité des actions administratives.'); ?>
                <?php if ($gate->allows('forum.moderate') || $gate->allows('forum.moderate_organization') || $gate->allows('admin.organization') || $gate->allows('admin.access')): ?>
                <?php $card(url('back-office/forum-moderation'), 'Modération forum', 'Files et outils modérateur forum.'); ?>
                <?php $card(url('admin/content-moderation'), 'Modération fichiers', 'Approbation des pièces jointes et médias.'); ?>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <!-- Détail technique existant -->
    <section class="mt-12">
        <h2 class="text-lg font-bold text-slate-900 mb-2">Données &amp; fiches personnel</h2>
        <p class="text-sm text-slate-600 mb-6">Aperçu des unités, grades, matricules et panneaux admin (inchangé, accès rapide).</p>

        <div class="space-y-8">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-bold text-slate-800">Unités / équipes / groupes</h3>
                <div class="flex gap-2">
                    <a href="<?= url('back-office/groups') ?>" class="px-3 py-1.5 bg-slate-100 text-slate-700 text-sm font-medium rounded-lg hover:bg-slate-200">Liste</a>
                    <a href="<?= url('back-office/groups/create') ?>" class="px-3 py-1.5 bg-slate-900 text-white text-sm font-medium rounded-lg hover:bg-slate-800">Nouvelle unité</a>
                </div>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
                <?php if (empty($units)): ?>
                <p class="p-6 text-slate-500">Aucune unité. <a href="<?= url('back-office/groups/create') ?>" class="underline font-medium">Créer une unité</a>.</p>
                <?php else: ?>
                <div class="overflow-x-auto">
                <table class="w-full min-w-[640px]">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Nom</th>
                            <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Type</th>
                            <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Code</th>
                            <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($units as $u): ?>
                        <tr class="border-b border-slate-100 hover:bg-slate-50">
                            <td class="p-3 font-medium"><?= htmlspecialchars($u['name']) ?></td>
                            <td class="p-3"><?= htmlspecialchars($u['type'] ?? '—') ?></td>
                            <td class="p-3"><?= htmlspecialchars($u['code'] ?? '—') ?></td>
                            <td class="p-3">
                                <a href="<?= url('back-office/groups/' . (int) $u['id'] . '/edit') ?>" class="text-slate-600 hover:text-slate-900 text-sm underline">Modifier</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php endif; ?>
            </div>

            <div class="grid gap-8 md:grid-cols-2">
                <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100">
                        <h3 class="text-base font-bold text-slate-900">Grades</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Rangs du tenant (voir référentiel pour éditer).</p>
                    </div>
                    <div class="p-6">
                        <?php if (empty($grades)): ?>
                        <p class="text-slate-500 text-sm">Aucun grade défini.</p>
                        <?php else: ?>
                        <ul class="space-y-2">
                            <?php foreach ($grades as $g): ?>
                            <?php
                            $gradeShortLabel = (string) ($g['label_short'] ?? $g['short_name'] ?? '');
                            $gradeNatoCode = trim((string) ($g['label_otan'] ?? $g['nato_code'] ?? ''));
                            ?>
                            <li class="flex justify-between items-center text-sm">
                                <span class="font-medium"><?= htmlspecialchars($g['label_long'] ?? $g['name'] ?? '') ?></span>
                                <span class="text-slate-500 text-right"><?= htmlspecialchars($gradeShortLabel, ENT_QUOTES, 'UTF-8') ?><?= $gradeNatoCode !== '' ? ' · ' . htmlspecialchars($gradeNatoCode, ENT_QUOTES, 'UTF-8') : '' ?></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100">
                        <h3 class="text-base font-bold text-slate-900">Configuration matricule</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Préfixe et format d’attribution.</p>
                    </div>
                    <div class="p-6">
                        <?php if ($matriculeConfig): ?>
                        <dl class="space-y-2 text-sm">
                            <div><dt class="text-slate-500 font-medium">Préfixe</dt><dd class="font-mono"><?= htmlspecialchars($matriculeConfig['prefix'] ?? '—') ?></dd></div>
                            <div><dt class="text-slate-500 font-medium">Format</dt><dd class="font-mono"><?= htmlspecialchars($matriculeConfig['format_pattern'] ?? '—') ?></dd></div>
                            <div><dt class="text-slate-500 font-medium">Prochain numéro</dt><dd><?= (int) ($matriculeConfig['next_number'] ?? 0) ?></dd></div>
                        </dl>
                        <p class="mt-4 text-xs text-slate-500">Utilisé lors de la génération depuis la fiche personnel.</p>
                        <?php else: ?>
                        <p class="text-slate-500 text-sm">Créée à la première génération de matricule.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div>
                <h3 class="text-base font-bold text-slate-800 mb-4">Panneaux administratifs (fiche personnel)</h3>
                <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
                    <?php if (empty($adminPanels)): ?>
                    <p class="p-6 text-slate-500 text-sm">Aucun panneau configuré.</p>
                    <?php else: ?>
                    <div class="overflow-x-auto">
                    <table class="w-full min-w-[560px]">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Nom</th>
                                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Référence</th>
                                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Ordre</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($adminPanels as $p): ?>
                            <tr class="border-b border-slate-100 hover:bg-slate-50">
                                <td class="p-3 font-medium"><?= htmlspecialchars($p['name']) ?></td>
                                <td class="p-3 font-mono text-sm"><?= htmlspecialchars($p['slug']) ?></td>
                                <td class="p-3"><?= (int) ($p['display_order'] ?? 0) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <p class="mt-10 text-sm text-slate-500 flex flex-wrap gap-x-3 gap-y-1">
        <a href="<?= url('back-office') ?>" class="underline hover:text-slate-800">Back-office</a>
        <span class="text-slate-300">·</span>
        <a href="<?= url('dashboard') ?>" class="underline hover:text-slate-800">Tableau de bord</a>
    </p>
</div>
</div>
