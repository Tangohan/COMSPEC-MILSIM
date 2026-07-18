<?php
/** @var list<array<string, mixed>> $events */
/** @var int|null $currentUserId */
/** @var array<string, mixed>|null $eventsQuota */
/** @var array<int, bool> $eventsCheckInFlags */

$eventsQuota = $eventsQuota ?? null;
$eventsCheckInFlags = $eventsCheckInFlags ?? [];
$canPublishOperationalBoard = !empty($canPublishOperationalBoard);

$typeLabel = static function (string $t): string {
    return match ($t) {
        'operation' => 'Opération',
        'formation' => 'Formation',
        'autre' => 'Autre',
        default => 'Événement',
    };
};
?>
<div class="bg-slate-50 pb-16 sm:pb-24">
    <div class="relative overflow-hidden border-b border-slate-800/80 bg-gradient-to-br from-slate-900 via-emerald-950 to-slate-900 text-white">
        <div class="relative mx-auto max-w-3xl px-4 pt-10 pb-12 sm:px-6 sm:pt-14 sm:pb-16">
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-emerald-300/95">Agenda</p>
            <h1 class="mt-2 text-3xl font-black tracking-tight sm:text-4xl">Événements</h1>
            <p class="mt-4 text-sm leading-relaxed text-slate-300">
                <a href="<?= htmlspecialchars(url('pointage')) ?>" class="font-semibold text-emerald-300 underline-offset-2 hover:underline">Ouvrir le pointage complet</a>
                — confirmation de participation, pointage le jour J et historique.
                <?php if (!empty($calendar_subscription_url)): ?>
                <span class="mt-3 block text-slate-400">Abonnement calendrier (lecture seule, lien personnel) :</span>
                <input type="text" readonly class="mt-2 w-full max-w-xl rounded-xl border border-white/15 bg-white/10 px-3 py-2 text-xs text-white" value="<?= htmlspecialchars((string) $calendar_subscription_url, ENT_QUOTES, 'UTF-8') ?>" onclick="this.select();">
                <?php endif; ?>
            </p>
        </div>
    </div>

    <div class="mx-auto max-w-3xl space-y-6 px-4 py-10 sm:px-6">
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

        <ul class="space-y-4">
            <?php foreach ($events as $ev): ?>
                <?php
                $eid = (int) ($ev['id'] ?? 0);
                $etype = (string) ($ev['event_type'] ?? 'evenement');
                $rsvp = $ev['rsvp_status'] ?? null;
                $cur = is_string($rsvp) ? $rsvp : '';
                ?>
                <li class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-500"><?= htmlspecialchars($typeLabel($etype)) ?></span>
                    <h2 class="mt-1 font-bold text-slate-900"><?= htmlspecialchars((string) ($ev['title'] ?? '')) ?></h2>
                    <p class="mt-1 text-xs text-slate-500"><?= htmlspecialchars((string) ($ev['starts_at'] ?? '')) ?>
                        <?php if (!empty($ev['location'])): ?> · <?= htmlspecialchars((string) $ev['location']) ?><?php endif; ?></p>
                    <?php if (!empty($ev['description'])): ?>
                        <p class="mt-3 text-sm text-slate-700"><?= nl2br(htmlspecialchars((string) $ev['description'])) ?></p>
                    <?php endif; ?>
                    <?php if ($canPublishOperationalBoard): ?>
                    <?php
                    $opBoardPublishSourceType = 'event';
                    $opBoardPublishSourceId = $eid;
                    $opBoardPublishCsrf = \App\Core\Csrf::token();
                    $opBoardPublishVariant = 'course';
                    require base_path('views/partials/operational_board_publish_linked_form.php');
                    ?>
                    <?php endif; ?>
                    <?php if ($currentUserId): ?>
                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        <form method="post" action="<?= url('evenements/rsvp') ?>" class="flex flex-wrap items-center gap-2">
                            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                            <input type="hidden" name="event_id" value="<?= $eid ?>">
                            <span class="text-xs font-semibold text-slate-500">Participation</span>
                            <select name="status" class="rounded-lg border border-slate-200 bg-slate-50 px-2 py-1.5 text-xs text-slate-900">
                                <?php foreach (['yes' => 'Présent', 'maybe' => 'Peut-être', 'no' => 'Absent'] as $val => $lab): ?>
                                    <option value="<?= $val ?>" <?= $cur === $val ? ' selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="absence_reason" class="rounded-lg border border-slate-200 bg-slate-50 px-2 py-1.5 text-xs text-slate-900">
                                <option value="">Motif d’absence</option>
                                <option value="service">Service</option>
                                <option value="sante">Santé</option>
                                <option value="indisponibilite_planifiee">Indispo planifiée</option>
                                <option value="absence_non_justifiee">Absence non justifiée</option>
                                <option value="autre">Autre</option>
                            </select>
                            <button type="submit" class="text-xs font-bold text-emerald-700 hover:text-emerald-900">Enregistrer</button>
                        </form>
                        <?php if (!empty($eventsCheckInFlags[$eid])): ?>
                            <form method="post" action="<?= url('pointage/check-in') ?>" class="inline">
                                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                                <input type="hidden" name="event_id" value="<?= $eid ?>">
                                <button type="submit" class="rounded-lg border border-emerald-300 bg-emerald-50 px-2.5 py-1 text-xs font-black uppercase tracking-wide text-emerald-900 hover:bg-emerald-100">
                                    Pointer ma présence
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if (empty($events)): ?>
            <p class="rounded-2xl border border-dashed border-slate-200 bg-white px-6 py-10 text-center text-sm text-slate-500">Aucun événement à venir.</p>
        <?php endif; ?>
    </div>
</div>
