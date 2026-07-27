<?php
declare(strict_types=1);

/**
 * Opérations / événements — rendu ATHENA.
 *
 * @var list<array<string, mixed>> $events
 * @var string $eventsVue
 * @var array<string, int> $eventsAttendanceKpis
 * @var bool $canCreateEvent
 * @var array<string, mixed> $eventsRegistryFilters
 * @var array<string, array{id: int, status: string, status_label: string}> $eventsAarIndex
 */

use App\Repositories\AarReportRepository;
use App\Support\CommunityEventDetails;

$events = is_array($events ?? null) ? $events : [];
$eventsVue = (string) ($eventsVue ?? 'a_venir');
$eventsAttendanceKpis = is_array($eventsAttendanceKpis ?? null) ? $eventsAttendanceKpis : [];
$canCreateEvent = !empty($canCreateEvent);
$registryFilters = is_array($eventsRegistryFilters ?? null) ? $eventsRegistryFilters : [];
$eventsAarIndex = is_array($eventsAarIndex ?? null) ? $eventsAarIndex : [];

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$confirmed = (int) ($eventsAttendanceKpis['confirmed_yes'] ?? 0);
$effective = (int) ($eventsAttendanceKpis['effective_yes'] ?? 0);
$noShow = (int) ($eventsAttendanceKpis['no_show_yes'] ?? 0);
$effectiveRate = $confirmed > 0 ? (int) round($effective / $confirmed * 100) : 0;

$filterAnnee = (int) ($registryFilters['annee'] ?? 0);
$filterType = (string) ($registryFilters['type'] ?? '');
$filterStatut = (string) ($registryFilters['statut'] ?? '');
$filterQ = (string) ($registryFilters['q'] ?? '');

$registryQuery = static function (array $extra = []) use ($registryFilters, $eventsVue): string {
    $q = array_merge([
        'vue' => $eventsVue,
        'annee' => !empty($registryFilters['annee']) ? (int) $registryFilters['annee'] : null,
        'type' => ($registryFilters['type'] ?? '') !== '' ? (string) $registryFilters['type'] : null,
        'statut' => ($registryFilters['statut'] ?? '') !== '' ? (string) $registryFilters['statut'] : null,
        'q' => ($registryFilters['q'] ?? '') !== '' ? (string) $registryFilters['q'] : null,
    ], $extra);
    $q = array_filter($q, static fn ($v) => $v !== null && $v !== '');

    return url('back-office/events') . ($q ? '?' . http_build_query($q) : '');
};

$typeLabel = static function (string $et): string {
    return match ($et) {
        'operation' => 'Opération',
        'formation' => 'Formation',
        'autre' => 'Autre',
        default => 'Événement',
    };
};

$statusLabel = static function (array $ev): string {
    return match ((string) ($ev['registry_status'] ?? '')) {
        'annule' => 'Annulé',
        'en_cours' => 'En cours',
        'clos' => 'Clos',
        default => 'Planifié',
    };
};

$fmtDate = static function (?string $raw): string {
    if ($raw === null || trim($raw) === '') {
        return '—';
    }
    $ts = strtotime($raw);

    return $ts ? date('d/m/Y', $ts) : $raw;
};

$fmtDuration = static function (?string $starts, ?string $ends): string {
    if ($starts === null || trim($starts) === '' || $ends === null || trim($ends) === '') {
        return '—';
    }
    $s = strtotime($starts);
    $e = strtotime($ends);
    if ($s === false || $e === false || $e <= $s) {
        return '—';
    }
    $mins = (int) round(($e - $s) / 60);
    $hPart = intdiv($mins, 60);
    $mPart = $mins % 60;
    if ($hPart > 0 && $mPart > 0) {
        return $hPart . 'h' . str_pad((string) $mPart, 2, '0', STR_PAD_LEFT);
    }
    if ($hPart > 0) {
        return $hPart . 'h';
    }

    return $mPart . ' min';
};

$fmtObjectives = static function (array $ev): string {
    $phases = CommunityEventDetails::decodeSchedule($ev['schedule_json'] ?? null);
    $phaseCount = 0;
    foreach ($phases as $phase) {
        if (($phase['type'] ?? '') === 'phase') {
            $phaseCount++;
        }
    }
    if ($phaseCount > 0) {
        return (string) $phaseCount;
    }
    $slots = (int) ($ev['slot_count'] ?? 0);
    if ($slots > 0) {
        return (string) $slots;
    }

    return '—';
};

$fmtWeather = static function (array $ev): string {
    $text = trim((string) ($ev['conditions_special'] ?? ''));
    if ($text === '') {
        $text = trim((string) ($ev['conditions_general'] ?? ''));
    }
    if ($text === '') {
        return '—';
    }
    $line = trim((string) (preg_split('/\R/u', $text)[0] ?? $text));
    if ($line === '') {
        return '—';
    }
    if (mb_strlen($line) > 40) {
        return mb_substr($line, 0, 37) . '…';
    }

    return $line;
};

$crLabel = static function (array $ev) use ($eventsAarIndex): string {
    $registryStatus = (string) ($ev['registry_status'] ?? '');
    if (in_array($registryStatus, ['planifie', 'en_cours'], true)) {
        return '—';
    }
    $titleKey = AarReportRepository::normalizeOperationKey((string) ($ev['title'] ?? ''));
    $aar = $titleKey !== '' ? ($eventsAarIndex[$titleKey] ?? null) : null;
    if ($aar === null) {
        $tagKey = AarReportRepository::normalizeOperationKey((string) ($ev['campaign_tag'] ?? ''));
        $aar = $tagKey !== '' ? ($eventsAarIndex[$tagKey] ?? null) : null;
    }
    if ($aar === null) {
        return $registryStatus === 'annule' ? '—' : 'Manquant';
    }

    return (string) ($aar['status_label'] ?? 'En attente');
};

$commanderLabel = static function (array $ev): string {
    $callsign = trim((string) ($ev['commander_callsign'] ?? ''));
    if ($callsign !== '') {
        return $callsign;
    }
    $name = trim((string) ($ev['commander_name'] ?? ''));

    return $name !== '' ? $name : '—';
};

$yearNow = (int) date('Y');
$yearOptions = range($yearNow + 1, $yearNow - 6);

$athKpis = [
    ['label' => 'CRÉNEAUX', 'value' => (string) count($events), 'delta' => '', 'tone' => '#0b8a5c', 'pct' => '100%', 'note' => match ($eventsVue) {
        'passes' => 'passés',
        'annules' => 'annulés',
        default => 'à venir',
    }],
    ['label' => 'CONFIRMÉS', 'value' => (string) $confirmed, 'delta' => '', 'tone' => '#1e4f80', 'pct' => $confirmed > 0 ? '74%' : '0%', 'note' => 'présence déclarée'],
    ['label' => 'PRÉSENCE EFF.', 'value' => $effectiveRate . ' %', 'delta' => '', 'tone' => '#0b8a5c', 'pct' => $effectiveRate . '%', 'note' => $effective . ' pointés'],
    ['label' => 'NO-SHOW', 'value' => (string) $noShow, 'delta' => '', 'tone' => $noShow > 0 ? '#c98a12' : '#0b8a5c', 'pct' => $confirmed > 0 ? (int) round($noShow / $confirmed * 100) . '%' : '0%', 'note' => 'absents non pointés'],
];
require base_path('views/partials/ath_kpis.php');

$s = \App\Core\Session::getFlash('success');
$e = \App\Core\Session::getFlash('error');
?>
<?php if ($s): ?><div class="ath-banner-warn ath-rise" style="background:#e6f8f0;border-color:#bfe9d8;" role="status"><div class="ath-banner-warn__text" style="color:#0b6b47;"><?= $h((string) $s) ?></div></div><?php endif; ?>
<?php if ($e): ?><div class="ath-banner-warn ath-rise" role="alert"><div class="ath-banner-warn__text"><?= $h((string) $e) ?></div></div><?php endif; ?>

<div class="ath-users-filters ath-rise">
    <a href="<?= $h(url('back-office/events') . '?vue=a_venir') ?>" class="ath-btn<?= $eventsVue === 'a_venir' ? ' ath-btn--solid' : '' ?>">À venir</a>
    <a href="<?= $h(url('back-office/events') . '?vue=passes') ?>" class="ath-btn<?= $eventsVue === 'passes' ? ' ath-btn--solid' : '' ?>">Passés</a>
    <a href="<?= $h(url('back-office/events') . '?vue=annules') ?>" class="ath-btn<?= $eventsVue === 'annules' ? ' ath-btn--solid' : '' ?>">Annulés</a>
    <a href="<?= $h(url('back-office/events/insights')) ?>" class="ath-btn">Insights présence</a>
</div>

<form method="get" action="<?= $h(url('back-office/events')) ?>" class="ath-users-filters ath-rise">
    <input type="hidden" name="vue" value="<?= $h($eventsVue) ?>">
    <label class="ath-users-filters__label" for="ops-annee">Année</label>
    <select name="annee" id="ops-annee" class="bo-select">
        <option value="">Toutes</option>
        <?php foreach ($yearOptions as $y): ?>
        <option value="<?= $y ?>" <?= $filterAnnee === $y ? 'selected' : '' ?>><?= $y ?></option>
        <?php endforeach; ?>
    </select>
    <label class="ath-users-filters__label" for="ops-type">Type</label>
    <select name="type" id="ops-type" class="bo-select">
        <option value="">Tous</option>
        <option value="operation" <?= $filterType === 'operation' ? 'selected' : '' ?>>Opération</option>
        <option value="formation" <?= $filterType === 'formation' ? 'selected' : '' ?>>Formation</option>
        <option value="evenement" <?= $filterType === 'evenement' ? 'selected' : '' ?>>Événement</option>
        <option value="autre" <?= $filterType === 'autre' ? 'selected' : '' ?>>Autre</option>
    </select>
    <label class="ath-users-filters__label" for="ops-statut">Statut</label>
    <select name="statut" id="ops-statut" class="bo-select">
        <option value="">Tous</option>
        <option value="planifie" <?= $filterStatut === 'planifie' ? 'selected' : '' ?>>Planifié</option>
        <option value="en_cours" <?= $filterStatut === 'en_cours' ? 'selected' : '' ?>>En cours</option>
        <option value="clos" <?= $filterStatut === 'clos' ? 'selected' : '' ?>>Clos</option>
        <option value="annule" <?= $filterStatut === 'annule' ? 'selected' : '' ?>>Annulé</option>
    </select>
    <label class="ath-users-filters__label" for="ops-q">Recherche</label>
    <input type="search" name="q" id="ops-q" value="<?= $h($filterQ) ?>" class="bo-select" style="height:40px;min-width:200px;" placeholder="Titre, zone, repère…" autocomplete="off" spellcheck="false">
    <button type="submit" class="ath-btn ath-btn--solid">Filtrer</button>
    <a href="<?= $h(url('back-office/events') . '?vue=' . rawurlencode($eventsVue)) ?>" class="ath-btn">Réinitialiser</a>
</form>

<?php if ($canCreateEvent): ?>
<form method="post" action="<?= $h(url('back-office/events')) ?>" enctype="multipart/form-data" class="ath-card ath-rise" id="nouveau" style="padding:18px 20px;margin-bottom:16px;">
    <div style="font-size:9px;font-weight:800;letter-spacing:0.18em;color:#8c979b;margin-bottom:12px;">NOUVEAU CRÉNEAU</div>
    <input type="hidden" name="_csrf_token" value="<?= $h(\App\Core\Csrf::token()) ?>">
    <input type="hidden" name="return_vue" value="<?= $h($eventsVue) ?>">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
        <div style="grid-column:1/-1;">
            <label class="ath-users-filters__label" for="ev-title-ath">Titre</label>
            <input id="ev-title-ath" type="text" name="title" required class="bo-select" style="height:40px;width:100%;" placeholder="Ex. Briefing opération">
        </div>
        <div>
            <label class="ath-users-filters__label" for="ev-start-ath">Début</label>
            <input id="ev-start-ath" type="datetime-local" name="starts_at" required step="60" class="bo-select" style="height:40px;width:100%;">
        </div>
        <div>
            <label class="ath-users-filters__label" for="ev-end-ath">Fin</label>
            <input id="ev-end-ath" type="datetime-local" name="ends_at" step="60" class="bo-select" style="height:40px;width:100%;">
        </div>
        <div>
            <label class="ath-users-filters__label" for="ev-loc-ath">Lieu</label>
            <input id="ev-loc-ath" type="text" name="location" class="bo-select" style="height:40px;width:100%;">
        </div>
        <div>
            <label class="ath-users-filters__label" for="ev-type-ath">Type</label>
            <select id="ev-type-ath" name="event_type" class="bo-select">
                <option value="operation">Opération</option>
                <option value="evenement" selected>Événement</option>
                <option value="formation">Formation</option>
                <option value="autre">Autre</option>
            </select>
        </div>
    </div>
    <button type="submit" class="ath-btn ath-btn--solid" style="margin-top:14px;">Publier le créneau</button>
</form>
<?php endif; ?>

<?php
$athTableRows = [];
$athTableRowHrefs = [];
foreach ($events as $ev) {
    $eid = (int) ($ev['id'] ?? 0);
    $startsRaw = isset($ev['starts_at']) ? (string) $ev['starts_at'] : '';
    $startsTs = $startsRaw !== '' ? strtotime($startsRaw) : false;
    $refYear = $startsTs !== false ? (int) date('Y', $startsTs) : (int) date('Y');
    $ref = 'OP-' . $refYear . '-' . str_pad((string) $eid, 3, '0', STR_PAD_LEFT);
    $title = trim((string) ($ev['title'] ?? ''));
    $et = (string) ($ev['event_type'] ?? 'evenement');
    $zone = trim((string) ($ev['location'] ?? ''));
    $engaged = (int) ($ev['engaged_count'] ?? 0);
    $athTableRows[] = [
        $ref,
        $title !== '' ? $title : '—',
        $fmtDate($startsRaw !== '' ? $startsRaw : null),
        $typeLabel($et),
        $zone !== '' ? $zone : '—',
        $commanderLabel($ev),
        $engaged > 0 ? (string) $engaged : '—',
        $fmtDuration(
            isset($ev['starts_at']) ? (string) $ev['starts_at'] : null,
            isset($ev['ends_at']) ? (string) $ev['ends_at'] : null
        ),
        $fmtObjectives($ev),
        '—',
        $fmtWeather($ev),
        $crLabel($ev),
        $statusLabel($ev),
    ];
    $athTableRowHrefs[] = $eid > 0 ? url('back-office/events/' . $eid) : null;
}

$athTableTitle = 'Registre des opérations';
$athTableCount = count($events);
$athTableCols = [
    'RÉF.|m', 'OPÉRATION', 'DATE|m', 'TYPE', 'ZONE', 'COMMANDANT',
    'ENGAGÉS|r', 'DURÉE|r', 'OBJECTIFS|r', 'PERTES|r', 'MÉTÉO', 'CR|b', 'STATUT|b',
];
$athTableFilters = [];
$athTableMinWidth = '1620px';
$athTableFilterName = 'q';
$athTableFilterValue = $filterQ;
$athTableExportUrl = $registryQuery(['export' => 'csv']);
$athTableFoot = count($events) > 0
    ? 'Affichage 1 – ' . count($events) . ' sur ' . count($events) . ' · ' . date('d/m/Y H:i')
    : 'Aucun créneau dans cette vue · ' . date('d/m/Y H:i');
require base_path('views/partials/ath_table.php');
