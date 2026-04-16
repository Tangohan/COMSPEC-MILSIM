<?php
declare(strict_types=1);

use App\Support\OrganizationRoleLabels;

$roles = $roles ?? [];
$roleMatrix = $roleMatrix ?? ['roles' => [], 'permissions' => [], 'byRole' => []];
$grades = $grades ?? [];
$gradeCategories = $gradeCategories ?? [];
$organizationRoleLabelMode = $organizationRoleLabelMode ?? OrganizationRoleLabels::MODE_FR;
$fieldIdPrefix = isset($fieldIdPrefix) && is_string($fieldIdPrefix) ? $fieldIdPrefix : '';
$matrixRootId = isset($matrixRootId) && is_string($matrixRootId) && $matrixRootId !== ''
    ? $matrixRootId
    : 'role-matrix-wrap';

$fid = static function (string $suffix) use ($fieldIdPrefix): string {
    return $fieldIdPrefix === '' ? $suffix : $fieldIdPrefix . $suffix;
};
?>
<div>
    <label for="<?= htmlspecialchars($fid('email'), ENT_QUOTES, 'UTF-8') ?>" class="block text-sm font-medium text-slate-700">E-mail *</label>
    <input type="email" id="<?= htmlspecialchars($fid('email'), ENT_QUOTES, 'UTF-8') ?>" name="email" required class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm" value="">
</div>
<div>
    <label for="<?= htmlspecialchars($fid('display_name'), ENT_QUOTES, 'UTF-8') ?>" class="block text-sm font-medium text-slate-700">Nom d'affichage</label>
    <input type="text" id="<?= htmlspecialchars($fid('display_name'), ENT_QUOTES, 'UTF-8') ?>" name="display_name" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm">
</div>
<div>
    <label for="<?= htmlspecialchars($fid('callsign'), ENT_QUOTES, 'UTF-8') ?>" class="block text-sm font-medium text-slate-700">Indicatif</label>
    <input type="text" id="<?= htmlspecialchars($fid('callsign'), ENT_QUOTES, 'UTF-8') ?>" name="callsign" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm">
</div>
<div class="rounded-xl border border-slate-200 bg-slate-50/80 p-4">
    <label class="block text-sm font-medium text-slate-700">Rôles (communauté et/ou opérationnel)</label>
    <p class="text-xs text-slate-500 mt-0.5 mb-3">Cochez un ou plusieurs rôles. Les droits effectifs sont l’<strong>union</strong> des permissions de chaque rôle. Les rôles site/plateforme ne sont pas listés ici.</p>
    <div class="grid lg:grid-cols-2 gap-6">
        <?php
        $byLayer = ['community' => [], 'intra' => []];
        foreach ($roles as $r) {
            $ly = (string) ($r['role_layer'] ?? 'community');
            if (!isset($byLayer[$ly])) {
                $byLayer[$ly] = [];
            }
            $byLayer[$ly][] = $r;
        }
        ?>
        <?php if (!empty($byLayer['community'])): ?>
        <div>
            <p class="text-xs font-bold text-slate-600 uppercase tracking-wide mb-2"><?= htmlspecialchars(OrganizationRoleLabels::layerGroupLabel('community', $organizationRoleLabelMode)) ?></p>
            <div class="space-y-2">
                <?php foreach ($byLayer['community'] as $r): ?>
                <?php $rDisp = OrganizationRoleLabels::displayName($r, $organizationRoleLabelMode); ?>
                <label class="flex items-start gap-2 cursor-pointer text-sm">
                    <input type="checkbox" name="role_ids[]" value="<?= (int) $r['id'] ?>" class="role-pick mt-0.5 rounded border-slate-300 text-slate-900" data-role-name="<?= htmlspecialchars($rDisp, ENT_QUOTES, 'UTF-8') ?>">
                    <span><span class="font-medium text-slate-900"><?= htmlspecialchars($rDisp) ?></span><?php if (!empty($r['description'])): ?><span class="block text-xs text-slate-500"><?= htmlspecialchars((string) $r['description']) ?></span><?php endif; ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php if (!empty($byLayer['intra'])): ?>
        <div>
            <p class="text-xs font-bold text-slate-600 uppercase tracking-wide mb-2"><?= htmlspecialchars(OrganizationRoleLabels::layerGroupLabel('intra', $organizationRoleLabelMode)) ?></p>
            <div class="space-y-2">
                <?php foreach ($byLayer['intra'] as $r): ?>
                <?php $rDisp = OrganizationRoleLabels::displayName($r, $organizationRoleLabelMode); ?>
                <label class="flex items-start gap-2 cursor-pointer text-sm">
                    <input type="checkbox" name="role_ids[]" value="<?= (int) $r['id'] ?>" class="role-pick mt-0.5 rounded border-slate-300 text-slate-900" data-role-name="<?= htmlspecialchars($rDisp, ENT_QUOTES, 'UTF-8') ?>">
                    <span><span class="font-medium text-slate-900"><?= htmlspecialchars($rDisp) ?></span><?php if (!empty($r['description'])): ?><span class="block text-xs text-slate-500"><?= htmlspecialchars((string) $r['description']) ?></span><?php endif; ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <div id="<?= htmlspecialchars($matrixRootId, ENT_QUOTES, 'UTF-8') ?>" class="mt-6 overflow-x-auto border border-slate-200 rounded-lg bg-white">
        <p class="px-3 py-2 text-xs font-semibold text-slate-700 border-b border-slate-100">Aperçu des droits (union dynamique selon les cases cochées)</p>
        <table class="min-w-full text-xs">
            <thead class="bg-slate-50 text-slate-600">
                <tr>
                    <th class="text-left p-2 font-semibold sticky left-0 bg-slate-50 z-10 border-r border-slate-100">Permission</th>
                    <?php foreach ($roleMatrix['roles'] as $rr): ?>
                    <th class="p-2 text-center font-medium whitespace-nowrap role-col" data-role-id="<?= (int) $rr['id'] ?>"><?= htmlspecialchars(OrganizationRoleLabels::displayName($rr, $organizationRoleLabelMode)) ?></th>
                    <?php endforeach; ?>
                    <th class="p-2 text-center font-bold text-emerald-800 bg-emerald-50/80">Union</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($roleMatrix['permissions'] as $p): ?>
                <?php
                $pid = (int) ($p['id'] ?? 0);
                $mod = trim((string) ($p['module'] ?? ''));
                ?>
                <tr class="border-t border-slate-100 perm-row" data-perm-id="<?= $pid ?>">
                    <td class="p-2 align-top sticky left-0 bg-white z-10 border-r border-slate-100">
                        <span class="font-medium text-slate-800"><?= htmlspecialchars((string) ($p['name'] ?? '')) ?></span>
                        <?php if ($mod !== ''): ?><span class="block text-[10px] text-slate-400"><?= htmlspecialchars($mod) ?></span><?php endif; ?>
                    </td>
                    <?php foreach ($roleMatrix['roles'] as $rr): ?>
                    <?php $rid = (int) $rr['id']; $has = !empty($roleMatrix['byRole'][$rid][$pid]); ?>
                    <td class="p-2 text-center role-cell" data-role-id="<?= $rid ?>" data-perm-id="<?= $pid ?>"><?= $has ? '✓' : '—' ?></td>
                    <?php endforeach; ?>
                    <td class="p-2 text-center font-semibold union-cell bg-emerald-50/40" data-perm-id="<?= $pid ?>">—</td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($roleMatrix['permissions'])): ?>
                <tr><td colspan="99" class="p-4 text-slate-500 text-center">Aucune permission liée aux rôles de cette communauté (catalogue ou rattachements à vérifier).</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
(function () {
    var matrixRootId = <?= json_encode($matrixRootId, JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?>;
    var matrix = <?= json_encode($roleMatrix, JSON_UNESCAPED_UNICODE) ?>;
    var root = document.getElementById(matrixRootId);
    if (!root) return;
    var picks = root.querySelectorAll('.role-pick');
    function selectedIds() {
        var ids = [];
        picks.forEach(function (cb) { if (cb.checked) ids.push(parseInt(cb.value, 10)); });
        return ids;
    }
    function refreshUnion() {
        var ids = selectedIds();
        var byRole = matrix.byRole || {};
        root.querySelectorAll('.union-cell').forEach(function (cell) {
            var pid = parseInt(cell.getAttribute('data-perm-id'), 10);
            var ok = false;
            for (var i = 0; i < ids.length; i++) {
                var rid = ids[i];
                if (byRole[rid] && byRole[rid][pid]) { ok = true; break; }
            }
            cell.textContent = ok ? '✓' : '—';
            cell.classList.toggle('text-emerald-700', ok);
            cell.classList.toggle('text-slate-300', !ok);
        });
        root.querySelectorAll('.role-col').forEach(function (th) {
            var rid = parseInt(th.getAttribute('data-role-id'), 10);
            var on = ids.indexOf(rid) !== -1;
            th.classList.toggle('ring-2', on);
            th.classList.toggle('ring-emerald-400', on);
            th.classList.toggle('bg-emerald-50', on);
        });
    }
    picks.forEach(function (cb) { cb.addEventListener('change', refreshUnion); });
    refreshUnion();
})();
</script>
<div>
    <label for="<?= htmlspecialchars($fid('nationality_code'), ENT_QUOTES, 'UTF-8') ?>" class="block text-sm font-medium text-slate-700">Nationalité / doctrine</label>
    <select id="<?= htmlspecialchars($fid('nationality_code'), ENT_QUOTES, 'UTF-8') ?>" name="nationality_code" class="<?= htmlspecialchars(bo_select_class('mt-1'), ENT_QUOTES, 'UTF-8') ?>">
        <option value="">—</option>
        <option value="FR">Français</option>
        <option value="US">Américain</option>
    </select>
</div>
<div>
    <label for="<?= htmlspecialchars($fid('professional_category_code'), ENT_QUOTES, 'UTF-8') ?>" class="block text-sm font-medium text-slate-700">Catégorie de personnel</label>
    <select id="<?= htmlspecialchars($fid('professional_category_code'), ENT_QUOTES, 'UTF-8') ?>" name="professional_category_code" class="<?= htmlspecialchars(bo_select_class('mt-1'), ENT_QUOTES, 'UTF-8') ?>">
        <option value="">—</option>
        <?php foreach ($gradeCategories as $c): ?>
        <option value="<?= htmlspecialchars($c['code']) ?>"><?= htmlspecialchars($c['label']) ?></option>
        <?php endforeach; ?>
    </select>
</div>
<div>
    <label for="<?= htmlspecialchars($fid('grade_id'), ENT_QUOTES, 'UTF-8') ?>" class="block text-sm font-medium text-slate-700">Grade</label>
    <select id="<?= htmlspecialchars($fid('grade_id'), ENT_QUOTES, 'UTF-8') ?>" name="grade_id" class="<?= htmlspecialchars(bo_select_class('mt-1'), ENT_QUOTES, 'UTF-8') ?>">
        <option value="">—</option>
        <?php foreach ($grades as $g): ?>
        <option value="<?= (int) $g['id'] ?>"><?= htmlspecialchars($g['label_long'] ?? $g['name'] ?? '') ?></option>
        <?php endforeach; ?>
    </select>
</div>
<div>
    <label for="<?= htmlspecialchars($fid('preferred_grade_format'), ENT_QUOTES, 'UTF-8') ?>" class="block text-sm font-medium text-slate-700">Format d'affichage du grade</label>
    <select id="<?= htmlspecialchars($fid('preferred_grade_format'), ENT_QUOTES, 'UTF-8') ?>" name="preferred_grade_format" class="<?= htmlspecialchars(bo_select_class('mt-1'), ENT_QUOTES, 'UTF-8') ?>">
        <option value="classic">Classique (texte)</option>
        <option value="otan">OTAN</option>
        <option value="hybrid">Hybride (ex. Capitaine (OF-2))</option>
    </select>
</div>
