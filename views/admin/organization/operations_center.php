<?php

declare(strict_types=1);

$profile = (string) ($operationsProfile ?? 'commandement');
$profiles = $operationsProfiles ?? ['commandement', 'rh', 'moderation', 'formation'];
$moderationOpen = (int) ($operationsModerationOpen ?? 0);
$pendingRecruitments = $operationsPendingRecruitments ?? [];
$pendingRecruitmentsError = $operationsPendingRecruitmentsError ?? null;
$eventsJ1 = $operationsEventsJ1 ?? [];
$eventsJ7 = $operationsEventsJ7 ?? [];
$eventsError = $operationsEventsError ?? null;
$activeAlerts = $operationsActiveAlerts ?? [];
$alertsError = $operationsAlertsError ?? null;
$anomalies = $operationsOnboardingAnomalies ?? [];
$opsByType = is_array($operationsOpsBoardItemsByType ?? null) ? $operationsOpsBoardItemsByType : [];
$opsFilters = is_array($operationsOpsBoardFilters ?? null) ? $operationsOpsBoardFilters : [];
$opsError = $operationsOpsBoardError ?? null;
$actionableAlerts = is_array($operationsActionableAlerts ?? null) ? $operationsActionableAlerts : [];
$playbookCatalog = is_array($operationsPlaybookCatalog ?? null) ? $operationsPlaybookCatalog : [];
$auditScenarios = is_array($operationsAuditScenarios ?? null) ? $operationsAuditScenarios : [];
$weeklyGoals = is_array($operationsWeeklyGoals ?? null) ? $operationsWeeklyGoals : [];
$kpiSnapshot = is_array($operationsKpiSnapshot ?? null) ? $operationsKpiSnapshot : [];

$profileLabels = [
    'commandement' => 'Commandement',
    'rh' => 'RH',
    'moderation' => 'Modération',
    'formation' => 'Formation',
];

$priorityClasses = [
    'critical' => 'border-rose-300 bg-rose-50 text-rose-900',
    'high' => 'border-amber-300 bg-amber-50 text-amber-900',
    'normal' => 'border-slate-200 bg-white text-slate-900',
    'low' => 'border-blue-200 bg-blue-50 text-blue-900',
];

$priorityLabels = [
    'critical' => 'Critique',
    'high' => 'Élevée',
    'normal' => 'Normale',
    'low' => 'Informationnelle',
];

$formatDate = static function (?string $raw, string $format = 'd/m/Y H:i'): string {
    if ($raw === null || trim($raw) === '') {
        return '—';
    }
    $ts = strtotime($raw);

    return $ts ? date($format, $ts) : (string) $raw;
};

$formatRange = static function (?string $from, ?string $to) use ($formatDate): string {
    if (($from === null || trim($from) === '') && ($to === null || trim($to) === '')) {
        return '—';
    }

    return $formatDate($from, 'd/m/Y') . ' → ' . $formatDate($to, 'd/m/Y');
};

$escape = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$renderPriorityBadge = static function (array $item) use ($priorityClasses, $priorityLabels, $escape): string {
    $prio = (string) ($item['priority'] ?? 'normal');
    $cls = $priorityClasses[$prio] ?? $priorityClasses['normal'];
    $lbl = $priorityLabels[$prio] ?? ucfirst($prio);

    return '<span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide ' . $cls . '">' . $escape($lbl) . '</span>';
};
?>

<div class="mx-auto max-w-[1400px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8 space-y-6">
    <header class="rounded-2xl border border-slate-200 bg-white px-6 py-5 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-[11px] uppercase tracking-[0.2em] font-bold text-emerald-700">Control Tower</p>
                <h1 class="text-2xl font-black text-slate-900 mt-1">Centre des opérations</h1>
                <p class="text-sm text-slate-600 mt-2">Socle unique fusionnant tableau de pilotage (missions, activités, affectations) et mur d’information opérationnelle.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="<?= url('back-office/tableau-operationnel') ?>" class="inline-flex items-center rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-900 hover:bg-emerald-100">Tableau opérationnel</a>
                <a href="<?= url('back-office') ?>" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Retour tableau de bord</a>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
            <?php foreach ($profiles as $p): ?>
                <?php $active = $profile === $p; ?>
                <a href="<?= htmlspecialchars(url('back-office/centre-operations') . '?profile=' . urlencode((string) $p), ENT_QUOTES, 'UTF-8') ?>"
                   class="rounded-lg px-3 py-2 text-xs font-bold uppercase tracking-wide <?= $active ? 'bg-emerald-700 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' ?>">
                    <?= htmlspecialchars($profileLabels[(string) $p] ?? (string) $p, ENT_QUOTES, 'UTF-8') ?>
                </a>
            <?php endforeach; ?>
        </div>
    </header>

    <section class="rounded-2xl border border-indigo-200 bg-gradient-to-br from-indigo-50 via-white to-white p-5 shadow-sm space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-[11px] uppercase tracking-[0.2em] font-bold text-indigo-700">Ops Admin</p>
                <h2 class="text-xl font-black text-slate-900 mt-1">Centre d’opérations admin — Priorisation quotidienne</h2>
                <p class="text-sm text-slate-600 mt-1">File unique des alertes actionnables, playbooks incidents, audit orienté scénario et objectifs hebdomadaires.</p>
            </div>
            <a href="<?= url('back-office/audit') ?>" class="inline-flex items-center rounded-lg border border-indigo-200 bg-white px-3 py-2 text-xs font-semibold text-indigo-800 hover:bg-indigo-100">Ouvrir le journal d’audit</a>
        </div>

        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <?php foreach ($kpiSnapshot as $kpi): ?>
                <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500"><?= $escape((string) ($kpi['label'] ?? 'KPI')) ?></p>
                    <p class="mt-2 text-2xl font-black text-slate-900"><?= $escape((string) ($kpi['value'] ?? 'N/D')) ?></p>
                    <p class="mt-1 text-xs text-slate-500"><?= $escape((string) ($kpi['trend'] ?? '—')) ?></p>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="grid gap-5 xl:grid-cols-2">
            <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="text-sm font-black uppercase tracking-[0.14em] text-slate-900">File unique des alertes actionnables</h3>
                <p class="mt-1 text-xs text-slate-500">Tri décroissant par score d’impact.</p>
                <ul class="mt-3 space-y-2">
                    <?php foreach ($actionableAlerts as $alert): ?>
                        <?php
                        $impact = (int) ($alert['impact_score'] ?? 0);
                        $impactClass = $impact >= 80
                            ? 'text-rose-700 bg-rose-50 border-rose-200'
                            : ($impact >= 60 ? 'text-amber-700 bg-amber-50 border-amber-200' : 'text-emerald-700 bg-emerald-50 border-emerald-200');
                        ?>
                        <li class="rounded-lg border border-slate-200 p-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-sm font-semibold text-slate-900"><?= $escape((string) ($alert['title'] ?? 'Alerte')) ?></p>
                                <span class="inline-flex rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide <?= $impactClass ?>">
                                    Impact <?= $impact ?>
                                </span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">
                                <?= $escape((string) ($alert['type'] ?? 'Ops')) ?> • <?= $escape((string) ($alert['sla_label'] ?? 'SLA: —')) ?> • <?= (int) ($alert['count'] ?? 0) ?> éléments
                            </p>
                            <a href="<?= $escape((string) ($alert['link'] ?? '#')) ?>" class="mt-2 inline-flex text-xs font-semibold text-indigo-700 hover:underline"><?= $escape((string) ($alert['cta'] ?? 'Ouvrir')) ?> →</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </article>

            <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="text-sm font-black uppercase tracking-[0.14em] text-slate-900">Playbooks guidés incidents</h3>
                <p class="mt-1 text-xs text-slate-500">Activation rapide des procédures standards.</p>
                <div class="mt-3 space-y-2">
                    <?php foreach ($playbookCatalog as $playbook): ?>
                        <details class="rounded-lg border border-slate-200 bg-slate-50/60 p-3">
                            <summary class="cursor-pointer text-sm font-semibold text-slate-900"><?= $escape((string) ($playbook['title'] ?? 'Playbook')) ?> <span class="text-xs text-slate-500">(résolus: <?= (int) ($playbook['resolved_count'] ?? 0) ?>)</span></summary>
                            <p class="mt-2 text-xs text-slate-600"><?= $escape((string) ($playbook['summary'] ?? '')) ?></p>
                            <?php if (!empty($playbook['steps']) && is_array($playbook['steps'])): ?>
                                <ol class="mt-2 list-decimal pl-5 text-xs text-slate-600 space-y-1">
                                    <?php foreach ($playbook['steps'] as $step): ?>
                                        <li><?= $escape((string) $step) ?></li>
                                    <?php endforeach; ?>
                                </ol>
                            <?php endif; ?>
                        </details>
                    <?php endforeach; ?>
                </div>
            </article>
        </div>

        <div class="grid gap-5 xl:grid-cols-2">
            <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="text-sm font-black uppercase tracking-[0.14em] text-slate-900">Journal d’audit par scénario</h3>
                <ul class="mt-3 space-y-2">
                    <?php foreach ($auditScenarios as $scenario): ?>
                        <li class="rounded-lg border border-slate-200 px-3 py-2">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-sm font-semibold text-slate-900"><?= $escape((string) ($scenario['label'] ?? 'Scénario')) ?></p>
                                <span class="text-xs font-bold text-slate-700"><?= (int) ($scenario['count'] ?? 0) ?> événements</span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500"><?= $escape((string) ($scenario['description'] ?? '')) ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </article>

            <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="text-sm font-black uppercase tracking-[0.14em] text-slate-900">Objectifs hebdomadaires (KPI)</h3>
                <ul class="mt-3 space-y-2">
                    <?php foreach ($weeklyGoals as $goal): ?>
                        <?php
                        $state = (string) ($goal['state'] ?? 'en cours');
                        $stateClass = $state === 'atteint'
                            ? 'text-emerald-700 bg-emerald-50 border-emerald-200'
                            : ($state === 'à risque' ? 'text-rose-700 bg-rose-50 border-rose-200' : 'text-amber-700 bg-amber-50 border-amber-200');
                        ?>
                        <li class="rounded-lg border border-slate-200 px-3 py-2">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-sm font-semibold text-slate-900"><?= $escape((string) ($goal['title'] ?? 'Objectif')) ?></p>
                                <span class="inline-flex rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide <?= $stateClass ?>"><?= $escape($state) ?></span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500"><?= $escape((string) ($goal['kpi'] ?? 'KPI')) ?>: <?= $escape((string) ($goal['value'] ?? '—')) ?> • Variation <?= $escape((string) ($goal['variation'] ?? '—')) ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </article>
        </div>
    </section>

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        <article class="rounded-xl border border-rose-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-rose-700">Signalements forum</p>
            <p class="mt-2 text-3xl font-black text-slate-900"><?= $moderationOpen ?></p>
            <p class="mt-1 text-[11px] text-slate-500">Dossiers signalés en attente dans cette communauté.</p>
            <a class="mt-3 inline-flex text-sm font-semibold text-rose-700 hover:underline" href="<?= url('back-office/forum-moderation') ?>">Ouvrir la console forum →</a>
            <?php if (\App\Core\Gate::getInstance()->allows('admin.members.moderate')): ?>
                <a class="mt-2 block text-xs font-semibold text-slate-600 hover:underline" href="<?= url('back-office/moderation') ?>">Restrictions membres (organisation)</a>
            <?php endif; ?>
        </article>

        <article class="rounded-xl border border-blue-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Candidatures en attente</p>
            <p class="mt-2 text-3xl font-black text-slate-900"><?= count($pendingRecruitments) ?></p>
            <a class="mt-3 inline-flex text-sm font-semibold text-blue-700 hover:underline" href="<?= url('back-office/recruitments') ?>">Instruire →</a>
        </article>

        <article class="rounded-xl border border-amber-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Événements J+1</p>
            <p class="mt-2 text-3xl font-black text-slate-900"><?= count($eventsJ1) ?></p>
            <a class="mt-3 inline-flex text-sm font-semibold text-amber-700 hover:underline" href="<?= url('back-office/events') ?>">Préparer →</a>
        </article>

        <article class="rounded-xl border border-violet-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-violet-700">Événements J+7</p>
            <p class="mt-2 text-3xl font-black text-slate-900"><?= count($eventsJ7) ?></p>
            <a class="mt-3 inline-flex text-sm font-semibold text-violet-700 hover:underline" href="<?= url('back-office/events') ?>">Planifier →</a>
        </article>

        <article class="rounded-xl border border-emerald-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Alertes locales actives</p>
            <p class="mt-2 text-3xl font-black text-slate-900"><?= count($activeAlerts) ?></p>
            <a class="mt-3 inline-flex text-sm font-semibold text-emerald-700 hover:underline" href="<?= url('back-office/alerts') ?>">Escalader →</a>
        </article>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-sm font-black uppercase tracking-[0.16em] text-slate-900">Mur opérationnel (diffusion structurée)</h2>
                <p class="mt-1 text-xs text-slate-500">Filtres globaux multi-tenant : unité, période, type, visibilité, priorité.</p>
            </div>
            <a href="<?= url('back-office/events') ?>" class="text-xs font-semibold text-slate-600 hover:text-slate-900 hover:underline">Lier un item à un event →</a>
        </div>

        <form method="get" action="<?= htmlspecialchars(url('back-office/centre-operations'), ENT_QUOTES, 'UTF-8') ?>" class="grid gap-3 md:grid-cols-3 xl:grid-cols-6">
            <input type="hidden" name="profile" value="<?= $escape($profile) ?>">
            <input type="text" name="unit" value="<?= $escape((string) ($opsFilters['unit_id'] ?? '')) ?>" placeholder="Unité (id)" class="rounded-lg border border-slate-300 px-3 py-2 text-xs">
            <input type="date" name="from" value="<?= $escape((string) ($opsFilters['period_start'] ?? '')) ?>" class="rounded-lg border border-slate-300 px-3 py-2 text-xs">
            <input type="date" name="to" value="<?= $escape((string) ($opsFilters['period_end'] ?? '')) ?>" class="rounded-lg border border-slate-300 px-3 py-2 text-xs">
            <select name="type" class="rounded-lg border border-slate-300 px-3 py-2 text-xs">
                <option value="">Type (tous)</option>
                <?php foreach (['permanence_speciale' => 'Permanence', 'info_pratique' => 'Info pratique', 'manifestation' => 'Manifestation', 'flash_info' => 'Flash info'] as $value => $label): ?>
                    <option value="<?= $value ?>" <?= (($opsFilters['block_type'] ?? '') === $value) ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="visibility" value="<?= $escape((string) ($opsFilters['visibility_level'] ?? '')) ?>" placeholder="Visibilité (tenant/unit/role...)" class="rounded-lg border border-slate-300 px-3 py-2 text-xs">
            <select name="priority" class="rounded-lg border border-slate-300 px-3 py-2 text-xs">
                <option value="">Priorité (toutes)</option>
                <?php foreach ($priorityLabels as $value => $label): ?>
                    <option value="<?= $value ?>" <?= (($opsFilters['priority'] ?? '') === $value) ? 'selected' : '' ?>><?= $escape($label) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="md:col-span-3 xl:col-span-6 flex gap-2">
                <button type="submit" class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-bold text-white">Filtrer</button>
                <a href="<?= htmlspecialchars(url('back-office/centre-operations') . '?profile=' . urlencode($profile), ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700">Réinitialiser</a>
            </div>
        </form>

        <?php if ($opsError): ?>
            <p class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700"><?= $escape((string) $opsError) ?></p>
        <?php endif; ?>

        <details open class="rounded-xl border border-slate-200 bg-slate-50/40 p-3">
            <summary class="cursor-pointer text-sm font-bold text-slate-900">A. Permanences particulières (<?= count($opsByType['permanence_speciale'] ?? []) ?>)</summary>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-xs">
                    <thead class="text-left text-slate-500 uppercase tracking-wide"><tr><th class="p-2">Type</th><th class="p-2">Personnels</th><th class="p-2">Validité</th><th class="p-2">Visibilité</th></tr></thead>
                    <tbody>
                        <?php foreach (($opsByType['permanence_speciale'] ?? []) as $item): ?>
                            <tr class="border-t border-slate-100">
                                <td class="p-2 font-semibold text-slate-800"><?= $escape((string) ($item['title'] ?? 'Permanence')) ?> <?= !empty($item['is_pinned']) ? '📌' : '' ?> <?= $renderPriorityBadge($item) ?></td>
                                <td class="p-2 text-slate-600"><?= $escape((string) ($item['assignment_summary'] ?? '—')) ?></td>
                                <td class="p-2 text-slate-600"><?= $escape($formatRange((string) ($item['start_date'] ?? ''), (string) ($item['end_date'] ?? ''))) ?></td>
                                <td class="p-2 text-slate-600"><?= $escape((string) ($item['visibility_level'] ?? 'tenant')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (($opsByType['permanence_speciale'] ?? []) === []): ?><tr><td colspan="4" class="p-2 text-slate-500">Aucune permanence spéciale publiée.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </details>

        <details open class="rounded-xl border border-slate-200 bg-slate-50/40 p-3">
            <summary class="cursor-pointer text-sm font-bold text-slate-900">B. Infos pratiques (<?= count($opsByType['info_pratique'] ?? []) ?>)</summary>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-xs">
                    <thead class="text-left text-slate-500 uppercase tracking-wide"><tr><th class="p-2">Visibilité</th><th class="p-2">Début</th><th class="p-2">Fin</th><th class="p-2">Libellé</th></tr></thead>
                    <tbody>
                        <?php foreach (($opsByType['info_pratique'] ?? []) as $item): ?>
                            <tr class="border-t border-slate-100">
                                <td class="p-2 text-slate-700"><?= $escape((string) ($item['visibility_level'] ?? 'tenant')) ?></td>
                                <td class="p-2 text-slate-600"><?= $escape($formatDate((string) ($item['start_date'] ?? ''), 'd/m/Y')) ?></td>
                                <td class="p-2 text-slate-600"><?= $escape($formatDate((string) ($item['end_date'] ?? ''), 'd/m/Y')) ?></td>
                                <td class="p-2 font-semibold text-slate-800"><?= !empty($item['is_pinned']) ? '📌 ' : '' ?><?= $escape((string) ($item['title'] ?? 'Info')) ?> <?= $renderPriorityBadge($item) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (($opsByType['info_pratique'] ?? []) === []): ?><tr><td colspan="4" class="p-2 text-slate-500">Aucune info pratique publiée.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </details>

        <details open class="rounded-xl border border-slate-200 bg-slate-50/40 p-3">
            <summary class="cursor-pointer text-sm font-bold text-slate-900">C. Manifestations particulières (<?= count($opsByType['manifestation'] ?? []) ?>)</summary>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-xs">
                    <thead class="text-left text-slate-500 uppercase tracking-wide"><tr><th class="p-2">Titre</th><th class="p-2">Début</th><th class="p-2">Fin</th><th class="p-2">Visibilité</th></tr></thead>
                    <tbody>
                        <?php foreach (($opsByType['manifestation'] ?? []) as $item): ?>
                            <tr class="border-t border-slate-100">
                                <td class="p-2 font-semibold text-slate-800"><?= $escape((string) ($item['title'] ?? 'Manifestation')) ?> <?= $renderPriorityBadge($item) ?></td>
                                <td class="p-2 text-slate-600"><?= $escape($formatDate((string) ($item['start_date'] ?? ''), 'd/m/Y')) ?></td>
                                <td class="p-2 text-slate-600"><?= $escape($formatDate((string) ($item['end_date'] ?? ''), 'd/m/Y')) ?></td>
                                <td class="p-2 text-slate-700"><?= $escape((string) ($item['visibility_level'] ?? 'tenant')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (($opsByType['manifestation'] ?? []) === []): ?><tr><td colspan="4" class="p-2 text-slate-500">Aucune manifestation particulière publiée.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </details>

        <details open class="rounded-xl border border-slate-200 bg-slate-50/40 p-3">
            <summary class="cursor-pointer text-sm font-bold text-slate-900">D. Flash infos (<?= count($opsByType['flash_info'] ?? []) ?>)</summary>
            <div class="mt-3 grid gap-3 md:grid-cols-2">
                <?php foreach (($opsByType['flash_info'] ?? []) as $item): ?>
                    <?php
                    $prio = (string) ($item['priority'] ?? 'normal');
                    $cardClass = $priorityClasses[$prio] ?? $priorityClasses['normal'];
                    ?>
                    <article class="rounded-xl border p-4 shadow-sm <?= $cardClass ?>">
                        <p class="text-[10px] uppercase tracking-[0.18em] font-bold">Flash info <?= !empty($item['is_pinned']) ? '• Épinglé' : '' ?></p>
                        <h3 class="mt-1 text-base font-black"><?= $escape((string) ($item['title'] ?? 'Annonce')) ?></h3>
                        <?php if (!empty($item['summary'])): ?>
                            <p class="mt-2 text-sm leading-relaxed"><?= nl2br($escape((string) $item['summary'])) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($item['content'])): ?>
                            <div class="mt-2 rounded-lg bg-white/70 p-3 text-xs leading-relaxed prose prose-sm max-w-none"><?= nl2br($escape((string) $item['content'])) ?></div>
                        <?php endif; ?>
                        <p class="mt-2 text-[11px] opacity-80">Affichage : <?= $escape($formatRange((string) ($item['start_date'] ?? ''), (string) ($item['end_date'] ?? ''))) ?> • Cible <?= $escape((string) ($item['visibility_level'] ?? 'tenant')) ?></p>
                    </article>
                <?php endforeach; ?>
                <?php if (($opsByType['flash_info'] ?? []) === []): ?><p class="text-sm text-slate-500">Aucun flash info actif.</p><?php endif; ?>
            </div>
        </details>
    </section>

    <section class="grid gap-6 xl:grid-cols-3">
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-bold text-slate-900">Candidatures à traiter</h2>
            <?php if ($pendingRecruitmentsError): ?>
                <p class="mt-3 text-sm text-rose-600"><?= htmlspecialchars((string) $pendingRecruitmentsError, ENT_QUOTES, 'UTF-8') ?></p>
            <?php elseif ($pendingRecruitments === []): ?>
                <p class="mt-3 text-sm text-slate-500">Aucune candidature en attente.</p>
            <?php else: ?>
                <ul class="mt-3 space-y-2 text-sm">
                    <?php foreach ($pendingRecruitments as $row): ?>
                        <li class="rounded-lg border border-slate-100 px-3 py-2">
                            <a class="font-semibold text-blue-700 hover:underline" href="<?= url('back-office/recruitments/' . (int) ($row['id'] ?? 0) . '?dossier=1') ?>">
                                <?= htmlspecialchars((string) ($row['display_name'] ?? $row['email'] ?? 'Dossier'), ENT_QUOTES, 'UTF-8') ?>
                            </a>
                            <p class="text-xs text-slate-500">Soumis le <?= htmlspecialchars($formatDate((string) ($row['created_at'] ?? '')), ENT_QUOTES, 'UTF-8') ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </article>

        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-bold text-slate-900">Événements imminents</h2>
            <?php if ($eventsError): ?>
                <p class="mt-3 text-sm text-rose-600"><?= htmlspecialchars((string) $eventsError, ENT_QUOTES, 'UTF-8') ?></p>
            <?php elseif ($eventsJ7 === []): ?>
                <p class="mt-3 text-sm text-slate-500">Aucun événement sur les 7 prochains jours.</p>
            <?php else: ?>
                <ul class="mt-3 space-y-2 text-sm">
                    <?php foreach (array_slice($eventsJ7, 0, 6) as $event): ?>
                        <li class="rounded-lg border border-slate-100 px-3 py-2">
                            <a class="font-semibold text-violet-700 hover:underline" href="<?= url('back-office/events/' . (int) ($event['id'] ?? 0)) ?>"><?= htmlspecialchars((string) ($event['title'] ?? 'Événement'), ENT_QUOTES, 'UTF-8') ?></a>
                            <p class="text-xs text-slate-500"><?= htmlspecialchars($formatDate((string) ($event['starts_at'] ?? '')), ENT_QUOTES, 'UTF-8') ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </article>

        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-bold text-slate-900">Anomalies onboarding / configuration</h2>
            <ul class="mt-3 space-y-2 text-sm text-slate-700">
                <li class="flex items-center justify-between rounded-lg border border-slate-100 px-3 py-2"><span>Profils incomplets</span><strong><?= (int) ($anomalies['profils_incomplets'] ?? 0) ?></strong></li>
                <li class="flex items-center justify-between rounded-lg border border-slate-100 px-3 py-2"><span>Membres sans unité</span><strong><?= (int) ($anomalies['membres_sans_unite'] ?? 0) ?></strong></li>
                <li class="flex items-center justify-between rounded-lg border border-slate-100 px-3 py-2"><span>Membres sans rôle</span><strong><?= (int) ($anomalies['membres_sans_role'] ?? 0) ?></strong></li>
                <li class="flex items-center justify-between rounded-lg border border-slate-100 px-3 py-2"><span>Invitations expirées</span><strong><?= (int) ($anomalies['invitations_expirees'] ?? 0) ?></strong></li>
            </ul>
            <div class="mt-4 flex flex-wrap gap-2 text-xs font-semibold">
                <a href="<?= url('back-office/users') . '?filter_incomplete=1' ?>" class="rounded-md bg-slate-100 px-2.5 py-1.5 text-slate-700 hover:bg-slate-200">Assigner</a>
                <a href="<?= url('back-office/users') . '?filter_no_role=1' ?>" class="rounded-md bg-slate-100 px-2.5 py-1.5 text-slate-700 hover:bg-slate-200">Traiter</a>
                <a href="<?= url('back-office/invitations') ?>" class="rounded-md bg-slate-100 px-2.5 py-1.5 text-slate-700 hover:bg-slate-200">Relancer</a>
            </div>
        </article>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-sm font-bold text-slate-900">Alertes locales</h2>
        <?php if ($alertsError): ?>
            <p class="mt-3 text-sm text-rose-600"><?= htmlspecialchars((string) $alertsError, ENT_QUOTES, 'UTF-8') ?></p>
        <?php elseif ($activeAlerts === []): ?>
            <p class="mt-3 text-sm text-slate-500">Aucune alerte locale active.</p>
        <?php else: ?>
            <ul class="mt-3 space-y-2">
                <?php foreach (array_slice($activeAlerts, 0, 6) as $alert): ?>
                    <li class="rounded-lg border border-slate-100 px-3 py-2">
                        <p class="font-semibold text-slate-900"><?= htmlspecialchars((string) ($alert['title'] ?? 'Alerte'), ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="text-xs text-slate-500">Actif depuis <?= htmlspecialchars($formatDate((string) ($alert['start_at'] ?? $alert['created_at'] ?? '')), ENT_QUOTES, 'UTF-8') ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
