<?php
declare(strict_types=1);

/**
 * Créer un groupe (formulaire commun _form.php).
 */

$parents = is_array($parents ?? null) ? array_values($parents) : [];
$users = is_array($users ?? null) ? $users : [];
$unitTypes = $unitTypes ?? config('units.types', []);
$currentType = (string) ($defaultType ?? 'group');
$formGroup = [];
$formAction = url('back-office/groups/store');
$formSubmit = 'Créer le groupe';
$formCancel = url('back-office/groups');
$formIdPrefix = 'group-create';
require __DIR__ . '/_form.php';
