<?php
declare(strict_types=1);
$c = is_array($deploymentCommunity ?? null) ? $deploymentCommunity : [];
$members = is_array($deploymentCommunityMembers ?? null) ? $deploymentCommunityMembers : [];
$csrf = htmlspecialchars((string) ($deploymentCsrf ?? ''), ENT_QUOTES, 'UTF-8');
$id = (int) ($c['id'] ?? 0);
?>
<div class="min-h-0 flex-1 bg-slate-50">
    <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
        <p class="text-xs font-semibold uppercase tracking-wider text-amber-700">
            <a href="<?= htmlspecialchars(url('admin/system/deployment/communities'), ENT_QUOTES, 'UTF-8') ?>" class="hover:underline">Communautés</a>
        </p>
        <h1 class="mt-2 text-2xl font-black text-slate-900"><?= htmlspecialchars((string) ($c['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h1>

        <section class="mt-8 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900">Paramètres</h2>
            <form method="post" action="<?= htmlspecialchars(url('admin/system/deployment/communities/' . $id . '/edit'), ENT_QUOTES, 'UTF-8') ?>" class="mt-4 space-y-4">
                <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                <div>
                    <label class="block text-xs font-semibold text-slate-600">Nom affiché</label>
                    <input name="name" required value="<?= htmlspecialchars((string) ($c['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full max-w-xl rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600">Description</label>
                    <textarea name="description" rows="3" class="mt-1 w-full max-w-2xl rounded-lg border border-slate-300 px-3 py-2 text-sm"><?= htmlspecialchars((string) ($c['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600">Priorité</label>
                        <input type="number" name="priority" value="<?= (int) ($c['priority'] ?? 100) ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600">Début de validité (optionnel)</label>
                        <input name="valid_from" value="<?= htmlspecialchars((string) ($c['valid_from'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="AAAA-MM-JJ HH:MM:SS">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600">Fin de validité (optionnel)</label>
                        <input name="valid_until" value="<?= htmlspecialchars((string) ($c['valid_until'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                </div>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" <?= !empty($c['is_active']) ? 'checked' : '' ?>> Communauté active</label>
                <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-bold text-white hover:bg-slate-800">Enregistrer</button>
            </form>
        </section>

        <section class="mt-8 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900">Membres</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead><tr class="border-b border-slate-200 text-left text-slate-600">
                        <th class="py-2 pr-4">Compte</th><th class="py-2 pr-4">Indicatif</th><th class="py-2">Statut</th><th class="py-2"></th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($members as $pack): ?>
                            <?php $m = is_array($pack['membership'] ?? null) ? $pack['membership'] : []; ?>
                            <?php $u = is_array($pack['user'] ?? null) ? $pack['user'] : []; ?>
                            <?php $uid = (int) ($m['user_id'] ?? 0); ?>
                            <tr class="border-b border-slate-100">
                                <td class="py-2 pr-4 font-mono text-xs"><?= $uid ?></td>
                                <td class="py-2 pr-4"><?= htmlspecialchars(trim((string) ($u['callsign'] ?? '') ?: '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="py-2"><?= htmlspecialchars((string) ($m['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="py-2">
                                    <form method="post" action="<?= htmlspecialchars(url('admin/system/deployment/communities/' . $id . '/members/remove'), ENT_QUOTES, 'UTF-8') ?>" class="inline" onsubmit="return confirm('Retirer ce membre ?');">
                                        <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                                        <input type="hidden" name="user_id" value="<?= $uid ?>">
                                        <button type="submit" class="text-xs font-semibold text-rose-700 hover:underline">Retirer</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <form method="post" action="<?= htmlspecialchars(url('admin/system/deployment/communities/' . $id . '/members'), ENT_QUOTES, 'UTF-8') ?>" class="mt-6 flex flex-wrap items-end gap-3 border-t border-slate-100 pt-6">
                <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                <div>
                    <label class="block text-xs font-semibold text-slate-600">Identifiant du compte membre</label>
                    <input type="number" name="user_id" min="1" required class="mt-1 w-40 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600">Fin d’inclusion (optionnel)</label>
                    <input name="expires_at" class="mt-1 w-56 rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="AAAA-MM-JJ …">
                </div>
                <button type="submit" class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-bold text-white hover:bg-amber-700">Ajouter</button>
            </form>
        </section>
    </div>
</div>
