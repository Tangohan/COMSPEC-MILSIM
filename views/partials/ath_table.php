<?php
declare(strict_types=1);

/**
 * Tableau standard ATHENA (toolbar, checkbox, pagination).
 *
 * @var string $athTableTitle
 * @var int|string|null $athTableCount
 * @var list<string|array{label: string, kind?: string}> $athTableCols
 * @var list<list<string>> $athTableRows
 * @var list<string> $athTableFilters
 * @var string $athTableMinWidth
 * @var string|null $athTableFoot
 * @var list<array{label: string, href?: string, active?: bool, disabled?: bool}>|null $athTablePager
 * @var bool $athTableShowCheckbox
 * @var string|null $athTableExportUrl
 * @var string|null $athTableFilterName
 * @var string|null $athTableFilterValue
 * @var list<string|null>|null $athTableRowHrefs
 * @var list<string|null>|null $athTableRowActions
 * @var string|null $athTableActionsLabel
 *
 * `$athTableRowActions` porte, pour chaque ligne, un fragment HTML d’action (formulaire
 * de validation, bouton de révocation…) rendu dans une dernière colonne. Ce fragment est
 * inséré **tel quel** : il doit être construit par la vue avec ses propres échappements,
 * et ne contenir que du balisage écrit côté serveur — jamais une valeur saisie par un
 * utilisateur sans passer par `htmlspecialchars()`.
 *
 * Le champ « Filtrer… » agit côté client sur les lignes rendues (insensible à la casse
 * et aux accents) : il affine ce que la page a déjà chargé, sans requête. Les pages qui
 * filtrent côté serveur gardent leur propre formulaire au-dessus du tableau et passent
 * `$athTableFilterValue` pour préremplir le champ.
 */

use App\Support\AthUi;

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$title = trim((string) ($athTableTitle ?? ''));
$rows = is_array($athTableRows ?? null) ? $athTableRows : [];
$rawCols = is_array($athTableCols ?? null) ? $athTableCols : [];
$filters = is_array($athTableFilters ?? null) ? $athTableFilters : [];
$minW = trim((string) ($athTableMinWidth ?? '1200px'));
$showCheck = ($athTableShowCheckbox ?? true) !== false;
$exportUrl = trim((string) ($athTableExportUrl ?? ''));
$filterName = trim((string) ($athTableFilterName ?? 'q'));
$filterValue = (string) ($athTableFilterValue ?? '');
$rowHrefs = is_array($athTableRowHrefs ?? null) ? $athTableRowHrefs : [];
$rowActions = is_array($athTableRowActions ?? null) ? $athTableRowActions : [];
$hasActions = $rowActions !== [];
$actionsLabel = trim((string) ($athTableActionsLabel ?? 'ACTION'));

$cols = [];
foreach ($rawCols as $col) {
    if (is_array($col)) {
        $cols[] = [
            'label' => (string) ($col['label'] ?? ''),
            'kind' => (string) ($col['kind'] ?? ''),
            'align' => (($col['kind'] ?? '') === 'r') ? 'right' : 'left',
        ];
    } else {
        $cols[] = AthUi::parseColumn((string) $col);
    }
}

$count = $athTableCount ?? count($rows);
if (is_numeric($count)) {
    $n = (int) $count;
    $countLabel = $n . ' ligne' . ($n > 1 ? 's' : '');
} else {
    $countLabel = (string) $count;
}

$foot = trim((string) ($athTableFoot ?? ''));
if ($foot === '') {
    $foot = 'Affichage 1 – ' . count($rows) . ' sur ' . (is_numeric($athTableCount ?? null) ? (string) (int) $athTableCount : (string) count($rows));
}

$pager = is_array($athTablePager ?? null) ? $athTablePager : null;

// Identifiant unique par tableau : plusieurs tableaux peuvent cohabiter dans une même
// page (ex. insights de présence). Un `static` ne conviendrait pas — il est réinitialisé
// à chaque `require` du même fichier dans un même appel, ce qui produirait des doublons.
$GLOBALS['__athTableSeq'] = (int) ($GLOBALS['__athTableSeq'] ?? 0) + 1;
$tableId = 'ath-table-' . $GLOBALS['__athTableSeq'];
?>
<div class="ath-table-panel ath-rise" data-ath-table-panel="<?= $h($tableId) ?>">
    <div class="ath-table-toolbar">
        <?php if ($title !== ''): ?>
        <span class="ath-table-toolbar__title"><?= $h($title) ?></span>
        <?php endif; ?>
        <span class="ath-table-toolbar__count" data-ath-count data-ath-count-base="<?= $h($countLabel) ?>"><?= $h($countLabel) ?></span>
        <span class="ath-table-toolbar__spacer" aria-hidden="true"></span>
        <label class="ath-table-toolbar__search">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#8c979b" stroke-width="2.2" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.2-3.2"></path></svg>
            <input type="search" name="<?= $h($filterName) ?>" value="<?= $h($filterValue) ?>" placeholder="Filtrer…" autocomplete="off" spellcheck="false" aria-label="Filtrer les lignes affichées" data-ath-filter>
        </label>
        <?php foreach ($filters as $filter): ?>
            <?php if (is_array($filter) && trim((string) ($filter['href'] ?? '')) !== ''): ?>
            <a href="<?= $h((string) $filter['href']) ?>" class="ath-table-toolbar__filter ath-btn"<?= !empty($filter['active']) ? ' aria-current="true"' : '' ?>>
                <?= $h((string) ($filter['label'] ?? '')) ?>
                <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#a9b3b7" stroke-width="3.2" stroke-linecap="round" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>
            </a>
            <?php else: ?>
            <button type="button" class="ath-table-toolbar__filter ath-btn" disabled>
                <?= $h(is_array($filter) ? (string) ($filter['label'] ?? '') : (string) $filter) ?>
                <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#a9b3b7" stroke-width="3.2" stroke-linecap="round" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>
            </button>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if ($exportUrl !== ''): ?>
        <a href="<?= $h($exportUrl) ?>" class="ath-table-toolbar__export ath-btn">Exporter CSV</a>
        <?php endif; ?>
    </div>
    <div class="ath-table-wrap">
        <table class="ath-table" style="min-width:<?= $h($minW) ?>">
            <thead>
                <tr>
                    <?php if ($showCheck): ?>
                    <th class="ath-th-check" scope="col"><span class="ath-table-check" aria-hidden="true"></span></th>
                    <?php endif; ?>
                    <?php foreach ($cols as $col): ?>
                    <th class="<?= $col['align'] === 'right' ? 'ath-th-num' : '' ?>" scope="col"><?= $h($col['label']) ?></th>
                    <?php endforeach; ?>
                    <?php if ($hasActions): ?>
                    <th class="ath-th-actions" scope="col"><?= $h($actionsLabel) ?></th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr>
                    <td colspan="<?= count($cols) + ($showCheck ? 1 : 0) + ($hasActions ? 1 : 0) ?>" class="ath-table-empty">Aucune donnée à afficher.</td>
                </tr>
            <?php endif; ?>
            <tr class="ath-row ath-row--filter-empty" hidden>
                <td colspan="<?= count($cols) + ($showCheck ? 1 : 0) + ($hasActions ? 1 : 0) ?>" class="ath-table-empty">Aucune ligne ne correspond au filtre.</td>
            </tr>
            <?php foreach ($rows as $ri => $row): ?>
                <?php
                $rowHref = $rowHrefs[$ri] ?? null;
                $rowHref = is_string($rowHref) ? trim($rowHref) : '';
                // Empreinte de recherche : toutes les cellules de la ligne, en minuscules.
                $searchBlob = '';
                foreach ($cols as $ci => $col) {
                    $searchBlob .= ' ' . (string) ($row[$ci] ?? '');
                }
                $searchBlob = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $searchBlob) ?? ''), 'UTF-8');
                ?>
                <tr class="ath-row<?= $rowHref !== '' ? ' ath-row--link' : '' ?>" data-ath-search="<?= $h($searchBlob) ?>"<?= $rowHref !== '' ? ' data-href="' . $h($rowHref) . '"' : '' ?>>
                    <?php if ($showCheck): ?>
                    <td class="ath-td-check"><span class="ath-table-check" aria-hidden="true"></span></td>
                    <?php endif; ?>
                    <?php foreach ($cols as $ci => $col): ?>
                        <?php
                        $val = (string) ($row[$ci] ?? '—');
                        $meta = AthUi::cellMeta($val, $col['kind']);
                        $tdClass = $meta['align'] === 'right' ? 'ath-td-num' : '';
                        ?>
                        <td class="<?= $tdClass ?>" style="text-align:<?= $h($meta['align']) ?>">
                            <span class="ath-cell<?= $meta['badge'] ? ' ath-cell--badge' : '' ?><?= $meta['mono'] ? ' ath-cell--mono' : '' ?>" style="color:<?= $h($meta['fg']) ?>;background:<?= $h($meta['bg']) ?>;border-color:<?= $h($meta['bd']) ?>;padding:<?= $h($meta['pad']) ?>;font-weight:<?= (int) $meta['weight'] ?>">
                                <?= $h($val) ?>
                            </span>
                        </td>
                    <?php endforeach; ?>
                    <?php if ($hasActions): ?>
                    <?php $action = $rowActions[$ri] ?? null; ?>
                    <td class="ath-td-actions">
                        <?php // Fragment déjà échappé par la vue appelante (cf. en-tête du partial). ?>
                        <?= is_string($action) && trim($action) !== '' ? $action : '<span class="ath-cell">—</span>' ?>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="ath-table-foot">
        <div class="ath-table-foot__meta"><?= $h($foot) ?></div>
        <?php if ($pager !== null && $pager !== []): ?>
        <div class="ath-pager">
            <?php foreach ($pager as $pg): ?>
                <?php
                $pgLabel = (string) ($pg['label'] ?? '');
                $pgHref = trim((string) ($pg['href'] ?? ''));
                $pgActive = !empty($pg['active']);
                $pgDisabled = !empty($pg['disabled']);
                $pgClass = 'ath-pager__btn' . ($pgActive ? ' is-active' : '') . ($pgDisabled ? ' is-disabled' : '');
                ?>
                <?php if ($pgHref !== '' && !$pgDisabled): ?>
                <a href="<?= $h($pgHref) ?>" class="<?= $pgClass ?>"><?= $h($pgLabel) ?></a>
                <?php else: ?>
                <span class="<?= $pgClass ?>"><?= $h($pgLabel) ?></span>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php if (empty($GLOBALS['__athTableScriptEmitted'])): $GLOBALS['__athTableScriptEmitted'] = true; ?>
<script>
/*
 * Comportements du tableau ATHENA, posés une seule fois par page : le partial peut être
 * inclus plusieurs fois (plusieurs tableaux), d'où la délégation sur `document` plutôt
 * qu'un branchement par ligne — sinon chaque inclusion rebranchait tous les tableaux
 * déjà présents et les clics se déclenchaient en double.
 */
(function () {
  if (window.__athTableBound) return;
  window.__athTableBound = true;

  var deburr = function (s) {
    return (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
  };

  var applyFilter = function (input) {
    var panel = input.closest('[data-ath-table-panel]');
    if (!panel) return;
    var q = deburr(input.value.trim());
    var rows = panel.querySelectorAll('tbody .ath-row[data-ath-search]');
    var shown = 0;
    rows.forEach(function (row) {
      var match = !q || deburr(row.getAttribute('data-ath-search')).indexOf(q) !== -1;
      row.hidden = !match;
      if (match) shown++;
    });
    var emptyRow = panel.querySelector('.ath-row--filter-empty');
    if (emptyRow) emptyRow.hidden = !(q && shown === 0);
    var count = panel.querySelector('[data-ath-count]');
    if (count) {
      count.textContent = q
        ? shown + ' / ' + rows.length + (rows.length > 1 ? ' lignes' : ' ligne')
        : (count.getAttribute('data-ath-count-base') || '');
    }
  };

  document.addEventListener('input', function (e) {
    var input = e.target.closest('[data-ath-filter]');
    if (input) applyFilter(input);
  });

  // Échap vide le filtre sans quitter le champ.
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    var input = e.target.closest('[data-ath-filter]');
    if (!input || input.value === '') return;
    input.value = '';
    applyFilter(input);
  });

  document.addEventListener('click', function (e) {
    if (e.target.closest('a, button, input, label, select, textarea')) return;
    var row = e.target.closest('.ath-row--link[data-href]');
    if (!row) return;
    var href = row.getAttribute('data-href');
    if (href) window.location.href = href;
  });
})();
</script>
<?php endif; ?>
