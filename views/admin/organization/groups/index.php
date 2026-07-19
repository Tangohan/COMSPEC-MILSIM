<?php
$groups = is_array($groups ?? null) ? $groups : [];
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
                <h1 class="mt-2 text-2xl font-black text-slate-900">Groupes opérationnels</h1>
                <p class="mt-2 text-sm text-slate-600">Vue consolidée des groupes, avec accès rapide aux fiches et à la publication sur la page publique.</p>
            </div>
            <a href="<?= url('back-office/groups/create') ?>" class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Créer un groupe</a>
        </div>
        <div class="mt-5 grid gap-3 sm:grid-cols-2">
            <div class="rounded-xl bg-slate-50 p-3">
                <p class="text-xs uppercase text-slate-500">Total groupes</p>
                <p class="text-2xl font-black text-slate-900"><?= count($groups) ?></p>
            </div>
            <div class="rounded-xl bg-slate-50 p-3">
                <p class="text-xs uppercase text-slate-500">Navigation</p>
                <a class="text-sm font-semibold text-blue-700 underline" href="<?= url('back-office/organisation/structure') ?>">Ouvrir le hub structure</a>
            </div>
        </div>
    </header>

    <?php if ($success): ?><p class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800"><?= htmlspecialchars((string) $success, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <?php if ($error): ?><p class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800"><?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <?php if ($groups === []): ?>
            <div class="p-6 text-sm text-slate-500">Aucun groupe enregistré.</div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Groupe</th>
                            <th class="px-4 py-3 text-left">Code</th>
                            <th class="px-4 py-3 text-left">Page publique</th>
                            <th class="px-4 py-3 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($groups as $g): ?>
                            <?php
                            $isPublic = !array_key_exists('show_on_public_page', $g) || (int) ($g['show_on_public_page'] ?? 0) === 1;
                            $hasBlurb = trim((string) ($g['public_blurb'] ?? '')) !== '';
                            ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 font-semibold text-slate-900"><?= htmlspecialchars((string) ($g['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-4 py-3 text-slate-700"><?= htmlspecialchars((string) ($g['code'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-4 py-3 text-slate-700">
                                    <?php if ($isPublic && $hasBlurb): ?>
                                        <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800 ring-1 ring-emerald-200">Visible · présentation renseignée</span>
                                    <?php elseif ($isPublic): ?>
                                        <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-900 ring-1 ring-amber-200">Visible · présentation à compléter</span>
                                    <?php else: ?>
                                        <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 ring-1 ring-slate-200">Masquée</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <a class="rounded-md border border-slate-300 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100" href="<?= url('back-office/groups/' . (int) ($g['id'] ?? 0)) ?>">Voir</a>
                                        <a class="rounded-md border border-slate-300 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100" href="<?= url('back-office/groups/' . (int) ($g['id'] ?? 0) . '/edit') ?>">Modifier</a>
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
