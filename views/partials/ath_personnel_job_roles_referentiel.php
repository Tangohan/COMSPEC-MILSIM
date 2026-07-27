<?php
declare(strict_types=1);

$categories = $categories ?? [];
$roles = $roles ?? [];
$permCounts = $permCounts ?? [];
$flashSuccess = \App\Core\Session::getFlash('success');
$flashError = \App\Core\Session::getFlash('error');
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>

<div class="pjr-ath-body ath-rise">
    <?php if (empty($personnelProfilesJobRoleReady)): ?>
    <div class="bo-settings-flash bo-settings-flash--warn ath-rise" role="status">
        Les colonnes dossier ne sont pas encore en base : le référentiel est éditable, mais les attributions effectifs nécessitent la migration complète.
    </div>
    <?php endif; ?>

    <?php if ($flashSuccess): ?>
    <div class="bo-settings-flash bo-settings-flash--ok ath-rise" role="status"><?= $h((string) $flashSuccess) ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
    <div class="bo-settings-flash bo-settings-flash--err ath-rise" role="alert"><?= $h((string) $flashError) ?></div>
    <?php endif; ?>

    <details class="ath-roles-edit-item ath-rise">
        <summary class="pjr-assign-settings__summary">
            <span class="pjr-assign-settings__title">Nouvelle catégorie</span>
            <span class="pjr-assign-settings__hint">Arborescence du référentiel</span>
        </summary>
        <form method="post" action="<?= url('back-office/personnel-job-roles/categories/save') ?>" class="ath-roles-edit-form">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="category_id" value="0">
            <label class="ath-users-filters__label">Parent (optionnel)
                <select name="parent_id">
                    <option value="">— Racine —</option>
                    <?php foreach ($categories as $c): ?>
                    <option value="<?= (int) $c['id'] ?>"><?= $h((string) $c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="ath-users-filters__label">Nom
                <input type="text" name="category_name" required maxlength="120" placeholder="Ex. Renseignement">
            </label>
            <label class="ath-users-filters__label">Adresse courte <span class="bo-ta-form__opt">(facultatif)</span>
                <input type="text" name="category_slug" maxlength="80" placeholder="Laisser vide pour génération automatique">
            </label>
            <label class="ath-users-filters__label">Ordre
                <input type="number" name="category_sort_order" value="0">
            </label>
            <div class="pjr-assign-settings__actions">
                <button type="submit" class="ath-btn ath-btn--solid">Créer la catégorie</button>
            </div>
        </form>
    </details>

    <form method="get" class="ath-users-filters ath-rise" id="pjr-library-filters" onsubmit="return false;">
        <label class="ath-users-filters__label" for="pjr-library-search">Recherche
            <input id="pjr-library-search" type="search" placeholder="Nom, catégorie, référence…" autocomplete="off">
        </label>
        <label class="ath-users-filters__label" for="pjr-library-category">Catégorie
            <select id="pjr-library-category">
                <option value="">Toutes les catégories</option>
                <?php foreach ($categories as $c): ?>
                <option value="<?= $h((string) ($c['name'] ?? '')) ?>"><?= $h((string) ($c['name'] ?? '')) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>

    <?php
    $athTableRows = [];
    $athTableRowHrefs = [];
    foreach ($roles as $r) {
        $rid = (int) ($r['id'] ?? 0);
        $mc = trim((string) ($r['mos_code'] ?? ''));
        $mt = trim((string) ($r['mos_specialty_title'] ?? ''));
        $mos = $mc !== '' ? $mc . ($mt !== '' ? ' — ' . $mt : '') : '—';
        $athTableRows[] = [
            (string) ($r['category_name'] ?? '—'),
            (string) ($r['name'] ?? '—'),
            (string) ($r['slug'] ?? '—'),
            $mos,
            (string) (int) ($permCounts[(int) ($r['id'] ?? 0)] ?? 0),
            empty($r['is_system']) ? 'Modifier' : 'Modifier',
        ];
        $athTableRowHrefs[] = $rid > 0 ? url('back-office/personnel-job-roles/roles/' . $rid . '/edit') : null;
    }
    $athTableTitle = 'Référentiel des emplois';
    $athTableCount = count($roles);
    $athTableCols = ['CATÉGORIE', 'EMPLOI', 'RÉFÉRENCE|m', 'SPÉCIALITÉ US', 'DROITS|r', 'ACTION'];
    $athTableFilters = ['Catégorie'];
    $athTableMinWidth = '1080px';
    $athTableShowCheckbox = false;
    $athTableFoot = count($roles) > 0 ? count($roles) . ' emploi(s) au référentiel' : 'Aucun emploi';
    ?>

    <div class="ath-table-panel ath-rise" id="pjr-library-table">
        <div class="ath-table-toolbar">
            <span class="ath-table-toolbar__title"><?= $h($athTableTitle) ?></span>
            <span class="ath-table-toolbar__count" id="pjr-library-count"><?= count($roles) ?> ligne<?= count($roles) > 1 ? 's' : '' ?></span>
        </div>
        <div class="ath-table-wrap">
            <table class="ath-table" style="min-width:<?= $h($athTableMinWidth) ?>">
                <thead>
                    <tr>
                        <?php foreach ($athTableCols as $col): ?>
                        <?php $parsed = \App\Support\AthUi::parseColumn($col); ?>
                        <th class="<?= $parsed['align'] === 'right' ? 'ath-th-num' : '' ?>" scope="col"><?= $h($parsed['label']) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($roles as $ri => $r): ?>
                    <?php
                    $rid = (int) ($r['id'] ?? 0);
                    $mc = trim((string) ($r['mos_code'] ?? ''));
                    $mt = trim((string) ($r['mos_specialty_title'] ?? ''));
                    $mos = $mc !== '' ? $mc . ($mt !== '' ? ' — ' . $mt : '') : '—';
                    $searchBlob = mb_strtolower(trim((string) (($r['category_name'] ?? '') . ' ' . ($r['name'] ?? '') . ' ' . ($r['slug'] ?? '') . ' ' . $mc . ' ' . $mt)), 'UTF-8');
                    $href = $rid > 0 ? url('back-office/personnel-job-roles/roles/' . $rid . '/edit') : '';
                    ?>
                    <tr class="ath-row<?= $href !== '' ? ' ath-row--link' : '' ?>" data-pjr-row data-category="<?= $h((string) ($r['category_name'] ?? '')) ?>" data-search="<?= $h($searchBlob) ?>"<?= $href !== '' ? ' data-href="' . $h($href) . '"' : '' ?>>
                        <td><?= $h((string) ($r['category_name'] ?? '—')) ?></td>
                        <td><span class="ath-cell" style="font-weight:800"><?= $h((string) ($r['name'] ?? '')) ?></span></td>
                        <td class="ath-td-num"><span class="ath-cell ath-cell--mono"><?= $h((string) ($r['slug'] ?? '')) ?></span></td>
                        <td><?= $h($mos) ?></td>
                        <td class="ath-td-num"><?= (int) ($permCounts[$rid] ?? 0) ?></td>
                        <td>
                            <?php if ($rid > 0): ?>
                            <a href="<?= $h(url('back-office/personnel-job-roles/roles/' . $rid . '/edit')) ?>" class="ath-btn">Modifier</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($roles === []): ?>
                    <tr><td colspan="6" class="ath-table-empty">Aucun emploi métier — créez une catégorie puis un emploi.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <p id="pjr-library-empty" class="bo-rf__empty hidden">Aucun emploi ne correspond à ce filtre.</p>
    </div>

    <?php if (!empty($categories)): ?>
    <details class="ath-roles-edit-item ath-rise">
        <summary class="pjr-assign-settings__summary">
            <span class="pjr-assign-settings__title">Supprimer une catégorie vide</span>
            <span class="pjr-assign-settings__hint">Uniquement si aucune sous-catégorie ni emploi n’y est rattaché</span>
        </summary>
        <ul class="bo-rf__units-list">
            <?php foreach ($categories as $c): ?>
            <li>
                <form action="<?= url('back-office/personnel-job-roles/categories/' . (int) $c['id'] . '/delete') ?>" method="post" class="inline" onsubmit="return confirm('Supprimer cette catégorie ?');">
                    <?= \App\Core\Csrf::field() ?>
                    <button type="submit" class="ath-btn"><?= $h((string) $c['name']) ?></button>
                </form>
            </li>
            <?php endforeach; ?>
        </ul>
    </details>
    <?php endif; ?>
</div>
<script>
(function () {
  var search = document.getElementById('pjr-library-search');
  var cat = document.getElementById('pjr-library-category');
  var rows = Array.prototype.slice.call(document.querySelectorAll('[data-pjr-row]'));
  var empty = document.getElementById('pjr-library-empty');
  var countEl = document.getElementById('pjr-library-count');
  if (!search || !cat || !rows.length) return;
  function apply() {
    var q = (search.value || '').trim().toLowerCase();
    var c = cat.value || '';
    var visible = 0;
    rows.forEach(function (row) {
      var okQ = !q || (row.getAttribute('data-search') || '').indexOf(q) !== -1;
      var okC = !c || (row.getAttribute('data-category') || '') === c;
      var show = okQ && okC;
      row.style.display = show ? '' : 'none';
      if (show) visible++;
    });
    if (empty) empty.classList.toggle('hidden', visible !== 0);
    if (countEl) countEl.textContent = visible + ' ligne' + (visible > 1 ? 's' : '');
  }
  search.addEventListener('input', apply);
  cat.addEventListener('change', apply);
  document.querySelectorAll('.ath-row--link[data-href]').forEach(function (row) {
    row.addEventListener('click', function (e) {
      if (e.target.closest('a, button, input, label, form')) return;
      var href = row.getAttribute('data-href');
      if (href) window.location.href = href;
    });
  });
})();
</script>
