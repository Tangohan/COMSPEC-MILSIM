<?php
declare(strict_types=1);

/**
 * Modifier un groupe (formulaire commun _form.php).
 */

$group = $group ?? null;
if (!$group) {
    echo '<p>Groupe introuvable.</p>';
    return;
}
$gid = (int) $group['id'];
$parents = is_array($parents ?? null) ? array_values($parents) : [];
$users = is_array($users ?? null) ? $users : [];
$unitTypes = $unitTypes ?? config('units.types', []);
$currentType = (string) ($group['type'] ?? 'group');
$formGroup = $group;
$formAction = url('back-office/groups/' . $gid . '/update');
$formSubmit = 'Enregistrer';
$formCancel = url('back-office/groups/' . $gid);
$formIdPrefix = 'group-edit';
require __DIR__ . '/_form.php';
