<?php
declare(strict_types=1);
/**
 * Options de la liste « À qui proposer ce kit » : tous les membres, une unité, un rôle, une qualification, un membre.
 * Variables : $audience (valeur choisie : all, unit:ID, role:ID, qualification:ID, member:ID), $kitUnitOptions,
 * $kitRoleOptions, $kitUnitsReady, $h ; facultatives : $kitQualificationOptions, $kitMemberOptions.
 */
$audience = (string) ($audience ?? 'all');
$kitUnitOptions = is_array($kitUnitOptions ?? null) ? $kitUnitOptions : [];
$kitRoleOptions = is_array($kitRoleOptions ?? null) ? $kitRoleOptions : [];
$kitQualificationOptions = is_array($kitQualificationOptions ?? null) ? $kitQualificationOptions : [];
$kitMemberOptions = is_array($kitMemberOptions ?? null) ? $kitMemberOptions : [];
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
<?php if ($kitQualificationOptions !== []): ?>
<optgroup label="Une qualification">
    <?php foreach ($kitQualificationOptions as $qid => $qname): ?>
    <option value="qualification:<?= (int) $qid ?>"<?= $audience === 'qualification:' . (int) $qid ? ' selected' : '' ?> translate="no"><?= $h((string) $qname) ?></option>
    <?php endforeach; ?>
</optgroup>
<?php endif; ?>
<?php if ($kitMemberOptions !== []): ?>
<optgroup label="Un membre (kit personnel)">
    <?php foreach ($kitMemberOptions as $mid => $mname): ?>
    <option value="member:<?= (int) $mid ?>"<?= $audience === 'member:' . (int) $mid ? ' selected' : '' ?> translate="no"><?= $h((string) $mname) ?></option>
    <?php endforeach; ?>
</optgroup>
<?php endif; ?>
