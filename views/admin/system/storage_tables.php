<?php
declare(strict_types=1);

use App\Services\Platform\PlatformStorageService;

/** @var list<array{name: string, rows: int, bytes: int, engine: string, comment: string, protected: bool, read_only: bool}> $tables */
/** @var string $search */

$tables = is_array($tables ?? null) ? $tables : [];
$search = (string) ($search ?? '');
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$totalBytes = array_sum(array_column($tables, 'bytes'));
$storageTab = 'data';
?>
<div class="max-w-6xl mx-auto px-4 sm:px-6 py-10 space-y-6">
    <header class="pa-head">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-amber-800 mb-1">Administration du site — toutes les communautés</p>
        <h1 class="text-2xl font-black text-slate-900">Explorateur de données</h1>
        <p class="mt-2 text-sm text-slate-600 leading-relaxed max-w-3xl">
            Toutes les tables de la base : ouvrez-en une pour chercher, lire, modifier ou supprimer des lignes, ou la vider.
            Les mots de passe, jetons et secrets restent masqués. Les comptes, communautés et droits ne peuvent pas être vidés en bloc ;
            le journal d’audit est en lecture seule. Chaque modification est inscrite au journal d’audit.
        </p>
    </header>
    <?php require base_path('views/admin/system/partials/storage_nav.php'); ?>

    <form method="get" action="<?= $h(url('admin/system/storage/donnees')) ?>" class="flex flex-wrap items-center gap-2">
        <label class="sr-only" for="tbl-search">Rechercher une table</label>
        <input id="tbl-search" type="search" name="q" value="<?= $h($search) ?>" placeholder="Nom de table (ex. atak_, users, enlist…)" class="min-w-[16rem] flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm" autofocus>
        <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Chercher</button>
        <?php if ($search !== ''): ?><a href="<?= $h(url('admin/system/storage/donnees')) ?>" class="text-sm text-slate-600 hover:underline">Tout afficher</a><?php endif; ?>
        <span class="ml-auto text-xs text-slate-500 tabular-nums"><?= count($tables) ?> table(s) · <?= $h(PlatformStorageService::formatBytes($totalBytes)) ?></span>
    </form>

    <?php if ($tables === []): ?>
        <p class="rounded-xl border border-dashed border-slate-300 bg-white p-6 text-center text-sm text-slate-600">Aucune table ne correspond.</p>
    <?php else: ?>
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full text-sm" data-sort-table>
                <thead class="bg-slate-50">
                    <tr class="text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        <th class="px-4 py-2.5"><button type="button" data-sort="text">Table</button></th>
                        <th class="px-4 py-2.5 text-right"><button type="button" data-sort="num">Lignes (approx.)</button></th>
                        <th class="px-4 py-2.5 text-right"><button type="button" data-sort="num">Taille</button></th>
                        <th class="px-4 py-2.5">Statut</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($tables as $t): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-2" data-v="<?= $h($t['name']) ?>">
                                <a href="<?= $h(url('admin/system/storage/donnees/' . rawurlencode($t['name']))) ?>" class="font-mono font-semibold text-slate-900 hover:text-emerald-700 hover:underline"><?= $h($t['name']) ?></a>
                                <?php if ($t['comment'] !== ''): ?><span class="block text-xs text-slate-500"><?= $h($t['comment']) ?></span><?php endif; ?>
                            </td>
                            <td class="px-4 py-2 text-right tabular-nums text-slate-700" data-v="<?= (int) $t['rows'] ?>"><?= number_format($t['rows'], 0, ',', ' ') ?></td>
                            <td class="px-4 py-2 text-right tabular-nums text-slate-700" data-v="<?= (int) $t['bytes'] ?>"><?= $h(PlatformStorageService::formatBytes($t['bytes'])) ?></td>
                            <td class="px-4 py-2" data-v="<?= $t['read_only'] ? 2 : ($t['protected'] ? 1 : 0) ?>">
                                <?php if ($t['read_only']): ?>
                                    <span class="rounded bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-700">Lecture seule</span>
                                <?php elseif ($t['protected']): ?>
                                    <span class="rounded bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-900">Protégée</span>
                                <?php else: ?>
                                    <span class="text-[11px] text-slate-400">Modifiable</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<script>
(function () {
    var table = document.querySelector('[data-sort-table]');
    if (!table) { return; }
    var body = table.tBodies[0];
    table.querySelectorAll('[data-sort]').forEach(function (btn, idx) {
        var dir = 1;
        btn.addEventListener('click', function () {
            var num = btn.getAttribute('data-sort') === 'num';
            var rows = Array.prototype.slice.call(body.rows);
            rows.sort(function (a, b) {
                var x = a.cells[idx].getAttribute('data-v') || '', y = b.cells[idx].getAttribute('data-v') || '';
                return (num ? (parseFloat(x) - parseFloat(y)) : x.localeCompare(y)) * dir;
            });
            dir = -dir;
            rows.forEach(function (r) { body.appendChild(r); });
        });
    });
})();
</script>
