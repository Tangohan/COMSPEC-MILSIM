<?php
declare(strict_types=1);

$boardFilters = $boardFilters ?? [];
$boardPanels = array_merge([
    'permanences' => [],
    'infos' => [],
    'manifestations' => [],
    'flash' => [],
    'activites' => [],
], $boardPanels ?? []);
$boardToday = $boardToday ?? date('Y-m-d');
$boardCategories = $boardCategories ?? [];
$boardTemplates = $boardTemplates ?? [];
$boardPosture = $boardPosture ?? ['posture_level' => 'NORMAL'];
$boardLogs = $boardLogs ?? [];
$boardTags = $boardTags ?? [];
$boardQualificationAlerts = $boardQualificationAlerts ?? [];
$boardFireConflicts = $boardFireConflicts ?? [];
$boardSchemaReady = $boardSchemaReady ?? true;
$boardShareSchemaReady = $boardShareSchemaReady ?? false;
$boardPublicWallUrl = trim((string) ($boardPublicWallUrl ?? ''));
$boardDraftCount = (int) ($boardDraftCount ?? 0);

$posture = (string) ($boardPosture['posture_level'] ?? 'NORMAL');

$posturePresentation = [
    'NORMAL' => ['label' => 'Normale', 'badge' => 'border-emerald-300 bg-emerald-50 text-emerald-900 ring-emerald-200'],
    'VIGILANCE' => ['label' => 'Vigilance', 'badge' => 'border-amber-300 bg-amber-50 text-amber-900 ring-amber-200'],
    'ALERTE' => ['label' => 'Alerte', 'badge' => 'border-orange-300 bg-orange-50 text-orange-950 ring-orange-200'],
    'CRISE' => ['label' => 'Crise', 'badge' => 'border-rose-300 bg-rose-50 text-rose-950 ring-rose-200'],
];
$postureUi = $posturePresentation[$posture] ?? $posturePresentation['NORMAL'];

$entryTypeLabels = [
    'permanence' => 'Permanence',
    'info' => 'Information pratique',
    'manifestation' => 'Manifestation',
    'mission' => 'Mission',
    'task' => 'Tâche',
    'formation' => 'Formation',
    'flash_info' => 'Flash information',
    'flash_info_detailed' => 'Flash information détaillé',
];

$temporalBucket = static function (array $e, string $today): string {
    $op = (string) ($e['operational_status'] ?? 'planned');
    if ($op === 'in_progress') {
        return 'en_cours';
    }
    $start = isset($e['start_date']) && $e['start_date'] !== null && $e['start_date'] !== '' ? (string) $e['start_date'] : '';
    $end = isset($e['end_date']) && $e['end_date'] !== null && $e['end_date'] !== '' ? (string) $e['end_date'] : '';
    if ($start !== '' && $start > $today) {
        return 'a_venir';
    }
    if ($end !== '' && $end < $today) {
        return 'passe';
    }
    if ($start !== '' && $start <= $today && ($end === '' || $end >= $today)) {
        return 'aujourdhui';
    }

    return 'sans_date';
};

$operationalLabels = [
    'planned' => 'Planifié',
    'in_progress' => 'En cours',
    'suspended' => 'Suspendu',
    'completed' => 'Terminé',
    'cancelled' => 'Annulé',
];

$phaseLabels = [
    'phase_1' => 'Phase 1',
    'phase_2' => 'Phase 2',
    'phase_3' => 'Phase 3',
];

$priorityClass = [
    'critical' => 'border-l-rose-500 border-rose-200 bg-white text-slate-900',
    'high' => 'border-l-orange-400 border-orange-100 bg-white text-slate-900',
    'normal' => 'border-l-slate-400 border-slate-200 bg-white text-slate-900',
    'low' => 'border-l-slate-300 border-slate-100 bg-slate-50 text-slate-800',
];

$priorityShort = [
    'critical' => 'Critique',
    'high' => 'Élevée',
    'normal' => 'Normale',
    'low' => 'Faible',
];

$tenantName = trim((string) (\App\Core\Session::get('tenant_name') ?? ''));
if ($tenantName === '') {
    $tenantName = 'Votre communauté';
}
$boardEntryCount = (int) ($boardEntryCount ?? (
    count($boardPanels['permanences']) + count($boardPanels['infos']) + count($boardPanels['manifestations'])
    + count($boardPanels['flash']) + count($boardPanels['activites'])
));
$posturePill = match ($posture) {
    'VIGILANCE', 'ALERTE' => 'ops-board__pill--warn',
    'CRISE' => 'ops-board__pill--danger',
    default => 'ops-board__pill--ok',
};

$draftListUrl = url('back-office/tableau-operationnel') . '?' . http_build_query([
    'status' => 'draft',
    'period_start' => (string) ($boardFilters['period_start'] ?? ''),
    'period_end' => (string) ($boardFilters['period_end'] ?? ''),
    'entry_type' => (string) ($boardFilters['entry_type'] ?? ''),
    'operational_status' => (string) ($boardFilters['operational_status'] ?? ''),
    'tag' => (string) ($boardFilters['tag'] ?? ''),
    'mode' => (string) ($boardFilters['mode'] ?? 'standard'),
    'critical_only' => (int) ($boardFilters['critical_only'] ?? 0),
], '', '&', PHP_QUERY_RFC3986);

$nouvelleFiche = url('back-office/tableau-operationnel/fiche/nouvelle');
$debutJour = rawurlencode((string) $boardToday);
$lienNouvelle = static function (string $type) use ($nouvelleFiche, $debutJour): string {
    return $nouvelleFiche . '/' . rawurlencode($type) . '?debut=' . $debutJour;
};

$familleModeleLabels = [
    'permanence_opj' => 'Permanence judiciaire',
    'mission_judiciaire' => 'Mission judiciaire',
    'instruction' => 'Instruction ou formation',
    'dispositif_securite' => 'Dispositif sécurité',
    'exercice' => 'Exercice',
    'custom' => 'Sur mesure',
];
?>
<link href="<?= htmlspecialchars(asset_url('assets/css/operational-board.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
<div class="ops-board">
    <header class="ops-board__hero">
        <div class="ops-board__hero-inner">
            <div>
                <p class="ops-board__eyebrow">État-major · Pilotage</p>
                <h1 class="ops-board__title">Tableau opérationnel</h1>
                <p class="ops-board__lead">
                    Consultez d’abord le mur publié, puis créez une fiche ou un modèle si besoin.
                    Communauté : <?= htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8') ?>.
                </p>
            </div>
            <div>
                <div class="ops-board__hero-meta" style="margin-bottom:0.65rem;justify-content:flex-end">
                    <span class="ops-board__pill <?= htmlspecialchars($posturePill, ENT_QUOTES, 'UTF-8') ?>">Posture <?= htmlspecialchars($postureUi['label'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="ops-board__pill"><?= (int) $boardEntryCount ?> fiche<?= $boardEntryCount > 1 ? 's' : '' ?></span>
                </div>
                <div class="ops-board__actions" style="justify-content:flex-end">
                    <?php if ($boardSchemaReady): ?>
                        <a href="#ops-zone-view" class="ops-board__btn ops-board__btn--solid">Voir le tableau</a>
                        <?php if ($boardPublicWallUrl !== ''): ?>
                            <a href="<?= htmlspecialchars($boardPublicWallUrl, ENT_QUOTES, 'UTF-8') ?>" class="ops-board__btn ops-board__btn--solid" target="_blank" rel="noopener">Voir la page publiée</a>
                        <?php else: ?>
                            <a href="#ops-zone-publish" class="ops-board__btn ops-board__btn--solid">Lien public</a>
                        <?php endif; ?>
                        <a href="<?= htmlspecialchars($draftListUrl, ENT_QUOTES, 'UTF-8') ?>" class="ops-board__btn ops-board__btn--amber">
                            Brouillons<?= $boardDraftCount > 0 ? ' · ' . $boardDraftCount : '' ?>
                        </a>
                        <a href="<?= url('tableau-operationnel') ?>" class="ops-board__btn ops-board__btn--ghost">Vue membres</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <div class="ops-board__deck">
    <?php
    $boardHelpIsPilotage = true;
    require base_path('views/operations/partials/board_help.php');
    ?>
    <?php if (!$boardSchemaReady): ?>
        <div class="ops-board__empty" role="status">
            <p>Ce module n’est pas encore disponible sur cet environnement</p>
            <span>Informez l’équipe d’hébergement : une étape d’installation doit encore être réalisée. Actualisez ensuite cette page.</span>
            <div class="ops-board__actions" style="justify-content:center;margin-top:1rem">
                <button type="button" class="ops-board__btn ops-board__btn--solid" onclick="location.reload()">Actualiser la page</button>
            </div>
        </div>
    <?php else: ?>

    <nav class="ops-board__map" aria-label="Organisation de la page">
        <a href="#ops-zone-view" class="ops-board__map-item ops-board__map-item--primary">
            <span class="ops-board__map-step">1</span>
            <span>
                <strong>Consulter</strong>
                <em>Afficher et filtrer le tableau</em>
            </span>
        </a>
        <a href="#ops-zone-create" class="ops-board__map-item">
            <span class="ops-board__map-step">2</span>
            <span>
                <strong>Créer une fiche</strong>
                <em>Création rapide ou éditeur complet</em>
            </span>
        </a>
        <a href="#ops-zone-templates" class="ops-board__map-item">
            <span class="ops-board__map-step">3</span>
            <span>
                <strong>Modèles</strong>
                <em>Squelettes réutilisables</em>
            </span>
        </a>
        <a href="#ops-zone-publish" class="ops-board__map-item">
            <span class="ops-board__map-step">4</span>
            <span>
                <strong>Lien public</strong>
                <em>Partager le mur en lecture seule</em>
            </span>
        </a>
    </nav>

    <!-- ========== 1. CONSULTER ========== -->
    <section id="ops-zone-view" class="ops-board__zone" aria-labelledby="ops-zone-view-title">
        <header class="ops-board__zone-head">
            <p class="ops-board__zone-kicker">Étape 1 · Prioritaire</p>
            <h2 id="ops-zone-view-title">Consulter le tableau</h2>
            <p>Filtrez et parcourez les fiches déjà présentes. Les actions de création sont plus bas, pour ne pas mélanger lecture et rédaction.</p>
        </header>

        <div class="ops-board__toolbar">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h3 class="ops-board__toolbar-title">Période &amp; publication</h3>
                    <p><?= htmlspecialchars((string) ($boardFilters['period_start'] ?? ''), ENT_QUOTES, 'UTF-8') ?> → <?= htmlspecialchars((string) ($boardFilters['period_end'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <form method="get" action="<?= url('back-office/tableau-operationnel') ?>" class="flex flex-wrap items-center gap-2">
                        <input type="hidden" name="period_start" value="<?= htmlspecialchars((string) ($boardFilters['period_start'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="period_end" value="<?= htmlspecialchars((string) ($boardFilters['period_end'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="entry_type" value="<?= htmlspecialchars((string) ($boardFilters['entry_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="operational_status" value="<?= htmlspecialchars((string) ($boardFilters['operational_status'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="tag" value="<?= htmlspecialchars((string) ($boardFilters['tag'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="mode" value="<?= htmlspecialchars((string) ($boardFilters['mode'] ?? 'standard'), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="critical_only" value="<?= (int) ($boardFilters['critical_only'] ?? 0) ?>">
                        <label class="text-xs font-semibold text-slate-600" for="board_pub_status">Publication</label>
                        <select id="board_pub_status" name="status" class="rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-xs font-medium text-slate-900" onchange="this.form.submit()">
                            <?php
                            $pub = (string) ($boardFilters['status'] ?? 'active');
                            $pubOpts = [
                                'active' => 'Publiées (actives)',
                                'draft' => 'Brouillons',
                                'cancelled' => 'Retirées du mur',
                                'all' => 'Toutes',
                            ];
                            foreach ($pubOpts as $val => $lbl): ?>
                                <option value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>" <?= $pub === $val ? 'selected' : '' ?>><?= htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <form method="post" action="<?= url('back-office/tableau-operationnel/posture') ?>" class="flex flex-wrap items-center gap-2">
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                        <label class="text-xs font-semibold text-slate-600" for="posture_level">Posture</label>
                        <select id="posture_level" name="posture_level" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-900">
                            <?php foreach (['NORMAL' => 'Normale', 'VIGILANCE' => 'Vigilance', 'ALERTE' => 'Alerte', 'CRISE' => 'Crise'] as $val => $lbl): ?>
                                <option value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>" <?= $posture === $val ? 'selected' : '' ?>><?= htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="rounded-lg bg-emerald-700 px-3 py-1.5 text-xs font-bold text-white hover:bg-emerald-800">Appliquer</button>
                    </form>
                </div>
            </div>
        </div>

        <?php if ($boardEntryCount < 1 && (string) ($boardFilters['status'] ?? 'active') === 'active'): ?>
            <div class="ops-board__empty" role="status" style="margin-bottom:1rem">
                <p>Aucune fiche publiée sur cette période</p>
                <span>Créez une entrée dans l’étape « Créer une fiche », validez-la, puis mettez-la en ligne.</span>
                <div class="ops-board__actions" style="justify-content:center;margin-top:1rem">
                    <a href="#ops-zone-create" class="ops-board__btn ops-board__btn--solid">Aller à la création</a>
                    <a href="<?= htmlspecialchars($draftListUrl, ENT_QUOTES, 'UTF-8') ?>" class="ops-board__btn ops-board__btn--amber">Voir les brouillons</a>
                </div>
            </div>
        <?php endif; ?>

        <div class="ops-board__toolbar">
            <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h3 class="ops-board__toolbar-title">Affichage</h3>
                    <p>Affinez la vue : le compteur se met à jour automatiquement.</p>
                </div>
                <p id="filter-live-count" class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-700" aria-live="polite">
                    Affichage : —
                </p>
            </div>
            <div id="js-filters" class="ops-board__filters">
                <input type="search" data-filter="text" aria-label="Recherche texte" placeholder="Recherche : titre, description, responsables, zone…">
                <select data-filter="entry_type" aria-label="Filtrer par type">
                    <option value="">Tous les types</option>
                    <?php foreach ($entryTypeLabels as $k => $lbl): ?>
                        <option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
                <select data-filter="operational_status" aria-label="Filtrer par statut opérationnel">
                    <option value="">Tous les statuts</option>
                    <?php foreach ($operationalLabels as $k => $lbl): ?>
                        <option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
                <select data-filter="priority" class="rounded-lg border border-slate-300 bg-white p-2 text-xs font-medium text-slate-800" aria-label="Filtrer par priorité">
                    <option value="">Toutes les priorités</option>
                    <?php foreach ($priorityShort as $k => $lbl): ?>
                        <option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
                <select data-filter="tag" class="rounded-lg border border-slate-300 bg-white p-2 text-xs font-medium text-slate-800" aria-label="Filtrer par étiquette">
                    <option value="">Toutes les étiquettes</option>
                    <?php foreach ($boardTags as $tag): ?>
                        <option value="<?= htmlspecialchars((string) ($tag['tag'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($tag['tag'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="mode-switch" class="rounded-lg border border-slate-300 bg-white p-2 text-xs font-medium text-slate-800" aria-label="Mode d’affichage">
                    <option value="standard">Vue complète</option>
                    <option value="crise">Vue synthèse crise</option>
                    <option value="briefing">Vue briefing</option>
                </select>
                <button type="button" id="filter-reset" class="rounded-lg border border-slate-300 bg-slate-50 p-2 text-xs font-bold text-slate-800 hover:bg-slate-100">Réinitialiser</button>
                <a href="<?= url('back-office/tableau-operationnel/stream') ?>" class="flex items-center justify-center rounded-lg border border-slate-300 bg-slate-900 p-2 text-center text-xs font-bold text-white hover:bg-slate-800" title="Flux des événements récents">Activité récente</a>
            </div>
            <?php if (!empty($boardQualificationAlerts) || !empty($boardFireConflicts)): ?>
                <div class="mt-3 space-y-2 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-950">
                    <?php foreach ($boardQualificationAlerts as $a): ?>
                        <p class="flex gap-2">
                            <span class="shrink-0" aria-hidden="true">⚠</span>
                            <span>
                                Compétence requise absente ou expirée pour <strong><?= htmlspecialchars((string) ($a['person_label'] ?? 'un membre'), ENT_QUOTES, 'UTF-8') ?></strong>
                                sur la ligne « <?= htmlspecialchars((string) ($a['entry_title'] ?? 'mission'), ENT_QUOTES, 'UTF-8') ?> ».
                                Vérifiez les habilitations sur les fiches concernées.
                            </span>
                        </p>
                    <?php endforeach; ?>
                    <?php foreach ($boardFireConflicts as $c): ?>
                        <p class="flex gap-2">
                            <span class="shrink-0" aria-hidden="true">⚠</span>
                            <span>
                                Fenêtre d’action incohérente pour « <?= htmlspecialchars((string) ($c['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?> » : la fin précède le début. Corrigez les horaires dans la fiche.
                            </span>
                        </p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="mt-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-[11px] text-slate-600">
                <span class="font-semibold text-slate-700">Astuce :</span> <span class="font-semibold">Vue synthèse crise</span> masque tout sauf les lignes critiques ou déjà en cours. <span class="font-semibold">Vue briefing</span> réduit les cartes pour lecture rapide avant point de situation.
            </div>
        </div>

        <div id="board-columns" class="space-y-3">
            <?php
            $renderBoardCard = static function (array $entry) use ($priorityClass, $priorityShort, $operationalLabels, $phaseLabels, $entryTypeLabels): void {
                $showAdminActions = true;
                require __DIR__ . '/board_card.php';
            };
            ?>
            <details class="group rounded-2xl border border-slate-200 bg-white shadow-sm" open>
                <summary class="cursor-pointer list-none rounded-t-2xl border-b border-slate-200 bg-slate-50 px-4 py-3 text-xs font-bold uppercase tracking-wider text-slate-800">
                    <span class="inline-flex items-center gap-2">A. Permanences particulières <span class="rounded-full bg-white px-2 py-0.5 text-[10px] text-slate-600"><?= count($boardPanels['permanences']) ?></span></span>
                </summary>
                <div class="p-3" data-column="permanences">
                    <div class="mb-3 grid gap-2 md:grid-cols-3">
                        <?php
                        $buckets = ['aujourdhui' => 'Aujourd’hui', 'en_cours' => 'En cours', 'a_venir' => 'À venir'];
                        foreach ($buckets as $bk => $bl): ?>
                            <div class="rounded-xl border border-slate-100 bg-slate-50/80 p-2">
                                <p class="mb-2 text-[10px] font-black uppercase tracking-widest text-slate-500"><?= htmlspecialchars($bl, ENT_QUOTES, 'UTF-8') ?></p>
                                <div class="space-y-2 subcolumn" data-bucket="<?= htmlspecialchars($bk, ENT_QUOTES, 'UTF-8') ?>">
                                    <?php foreach ($boardPanels['permanences'] as $entry):
                                        if ($temporalBucket($entry, $boardToday) !== $bk) {
                                            continue;
                                        }
                                        $renderBoardCard($entry);
                                        endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="space-y-2" data-bucket-rest="permanences">
                        <?php foreach ($boardPanels['permanences'] as $entry):
                            if (in_array($temporalBucket($entry, $boardToday), ['aujourdhui', 'en_cours', 'a_venir'], true)) {
                                continue;
                            }
                            $renderBoardCard($entry);
                            endforeach; ?>
                    </div>
                </div>
            </details>

            <details class="group rounded-2xl border border-slate-200 bg-white shadow-sm" open>
                <summary class="cursor-pointer list-none rounded-t-2xl border-b border-slate-200 bg-slate-50 px-4 py-3 text-xs font-bold uppercase tracking-wider text-slate-800">
                    B. Infos pratiques <span class="rounded-full bg-white px-2 py-0.5 text-[10px] text-slate-600"><?= count($boardPanels['infos']) ?></span>
                </summary>
                <div class="space-y-2 p-3" data-column="infos">
                    <?php foreach ($boardPanels['infos'] as $entry) {
                        $renderBoardCard($entry);
                    } ?>
                </div>
            </details>

            <details class="group rounded-2xl border border-slate-200 bg-white shadow-sm" open>
                <summary class="cursor-pointer list-none rounded-t-2xl border-b border-slate-200 bg-slate-50 px-4 py-3 text-xs font-bold uppercase tracking-wider text-slate-800">
                    C. Manifestations <span class="rounded-full bg-white px-2 py-0.5 text-[10px] text-slate-600"><?= count($boardPanels['manifestations']) ?></span>
                </summary>
                <div class="space-y-2 p-3" data-column="manifestations">
                    <?php foreach ($boardPanels['manifestations'] as $entry) {
                        $renderBoardCard($entry);
                    } ?>
                </div>
            </details>

            <details class="group rounded-2xl border border-slate-200 bg-white shadow-sm" open>
                <summary class="cursor-pointer list-none rounded-t-2xl border-b border-slate-200 bg-slate-50 px-4 py-3 text-xs font-bold uppercase tracking-wider text-slate-800">
                    D. Missions et activités <span class="rounded-full bg-white px-2 py-0.5 text-[10px] text-slate-600"><?= count($boardPanels['activites']) ?></span>
                </summary>
                <div class="space-y-2 p-3" data-column="activites">
                    <?php foreach ($boardPanels['activites'] as $entry) {
                        $renderBoardCard($entry);
                    } ?>
                </div>
            </details>

            <details class="group rounded-2xl border border-amber-100 bg-amber-50/40 shadow-sm" open>
                <summary class="cursor-pointer list-none rounded-t-2xl border-b border-amber-200 bg-amber-100/60 px-4 py-3 text-xs font-bold uppercase tracking-wider text-amber-950">
                    Flash infos <span class="rounded-full bg-white px-2 py-0.5 text-[10px] text-amber-900"><?= count($boardPanels['flash']) ?></span>
                </summary>
                <div class="space-y-3 p-4" data-column="flash">
                    <?php foreach ($boardPanels['flash'] as $entry) {
                        $renderBoardCard($entry);
                    } ?>
                </div>
            </details>
        </div>

        <details class="ops-board__journal mt-4 rounded-2xl border border-slate-200 bg-white shadow-sm">
            <summary class="cursor-pointer list-none px-4 py-3 text-sm font-bold text-slate-800">Journal récent</summary>
            <div id="live-stream" class="max-h-40 space-y-1 overflow-auto border-t border-slate-100 px-4 py-3 text-xs text-slate-700">
                <?php foreach ($boardLogs as $log): ?>
                    <p class="border-b border-slate-100 py-1.5">
                        <?= htmlspecialchars((string) ($log['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        · <?= htmlspecialchars((string) ($log['action_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        · <?= htmlspecialchars((string) ($log['summary'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        <span class="text-slate-400">(<?= htmlspecialchars((string) ($log['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>)</span>
                    </p>
                <?php endforeach; ?>
            </div>
        </details>
    </section>

    <!-- ========== 2. CRÉER ========== -->
    <section id="ops-zone-create" class="ops-board__zone ops-board__zone--create" aria-labelledby="ops-zone-create-title">
        <header class="ops-board__zone-head">
            <p class="ops-board__zone-kicker">Étape 2 · Quand vous devez publier</p>
            <h2 id="ops-zone-create-title">Créer une fiche</h2>
            <p>Utilisez la <strong>création rapide</strong> pour démarrer un brouillon du jour avec le bon type. Pour une saisie complète (affectations, moyens, consignes), ouvrez l’éditeur.</p>
        </header>
        <div class="ops-board__quick">
            <p class="ops-board__quick-hint">Chaque bouton ouvre l’éditeur en brouillon, avec la date du jour. Complétez, validez, puis mettez en ligne.</p>
            <div class="ops-board__quick-grid">
                <a href="<?= htmlspecialchars($lienNouvelle('flash_info'), ENT_QUOTES, 'UTF-8') ?>">Flash information</a>
                <a href="<?= htmlspecialchars($lienNouvelle('flash_info_detailed'), ENT_QUOTES, 'UTF-8') ?>">Flash détaillé</a>
                <a href="<?= htmlspecialchars($lienNouvelle('permanence'), ENT_QUOTES, 'UTF-8') ?>">Permanence</a>
                <a href="<?= htmlspecialchars($lienNouvelle('info'), ENT_QUOTES, 'UTF-8') ?>">Info pratique</a>
                <a href="<?= htmlspecialchars($lienNouvelle('manifestation'), ENT_QUOTES, 'UTF-8') ?>">Manifestation</a>
                <a href="<?= htmlspecialchars($lienNouvelle('mission'), ENT_QUOTES, 'UTF-8') ?>">Mission</a>
                <a href="<?= htmlspecialchars($lienNouvelle('task'), ENT_QUOTES, 'UTF-8') ?>">Tâche interne</a>
                <a href="<?= htmlspecialchars($lienNouvelle('formation'), ENT_QUOTES, 'UTF-8') ?>">Formation</a>
            </div>
            <div class="ops-board__quick-footer">
                <a href="<?= url('back-office/tableau-operationnel/fiche/nouvelle') ?>" class="ops-board__btn ops-board__btn--solid" style="background:#064e3b;color:#ecfdf5;border-color:#064e3b">Ouvrir l’éditeur complet</a>
                <p>Astuce : sur une fiche existante, utilisez <strong>Copier en brouillon</strong>. Les modèles (étape suivante) génèrent aussi un brouillon prêt à compléter.</p>
            </div>
        </div>
    </section>

    <!-- ========== 3. MODÈLES ========== -->
    <section id="ops-zone-templates" class="ops-board__zone ops-board__zone--templates" aria-labelledby="ops-zone-templates-title">
        <header class="ops-board__zone-head">
            <p class="ops-board__zone-kicker">Étape 3 · Préparation</p>
            <h2 id="ops-zone-templates-title">Modèles</h2>
            <p>Un modèle est un <strong>squelette réutilisable</strong> (type, intitulé, consignes). Il ne s’affiche pas sur le mur : il sert uniquement à générer une nouvelle fiche brouillon.</p>
        </header>
        <div class="ops-board__templates-grid">
            <form method="post" action="<?= url('back-office/tableau-operationnel/template') ?>" class="ops-board__tpl-card">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                <p class="ops-board__tpl-label">Utiliser un modèle</p>
                <label class="block font-semibold text-slate-800" for="template_id">Choisir un modèle</label>
                <select id="template_id" name="template_id" class="w-full rounded-lg border border-slate-300 bg-white p-2 text-sm">
                    <?php foreach ($boardTemplates as $tpl): ?>
                        <?php
                        $tt = (string) ($tpl['template_type'] ?? 'custom');
                        $fam = $familleModeleLabels[$tt] ?? 'Sur mesure';
                        ?>
                        <option value="<?= (int) ($tpl['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($tpl['name'] ?? 'Modèle'), ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($fam, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (count($boardTemplates) === 0): ?>
                    <p class="text-slate-500">Aucun modèle pour l’instant : créez-en un à droite, ou enregistrez une fiche existante comme modèle.</p>
                <?php endif; ?>
                <button type="submit" class="w-full rounded-lg bg-slate-900 py-2.5 text-sm font-bold text-white hover:bg-slate-800" <?= count($boardTemplates) === 0 ? 'disabled' : '' ?>>Générer une fiche brouillon</button>
            </form>

            <form method="post" action="<?= url('back-office/tableau-operationnel/modele') ?>" class="ops-board__tpl-card ops-board__tpl-card--new">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                <p class="ops-board__tpl-label">Enregistrer un nouveau modèle</p>
                <label class="block font-semibold text-slate-800" for="template_name">Nom du modèle</label>
                <input id="template_name" name="template_name" required maxlength="160" class="w-full rounded-lg border border-slate-300 bg-white p-2 text-sm" autocomplete="off" placeholder="Ex. Permanence cellule recrutement">
                <label class="block font-semibold text-slate-800" for="mission_family">Famille / type métier</label>
                <select id="mission_family" name="mission_family" class="w-full rounded-lg border border-slate-300 bg-white p-2 text-sm">
                    <?php foreach ($familleModeleLabels as $val => $lib): ?>
                        <option value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($lib, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="block font-semibold text-slate-800" for="tpl_entry_type">Type d’entrée sur le tableau</label>
                <select id="tpl_entry_type" name="entry_type" class="w-full rounded-lg border border-slate-300 bg-white p-2 text-sm">
                    <?php foreach ($entryTypeLabels as $k => $lbl): ?>
                        <option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="block font-semibold text-slate-800" for="default_title">Intitulé proposé sur la fiche</label>
                <input id="default_title" name="default_title" maxlength="180" class="w-full rounded-lg border border-slate-300 bg-white p-2 text-sm" autocomplete="off" placeholder="Laisser vide pour reprendre le nom du modèle">
                <label class="block font-semibold text-slate-800" for="default_description">Consigne ou description type</label>
                <textarea id="default_description" name="default_description" rows="3" class="w-full rounded-lg border border-slate-300 bg-white p-2 text-sm" placeholder="Texte réutilisable ; vous l’affinerez sur chaque fiche générée."></textarea>
                <button type="submit" class="w-full rounded-lg bg-emerald-700 py-2.5 text-sm font-bold text-white hover:bg-emerald-800">Enregistrer le modèle</button>
            </form>
        </div>
    </section>

    <!-- ========== 4. LIEN PUBLIC ========== -->
    <section id="ops-zone-publish" class="ops-board__zone ops-board__zone--publish" aria-labelledby="ops-zone-publish-title">
        <header class="ops-board__zone-head">
            <p class="ops-board__zone-kicker">Partage</p>
            <h2 id="ops-zone-publish-title">Lien public du mur</h2>
            <p>Ouvre une page <strong>lecture seule</strong> soignée, sans boutons d’édition. Idéal pour diffuser la situation à des personnes hors du back-office.</p>
        </header>
        <?php if (!$boardShareSchemaReady): ?>
            <div class="ops-board__notice" role="status">
                Le lien public n’est pas encore activé sur cet environnement. Demandez une mise à jour technique, puis revenez ici.
            </div>
        <?php elseif ($boardPublicWallUrl === ''): ?>
            <form method="post" action="<?= url('back-office/tableau-operationnel/lien-public') ?>" class="ops-board__publish-box">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                <p>Aucun lien public pour le moment. Créez-en un pour partager le mur publié (fiches actives de la période courante).</p>
                <button type="submit" class="ops-board__btn ops-board__btn--solid" style="background:#064e3b;color:#ecfdf5;border-color:#064e3b">Créer le lien public</button>
            </form>
        <?php else: ?>
            <div class="ops-board__publish-box">
                <label class="block text-xs font-bold uppercase tracking-wide text-slate-500" for="ops-public-url">Adresse de la page publiée</label>
                <div class="mt-2 flex flex-wrap gap-2">
                    <input id="ops-public-url" type="text" readonly class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800" value="<?= htmlspecialchars($boardPublicWallUrl, ENT_QUOTES, 'UTF-8') ?>" onclick="this.select();">
                    <button type="button" class="ops-board__btn ops-board__btn--solid" style="background:#064e3b;color:#ecfdf5;border-color:#064e3b" onclick="navigator.clipboard.writeText(document.getElementById('ops-public-url').value); this.textContent='Copié'; setTimeout(()=>this.textContent='Copier le lien',1600);">Copier le lien</button>
                    <a href="<?= htmlspecialchars($boardPublicWallUrl, ENT_QUOTES, 'UTF-8') ?>" class="ops-board__btn ops-board__btn--solid" target="_blank" rel="noopener">Ouvrir la page publiée</a>
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <form method="post" action="<?= url('back-office/tableau-operationnel/lien-public/renouveler') ?>" onsubmit="return confirm('Renouveler le lien ? L’ancien cessera de fonctionner.');">
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" class="ops-board__btn ops-board__btn--amber">Renouveler le lien</button>
                    </form>
                    <form method="post" action="<?= url('back-office/tableau-operationnel/lien-public/desactiver') ?>" onsubmit="return confirm('Désactiver le lien public ?');">
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" class="ops-board__btn ops-board__btn--ghost" style="color:#334155;border-color:#cbd5e1;background:#fff">Désactiver</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <?php endif; ?>
    </div><!-- .ops-board__deck -->
</div><!-- .ops-board -->

<?php if ($boardSchemaReady): ?>
<script>
(() => {
    const filters = document.querySelectorAll('[data-filter]');
    const cards = document.querySelectorAll('.entry-card');
    const modeSwitch = document.getElementById('mode-switch');
    const resetBtn = document.getElementById('filter-reset');
    const live = document.getElementById('live-stream');
    const liveCount = document.getElementById('filter-live-count');
    const boardColumns = document.getElementById('board-columns');
    let sinceId = 0;

    function applyFilters() {
        const state = {};
        filters.forEach(el => { state[el.dataset.filter] = (el.value || '').toLowerCase(); });
        const mode = (modeSwitch?.value || 'standard');
        let visibleCount = 0;
        let hiddenCount = 0;

        if (boardColumns) {
            boardColumns.classList.toggle('board-mode-briefing', mode === 'briefing');
        }

        cards.forEach(card => {
            const ok = Object.entries(state).every(([k, v]) => {
                if (!v) return true;
                if (k === 'text') {
                    return (card.dataset.search || '').includes(v);
                }
                if (k === 'tag') {
                    return (card.dataset.tag || '').includes(v);
                }
                return (card.dataset[k] || '').toLowerCase().includes(v);
            });

            const isCritical = (card.dataset.priority || '') === 'critical';
            const inProgress = (card.dataset.operational_status || '') === 'in_progress';
            const showInMode = mode !== 'crise' || isCritical || inProgress;
            const visible = ok && showInMode;
            card.style.display = visible ? '' : 'none';
            if (visible) {
                visibleCount += 1;
            } else {
                hiddenCount += 1;
            }
        });

        if (liveCount) {
            liveCount.textContent = 'Affichage : ' + visibleCount + ' visible(s) · ' + hiddenCount + ' masquée(s)';
        }
    }

    filters.forEach(el => el.addEventListener('change', applyFilters));
    modeSwitch?.addEventListener('change', applyFilters);
    resetBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        filters.forEach(el => { el.value = ''; });
        if (modeSwitch) modeSwitch.value = 'standard';
        applyFilters();
    });
    applyFilters();

    async function poll() {
        try {
            const res = await fetch('<?= url('back-office/tableau-operationnel/stream') ?>?since_id=' + sinceId, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const json = await res.json();
            if (!json || !Array.isArray(json.events)) return;
            json.events.forEach(ev => {
                sinceId = Math.max(sinceId, Number(ev.id || 0));
                const row = document.createElement('p');
                row.className = 'border-b border-slate-100 py-1.5';
                row.textContent = (ev.event_type || 'Événement') + ' · ' + (ev.created_at || '');
                live?.prepend(row);
            });
        } catch (e) {}
        setTimeout(poll, 8000);
    }
    poll();
})();
</script>
<style>
.board-mode-briefing .entry-card > p,
.board-mode-briefing .entry-card > .entry-card__body,
.board-mode-briefing .entry-card > .entry-card__meta,
.board-mode-briefing .entry-card .entry-card-actions { display: none !important; }
.board-mode-briefing .entry-card > h3,
.board-mode-briefing .entry-card .entry-card__title { margin-bottom: 0; }
.board-mode-briefing .entry-card .entry-card-draft-open { display: block !important; }
</style>
<?php endif; ?>
