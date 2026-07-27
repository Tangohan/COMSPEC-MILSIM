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
?>
<div class="ath-table-panel ath-rise">
    <div class="ath-table-toolbar">
        <?php if ($title !== ''): ?>
        <span class="ath-table-toolbar__title"><?= $h($title) ?></span>
        <?php endif; ?>
        <span class="ath-table-toolbar__count"><?= $h($countLabel) ?></span>
        <span class="ath-table-toolbar__spacer" aria-hidden="true"></span>
        <label class="ath-table-toolbar__search">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#8c979b" stroke-width="2.2" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.2-3.2"></path></svg>
            <input type="search" name="<?= $h($filterName) ?>" value="<?= $h($filterValue) ?>" placeholder="Filtrer…" autocomplete="off" spellcheck="false" aria-label="Filtrer le tableau">
        </label>
        <?php foreach ($filters as $filter): ?>
        <button type="button" class="ath-table-toolbar__filter ath-btn">
            <?= $h((string) $filter) ?>
            <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#a9b3b7" stroke-width="3.2" stroke-linecap="round" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>
        </button>
        <?php endforeach; ?>
        <?php if ($exportUrl !== ''): ?>
        <a href="<?= $h($exportUrl) ?>" class="ath-table-toolbar__export ath-btn">Exporter CSV</a>
        <?php else: ?>
        <button type="button" class="ath-table-toolbar__export ath-btn">Exporter CSV</button>
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
                </tr>
            </thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr>
                    <td colspan="<?= count($cols) + ($showCheck ? 1 : 0) ?>" class="ath-table-empty">Aucune donnée à afficher.</td>
                </tr>
            <?php endif; ?>
            <?php foreach ($rows as $ri => $row): ?>
                <?php
                $rowHref = $rowHrefs[$ri] ?? null;
                $rowHref = is_string($rowHref) ? trim($rowHref) : '';
                ?>
                <tr class="ath-row<?= $rowHref !== '' ? ' ath-row--link' : '' ?>"<?= $rowHref !== '' ? ' data-href="' . $h($rowHref) . '"' : '' ?>>
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
<script>
(function () {
  document.querySelectorAll('.ath-row--link[data-href]').forEach(function (row) {
    row.addEventListener('click', function (e) {
      if (e.target.closest('a, button, input, label')) return;
      var href = row.getAttribute('data-href');
      if (href) window.location.href = href;
    });
  });
})();
</script>
