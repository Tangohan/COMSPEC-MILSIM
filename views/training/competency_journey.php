<?php
declare(strict_types=1);

$competencyJourney = $competencyJourney ?? [
    'schema_available' => false,
    'load_error' => false,
    'phases' => ['ALPHA' => [], 'BRAVO' => [], 'CHARLIE' => [], 'DELTA' => []],
    'stats' => [
        'by_phase' => [
            'ALPHA' => ['total' => 0, 'completed' => 0],
            'BRAVO' => ['total' => 0, 'completed' => 0],
            'CHARLIE' => ['total' => 0, 'completed' => 0],
            'DELTA' => ['total' => 0, 'completed' => 0],
        ],
        'by_status' => [],
    ],
    'next_actions' => [],
];

$statusLabels = [
    'NOT_STARTED' => 'Pas commencé',
    'IN_PROGRESS' => 'En cours',
    'COMPLETED' => 'Validé',
    'FAILED' => 'Non validé',
    'EXPIRED' => 'À renouveler',
];

$phasePresentation = [
    'ALPHA' => ['label' => 'ALPHA', 'subtitle' => 'Doctrine / cadre légal'],
    'BRAVO' => ['label' => 'BRAVO', 'subtitle' => 'Application pratique'],
    'CHARLIE' => ['label' => 'CHARLIE', 'subtitle' => 'Simulation scénarisée'],
    'DELTA' => ['label' => 'DELTA', 'subtitle' => 'Validation instructeur'],
];

$deliveryLabels = [
    'INITIAL' => 'Parcours initial',
    'RENFORCE' => 'Renforcement',
    'RECYCLAGE' => 'Recyclage',
    'CRITIQUE' => 'Critique',
];

$schemaOk = !empty($competencyJourney['schema_available']);
$loadError = !empty($competencyJourney['load_error']);
$stats = $competencyJourney['stats'] ?? ['by_phase' => [], 'by_status' => []];
$phases = $competencyJourney['phases'] ?? [];
$nextActions = $competencyJourney['next_actions'] ?? [];

$totalModules = 0;
foreach (($stats['by_phase'] ?? []) as $row) {
    $totalModules += (int) ($row['total'] ?? 0);
}
$hasModules = $totalModules > 0;
?>
<div class="max-w-6xl mx-auto px-6 py-8 space-y-6">
    <header class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs uppercase tracking-[0.2em] text-emerald-700 font-bold">Parcours opérateur</p>
        <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-900">Mon parcours compétences</h1>
        <p class="mt-3 text-sm text-slate-600 max-w-3xl">
            Visualisez vos blocs ALPHA / BRAVO / CHARLIE / DELTA, les prérequis à respecter et les échéances de renouvellement prévues pour votre organisation.
        </p>
        <p class="mt-4">
            <a href="<?= htmlspecialchars(url('formations'), ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-semibold text-emerald-700 underline-offset-2 hover:underline">Retour aux formations</a>
        </p>
    </header>

    <?php if (!$schemaOk): ?>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-950">
            Le suivi détaillé des compétences n’est pas encore disponible sur cet environnement. Revenez plus tard ou contactez un responsable si le besoin est urgent.
        </div>
    <?php elseif ($loadError): ?>
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-6 text-sm text-rose-950">
            Impossible de charger votre parcours pour le moment. Réessayez dans quelques instants.
        </div>
    <?php elseif (!$hasModules): ?>
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-6 text-sm text-slate-700">
            Aucun module de compétences n’est activé pour votre organisation. Lorsque votre encadrement en aura publié, ils apparaîtront ici.
        </div>
    <?php endif; ?>

    <?php if ($schemaOk && !$loadError && $hasModules): ?>
    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <?php foreach ($phasePresentation as $code => $meta): ?>
            <?php
            $st = $stats['by_phase'][$code] ?? ['total' => 0, 'completed' => 0];
            $t = (int) ($st['total'] ?? 0);
            $c = (int) ($st['completed'] ?? 0);
            ?>
            <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-[11px] uppercase tracking-[0.18em] text-slate-500 font-bold"><?= htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8') ?></p>
                <p class="mt-2 text-sm text-slate-700"><?= htmlspecialchars($meta['subtitle'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php if ($t > 0): ?>
                    <p class="mt-3 text-xs font-semibold text-emerald-800">
                        <?= (int) $c ?> validé<?= $c > 1 ? 's' : '' ?> sur <?= (int) $t ?> module<?= $t > 1 ? 's' : '' ?>
                    </p>
                <?php else: ?>
                    <p class="mt-3 text-xs text-slate-500">Aucun module dans ce bloc pour l’instant.</p>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </section>

    <?php foreach ($phasePresentation as $code => $meta): ?>
        <?php
        $entries = $phases[$code] ?? [];
        if ($entries === []) {
            continue;
        }
        ?>
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-sm font-black uppercase tracking-[0.2em] text-slate-900">
                <?= htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($meta['subtitle'], ENT_QUOTES, 'UTF-8') ?>
            </h2>
            <ul class="mt-4 space-y-4">
                <?php foreach ($entries as $entry): ?>
                    <?php
                    $stKey = (string) ($entry['progress_status'] ?? 'NOT_STARTED');
                    $stLabel = $statusLabels[$stKey] ?? $stKey;
                    $name = trim((string) ($entry['module_name'] ?? ''));
                    if ($name === '') {
                        $name = (string) ($entry['module_code'] ?? 'Module');
                    }
                    $delivery = (string) ($entry['delivery_mode'] ?? '');
                    $deliveryHuman = $deliveryLabels[$delivery] ?? '';
                    ?>
                    <li class="rounded-xl border border-slate-100 bg-slate-50/80 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="font-bold text-slate-900"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></p>
                                <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-600">
                                    <?php if (!empty($entry['is_mandatory'])): ?>
                                        <span class="rounded-full bg-amber-100 px-2 py-0.5 font-semibold text-amber-900">Obligatoire</span>
                                    <?php endif; ?>
                                    <?php if ($deliveryHuman !== ''): ?>
                                        <span class="rounded-full bg-slate-200/80 px-2 py-0.5 font-medium text-slate-800"><?= htmlspecialchars($deliveryHuman, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide <?= $stKey === 'COMPLETED' ? 'bg-emerald-100 text-emerald-900' : ($stKey === 'EXPIRED' ? 'bg-rose-100 text-rose-900' : ($stKey === 'IN_PROGRESS' ? 'bg-sky-100 text-sky-900' : 'bg-slate-200 text-slate-800')) ?>">
                                <?= htmlspecialchars($stLabel, ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>
                        <?php if (!empty($entry['blocked_by_prereq']) && !empty($entry['missing_prereq_labels'])): ?>
                            <p class="mt-2 text-sm text-amber-900">
                                <span class="font-semibold">En attente de prérequis :</span>
                                <?= htmlspecialchars(implode(', ', $entry['missing_prereq_labels']), ENT_QUOTES, 'UTF-8') ?>.
                            </p>
                        <?php endif; ?>
                        <?php if (!empty($entry['expires_at_display'])): ?>
                            <p class="mt-2 text-xs text-slate-600">
                                <span class="font-semibold text-slate-700">Échéance actuelle :</span>
                                <?= htmlspecialchars((string) $entry['expires_at_display'], ENT_QUOTES, 'UTF-8') ?>
                            </p>
                        <?php endif; ?>
                        <?php if (!empty($entry['recurrence_hint'])): ?>
                            <p class="mt-1 text-xs text-slate-600"><?= htmlspecialchars((string) $entry['recurrence_hint'], ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endforeach; ?>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-sm font-black uppercase tracking-[0.2em] text-slate-900">Prochaines actions</h2>
        <?php if ($nextActions === []): ?>
            <p class="mt-4 text-sm text-slate-600">Rien d’urgent à signaler d’après les informations disponibles. Poursuivez vos modules en cours ou consultez vos formations.</p>
        <?php else: ?>
            <ul class="mt-4 list-disc space-y-2 pl-5 text-sm text-slate-700">
                <?php foreach ($nextActions as $line): ?>
                    <li><?= htmlspecialchars($line, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
    <?php endif; ?>
</div>
