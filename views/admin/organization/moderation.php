<?php
/** @var list<array<string, mixed>> $actions */
/** @var list<array<string, mixed>> $memberUsers */
?>
<div class="max-w-5xl mx-auto px-6 py-12">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-black text-slate-900">Modération</h1>
        <a href="<?= url('admin/organization') ?>" class="text-sm text-slate-600 hover:underline">Retour</a>
    </div>
    <?php $f = \App\Core\Session::getFlash('error'); $s = \App\Core\Session::getFlash('success'); ?>
    <?php if ($f): ?><p class="text-red-600 text-sm mb-4"><?= htmlspecialchars($f) ?></p><?php endif; ?>
    <?php if ($s): ?><p class="text-emerald-700 text-sm mb-4"><?= htmlspecialchars($s) ?></p><?php endif; ?>

    <form method="post" action="<?= url('admin/organization/moderation/apply') ?>" class="grid md:grid-cols-2 gap-4 mb-10 border border-slate-200 rounded-lg p-4">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
        <div class="md:col-span-2">
            <label class="block text-xs text-slate-500 mb-1">Membre</label>
            <select name="target_user_id" required class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
                <option value="">—</option>
                <?php foreach ($memberUsers as $u): ?>
                    <option value="<?= (int) ($u['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($u['email'] ?? '')) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs text-slate-500 mb-1">Sanction</label>
            <select name="action_type" class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
                <option value="warn">Avertissement</option>
                <option value="mute">Mute</option>
                <option value="suspend">Suspension</option>
                <option value="ban">Bannissement</option>
            </select>
        </div>
        <div>
            <label class="block text-xs text-slate-500 mb-1">Durée (jours, 0 = selon type)</label>
            <input type="number" name="duration_days" value="0" min="0" class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
        </div>
        <div class="md:col-span-2">
            <label class="block text-xs text-slate-500 mb-1">Motif</label>
            <textarea name="reason" rows="2" class="w-full border border-slate-300 rounded px-3 py-2 text-sm"></textarea>
        </div>
        <div class="md:col-span-2">
            <button type="submit" class="px-4 py-2 bg-rose-700 text-white text-sm font-semibold rounded">Appliquer</button>
        </div>
    </form>

    <h2 class="text-lg font-bold text-slate-800 mb-2">Historique récent</h2>
    <table class="w-full text-sm border border-slate-200 rounded">
        <thead class="bg-slate-50">
            <tr>
                <th class="text-left p-2">Date</th>
                <th class="text-left p-2">Cible</th>
                <th class="text-left p-2">Type</th>
                <th class="text-left p-2">Acteur</th>
                <th class="text-left p-2"></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($actions as $a): ?>
            <tr class="border-t border-slate-100">
                <td class="p-2"><?= htmlspecialchars((string) ($a['created_at'] ?? '')) ?></td>
                <td class="p-2"><?= htmlspecialchars((string) ($a['target_email'] ?? '')) ?></td>
                <td class="p-2"><?= htmlspecialchars((string) ($a['action_type'] ?? '')) ?></td>
                <td class="p-2"><?= htmlspecialchars((string) ($a['actor_email'] ?? '')) ?></td>
                <td class="p-2">
                    <?php if (empty($a['revoked_at'])): ?>
                    <form method="post" action="<?= url('admin/organization/moderation/revoke') ?>" class="inline">
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                        <input type="hidden" name="action_id" value="<?= (int) ($a['id'] ?? 0) ?>">
                        <button type="submit" class="text-rose-600 text-xs underline">Lever</button>
                    </form>
                    <?php else: ?>
                        <span class="text-slate-400">Révoqué</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
