<?php
/** @var list<array<string, mixed>> $events */
/** @var int|null $currentUserId */
/** @var array<string, mixed>|null $eventsQuota */
/** @var array<int, bool> $eventsCheckInFlags */

$eventsQuota = $eventsQuota ?? null;
$eventsCheckInFlags = $eventsCheckInFlags ?? [];
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
                            $searchHay = mb_strtolower($title . ' ' . $location);
                            ?>
                        <tr data-event-row data-event-type="<?= htmlspecialchars($etype, ENT_QUOTES, 'UTF-8') ?>" data-event-search="<?= htmlspecialchars($searchHay, ENT_QUOTES, 'UTF-8') ?>">
                            <td class="max-w-sm">
                                <span class="events-sheets__badge <?= $tMeta['badge'] ?>"><?= htmlspecialchars($tMeta['label']) ?></span>
                                <div class="mt-1 font-semibold text-slate-900"><?= htmlspecialchars($title) ?></div>
                                <?php if ($descPreview !== ''): ?>
                                <div class="mt-0.5 text-xs text-slate-500" title="<?= htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($descPreview) ?></div>
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
    var searchInput = root.querySelector('[data-events-search]');
    var typeChips = Array.prototype.slice.call(root.querySelectorAll('[data-events-type-filter]'));
    var emptyFiltered = root.querySelector('[data-events-empty-filtered]');
    var resetBtn = root.querySelector('[data-events-reset]');
    var activeType = 'all';

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
        });
        if (emptyFiltered) {
            emptyFiltered.classList.toggle('hidden', visible !== 0);
        }
    }

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
