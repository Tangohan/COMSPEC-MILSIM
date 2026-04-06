<?php
/** @var list<array<string, mixed>> $events */
/** @var int|null $currentUserId */
/** @var array<string, mixed>|null $eventsQuota */
/** @var array<int, bool> $eventsCheckInFlags */

$eventsQuota = $eventsQuota ?? null;
$eventsCheckInFlags = $eventsCheckInFlags ?? [];

$typeLabel = static function (string $t): string {
    return match ($t) {
        'operation' => 'Opération',
        'formation' => 'Formation',
        'autre' => 'Autre',
        default => 'Événement',
    };
};
?>
<div class="max-w-3xl mx-auto px-4 py-10">
    <h1 class="text-2xl font-black text-white mb-2">Événements</h1>
    <p class="text-sm text-neutral-400 mb-6">
        <a href="<?= htmlspecialchars(url('pointage')) ?>" class="text-emerald-400 hover:underline font-semibold">Ouvrir le pointage complet</a>
        — RSVP, pointage jour J et historique.
        <?php if (!empty($calendar_subscription_url)): ?>
        <span class="block mt-3 text-neutral-500">Abonnement calendrier (lecture seule, lien personnel) :</span>
        <input type="text" readonly class="mt-1 w-full max-w-xl rounded border border-white/10 bg-neutral-950/80 px-3 py-2 text-xs text-neutral-200" value="<?= htmlspecialchars((string) $calendar_subscription_url, ENT_QUOTES, 'UTF-8') ?>" onclick="this.select();">
        <?php endif; ?>
    </p>
    <?php
    $quotaBanner = $eventsQuota ?? null;
    $quotaCanProceed = true;
    $variant = 'dark';
    $quotaFromKey = 'events';
    require __DIR__ . '/../partials/quota_limited_banner.php';
    ?>
    <?php $s = \App\Core\Session::getFlash('success'); $e = \App\Core\Session::getFlash('error'); ?>
    <?php if ($s): ?><p class="text-emerald-400 text-sm mb-4"><?= htmlspecialchars($s) ?></p><?php endif; ?>
    <?php if ($e): ?><p class="text-red-400 text-sm mb-4"><?= htmlspecialchars($e) ?></p><?php endif; ?>
    <ul class="space-y-4">
        <?php foreach ($events as $ev): ?>
            <?php
            $eid = (int) ($ev['id'] ?? 0);
            $etype = (string) ($ev['event_type'] ?? 'evenement');
            $rsvp = $ev['rsvp_status'] ?? null;
            $cur = is_string($rsvp) ? $rsvp : '';
            ?>
            <li class="border border-white/10 rounded-lg p-4 bg-neutral-900/50">
                <span class="text-[10px] font-black uppercase tracking-wider text-neutral-500"><?= htmlspecialchars($typeLabel($etype)) ?></span>
                <h2 class="font-bold text-white mt-1"><?= htmlspecialchars((string) ($ev['title'] ?? '')) ?></h2>
                <p class="text-xs text-neutral-500 mt-1"><?= htmlspecialchars((string) ($ev['starts_at'] ?? '')) ?>
                    <?php if (!empty($ev['location'])): ?> · <?= htmlspecialchars((string) $ev['location']) ?><?php endif; ?></p>
                <?php if (!empty($ev['description'])): ?>
                    <p class="text-sm text-neutral-300 mt-2"><?= nl2br(htmlspecialchars((string) $ev['description'])) ?></p>
                <?php endif; ?>
                <?php if ($currentUserId): ?>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <form method="post" action="<?= url('evenements/rsvp') ?>" class="flex gap-2 items-center flex-wrap">
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                        <input type="hidden" name="event_id" value="<?= $eid ?>">
                        <span class="text-xs text-neutral-500">RSVP</span>
                        <select name="status" class="bg-neutral-800 border border-white/10 text-xs rounded px-2 py-1">
                            <?php foreach (['yes' => 'Présent', 'maybe' => 'Peut-être', 'no' => 'Absent'] as $val => $lab): ?>
                                <option value="<?= $val ?>" <?= $cur === $val ? ' selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="text-xs font-bold text-emerald-400 hover:text-emerald-300">Enregistrer</button>
                    </form>
                    <?php if (!empty($eventsCheckInFlags[$eid])): ?>
                        <form method="post" action="<?= url('pointage/check-in') ?>" class="inline">
                            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                            <input type="hidden" name="event_id" value="<?= $eid ?>">
                            <button type="submit" class="text-xs font-black uppercase tracking-wide text-emerald-300 border border-emerald-500/50 rounded px-2 py-1 hover:bg-emerald-500/10">
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
        <p class="text-neutral-500 text-sm">Aucun événement à venir.</p>
    <?php endif; ?>
</div>
