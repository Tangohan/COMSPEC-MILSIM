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
 * @var array{
 *   mois: string,
 *   label: string,
 *   prev: string,
 *   next: string,
 *   today: string,
 *   weeks: list<list<array{ymd: string, in_month: bool, is_today: bool, day: int, events: list<array<string, mixed>>}>>
 * }|null $eventsCalendarMonth
 */

use App\Repositories\AarReportRepository;
use App\Support\CommunityEventDetails;

$events = is_array($events ?? null) ? $events : [];
$eventsVue = (string) ($eventsVue ?? 'calendrier');
$eventsAttendanceKpis = is_array($eventsAttendanceKpis ?? null) ? $eventsAttendanceKpis : [];
$canCreateEvent = !empty($canCreateEvent);
$registryFilters = is_array($eventsRegistryFilters ?? null) ? $eventsRegistryFilters : [];
$eventsAarIndex = is_array($eventsAarIndex ?? null) ? $eventsAarIndex : [];
$eventsCalendarMonth = is_array($eventsCalendarMonth ?? null) ? $eventsCalendarMonth : null;

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$confirmed = (int) ($eventsAttendanceKpis['confirmed_yes'] ?? 0);
$effective = (int) ($eventsAttendanceKpis['effective_yes'] ?? 0);
$noShow = (int) ($eventsAttendanceKpis['no_show_yes'] ?? 0);
$effectiveRate = $confirmed > 0 ? (int) round($effective / $confirmed * 100) : 0;

$filterAnnee = (int) ($registryFilters['annee'] ?? 0);
$filterType = (string) ($registryFilters['type'] ?? '');
$filterStatut = (string) ($registryFilters['statut'] ?? '');
$filterQ = (string) ($registryFilters['q'] ?? '');
$filterMois = (string) ($registryFilters['mois'] ?? ($eventsCalendarMonth['mois'] ?? date('Y-m')));

$registryQuery = static function (array $extra = []) use ($registryFilters, $eventsVue, $filterMois): string {
    $q = array_merge([
        'vue' => $eventsVue,
        'mois' => $eventsVue === 'calendrier' ? $filterMois : null,
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
        'calendrier' => 'ce mois',
        default => 'à venir',
    }],
    ['label' => 'CONFIRMÉS', 'value' => (string) $confirmed, 'delta' => '', 'tone' => '#1e4f80', 'pct' => $confirmed > 0 ? '74%' : '0%', 'note' => 'présence déclarée'],
    ['label' => 'PRÉSENCE EFF.', 'value' => $effectiveRate . ' %', 'delta' => '', 'tone' => '#0b8a5c', 'pct' => $effectiveRate . '%', 'note' => $effective . ' pointés'],
    ['label' => 'NO-SHOW', 'value' => (string) $noShow, 'delta' => '', 'tone' => $noShow > 0 ? '#c98a12' : '#0b8a5c', 'pct' => $confirmed > 0 ? (int) round($noShow / $confirmed * 100) . '%' : '0%', 'note' => 'absents non pointés'],
];
require base_path('views/partials/ath_kpis.php');

$chipClass = static function (array $ev): string {
    $status = (string) ($ev['registry_status'] ?? '');
    $type = (string) ($ev['event_type'] ?? 'evenement');
    if ($status === 'annule') {
        return 'ath-cal__chip ath-cal__chip--annule';
    }
    return match ($type) {
        'operation' => 'ath-cal__chip ath-cal__chip--op',
        'formation' => 'ath-cal__chip ath-cal__chip--form',
        'autre' => 'ath-cal__chip ath-cal__chip--autre',
        default => 'ath-cal__chip ath-cal__chip--evt',
    };
};
?>

<div class="ath-users-filters ath-rise">
    <a href="<?= $h(url('back-office/events') . '?vue=calendrier') ?>" class="ath-btn<?= $eventsVue === 'calendrier' ? ' ath-btn--solid' : '' ?>">Calendrier</a>
    <a href="<?= $h(url('back-office/events') . '?vue=a_venir') ?>" class="ath-btn<?= $eventsVue === 'a_venir' ? ' ath-btn--solid' : '' ?>">À venir</a>
    <a href="<?= $h(url('back-office/events') . '?vue=passes') ?>" class="ath-btn<?= $eventsVue === 'passes' ? ' ath-btn--solid' : '' ?>">Passés</a>
    <a href="<?= $h(url('back-office/events') . '?vue=annules') ?>" class="ath-btn<?= $eventsVue === 'annules' ? ' ath-btn--solid' : '' ?>">Annulés</a>
    <a href="<?= $h(url('back-office/events/insights')) ?>" class="ath-btn">Insights présence</a>
</div>

<?php if ($eventsVue === 'calendrier' && is_array($eventsCalendarMonth)): ?>
<?php
    $cal = $eventsCalendarMonth;
    $calMois = (string) ($cal['mois'] ?? $filterMois);
    $calLabel = (string) ($cal['label'] ?? $calMois);
    $calPrev = (string) ($cal['prev'] ?? $calMois);
    $calNext = (string) ($cal['next'] ?? $calMois);
    $calWeeks = is_array($cal['weeks'] ?? null) ? $cal['weeks'] : [];
?>
<div class="ath-cal ath-rise" aria-label="Calendrier agenda">
    <div class="ath-cal__toolbar">
        <div class="ath-cal__nav">
            <a class="ath-btn" href="<?= $h($registryQuery(['mois' => $calPrev])) ?>" aria-label="Mois précédent">‹</a>
            <h2 class="ath-cal__title"><?= $h($calLabel) ?></h2>
            <a class="ath-btn" href="<?= $h($registryQuery(['mois' => $calNext])) ?>" aria-label="Mois suivant">›</a>
        </div>
        <div class="ath-cal__actions">
            <a class="ath-btn" href="<?= $h($registryQuery(['mois' => date('Y-m')])) ?>">Aujourd’hui</a>
            <form method="get" action="<?= $h(url('back-office/events')) ?>" class="ath-cal__jump">
                <input type="hidden" name="vue" value="calendrier">
                <?php if ($filterType !== ''): ?><input type="hidden" name="type" value="<?= $h($filterType) ?>"><?php endif; ?>
                <?php if ($filterStatut !== ''): ?><input type="hidden" name="statut" value="<?= $h($filterStatut) ?>"><?php endif; ?>
                <?php if ($filterQ !== ''): ?><input type="hidden" name="q" value="<?= $h($filterQ) ?>"><?php endif; ?>
                <label class="ath-users-filters__label" for="ops-mois">Mois</label>
                <input type="month" id="ops-mois" name="mois" value="<?= $h($calMois) ?>" class="bo-select" style="height:40px;" onchange="this.form.submit()">
            </form>
        </div>
    </div>

    <form method="get" action="<?= $h(url('back-office/events')) ?>" class="ath-users-filters" style="margin-bottom:12px;">
        <input type="hidden" name="vue" value="calendrier">
        <input type="hidden" name="mois" value="<?= $h($calMois) ?>">
        <label class="ath-users-filters__label" for="ops-type-cal">Type</label>
        <select name="type" id="ops-type-cal" class="bo-select">
            <option value="">Tous</option>
            <option value="operation" <?= $filterType === 'operation' ? 'selected' : '' ?>>Opération</option>
            <option value="formation" <?= $filterType === 'formation' ? 'selected' : '' ?>>Formation</option>
            <option value="evenement" <?= $filterType === 'evenement' ? 'selected' : '' ?>>Événement</option>
            <option value="autre" <?= $filterType === 'autre' ? 'selected' : '' ?>>Autre</option>
        </select>
        <label class="ath-users-filters__label" for="ops-statut-cal">Statut</label>
        <select name="statut" id="ops-statut-cal" class="bo-select">
            <option value="">Tous</option>
            <option value="planifie" <?= $filterStatut === 'planifie' ? 'selected' : '' ?>>Planifié</option>
            <option value="en_cours" <?= $filterStatut === 'en_cours' ? 'selected' : '' ?>>En cours</option>
            <option value="clos" <?= $filterStatut === 'clos' ? 'selected' : '' ?>>Clos</option>
            <option value="annule" <?= $filterStatut === 'annule' ? 'selected' : '' ?>>Annulé</option>
        </select>
        <label class="ath-users-filters__label" for="ops-q-cal">Recherche</label>
        <input type="search" name="q" id="ops-q-cal" value="<?= $h($filterQ) ?>" class="bo-select" style="height:40px;min-width:180px;" placeholder="Titre, zone…" autocomplete="off">
        <button type="submit" class="ath-btn ath-btn--solid">Filtrer</button>
    </form>

    <div class="ath-cal__legend" aria-hidden="true">
        <span><i class="ath-cal__dot ath-cal__dot--op"></i> Opération</span>
        <span><i class="ath-cal__dot ath-cal__dot--evt"></i> Événement</span>
        <span><i class="ath-cal__dot ath-cal__dot--form"></i> Formation</span>
        <span><i class="ath-cal__dot ath-cal__dot--annule"></i> Annulé</span>
    </div>

    <div class="ath-cal__grid" role="grid" aria-label="<?= $h($calLabel) ?>">
        <div class="ath-cal__head" role="row">
            <?php foreach (['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'] as $dow): ?>
            <div class="ath-cal__dow" role="columnheader"><?= $dow ?></div>
            <?php endforeach; ?>
        </div>
        <?php foreach ($calWeeks as $week): ?>
        <div class="ath-cal__week" role="row">
            <?php foreach ($week as $cell): ?>
            <?php
                $cellClasses = 'ath-cal__day';
                if (empty($cell['in_month'])) {
                    $cellClasses .= ' ath-cal__day--out';
                }
                if (!empty($cell['is_today'])) {
                    $cellClasses .= ' ath-cal__day--today';
                }
                $dayEvents = is_array($cell['events'] ?? null) ? $cell['events'] : [];
            ?>
            <div class="<?= $h($cellClasses) ?>" role="gridcell" data-ymd="<?= $h((string) ($cell['ymd'] ?? '')) ?>">
                <div class="ath-cal__daynum"><?= (int) ($cell['day'] ?? 0) ?></div>
                <div class="ath-cal__chips">
                    <?php foreach (array_slice($dayEvents, 0, 4) as $ev): ?>
                    <?php
                        $eid = (int) ($ev['id'] ?? 0);
                        $title = trim((string) ($ev['title'] ?? 'Créneau'));
                        $startsRaw = isset($ev['starts_at']) ? (string) $ev['starts_at'] : '';
                        $startsTs = $startsRaw !== '' ? strtotime($startsRaw) : false;
                        $time = $startsTs !== false ? date('H:i', $startsTs) : '';
                        $href = $eid > 0 ? url('back-office/events/' . $eid) : '#';
                    ?>
                    <a class="<?= $h($chipClass($ev)) ?>" href="<?= $h($href) ?>" title="<?= $h(($time !== '' ? $time . ' · ' : '') . $title) ?>">
                        <?php if ($time !== ''): ?><span class="ath-cal__time"><?= $h($time) ?></span><?php endif; ?>
                        <span class="ath-cal__name"><?= $h($title) ?></span>
                    </a>
                    <?php endforeach; ?>
                    <?php if (count($dayEvents) > 4): ?>
                    <span class="ath-cal__more">+<?= count($dayEvents) - 4 ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php if ($canCreateEvent): ?>
<form method="post" action="<?= $h(url('back-office/events')) ?>" enctype="multipart/form-data" class="ath-card ath-rise" id="nouveau" style="padding:18px 20px;margin:16px 0;">
    <div style="font-size:9px;font-weight:800;letter-spacing:0.18em;color:#8c979b;margin-bottom:12px;">NOUVEAU CRÉNEAU</div>
    <input type="hidden" name="_csrf_token" value="<?= $h(\App\Core\Csrf::token()) ?>">
    <input type="hidden" name="return_vue" value="calendrier">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
        <div style="grid-column:1/-1;">
            <label class="ath-users-filters__label" for="ev-title-ath-cal">Titre</label>
            <input id="ev-title-ath-cal" type="text" name="title" required class="bo-select" style="height:40px;width:100%;" placeholder="Ex. Briefing opération">
        </div>
        <div>
            <label class="ath-users-filters__label" for="ev-start-ath-cal">Début</label>
            <input id="ev-start-ath-cal" type="datetime-local" name="starts_at" required step="60" class="bo-select" style="height:40px;width:100%;">
        </div>
        <div>
            <label class="ath-users-filters__label" for="ev-end-ath-cal">Fin</label>
            <input id="ev-end-ath-cal" type="datetime-local" name="ends_at" step="60" class="bo-select" style="height:40px;width:100%;">
        </div>
        <div>
            <label class="ath-users-filters__label" for="ev-loc-ath-cal">Lieu</label>
            <input id="ev-loc-ath-cal" type="text" name="location" class="bo-select" style="height:40px;width:100%;">
        </div>
        <div>
            <label class="ath-users-filters__label" for="ev-type-ath-cal">Type</label>
            <select id="ev-type-ath-cal" name="event_type" class="bo-select">
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

<?php return; ?>
<?php endif; ?>

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
