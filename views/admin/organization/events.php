<?php
/** @var list<array<string, mixed>> $events */
/** @var array<string, mixed>|null $eventsQuota */
/** @var bool $canCreateEvent */
$eventsQuota = $eventsQuota ?? null;
$canCreateEvent = $canCreateEvent ?? true;
?>
<div class="max-w-4xl mx-auto px-6 py-12">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-black text-slate-900">Événements communauté</h1>
        <a href="<?= url('back-office') ?>" class="text-sm text-slate-600 hover:underline">Retour</a>
    </div>
    <?php $s = \App\Core\Session::getFlash('success'); $e = \App\Core\Session::getFlash('error'); ?>
    <?php if ($s): ?><p class="text-emerald-700 text-sm mb-4"><?= htmlspecialchars($s) ?></p><?php endif; ?>
    <?php if ($e): ?><p class="text-red-600 text-sm mb-4"><?= htmlspecialchars($e) ?></p><?php endif; ?>

    <?php
    $quotaBanner = $eventsQuota;
    $quotaCanProceed = $canCreateEvent;
    $variant = 'light';
    $quotaFromKey = 'events';
    require __DIR__ . '/../../partials/quota_limited_banner.php';
    ?>

    <form method="post" action="<?= url('back-office/events') ?>" class="space-y-3 border border-slate-200 rounded-lg p-4 mb-10 <?= !$canCreateEvent ? 'opacity-75' : '' ?>">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
        <div>
            <label class="block text-xs text-slate-500">Titre</label>
            <input type="text" name="title" required class="w-full border border-slate-300 rounded px-3 py-2 text-sm" <?= !$canCreateEvent ? 'disabled' : '' ?>>
        </div>
        <div>
            <label class="block text-xs text-slate-500">Description</label>
            <textarea name="description" rows="2" class="w-full border border-slate-300 rounded px-3 py-2 text-sm" <?= !$canCreateEvent ? 'disabled' : '' ?>></textarea>
        </div>
        <div class="grid sm:grid-cols-2 gap-2">
            <div>
                <label class="block text-xs text-slate-500">Début (YYYY-MM-DD HH:MM)</label>
                <input type="text" name="starts_at" required placeholder="2026-04-10 20:00:00" class="w-full border border-slate-300 rounded px-3 py-2 text-sm" <?= !$canCreateEvent ? 'disabled' : '' ?>>
            </div>
            <div>
                <label class="block text-xs text-slate-500">Fin (optionnel)</label>
                <input type="text" name="ends_at" class="w-full border border-slate-300 rounded px-3 py-2 text-sm" <?= !$canCreateEvent ? 'disabled' : '' ?>>
            </div>
        </div>
        <div>
            <label class="block text-xs text-slate-500">Lieu</label>
            <input type="text" name="location" class="w-full border border-slate-300 rounded px-3 py-2 text-sm" <?= !$canCreateEvent ? 'disabled' : '' ?>>
        </div>
        <div>
            <label class="block text-xs text-slate-500">Campagne / tag</label>
            <input type="text" name="campaign_tag" class="w-full border border-slate-300 rounded px-3 py-2 text-sm" <?= !$canCreateEvent ? 'disabled' : '' ?>>
        </div>
        <div>
            <label class="block text-xs text-slate-500">Type</label>
            <select name="event_type" class="w-full border border-slate-300 rounded px-3 py-2 text-sm" <?= !$canCreateEvent ? 'disabled' : '' ?>>
                <option value="operation">Opération</option>
                <option value="evenement" selected>Événement</option>
                <option value="formation">Formation (créneau)</option>
                <option value="autre">Autre</option>
            </select>
        </div>
        <button type="submit" class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded" <?= !$canCreateEvent ? 'disabled' : '' ?>>Créer</button>
    </form>

    <ul class="divide-y divide-slate-200 border border-slate-200 rounded-lg">
        <?php foreach ($events as $ev): ?>
            <li class="px-4 py-3 text-sm flex flex-wrap items-center justify-between gap-2">
                <div>
                    <span class="font-semibold"><?= htmlspecialchars((string) ($ev['title'] ?? '')) ?></span>
                    <span class="text-slate-500 ml-2"><?= htmlspecialchars((string) ($ev['starts_at'] ?? '')) ?></span>
                    <?php
                    $et = (string) ($ev['event_type'] ?? 'evenement');
                    $etLab = match ($et) {
                        'operation' => 'Opération',
                        'formation' => 'Formation',
                        'autre' => 'Autre',
                        default => 'Événement',
                    };
                    ?>
                    <span class="ml-2 text-[10px] font-bold uppercase tracking-wider text-slate-400"><?= htmlspecialchars($etLab) ?></span>
                </div>
                <a href="<?= url('back-office/events/' . (int) ($ev['id'] ?? 0)) ?>" class="text-emerald-700 text-xs font-semibold hover:underline">Participants</a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
