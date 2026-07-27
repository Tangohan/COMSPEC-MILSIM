<?php
declare(strict_types=1);

use App\Services\Attendance\EventRsvpNominativeService;

$event = is_array($event ?? null) ? $event : [];
$rows = is_array($nominativeRows ?? null) ? $nominativeRows : [];
$stats = is_array($nominativeStats ?? null) ? $nominativeStats : [];
$sections = is_array($nominativeSections ?? null) ? $nominativeSections : [];
$filters = is_array($nominativeFilters ?? null) ? $nominativeFilters : [];
$responseFilterLabels = is_array($responseFilterLabels ?? null) ? $responseFilterLabels : EventRsvpNominativeService::responseFilterLabelsFr();
$atakFilterLabels = is_array($atakFilterLabels ?? null) ? $atakFilterLabels : EventRsvpNominativeService::atakFilterLabelsFr();
$eventId = (int) ($event['id'] ?? 0);
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$total = (int) ($stats['total'] ?? count($rows));

$athKpis = [
    ['label' => 'CONFIRMÉS', 'value' => (string) (int) ($stats['confirmed'] ?? 0), 'delta' => '', 'tone' => '#0b8a5c', 'pct' => $total > 0 ? (int) round((int) ($stats['confirmed'] ?? 0) / $total * 100) . '%' : '0%', 'note' => 'présents'],
    ['label' => 'PEUT-ÊTRE', 'value' => (string) (int) ($stats['maybe'] ?? 0), 'delta' => '', 'tone' => '#c98a12', 'pct' => '—', 'note' => 'en attente'],
    ['label' => 'SANS RÉPONSE', 'value' => (string) (int) ($stats['no_response'] ?? 0), 'delta' => '', 'tone' => '#1e4f80', 'pct' => '—', 'note' => 'à relancer'],
    ['label' => 'DÉCLINÉS', 'value' => (string) (int) ($stats['declined'] ?? 0), 'delta' => '', 'tone' => '#64748b', 'pct' => '—', 'note' => 'absents'],
    ['label' => 'ATAK ACTIFS', 'value' => (string) (int) ($stats['atak_active'] ?? 0), 'delta' => '', 'tone' => '#0b8a5c', 'pct' => '—', 'note' => 'terminaux'],
];
require base_path('views/partials/ath_kpis.php');

$successFlash = \App\Core\Session::getFlash('success');
$errorFlash = \App\Core\Session::getFlash('error');
$exportUrl = url('back-office/events/' . $eventId . '/reponses-nominatives/export') . '?' . http_build_query(array_filter($filters));
?>

<div class="bo-events bo-rsvp-nom ath-rise">
    <div class="ath-users-filters ath-rise">
        <a href="<?= $h(url('back-office/events/' . $eventId)) ?>" class="ath-btn">RSVP &amp; pointage</a>
        <a href="<?= $h(url('back-office/events')) ?>" class="ath-btn">Liste des créneaux</a>
        <a href="<?= $h($exportUrl) ?>" class="ath-btn ath-btn--solid">Exporter CSV</a>
    </div>

    <?php if ($successFlash): ?>
    <div class="bo-settings-flash bo-settings-flash--ok ath-rise" role="status"><?= $h((string) $successFlash) ?></div>
    <?php endif; ?>
    <?php if ($errorFlash): ?>
    <div class="bo-settings-flash bo-settings-flash--err ath-rise" role="alert"><?= $h((string) $errorFlash) ?></div>
    <?php endif; ?>

    <form method="get" action="<?= $h(url('back-office/events/' . $eventId . '/reponses-nominatives')) ?>" class="ath-users-filters ath-rise">
        <label class="ath-users-filters__label" for="rsvp-q">Recherche
            <input type="search" name="q" id="rsvp-q" value="<?= $h((string) ($filters['q'] ?? '')) ?>" placeholder="Nom, indicatif, section…">
        </label>
        <label class="ath-users-filters__label" for="rsvp-response">Réponse
            <select name="response" id="rsvp-response">
                <option value="">Toutes</option>
                <?php foreach ($responseFilterLabels as $key => $label): ?>
                <option value="<?= $h($key) ?>" <?= ($filters['response'] ?? '') === $key ? 'selected' : '' ?>><?= $h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="ath-users-filters__label" for="rsvp-section">Section
            <select name="section" id="rsvp-section">
                <option value="">Toutes</option>
                <?php foreach ($sections as $section): ?>
                <option value="<?= $h($section) ?>" <?= ($filters['section'] ?? '') === $section ? 'selected' : '' ?>><?= $h($section) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="ath-users-filters__label" for="rsvp-atak">ATAK
            <select name="atak" id="rsvp-atak">
                <option value="">Tous</option>
                <?php foreach ($atakFilterLabels as $key => $label): ?>
                <option value="<?= $h($key) ?>" <?= ($filters['atak'] ?? '') === $key ? 'selected' : '' ?>><?= $h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="ath-btn ath-btn--solid">Appliquer les filtres</button>
    </form>

    <?php
    $athTableRows = [];
    $athTableRowHrefs = [];
    foreach ($rows as $row) {
        $athTableRows[] = [
            (string) ($row['matricule'] ?? '—'),
            (string) ($row['callsign'] ?? '—'),
            (string) ($row['display_name'] ?? '—'),
            (string) ($row['section'] ?? '—'),
            (string) ($row['planned_role'] ?? '—'),
            (string) ($row['response_label'] ?? '—'),
            (string) ($row['responded_label'] ?? '—'),
            (string) ($row['availability_label'] ?? '—'),
            (string) ($row['atak_label'] ?? '—'),
            (string) ($row['atak_terminal_label'] ?? '—'),
            (string) (int) ($row['reminder_count'] ?? 0),
            (string) ((int) ($row['historical_presence_pct'] ?? 0)) . ' %',
            (string) (($row['admin_comment'] ?? '') !== '' ? $row['admin_comment'] : '—'),
        ];
        $athTableRowHrefs[] = null;
    }

    $athTableTitle = (string) ($event['title'] ?? 'Créneau') . ' — réponses nominatives';
    $athTableCount = count($rows);
    $athTableCols = [
        'MATRICULE|m', 'INDICATIF', 'NOM', 'SECTION', 'RÔLE PRÉVU', 'RÉPONSE|b', 'RÉPONDU LE|m',
        'DISPO.|m', 'ATAK|b', 'TERMINAL|m', 'RELANCES|r', 'PRÉSENCE|r', 'COMMENTAIRES',
    ];
    $athTableFilters = ['Réponse', 'Section', 'ATAK'];
    $athTableMinWidth = '1480px';
    $athTableShowCheckbox = false;
    $athTableFoot = 'Affichage 1 – ' . count($rows) . ' sur ' . $total
        . ' · mis à jour ' . $h((string) ($stats['updated_at_label'] ?? ''));
    $athTableExportUrl = $exportUrl;
    require base_path('views/partials/ath_table.php');
    ?>
</div>
