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
?>
<div class="mx-auto max-w-[1700px] space-y-4 pb-8">
    <?php if (!$boardSchemaReady): ?>
        <header class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm sm:px-6">
            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-emerald-700">Pilotage</p>
            <h1 class="mt-1 text-xl font-black tracking-tight text-slate-900 sm:text-2xl">Tableau opérationnel</h1>
            <p class="mt-1 text-sm text-slate-600">Vue consolidée des permanences, informations et missions pour la période sélectionnée.</p>
        </header>
        <div class="rounded-2xl border border-amber-200 bg-gradient-to-b from-amber-50/40 to-white px-6 py-12 shadow-sm sm:px-10" role="status">
            <div class="mx-auto max-w-xl text-center">
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-amber-900">Activation en attente</p>
                <h2 class="mt-2 text-lg font-black tracking-tight text-slate-900 sm:text-xl">Ce module n’est pas encore disponible sur cet environnement</h2>
                <p class="mt-4 text-sm leading-relaxed text-slate-600">
                    Merci d’en informer la personne ou l’équipe qui administre l’hébergement du site : une étape d’installation prévue avec la version déployée doit encore être réalisée par cette équipe. Lorsque ce sera fait, actualisez cette page pour retrouver le tableau.
                </p>
                <button type="button" class="mt-8 inline-flex items-center justify-center rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2" onclick="location.reload()">
                    Actualiser la page
                </button>
            </div>
        </div>
    <?php else: ?>
    <header class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm sm:px-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-emerald-700">Pilotage</p>
                <h1 class="mt-1 text-xl font-black tracking-tight text-slate-900 sm:text-2xl">Tableau opérationnel</h1>
                <p class="mt-1 text-sm text-slate-600">Vue consolidée des permanences, informations et missions pour la période sélectionnée.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="<?= url('back-office/tableau-operationnel/fiche/nouvelle') ?>" class="rounded-lg bg-emerald-700 px-3 py-1.5 text-xs font-bold text-white shadow-sm hover:bg-emerald-800">Nouvelle entrée</a>
                <?php
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
                ?>
                <a href="<?= htmlspecialchars($draftListUrl, ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-bold text-amber-950 shadow-sm hover:bg-amber-100" title="Afficher uniquement les fiches non publiées sur le tableau">
                    Brouillons
                    <?php if ($boardDraftCount > 0): ?>
                        <span class="rounded-full bg-amber-200 px-1.5 py-0.5 text-[10px] font-black text-amber-950"><?= $boardDraftCount ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?= url('tableau-operationnel') ?>" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-bold text-slate-800 hover:bg-slate-100">Vue portail (lecture)</a>
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
                <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-bold uppercase tracking-wide ring-1 <?= htmlspecialchars($postureUi['badge'], ENT_QUOTES, 'UTF-8') ?>">
                    Posture <?= htmlspecialchars($postureUi['label'], ENT_QUOTES, 'UTF-8') ?>
                </span>
            </div>
        </div>
        <div class="mt-4 flex flex-wrap items-end justify-between gap-3 border-t border-slate-100 pt-4 text-sm text-slate-600">
            <div class="space-y-1">
                <p><span class="font-semibold text-slate-800">Communauté :</span> <?= htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8') ?></p>
                <p><span class="font-semibold text-slate-800">Période :</span> <?= htmlspecialchars((string) ($boardFilters['period_start'] ?? ''), ENT_QUOTES, 'UTF-8') ?> → <?= htmlspecialchars((string) ($boardFilters['period_end'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                <p><span class="font-semibold text-slate-800">Activités suivies :</span> <?= count($boardPanels['activites']) ?> · <span class="font-semibold text-slate-800">Priorité critique :</span> <?= count(array_filter($boardPanels['activites'], static fn ($e) => ($e['priority'] ?? '') === 'critical')) ?></p>
            </div>
            <form method="post" action="<?= url('back-office/tableau-operationnel/posture') ?>" class="flex flex-wrap items-center gap-2">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500" for="posture_level">Niveau de posture</label>
                <select id="posture_level" name="posture_level" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-900 shadow-sm">
                    <?php foreach (['NORMAL' => 'Normale', 'VIGILANCE' => 'Vigilance', 'ALERTE' => 'Alerte', 'CRISE' => 'Crise'] as $val => $lbl): ?>
                        <option value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>" <?= $posture === $val ? 'selected' : '' ?>><?= htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-emerald-800">Appliquer</button>
            </form>
        </div>
    </header>

    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div id="js-filters" class="grid grid-cols-2 gap-2 md:grid-cols-7">
            <select data-filter="entry_type" class="rounded-lg border border-slate-300 bg-white p-2 text-xs font-medium text-slate-800" aria-label="Filtrer par type">
                <option value="">Tous les types</option>
                <?php foreach ($entryTypeLabels as $k => $lbl): ?>
                    <option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
            <select data-filter="operational_status" class="rounded-lg border border-slate-300 bg-white p-2 text-xs font-medium text-slate-800" aria-label="Filtrer par statut opérationnel">
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
            <a href="<?= url('back-office/tableau-operationnel/stream') ?>" class="flex items-center justify-center rounded-lg border border-slate-300 bg-slate-900 p-2 text-center text-xs font-bold text-white hover:bg-slate-800" title="Flux des événements récents">Activité temps réel</a>
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
    </section>

    <?php
    $nouvelleFiche = url('back-office/tableau-operationnel/fiche/nouvelle');
    $debutJour = rawurlencode((string) $boardToday);
    $lienNouvelle = static function (string $type) use ($nouvelleFiche, $debutJour): string {
        return $nouvelleFiche . '/' . rawurlencode($type) . '?debut=' . $debutJour;
    };
    $pillClass = 'inline-flex items-center rounded-lg border border-emerald-200 bg-white px-3 py-1.5 text-xs font-bold text-emerald-900 shadow-sm hover:bg-emerald-50';
    ?>
    <section class="rounded-2xl border border-emerald-100 bg-gradient-to-b from-emerald-50/50 to-white p-4 shadow-sm" aria-labelledby="board-quick-create-heading">
        <h2 id="board-quick-create-heading" class="text-sm font-bold uppercase tracking-wider text-emerald-950">Création rapide</h2>
        <p class="mt-1 max-w-3xl text-xs leading-relaxed text-slate-600">Ouvre l’éditeur avec le type choisi et la date de début du jour. La fiche est créée en brouillon : complétez le texte, validez puis mettez en ligne quand c’est prêt.</p>
        <div class="mt-3 flex flex-wrap gap-2">
            <a href="<?= htmlspecialchars($lienNouvelle('flash_info'), ENT_QUOTES, 'UTF-8') ?>" class="<?= $pillClass ?>">Flash information</a>
            <a href="<?= htmlspecialchars($lienNouvelle('permanence'), ENT_QUOTES, 'UTF-8') ?>" class="<?= $pillClass ?>">Permanence</a>
            <a href="<?= htmlspecialchars($lienNouvelle('info'), ENT_QUOTES, 'UTF-8') ?>" class="<?= $pillClass ?>">Info pratique</a>
            <a href="<?= htmlspecialchars($lienNouvelle('manifestation'), ENT_QUOTES, 'UTF-8') ?>" class="<?= $pillClass ?>">Manifestation</a>
            <a href="<?= htmlspecialchars($lienNouvelle('mission'), ENT_QUOTES, 'UTF-8') ?>" class="<?= $pillClass ?>">Mission</a>
            <a href="<?= htmlspecialchars($lienNouvelle('task'), ENT_QUOTES, 'UTF-8') ?>" class="<?= $pillClass ?>">Tâche interne</a>
            <a href="<?= htmlspecialchars($lienNouvelle('formation'), ENT_QUOTES, 'UTF-8') ?>" class="<?= $pillClass ?>">Formation</a>
        </div>
        <p class="mt-3 text-[11px] text-slate-500">Pour repartir d’une entrée déjà rédigée, utilisez <span class="font-semibold text-slate-700">Copier en brouillon</span> sur la carte concernée. Les modèles enregistrés en bas de page génèrent aussi une fiche prête à compléter.</p>
    </section>

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

    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <h2 class="text-sm font-bold uppercase tracking-wider text-slate-800">Journal récent</h2>
        <div id="live-stream" class="mt-2 max-h-40 space-y-1 overflow-auto text-xs text-slate-700">
            <?php foreach ($boardLogs as $log): ?>
                <p class="border-b border-slate-100 py-1.5">
                    <?= htmlspecialchars((string) ($log['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                    · <?= htmlspecialchars((string) ($log['action_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                    · <?= htmlspecialchars((string) ($log['summary'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                    <span class="text-slate-400">(<?= htmlspecialchars((string) ($log['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>)</span>
                </p>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <h2 class="text-sm font-bold uppercase tracking-wider text-slate-800">Modèles et création guidée</h2>
        <p class="mt-2 max-w-3xl text-xs text-slate-600">Un <strong class="font-semibold text-slate-800">modèle</strong> enregistre un squelette (type d’activité, intitulé, consignes, visibilité de base) pour générer une <strong class="font-semibold text-slate-800">nouvelle fiche brouillon</strong> à chaque usage. La <strong class="font-semibold text-slate-800">famille</strong> sert à classer les missions types dans la liste (libellé interne à l’équipe pilotage).</p>
        <?php
        $familleModeleLabels = [
            'permanence_opj' => 'Permanence judiciaire',
            'mission_judiciaire' => 'Mission judiciaire',
            'instruction' => 'Instruction ou formation',
            'dispositif_securite' => 'Dispositif sécurité',
            'exercice' => 'Exercice',
            'custom' => 'Sur mesure',
        ];
        ?>
        <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-3">
            <form method="post" action="<?= url('back-office/tableau-operationnel/template') ?>" class="space-y-2 rounded-xl border border-slate-100 bg-slate-50 p-3 text-xs">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">À partir d’un modèle</p>
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
                    <p class="text-slate-500">Aucun modèle pour l’instant : créez-en un au centre ou ouvrez une fiche existante pour l’enregistrer comme modèle.</p>
                <?php endif; ?>
                <button type="submit" class="w-full rounded-lg bg-slate-900 py-2.5 text-sm font-bold text-white hover:bg-slate-800" <?= count($boardTemplates) === 0 ? 'disabled' : '' ?>>Générer une fiche brouillon</button>
            </form>

            <form method="post" action="<?= url('back-office/tableau-operationnel/modele') ?>" class="space-y-2 rounded-xl border border-emerald-100 bg-emerald-50/40 p-3 text-xs">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                <p class="text-[11px] font-bold uppercase tracking-wide text-emerald-800">Nouveau modèle (squelette vide)</p>
                <label class="block font-semibold text-slate-800" for="template_name">Nom du modèle (dans la liste)</label>
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

            <div class="rounded-xl border border-slate-100 bg-slate-50 p-4 text-sm text-slate-700">
                <p class="font-semibold text-slate-900">Sans modèle</p>
                <p class="mt-2">Ouvrez l’éditeur complet pour tout saisir à la main (affectations, moyens, consignes, rattachements).</p>
                <p class="mt-3 text-xs text-slate-600">Astuce : depuis une fiche déjà rédigée, utilisez <span class="font-semibold text-slate-800">Enregistrer comme modèle</span> pour conserver type, visibilité et textes types sans les dates.</p>
                <a href="<?= url('back-office/tableau-operationnel/fiche/nouvelle') ?>" class="mt-4 inline-flex rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-800">Créer une entrée libre</a>
            </div>
        </div>
    </section>
    <?php endif; ?>
</div>

<?php if ($boardSchemaReady): ?>
<script>
(() => {
    const filters = document.querySelectorAll('[data-filter]');
    const cards = document.querySelectorAll('.entry-card');
    const modeSwitch = document.getElementById('mode-switch');
    const resetBtn = document.getElementById('filter-reset');
    const live = document.getElementById('live-stream');
    const boardColumns = document.getElementById('board-columns');
    let sinceId = 0;

    function applyFilters() {
        const state = {};
        filters.forEach(el => { state[el.dataset.filter] = (el.value || '').toLowerCase(); });
        const mode = (modeSwitch?.value || 'standard');

        if (boardColumns) {
            boardColumns.classList.toggle('board-mode-briefing', mode === 'briefing');
        }

        cards.forEach(card => {
            const ok = Object.entries(state).every(([k, v]) => {
                if (!v) return true;
                if (k === 'tag') {
                    return (card.dataset.tag || '').includes(v);
                }
                return (card.dataset[k] || '').toLowerCase().includes(v);
            });

            const isCritical = (card.dataset.priority || '') === 'critical';
            const inProgress = (card.dataset.operational_status || '') === 'in_progress';
            const showInMode = mode !== 'crise' || isCritical || inProgress;
            card.style.display = ok && showInMode ? '' : 'none';
        });
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
.board-mode-briefing .entry-card .entry-card-actions { display: none !important; }
.board-mode-briefing .entry-card > h3 { margin-bottom: 0; }
</style>
<?php endif; ?>
