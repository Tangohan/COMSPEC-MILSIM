<?php
declare(strict_types=1);

use App\Support\OrganizationRoleLabels;

$user = $user ?? null;
$userProfile = $userProfile ?? null;
$roles = $roles ?? [];
$roleMatrix = $roleMatrix ?? ['roles' => [], 'permissions' => [], 'byRole' => []];
$selectedRoleIds = $selectedRoleIds ?? [];
$grades = $grades ?? [];
$gradeCategories = $gradeCategories ?? [];
$positionsList = is_array($positionsList ?? null) ? $positionsList : [];
$userActivePositions = is_array($userActivePositions ?? null) ? $userActivePositions : [];
$roleSetsList = is_array($roleSetsList ?? null) ? $roleSetsList : [];
$organizationRoleLabelMode = $organizationRoleLabelMode ?? OrganizationRoleLabels::MODE_FR;
if (!$user) {
    echo '<p>Utilisateur introuvable.</p>';
    return;
}
$uid = (int) $user['id'];
$isServiceAccount = !empty($isServiceAccount);
?>
<div class="max-w-7xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-6">Modifier le compte administratif</h1>
    <p class="text-sm text-slate-600 mb-4">Connexion, rôle, identité civile liée au compte. L’identité opérationnelle (personnage, affectation, clearance) se gère dans la <a href="<?= url('personnel/' . $uid . '/edit') ?>" class="text-blue-700 font-medium underline">fiche personnelle</a>.</p>
    <?php if (\App\Core\Session::get('success')): ?>
    <p class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"><?= htmlspecialchars((string) \App\Core\Session::get('success')) ?></p>
    <?php \App\Core\Session::forget('success'); endif; ?>
    <?php if (\App\Core\Session::get('error')): ?>
    <p class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900"><?= htmlspecialchars((string) \App\Core\Session::get('error')) ?></p>
    <?php \App\Core\Session::forget('error'); endif; ?>
    <?php
    $gradeValidationIssues = $gradeValidationIssues ?? [];
    foreach ($gradeValidationIssues as $i):
        $class = ($i['type'] ?? '') === 'error' ? 'text-red-700 bg-red-50' : 'text-amber-700 bg-amber-50';
    ?>
    <p class="mb-2 text-sm px-3 py-2 rounded <?= $class ?>"><?= htmlspecialchars($i['message'] ?? '') ?></p>
    <?php endforeach; ?>
    <form method="post" action="<?= url('back-office/users/' . $uid . '/update') ?>" class="space-y-4">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="user_roles_form" value="1">
        <div>
            <label for="email" class="block text-sm font-medium text-slate-700">Email *</label>
            <input type="email" id="email" name="email" required class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm" value="<?= htmlspecialchars($user['email'] ?? '') ?>">
        </div>
        <div>
            <label for="password" class="block text-sm font-medium text-slate-700">Nouveau mot de passe (laisser vide pour ne pas changer)</label>
            <input type="password" id="password" name="password" minlength="6" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm">
        </div>
        <div>
            <label for="display_name" class="block text-sm font-medium text-slate-700">Nom d'affichage</label>
            <input type="text" id="display_name" name="display_name" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm" value="<?= htmlspecialchars($user['display_name'] ?? '') ?>">
        </div>
        <div>
            <label for="callsign" class="block text-sm font-medium text-slate-700">Indicatif</label>
            <input type="text" id="callsign" name="callsign" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm" value="<?= htmlspecialchars($user['callsign'] ?? '') ?>">
        </div>
        <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-4">
            <label class="block text-sm font-medium text-slate-700">Rôles (communauté et/ou opérationnel)</label>
            <p class="text-xs text-slate-500 mt-0.5 mb-3">Cochez un ou plusieurs rôles. Les droits effectifs sont l’<strong>union</strong> des permissions. Les rôles site/plateforme ne sont pas listés ici.</p>
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
                        <?php
                        $rid = (int) $r['id'];
                        $chk = in_array($rid, $selectedRoleIds, true);
                        $rDisp = OrganizationRoleLabels::displayName($r, $organizationRoleLabelMode);
                        ?>
                        <label class="flex items-start gap-2 cursor-pointer text-sm">
                            <input type="checkbox" name="role_ids[]" value="<?= $rid ?>" class="role-pick mt-0.5 rounded border-slate-300 text-slate-900" <?= $chk ? 'checked' : '' ?> data-role-name="<?= htmlspecialchars($rDisp, ENT_QUOTES, 'UTF-8') ?>">
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
                        <?php
                        $rid = (int) $r['id'];
                        $chk = in_array($rid, $selectedRoleIds, true);
                        $rDisp = OrganizationRoleLabels::displayName($r, $organizationRoleLabelMode);
                        ?>
                        <label class="flex items-start gap-2 cursor-pointer text-sm">
                            <input type="checkbox" name="role_ids[]" value="<?= $rid ?>" class="role-pick mt-0.5 rounded border-slate-300 text-slate-900" <?= $chk ? 'checked' : '' ?> data-role-name="<?= htmlspecialchars($rDisp, ENT_QUOTES, 'UTF-8') ?>">
                            <span><span class="font-medium text-slate-900"><?= htmlspecialchars($rDisp) ?></span><?php if (!empty($r['description'])): ?><span class="block text-xs text-slate-500"><?= htmlspecialchars((string) $r['description']) ?></span><?php endif; ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <div id="role-matrix-wrap" class="mt-6 overflow-x-auto border border-slate-200 rounded-lg bg-white">
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
                    <tbody id="role-matrix-body">
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
                        <tr><td colspan="99" class="p-4 text-slate-500 text-center">Aucune permission liée aux rôles de cette communauté.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <script>
        (function () {
            var matrix = <?= json_encode($roleMatrix, JSON_UNESCAPED_UNICODE) ?>;
            var picks = document.querySelectorAll('.role-pick');
            function selectedIds() {
                var ids = [];
                picks.forEach(function (cb) { if (cb.checked) ids.push(parseInt(cb.value, 10)); });
                return ids;
            }
            function refreshUnion() {
                var ids = selectedIds();
                var byRole = matrix.byRole || {};
                document.querySelectorAll('.union-cell').forEach(function (cell) {
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
                document.querySelectorAll('.role-col').forEach(function (th) {
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

        <?php if (!$isServiceAccount && $positionsList !== []): ?>
        <div class="rounded-xl border border-slate-200 bg-white p-4 space-y-3">
            <h2 class="text-sm font-bold text-slate-900">Poste organisationnel</h2>
            <p class="text-xs text-slate-600">Affectation de fonction (distincte des rôles ci-dessus). <a href="<?= url('back-office/positions') ?>" class="text-blue-700 underline font-medium">Gérer les postes</a></p>
            <?php if ($userActivePositions !== []): ?>
            <ul class="text-xs text-slate-700 space-y-1 mb-2">
                <?php foreach ($userActivePositions as $up): ?>
                <li>• <?= htmlspecialchars((string) ($up['position_name'] ?? '')) ?> — depuis <?= htmlspecialchars((string) ($up['starts_at'] ?? '')) ?><?php if (!empty($up['ends_at'])): ?> jusqu’au <?= htmlspecialchars((string) $up['ends_at']) ?><?php endif; ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <form method="post" action="<?= url('back-office/users/' . $uid . '/assign-position') ?>" class="grid sm:grid-cols-2 gap-3 items-end">
                <?= \App\Core\Csrf::field() ?>
                <div class="sm:col-span-2">
                    <label for="position_id" class="block text-xs font-medium text-slate-600">Poste</label>
                    <select id="position_id" name="position_id" required class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md text-sm">
                        <option value="">— Choisir —</option>
                        <?php foreach ($positionsList as $pos): ?>
                        <option value="<?= (int) ($pos['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($pos['name'] ?? '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="starts_at" class="block text-xs font-medium text-slate-600">Début</label>
                    <input type="date" id="starts_at" name="starts_at" required class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md text-sm">
                </div>
                <div>
                    <label for="ends_at" class="block text-xs font-medium text-slate-600">Fin (optionnel)</label>
                    <input type="date" id="ends_at" name="ends_at" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md text-sm">
                </div>
                <div class="sm:col-span-2">
                    <button type="submit" class="px-3 py-2 bg-slate-800 text-white text-xs font-semibold rounded hover:bg-slate-900">Ajouter l’affectation</button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <?php if (!$isServiceAccount && $roleSetsList !== []): ?>
        <div class="rounded-xl border border-slate-200 bg-amber-50/40 p-4">
            <h2 class="text-sm font-bold text-slate-900">Jeu de rôles (pack)</h2>
            <p class="text-xs text-slate-600 mt-1 mb-3">Ajoute en une fois les rôles prédéfinis du pack, <strong>sans retirer</strong> les rôles déjà cochés.</p>
            <form method="post" action="<?= url('back-office/users/' . $uid . '/apply-role-set') ?>" class="flex flex-wrap items-end gap-3">
                <?= \App\Core\Csrf::field() ?>
                <div class="flex-1 min-w-[200px]">
                    <label for="role_set_id" class="block text-xs font-medium text-slate-600">Pack</label>
                    <select id="role_set_id" name="role_set_id" required class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md text-sm">
                        <option value="">— Choisir —</option>
                        <?php foreach ($roleSetsList as $rs): ?>
                        <option value="<?= (int) ($rs['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($rs['name'] ?? '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="px-3 py-2 bg-amber-900 text-white text-xs font-semibold rounded hover:bg-amber-950">Appliquer</button>
            </form>
        </div>
        <?php endif; ?>

        <div>
            <label for="nationality_code" class="block text-sm font-medium text-slate-700">Nationalité / doctrine</label>
            <select id="nationality_code" name="nationality_code" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm">
                <option value="">—</option>
                <option value="FR" <?= ($user['nationality_code'] ?? '') === 'FR' ? 'selected' : '' ?>>Français</option>
                <option value="US" <?= ($user['nationality_code'] ?? '') === 'US' ? 'selected' : '' ?>>Américain</option>
            </select>
        </div>
        <div>
            <label for="professional_category_code" class="block text-sm font-medium text-slate-700">Catégorie de personnel</label>
            <select id="professional_category_code" name="professional_category_code" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm">
                <option value="">—</option>
                <?php foreach ($gradeCategories as $c): ?>
                <option value="<?= htmlspecialchars($c['code']) ?>" <?= ($user['professional_category_code'] ?? '') === $c['code'] ? 'selected' : '' ?>><?= htmlspecialchars($c['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="grade_id" class="block text-sm font-medium text-slate-700">Grade</label>
            <select id="grade_id" name="grade_id" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm">
                <option value="">—</option>
                <?php foreach ($grades as $g): ?>
                <option value="<?= (int) $g['id'] ?>" <?= (int) ($user['grade_id'] ?? 0) === (int) $g['id'] ? 'selected' : '' ?>><?= htmlspecialchars($g['label_long'] ?? $g['name'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="preferred_grade_format" class="block text-sm font-medium text-slate-700">Format d'affichage du grade</label>
            <select id="preferred_grade_format" name="preferred_grade_format" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm">
                <option value="classic" <?= ($user['preferred_grade_format'] ?? 'classic') === 'classic' ? 'selected' : '' ?>>Classique (texte)</option>
                <option value="otan" <?= ($user['preferred_grade_format'] ?? '') === 'otan' ? 'selected' : '' ?>>OTAN</option>
                <option value="hybrid" <?= ($user['preferred_grade_format'] ?? '') === 'hybrid' ? 'selected' : '' ?>>Hybride (ex. Capitaine (OF-2))</option>
            </select>
        </div>
        <div>
            <label for="status" class="block text-sm font-medium text-slate-700">Statut</label>
            <select id="status" name="status" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm">
                <option value="pending_verification" <?= ($user['status'] ?? '') === 'pending_verification' ? 'selected' : '' ?>>En attente de vérification de l’e-mail</option>
                <option value="active" <?= ($user['status'] ?? '') === 'active' ? 'selected' : '' ?>>Actif</option>
                <option value="inactive" <?= ($user['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactif</option>
            </select>
        </div>
        <?php if (!$isServiceAccount): ?>
        <div class="rounded-lg border border-blue-200 bg-blue-50/80 p-4 text-sm text-slate-700">
            <p class="font-semibold text-slate-900 mb-1">Personnage & dossier opérationnel</p>
            <p class="mb-2">Indicatif RP, unité, clearance, forum — distinct du compte ci-dessus.</p>
            <a href="<?= url('personnel/' . $uid . '/edit') ?>" class="inline-flex text-blue-800 font-semibold hover:underline">Ouvrir la fiche personnelle →</a>
        </div>
        <?php endif; ?>

        <div class="flex gap-3 pt-4">
            <button type="submit" class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded hover:bg-slate-800">Enregistrer</button>
            <a href="<?= url('back-office/users/' . $uid) ?>" class="px-4 py-2 text-slate-600 text-sm hover:underline">Annuler</a>
        </div>
    </form>
</div>
