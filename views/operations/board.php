<?php
$boardFilters = $boardFilters ?? [];
$boardPanels = $boardPanels ?? ['permanences' => [], 'infos' => [], 'activites' => []];
$boardCategories = $boardCategories ?? [];
$boardTemplates = $boardTemplates ?? [];
$boardPosture = $boardPosture ?? ['posture_level' => 'NORMAL'];
$boardLogs = $boardLogs ?? [];
$boardTags = $boardTags ?? [];
$boardQualificationAlerts = $boardQualificationAlerts ?? [];
$boardFireConflicts = $boardFireConflicts ?? [];

$posture = (string) ($boardPosture['posture_level'] ?? 'NORMAL');
$postureAccent = ['NORMAL' => 'emerald', 'VIGILANCE' => 'amber', 'ALERTE' => 'orange', 'CRISE' => 'rose'][$posture] ?? 'slate';
$priorityClass = ['critical' => 'border-rose-500 text-rose-700', 'high' => 'border-orange-400 text-orange-700', 'normal' => 'border-slate-300 text-slate-700', 'low' => 'border-slate-200 text-slate-500'];
?>
<div class="bg-slate-950 min-h-screen text-slate-100">
    <header class="sticky top-0 z-30 border-b border-slate-800 bg-slate-950/95 backdrop-blur">
        <div class="max-w-[1700px] mx-auto px-4 py-3 flex flex-wrap items-center justify-between gap-3 uppercase tracking-wider text-xs">
            <div class="flex items-center gap-2">
                <span class="font-black text-sm">Tableau Opérationnel</span>
                <span class="px-2 py-1 border border-<?= $postureAccent ?>-400 text-<?= $postureAccent ?>-300 font-bold">POSTURE <?= htmlspecialchars($posture) ?></span>
            </div>
            <div class="flex items-center gap-4">
                <span>Unité active: <?= htmlspecialchars((string) (
App\Core\Session::get('tenant_name') ?? 'Tenant')) ?></span>
                <span>Plage: <?= htmlspecialchars((string) ($boardFilters['period_start'] ?? '')) ?> → <?= htmlspecialchars((string) ($boardFilters['period_end'] ?? '')) ?></span>
                <span>Missions actives: <?= count($boardPanels['activites']) ?></span>
                <span>Alertes critiques: <?= count(array_filter($boardPanels['activites'], static fn($e) => ($e['priority'] ?? '') === 'critical')) ?></span>
            </div>
            <form method="post" action="<?= url('back-office/tableau-operationnel/posture') ?>" class="flex items-center gap-2">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                <select name="posture_level" class="bg-slate-900 border border-slate-700 text-xs px-2 py-1">
                    <?php foreach (['NORMAL', 'VIGILANCE', 'ALERTE', 'CRISE'] as $level): ?><option value="<?= $level ?>" <?= $posture === $level ? 'selected' : '' ?>><?= $level ?></option><?php endforeach; ?>
                </select>
                <button class="bg-slate-100 text-slate-900 px-2 py-1 font-bold">APPLIQUER</button>
            </form>
        </div>
    </header>

    <main class="max-w-[1700px] mx-auto px-4 py-4 space-y-4">
        <section class="border border-slate-800 bg-slate-900 p-3">
            <div id="js-filters" class="grid grid-cols-2 md:grid-cols-7 gap-2 text-xs uppercase">
                <select data-filter="entry_type" class="bg-slate-950 border border-slate-700 p-2"><option value="">Type</option><option value="permanence">Permanence</option><option value="info">Info</option><option value="mission">Mission</option><option value="formation">Formation</option><option value="task">Task</option></select>
                <select data-filter="operational_status" class="bg-slate-950 border border-slate-700 p-2"><option value="">Statut</option><option value="planned">Planifié</option><option value="in_progress">En cours</option><option value="suspended">Suspendu</option><option value="completed">Terminé</option><option value="cancelled">Annulé</option></select>
                <select data-filter="priority" class="bg-slate-950 border border-slate-700 p-2"><option value="">Priorité</option><option value="critical">Critique</option><option value="high">Élevée</option><option value="normal">Normale</option><option value="low">Faible</option></select>
                <select data-filter="tag" class="bg-slate-950 border border-slate-700 p-2"><option value="">Tag</option><?php foreach ($boardTags as $tag): ?><option value="<?= htmlspecialchars((string) ($tag['tag'] ?? '')) ?>"><?= htmlspecialchars((string) ($tag['tag'] ?? '')) ?></option><?php endforeach; ?></select>
                <select id="mode-switch" class="bg-slate-950 border border-slate-700 p-2"><option value="standard">Mode normal</option><option value="crise">Mode crise</option><option value="briefing">Mode brief</option></select>
                <button id="filter-reset" class="bg-slate-100 text-slate-900 font-bold">RESET</button>
                <a href="<?= url('back-office/tableau-operationnel/stream') ?>" class="bg-slate-800 text-center p-2">Flux live</a>
            </div>
            <?php if (!empty($boardQualificationAlerts) || !empty($boardFireConflicts)): ?>
                <div class="mt-2 text-[11px] text-rose-300 space-y-1">
                    <?php foreach ($boardQualificationAlerts as $a): ?><p>⚠ Habilitation manquante — Entry #<?= (int) ($a['planning_entry_id'] ?? 0) ?> / user #<?= (int) ($a['user_id'] ?? 0) ?> / <?= htmlspecialchars((string) ($a['skill_code'] ?? '')) ?></p><?php endforeach; ?>
                    <?php foreach ($boardFireConflicts as $c): ?><p>⚠ Contrainte fenêtre de tir — <?= htmlspecialchars((string) ($c['title'] ?? '')) ?> (start > end)</p><?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="grid grid-cols-1 lg:grid-cols-3 gap-3" id="board-columns">
            <?php foreach (['permanences' => 'PERMANENCES', 'infos' => 'INFORMATIONS', 'activites' => 'MISSIONS / ACTIVITÉS'] as $key => $title): ?>
                <div class="border border-slate-800 bg-slate-900 min-h-[420px]">
                    <div class="px-3 py-2 border-b border-slate-800 text-xs font-bold uppercase tracking-wider"><?= $title ?></div>
                    <div class="p-2 space-y-2" data-column="<?= $key ?>">
                        <?php foreach ($boardPanels[$key] as $entry):
                            $priority = (string) ($entry['priority'] ?? 'normal');
                            $tags = array_filter(array_map('trim', explode(',', (string) ($entry['tags_list'] ?? ''))));
                            ?>
                            <article class="entry-card border-l-4 bg-slate-950 p-2 text-xs <?= $priorityClass[$priority] ?? $priorityClass['normal'] ?>"
                                     data-entry_type="<?= htmlspecialchars((string) ($entry['entry_type'] ?? '')) ?>"
                                     data-operational_status="<?= htmlspecialchars((string) ($entry['operational_status'] ?? '')) ?>"
                                     data-priority="<?= htmlspecialchars($priority) ?>"
                                     data-tag="<?= htmlspecialchars((string) ($entry['tags_list'] ?? '')) ?>">
                                <div class="flex items-start justify-between gap-2">
                                    <h3 class="font-bold uppercase tracking-wide"><?= htmlspecialchars((string) ($entry['title'] ?? '')) ?></h3>
                                    <span><?= htmlspecialchars(strtoupper($priority)) ?></span>
                                </div>
                                <p class="text-slate-300">Chef: <?= htmlspecialchars((string) ($entry['chief_name'] ?? 'N/A')) ?> · Adj: <?= htmlspecialchars((string) ($entry['deputy_name'] ?? 'N/A')) ?> · Rempl: <?= htmlspecialchars((string) ($entry['replacement_name'] ?? 'N/A')) ?></p>
                                <p class="text-slate-400">Statut: <?= htmlspecialchars((string) ($entry['operational_status'] ?? 'planned')) ?> · Phase: <?= htmlspecialchars((string) ($entry['phase_current'] ?? 'phase_1')) ?></p>
                                <p class="text-slate-400">Checklist: <?= (int) ($entry['checklist_done'] ?? 0) ?>/<?= (int) ($entry['checklist_required'] ?? 0) ?> · Dossier: <?= htmlspecialchars((string) ($entry['dossier_ref'] ?? '-')) ?></p>
                                <p class="text-slate-400">Zone: <?= htmlspecialchars((string) ($entry['operation_zone'] ?? '-')) ?> <?php if (!empty($entry['map_link'])): ?>· <a class="underline" href="<?= htmlspecialchars((string) $entry['map_link']) ?>" target="_blank" rel="noopener">Carte</a><?php endif; ?></p>
                                <?php if ($tags): ?><p class="mt-1 text-[10px] uppercase tracking-wide text-slate-500">Tags: <?= htmlspecialchars(implode(' · ', $tags)) ?></p><?php endif; ?>
                                <div class="mt-2 flex flex-wrap gap-1">
                                    <form method="post" action="<?= url('back-office/tableau-operationnel/' . (int) ($entry['id'] ?? 0) . '/frago') ?>"><input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>"><button class="bg-slate-700 px-2 py-1">FRAGO</button></form>
                                    <form method="post" action="<?= url('back-office/tableau-operationnel/' . (int) ($entry['id'] ?? 0) . '/status') ?>" class="flex"><input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>"><input type="hidden" name="operational_status" value="completed"><button class="bg-emerald-700 px-2 py-1">Clôturer</button></form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>

        <section class="border border-slate-800 bg-slate-900 p-3">
            <h2 class="text-xs font-bold uppercase tracking-wider mb-2">Timeline / Flux global</h2>
            <div id="live-stream" class="space-y-1 text-[11px] max-h-40 overflow-auto">
                <?php foreach ($boardLogs as $log): ?><p class="border-b border-slate-800 py-1">#<?= (int) ($log['planning_entry_id'] ?? 0) ?> · <?= htmlspecialchars((string) ($log['action_type'] ?? '')) ?> · <?= htmlspecialchars((string) ($log['summary'] ?? '')) ?> <span class="text-slate-500">(<?= htmlspecialchars((string) ($log['created_at'] ?? '')) ?>)</span></p><?php endforeach; ?>
            </div>
        </section>

        <section class="border border-slate-800 bg-slate-900 p-3">
            <h2 class="text-xs font-bold uppercase tracking-wider mb-2">Panneau latéral (édition rapide)</h2>
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-3">
                <form method="post" action="<?= url('back-office/tableau-operationnel/template') ?>" class="space-y-2 text-xs">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                    <select name="template_id" class="w-full bg-slate-950 border border-slate-700 p-2"><?php foreach ($boardTemplates as $tpl): ?><option value="<?= (int) ($tpl['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($tpl['name'] ?? 'Template')) ?></option><?php endforeach; ?></select>
                    <button class="w-full bg-slate-100 text-slate-900 p-2 font-bold uppercase">Créer depuis template</button>
                </form>

                <form method="post" action="<?= url('back-office/tableau-operationnel') ?>" class="xl:col-span-2 grid grid-cols-2 md:grid-cols-4 gap-2 text-xs">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                    <input name="title" required placeholder="Titre" class="bg-slate-950 border border-slate-700 p-2">
                    <select name="entry_type" class="bg-slate-950 border border-slate-700 p-2"><option value="permanence">Permanence</option><option value="info">Info</option><option value="mission">Mission</option><option value="task">Task</option><option value="formation">Formation</option></select>
                    <input name="dossier_ref" placeholder="Réf dossier" class="bg-slate-950 border border-slate-700 p-2">
                    <input name="operation_zone" placeholder="Zone" class="bg-slate-950 border border-slate-700 p-2">
                    <input name="description" placeholder="Consigne" class="bg-slate-950 border border-slate-700 p-2 md:col-span-2">
                    <input name="legal_constraints" placeholder="Contraintes légales/techniques" class="bg-slate-950 border border-slate-700 p-2 md:col-span-2">
                    <input type="datetime-local" name="fire_window_start" class="bg-slate-950 border border-slate-700 p-2">
                    <input type="datetime-local" name="fire_window_end" class="bg-slate-950 border border-slate-700 p-2">
                    <input type="number" name="chief_user_id" placeholder="Chef user_id" class="bg-slate-950 border border-slate-700 p-2">
                    <input type="number" name="replacement_user_id" placeholder="Remplaçant user_id" class="bg-slate-950 border border-slate-700 p-2">
                    <label class="flex items-center gap-2 p-2 border border-slate-700"><input type="checkbox" name="replacement_auto_activate" value="1">Auto-remplacement</label>
                    <select name="phase_current" class="bg-slate-950 border border-slate-700 p-2"><option value="phase_1">Phase 1</option><option value="phase_2">Phase 2</option><option value="phase_3">Phase 3</option></select>
                    <button class="bg-emerald-600 text-white p-2 font-bold uppercase md:col-span-2">Créer entrée</button>
                </form>
            </div>
        </section>
    </main>
</div>

<script>
(() => {
    const filters = document.querySelectorAll('[data-filter]');
    const cards = document.querySelectorAll('.entry-card');
    const modeSwitch = document.getElementById('mode-switch');
    const resetBtn = document.getElementById('filter-reset');
    const live = document.getElementById('live-stream');
    let sinceId = 0;

    function applyFilters() {
        const state = {};
        filters.forEach(el => state[el.dataset.filter] = (el.value || '').toLowerCase());
        const mode = (modeSwitch?.value || 'standard');

        cards.forEach(card => {
            const ok = Object.entries(state).every(([k, v]) => {
                if (!v) return true;
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
        filters.forEach(el => el.value = '');
        if (modeSwitch) modeSwitch.value = 'standard';
        applyFilters();
    });
    applyFilters();

    async function poll() {
        try {
            const res = await fetch('<?= url('back-office/tableau-operationnel/stream') ?>?since_id=' + sinceId, {headers: {'X-Requested-With':'XMLHttpRequest'}});
            const json = await res.json();
            if (!json || !Array.isArray(json.events)) return;
            json.events.forEach(ev => {
                sinceId = Math.max(sinceId, Number(ev.id || 0));
                const row = document.createElement('p');
                row.className = 'border-b border-slate-800 py-1';
                row.textContent = '#' + (ev.id || 0) + ' · ' + (ev.event_type || 'event') + ' · ' + (ev.created_at || '');
                live?.prepend(row);
            });
        } catch (e) {}
        setTimeout(poll, 8000);
    }
    poll();
})();
</script>
