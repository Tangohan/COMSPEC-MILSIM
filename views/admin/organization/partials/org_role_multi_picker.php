<?php
declare(strict_types=1);

use App\Support\OrganizationRoleLabels;

/**
 * Sélecteur multi-rôles compact (recherche + liste déroulante + pastilles).
 *
 * @var list<array<string, mixed>> $roles
 * @var list<int> $selectedRoleIds
 * @var string $organizationRoleLabelMode
 * @var array{roles?: list<array>, permissions?: list<array>, byRole?: array} $roleMatrix
 * @var string $matrixRootId
 * @var string $pickerId
 * @var string $inputName
 * @var bool $showMatrix
 * @var bool $matrixOpen
 */
$roles = $roles ?? [];
$selectedRoleIds = array_values(array_unique(array_map('intval', $selectedRoleIds ?? [])));
$organizationRoleLabelMode = $organizationRoleLabelMode ?? OrganizationRoleLabels::MODE_FR;
$roleMatrix = $roleMatrix ?? ['roles' => [], 'permissions' => [], 'byRole' => []];
$matrixRootId = isset($matrixRootId) && is_string($matrixRootId) && $matrixRootId !== ''
    ? $matrixRootId
    : 'role-matrix-wrap';
$pickerId = isset($pickerId) && is_string($pickerId) && $pickerId !== ''
    ? $pickerId
    : 'org-role-picker';
$inputName = isset($inputName) && is_string($inputName) && $inputName !== ''
    ? $inputName
    : 'role_ids[]';
if (!isset($showMatrix)) {
    $showMatrix = true;
} else {
    $showMatrix = (bool) $showMatrix;
}
$matrixOpen = !empty($matrixOpen);

$rolesByLayer = ['community' => [], 'intra' => [], 'other' => []];
$catalog = [];
foreach ($roles as $r) {
    $rid = (int) ($r['id'] ?? 0);
    if ($rid < 1) {
        continue;
    }
    $ly = (string) ($r['role_layer'] ?? 'community');
    if ($ly !== 'community' && $ly !== 'intra') {
        $ly = 'other';
    }
    $disp = OrganizationRoleLabels::displayName($r, $organizationRoleLabelMode);
    $label = $disp !== '' ? $disp : 'Rôle sans intitulé';
    $hint = trim((string) ($r['description'] ?? ''));
    $item = [
        'id' => $rid,
        'layer' => $ly,
        'label' => $label,
        'hint' => $hint,
        'search' => mb_strtolower($label . ' ' . $hint, 'UTF-8'),
    ];
    $rolesByLayer[$ly][] = $item;
    $catalog[] = $item;
}

$layerFilterOptions = [];
foreach (['community', 'intra', 'other'] as $ly) {
    if ($rolesByLayer[$ly] === []) {
        continue;
    }
    $layerFilterOptions[] = [
        'value' => $ly,
        'label' => OrganizationRoleLabels::layerGroupLabel($ly, $organizationRoleLabelMode),
        'count' => count($rolesByLayer[$ly]),
    ];
}

$selectedSet = array_fill_keys($selectedRoleIds, true);
$catalogById = [];
foreach ($catalog as $item) {
    $catalogById[(int) $item['id']] = $item;
}

$catalogJson = json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP);
if ($catalogJson === false) {
    $catalogJson = '[]';
}
$selectedJson = json_encode($selectedRoleIds, JSON_UNESCAPED_UNICODE);
if ($selectedJson === false) {
    $selectedJson = '[]';
}
?>
<div
    id="<?= htmlspecialchars($pickerId, ENT_QUOTES, 'UTF-8') ?>"
    class="org-role-picker"
    data-org-role-picker
    data-input-name="<?= htmlspecialchars($inputName, ENT_QUOTES, 'UTF-8') ?>"
    data-matrix-id="<?= htmlspecialchars($matrixRootId, ENT_QUOTES, 'UTF-8') ?>"
    data-catalog="<?= htmlspecialchars($catalogJson, ENT_QUOTES, 'UTF-8') ?>"
    data-selected="<?= htmlspecialchars($selectedJson, ENT_QUOTES, 'UTF-8') ?>"
>
    <div class="org-role-picker__toolbar">
        <?php if (count($layerFilterOptions) > 1): ?>
        <div>
            <label for="<?= htmlspecialchars($pickerId, ENT_QUOTES, 'UTF-8') ?>-layer" class="org-role-picker__label">Famille</label>
            <select id="<?= htmlspecialchars($pickerId, ENT_QUOTES, 'UTF-8') ?>-layer" class="org-role-picker__control" data-role-layer>
                <option value="">Toutes (<?= count($catalog) ?>)</option>
                <?php foreach ($layerFilterOptions as $lof): ?>
                <option value="<?= htmlspecialchars($lof['value'], ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($lof['label'], ENT_QUOTES, 'UTF-8') ?> (<?= (int) $lof['count'] ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="org-role-picker__grow">
            <label for="<?= htmlspecialchars($pickerId, ENT_QUOTES, 'UTF-8') ?>-search" class="org-role-picker__label">Rechercher</label>
            <input
                type="search"
                id="<?= htmlspecialchars($pickerId, ENT_QUOTES, 'UTF-8') ?>-search"
                class="org-role-picker__control"
                placeholder="Nom ou description du rôle…"
                autocomplete="off"
                data-role-search
            >
        </div>
    </div>

    <div class="org-role-picker__add-row">
        <div class="org-role-picker__grow">
            <label for="<?= htmlspecialchars($pickerId, ENT_QUOTES, 'UTF-8') ?>-select" class="org-role-picker__label">Ajouter un rôle</label>
            <select id="<?= htmlspecialchars($pickerId, ENT_QUOTES, 'UTF-8') ?>-select" class="org-role-picker__control" data-role-select>
                <option value="">Choisir un rôle…</option>
                <?php foreach (['community', 'intra', 'other'] as $ly): ?>
                    <?php if ($rolesByLayer[$ly] === []) {
                        continue;
                    } ?>
                    <optgroup
                        label="<?= htmlspecialchars(OrganizationRoleLabels::layerGroupLabel($ly, $organizationRoleLabelMode), ENT_QUOTES, 'UTF-8') ?>"
                        data-layer="<?= htmlspecialchars($ly, ENT_QUOTES, 'UTF-8') ?>"
                    >
                        <?php foreach ($rolesByLayer[$ly] as $item): ?>
                        <option
                            value="<?= (int) $item['id'] ?>"
                            data-layer="<?= htmlspecialchars((string) $item['layer'], ENT_QUOTES, 'UTF-8') ?>"
                            data-search="<?= htmlspecialchars((string) $item['search'], ENT_QUOTES, 'UTF-8') ?>"
                            title="<?= htmlspecialchars((string) ($item['hint'] !== '' ? $item['hint'] : $item['label']), ENT_QUOTES, 'UTF-8') ?>"
                            <?= isset($selectedSet[(int) $item['id']]) ? 'disabled hidden' : '' ?>
                        ><?= htmlspecialchars((string) $item['label'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="button" class="org-role-picker__add-btn" data-role-add>Ajouter</button>
    </div>
    <p class="org-role-picker__meta" data-role-meta></p>
    <p class="org-role-picker__hint" data-role-hint hidden></p>
    <p class="org-role-picker__empty" data-role-empty hidden>Aucun rôle ne correspond à cette recherche.</p>

    <div class="org-role-picker__chips" data-role-chips aria-live="polite">
        <?php foreach ($selectedRoleIds as $sid):
            $item = $catalogById[$sid] ?? null;
            if ($item === null) {
                continue;
            }
            ?>
        <span class="org-role-picker__chip" data-role-id="<?= $sid ?>">
            <input type="hidden" name="<?= htmlspecialchars($inputName, ENT_QUOTES, 'UTF-8') ?>" value="<?= $sid ?>" class="role-pick" data-role-name="<?= htmlspecialchars((string) $item['label'], ENT_QUOTES, 'UTF-8') ?>">
            <span class="org-role-picker__chip-label"><?= htmlspecialchars((string) $item['label'], ENT_QUOTES, 'UTF-8') ?></span>
            <button type="button" class="org-role-picker__chip-remove" data-role-remove="<?= $sid ?>" aria-label="Retirer <?= htmlspecialchars((string) $item['label'], ENT_QUOTES, 'UTF-8') ?>">×</button>
        </span>
        <?php endforeach; ?>
    </div>
    <p class="org-role-picker__none" data-role-none <?= $selectedRoleIds !== [] ? 'hidden' : '' ?>>Aucun rôle sélectionné pour l’instant.</p>

    <?php if ($showMatrix && !empty($roleMatrix['permissions'])): ?>
    <details class="org-role-picker__matrix" id="<?= htmlspecialchars($matrixRootId, ENT_QUOTES, 'UTF-8') ?>" <?= $matrixOpen ? 'open' : '' ?>>
        <summary class="org-role-picker__matrix-summary">
            Aperçu des droits cumulés
            <span class="org-role-picker__matrix-badge">replié par défaut</span>
        </summary>
        <div class="org-role-picker__matrix-body">
            <p class="org-role-picker__matrix-cap">Selon les rôles ajoutés ci-dessus (union des habilitations).</p>
            <div class="org-role-picker__matrix-scroll">
                <table class="org-role-picker__table">
                    <thead>
                        <tr>
                            <th>Droit</th>
                            <?php foreach ($roleMatrix['roles'] as $rr): ?>
                            <th class="role-col" data-role-id="<?= (int) $rr['id'] ?>"><?= htmlspecialchars(OrganizationRoleLabels::displayName($rr, $organizationRoleLabelMode), ENT_QUOTES, 'UTF-8') ?></th>
                            <?php endforeach; ?>
                            <th>Cumulé</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($roleMatrix['permissions'] as $p):
                            $pid = (int) ($p['id'] ?? 0);
                            $mod = trim((string) ($p['module'] ?? ''));
                            ?>
                        <tr class="perm-row" data-perm-id="<?= $pid ?>">
                            <td>
                                <span class="org-role-picker__perm-name"><?= htmlspecialchars((string) ($p['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                <?php if ($mod !== ''): ?>
                                <span class="org-role-picker__perm-mod"><?= htmlspecialchars($mod, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </td>
                            <?php foreach ($roleMatrix['roles'] as $rr):
                                $rid = (int) $rr['id'];
                                $has = !empty($roleMatrix['byRole'][$rid][$pid]);
                                ?>
                            <td class="role-cell" data-role-id="<?= $rid ?>" data-perm-id="<?= $pid ?>"><?= $has ? '✓' : '—' ?></td>
                            <?php endforeach; ?>
                            <td class="union-cell" data-perm-id="<?= $pid ?>">—</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </details>
    <?php endif; ?>
</div>
<?php if (empty($GLOBALS['__org_role_picker_assets'])): ?>
<?php $GLOBALS['__org_role_picker_assets'] = true; ?>
<style>
.org-role-picker { display: flex; flex-direction: column; gap: 0.75rem; }
.org-role-picker__toolbar,
.org-role-picker__add-row { display: flex; flex-wrap: wrap; gap: 0.65rem; align-items: flex-end; }
.org-role-picker__grow { flex: 1 1 12rem; min-width: 0; }
.org-role-picker__label { display: block; margin-bottom: 0.25rem; font-size: 0.75rem; font-weight: 600; color: #334155; }
.org-role-picker__control {
  display: block; width: 100%; border: 1px solid #cbd5e1; border-radius: 0.5rem;
  background: #fff; padding: 0.5rem 0.7rem; font-size: 0.875rem; color: #0f172a;
}
.org-role-picker__control:focus { outline: none; border-color: #64748b; box-shadow: 0 0 0 3px rgba(100, 116, 139, 0.2); }
.org-role-picker__add-btn {
  flex: 0 0 auto; border-radius: 0.5rem; border: 1px solid #0f172a; background: #0f172a;
  color: #fff; font-size: 0.8125rem; font-weight: 700; padding: 0.55rem 0.9rem; cursor: pointer;
}
.org-role-picker__add-btn:hover { background: #1e293b; }
.org-role-picker__meta,
.org-role-picker__hint,
.org-role-picker__empty,
.org-role-picker__none { margin: 0; font-size: 0.75rem; color: #64748b; }
.org-role-picker__hint { color: #475569; line-height: 1.4; }
.org-role-picker__empty { color: #b45309; }
.org-role-picker__chips { display: flex; flex-wrap: wrap; gap: 0.4rem; min-height: 0.25rem; }
.org-role-picker__chip {
  display: inline-flex; align-items: center; gap: 0.35rem; max-width: 100%;
  border: 1px solid #cbd5e1; border-radius: 999px; background: #f8fafc;
  padding: 0.25rem 0.35rem 0.25rem 0.65rem; font-size: 0.75rem; font-weight: 600; color: #0f172a;
}
.org-role-picker__chip-label { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.org-role-picker__chip-remove {
  border: 0; background: transparent; color: #64748b; cursor: pointer;
  font-size: 1rem; line-height: 1; width: 1.35rem; height: 1.35rem; border-radius: 999px;
}
.org-role-picker__chip-remove:hover { background: #e2e8f0; color: #0f172a; }
.org-role-picker__matrix {
  border: 1px solid #e2e8f0; border-radius: 0.75rem; background: #fff; overflow: hidden;
}
.org-role-picker__matrix-summary {
  cursor: pointer; list-style: none; display: flex; align-items: center; justify-content: space-between; gap: 0.75rem;
  padding: 0.7rem 0.85rem; font-size: 0.8125rem; font-weight: 700; color: #334155; background: #f8fafc;
}
.org-role-picker__matrix-summary::-webkit-details-marker { display: none; }
.org-role-picker__matrix-badge {
  font-size: 0.65rem; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase;
  color: #64748b; background: #e2e8f0; border-radius: 999px; padding: 0.15rem 0.45rem;
}
.org-role-picker__matrix[open] .org-role-picker__matrix-badge { display: none; }
.org-role-picker__matrix-body { border-top: 1px solid #e2e8f0; }
.org-role-picker__matrix-cap { margin: 0; padding: 0.55rem 0.85rem; font-size: 0.75rem; color: #64748b; }
.org-role-picker__matrix-scroll { overflow-x: auto; }
.org-role-picker__table { width: 100%; min-width: 28rem; border-collapse: collapse; font-size: 0.75rem; }
.org-role-picker__table th,
.org-role-picker__table td { border-top: 1px solid #f1f5f9; padding: 0.45rem 0.55rem; text-align: center; vertical-align: top; }
.org-role-picker__table th:first-child,
.org-role-picker__table td:first-child { text-align: left; position: sticky; left: 0; background: #fff; z-index: 1; }
.org-role-picker__table thead th { background: #f8fafc; font-weight: 600; color: #475569; white-space: nowrap; }
.org-role-picker__table thead th:first-child { background: #f8fafc; }
.org-role-picker__table .role-col.is-on { background: #ecfdf5; color: #065f46; box-shadow: inset 0 0 0 1px #6ee7b7; }
.org-role-picker__table .union-cell { font-weight: 700; background: #f0fdf4; }
.org-role-picker__table .union-cell.is-on { color: #047857; }
.org-role-picker__table .union-cell.is-off { color: #cbd5e1; }
.org-role-picker__perm-name { display: block; font-weight: 600; color: #1e293b; }
.org-role-picker__perm-mod { display: block; font-size: 0.65rem; color: #94a3b8; }
</style>
<script>
(function () {
    if (window.__orgRolePickerBound) return;
    window.__orgRolePickerBound = true;

    function parseJson(raw, fallback) {
        try { return JSON.parse(raw || ''); } catch (e) { return fallback; }
    }

    function initPicker(root) {
        if (!root || root.__orgRolePickerReady) return;
        root.__orgRolePickerReady = true;

        var catalog = parseJson(root.getAttribute('data-catalog'), []);
        var selected = parseJson(root.getAttribute('data-selected'), []).map(function (n) { return parseInt(n, 10); }).filter(function (n) { return n > 0; });
        var inputName = root.getAttribute('data-input-name') || 'role_ids[]';
        var matrixId = root.getAttribute('data-matrix-id') || '';
        var byId = {};
        catalog.forEach(function (item) { if (item && item.id) byId[String(item.id)] = item; });

        var searchEl = root.querySelector('[data-role-search]');
        var layerEl = root.querySelector('[data-role-layer]');
        var selectEl = root.querySelector('[data-role-select]');
        var addBtn = root.querySelector('[data-role-add]');
        var chipsEl = root.querySelector('[data-role-chips]');
        var noneEl = root.querySelector('[data-role-none]');
        var metaEl = root.querySelector('[data-role-meta]');
        var hintEl = root.querySelector('[data-role-hint]');
        var emptyEl = root.querySelector('[data-role-empty]');
        var matrixRoot = matrixId ? document.getElementById(matrixId) : null;

        function selectedIds() { return selected.slice(); }

        function refreshUnion() {
            if (!matrixRoot) return;
            var ids = selectedIds();
            var matrix = window.__orgRoleMatrices && window.__orgRoleMatrices[matrixId];
            var byRole = (matrix && matrix.byRole) || {};
            matrixRoot.querySelectorAll('.union-cell').forEach(function (cell) {
                var pid = parseInt(cell.getAttribute('data-perm-id'), 10);
                var ok = false;
                for (var i = 0; i < ids.length; i++) {
                    var rid = ids[i];
                    if (byRole[rid] && byRole[rid][pid]) { ok = true; break; }
                }
                cell.textContent = ok ? '✓' : '—';
                cell.classList.toggle('is-on', ok);
                cell.classList.toggle('is-off', !ok);
                cell.classList.toggle('text-emerald-700', ok);
                cell.classList.toggle('text-slate-300', !ok);
            });
            matrixRoot.querySelectorAll('.role-col').forEach(function (th) {
                var rid = parseInt(th.getAttribute('data-role-id'), 10);
                var on = ids.indexOf(rid) !== -1;
                th.classList.toggle('is-on', on);
                th.classList.toggle('ring-2', on);
                th.classList.toggle('ring-emerald-400', on);
                th.classList.toggle('bg-emerald-50', on);
            });
        }

        function renderChips() {
            if (!chipsEl) return;
            chipsEl.innerHTML = '';
            selected.forEach(function (id) {
                var item = byId[String(id)];
                if (!item) return;
                var chip = document.createElement('span');
                chip.className = 'org-role-picker__chip';
                chip.setAttribute('data-role-id', String(id));
                var hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = inputName;
                hidden.value = String(id);
                hidden.className = 'role-pick';
                hidden.setAttribute('data-role-name', item.label || '');
                var lab = document.createElement('span');
                lab.className = 'org-role-picker__chip-label';
                lab.textContent = item.label || ('Rôle #' + id);
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'org-role-picker__chip-remove';
                btn.setAttribute('data-role-remove', String(id));
                btn.setAttribute('aria-label', 'Retirer ' + (item.label || ''));
                btn.textContent = '×';
                chip.appendChild(hidden);
                chip.appendChild(lab);
                chip.appendChild(btn);
                chipsEl.appendChild(chip);
            });
            if (noneEl) noneEl.hidden = selected.length > 0;
            refreshUnion();
        }

        function filterOptions() {
            if (!selectEl) return;
            var needle = ((searchEl && searchEl.value) || '').toLowerCase().trim();
            var layer = (layerEl && layerEl.value) || '';
            var visible = 0;
            Array.from(selectEl.querySelectorAll('optgroup')).forEach(function (group) {
                var groupLayer = group.getAttribute('data-layer') || '';
                var groupVisible = 0;
                Array.from(group.querySelectorAll('option')).forEach(function (opt) {
                    var id = parseInt(opt.value, 10);
                    var already = selected.indexOf(id) !== -1;
                    var optLayer = opt.getAttribute('data-layer') || groupLayer;
                    var hay = opt.getAttribute('data-search') || (opt.textContent || '').toLowerCase();
                    var layerOk = !layer || optLayer === layer;
                    var textOk = !needle || hay.indexOf(needle) !== -1;
                    var show = !already && layerOk && textOk;
                    opt.hidden = !show;
                    opt.disabled = !show;
                    if (show) groupVisible += 1;
                });
                group.hidden = groupVisible === 0;
                group.disabled = groupVisible === 0;
                visible += groupVisible;
            });
            if (metaEl) {
                metaEl.textContent = visible + ' rôle' + (visible > 1 ? 's' : '') + ' proposé' + (visible > 1 ? 's' : '')
                    + (selected.length ? (' · ' + selected.length + ' sélectionné' + (selected.length > 1 ? 's' : '')) : '');
            }
            if (emptyEl) emptyEl.hidden = visible > 0 || catalog.length === 0;
            syncHint();
        }

        function syncHint() {
            if (!hintEl || !selectEl) return;
            var id = selectEl.value || '';
            var item = byId[id];
            var hint = item && item.hint ? item.hint : '';
            hintEl.textContent = hint;
            hintEl.hidden = !hint;
        }

        function addSelected() {
            if (!selectEl) return;
            var id = parseInt(selectEl.value, 10);
            if (!(id > 0) || selected.indexOf(id) !== -1) return;
            selected.push(id);
            selectEl.value = '';
            renderChips();
            filterOptions();
        }

        function removeId(id) {
            selected = selected.filter(function (n) { return n !== id; });
            renderChips();
            filterOptions();
        }

        if (searchEl) searchEl.addEventListener('input', filterOptions);
        if (layerEl) layerEl.addEventListener('change', filterOptions);
        if (selectEl) {
            selectEl.addEventListener('change', function () {
                syncHint();
                if (selectEl.value) addSelected();
            });
        }
        if (addBtn) addBtn.addEventListener('click', addSelected);
        if (chipsEl) {
            chipsEl.addEventListener('click', function (ev) {
                var btn = ev.target.closest('[data-role-remove]');
                if (!btn || !chipsEl.contains(btn)) return;
                var id = parseInt(btn.getAttribute('data-role-remove'), 10);
                if (id > 0) removeId(id);
            });
        }

        renderChips();
        filterOptions();
    }

    function boot() {
        document.querySelectorAll('[data-org-role-picker]').forEach(initPicker);
    }

    window.__orgRolePickerBoot = boot;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
</script>
<?php endif; ?>
<script>
(function () {
    window.__orgRoleMatrices = window.__orgRoleMatrices || {};
    window.__orgRoleMatrices[<?= json_encode($matrixRootId, JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?>] = <?= json_encode($roleMatrix, JSON_UNESCAPED_UNICODE) ?>;
    if (typeof window.__orgRolePickerBoot === 'function') {
        window.__orgRolePickerBoot();
    }
})();
</script>
