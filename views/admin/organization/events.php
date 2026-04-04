<?php
/** @var list<array<string, mixed>> $events */
?>
<div class="max-w-4xl mx-auto px-6 py-12">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-black text-slate-900">Événements communauté</h1>
        <a href="<?= url('admin/organization') ?>" class="text-sm text-slate-600 hover:underline">Retour</a>
    </div>
    <?php $s = \App\Core\Session::getFlash('success'); $e = \App\Core\Session::getFlash('error'); ?>
    <?php if ($s): ?><p class="text-emerald-700 text-sm mb-4"><?= htmlspecialchars($s) ?></p><?php endif; ?>
    <?php if ($e): ?><p class="text-red-600 text-sm mb-4"><?= htmlspecialchars($e) ?></p><?php endif; ?>

    <form method="post" action="<?= url('admin/organization/events') ?>" class="space-y-3 border border-slate-200 rounded-lg p-4 mb-10">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
        <div>
            <label class="block text-xs text-slate-500">Titre</label>
            <input type="text" name="title" required class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs text-slate-500">Description</label>
            <textarea name="description" rows="2" class="w-full border border-slate-300 rounded px-3 py-2 text-sm"></textarea>
        </div>
        <div class="grid sm:grid-cols-2 gap-2">
            <div>
                <label class="block text-xs text-slate-500">Début (YYYY-MM-DD HH:MM)</label>
                <input type="text" name="starts_at" required placeholder="2026-04-10 20:00:00" class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs text-slate-500">Fin (optionnel)</label>
                <input type="text" name="ends_at" class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
            </div>
        </div>
        <div>
            <label class="block text-xs text-slate-500">Lieu</label>
            <input type="text" name="location" class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs text-slate-500">Campagne / tag</label>
            <input type="text" name="campaign_tag" class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
        </div>
        <button type="submit" class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded">Créer</button>
    </form>

    <ul class="divide-y divide-slate-200 border border-slate-200 rounded-lg">
        <?php foreach ($events as $ev): ?>
            <li class="px-4 py-3 text-sm">
                <span class="font-semibold"><?= htmlspecialchars((string) ($ev['title'] ?? '')) ?></span>
                <span class="text-slate-500"><?= htmlspecialchars((string) ($ev['starts_at'] ?? '')) ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
