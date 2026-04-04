<?php
/** @var list<array<string, mixed>> $events */
/** @var int|null $currentUserId */
?>
<div class="max-w-3xl mx-auto px-4 py-10">
    <h1 class="text-2xl font-black text-white mb-6">Événements</h1>
    <?php $s = \App\Core\Session::getFlash('success'); $e = \App\Core\Session::getFlash('error'); ?>
    <?php if ($s): ?><p class="text-emerald-400 text-sm mb-4"><?= htmlspecialchars($s) ?></p><?php endif; ?>
    <?php if ($e): ?><p class="text-red-400 text-sm mb-4"><?= htmlspecialchars($e) ?></p><?php endif; ?>
    <ul class="space-y-4">
        <?php foreach ($events as $ev): ?>
            <li class="border border-white/10 rounded-lg p-4 bg-neutral-900/50">
                <h2 class="font-bold text-white"><?= htmlspecialchars((string) ($ev['title'] ?? '')) ?></h2>
                <p class="text-xs text-neutral-500 mt-1"><?= htmlspecialchars((string) ($ev['starts_at'] ?? '')) ?>
                    <?php if (!empty($ev['location'])): ?> · <?= htmlspecialchars((string) $ev['location']) ?><?php endif; ?></p>
                <?php if (!empty($ev['description'])): ?>
                    <p class="text-sm text-neutral-300 mt-2"><?= nl2br(htmlspecialchars((string) $ev['description'])) ?></p>
                <?php endif; ?>
                <?php if ($currentUserId): ?>
                <form method="post" action="<?= url('evenements/rsvp') ?>" class="mt-3 flex gap-2 items-center">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                    <input type="hidden" name="event_id" value="<?= (int) ($ev['id'] ?? 0) ?>">
                    <span class="text-xs text-neutral-500">RSVP</span>
                    <select name="status" class="bg-neutral-800 border border-white/10 text-xs rounded px-2 py-1">
                        <option value="yes">Présent</option>
                        <option value="maybe">Peut-être</option>
                        <option value="no">Absent</option>
                    </select>
                    <button type="submit" class="text-xs font-bold text-emerald-400 hover:text-emerald-300">Enregistrer</button>
                </form>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php if (empty($events)): ?>
        <p class="text-neutral-500 text-sm">Aucun événement à venir.</p>
    <?php endif; ?>
</div>
