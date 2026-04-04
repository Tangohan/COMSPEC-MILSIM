<?php
$user = $user ?? null;
$userProfile = $userProfile ?? null;
$roles = $roles ?? [];
$grades = $grades ?? [];
$gradeCategories = $gradeCategories ?? [];
if (!$user) {
    echo '<p>Utilisateur introuvable.</p>';
    return;
}
$uid = (int) $user['id'];
?>
<div class="max-w-2xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-6">Modifier l'utilisateur</h1>
    <?php
    $gradeValidationIssues = $gradeValidationIssues ?? [];
    foreach ($gradeValidationIssues as $i):
        $class = ($i['type'] ?? '') === 'error' ? 'text-red-700 bg-red-50' : 'text-amber-700 bg-amber-50';
    ?>
    <p class="mb-2 text-sm px-3 py-2 rounded <?= $class ?>"><?= htmlspecialchars($i['message'] ?? '') ?></p>
    <?php endforeach; ?>
    <form method="post" action="<?= url('admin/organization/users/' . $uid . '/update') ?>" class="space-y-4">
        <?= \App\Core\Csrf::field() ?>
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
        <div>
            <label for="role_id" class="block text-sm font-medium text-slate-700">Rôle (communauté ou opérationnel)</label>
            <p class="text-xs text-slate-500 mt-0.5 mb-1">Les rôles site/plateforme ne sont pas attribuables ici.</p>
            <select id="role_id" name="role_id" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm">
                <option value="">—</option>
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
                <optgroup label="Gouvernance communauté">
                    <?php foreach ($byLayer['community'] as $r): ?>
                    <option value="<?= (int) $r['id'] ?>" <?= (int) ($user['role_id'] ?? 0) === (int) $r['id'] ? 'selected' : '' ?>><?= htmlspecialchars($r['name']) ?></option>
                    <?php endforeach; ?>
                </optgroup>
                <?php endif; ?>
                <?php if (!empty($byLayer['intra'])): ?>
                <optgroup label="Rôles opérationnels">
                    <?php foreach ($byLayer['intra'] as $r): ?>
                    <option value="<?= (int) $r['id'] ?>" <?= (int) ($user['role_id'] ?? 0) === (int) $r['id'] ? 'selected' : '' ?>><?= htmlspecialchars($r['name']) ?></option>
                    <?php endforeach; ?>
                </optgroup>
                <?php endif; ?>
            </select>
        </div>
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
                <option value="pending" <?= ($user['status'] ?? '') === 'pending' ? 'selected' : '' ?>>En attente</option>
                <option value="active" <?= ($user['status'] ?? '') === 'active' ? 'selected' : '' ?>>Actif</option>
                <option value="inactive" <?= ($user['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactif</option>
            </select>
        </div>
        <div class="flex gap-3 pt-4">
            <button type="submit" class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded hover:bg-slate-800">Enregistrer</button>
            <a href="<?= url('admin/organization/users/' . $uid) ?>" class="px-4 py-2 text-slate-600 text-sm hover:underline">Annuler</a>
        </div>
    </form>
</div>
