<?php
declare(strict_types=1);
/**
 * Mon parcours compétences — progression du membre sur les modules ALPHA → DELTA de son organisation.
 * @var array<string, mixed> $competencyJourney
 */
$competencyJourney = $competencyJourney ?? [
    'schema_available' => false,
    'load_error' => false,
    'phases' => ['ALPHA' => [], 'BRAVO' => [], 'CHARLIE' => [], 'DELTA' => []],
    'stats' => ['by_phase' => [], 'by_status' => []],
    'next_actions' => [],
];
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$statusLabels = \App\Services\Training\CompetencyModuleService::STATUS_LABELS;
$phasePresentation = \App\Services\Training\CompetencyModuleService::PHASE_LABELS;
$deliveryLabels = \App\Services\Training\CompetencyModuleService::DELIVERY_LABELS;

$schemaOk = !empty($competencyJourney['schema_available']);
$loadError = !empty($competencyJourney['load_error']);
$stats = $competencyJourney['stats'] ?? ['by_phase' => [], 'by_status' => []];
$phases = $competencyJourney['phases'] ?? [];
$nextActions = $competencyJourney['next_actions'] ?? [];

$totalModules = 0;
$totalDone = 0;
foreach (($stats['by_phase'] ?? []) as $row) {
    $totalModules += (int) ($row['total'] ?? 0);
    $totalDone += (int) ($row['completed'] ?? 0);
}
$hasModules = $totalModules > 0;
$pct = $totalModules > 0 ? (int) round($totalDone / $totalModules * 100) : 0;
$toRenew = (int) (($stats['by_status'] ?? [])['EXPIRED'] ?? 0);
$inProgress = (int) (($stats['by_status'] ?? [])['IN_PROGRESS'] ?? 0);
?>
<link rel="stylesheet" href="<?= $h(asset_url('assets/css/competency.css')) ?>">

<div class="max-w-6xl mx-auto px-4 md:px-6 py-8">
<div class="cp">
    <header class="cp-head">
        <div style="flex: 1 1 360px">
            <p class="cp-kicker">Parcours opérateur</p>
            <h1 class="cp-title">Mon parcours compétences</h1>
            <p class="cp-lead">
                Quatre phases, de la doctrine à la validation par un instructeur. Votre encadrement enregistre vos validations ;
                certaines doivent être renouvelées régulièrement.
            </p>
        </div>
        <?php if ($schemaOk && !$loadError && $hasModules): ?>
            <div style="flex: 0 1 320px; min-width: 240px">
                <div class="cp-progress">
                    <span class="cp-bar"><span style="width: <?= $pct ?>%"></span></span>
                    <span class="cp-progress__num"><?= $totalDone ?>/<?= $totalModules ?></span>
                </div>
                <p class="cp-help" style="margin:0.35rem 0 0">
                    modules validés<?= $inProgress > 0 ? ' · ' . $inProgress . ' en cours' : '' ?><?= $toRenew > 0 ? ' · ' . $toRenew . ' à renouveler' : '' ?>
                </p>
            </div>
        <?php endif; ?>
    </header>

    <?php if (!$schemaOk): ?>
        <div class="cp-empty">
            <p><strong>Le suivi des compétences n’est pas encore disponible ici.</strong></p>
            <p>Revenez plus tard ou contactez votre encadrement si le besoin est urgent.</p>
        </div>
    <?php elseif ($loadError): ?>
        <div class="cp-empty">
            <p><strong>Impossible de charger votre parcours pour le moment.</strong></p>
            <p>Réessayez dans quelques instants.</p>
        </div>
    <?php elseif (!$hasModules): ?>
        <div class="cp-empty">
            <p><strong>Votre organisation n’a pas encore publié de modules de compétences.</strong></p>
            <p>Dès que l’encadrement formation en aura créé, ils apparaîtront ici, rangés de ALPHA à DELTA, avec votre progression.</p>
        </div>
    <?php else: ?>

        <?php if ($nextActions !== []): ?>
            <section class="cp-section" aria-labelledby="cp-next-title">
                <h2 id="cp-next-title">À faire</h2>
                <ul class="cp-next">
                    <?php foreach ($nextActions as $line): ?>
                        <li><?= $h($line) ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <nav class="cp-track" aria-label="Phases du parcours">
            <?php foreach ($phasePresentation as $code => $meta): ?>
                <?php
                $st = $stats['by_phase'][$code] ?? ['total' => 0, 'completed' => 0];
                $t = (int) ($st['total'] ?? 0);
                $c = (int) ($st['completed'] ?? 0);
                $p = $t > 0 ? (int) round($c / $t * 100) : 0;
                ?>
                <a class="cp-track__step cp-phase--<?= $h($code) ?>" href="#phase-<?= $h(strtolower($code)) ?>">
                    <span class="cp-track__code"><?= $h($meta['label']) ?></span>
                    <span class="cp-track__name"><?= $h($meta['subtitle']) ?></span>
                    <span class="cp-bar"><span style="width: <?= $p ?>%"></span></span>
                    <span class="cp-track__meta"><?= $t > 0 ? $c . ' / ' . $t . ' validé' . ($c > 1 ? 's' : '') : 'Aucun module' ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php foreach ($phasePresentation as $code => $meta): ?>
            <?php
            $entries = $phases[$code] ?? [];
            if ($entries === []) {
                continue;
            }
            ?>
            <section class="cp-phase-block cp-phase--<?= $h($code) ?>" id="phase-<?= $h(strtolower($code)) ?>" aria-labelledby="cp-ph-<?= $h($code) ?>">
                <div class="cp-phase-block__head">
                    <h2 id="cp-ph-<?= $h($code) ?>"><span><?= $h($meta['label']) ?></span> — <?= $h($meta['subtitle']) ?></h2>
                    <span class="cp-count"><?= count($entries) ?></span>
                </div>
                <ul class="cp-items">
                    <?php foreach ($entries as $entry): ?>
                        <?php
                        $stKey = (string) ($entry['progress_status'] ?? 'NOT_STARTED');
                        $stKey = isset($statusLabels[$stKey]) ? $stKey : 'NOT_STARTED';
                        $name = trim((string) ($entry['module_name'] ?? ''));
                        if ($name === '') {
                            $name = (string) ($entry['module_code'] ?? 'Module');
                        }
                        $delivery = (string) ($entry['delivery_mode'] ?? '');
                        $blocked = !empty($entry['blocked_by_prereq']) && !empty($entry['missing_prereq_labels']);
                        ?>
                        <li>
                            <article class="cp-item cp-item--<?= $h($stKey) ?><?= $blocked && $stKey !== 'COMPLETED' ? ' is-blocked' : '' ?>">
                                <div class="cp-item__top">
                                    <h3 class="cp-item__name"><?= $h($name) ?></h3>
                                    <span class="cp-status cp-status--<?= $h($stKey) ?>"><?= $h($statusLabels[$stKey]) ?></span>
                                </div>
                                <?php if (trim((string) ($entry['description'] ?? '')) !== ''): ?>
                                    <p class="cp-item__desc"><?= $h((string) $entry['description']) ?></p>
                                <?php endif; ?>
                                <div class="cp-tags">
                                    <?php if (!empty($entry['is_mandatory'])): ?><span class="cp-tag cp-tag--must">Obligatoire</span><?php endif; ?>
                                    <?php if (isset($deliveryLabels[$delivery])): ?><span class="cp-tag"><?= $h($deliveryLabels[$delivery]) ?></span><?php endif; ?>
                                    <?php if (!empty($entry['duration_min'])): ?><span class="cp-tag"><?= (int) $entry['duration_min'] ?> min</span><?php endif; ?>
                                </div>
                                <?php if ($blocked && $stKey !== 'COMPLETED'): ?>
                                    <p class="cp-item__lock">Débloqué après : <?= $h(implode(', ', (array) $entry['missing_prereq_labels'])) ?></p>
                                <?php endif; ?>
                                <ul class="cp-item__facts">
                                    <?php if (!empty($entry['validated_at_display']) && $stKey !== 'NOT_STARTED'): ?>
                                        <li>Validé le <b><?= $h((string) $entry['validated_at_display']) ?></b></li>
                                    <?php endif; ?>
                                    <?php if (!empty($entry['expires_at_display'])): ?>
                                        <li><?= $stKey === 'EXPIRED' ? 'Expiré le' : 'Échéance' ?> <b><?= $h((string) $entry['expires_at_display']) ?></b></li>
                                    <?php endif; ?>
                                    <?php if (!empty($entry['recurrence_hint'])): ?>
                                        <li><?= $h((string) $entry['recurrence_hint']) ?></li>
                                    <?php endif; ?>
                                </ul>
                            </article>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>

    <p><a class="cp-link" href="<?= $h(url('formations')) ?>">← Retour aux formations</a></p>
</div>
</div>
