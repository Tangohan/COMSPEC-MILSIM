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
        <div class="rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950 shadow-sm" role="alert">
            <p class="font-bold">Tableau opérationnel : mise à jour de base requise</p>
            <p class="mt-1">Les données de ce module ne sont pas encore installées sur ce serveur. Demandez à l’équipe qui gère l’hébergement d’exécuter la procédure d’initialisation de la base (incluant les migrations prévues dans le dépôt), puis rechargez cette page.</p>
        </div>
    <?php endif; ?>
    <header class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm sm:px-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-emerald-700">Pilotage</p>
                <h1 class="mt-1 text-xl font-black tracking-tight text-slate-900 sm:text-2xl">Tableau opérationnel</h1>
                <p class="mt-1 text-sm text-slate-600">Vue consolidée des permanences, informations et missions pour la période sélectionnée.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="<?= url('tableau-operationnel') ?>" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-bold text-slate-800 hover:bg-slate-100">Vue portail (lecture)</a>
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
        <h2 class="text-sm font-bold uppercase tracking-wider text-slate-800">Création et modèles</h2>
        <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-3">
            <form method="post" action="<?= url('back-office/tableau-operationnel/template') ?>" class="space-y-2 rounded-xl border border-slate-100 bg-slate-50 p-3 text-xs">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                <label class="block font-semibold text-slate-800" for="template_id">Partir d’un modèle enregistré</label>
                <select id="template_id" name="template_id" class="w-full rounded-lg border border-slate-300 bg-white p-2 text-sm">
                    <?php foreach ($boardTemplates as $tpl): ?>
                        <option value="<?= (int) ($tpl['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($tpl['name'] ?? 'Modèle'), ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (count($boardTemplates) === 0): ?>
                    <p class="text-slate-500">Aucun modèle disponible pour l’instant.</p>
                <?php endif; ?>
                <button type="submit" class="w-full rounded-lg bg-slate-900 py-2.5 text-sm font-bold text-white hover:bg-slate-800" <?= count($boardTemplates) === 0 ? 'disabled' : '' ?>>Créer depuis le modèle</button>
            </form>

            <div class="xl:col-span-2 rounded-xl border border-slate-100 bg-slate-50 p-4 text-sm text-slate-700">
                <p class="font-semibold text-slate-900">Nouvelle entrée</p>
                <p class="mt-2">Ouvrez l’éditeur complet pour saisir les affectations, moyens, consignes et rattachements.</p>
                <a href="<?= url('back-office/tableau-operationnel/fiche/nouvelle') ?>" class="mt-4 inline-flex rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-800">Créer une entrée libre</a>
            </div>
        </div>
    </section>
</div>

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
