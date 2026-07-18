<?php

declare(strict_types=1);

use App\Support\RoleDoctrineUiLabels;

$roleDefinitions = is_array($roleDefinitions ?? null) ? $roleDefinitions : [];
$families = array_values(array_unique(array_filter(array_map(
    static fn (array $d): string => trim((string) ($d['family'] ?? '')),
    $roleDefinitions
))));
sort($families, SORT_NATURAL | SORT_FLAG_CASE);
?>
<style>
.rf-sheet { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.8125rem; }
.rf-sheet thead th {
    position: sticky; top: 0; z-index: 2;
    background: #0f172a; color: #f8fafc;
    font-size: 0.65rem; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase;
    text-align: left; padding: 0.75rem 0.9rem; white-space: nowrap;
}
.rf-sheet tbody td {
    padding: 0.8rem 0.9rem; vertical-align: middle;
    border-bottom: 1px solid #e2e8f0; border-right: 1px solid #f1f5f9;
    background: #fff; color: #0f172a;
}
.rf-sheet tbody td:last-child { border-right: none; }
.rf-sheet tbody tr:nth-child(even) td { background: #f8fafc; }
.rf-sheet tbody tr:hover td { background: #ecfdf5; }
.rf-sheet tbody tr.is-hidden { display: none; }
.rf-sheet .num { text-align: right; font-variant-numeric: tabular-nums; color: #94a3b8; }
</style>

<div class="min-h-[calc(100dvh-5rem)] bg-slate-50">
    <header class="border-b border-emerald-200/80 bg-gradient-to-br from-emerald-50 via-white to-slate-50">
        <div class="w-full px-4 sm:px-6 lg:px-8 py-6 sm:py-8 flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="min-w-0">
                <p class="text-[11px] font-black uppercase tracking-[0.22em] text-emerald-800/90">Back-office · Cellule S1</p>
                <h1 class="mt-2 text-2xl sm:text-3xl font-black tracking-tight text-slate-900">Catalogue des fonctions</h1>
                <p class="mt-2 max-w-3xl text-sm text-slate-600 leading-relaxed">
                    Tableur complet des fonctions de référence (noms, familles, descriptions) disponibles pour votre communauté.
                </p>
            </div>
            <div class="flex flex-wrap gap-2 shrink-0">
                <a href="<?= htmlspecialchars(url('back-office/roles-functions/referentiel'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-800 shadow-sm hover:bg-slate-50">Référentiel</a>
                <a href="<?= htmlspecialchars(url('back-office/roles-functions'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Doctrine</a>
            </div>
        </div>
    </header>

    <div class="w-full px-4 sm:px-6 lg:px-8 py-5 space-y-4">
        <div class="flex flex-wrap items-end gap-3">
            <label class="text-xs font-semibold text-slate-700 min-w-[14rem] flex-1">
                Recherche
                <input id="rf-cat-search" type="search" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none" placeholder="Nom, famille, description…">
            </label>
            <label class="text-xs font-semibold text-slate-700 w-full sm:w-56">
                Famille
                <select id="rf-cat-family" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none">
                    <option value="">Toutes les familles</option>
                    <?php foreach ($families as $fam): ?>
                        <option value="<?= htmlspecialchars($fam, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(RoleDoctrineUiLabels::definitionFamilyLabel($fam), ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <p class="text-sm text-slate-500 pb-2.5">
                <span id="rf-cat-count" class="font-bold tabular-nums text-slate-800"><?= count($roleDefinitions) ?></span>
                fonction<?= count($roleDefinitions) > 1 ? 's' : '' ?>
            </p>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="rf-sheet min-w-[56rem]" id="rf-cat-table">
                    <thead>
                        <tr>
                            <th style="width:3rem">#</th>
                            <th>Nom FR</th>
                            <th>Nom EN</th>
                            <th>Famille</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($roleDefinitions === []): ?>
                            <tr>
                                <td colspan="5" class="!bg-white px-4 py-16 text-center text-sm text-slate-500">Aucune fonction dans le catalogue.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($roleDefinitions as $idx => $d): ?>
                                <?php
                                $famRaw = trim((string) ($d['family'] ?? ''));
                                $desc = trim((string) ($d['description'] ?? ''));
                                $searchBlob = mb_strtolower(trim((string) (($d['slug'] ?? '') . ' ' . ($d['name_fr'] ?? '') . ' ' . ($d['name_us'] ?? '') . ' ' . $famRaw . ' ' . $desc . ' ' . RoleDoctrineUiLabels::definitionFamilyLabel($famRaw))), 'UTF-8');
                                ?>
                                <tr data-rf-row data-family="<?= htmlspecialchars($famRaw, ENT_QUOTES, 'UTF-8') ?>" data-search="<?= htmlspecialchars($searchBlob, ENT_QUOTES, 'UTF-8') ?>">
                                    <td class="num"><?= (int) ($idx + 1) ?></td>
                                    <td class="font-semibold"><?= htmlspecialchars((string) ($d['name_fr'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-slate-600"><?= htmlspecialchars((string) ($d['name_us'] ?? '') !== '' ? (string) $d['name_us'] : '—', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars(RoleDoctrineUiLabels::definitionFamilyLabel($famRaw), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-slate-600 max-w-[28rem]">
                                        <?php if ($desc !== ''): ?>
                                            <?= htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') ?>
                                        <?php else: ?>
                                            <span class="text-slate-400">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <p id="rf-cat-empty" class="hidden border-t border-amber-100 bg-amber-50 px-4 py-3 text-sm text-amber-900">Aucune entrée ne correspond au filtre.</p>
        </div>
    </div>
</div>
<script>
(function () {
  var search = document.getElementById('rf-cat-search');
  var family = document.getElementById('rf-cat-family');
  var countEl = document.getElementById('rf-cat-count');
  var empty = document.getElementById('rf-cat-empty');
  var rows = Array.prototype.slice.call(document.querySelectorAll('#rf-cat-table [data-rf-row]'));
  if (!rows.length || !search || !family) return;
  function apply() {
    var q = (search.value || '').trim().toLowerCase();
    var fam = family.value || '';
    var visible = 0;
    rows.forEach(function (row) {
      var okQ = !q || (row.getAttribute('data-search') || '').indexOf(q) !== -1;
      var okFam = !fam || (row.getAttribute('data-family') || '') === fam;
      var show = okQ && okFam;
      row.classList.toggle('is-hidden', !show);
      if (show) visible++;
    });
    if (countEl) countEl.textContent = String(visible);
    if (empty) empty.classList.toggle('hidden', visible !== 0);
  }
  search.addEventListener('input', apply);
  family.addEventListener('change', apply);
})();
</script>
