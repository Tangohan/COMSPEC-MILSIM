<?php
declare(strict_types=1);
$rows = is_array($subscriptionPlansRows ?? null) ? $subscriptionPlansRows : [];
?>
<div class="min-h-0 flex-1 bg-slate-50">
    <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
        <nav class="mb-6 text-sm text-slate-500">
            <a href="<?= htmlspecialchars(url('admin'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-emerald-800 hover:text-emerald-950">Administration plateforme</a>
            <span class="mx-2" aria-hidden="true">/</span>
            <span class="text-slate-800">Formules d’accès</span>
        </nav>

        <?php $ok = \App\Core\Session::getFlash('success'); ?>
        <?php if ($ok): ?>
            <p class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900"><?= htmlspecialchars((string) $ok, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <?php $err = \App\Core\Session::getFlash('error'); ?>
        <?php if ($err): ?>
            <p class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-900"><?= htmlspecialchars((string) $err, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <h1 class="text-2xl font-black text-slate-900">Formules d’accès (paliers)</h1>
        <p class="mt-2 max-w-3xl text-sm text-slate-600">
            Libellés, ordre d’affichage, modules inclus, plafonds et identifiants de paiement PayPal (ou Stripe en secours).
            L’identifiant interne de chaque formule n’est pas modifiable ici, pour ne pas désynchroniser les communautés déjà créées.
        </p>

        <div class="mt-8 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Ordre</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Libellé</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Slug</th>
                        <th class="px-4 py-3 text-right font-semibold text-slate-600">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if ($rows === []): ?>
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-slate-500">Aucune formule enregistrée.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <?php
                            $rid = (int) ($r['id'] ?? 0);
                            $slug = (string) ($r['slug'] ?? '');
                            $name = (string) ($r['name'] ?? '');
                            $sort = (int) ($r['sort_order'] ?? 0);
                            ?>
                            <tr>
                                <td class="px-4 py-3 text-slate-700"><?= (int) $sort ?></td>
                                <td class="px-4 py-3 font-medium text-slate-900"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-4 py-3 font-mono text-xs text-slate-600"><?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-4 py-3 text-right">
                                    <?php if ($rid > 0): ?>
                                        <a href="<?= htmlspecialchars(url('admin/system/subscription-plans/' . $rid . '/edit'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 hover:bg-slate-50">Modifier</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
