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
$boardPosture = $boardPosture ?? ['posture_level' => 'NORMAL'];
$boardSchemaReady = $boardSchemaReady ?? true;
$boardToday = $boardToday ?? date('Y-m-d');

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
$operationalLabels = [
    'planned' => 'Planifié',
    'in_progress' => 'En cours',
    'suspended' => 'Suspendu',
    'completed' => 'Terminé',
    'cancelled' => 'Annulé',
];
$phaseLabels = ['phase_1' => 'Phase 1', 'phase_2' => 'Phase 2', 'phase_3' => 'Phase 3'];
$priorityClass = [
    'critical' => 'border-l-rose-500 border-rose-200 bg-white text-slate-900',
    'high' => 'border-l-orange-400 border-orange-100 bg-white text-slate-900',
    'normal' => 'border-l-slate-400 border-slate-200 bg-white text-slate-900',
    'low' => 'border-l-slate-300 border-slate-100 bg-slate-50 text-slate-800',
];
$priorityShort = ['critical' => 'Critique', 'high' => 'Élevée', 'normal' => 'Normale', 'low' => 'Faible'];

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

$renderBoardCard = static function (array $entry) use ($priorityClass, $priorityShort, $operationalLabels, $phaseLabels, $entryTypeLabels): void {
    $showAdminActions = false;
    require __DIR__ . '/board_card.php';
};
?>
<div class="mx-auto max-w-[1700px] space-y-4 pb-8 px-4">
    <?php if (!$boardSchemaReady): ?>
        <div class="rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950 shadow-sm" role="alert">
            <p class="font-bold">Mur opérationnel indisponible</p>
            <p class="mt-1">Les données ne sont pas encore installées sur ce serveur.</p>
        </div>
    <?php endif; ?>
    <header class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm sm:px-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-emerald-700">Diffusion</p>
                <h1 class="mt-1 text-xl font-black tracking-tight text-slate-900 sm:text-2xl">Mur opérationnel</h1>
                <p class="mt-1 text-sm text-slate-600">Synthèse à destination des membres autorisés.</p>
            </div>
            <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-bold uppercase tracking-wide ring-1 <?= htmlspecialchars($postureUi['badge'], ENT_QUOTES, 'UTF-8') ?>">
                Posture <?= htmlspecialchars($postureUi['label'], ENT_QUOTES, 'UTF-8') ?>
            </span>
        </div>
        <p class="mt-3 text-sm text-slate-600">Période affichée : <?= htmlspecialchars((string) ($boardFilters['period_start'] ?? ''), ENT_QUOTES, 'UTF-8') ?> → <?= htmlspecialchars((string) ($boardFilters['period_end'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
    </header>

    <div class="space-y-3">
        <details class="rounded-2xl border border-slate-200 bg-white shadow-sm" open>
            <summary class="cursor-pointer list-none rounded-t-2xl border-b border-slate-200 bg-slate-50 px-4 py-3 text-xs font-bold uppercase tracking-wider text-slate-800">A. Permanences <span class="ml-1 rounded-full bg-white px-2 py-0.5 text-[10px]"><?= count($boardPanels['permanences']) ?></span></summary>
            <div class="p-3">
                <div class="mb-3 grid gap-2 md:grid-cols-3">
                    <?php foreach (['aujourdhui' => 'Aujourd’hui', 'en_cours' => 'En cours', 'a_venir' => 'À venir'] as $bk => $bl): ?>
                        <div class="rounded-xl border border-slate-100 bg-slate-50/80 p-2">
                            <p class="mb-2 text-[10px] font-black uppercase tracking-widest text-slate-500"><?= htmlspecialchars($bl, ENT_QUOTES, 'UTF-8') ?></p>
                            <div class="space-y-2">
                                <?php foreach ($boardPanels['permanences'] as $entry) {
                                    if ($temporalBucket($entry, $boardToday) !== $bk) {
                                        continue;
                                    }
                                    $renderBoardCard($entry);
                                } ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="space-y-2">
                    <?php foreach ($boardPanels['permanences'] as $entry) {
                        if (in_array($temporalBucket($entry, $boardToday), ['aujourdhui', 'en_cours', 'a_venir'], true)) {
                            continue;
                        }
                        $renderBoardCard($entry);
                    } ?>
                </div>
            </div>
        </details>
        <details class="rounded-2xl border border-slate-200 bg-white shadow-sm" open>
            <summary class="cursor-pointer list-none rounded-t-2xl border-b border-slate-200 bg-slate-50 px-4 py-3 text-xs font-bold uppercase tracking-wider text-slate-800">B. Infos pratiques <span class="ml-1 rounded-full bg-white px-2 py-0.5 text-[10px]"><?= count($boardPanels['infos']) ?></span></summary>
            <div class="space-y-2 p-3"><?php foreach ($boardPanels['infos'] as $entry) {
                $renderBoardCard($entry);
            } ?></div>
        </details>
        <details class="rounded-2xl border border-slate-200 bg-white shadow-sm" open>
            <summary class="cursor-pointer list-none rounded-t-2xl border-b border-slate-200 bg-slate-50 px-4 py-3 text-xs font-bold uppercase tracking-wider text-slate-800">C. Manifestations <span class="ml-1 rounded-full bg-white px-2 py-0.5 text-[10px]"><?= count($boardPanels['manifestations']) ?></span></summary>
            <div class="space-y-2 p-3"><?php foreach ($boardPanels['manifestations'] as $entry) {
                $renderBoardCard($entry);
            } ?></div>
        </details>
        <details class="rounded-2xl border border-slate-200 bg-white shadow-sm" open>
            <summary class="cursor-pointer list-none rounded-t-2xl border-b border-slate-200 bg-slate-50 px-4 py-3 text-xs font-bold uppercase tracking-wider text-slate-800">D. Missions et activités <span class="ml-1 rounded-full bg-white px-2 py-0.5 text-[10px]"><?= count($boardPanels['activites']) ?></span></summary>
            <div class="space-y-2 p-3"><?php foreach ($boardPanels['activites'] as $entry) {
                $renderBoardCard($entry);
            } ?></div>
        </details>
        <details class="rounded-2xl border border-amber-100 bg-amber-50/40 shadow-sm" open>
            <summary class="cursor-pointer list-none rounded-t-2xl border-b border-amber-200 bg-amber-100/60 px-4 py-3 text-xs font-bold uppercase tracking-wider text-amber-950">Flash infos <span class="ml-1 rounded-full bg-white px-2 py-0.5 text-[10px]"><?= count($boardPanels['flash']) ?></span></summary>
            <div class="space-y-3 p-4"><?php foreach ($boardPanels['flash'] as $entry) {
                $renderBoardCard($entry);
            } ?></div>
        </details>
    </div>
</div>
