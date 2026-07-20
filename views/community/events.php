<?php
/** @var list<array<string, mixed>> $events */
/** @var int|null $currentUserId */
/** @var array<string, mixed>|null $eventsQuota */
/** @var array<int, bool> $eventsCheckInFlags */
/** @var array<int, array{yes:list<array{display_name:string,callsign:string}>,maybe:list<array{display_name:string,callsign:string}>,no:list<array{display_name:string,callsign:string}>}> $eventsRsvpSummaries */
/** @var array<int, list<array<string, mixed>>> $eventSlotsByEvent */
/** @var array<int, array<string, mixed>> $mySlotAssignmentByEvent */

use App\Support\CommunityEventDetails;

$eventsQuota = $eventsQuota ?? null;
$eventsCheckInFlags = $eventsCheckInFlags ?? [];
$eventsRsvpSummaries = $eventsRsvpSummaries ?? [];
$eventSlotsByEvent = $eventSlotsByEvent ?? [];
$mySlotAssignmentByEvent = $mySlotAssignmentByEvent ?? [];
$canPublishOperationalBoard = !empty($canPublishOperationalBoard);

$typeMeta = [
    'operation' => ['label' => 'Opération', 'badge' => 'is-rose'],
    'formation' => ['label' => 'Formation', 'badge' => 'is-sky'],
    'autre' => ['label' => 'Autre', 'badge' => 'is-violet'],
    'evenement' => ['label' => 'Événement', 'badge' => 'is-ok'],
];
$typeMetaFor = static function (string $t) use ($typeMeta): array {
    return $typeMeta[$t] ?? $typeMeta['evenement'];
};

$moisFr = ['', 'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
$formatWhen = static function (?string $iso) use ($moisFr): string {
    if (!$iso) {
        return '—';
    }
    try {
        $d = new DateTimeImmutable($iso);
    } catch (\Throwable) {
        return $iso;
    }

    return sprintf('%d %s %d · %02d:%02d', (int) $d->format('j'), $moisFr[(int) $d->format('n')], (int) $d->format('Y'), (int) $d->format('H'), (int) $d->format('i'));
};
$formatTime = static function (?string $iso): string {
    if (!$iso) {
        return '';
    }
    try {
        $d = new DateTimeImmutable($iso);
    } catch (\Throwable) {
        return '';
    }

    return $d->format('H:i');
};
/** @return array{label:string, badge:string} */
$dayBadge = static function (?string $iso): array {
    if (!$iso) {
        return ['label' => '—', 'badge' => 'is-muted'];
    }
    try {
        $d = (new DateTimeImmutable($iso))->setTime(0, 0, 0);
    } catch (\Throwable) {
        return ['label' => '—', 'badge' => 'is-muted'];
    }
    $today = (new DateTimeImmutable('today'));
    $diff = (int) round(($d->getTimestamp() - $today->getTimestamp()) / 86400);
    if ($diff < 0) {
        return ['label' => 'Passé', 'badge' => 'is-muted'];
    }
    if ($diff === 0) {
        return ['label' => 'Aujourd’hui', 'badge' => 'is-ok'];
    }
    if ($diff === 1) {
        return ['label' => 'Demain', 'badge' => 'is-watch'];
    }
    if ($diff <= 6) {
        return ['label' => 'Dans ' . $diff . ' jours', 'badge' => 'is-sky'];
    }

    return ['label' => 'Dans ' . $diff . ' jours', 'badge' => 'is-muted'];
};
/** @return array{label:string, badge:string} */
$rsvpMetaFor = static function (string $status): array {
    return match ($status) {
        'yes' => ['label' => 'Présent', 'badge' => 'is-ok'],
        'maybe' => ['label' => 'Peut-être', 'badge' => 'is-watch'],
        'no' => ['label' => 'Absent', 'badge' => 'is-rose'],
        default => ['label' => 'À confirmer', 'badge' => 'is-muted'],
    };
};

$eventCount = count($events);
?>
<style>
    .events-sheets {
        border: 1px solid #cbd5e1;
        background: #ffffff;
        overflow: auto;
        max-height: min(74vh, 56rem);
        width: 100%;
    }
    .events-sheets__table {
        width: 100%;
        min-width: 62rem;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 0.8125rem;
        line-height: 1.4;
    }
    .events-sheets__table th,
    .events-sheets__table td {
        border-right: 1px solid #e2e8f0;
        border-bottom: 1px solid #e2e8f0;
        padding: 0.6rem 0.75rem;
        vertical-align: top;
    }
    .events-sheets__table th:last-child,
    .events-sheets__table td:last-child {
        border-right: 0;
    }
    .events-sheets__table thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: #ecfdf5;
        color: #065f46;
        font-size: 0.625rem;
        font-weight: 800;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        white-space: nowrap;
        border-bottom: 1px solid #059669;
        box-shadow: 0 1px 0 #059669;
    }
    .events-sheets__table tbody tr:nth-child(even) td {
        background: #f8fafc;
    }
    .events-sheets__table tbody tr:hover td {
        background: #ecfdf5;
    }
    .events-sheets__table tbody tr.hidden {
        display: none;
    }
    .events-sheets__badge {
        display: inline-flex;
        align-items: center;
        border-radius: 0.25rem;
        border: 1px solid transparent;
        padding: 0.1rem 0.45rem;
        font-size: 0.6875rem;
        font-weight: 800;
        letter-spacing: 0.02em;
        white-space: nowrap;
    }
    .events-sheets__badge.is-ok { background: #ecfdf5; border-color: #a7f3d0; color: #065f46; }
    .events-sheets__badge.is-watch { background: #fffbeb; border-color: #fde68a; color: #92400e; }
    .events-sheets__badge.is-sky { background: #eff6ff; border-color: #bfdbfe; color: #1d4ed8; }
    .events-sheets__badge.is-rose { background: #fff1f2; border-color: #fecdd3; color: #9f1239; }
    .events-sheets__badge.is-violet { background: #f5f3ff; border-color: #ddd6fe; color: #5b21b6; }
    .events-sheets__badge.is-muted { background: #f1f5f9; border-color: #e2e8f0; color: #64748b; }
    .events-sheets__select {
        height: 1.65rem;
        border-radius: 0.3rem;
        border: 1px solid #cbd5e1;
        background: #ffffff;
        padding: 0 0.35rem;
        font-size: 0.6875rem;
        color: #0f172a;
    }
    .events-sheets__btn {
        display: inline-flex;
        align-items: center;
        height: 1.65rem;
        padding: 0 0.55rem;
        border-radius: 0.3rem;
        border: 1px solid #059669;
        background: #059669;
        color: #fff;
        font-size: 0.6875rem;
        font-weight: 800;
        cursor: pointer;
        white-space: nowrap;
    }
    .events-sheets__btn:hover { background: #047857; }
    .events-sheets__actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.3rem;
    }
    .events-sheets__actions form { display: inline; margin: 0; }
    .events-sheets__actions button,
    .events-sheets__actions a {
        display: inline-flex;
        align-items: center;
        height: 1.65rem;
        padding: 0 0.55rem;
        border-radius: 0.3rem;
        border: 1px solid #cbd5e1;
        background: #fff;
        color: #0f172a;
        font-size: 0.6875rem;
        font-weight: 700;
        text-decoration: none;
        white-space: nowrap;
        cursor: pointer;
    }
    .events-sheets__actions button:hover,
    .events-sheets__actions a:hover { background: #f8fafc; border-color: #94a3b8; }
    .events-sheets__actions button.is-primary { border-color: #059669; background: #ecfdf5; color: #065f46; }
    .events-sheets__actions button.is-primary:hover { background: #d1fae5; }
    .events-sheets__chip {
        display: inline-flex;
        align-items: center;
        height: 1.85rem;
        padding: 0 0.7rem;
        border-radius: 999px;
        border: 1px solid #cbd5e1;
        background: #fff;
        color: #475569;
        font-size: 0.75rem;
        font-weight: 700;
        cursor: pointer;
        white-space: nowrap;
    }
    .events-sheets__chip:hover { background: #f8fafc; }
    .events-sheets__chip.is-active { border-color: #059669; background: #059669; color: #fff; }
    .events-sheets__detail-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        margin-top: 0.45rem;
        border: 0;
        background: transparent;
        padding: 0;
        font-size: 0.6875rem;
        font-weight: 800;
        color: #047857;
        cursor: pointer;
    }
    .events-sheets__detail-btn:hover { text-decoration: underline; }
    .events-sheets__detail-row td {
        background: #f8fafc !important;
        padding: 0 !important;
        border-bottom: 1px solid #cbd5e1;
    }
    .events-detail {
        display: grid;
        gap: 1rem;
        padding: 1rem 1.1rem 1.25rem;
    }
    @media (min-width: 900px) {
        .events-detail { grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr); }
    }
    .events-detail__cover {
        width: 100%;
        max-height: 14rem;
        object-fit: cover;
        border-radius: 0.75rem;
        border: 1px solid #e2e8f0;
    }
    .events-detail__section h3 {
        margin: 0 0 0.45rem;
        font-size: 0.6875rem;
        font-weight: 800;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: #64748b;
    }
    .events-detail__body {
        margin: 0;
        font-size: 0.8125rem;
        line-height: 1.55;
        color: #334155;
        white-space: pre-wrap;
    }
    .events-detail__tags {
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem;
        margin: 0 0 0.75rem;
    }
    .events-detail__tag {
        display: inline-flex;
        border-radius: 999px;
        border: 1px solid #bbf7d0;
        background: #ecfdf5;
        color: #065f46;
        padding: 0.15rem 0.55rem;
        font-size: 0.6875rem;
        font-weight: 800;
    }
    .events-detail__timeline {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: 0.4rem;
    }
    .events-detail__phase {
        display: grid;
        grid-template-columns: 0.65rem minmax(0, 1fr) auto;
        gap: 0.55rem;
        align-items: start;
        border-radius: 0.55rem;
        border: 1px solid #e2e8f0;
        background: #fff;
        padding: 0.5rem 0.65rem;
    }
    .events-detail__dot {
        width: 0.65rem;
        height: 0.65rem;
        border-radius: 999px;
        margin-top: 0.3rem;
        background: #94a3b8;
    }
    .events-detail__dot.is-red { background: #ef4444; }
    .events-detail__dot.is-orange { background: #f97316; }
    .events-detail__dot.is-yellow { background: #eab308; }
    .events-detail__dot.is-green { background: #22c55e; }
    .events-detail__dot.is-black { background: #0f172a; }
    .events-detail__dot.is-white { background: #f8fafc; box-shadow: inset 0 0 0 1px #94a3b8; }
    .events-detail__dot.is-gray { background: #94a3b8; }
    .events-detail__phase-label {
        margin: 0;
        font-size: 0.8125rem;
        font-weight: 700;
        color: #0f172a;
    }
    .events-detail__phase-time {
        margin: 0;
        font-size: 0.75rem;
        font-weight: 700;
        color: #047857;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
    .events-detail__section-title {
        margin: 0.55rem 0 0.25rem;
        font-size: 0.75rem;
        font-weight: 800;
        color: #475569;
        border-top: 1px dashed #cbd5e1;
        padding-top: 0.55rem;
    }
    .events-detail__rsvp {
        display: grid;
        gap: 0.65rem;
    }
    @media (min-width: 640px) {
        .events-detail__rsvp { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }
    .events-detail__rsvp-col {
        border-radius: 0.65rem;
        border: 1px solid #e2e8f0;
        background: #fff;
        padding: 0.65rem 0.75rem;
    }
    .events-detail__rsvp-col h4 {
        margin: 0 0 0.4rem;
        font-size: 0.6875rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }
    .events-detail__rsvp-col.is-yes h4 { color: #065f46; }
    .events-detail__rsvp-col.is-maybe h4 { color: #92400e; }
    .events-detail__rsvp-col.is-no h4 { color: #9f1239; }
    .events-detail__rsvp-col ul {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }
    .events-detail__rsvp-col li {
        font-size: 0.75rem;
        color: #334155;
    }
    .events-detail__rsvp-col .muted { color: #94a3b8; font-size: 0.75rem; }
</style>
<div class="bg-slate-50 pb-16 sm:pb-24">
    <div class="relative overflow-hidden border-b border-slate-800/80 bg-gradient-to-br from-slate-900 via-emerald-950 to-slate-900 text-white">
        <div class="relative mx-auto max-w-[1600px] px-4 pt-10 pb-12 sm:px-6 sm:pt-14 sm:pb-16 lg:px-8">
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-emerald-300/95">Agenda</p>
            <h1 class="mt-2 text-3xl font-black tracking-tight sm:text-4xl">Événements &amp; opérations</h1>
            <p class="mt-4 max-w-2xl text-sm leading-relaxed text-slate-300">
                <a href="<?= htmlspecialchars(url('pointage')) ?>" class="font-semibold text-emerald-300 underline-offset-2 hover:underline">Ouvrir le pointage complet</a>
                — confirmation de participation, pointage le jour J et historique.
            </p>
            <?php if (!empty($calendar_subscription_url)): ?>
            <div class="mt-4 max-w-xl">
                <span class="block text-xs text-slate-400">Abonnement calendrier (lecture seule, lien personnel) :</span>
                <input type="text" readonly class="mt-2 w-full rounded-xl border border-white/15 bg-white/10 px-3 py-2 text-xs text-white" value="<?= htmlspecialchars((string) $calendar_subscription_url, ENT_QUOTES, 'UTF-8') ?>" onclick="this.select();">
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="mx-auto max-w-[1600px] space-y-6 px-4 py-10 sm:px-6 lg:px-8">
        <?php
        $quotaBanner = $eventsQuota ?? null;
        $quotaCanProceed = true;
        $variant = 'light';
        $quotaFromKey = 'events';
        require __DIR__ . '/../partials/quota_limited_banner.php';
        ?>
        <?php $s = \App\Core\Session::getFlash('success'); $e = \App\Core\Session::getFlash('error'); ?>
        <?php if ($s): ?><p class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"><?= htmlspecialchars($s) ?></p><?php endif; ?>
        <?php if ($e): ?><p class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900"><?= htmlspecialchars($e) ?></p><?php endif; ?>

        <div class="w-full max-w-none overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm" data-events-sheets>
            <div class="flex flex-col gap-4 border-b border-slate-200 bg-slate-50 px-4 py-4 sm:flex-row sm:items-end sm:justify-between sm:px-6">
                <div class="min-w-0">
                    <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-slate-500">Calendrier communautaire</p>
                    <h2 class="mt-0.5 text-lg font-black tracking-tight text-slate-900 sm:text-xl">Prochains rendez-vous</h2>
                    <p class="mt-1 text-xs text-slate-600">
                        <?= $eventCount === 0
                            ? 'Aucun événement à venir pour le moment.'
                            : ($eventCount === 1 ? '1 événement à venir.' : $eventCount . ' événements à venir.') ?>
                    </p>
                </div>
                <?php if ($eventCount > 0): ?>
                <div class="flex flex-wrap items-center gap-2">
                    <label class="sr-only" for="events-sheets-search">Rechercher un événement</label>
                    <input type="search" id="events-sheets-search" data-events-search placeholder="Rechercher un titre, un lieu…" class="h-9 w-full min-w-[12rem] rounded-lg border border-slate-300 bg-white px-3 text-xs text-slate-900 placeholder:text-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 sm:w-56">
                    <div class="flex flex-wrap gap-1.5" role="group" aria-label="Filtrer par type">
                        <button type="button" class="events-sheets__chip is-active" data-events-type-filter="all">Tous</button>
                        <?php foreach ($typeMeta as $tKey => $tMetaOption): ?>
                        <button type="button" class="events-sheets__chip" data-events-type-filter="<?= htmlspecialchars($tKey) ?>"><?= htmlspecialchars($tMetaOption['label']) ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($eventCount === 0): ?>
            <div class="px-6 py-14 text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-500" aria-hidden="true">
                    <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5a2.25 2.25 0 0 0 2.25-2.25m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5a2.25 2.25 0 0 1 2.25 2.25v7.5" /></svg>
                </div>
                <p class="mt-4 text-sm font-semibold text-slate-800">Aucun événement à venir</p>
                <p class="mt-2 text-sm text-slate-600">Revenez plus tard : les prochaines opérations, formations et rendez-vous s’afficheront ici.</p>
            </div>
            <?php else: ?>
            <div class="events-sheets" role="region" aria-label="Liste des événements à venir">
                <table class="events-sheets__table text-left">
                    <thead>
                        <tr>
                            <th>Événement</th>
                            <th>Quand</th>
                            <th>Lieu</th>
                            <th>Ma participation</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $ev):
                            $eid = (int) ($ev['id'] ?? 0);
                            $etype = (string) ($ev['event_type'] ?? 'evenement');
                            $tMeta = $typeMetaFor($etype);
                            $rsvp = $ev['rsvp_status'] ?? null;
                            $cur = is_string($rsvp) ? $rsvp : '';
                            $title = (string) ($ev['title'] ?? '');
                            $location = trim((string) ($ev['location'] ?? ''));
                            $desc = trim((string) ($ev['description'] ?? ''));
                            $descPreview = $desc !== '' ? (mb_strlen($desc) > 110 ? mb_substr($desc, 0, 110) . '…' : $desc) : '';
                            $when = $dayBadge(is_string($ev['starts_at'] ?? null) ? $ev['starts_at'] : null);
                            $rsvpBadge = $rsvpMetaFor($cur);
                            $tags = CommunityEventDetails::decodeTags($ev['tags_json'] ?? null);
                            $schedule = CommunityEventDetails::decodeSchedule($ev['schedule_json'] ?? null);
                            $cg = trim((string) ($ev['conditions_general'] ?? ''));
                            $cs = trim((string) ($ev['conditions_special'] ?? ''));
                            $coverUrl = CommunityEventDetails::publicCoverUrl(isset($ev['cover_image_path']) ? (string) $ev['cover_image_path'] : null);
                            $summary = $eventsRsvpSummaries[$eid] ?? ['yes' => [], 'maybe' => [], 'no' => []];
                            $hasDetails = $desc !== '' || $cg !== '' || $cs !== '' || $schedule !== [] || $tags !== [] || $coverUrl !== null
                                || $summary['yes'] !== [] || $summary['maybe'] !== [] || $summary['no'] !== []
                                || !empty($eventSlotsByEvent[$eid]);
                            $searchHay = mb_strtolower($title . ' ' . $location . ' ' . implode(' ', array_map([CommunityEventDetails::class, 'tagLabel'], $tags)));
                            ?>
                        <tr data-event-row data-event-type="<?= htmlspecialchars($etype, ENT_QUOTES, 'UTF-8') ?>" data-event-search="<?= htmlspecialchars($searchHay, ENT_QUOTES, 'UTF-8') ?>" data-event-id="<?= $eid ?>">
                            <td class="max-w-sm">
                                <span class="events-sheets__badge <?= $tMeta['badge'] ?>"><?= htmlspecialchars($tMeta['label']) ?></span>
                                <div class="mt-1 font-semibold text-slate-900"><?= htmlspecialchars($title) ?></div>
                                <?php if ($tags !== []): ?>
                                <div class="mt-1 flex flex-wrap gap-1">
                                    <?php foreach ($tags as $tagCode): ?>
                                        <span class="events-sheets__badge is-ok"><?= htmlspecialchars(CommunityEventDetails::tagLabel($tagCode)) ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                                <?php if ($descPreview !== ''): ?>
                                <div class="mt-0.5 text-xs text-slate-500"><?= htmlspecialchars($descPreview) ?></div>
                                <?php endif; ?>
                                <?php if ($hasDetails): ?>
                                <button type="button" class="events-sheets__detail-btn" data-events-toggle-detail="<?= $eid ?>" aria-expanded="false">
                                    Voir le détail
                                </button>
                                <?php endif; ?>
                            </td>
                            <td class="whitespace-nowrap">
                                <span class="events-sheets__badge <?= $when['badge'] ?>"><?= htmlspecialchars($when['label']) ?></span>
                                <div class="mt-1 text-slate-700"><?= htmlspecialchars($formatWhen(is_string($ev['starts_at'] ?? null) ? $ev['starts_at'] : null)) ?></div>
                                <?php if (!empty($ev['ends_at']) && is_string($ev['ends_at'])): ?>
                                <div class="text-slate-400">→ <?= htmlspecialchars($formatTime($ev['ends_at'])) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="max-w-[12rem]"><?= $location !== '' ? htmlspecialchars($location) : '—' ?></td>
                            <td>
                                <?php if ($currentUserId): ?>
                                <span class="events-sheets__badge <?= $rsvpBadge['badge'] ?>"><?= htmlspecialchars($rsvpBadge['label']) ?></span>
                                <form method="post" action="<?= url('evenements/rsvp') ?>" class="mt-1.5" data-rsvp-form>
                                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                                    <input type="hidden" name="event_id" value="<?= $eid ?>">
                                    <div class="flex flex-wrap items-center gap-1">
                                        <select name="status" data-rsvp-status-select class="events-sheets__select">
                                            <?php foreach (['yes' => 'Présent', 'maybe' => 'Peut-être', 'no' => 'Absent'] as $val => $lab): ?>
                                                <option value="<?= $val ?>" <?= $cur === $val ? ' selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <span data-rsvp-reason-wrap class="<?= $cur === 'no' ? '' : 'hidden' ?>">
                                            <select name="absence_reason" class="events-sheets__select">
                                                <option value="">Motif d’absence</option>
                                                <option value="service">Service</option>
                                                <option value="sante">Santé</option>
                                                <option value="indisponibilite_planifiee">Indispo planifiée</option>
                                                <option value="absence_non_justifiee">Absence non justifiée</option>
                                                <option value="autre">Autre</option>
                                            </select>
                                        </span>
                                        <button type="submit" class="events-sheets__btn">OK</button>
                                    </div>
                                </form>
                                <?php else: ?>
                                <span class="text-xs text-slate-400">Connexion requise</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="events-sheets__actions" role="group" aria-label="Actions pour cet événement">
                                    <?php if ($currentUserId && !empty($eventsCheckInFlags[$eid])): ?>
                                    <form method="post" action="<?= url('pointage/check-in') ?>">
                                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                                        <input type="hidden" name="event_id" value="<?= $eid ?>">
                                        <button type="submit" class="is-primary">Pointer ma présence</button>
                                    </form>
                                    <?php endif; ?>
                                    <?php if ($canPublishOperationalBoard):
                                        $opBoardPublishSourceType = 'event';
                                        $opBoardPublishSourceId = $eid;
                                        $opBoardPublishCsrf = \App\Core\Csrf::token();
                                        $opBoardPublishVariant = 'sheets';
                                        require base_path('views/partials/operational_board_publish_linked_form.php');
                                    endif; ?>
                                    <?php if (empty($eventsCheckInFlags[$eid]) && !$canPublishOperationalBoard): ?>
                                    <span class="text-xs text-slate-400">—</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php if ($hasDetails): ?>
                        <tr class="events-sheets__detail-row hidden" data-event-detail="<?= $eid ?>" data-event-type="<?= htmlspecialchars($etype, ENT_QUOTES, 'UTF-8') ?>" data-event-search="<?= htmlspecialchars($searchHay, ENT_QUOTES, 'UTF-8') ?>">
                            <td colspan="5">
                                <div class="events-detail">
                                    <div>
                                        <?php if ($coverUrl): ?>
                                            <img class="events-detail__cover" src="<?= htmlspecialchars($coverUrl, ENT_QUOTES, 'UTF-8') ?>" alt="">
                                        <?php endif; ?>
                                        <?php if ($tags !== []): ?>
                                            <div class="events-detail__tags" <?= $coverUrl ? 'style="margin-top:0.75rem"' : '' ?>>
                                                <?php foreach ($tags as $tagCode): ?>
                                                    <span class="events-detail__tag"><?= htmlspecialchars(CommunityEventDetails::tagLabel($tagCode)) ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($desc !== ''): ?>
                                            <div class="events-detail__section">
                                                <h3>Description</h3>
                                                <p class="events-detail__body"><?= htmlspecialchars($desc) ?></p>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($cg !== ''): ?>
                                            <div class="events-detail__section" style="margin-top:0.85rem">
                                                <h3>Conditions générales</h3>
                                                <p class="events-detail__body"><?= htmlspecialchars($cg) ?></p>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($cs !== ''): ?>
                                            <div class="events-detail__section" style="margin-top:0.85rem">
                                                <h3>Conditions particulières</h3>
                                                <p class="events-detail__body"><?= htmlspecialchars($cs) ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <?php if ($schedule !== []): ?>
                                            <div class="events-detail__section">
                                                <h3>Déroulement</h3>
                                                <ul class="events-detail__timeline">
                                                    <?php foreach ($schedule as $phase): ?>
                                                        <?php if ($phase['type'] === 'section'): ?>
                                                            <li class="events-detail__section-title"><?= htmlspecialchars($phase['label']) ?></li>
                                                        <?php else: ?>
                                                            <li class="events-detail__phase">
                                                                <span class="events-detail__dot is-<?= htmlspecialchars((string) $phase['tone']) ?>" aria-hidden="true"></span>
                                                                <p class="events-detail__phase-label"><?= htmlspecialchars($phase['label']) ?></p>
                                                                <?php if (!empty($phase['time'])): ?>
                                                                    <p class="events-detail__phase-time"><?= htmlspecialchars((string) $phase['time']) ?></p>
                                                                <?php else: ?>
                                                                    <span></span>
                                                                <?php endif; ?>
                                                            </li>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                        <div class="events-detail__section" style="<?= $schedule !== [] ? 'margin-top:1rem' : '' ?>">
                                            <h3>Participations</h3>
                                            <div class="events-detail__rsvp">
                                                <?php
                                                $rsvpCols = [
                                                    'yes' => ['Présents', 'is-yes'],
                                                    'maybe' => ['Peut-être', 'is-maybe'],
                                                    'no' => ['Absents', 'is-no'],
                                                ];
                                                foreach ($rsvpCols as $stKey => [$stLab, $stClass]):
                                                    $people = $summary[$stKey] ?? [];
                                                    ?>
                                                    <div class="events-detail__rsvp-col <?= $stClass ?>">
                                                        <h4><?= htmlspecialchars($stLab) ?> (<?= count($people) ?>)</h4>
                                                        <?php if ($people === []): ?>
                                                            <p class="muted">Personne pour l’instant</p>
                                                        <?php else: ?>
                                                            <ul>
                                                                <?php foreach ($people as $p):
                                                                    $dn = trim((string) ($p['display_name'] ?? ''));
                                                                    $csign = trim((string) ($p['callsign'] ?? ''));
                                                                    $line = $csign !== '' ? ($csign . ($dn !== '' ? ' · ' . $dn : '')) : $dn;
                                                                    if ($line === '') {
                                                                        continue;
                                                                    }
                                                                    ?>
                                                                    <li><?= htmlspecialchars($line) ?></li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <?php
                                        $slotsForEvent = $eventSlotsByEvent[$eid] ?? [];
                                        $mySlotAssignment = $mySlotAssignmentByEvent[$eid] ?? null;
                                        ?>
                                        <?php if ($slotsForEvent !== []): ?>
                                        <div class="events-detail__section" style="margin-top:1rem">
                                            <h3>Postes de mission</h3>
                                            <?php if ($mySlotAssignment && $currentUserId): ?>
                                            <p class="muted">
                                                Vous êtes <?= (string) ($mySlotAssignment['status'] ?? '') === 'waitlisted' ? 'en liste d’attente sur un poste' : 'inscrit sur un poste' ?> pour cet événement.
                                                <form method="post" action="<?= url('evenements/' . $eid . '/slots/desinscription') ?>" style="display:inline">
                                                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                                                    <button type="submit" class="events-sheets__btn">Me désinscrire</button>
                                                </form>
                                            </p>
                                            <?php endif; ?>
                                            <ul class="events-detail__timeline">
                                                <?php foreach ($slotsForEvent as $slot):
                                                    $sid = (int) ($slot['id'] ?? 0);
                                                    $capacity = (int) ($slot['capacity'] ?? 1);
                                                    $confirmedN = (int) ($slot['confirmed_count'] ?? 0);
                                                    $full = $confirmedN >= $capacity;
                                                    $mine = $mySlotAssignment && (int) ($mySlotAssignment['slot_id'] ?? 0) === $sid;
                                                    $unitName = trim((string) ($slot['unit_name'] ?? ''));
                                                    ?>
                                                    <li class="events-detail__phase">
                                                        <span class="events-detail__dot is-<?= $full && !$mine ? 'no' : 'ok' ?>" aria-hidden="true"></span>
                                                        <p class="events-detail__phase-label">
                                                            <?= htmlspecialchars((string) ($slot['label'] ?? '')) ?>
                                                            <?php if ($unitName !== ''): ?> · <?= htmlspecialchars($unitName) ?><?php endif; ?>
                                                            — <?= $confirmedN ?>/<?= $capacity ?><?= $mine ? ' · vous' : '' ?>
                                                        </p>
                                                        <?php if ($currentUserId && !$mySlotAssignment): ?>
                                                        <form method="post" action="<?= url('evenements/' . $eid . '/slots/' . $sid . '/inscription') ?>">
                                                            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                                                            <button type="submit" class="events-sheets__btn"><?= $full ? 'Liste d’attente' : 'S’inscrire' ?></button>
                                                        </form>
                                                        <?php else: ?>
                                                        <span></span>
                                                        <?php endif; ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="hidden px-4 py-8 text-center text-sm text-slate-500 sm:px-6" data-events-empty-filtered>
                Aucun événement ne correspond à votre recherche.
                <button type="button" class="font-semibold text-emerald-700 underline" data-events-reset>Réinitialiser les filtres</button>
            </p>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
(function () {
    var root = document.querySelector('[data-events-sheets]');
    if (!root) {
        return;
    }
    var rows = Array.prototype.slice.call(root.querySelectorAll('[data-event-row]'));
    var detailRows = Array.prototype.slice.call(root.querySelectorAll('[data-event-detail]'));
    var searchInput = root.querySelector('[data-events-search]');
    var typeChips = Array.prototype.slice.call(root.querySelectorAll('[data-events-type-filter]'));
    var emptyFiltered = root.querySelector('[data-events-empty-filtered]');
    var resetBtn = root.querySelector('[data-events-reset]');
    var activeType = 'all';
    var openDetails = {};

    function applyFilters() {
        var q = (searchInput ? searchInput.value : '').trim().toLowerCase();
        var visible = 0;
        rows.forEach(function (row) {
            var matchesType = activeType === 'all' || row.getAttribute('data-event-type') === activeType;
            var haystack = row.getAttribute('data-event-search') || '';
            var matchesQuery = q === '' || haystack.indexOf(q) !== -1;
            var show = matchesType && matchesQuery;
            row.classList.toggle('hidden', !show);
            if (show) {
                visible += 1;
            }
            var id = row.getAttribute('data-event-id');
            var detail = id ? root.querySelector('[data-event-detail="' + id + '"]') : null;
            if (detail) {
                var detailOpen = !!openDetails[id];
                detail.classList.toggle('hidden', !(show && detailOpen));
            }
        });
        if (emptyFiltered) {
            emptyFiltered.classList.toggle('hidden', visible !== 0);
        }
    }

    root.querySelectorAll('[data-events-toggle-detail]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-events-toggle-detail');
            if (!id) return;
            openDetails[id] = !openDetails[id];
            btn.setAttribute('aria-expanded', openDetails[id] ? 'true' : 'false');
            btn.textContent = openDetails[id] ? 'Masquer le détail' : 'Voir le détail';
            applyFilters();
        });
    });

    typeChips.forEach(function (chip) {
        chip.addEventListener('click', function () {
            activeType = chip.getAttribute('data-events-type-filter') || 'all';
            typeChips.forEach(function (c) {
                c.classList.toggle('is-active', c === chip);
            });
            applyFilters();
        });
    });

    if (searchInput) {
        searchInput.addEventListener('input', applyFilters);
    }

    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            if (searchInput) {
                searchInput.value = '';
            }
            activeType = 'all';
            typeChips.forEach(function (c) {
                c.classList.toggle('is-active', c.getAttribute('data-events-type-filter') === 'all');
            });
            applyFilters();
        });
    }

    root.querySelectorAll('[data-rsvp-status-select]').forEach(function (sel) {
        sel.addEventListener('change', function () {
            var form = sel.closest('form');
            var wrap = form ? form.querySelector('[data-rsvp-reason-wrap]') : null;
            if (wrap) {
                wrap.classList.toggle('hidden', sel.value !== 'no');
            }
        });
    });
})();
</script>
