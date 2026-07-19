<?php
/** @var list<array<string, mixed>> $pointageUpcoming */
/** @var list<array<string, mixed>> $pointageToday */
/** @var list<array<string, mixed>> $pointagePast */
/** @var array<int, bool> $pointageCheckInFlags */
/** @var array<int, list<array<string, mixed>>> $pointageRsvpHistoryByEvent */
/** @var string $pointageTypeFilter */
/** @var int $currentUserId */
/** @var array<string, mixed>|null $eventsQuota */

$pointageUpcoming = $pointageUpcoming ?? [];
$pointageToday = $pointageToday ?? [];
$pointagePast = $pointagePast ?? [];
$pointageCheckInFlags = $pointageCheckInFlags ?? [];
$pointageRsvpHistoryByEvent = $pointageRsvpHistoryByEvent ?? [];
$pointageTypeFilter = $pointageTypeFilter ?? '';
$eventsQuota = $eventsQuota ?? null;

$typeLabel = static function (string $t): string {
    return match ($t) {
        'operation' => 'Opération',
        'formation' => 'Formation',
        'autre' => 'Autre',
        default => 'Événement',
    };
};

$rsvpLabel = static function (?string $s): string {
    return match ($s) {
        'yes' => 'Présent',
        'maybe' => 'Peut-être',
        'no' => 'Absent',
        default => '—',
    };
};

$absenceLabel = static function (?string $s): string {
    return match ($s) {
        'service' => 'Service',
        'sante' => 'Santé',
        'indisponibilite_planifiee' => 'Indisponibilité planifiée',
        'absence_non_justifiee' => 'Absence non justifiée',
        'autre' => 'Autre',
        default => '',
    };
};

$historyLine = static function (array $h) use ($rsvpLabel, $absenceLabel): string {
    $action = (string) ($h['action'] ?? '');
    $from = $rsvpLabel(isset($h['status_from']) ? (string) $h['status_from'] : null);
    $to = $rsvpLabel(isset($h['status_to']) ? (string) $h['status_to'] : null);
    $reason = $absenceLabel(isset($h['absence_reason']) ? (string) $h['absence_reason'] : null);

    return match ($action) {
        'check_in' => 'Présence enregistrée sur place',
        'check_in_clear' => 'Pointage retiré',
        'rsvp_remove' => 'Participation retirée' . ($from !== '—' ? ' (était : ' . $from . ')' : ''),
        default => ($from !== '—' && $from !== $to
            ? 'Participation : ' . $from . ' → ' . $to
            : 'Participation : ' . $to)
            . ($reason !== '' ? ' · Motif : ' . $reason : ''),
    };
};

$formatSlot = static function (?string $sqlDatetime): string {
    if ($sqlDatetime === null) {
        return '—';
    }
    $raw = trim((string) $sqlDatetime);
    if ($raw === '') {
        return '—';
    }
    $t = strtotime($raw);
    if ($t === false) {
        return htmlspecialchars($raw, ENT_QUOTES, 'UTF-8');
    }

    return htmlspecialchars(date('d/m/Y \à H:i', $t), ENT_QUOTES, 'UTF-8');
};

$typePillClass = static function (string $t): string {
    return match ($t) {
        'operation' => 'bg-rose-500/15 text-rose-950 ring-rose-200/60',
        'formation' => 'bg-sky-500/15 text-sky-950 ring-sky-200/60',
        'autre' => 'bg-violet-500/15 text-violet-950 ring-violet-200/60',
        default => 'bg-emerald-500/15 text-emerald-950 ring-emerald-200/60',
    };
};

$filterOptions = [
    '' => 'Tout',
    'operation' => 'Opérations',
    'evenement' => 'Événements',
    'formation' => 'Formations',
    'autre' => 'Autre',
];
?>
<style>
.pointage-card { border-radius: 1rem; border: 1px solid #e2e8f0; background: #fff; box-shadow: 0 1px 2px rgba(15,23,42,.04); }
.pointage-card[open] { border-color: #a7f3d0; box-shadow: 0 0 0 1px rgba(16,185,129,.12); }
.pointage-card__summary {
  display: flex; flex-direction: column; gap: 1rem; padding: 1.15rem 1.25rem;
  cursor: pointer; list-style: none; user-select: none;
}
.pointage-card__summary::-webkit-details-marker { display: none; }
.pointage-card__summary::marker { content: ""; }
@media (min-width: 1024px) {
  .pointage-card__summary { flex-direction: row; align-items: flex-start; justify-content: space-between; }
}
.pointage-card__meta {
  flex-shrink: 0; font-style: normal; font-weight: 800; color: #94a3b8; min-width: 1.1rem; text-align: center;
  align-self: flex-end;
}
.pointage-card[open] .pointage-card__meta { color: #059669; }
.pointage-card__body { padding: 0 1.25rem 1.25rem; border-top: 1px solid #f1f5f9; }
.pointage-card__history { margin-top: 1rem; border-radius: .75rem; border: 1px solid #e2e8f0; background: #f8fafc; padding: .85rem 1rem; }
.pointage-card__history h3 { margin: 0; font-size: .65rem; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; color: #64748b; }
.pointage-card__history ol { margin: .55rem 0 0; padding: 0; list-style: none; display: grid; gap: .45rem; }
.pointage-card__history li { display: flex; flex-direction: column; gap: .1rem; font-size: .8125rem; color: #334155; }
.pointage-card__history time { font-size: .7rem; color: #94a3b8; font-variant-numeric: tabular-nums; }
</style>
<div class="bg-slate-50 pb-16 sm:pb-24">
    <div class="relative overflow-hidden border-b border-slate-800/80 bg-gradient-to-br from-slate-900 via-emerald-950 to-slate-900 text-white">
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_20%_30%,rgba(52,211,153,0.14)_0,transparent_42%),radial-gradient(circle_at_88%_12%,rgba(167,139,250,0.1)_0,transparent_38%)]" aria-hidden="true"></div>
        <div class="pointer-events-none absolute inset-0 opacity-[0.35] bg-[length:22px_22px] bg-[radial-gradient(circle_at_1px_1px,rgba(255,255,255,0.06)_1px,transparent_0)]" aria-hidden="true"></div>
        <div class="relative mx-auto max-w-5xl px-4 pt-10 pb-12 sm:px-6 sm:pt-14 sm:pb-16 lg:px-8">
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-emerald-300/95">Présence</p>
            <h1 class="mt-2 text-3xl font-black tracking-tight sm:text-4xl">Pointage et agenda</h1>
            <p class="mt-4 max-w-2xl text-sm leading-relaxed text-slate-300 sm:text-base">
                Indiquez votre participation aux créneaux prévus, puis le jour même enregistrez votre présence sur place lorsque le bouton le permet — en général à partir de trente minutes avant le début.
            </p>
            <div class="mt-8 flex flex-wrap items-center gap-2">
                <span class="mr-1 text-xs font-semibold uppercase tracking-wider text-slate-400">Affichage</span>
                <nav class="inline-flex flex-wrap gap-1.5 rounded-2xl border border-white/10 bg-white/5 p-1.5 backdrop-blur-sm" aria-label="Filtrer les créneaux">
                    <?php foreach ($filterOptions as $k => $lab): ?>
                        <?php
                        $active = $pointageTypeFilter === $k;
                        $href = url('pointage') . ($k !== '' ? '?type=' . rawurlencode($k) : '');
                        ?>
                        <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"
                           class="rounded-xl px-3.5 py-2 text-xs font-bold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-300 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900 <?= $active ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-200 hover:bg-white/10' ?>">
                            <?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>
        </div>
    </div>

    <div class="mx-auto max-w-5xl space-y-10 px-4 -mt-6 sm:px-6 lg:px-8">
        <div class="relative z-[1] rounded-2xl border border-slate-200/90 bg-white p-5 shadow-lg shadow-slate-900/10 sm:p-7">
            <?php
            $quotaBanner = $eventsQuota;
            $quotaCanProceed = true;
            $variant = 'light';
            $quotaFromKey = 'events';
            require base_path('views/partials/quota_limited_banner.php');
            ?>

            <?php
            $fOk = \App\Core\Session::getFlash('success');
            $fErr = \App\Core\Session::getFlash('error');
            ?>
            <?php if ($fOk !== null && trim((string) $fOk) !== ''): ?>
                <?php $flash_variant = 'success'; $flash_message = (string) $fOk; $flash_margin_class = 'mb-0'; require base_path('views/partials/flash_message.php'); ?>
            <?php endif; ?>
            <?php if ($fErr !== null && trim((string) $fErr) !== ''): ?>
                <?php $flash_variant = 'error'; $flash_message = (string) $fErr; $flash_margin_class = ($fOk !== null && trim((string) $fOk) !== '') ? 'mt-4 mb-0' : 'mb-0'; require base_path('views/partials/flash_message.php'); ?>
            <?php endif; ?>
        </div>

        <?php
        $renderEventBlock = static function (array $ev, array $checkFlags, array $historyByEvent) use ($typeLabel, $rsvpLabel, $formatSlot, $typePillClass, $historyLine): void {
            $eid = (int) ($ev['id'] ?? 0);
            $etype = (string) ($ev['event_type'] ?? 'evenement');
            $rsvp = $ev['rsvp_status'] ?? null;
            $checked = $ev['rsvp_checked_in_at'] ?? null;
            $pill = $typePillClass($etype);
            $history = $historyByEvent[$eid] ?? [];
            $startOpen = !empty($checkFlags[$eid]);
            ?>
            <li>
                <details class="pointage-card group"<?= $startOpen ? ' open' : '' ?> data-pointage-card>
                    <summary class="pointage-card__summary">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider ring-1 <?= $pill ?>">
                                    <?= htmlspecialchars($typeLabel($etype), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>
                            <h2 class="mt-3 text-lg font-bold leading-snug text-slate-900 sm:text-xl"><?= htmlspecialchars((string) ($ev['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h2>
                            <p class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-slate-600">
                                <span class="inline-flex items-center gap-1.5 font-medium text-slate-800">
                                    <svg class="h-4 w-4 shrink-0 text-emerald-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5a2.25 2.25 0 002.25-2.25m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5a2.25 2.25 0 012.25 2.25v7.5"/></svg>
                                    <?= $formatSlot(isset($ev['starts_at']) ? (string) $ev['starts_at'] : null) ?>
                                </span>
                                <?php if (!empty($ev['location'])): ?>
                                    <span class="text-slate-400" aria-hidden="true">·</span>
                                    <span class="text-slate-600"><?= htmlspecialchars((string) $ev['location'], ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="flex items-start gap-3 shrink-0">
                            <div class="rounded-xl border border-slate-100 bg-slate-50/90 px-4 py-3 text-sm lg:min-w-[180px] lg:text-right">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Participation annoncée</p>
                                <p class="mt-1 font-bold text-slate-900"><?= htmlspecialchars($rsvpLabel(is_string($rsvp) ? $rsvp : null), ENT_QUOTES, 'UTF-8') ?></p>
                                <?php if (!empty($checked)): ?>
                                    <p class="mt-2 text-xs font-semibold text-emerald-700">Présence enregistrée</p>
                                <?php endif; ?>
                            </div>
                            <i class="pointage-card__meta" aria-hidden="true">−</i>
                        </div>
                    </summary>

                    <div class="pointage-card__body">
                        <?php if (!empty($ev['description'])): ?>
                            <div class="pt-4 text-sm leading-relaxed text-slate-600"><?= nl2br(htmlspecialchars((string) $ev['description'], ENT_QUOTES, 'UTF-8')) ?></div>
                        <?php endif; ?>

                        <div class="mt-5 flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between">
                            <form method="post" action="<?= url('pointage/rsvp') ?>" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-3">
                                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="event_id" value="<?= $eid ?>">
                                <label class="text-xs font-semibold text-slate-600 sm:sr-only" for="participation-<?= $eid ?>">Modifier ma participation</label>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="hidden text-xs font-semibold text-slate-600 sm:inline">Ma participation</span>
                                    <select id="participation-<?= $eid ?>" name="status" class="bo-select min-w-[11rem] rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/25">
                                        <?php
                                        $cur = is_string($rsvp) ? $rsvp : '';
                                        foreach (['yes' => 'Présent', 'maybe' => 'Peut-être', 'no' => 'Absent'] as $val => $lab):
                                        ?>
                                            <option value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>" <?= $cur === $val ? ' selected' : '' ?>><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <select name="absence_reason" class="bo-select min-w-[12rem] rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/25">
                                        <option value="">Motif d’absence</option>
                                        <option value="service">Service</option>
                                        <option value="sante">Santé</option>
                                        <option value="indisponibilite_planifiee">Indisponibilité planifiée</option>
                                        <option value="absence_non_justifiee">Absence non justifiée</option>
                                        <option value="autre">Autre</option>
                                    </select>
                                    <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2">
                                        Mettre à jour
                                    </button>
                                </div>
                            </form>
                            <?php if (!empty($checkFlags[$eid])): ?>
                                <form method="post" action="<?= url('pointage/check-in') ?>" class="sm:ml-auto">
                                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="event_id" value="<?= $eid ?>">
                                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-emerald-900/20 transition hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400 focus-visible:ring-offset-2 sm:w-auto">
                                        <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        Enregistrer ma présence sur place
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>

                        <div class="pointage-card__history">
                            <h3>Historique des changements</h3>
                            <?php if ($history === []): ?>
                                <p class="mt-2 text-sm text-slate-500">Aucun changement enregistré pour ce créneau.</p>
                            <?php else: ?>
                                <ol>
                                    <?php foreach ($history as $hRow): ?>
                                        <li>
                                            <span><?= htmlspecialchars($historyLine($hRow), ENT_QUOTES, 'UTF-8') ?></span>
                                            <time datetime="<?= htmlspecialchars((string) ($hRow['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                <?= $formatSlot(isset($hRow['created_at']) ? (string) $hRow['created_at'] : null) ?>
                                                <?php
                                                $actorName = trim((string) ($hRow['actor_display_name'] ?? ''));
                                                if ($actorName !== ''):
                                                ?>
                                                    · <?= htmlspecialchars($actorName, ENT_QUOTES, 'UTF-8') ?>
                                                <?php endif; ?>
                                            </time>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                            <?php endif; ?>
                        </div>
                    </div>
                </details>
            </li>
            <?php
        };
        ?>

        <?php if ($pointageToday !== []): ?>
            <section class="scroll-mt-6" aria-labelledby="pointage-aujourdhui">
                <div class="flex items-end justify-between gap-4 border-b border-slate-200/80 pb-3">
                    <h2 id="pointage-aujourdhui" class="text-base font-black uppercase tracking-[0.18em] text-slate-800">Aujourd’hui</h2>
                    <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-amber-900">Jour J</span>
                </div>
                <ul class="mt-5 space-y-5">
                    <?php foreach ($pointageToday as $ev): ?>
                        <?php $renderEventBlock($ev, $pointageCheckInFlags, $pointageRsvpHistoryByEvent); ?>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <section class="scroll-mt-6" aria-labelledby="pointage-a-venir">
            <h2 id="pointage-a-venir" class="border-b border-slate-200/80 pb-3 text-base font-black uppercase tracking-[0.18em] text-slate-800">À venir</h2>
            <ul class="mt-5 space-y-5">
                <?php if ($pointageUpcoming === []): ?>
                    <li class="rounded-2xl border border-dashed border-slate-200 bg-white px-6 py-14 text-center shadow-sm">
                        <p class="text-sm font-medium text-slate-700">Aucun créneau à venir<?= $pointageTypeFilter !== '' ? ' pour ce filtre' : '' ?>.</p>
                        <p class="mt-2 text-xs text-slate-500">Les nouvelles séances apparaîtront ici dès qu’elles seront publiées pour votre communauté.</p>
                    </li>
                <?php endif; ?>
                <?php foreach ($pointageUpcoming as $ev): ?>
                    <?php
                    $isToday = false;
                    foreach ($pointageToday as $t) {
                        if ((int) ($t['id'] ?? 0) === (int) ($ev['id'] ?? 0)) {
                            $isToday = true;
                            break;
                        }
                    }
                    if ($isToday) {
                        continue;
                    }
                    ?>
                    <?php $renderEventBlock($ev, $pointageCheckInFlags, $pointageRsvpHistoryByEvent); ?>
                <?php endforeach; ?>
            </ul>
        </section>

        <?php if ($pointagePast !== []): ?>
            <section class="scroll-mt-6" aria-labelledby="pointage-historique">
                <h2 id="pointage-historique" class="border-b border-slate-200/80 pb-3 text-base font-black uppercase tracking-[0.18em] text-slate-800">Historique récent</h2>
                <ul class="mt-5 space-y-3">
                    <?php foreach ($pointagePast as $ev): ?>
                        <?php
                        $pastType = (string) ($ev['event_type'] ?? 'evenement');
                        $pastPill = $typePillClass($pastType);
                        $didCheck = !empty($ev['rsvp_checked_in_at']);
                        $pastId = (int) ($ev['id'] ?? 0);
                        $pastHistory = $pointageRsvpHistoryByEvent[$pastId] ?? [];
                        ?>
                        <li>
                            <details class="pointage-card">
                                <summary class="pointage-card__summary" style="padding-top:1rem;padding-bottom:1rem">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-black uppercase tracking-wider ring-1 <?= $pastPill ?>"><?= htmlspecialchars($typeLabel($pastType), ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                        <p class="mt-1 font-semibold text-slate-900"><?= htmlspecialchars((string) ($ev['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                        <p class="mt-0.5 text-xs text-slate-500"><?= $formatSlot(isset($ev['starts_at']) ? (string) $ev['starts_at'] : null) ?></p>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <span class="inline-flex rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700">
                                            <?= htmlspecialchars($rsvpLabel(is_string($ev['rsvp_status'] ?? null) ? (string) $ev['rsvp_status'] : null), ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                        <?php if ($didCheck): ?>
                                            <span class="inline-flex items-center gap-1 rounded-lg bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-900 ring-1 ring-emerald-200/80">Présence enregistrée</span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-500">Pas de pointage</span>
                                        <?php endif; ?>
                                        <i class="pointage-card__meta" aria-hidden="true">—</i>
                                    </div>
                                </summary>
                                <div class="pointage-card__body">
                                    <div class="pointage-card__history" style="margin-top:1rem">
                                        <h3>Historique des changements</h3>
                                        <?php if ($pastHistory === []): ?>
                                            <p class="mt-2 text-sm text-slate-500">Aucun détail de changement pour ce créneau.</p>
                                        <?php else: ?>
                                            <ol>
                                                <?php foreach ($pastHistory as $hRow): ?>
                                                    <li>
                                                        <span><?= htmlspecialchars($historyLine($hRow), ENT_QUOTES, 'UTF-8') ?></span>
                                                        <time><?= $formatSlot(isset($hRow['created_at']) ? (string) $hRow['created_at'] : null) ?></time>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ol>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </details>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <section class="rounded-2xl border border-slate-200 bg-gradient-to-br from-white to-slate-50 p-6 shadow-sm" aria-labelledby="pointage-raccourcis">
            <h2 id="pointage-raccourcis" class="text-sm font-bold text-slate-900">Pour aller plus loin</h2>
            <p class="mt-1 text-xs text-slate-500">Accès rapides depuis l’espace présence.</p>
            <ul class="mt-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:gap-6">
                <li>
                    <a href="<?= url('evenements') ?>" class="inline-flex items-center gap-2 text-sm font-semibold text-emerald-800 underline decoration-emerald-200 underline-offset-4 transition hover:text-emerald-950">
                        Calendrier des événements
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                    </a>
                </li>
                <li>
                    <a href="<?= url('dashboard') ?>" class="inline-flex items-center gap-2 text-sm font-semibold text-slate-800 underline decoration-slate-300 underline-offset-4 transition hover:text-slate-950">
                        Tableau de bord
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                    </a>
                </li>
                <?php if (\can('admin.organization')): ?>
                    <li>
                        <a href="<?= url('back-office/events') ?>" class="inline-flex items-center gap-2 text-sm font-semibold text-violet-800 underline decoration-violet-200 underline-offset-4 transition hover:text-violet-950">
                            Gérer les créneaux (encadrement)
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </section>
    </div>
</div>
<script>
(function () {
  document.querySelectorAll('[data-pointage-card]').forEach(function (card) {
    var meta = card.querySelector('.pointage-card__meta');
    function sync() {
      if (meta) meta.textContent = card.open ? '−' : '—';
    }
    sync();
    card.addEventListener('toggle', sync);
  });
})();
</script>
