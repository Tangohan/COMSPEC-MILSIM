<?php
$categories = is_array($categories ?? null) ? $categories : [];
$filterType = (string) ($filterType ?? '');
$success = \App\Core\Session::get('success');
$error = \App\Core\Session::get('error');
\App\Core\Session::forget('success');
\App\Core\Session::forget('error');
?>
<div class="max-w-6xl mx-auto px-4 sm:px-6 py-8 space-y-6">
    <header class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Métadonnées</p>
                <h1 class="mt-2 text-2xl font-black text-slate-900">Catégories de doctrine</h1>
                <p class="mt-2 text-sm text-slate-600">Classement des référentiels de rôles, profils utilisateur et domaines organisationnels.</p>
            </div>
            <a href="<?= url('back-office/categories/create') ?>" class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Créer une catégorie</a>
        </div>
        <form method="get" action="<?= url('back-office/categories') ?>" class="mt-5 flex flex-wrap items-end gap-2">
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Type
                <select name="type" class="mt-1 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900">
                    <option value="">Tous les types</option>
                    <option value="role" <?= $filterType === 'role' ? 'selected' : '' ?>>Rôles</option>
                    <option value="user" <?= $filterType === 'user' ? 'selected' : '' ?>>Utilisateurs</option>
                    <option value="organizational" <?= $filterType === 'organizational' ? 'selected' : '' ?>>Organisation</option>
                    <option value="business" <?= $filterType === 'business' ? 'selected' : '' ?>>Métier</option>
                </select>
            </label>
            <button type="submit" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Filtrer</button>
        </form>
    </header>

    <?php if ($success): ?><p class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800"><?= htmlspecialchars((string) $success, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <?php if ($error): ?><p class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800"><?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <?php if ($categories === []): ?>
            <div class="p-6 text-sm text-slate-500">Aucune catégorie trouvée pour ce filtre.</div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Nom</th>
                            <th class="px-4 py-3 text-left">Type</th>
                            <th class="px-4 py-3 text-left">Couleur</th>
                            <th class="px-4 py-3 text-left">Ordre</th>
                            <th class="px-4 py-3 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($categories as $c): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 font-semibold text-slate-900"><?= htmlspecialchars((string) ($c['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-4 py-3 text-slate-700"><?= htmlspecialchars((string) ($c['type'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-4 py-3">
                                    <?php if (!empty($c['color'])): ?>
                                        <span class="inline-flex items-center gap-2">
                                            <span class="inline-block h-4 w-4 rounded border border-slate-300" style="background:<?= htmlspecialchars((string) $c['color'], ENT_QUOTES, 'UTF-8') ?>"></span>
                                            <span class="font-mono text-xs text-slate-600"><?= htmlspecialchars((string) $c['color'], ENT_QUOTES, 'UTF-8') ?></span>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-slate-400">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-slate-700"><?= (int) ($c['display_order'] ?? 0) ?></td>
                                <td class="px-4 py-3">
                                    <a class="rounded-md border border-slate-300 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100" href="<?= url('back-office/categories/' . (int) ($c['id'] ?? 0) . '/edit') ?>">Modifier</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
