<?php
declare(strict_types=1);

/**
 * Référentiel des fonctions (liens doctrinaux) — rendu ATHENA.
 */

use App\Support\RoleDoctrineUiLabels;

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$roleDefinitions = is_array($roleDefinitions ?? null) ? $roleDefinitions : [];
$definitionRelations = is_array($definitionRelations ?? null) ? $definitionRelations : [];

$defNameBySlug = [];
foreach ($roleDefinitions as $d) {
    $slug = trim((string) ($d['slug'] ?? ''));
    if ($slug !== '') {
        $defNameBySlug[$slug] = trim((string) ($d['name_fr'] ?? '')) ?: $slug;
    }
}

$relationTypes = [];
foreach ($definitionRelations as $dr) {
    $t = (string) ($dr['relation_type'] ?? '');
    if ($t !== '') {
        $relationTypes[$t] = RoleDoctrineUiLabels::relationTypeShort($t);
    }
}
asort($relationTypes, SORT_NATURAL | SORT_FLAG_CASE);

$athKpis = [
    ['label' => 'LIENS', 'value' => (string) count($definitionRelations), 'delta' => '', 'tone' => '#1e4f80', 'pct' => '100%', 'note' => 'doctrine'],
    ['label' => 'FONCTIONS', 'value' => (string) count($roleDefinitions), 'delta' => '', 'tone' => '#0b8a5c', 'pct' => '100%', 'note' => 'catalogue'],
    ['label' => 'NATURES', 'value' => (string) count($relationTypes), 'delta' => '', 'tone' => '#c98a12', 'pct' => '—', 'note' => 'types de lien'],
    ['label' => 'SOURCE', 'value' => 'Global', 'delta' => '', 'tone' => '#1e4f80', 'pct' => '—', 'note' => 'référentiel plateforme'],
];
require base_path('views/partials/ath_kpis.php');
?>

<div class="bo-rf ath-rise">
    <div class="ath-users-filters ath-rise">
        <a href="<?= $h(url('back-office/roles-functions')) ?>" class="ath-btn">Doctrine</a>
        <a href="<?= $h(url('back-office/roles-functions/referentiel')) ?>" class="ath-btn ath-btn--solid">Référentiel</a>
        <a href="<?= $h(url('back-office/roles-functions/catalogue')) ?>" class="ath-btn">Catalogue</a>
    </div>

    <form class="ath-users-filters ath-rise" id="rf-ref-filters" onsubmit="return false;">
        <div>
            <label class="ath-users-filters__label" for="rf-ref-search">Recherche</label>
            <input type="search" id="rf-ref-search" class="bo-setting-row__field" placeholder="Fonction source, cible, nature…" autocomplete="off">
        </div>
        <div>
            <label class="ath-users-filters__label" for="rf-ref-type">Nature du lien</label>
            <select id="rf-ref-type" class="bo-setting-row__field">
                <option value="">Toutes</option>
                <?php foreach ($relationTypes as $typeKey => $typeLabel): ?>
                <option value="<?= $h($typeKey) ?>"><?= $h($typeLabel) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <p class="ath-body" style="align-self:end;margin:0;">
            <span id="rf-ref-count" class="ath-cell ath-cell--badge" style="font-weight:800;"><?= count($definitionRelations) ?></span>
            lien<?= count($definitionRelations) > 1 ? 's' : '' ?>
        </p>
    </form>

    <div class="ath-table-panel ath-rise">
        <div class="ath-table-toolbar">
            <span class="ath-table-toolbar__title">Liens doctrinaux</span>
            <span class="ath-table-toolbar__count"><?= count($definitionRelations) ?> ligne<?= count($definitionRelations) > 1 ? 's' : '' ?></span>
        </div>
        <div class="ath-table-wrap">
            <table class="ath-table" id="rf-ref-table" style="min-width:960px">
                <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Fonction source</th>
                        <th scope="col">Fonction cible</th>
                        <th scope="col">Nature du lien</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($definitionRelations === []): ?>
                    <tr><td colspan="4" class="ath-table-empty">Aucune relation doctrinale n’est encore enregistrée.</td></tr>
                    <?php else: ?>
                    <?php foreach ($definitionRelations as $idx => $dr): ?>
                        <?php
                        $fs = trim((string) ($dr['from_slug'] ?? ''));
                        $ts = trim((string) ($dr['to_slug'] ?? ''));
                        $drt = (string) ($dr['relation_type'] ?? '');
                        $fromName = $defNameBySlug[$fs] ?? $fs;
                        $toName = $defNameBySlug[$ts] ?? $ts;
                        $typeLabel = RoleDoctrineUiLabels::relationTypeShort($drt);
                        $searchBlob = mb_strtolower(trim($fromName . ' ' . $toName . ' ' . $typeLabel . ' ' . $fs . ' ' . $ts), 'UTF-8');
                        ?>
                    <tr class="ath-row" data-rf-row data-type="<?= $h($drt) ?>" data-search="<?= $h($searchBlob) ?>">
                        <td class="ath-td-num"><span class="ath-cell ath-cell--mono"><?= (int) ($idx + 1) ?></span></td>
                        <td><span class="ath-cell" style="font-weight:800;"><?= $h($fromName) ?></span></td>
                        <td><?= $h($toName) ?></td>
                        <td><span class="ath-cell ath-cell--badge"><?= $h($typeLabel) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <p id="rf-ref-empty" class="hidden ath-table-foot__meta" style="padding:12px 16px;color:#9a3412;">Aucun lien ne correspond au filtre.</p>
        <div class="ath-table-foot">
            <div class="ath-table-foot__meta">Affichage 1 – <?= count($definitionRelations) ?> sur <?= count($definitionRelations) ?></div>
        </div>
    </div>
</div>
<script>
(function () {
  var search = document.getElementById('rf-ref-search');
  var typeSel = document.getElementById('rf-ref-type');
  var countEl = document.getElementById('rf-ref-count');
  var empty = document.getElementById('rf-ref-empty');
  var rows = Array.prototype.slice.call(document.querySelectorAll('#rf-ref-table [data-rf-row]'));
  if (!rows.length || !search || !typeSel) return;
  function apply() {
    var q = (search.value || '').trim().toLowerCase();
    var t = typeSel.value || '';
    var visible = 0;
    rows.forEach(function (row) {
      var okQ = !q || (row.getAttribute('data-search') || '').indexOf(q) !== -1;
      var okT = !t || (row.getAttribute('data-type') || '') === t;
      var show = okQ && okT;
      row.style.display = show ? '' : 'none';
      if (show) visible++;
    });
    if (countEl) countEl.textContent = String(visible);
    if (empty) empty.classList.toggle('hidden', visible !== 0 || rows.length === 0);
  }
  search.addEventListener('input', apply);
  typeSel.addEventListener('change', apply);
})();
</script>
