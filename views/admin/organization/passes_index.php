<?php
declare(strict_types=1);

/** @var list<array<string, mixed>> $passList */
/** @var bool $passSchemaReady */

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$list = is_array($passList ?? null) ? $passList : [];
$ready = !empty($passSchemaReady);
$flashError = \App\Core\Session::getFlash('error');
$flashSuccess = \App\Core\Session::getFlash('success');
?>
<div class="max-w-5xl mx-auto space-y-6 px-4 py-6">
    <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div class="space-y-1">
            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Back-office · Personnel</p>
            <h1 class="text-2xl font-black text-slate-900">PASS RH</h1>
            <p class="text-sm text-slate-600 max-w-2xl">
                Un PASS regroupe des conditions (formation, heures, grade, avis hiérarchique, notation)
                et s’attache à un poste ouvert, un avancement ou une notation.
            </p>
        </div>
        <?php if ($ready): ?>
        <a href="<?= $h(url('back-office/organisation/passes/create')) ?>" class="ath-btn ath-btn--solid">Créer un PASS</a>
        <?php endif; ?>
    </header>

    <?php if ($flashError): ?>
    <p class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert"><?= $h((string) $flashError) ?></p>
    <?php endif; ?>
    <?php if ($flashSuccess): ?>
    <p class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status"><?= $h((string) $flashSuccess) ?></p>
    <?php endif; ?>

    <?php if (!$ready): ?>
    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        Le schéma PASS n’est pas encore appliqué sur cette instance. Demandez la mise à jour de la base, puis rechargez.
    </div>
    <?php elseif ($list === []): ?>
    <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-5 py-8 text-center">
        <p class="text-sm font-semibold text-slate-800">Aucun PASS pour le moment</p>
        <p class="mt-1 text-sm text-slate-500">Exemple : un PASS JTAC qui exige une formation, un seuil d’heures et un avis N+1.</p>
        <a href="<?= $h(url('back-office/organisation/passes/create')) ?>" class="ath-btn ath-btn--solid mt-4 inline-flex">Créer le premier PASS</a>
    </div>
    <?php else: ?>
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-[11px] font-black uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">PASS</th>
                    <th class="px-4 py-3">Usages</th>
                    <th class="px-4 py-3">Logique</th>
                    <th class="px-4 py-3">État</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($list as $row): ?>
                <?php
                $usages = [];
                if (!empty($row['use_for_post'])) {
                    $usages[] = 'Poste';
                }
                if (!empty($row['use_for_advancement'])) {
                    $usages[] = 'Avancement';
                }
                if (!empty($row['use_for_notation'])) {
                    $usages[] = 'Notation';
                }
                ?>
                <tr class="border-t border-slate-100">
                    <td class="px-4 py-3">
                        <p class="font-semibold text-slate-900"><?= $h((string) ($row['label'] ?? '')) ?></p>
                        <p class="text-xs text-slate-500"><?= $h((string) ($row['code'] ?? '')) ?></p>
                    </td>
                    <td class="px-4 py-3 text-slate-600"><?= $h($usages !== [] ? implode(' · ', $usages) : '—') ?></td>
                    <td class="px-4 py-3 text-slate-600"><?= ((string) ($row['logic'] ?? 'all')) === 'any' ? 'Au moins une' : 'Toutes' ?></td>
                    <td class="px-4 py-3">
                        <?php if (!empty($row['is_active'])): ?>
                        <span class="rounded-md bg-emerald-50 px-2 py-0.5 text-[11px] font-bold uppercase text-emerald-800">Actif</span>
                        <?php else: ?>
                        <span class="rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-bold uppercase text-slate-600">Inactif</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <a href="<?= $h(url('back-office/organisation/passes/' . (int) $row['id'] . '/edit')) ?>" class="font-semibold text-emerald-800 hover:underline">Modifier</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
