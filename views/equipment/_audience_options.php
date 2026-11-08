<?php
declare(strict_types=1);
/**
 * Options de la liste « À qui proposer ce kit » : tous les membres, une unité, un rôle.
 * Variables : $audience (valeur choisie : all, unit:ID, role:ID), $kitUnitOptions, $kitRoleOptions, $kitUnitsReady, $h.
 */
$audience = (string) ($audience ?? 'all');
$kitUnitOptions = is_array($kitUnitOptions ?? null) ? $kitUnitOptions : [];
$kitRoleOptions = is_array($kitRoleOptions ?? null) ? $kitRoleOptions : [];
?>
<option value="all"<?= $audience === 'all' ? ' selected' : '' ?>>Tous les membres</option>
<?php if ($kitUnitOptions !== [] && !empty($kitUnitsReady)): ?>
<optgroup label="Une unité">
    <?php foreach ($kitUnitOptions as $uid => $uname): ?>
    <option value="unit:<?= (int) $uid ?>"<?= $audience === 'unit:' . (int) $uid ? ' selected' : '' ?> translate="no"><?= $h((string) $uname) ?></option>
    <?php endforeach; ?>
</optgroup>
<?php endif; ?>
<?php if ($kitRoleOptions !== []): ?>
<optgroup label="Un rôle">
    <?php foreach ($kitRoleOptions as $rid => $rname): ?>
    <option value="role:<?= (int) $rid ?>"<?= $audience === 'role:' . (int) $rid ? ' selected' : '' ?> translate="no"><?= $h((string) $rname) ?></option>
    <?php endforeach; ?>
</optgroup>
<?php endif; ?>
