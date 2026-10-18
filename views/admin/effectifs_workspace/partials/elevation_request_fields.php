<?php
declare(strict_types=1);

/**
 * Champs communs demande d’élévation (type, grade, rôle, fonction, affectation, note).
 *
 * @var string $fieldIdPrefix
 * @var array{grades?:list,roles?:list,job_roles?:list,units?:list,permissions?:list}|null $elevationCatalog
 * @var string|null $selectedKind
 * @var bool $includeUnit
 */

use App\Services\Rbac\CommunityAccessProfiles;
use App\Support\GradeSelectOptions;
use App\Support\OrganizationRoleLabels;

$fieldIdPrefix = isset($fieldIdPrefix) && is_string($fieldIdPrefix) ? $fieldIdPrefix : 'elev';
$catalog = is_array($elevationCatalog ?? null) ? $elevationCatalog : [];
$grades = is_array($catalog['grades'] ?? null) ? $catalog['grades'] : [];
$roles = is_array($catalog['roles'] ?? null) ? $catalog['roles'] : [];
$jobRoles = is_array($catalog['job_roles'] ?? null) ? $catalog['job_roles'] : [];
$units = is_array($catalog['units'] ?? null) ? $catalog['units'] : [];
$permissions = is_array($catalog['permissions'] ?? null) ? $catalog['permissions'] : [];
$selectedKind = isset($selectedKind) ? (string) $selectedKind : 'grade';
$includeUnit = (bool) ($includeUnit ?? true);
$gradeGroups = is_array($catalog['grade_groups'] ?? null) ? $catalog['grade_groups'] : GradeSelectOptions::groups($grades);
// Droits regroupés par module, dans l'ordre et avec les libellés de la page « Accès ».
$permissionGroups = [];
foreach ($permissions as $permission) {
    $module = strtolower(trim((string) ($permission['module'] ?? ''))) ?: 'other';
    $permissionGroups[$module][] = $permission;
}
$moduleRank = array_flip(CommunityAccessProfiles::moduleOrder());
uksort($permissionGroups, static fn (string $a, string $b): int => [$moduleRank[$a] ?? 99, $a] <=> [$moduleRank[$b] ?? 99, $b]);
foreach ($permissionGroups as &$modulePermissions) {
    usort($modulePermissions, static fn (array $x, array $y): int => strcasecmp((string) ($x['name'] ?? ''), (string) ($y['name'] ?? '')));
}
unset($modulePermissions);
$fid = static function (string $suffix) use ($fieldIdPrefix): string {
    return $fieldIdPrefix . '-' . $suffix;
};
?>
<div class="elev-fields" data-elev-form>
<label for="<?= htmlspecialchars($fid('kind'), ENT_QUOTES, 'UTF-8') ?>">Type d’élévation</label>
<select id="<?= htmlspecialchars($fid('kind'), ENT_QUOTES, 'UTF-8') ?>" name="elevation_kind" data-elev-kind>
    <option value="grade" <?= $selectedKind === 'grade' ? 'selected' : '' ?>>Grade</option>
    <option value="role" <?= $selectedKind === 'role' ? 'selected' : '' ?>>Rôle, fonction ou affectation</option>
    <option value="droits" <?= $selectedKind === 'droits' ? 'selected' : '' ?>>Droits d’accès</option>
    <option value="general" <?= $selectedKind === 'general' ? 'selected' : '' ?>>Situation RH (tous les champs)</option>
</select>

<div class="elev-field" data-elev-kinds="grade general">
<label for="<?= htmlspecialchars($fid('grade'), ENT_QUOTES, 'UTF-8') ?>">Grade proposé</label>
<select id="<?= htmlspecialchars($fid('grade'), ENT_QUOTES, 'UTF-8') ?>" name="elevation_grade_id">
    <option value="">— Sans changement de grade —</option>
    <?php foreach ($gradeGroups as $gradeGroup): ?>
        <optgroup label="<?= htmlspecialchars((string) $gradeGroup['label'], ENT_QUOTES, 'UTF-8') ?>">
            <?php foreach ($gradeGroup['grades'] as $g): ?>
                <option value="<?= (int) ($g['id'] ?? 0) ?>"><?= htmlspecialchars(GradeSelectOptions::label($g), ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
        </optgroup>
    <?php endforeach; ?>
</select>
</div>

<div class="elev-field" data-elev-kinds="role droits general">
<label for="<?= htmlspecialchars($fid('role'), ENT_QUOTES, 'UTF-8') ?>">Rôle proposé</label>
<select id="<?= htmlspecialchars($fid('role'), ENT_QUOTES, 'UTF-8') ?>" name="elevation_role_id">
    <option value="">— Sans changement de rôle —</option>
    <?php foreach ($roles as $r): ?>
        <?php
        $rid = (int) ($r['id'] ?? 0);
        if ($rid < 1) {
            continue;
        }
        $rLabel = OrganizationRoleLabels::displayName($r, OrganizationRoleLabels::MODE_FR);
        $layer = (string) ($r['role_layer'] ?? 'community');
        $layerFr = $layer === 'intra' ? 'Opérationnel' : 'Communauté';
        ?>
        <option value="<?= $rid ?>"><?= htmlspecialchars($rLabel . ' (' . $layerFr . ')', ENT_QUOTES, 'UTF-8') ?></option>
    <?php endforeach; ?>
</select>
</div>

<div class="elev-field" data-elev-kinds="role general">
<label for="<?= htmlspecialchars($fid('job'), ENT_QUOTES, 'UTF-8') ?>">Fonction proposée</label>
<select id="<?= htmlspecialchars($fid('job'), ENT_QUOTES, 'UTF-8') ?>" name="elevation_job_role_id">
    <option value="">— Sans changement de fonction —</option>
    <?php foreach ($jobRoles as $jr): ?>
        <?php
        $jid = (int) ($jr['id'] ?? 0);
        if ($jid < 1) {
            continue;
        }
        $jLabel = trim((string) ($jr['label'] ?? $jr['name'] ?? ''));
        if ($jLabel === '') {
            continue;
        }
        ?>
        <option value="<?= $jid ?>"><?= htmlspecialchars($jLabel, ENT_QUOTES, 'UTF-8') ?></option>
    <?php endforeach; ?>
</select>
</div>

<?php if ($includeUnit): ?>
<div class="elev-field" data-elev-kinds="role general">
<label for="<?= htmlspecialchars($fid('unit'), ENT_QUOTES, 'UTF-8') ?>">Affectation proposée</label>
<select id="<?= htmlspecialchars($fid('unit'), ENT_QUOTES, 'UTF-8') ?>" name="elevation_unit_id">
    <option value="">— Sans changement d’affectation —</option>
    <?php foreach ($units as $u): ?>
        <?php
        $uid = (int) ($u['id'] ?? 0);
        if ($uid < 1) {
            continue;
        }
        $uLabel = trim((string) ($u['assignment_path'] ?? $u['name'] ?? ''));
        if ($uLabel === '') {
            continue;
        }
        ?>
        <option value="<?= $uid ?>"><?= htmlspecialchars($uLabel, ENT_QUOTES, 'UTF-8') ?></option>
    <?php endforeach; ?>
</select>
</div>
<?php endif; ?>

<?php if ($permissions !== []): ?>
<fieldset class="elevation-access-rights elev-field" data-elev-kinds="droits general">
    <legend>Droits d’accès spécifiques (optionnel)</legend>
    <p class="elevation-access-rights__hint">Cochez un ou plusieurs accès. Ils peuvent être demandés avec ou sans changement de rôle.</p>
    <input type="search" class="elevation-access-rights__search" placeholder="Rechercher un droit (ex. forum, opérations, ATAK)…" aria-label="Rechercher un droit d’accès" data-filter-input>
    <p class="elevation-access-rights__count" data-elev-selected-count hidden></p>
    <div class="elevation-access-rights__groups" data-filter-scope>
        <?php foreach ($permissionGroups as $module => $modulePermissions): ?>
            <?php $moduleLabel = CommunityAccessProfiles::moduleLabel((string) $module); ?>
            <details class="elevation-access-rights__module" data-filter-group>
                <summary><?= htmlspecialchars($moduleLabel, ENT_QUOTES, 'UTF-8') ?> <small><?= count($modulePermissions) ?></small></summary>
                <div class="elevation-access-rights__grid">
                    <?php foreach ($modulePermissions as $permission): ?>
                        <?php
                        $permissionId = (int) ($permission['id'] ?? 0);
                        if ($permissionId < 1) continue;
                        $permissionLabel = trim((string) ($permission['name'] ?? '')) ?: (string) ($permission['slug'] ?? 'Droit #' . $permissionId);
                        $permissionDescription = trim((string) ($permission['description'] ?? ''));
                        ?>
                        <label class="elevation-access-rights__option" for="<?= htmlspecialchars($fid('permission-' . $permissionId), ENT_QUOTES, 'UTF-8') ?>" data-filter-item data-filter-text="<?= htmlspecialchars(mb_strtolower($permissionLabel . ' ' . $moduleLabel . ' ' . $permissionDescription . ' ' . (string) ($permission['slug'] ?? ''), 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="checkbox" id="<?= htmlspecialchars($fid('permission-' . $permissionId), ENT_QUOTES, 'UTF-8') ?>" name="elevation_permission_ids[]" value="<?= $permissionId ?>">
                            <span><strong><?= htmlspecialchars($permissionLabel, ENT_QUOTES, 'UTF-8') ?></strong><?php if ($permissionDescription !== ''): ?><small><?= htmlspecialchars($permissionDescription, ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </details>
        <?php endforeach; ?>
    </div>
    <p class="elevation-access-rights__empty" data-filter-empty hidden>Aucun droit ne correspond à la recherche.</p>
</fieldset>
<?php endif; ?>

<label for="<?= htmlspecialchars($fid('note'), ENT_QUOTES, 'UTF-8') ?>">Message (optionnel)</label>
<textarea id="<?= htmlspecialchars($fid('note'), ENT_QUOTES, 'UTF-8') ?>" name="elevation_note" rows="2" maxlength="500" placeholder="Précisez le besoin ou le contexte…"></textarea>
</div>
<script src="<?= htmlspecialchars(asset_url('assets/js/eff-member.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
