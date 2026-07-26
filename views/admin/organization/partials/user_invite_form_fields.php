<?php
declare(strict_types=1);

use App\Support\OrganizationRoleLabels;

$roles = $roles ?? [];
$roleMatrix = $roleMatrix ?? ['roles' => [], 'permissions' => [], 'byRole' => []];
$grades = $grades ?? [];
$gradeCategories = $gradeCategories ?? [];
$organizationRoleLabelMode = $organizationRoleLabelMode ?? OrganizationRoleLabels::MODE_FR;
$steamWebConfigured = !empty($steamWebConfigured);
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
<div class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-4 space-y-3">
    <div>
        <label for="<?= htmlspecialchars($fid('steam_id'), ENT_QUOTES, 'UTF-8') ?>" class="block text-sm font-medium text-slate-700">Identifiant Steam</label>
        <input type="text" id="<?= htmlspecialchars($fid('steam_id'), ENT_QUOTES, 'UTF-8') ?>" name="steam_id" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm" placeholder="Numéro Steam, format classique, ou adresse du profil" autocomplete="off">
        <p class="mt-1 text-xs text-slate-500">Utile pour rattacher le compte à la carte et aux opérateurs en liaison.</p>
    </div>
    <label class="flex items-start gap-2 text-sm text-slate-700 cursor-pointer">
        <input type="checkbox" name="sync_steam_profile" value="1" class="mt-0.5 rounded border-slate-300 text-emerald-700" <?= !empty($steamWebConfigured) ? '' : 'disabled' ?>>
        <span>Synchroniser photo (et éventuellement le nom) depuis le profil public Steam après création.</span>
    </label>
    <label class="flex items-start gap-2 text-sm text-slate-700 cursor-pointer">
        <input type="checkbox" name="apply_steam_display_name" value="1" class="mt-0.5 rounded border-slate-300 text-emerald-700" <?= !empty($steamWebConfigured) ? '' : 'disabled' ?>>
        <span>Utiliser le pseudo Steam comme nom d’affichage.</span>
    </label>
    <?php if (empty($steamWebConfigured)): ?>
    <p class="text-xs text-amber-800">La synchronisation automatique n’est pas configurée sur ce serveur : l’identifiant peut tout de même être enregistré.</p>
    <?php endif; ?>
</div>
<div class="rounded-xl border border-slate-200 bg-slate-50/80 p-4">
    <label class="block text-sm font-medium text-slate-700">Rôles (communauté et/ou opérationnel)</label>
    <p class="text-xs text-slate-500 mt-0.5 mb-3">Ajoutez un ou plusieurs rôles via la liste. Les droits effectifs sont l’<strong>union</strong> des habilitations de chaque rôle. Les rôles site/plateforme ne sont pas listés ici.</p>
    <?php
    $selectedRoleIds = $selectedRoleIds ?? [];
    $pickerId = ($fieldIdPrefix !== '' ? $fieldIdPrefix : 'invite-') . 'org-role-picker';
    $showMatrix = true;
    $matrixOpen = false;
    require base_path('views/admin/organization/partials/org_role_multi_picker.php');
    ?>
</div>
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
