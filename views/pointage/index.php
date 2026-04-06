<?php
/** @var list<array<string, mixed>> $pointageUpcoming */
/** @var list<array<string, mixed>> $pointageToday */
/** @var list<array<string, mixed>> $pointagePast */
/** @var array<int, bool> $pointageCheckInFlags */
/** @var string $pointageTypeFilter */
/** @var int $currentUserId */
/** @var array<string, mixed>|null $eventsQuota */

$pointageUpcoming = $pointageUpcoming ?? [];
$pointageToday = $pointageToday ?? [];
$pointagePast = $pointagePast ?? [];
$pointageCheckInFlags = $pointageCheckInFlags ?? [];
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
?>
<div class="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8">
    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <p class="text-[11px] font-black uppercase tracking-[0.28em] text-emerald-700">Présence</p>
        <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-950 sm:text-3xl">Pointage &amp; agenda</h1>
        <p class="mt-3 max-w-2xl text-sm leading-relaxed text-slate-600">
            Inscrivez-vous aux opérations, événements et formations datés, puis le jour J enregistrez votre présence réelle
            (fenêtre ouverte à partir de 30&nbsp;min avant le début).
        </p>
        <div class="mt-6 flex flex-wrap gap-2">
            <span class="text-xs font-semibold text-slate-500">Filtrer :</span>
            <?php foreach (['' => 'Tout', 'operation' => 'Opérations', 'evenement' => 'Événements', 'formation' => 'Formations', 'autre' => 'Autre'] as $k => $lab): ?>
                <a href="<?= htmlspecialchars(url('pointage') . ($k !== '' ? '?type=' . rawurlencode($k) : '')) ?>"
                   class="rounded-full border px-3 py-1 text-xs font-bold <?= $pointageTypeFilter === $k ? 'border-emerald-600 bg-emerald-50 text-emerald-900' : 'border-slate-200 bg-white text-slate-600 hover:border-emerald-300' ?>">
                    <?= htmlspecialchars($lab) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php
    $quotaBanner = $eventsQuota;
    $quotaCanProceed = true;
    $variant = 'light';
    $quotaFromKey = 'events';
    require base_path('views/partials/quota_limited_banner.php');
    ?>

    <?php $s = \App\Core\Session::getFlash('success'); $e = \App\Core\Session::getFlash('error'); ?>
    <?php if ($s): ?><p class="mt-6 text-sm font-semibold text-emerald-700"><?= htmlspecialchars($s) ?></p><?php endif; ?>
    <?php if ($e): ?><p class="mt-6 text-sm font-semibold text-red-600"><?= htmlspecialchars($e) ?></p><?php endif; ?>

    <?php
    $renderEventBlock = static function (array $ev, array $checkFlags) use ($typeLabel, $rsvpLabel): void {
        $eid = (int) ($ev['id'] ?? 0);
        $etype = (string) ($ev['event_type'] ?? 'evenement');
        $rsvp = $ev['rsvp_status'] ?? null;
        $checked = $ev['rsvp_checked_in_at'] ?? null;
        ?>
        <li class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <span class="inline-block rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-black uppercase tracking-wider text-slate-600"><?= htmlspecialchars($typeLabel($etype)) ?></span>
                    <h2 class="mt-2 text-lg font-black text-slate-950"><?= htmlspecialchars((string) ($ev['title'] ?? '')) ?></h2>
                    <p class="mt-1 text-xs text-slate-500"><?= htmlspecialchars((string) ($ev['starts_at'] ?? '')) ?>
                        <?php if (!empty($ev['location'])): ?> · <?= htmlspecialchars((string) $ev['location']) ?><?php endif; ?></p>
                    <?php if (!empty($ev['description'])): ?>
                        <p class="mt-2 text-sm text-slate-600"><?= nl2br(htmlspecialchars((string) $ev['description'])) ?></p>
                    <?php endif; ?>
                </div>
                <div class="text-right text-xs text-slate-500">
                    <div>RSVP : <strong class="text-slate-800"><?= htmlspecialchars($rsvpLabel(is_string($rsvp) ? $rsvp : null)) ?></strong></div>
                    <?php if (!empty($checked)): ?>
                        <div class="mt-1 text-emerald-700">Pointé le <?= htmlspecialchars((string) $checked) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-slate-100 pt-4">
                <form method="post" action="<?= url('pointage/rsvp') ?>" class="flex flex-wrap items-center gap-2">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                    <input type="hidden" name="event_id" value="<?= $eid ?>">
                    <span class="text-xs font-semibold text-slate-500">Participation</span>
                    <select name="status" class="rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-sm">
                        <?php
                        $cur = is_string($rsvp) ? $rsvp : '';
                        foreach (['yes' => 'Présent', 'maybe' => 'Peut-être', 'no' => 'Absent'] as $val => $lab):
                        ?>
                            <option value="<?= $val ?>" <?= $cur === $val ? ' selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-bold text-white hover:bg-slate-800">Enregistrer</button>
                </form>
                <?php if (!empty($checkFlags[$eid])): ?>
                    <form method="post" action="<?= url('pointage/check-in') ?>" class="inline">
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                        <input type="hidden" name="event_id" value="<?= $eid ?>">
                        <button type="submit" class="rounded-lg border border-emerald-500 bg-emerald-50 px-3 py-1.5 text-xs font-black uppercase tracking-wide text-emerald-900 hover:bg-emerald-100">
                            Pointer ma présence
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </li>
        <?php
    };
    ?>

    <?php if ($pointageToday !== []): ?>
        <h2 class="mt-10 text-sm font-black uppercase tracking-[0.2em] text-slate-500">Aujourd’hui</h2>
        <ul class="mt-4 space-y-4">
            <?php foreach ($pointageToday as $ev): ?>
                <?php $renderEventBlock($ev, $pointageCheckInFlags); ?>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <h2 class="mt-10 text-sm font-black uppercase tracking-[0.2em] text-slate-500">À venir</h2>
    <ul class="mt-4 space-y-4">
        <?php if ($pointageUpcoming === []): ?>
            <li class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/80 p-8 text-center text-sm text-slate-500">
                Aucun créneau à venir<?= $pointageTypeFilter !== '' ? ' pour ce filtre' : '' ?>.
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
            <?php $renderEventBlock($ev, $pointageCheckInFlags); ?>
        <?php endforeach; ?>
    </ul>

    <?php if ($pointagePast !== []): ?>
        <h2 class="mt-12 text-sm font-black uppercase tracking-[0.2em] text-slate-500">Historique récent</h2>
        <ul class="mt-4 divide-y divide-slate-100 rounded-2xl border border-slate-200 bg-white">
            <?php foreach ($pointagePast as $ev): ?>
                <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm">
                    <span class="font-semibold text-slate-800"><?= htmlspecialchars((string) ($ev['title'] ?? '')) ?></span>
                    <span class="text-xs text-slate-500"><?= htmlspecialchars((string) ($ev['starts_at'] ?? '')) ?></span>
                    <span class="text-xs text-slate-600"><?= htmlspecialchars($rsvpLabel(is_string($ev['rsvp_status'] ?? null) ? (string) $ev['rsvp_status'] : null)) ?>
                        <?php if (!empty($ev['rsvp_checked_in_at'])): ?> · <span class="text-emerald-700">pointé</span><?php else: ?> · <span class="text-slate-400">non pointé</span><?php endif; ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <div class="mt-12 rounded-2xl border border-slate-200 bg-slate-50 p-5 text-sm text-slate-600">
        <p class="font-semibold text-slate-800">Raccourcis</p>
        <ul class="mt-2 flex flex-wrap gap-4">
            <li><a href="<?= url('evenements') ?>" class="text-emerald-800 underline">Vue liste événements</a></li>
            <li><a href="<?= url('dashboard') ?>" class="text-emerald-800 underline">Tableau de bord</a></li>
            <?php if (\can('admin.organization')): ?>
                <li><a href="<?= url('back-office/events') ?>" class="text-emerald-800 underline">Gérer les créneaux (admin)</a></li>
            <?php endif; ?>
        </ul>
    </div>
</div>
