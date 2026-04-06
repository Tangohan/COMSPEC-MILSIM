<?php
$roles = $roles ?? [];
$roleMatrix = $roleMatrix ?? ['roles' => [], 'permissions' => [], 'byRole' => []];
$grades = $grades ?? [];
$gradeCategories = $gradeCategories ?? [];
?>
<div class="max-w-5xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-2">Nouvel utilisateur</h1>
    <p class="text-sm text-slate-600 mb-6">Aucun mot de passe n’est saisi ici : un <strong>e-mail</strong> est envoyé à la personne avec un lien sécurisé pour définir son mot de passe et activer le compte (comme une invitation de la communauté).</p>

    <form method="post" action="<?= url('back-office/users/store') ?>" class="space-y-4">
        <?= \App\Core\Csrf::field() ?>
        <div>
            <label for="email" class="block text-sm font-medium text-slate-700">E-mail *</label>
            <input type="email" id="email" name="email" required class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm" value="">
        </div>
        <div>
            <label for="display_name" class="block text-sm font-medium text-slate-700">Nom d'affichage</label>
            <input type="text" id="display_name" name="display_name" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm">
        </div>
        <div>
            <label for="callsign" class="block text-sm font-medium text-slate-700">Indicatif</label>
            <input type="text" id="callsign" name="callsign" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm">
        </div>
        <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-4">
            <label class="block text-sm font-medium text-slate-700">Rôles (communauté et/ou opérationnel)</label>
            <p class="text-xs text-slate-500 mt-0.5 mb-3">Cochez un ou plusieurs rôles. Les droits effectifs sont l’<strong>union</strong> des permissions de chaque rôle. Les rôles site/plateforme ne sont pas listés ici.</p>
            <div class="grid sm:grid-cols-2 gap-3">
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
                    <p class="text-xs font-bold text-slate-600 uppercase tracking-wide mb-2">Gouvernance communauté</p>
                    <div class="space-y-2">
                        <?php foreach ($byLayer['community'] as $r): ?>
                        <label class="flex items-start gap-2 cursor-pointer text-sm">
                            <input type="checkbox" name="role_ids[]" value="<?= (int) $r['id'] ?>" class="role-pick mt-0.5 rounded border-slate-300 text-slate-900" data-role-name="<?= htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') ?>">
                            <span><span class="font-medium text-slate-900"><?= htmlspecialchars($r['name']) ?></span><?php if (!empty($r['description'])): ?><span class="block text-xs text-slate-500"><?= htmlspecialchars((string) $r['description']) ?></span><?php endif; ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (!empty($byLayer['intra'])): ?>
                <div>
                    <p class="text-xs font-bold text-slate-600 uppercase tracking-wide mb-2">Rôles opérationnels</p>
                    <div class="space-y-2">
                        <?php foreach ($byLayer['intra'] as $r): ?>
                        <label class="flex items-start gap-2 cursor-pointer text-sm">
                            <input type="checkbox" name="role_ids[]" value="<?= (int) $r['id'] ?>" class="role-pick mt-0.5 rounded border-slate-300 text-slate-900" data-role-name="<?= htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') ?>">
                            <span><span class="font-medium text-slate-900"><?= htmlspecialchars($r['name']) ?></span><?php if (!empty($r['description'])): ?><span class="block text-xs text-slate-500"><?= htmlspecialchars((string) $r['description']) ?></span><?php endif; ?></span>
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
                            <th class="p-2 text-center font-medium whitespace-nowrap role-col" data-role-id="<?= (int) $rr['id'] ?>"><?= htmlspecialchars($rr['name']) ?></th>
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
                        <tr><td colspan="99" class="p-4 text-slate-500 text-center">Aucune permission liée aux rôles de cette communauté (catalogue ou rattachements à vérifier).</td></tr>
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
        <div>
            <label for="nationality_code" class="block text-sm font-medium text-slate-700">Nationalité / doctrine</label>
            <select id="nationality_code" name="nationality_code" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm">
                <option value="">—</option>
                <option value="FR">Français</option>
                <option value="US">Américain</option>
            </select>
        </div>
        <div>
            <label for="professional_category_code" class="block text-sm font-medium text-slate-700">Catégorie de personnel</label>
            <select id="professional_category_code" name="professional_category_code" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm">
                <option value="">—</option>
                <?php foreach ($gradeCategories as $c): ?>
                <option value="<?= htmlspecialchars($c['code']) ?>"><?= htmlspecialchars($c['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="grade_id" class="block text-sm font-medium text-slate-700">Grade</label>
            <select id="grade_id" name="grade_id" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm">
                <option value="">—</option>
                <?php foreach ($grades as $g): ?>
                <option value="<?= (int) $g['id'] ?>"><?= htmlspecialchars($g['label_long'] ?? $g['name'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="preferred_grade_format" class="block text-sm font-medium text-slate-700">Format d'affichage du grade</label>
            <select id="preferred_grade_format" name="preferred_grade_format" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm">
                <option value="classic">Classique (texte)</option>
                <option value="otan">OTAN</option>
                <option value="hybrid">Hybride (ex. Capitaine (OF-2))</option>
            </select>
        </div>
        <div class="flex gap-3 pt-4">
            <button type="submit" class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded hover:bg-slate-800">Créer et envoyer l’e-mail</button>
            <a href="<?= url('back-office/users') ?>" class="px-4 py-2 text-slate-600 text-sm hover:underline">Annuler</a>
        </div>
    </form>
</div>
