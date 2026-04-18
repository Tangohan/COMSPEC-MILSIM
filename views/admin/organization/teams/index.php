<?php
$teams = is_array($teams ?? null) ? $teams : [];
$success = \App\Core\Session::get('success');
$error = \App\Core\Session::get('error');
\App\Core\Session::forget('success');
\App\Core\Session::forget('error');
?>
<div class="max-w-6xl mx-auto px-4 sm:px-6 py-8 space-y-6">
    <header class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Organigramme</p>
                <h1 class="mt-2 text-2xl font-black text-slate-900">Équipes tactiques</h1>
                <p class="mt-2 text-sm text-slate-600">Gestion détaillée des équipes, codes opérationnels et accès vers les fiches d’édition.</p>
            </div>
            <a href="<?= url('back-office/teams/create') ?>" class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Créer une équipe</a>
        </div>
        <div class="mt-5 rounded-xl bg-slate-50 p-3">
            <p class="text-xs uppercase text-slate-500">Total équipes</p>
            <p class="text-2xl font-black text-slate-900"><?= count($teams) ?></p>
        </div>
    </header>

    <?php if ($success): ?><p class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800"><?= htmlspecialchars((string) $success, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <?php if ($error): ?><p class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800"><?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <?php if ($teams === []): ?>
            <div class="p-6 text-sm text-slate-500">Aucune équipe enregistrée.</div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Équipe</th>
                            <th class="px-4 py-3 text-left">Code</th>
                            <th class="px-4 py-3 text-left">Slug</th>
                            <th class="px-4 py-3 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($teams as $t): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 font-semibold text-slate-900"><?= htmlspecialchars((string) ($t['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-4 py-3 text-slate-700"><?= htmlspecialchars((string) ($t['code'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-4 py-3 font-mono text-xs text-slate-600"><?= htmlspecialchars((string) ($t['slug'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <a class="rounded-md border border-slate-300 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100" href="<?= url('back-office/teams/' . (int) ($t['id'] ?? 0)) ?>">Voir</a>
                                        <a class="rounded-md border border-slate-300 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100" href="<?= url('back-office/teams/' . (int) ($t['id'] ?? 0) . '/edit') ?>">Modifier</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
