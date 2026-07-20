<?php
declare(strict_types=1);

/**
 * Champs communs demande d’élévation (type, grade, rôle, fonction, affectation, note).
 *
 * @var string $fieldIdPrefix
 * @var array{grades?:list,roles?:list,job_roles?:list,units?:list,clearance_levels?:array<string,string>}|null $elevationCatalog
 * @var string|null $selectedKind
 * @var bool $includeUnit
 */

use App\Support\OrganizationRoleLabels;

$fieldIdPrefix = isset($fieldIdPrefix) && is_string($fieldIdPrefix) ? $fieldIdPrefix : 'elev';
$catalog = is_array($elevationCatalog ?? null) ? $elevationCatalog : [];
$grades = is_array($catalog['grades'] ?? null) ? $catalog['grades'] : [];
$roles = is_array($catalog['roles'] ?? null) ? $catalog['roles'] : [];
$jobRoles = is_array($catalog['job_roles'] ?? null) ? $catalog['job_roles'] : [];
$units = is_array($catalog['units'] ?? null) ? $catalog['units'] : [];
$clearanceLevels = is_array($catalog['clearance_levels'] ?? null) ? $catalog['clearance_levels'] : [];
$selectedKind = isset($selectedKind) ? (string) $selectedKind : 'grade';
$includeUnit = (bool) ($includeUnit ?? true);
$fid = static function (string $suffix) use ($fieldIdPrefix): string {
    return $fieldIdPrefix . '-' . $suffix;
};
?>
<label for="<?= htmlspecialchars($fid('kind'), ENT_QUOTES, 'UTF-8') ?>">Type d’élévation</label>
<select id="<?= htmlspecialchars($fid('kind'), ENT_QUOTES, 'UTF-8') ?>" name="elevation_kind">
    <option value="grade" <?= $selectedKind === 'grade' ? 'selected' : '' ?>>Grade</option>
    <option value="role" <?= $selectedKind === 'role' ? 'selected' : '' ?>>Rôle</option>
    <option value="droits" <?= $selectedKind === 'droits' ? 'selected' : '' ?>>Droits d’accès</option>
    <option value="general" <?= $selectedKind === 'general' ? 'selected' : '' ?>>Situation RH</option>
</select>

<label for="<?= htmlspecialchars($fid('grade'), ENT_QUOTES, 'UTF-8') ?>">Grade proposé</label>
<select id="<?= htmlspecialchars($fid('grade'), ENT_QUOTES, 'UTF-8') ?>" name="elevation_grade_id">
    <option value="">— Sans changement de grade —</option>
    <?php foreach ($grades as $g): ?>
        <?php
        $gid = (int) ($g['id'] ?? 0);
        if ($gid < 1) {
            continue;
        }
        $gShort = trim((string) ($g['label_short'] ?? ''));
        $gLong = trim((string) ($g['label_long'] ?? ''));
        $gLabel = $gShort !== '' ? $gShort : ($gLong !== '' ? $gLong : 'Grade #' . $gid);
        if ($gShort !== '' && $gLong !== '' && $gShort !== $gLong) {
            $gLabel = $gShort . ' — ' . $gLong;
        }
        ?>
        <option value="<?= $gid ?>"><?= htmlspecialchars($gLabel, ENT_QUOTES, 'UTF-8') ?></option>
    <?php endforeach; ?>
</select>

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

<?php if ($includeUnit): ?>
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
<?php endif; ?>

<label for="<?= htmlspecialchars($fid('clearance'), ENT_QUOTES, 'UTF-8') ?>">Niveau d’habilitation proposé</label>
<select id="<?= htmlspecialchars($fid('clearance'), ENT_QUOTES, 'UTF-8') ?>" name="elevation_clearance_level">
    <option value="">— Sans changement d’habilitation —</option>
    <?php foreach ($clearanceLevels as $clValue => $clLabel): ?>
        <option value="<?= htmlspecialchars((string) $clValue, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $clLabel, ENT_QUOTES, 'UTF-8') ?></option>
    <?php endforeach; ?>
</select>
<p style="margin:0 0 .5rem;font-size:11px;color:rgba(15,23,42,.5)">Conditionne l’accès aux documents classifiés — la revue d’habilitation est marquée à jour dès l’application.</p>

<label for="<?= htmlspecialchars($fid('note'), ENT_QUOTES, 'UTF-8') ?>">Message (optionnel)</label>
<textarea id="<?= htmlspecialchars($fid('note'), ENT_QUOTES, 'UTF-8') ?>" name="elevation_note" rows="2" maxlength="500" placeholder="Précisez le besoin ou le contexte…"></textarea>
