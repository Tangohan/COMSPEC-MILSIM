<?php
declare(strict_types=1);

/**
 * Catalogue des fonctions — rendu ATHENA.
 */

use App\Support\RoleDoctrineUiLabels;

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$roleDefinitions = is_array($roleDefinitions ?? null) ? $roleDefinitions : [];
$families = array_values(array_unique(array_filter(array_map(
    static fn (array $d): string => trim((string) ($d['family'] ?? '')),
    $roleDefinitions
))));
sort($families, SORT_NATURAL | SORT_FLAG_CASE);

$athKpis = [
    ['label' => 'FONCTIONS', 'value' => (string) count($roleDefinitions), 'delta' => '', 'tone' => '#0b8a5c', 'pct' => '100%', 'note' => 'référentiel'],
    ['label' => 'FAMILLES', 'value' => (string) count($families), 'delta' => '', 'tone' => '#1e4f80', 'pct' => '—', 'note' => 'catégories'],
    ['label' => 'SOURCE', 'value' => 'Global', 'delta' => '', 'tone' => '#1e4f80', 'pct' => '—', 'note' => 'plateforme'],
    ['label' => 'USAGE', 'value' => 'Doctrine', 'delta' => '', 'tone' => '#c98a12', 'pct' => '—', 'note' => 'rattachement rôles'],
];
require base_path('views/partials/ath_kpis.php');
?>

<div class="bo-rf ath-rise">
    <div class="ath-users-filters ath-rise">
        <a href="<?= $h(url('back-office/roles-functions')) ?>" class="ath-btn">Doctrine</a>
        <a href="<?= $h(url('back-office/roles-functions/referentiel')) ?>" class="ath-btn">Référentiel</a>
        <a href="<?= $h(url('back-office/roles-functions/catalogue')) ?>" class="ath-btn ath-btn--solid">Catalogue</a>
    </div>

    <form class="ath-users-filters ath-rise" id="rf-cat-filters" onsubmit="return false;">
        <div>
            <label class="ath-users-filters__label" for="rf-cat-search">Recherche</label>
            <input type="search" id="rf-cat-search" class="bo-setting-row__field" placeholder="Nom, famille, description…" autocomplete="off">
        </div>
        <div>
            <label class="ath-users-filters__label" for="rf-cat-family">Famille</label>
            <select id="rf-cat-family" class="bo-setting-row__field">
                <option value="">Toutes les familles</option>
                <?php foreach ($families as $fam): ?>
                <option value="<?= $h($fam) ?>"><?= $h(RoleDoctrineUiLabels::definitionFamilyLabel($fam)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <p class="ath-body" style="align-self:end;margin:0;">
            <span id="rf-cat-count" class="ath-cell ath-cell--badge" style="font-weight:800;"><?= count($roleDefinitions) ?></span>
            fonction<?= count($roleDefinitions) > 1 ? 's' : '' ?>
        </p>
    </form>

    <div class="ath-table-panel ath-rise">
        <div class="ath-table-toolbar">
            <span class="ath-table-toolbar__title">Catalogue des fonctions</span>
            <span class="ath-table-toolbar__count"><?= count($roleDefinitions) ?> entrée<?= count($roleDefinitions) > 1 ? 's' : '' ?></span>
        </div>
        <div class="ath-table-wrap">
            <table class="ath-table" id="rf-cat-table" style="min-width:1100px">
                <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Nom FR</th>
                        <th scope="col">Nom EN</th>
                        <th scope="col">Famille</th>
                        <th scope="col">Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($roleDefinitions === []): ?>
                    <tr><td colspan="5" class="ath-table-empty">Aucune fonction dans le catalogue.</td></tr>
                    <?php else: ?>
                    <?php foreach ($roleDefinitions as $idx => $d): ?>
                        <?php
                        $famRaw = trim((string) ($d['family'] ?? ''));
                        $desc = trim((string) ($d['description'] ?? ''));
                        $searchBlob = mb_strtolower(trim((string) (($d['slug'] ?? '') . ' ' . ($d['name_fr'] ?? '') . ' ' . ($d['name_us'] ?? '') . ' ' . $famRaw . ' ' . $desc . ' ' . RoleDoctrineUiLabels::definitionFamilyLabel($famRaw))), 'UTF-8');
                        ?>
                    <tr class="ath-row" data-rf-row data-family="<?= $h($famRaw) ?>" data-search="<?= $h($searchBlob) ?>">
                        <td class="ath-td-num"><span class="ath-cell ath-cell--mono"><?= (int) ($idx + 1) ?></span></td>
                        <td><span class="ath-cell" style="font-weight:800;"><?= $h((string) ($d['name_fr'] ?? '')) ?></span></td>
                        <td><?= $h((string) ($d['name_us'] ?? '') !== '' ? (string) $d['name_us'] : '—') ?></td>
                        <td><span class="ath-cell ath-cell--badge"><?= $h(RoleDoctrineUiLabels::definitionFamilyLabel($famRaw)) ?></span></td>
                        <td><?= $h($desc !== '' ? $desc : '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <p id="rf-cat-empty" class="hidden ath-table-foot__meta" style="padding:12px 16px;color:#9a3412;">Aucune entrée ne correspond au filtre.</p>
        <div class="ath-table-foot">
            <div class="ath-table-foot__meta">Affichage 1 – <?= count($roleDefinitions) ?> sur <?= count($roleDefinitions) ?></div>
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
      row.style.display = show ? '' : 'none';
      if (show) visible++;
    });
    if (countEl) countEl.textContent = String(visible);
    if (empty) empty.classList.toggle('hidden', visible !== 0 || rows.length === 0);
  }
  search.addEventListener('input', apply);
  family.addEventListener('change', apply);
})();
</script>
