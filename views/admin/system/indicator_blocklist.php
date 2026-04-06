<?php
/** @var list<array<string, mixed>> $blocklistRows */
$indicatorKindLabel = static function (string $t): string {
    return $t === 'ip' ? 'Adresse réseau' : 'Adresse e-mail';
};
?>
<div class="max-w-4xl mx-auto px-4 sm:px-6 py-10">
    <header class="mb-8">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1">Administration plateforme</p>
        <h1 class="text-2xl font-black text-slate-900">Liste de restriction (toute la plateforme)</h1>
        <p class="mt-2 text-sm text-slate-600 leading-relaxed">
            Ces entrées s’appliquent à <strong class="font-semibold text-slate-800">toutes les communautés</strong> pour les connexions, inscriptions et candidatures concernées.
            À utiliser avec discernement. Les valeurs sensibles ne sont pas réaffichées ici.
        </p>
        <a href="<?= url('admin') ?>" class="inline-block mt-4 text-sm text-slate-600 hover:underline">Retour au centre opérateur site</a>
    </header>

    <?php $f = \App\Core\Session::getFlash('error'); $s = \App\Core\Session::getFlash('success'); ?>
    <?php if ($f): ?><p class="text-red-600 text-sm mb-4"><?= htmlspecialchars($f) ?></p><?php endif; ?>
    <?php if ($s): ?><p class="text-emerald-700 text-sm mb-4"><?= htmlspecialchars($s) ?></p><?php endif; ?>

    <section class="border border-slate-200 rounded-xl p-5 bg-white shadow-sm mb-10">
        <h2 class="text-sm font-bold text-slate-800 mb-3">Ajouter une entrée globale</h2>
        <form method="post" action="<?= url('admin/system/blocklist/add') ?>" class="grid md:grid-cols-2 gap-3">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
            <div>
                <label class="block text-xs text-slate-500 mb-1">Type</label>
                <select name="indicator_kind" class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
                    <option value="email">Adresse e-mail</option>
                    <option value="ip">Adresse réseau</option>
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs text-slate-500 mb-1">Valeur</label>
                <input type="text" name="restriction_target" required class="w-full border border-slate-300 rounded px-3 py-2 text-sm" placeholder="E-mail complet ou adresse réseau observée côté serveur">
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs text-slate-500 mb-1">Motif interne</label>
                <input type="text" name="block_reason" class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
            </div>
            <div class="md:col-span-2 flex flex-wrap gap-4 items-center text-sm">
                <label class="flex items-center gap-2">
                    <input type="radio" name="block_duration_mode" value="permanent" checked class="rounded border-slate-300"> Sans date de fin
                </label>
                <label class="flex items-center gap-2">
                    <input type="radio" name="block_duration_mode" value="temporary" class="rounded border-slate-300"> Pendant
                </label>
                <input type="number" name="block_duration_days" value="90" min="1" class="w-20 border border-slate-300 rounded px-2 py-1 text-sm"> jours
            </div>
            <div class="md:col-span-2">
                <button type="submit" class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded-lg hover:bg-slate-800">Enregistrer</button>
            </div>
        </form>
    </section>

    <section>
        <h2 class="text-sm font-bold text-slate-800 mb-3">Entrées actives</h2>
        <?php if ($blocklistRows === []): ?>
            <p class="text-sm text-slate-500">Aucune entrée globale active.</p>
        <?php else: ?>
            <table class="w-full text-sm border border-slate-200 rounded-lg overflow-hidden">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="text-left p-2">Réf.</th>
                        <th class="text-left p-2">Type</th>
                        <th class="text-left p-2">Fin</th>
                        <th class="text-left p-2">Motif</th>
                        <th class="text-left p-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($blocklistRows as $b): ?>
                    <tr class="border-t border-slate-100 bg-white">
                        <td class="p-2">#<?= (int) ($b['id'] ?? 0) ?></td>
                        <td class="p-2"><?= htmlspecialchars($indicatorKindLabel((string) ($b['indicator_type'] ?? ''))) ?></td>
                        <td class="p-2"><?= !empty($b['expires_at']) ? htmlspecialchars((string) $b['expires_at']) : '—' ?></td>
                        <td class="p-2"><?= htmlspecialchars(trim((string) ($b['reason'] ?? '')) !== '' ? (string) $b['reason'] : '—') ?></td>
                        <td class="p-2">
                            <form method="post" action="<?= url('admin/system/blocklist/revoke') ?>" class="inline">
                                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                                <input type="hidden" name="indicator_id" value="<?= (int) ($b['id'] ?? 0) ?>">
                                <button type="submit" class="text-rose-600 text-xs underline">Lever</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</div>
