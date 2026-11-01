<?php
declare(strict_types=1);

/**
 * Créer une équipe (formulaire commun des groupes, sans le choix du type de structure).
 */

$unitKind = 'team';
$parents = is_array($parents ?? null) ? array_values($parents) : [];
$users = is_array($users ?? null) ? $users : [];
$unitTypes = [];
$currentType = 'team';
$formGroup = [];
$formAction = url('back-office/teams/store');
$formSubmit = 'Créer l’équipe';
$formCancel = url('back-office/teams');
$formIdPrefix = 'team-create';
require base_path('views/admin/organization/groups/_form.php');
