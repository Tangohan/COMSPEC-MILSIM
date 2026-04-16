<?php
declare(strict_types=1);
/** @var list<array<string,mixed>> $openings */
/** @var string $statusFilter */
/** @var array<string,string> $statusLabels */
$openings = $openings ?? [];
$statusFilter = $statusFilter ?? 'all';
$statusLabels = is_array($statusLabels ?? null) ? $statusLabels : [];
$flashOk = \App\Core\Session::getFlash('success');
$flashErr = \App\Core\Session::getFlash('error');
?>
<div class="max-w-6xl mx-auto px-6 py-10">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between mb-8">
        <div>
            <h1 class="text-2xl font-black text-slate-900">Offres publiées</h1>
            <p class="mt-1 text-sm text-slate-600">Avis de vacance affichés sur la vitrine de votre communauté (mise en page « prospection »).</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="<?= htmlspecialchars(url('back-office/recruitment/reference-format'), ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">Format des références</a>
            <a href="<?= htmlspecialchars(url('back-office/recruitment/offers/create'), ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Nouvelle offre</a>
        </div>
    </div>
    <?php if ($flashOk): ?>
        <p class="mb-4 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-900"><?= htmlspecialchars($flashOk, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <?php if ($flashErr): ?>
        <p class="mb-4 rounded-lg bg-rose-50 px-4 py-2 text-sm text-rose-900"><?= htmlspecialchars($flashErr, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <form method="get" action="<?= htmlspecialchars(url('back-office/recruitment/offers'), ENT_QUOTES, 'UTF-8') ?>" class="mb-6 flex flex-wrap items-center gap-3">
        <label class="text-sm font-medium text-slate-700">Filtrer</label>
        <select name="status" class="<?= htmlspecialchars(bo_select_class('min-w-[11rem] sm:min-w-[13rem]'), ENT_QUOTES, 'UTF-8') ?>" onchange="this.form.submit()">
            <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>Tous les statuts</option>
            <?php foreach ($statusLabels as $k => $lab): ?>
                <option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>" <?= $statusFilter === $k ? 'selected' : '' ?>><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
        </select>
    </form>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Titre</th>
                    <th class="px-4 py-3">Unité</th>
                    <th class="px-4 py-3">Statut</th>
                    <th class="px-4 py-3">Référence</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if ($openings === []): ?>
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">Aucune offre pour l’instant.</td></tr>
                <?php else: ?>
                    <?php foreach ($openings as $o): ?>
                        <?php
                        $st = (string) ($o['status'] ?? '');
                        $stLab = $statusLabels[$st] ?? $st;
                        ?>
                        <tr class="hover:bg-slate-50/80">
                            <td class="px-4 py-3 font-semibold text-slate-900"><?= htmlspecialchars((string) ($o['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars((string) ($o['unit_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-4 py-3"><span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700"><?= htmlspecialchars($stLab, ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-600"><?= htmlspecialchars((string) ($o['reference_public'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-4 py-3 text-right space-x-2">
                                <?php if ($st === 'draft'): ?>
                                    <a href="<?= htmlspecialchars(url('back-office/recruitment/offers/' . (int) ($o['id'] ?? 0) . '/edit'), ENT_QUOTES, 'UTF-8') ?>" class="text-sky-700 font-semibold hover:underline">Modifier</a>
                                    <form action="<?= htmlspecialchars(url('back-office/recruitment/offers/' . (int) ($o['id'] ?? 0) . '/publish'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="mt-2 inline-block max-w-[220px] text-left align-top">
                                        <?= \App\Core\Csrf::field() ?>
                                        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 mb-1.5">À la publication</p>
                                        <label class="flex items-start gap-2 text-xs text-slate-700 cursor-pointer mb-1.5 leading-snug">
                                            <input type="checkbox" name="forum_annonce_generale" value="1" class="mt-0.5 rounded border-slate-300">
                                            <span>Publier aussi une annonce dans le <strong>forum général</strong> (recrutement visible par toute la communauté).</span>
                                        </label>
                                        <label class="flex items-start gap-2 text-xs text-slate-700 cursor-pointer mb-2 leading-snug">
                                            <input type="checkbox" name="forum_annonce_organisation" value="1" class="mt-0.5 rounded border-slate-300">
                                            <span>Publier aussi une annonce dans l’<strong>espace réservé à l’organisation</strong> (membres et encadrement).</span>
                                        </label>
                                        <button type="submit" class="text-emerald-700 font-semibold hover:underline text-sm">Publier l’offre</button>
                                    </form>
                                <?php elseif ($st === 'published'): ?>
                                    <form action="<?= htmlspecialchars(url('back-office/recruitment/offers/' . (int) ($o['id'] ?? 0) . '/close'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="inline">
                                        <?= \App\Core\Csrf::field() ?>
                                        <button type="submit" class="text-amber-800 font-semibold hover:underline">Clôturer</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
