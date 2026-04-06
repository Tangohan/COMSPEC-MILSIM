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
                    <dt class="text-slate-500">Slug URL</dt>
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
                <?php $card(url('back-office/recruitments/messages-prefaits'), 'Messages préfaits (recrutement)', 'Modèles de commentaires internes pour traiter les candidatures.', 'border-emerald-200/80 hover:border-emerald-400 hover:bg-emerald-50/40'); ?>
            </div>
        </section>

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
                <?php $card(url('back-office/moderation'), 'Modération', 'Sanctions et suivi des membres.'); ?>
                <?php $card(url('back-office/audit'), 'Journal d’activité', 'Traçabilité des actions administratives.'); ?>
                <?php if ($gate->allows('forum.moderate') || $gate->allows('forum.moderate_organization') || $gate->allows('admin.organization') || $gate->allows('admin.access')): ?>
                <?php $card(url('back-office/forum-moderation'), 'Modération forum', 'Files et outils modérateur forum.'); ?>
                <?php $card(url('back-office/content-moderation'), 'Modération fichiers', 'Approbation des pièces jointes et médias.'); ?>
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
                <table class="w-full">
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
                            <li class="flex justify-between items-center text-sm">
                                <span class="font-medium"><?= htmlspecialchars($g['label_long'] ?? $g['name'] ?? '') ?></span>
                                <span class="text-slate-500"><?= htmlspecialchars($g['label_short'] ?? $g['short_name'] ?? '') ?><?= !empty($g['label_otan'] ?? $g['nato_code']) ? ' · ' . htmlspecialchars($g['label_otan'] ?? $g['nato_code']) : '' ?></span>
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
                    <table class="w-full">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Nom</th>
                                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Slug</th>
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
