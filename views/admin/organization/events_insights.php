<?php
declare(strict_types=1);

/**
 * Insights de présence — charte ATHENA.
 *
 * L’en-tête (kicker, titre, sous-titre) est rendu par la coque back-office
 * (`views/partials/back_office_page_head.php` via `config/back_office_pages.php`) :
 * cette vue ne produit que les indicateurs et les tableaux.
 *
 * @var array<string, int> $eventsAttendanceKpis
 * @var list<array<string, mixed>> $eventsAbsenceReasons
 * @var list<array<string, mixed>> $eventsRecommendedSlots
 * @var list<array<string, mixed>> $eventsRegularityScores
 * @var float $eventsNewMemberParticipationDelta
 */

$eventsAttendanceKpis = $eventsAttendanceKpis ?? ['confirmed_yes' => 0, 'effective_yes' => 0, 'no_show_yes' => 0];
$eventsAbsenceReasons = is_array($eventsAbsenceReasons ?? null) ? $eventsAbsenceReasons : [];
$eventsRecommendedSlots = is_array($eventsRecommendedSlots ?? null) ? $eventsRecommendedSlots : [];
$eventsRegularityScores = is_array($eventsRegularityScores ?? null) ? $eventsRegularityScores : [];
$eventsNewMemberParticipationDelta = isset($eventsNewMemberParticipationDelta) ? (float) $eventsNewMemberParticipationDelta : 0.0;

$confirmed = (int) ($eventsAttendanceKpis['confirmed_yes'] ?? 0);
$effective = (int) ($eventsAttendanceKpis['effective_yes'] ?? 0);
$noShow = (int) ($eventsAttendanceKpis['no_show_yes'] ?? 0);
$effectiveRate = $confirmed > 0 ? ($effective / $confirmed) * 100 : 0.0;
$noShowRate = $confirmed > 0 ? ($noShow / $confirmed) * 100 : 0.0;
$deltaPoints = $eventsNewMemberParticipationDelta * 100;

$pct1 = static fn (float $v): string => number_format($v, 1, ',', ' ') . ' %';
$clampPct = static fn (float $v): string => (string) max(0, min(100, (int) round($v))) . '%';

$dowLabel = static function (int $day): string {
    return match ($day) {
        1 => 'Dimanche',
        2 => 'Lundi',
        3 => 'Mardi',
        4 => 'Mercredi',
        5 => 'Jeudi',
        6 => 'Vendredi',
        7 => 'Samedi',
        default => 'Jour',
    };
};

/** Libellés métier pour les motifs d’absence enregistrés (sans jargon technique). */
$absenceReasonLabel = static function (string $raw): string {
    return match ($raw) {
        'non_renseigne', '' => 'Non renseigné',
        'indisponible' => 'Indisponible',
        'travail' => 'Contrainte professionnelle',
        'famille' => 'Contrainte familiale',
        'sante' => 'Santé',
        'technique' => 'Problème technique',
        'oubli' => 'Oubli',
        default => ucfirst(str_replace('_', ' ', $raw)),
    };
};

$athKpis = [
    [
        'label' => 'PRÉSENCE EFFECTIVE',
        'value' => $confirmed > 0 ? $pct1($effectiveRate) : '—',
        'delta' => '',
        'tone' => $effectiveRate >= 80.0 ? '#0b8a5c' : ($effectiveRate >= 60.0 ? '#c98a12' : '#c72e2e'),
        'pct' => $clampPct($effectiveRate),
        'note' => $effective . ' / ' . $confirmed . ' RSVP « présent » pointés',
    ],
    [
        'label' => 'TAUX DE NO-SHOW',
        'value' => $confirmed > 0 ? $pct1($noShowRate) : '—',
        'delta' => '',
        'tone' => $noShowRate <= 10.0 ? '#0b8a5c' : ($noShowRate <= 25.0 ? '#c98a12' : '#c72e2e'),
        'pct' => $clampPct($noShowRate),
        'note' => $noShow . ' RSVP « présent » non pointés',
    ],
    [
        'label' => 'NOUVEAUX MEMBRES',
        'value' => number_format($deltaPoints, 1, ',', ' ') . ' pts',
        'delta' => $deltaPoints > 0 ? '+' . number_format($deltaPoints, 1, ',', ' ') : '',
        'tone' => $deltaPoints >= 0 ? '#0b8a5c' : '#c72e2e',
        'pct' => $clampPct(abs($deltaPoints)),
        'note' => 'participation J+30 → J+90',
    ],
    [
        'label' => 'CRÉNEAUX ANALYSÉS',
        'value' => (string) count($eventsRecommendedSlots),
        'delta' => '',
        'tone' => '#1e4f80',
        'pct' => $clampPct(count($eventsRecommendedSlots) * 10),
        'note' => 'sur 90 jours glissants',
    ],
];
require base_path('views/partials/ath_kpis.php');

// ---- Motifs d’absence ----
$absenceTotal = 0;
foreach ($eventsAbsenceReasons as $reason) {
    $absenceTotal += (int) ($reason['total'] ?? 0);
}
$athTableTitle = 'Motifs d’absence';
$athTableCount = $absenceTotal > 0 ? $absenceTotal . ' absence' . ($absenceTotal > 1 ? 's' : '') : 'aucune absence';
$athTableCols = ['MOTIF', 'OCCURRENCES|r', 'PART|r'];
$athTableRows = [];
foreach ($eventsAbsenceReasons as $reason) {
    $n = (int) ($reason['total'] ?? 0);
    $athTableRows[] = [
        $absenceReasonLabel((string) ($reason['absence_reason'] ?? '')),
        (string) $n,
        $absenceTotal > 0 ? number_format($n / $absenceTotal * 100, 1, ',', ' ') . ' %' : '—',
    ];
}
$athTableFilters = [];
$athTableMinWidth = '620px';
$athTableShowCheckbox = false;
$athTableExportUrl = null;
$athTablePager = null;
$athTableRowHrefs = null;
$athTableFoot = $eventsAbsenceReasons === []
    ? 'Aucune absence consolidée sur la période.'
    : count($eventsAbsenceReasons) . ' motif' . (count($eventsAbsenceReasons) > 1 ? 's' : '') . ' distinct' . (count($eventsAbsenceReasons) > 1 ? 's' : '');
require base_path('views/partials/ath_table.php');

// ---- Créneaux recommandés ----
$athTableTitle = 'Créneaux recommandés';
$athTableCount = count($eventsRecommendedSlots) . ' créneau' . (count($eventsRecommendedSlots) > 1 ? 'x' : '');
$athTableCols = ['JOUR', 'HEURE|m', 'PRÉSENCE EFFECTIVE|r', 'ÉCHANTILLON|r'];
$athTableRows = [];
foreach ($eventsRecommendedSlots as $slot) {
    $athTableRows[] = [
        $dowLabel((int) ($slot['day_of_week'] ?? 0)),
        str_pad((string) (int) ($slot['hour_slot'] ?? 0), 2, '0', STR_PAD_LEFT) . ' h',
        number_format(((float) ($slot['attendance_rate'] ?? 0)) * 100, 1, ',', ' ') . ' %',
        (string) (int) ($slot['sample_size'] ?? 0),
    ];
}
$athTableMinWidth = '760px';
$athTableFoot = $eventsRecommendedSlots === []
    ? 'Pas assez de données pour proposer des créneaux : il faut plusieurs événements pointés sur un même jour et une même heure.'
    : 'Classés par taux de présence effective décroissant.';
require base_path('views/partials/ath_table.php');

// ---- Régularité à surveiller ----
$athTableTitle = 'Régularité à surveiller';
$athTableCount = count($eventsRegularityScores) . ' membre' . (count($eventsRegularityScores) > 1 ? 's' : '');
$athTableCols = ['MEMBRE', 'RÉGULARITÉ|r', 'ENGAGEMENTS|r', 'SUIVI|b'];
$athTableRows = [];
foreach ($eventsRegularityScores as $member) {
    $score = ((float) ($member['regularity_score'] ?? 0)) * 100;
    $athTableRows[] = [
        (string) ($member['display_name'] ?? 'Membre'),
        number_format($score, 1, ',', ' ') . ' %',
        (string) (int) ($member['commitments'] ?? 0),
        $score >= 80.0 ? 'À jour' : ($score >= 50.0 ? 'En attente' : 'Critique'),
    ];
}
$athTableMinWidth = '820px';
$athTableFoot = $eventsRegularityScores === []
    ? 'Scores insuffisants : au moins deux engagements par membre sont nécessaires.'
    : 'Score = présences pointées rapportées aux RSVP « présent ».';
require base_path('views/partials/ath_table.php');
