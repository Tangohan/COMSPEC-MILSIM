<?php
declare(strict_types=1);

/**
 * Suivi onboarding membres — charte ATHENA.
 *
 * L’en-tête de page est rendu par la coque back-office ; cette vue ne produit que
 * les indicateurs et le tableau.
 *
 * @var list<array<string,mixed>> $onboardingRows
 * @var array<string,mixed> $onboardingKpis
 */

$rows = is_array($onboardingRows ?? null) ? $onboardingRows : [];
$kpis = is_array($onboardingKpis ?? null) ? $onboardingKpis : [];

$rate = static function (mixed $v): float {
    return is_numeric($v) ? max(0.0, (float) $v) : 0.0;
};
$fmtPct = static fn (mixed $v): string => number_format($rate($v), 1, ',', ' ') . ' %';
$clampPct = static fn (mixed $v): string => (string) max(0, min(100, (int) round($rate($v)))) . '%';
$toneForRate = static function (mixed $v): string {
    $n = is_numeric($v) ? (float) $v : 0.0;

    return $n >= 70.0 ? '#0b8a5c' : ($n >= 40.0 ? '#c98a12' : '#c72e2e');
};

$j7 = $kpis['j7_completion_rate'] ?? 0;
$j14 = $kpis['j14_completion_rate'] ?? 0;
$cross = $kpis['cross_modules_rate'] ?? 0;

$lateCount = 0;
foreach ($rows as $row) {
    if (trim((string) ($row['nudge'] ?? 'RAS')) !== 'RAS') {
        $lateCount++;
    }
}

$athKpis = [
    [
        'label' => 'COMPLÉTION J+7',
        'value' => $fmtPct($j7),
        'delta' => '',
        'tone' => $toneForRate($j7),
        'pct' => $clampPct($j7),
        'note' => 'cohorte de ' . (int) ($kpis['cohort_j7'] ?? 0) . ' membre' . ((int) ($kpis['cohort_j7'] ?? 0) > 1 ? 's' : ''),
    ],
    [
        'label' => 'COMPLÉTION J+14',
        'value' => $fmtPct($j14),
        'delta' => '',
        'tone' => $toneForRate($j14),
        'pct' => $clampPct($j14),
        'note' => 'cohorte de ' . (int) ($kpis['cohort_j14'] ?? 0) . ' membre' . ((int) ($kpis['cohort_j14'] ?? 0) > 1 ? 's' : ''),
    ],
    [
        'label' => 'ACTIVATION 3 MODULES',
        'value' => $fmtPct($cross),
        'delta' => '',
        'tone' => $toneForRate($cross),
        'pct' => $clampPct($cross),
        'note' => 'entre J+0 et J+14',
    ],
    [
        'label' => 'RELANCES À FAIRE',
        'value' => (string) $lateCount,
        'delta' => '',
        'tone' => $lateCount === 0 ? '#0b8a5c' : '#c98a12',
        'pct' => $rows === [] ? '0%' : (string) (int) round($lateCount / max(1, count($rows)) * 100) . '%',
        'note' => 'membres avec une action suggérée',
    ],
];
require base_path('views/partials/ath_kpis.php');

$athTableTitle = 'Nouveaux membres (30 jours)';
$athTableCount = count($rows);
$athTableCols = [
    'MEMBRE',
    'ADRESSE E-MAIL|m',
    'PLAN',
    'PROGRESSION|r',
    'ÉTAPES|r',
    'MODULES ACTIFS|r',
    'ANCIENNETÉ|r',
    'ACTION SUGGÉRÉE|b',
];
$athTableRows = [];
foreach ($rows as $row) {
    $name = trim((string) ($row['display_name'] ?? ''));
    if ($name === '') {
        $name = 'Membre #' . (int) ($row['user_id'] ?? 0);
    }
    $pct = (int) ($row['percent'] ?? 0);
    $athTableRows[] = [
        $name,
        (string) ($row['email'] ?? '—'),
        ucfirst((string) ($row['plan'] ?? 'membre')),
        max(0, min(100, $pct)) . ' %',
        (int) ($row['completed_count'] ?? 0) . ' / ' . (int) ($row['total_count'] ?? 0),
        (int) ($row['modules_done_count'] ?? 0) . ' / 5',
        'J+' . (int) ($row['age_days'] ?? 0),
        trim((string) ($row['nudge'] ?? 'RAS')) !== '' ? (string) $row['nudge'] : 'RAS',
    ];
}
$athTableFilters = [];
$athTableMinWidth = '1320px';
$athTableShowCheckbox = false;
$athTableExportUrl = null;
$athTablePager = null;
$athTableRowHrefs = null;
$athTableFoot = $rows === []
    ? 'Aucun membre arrivé dans les 30 derniers jours.'
    : 'Progression consolidée sur cinq modules : profil, forum, document essentiel, formation, événement.';
require base_path('views/partials/ath_table.php');
