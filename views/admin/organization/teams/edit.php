<?php
declare(strict_types=1);

/**
 * Modifier une équipe (formulaire commun des groupes, sans le choix du type de structure).
 */

$team = $team ?? null;
if (!$team) {
    echo '<p>Équipe introuvable.</p>';
    return;
}
$unitKind = 'team';
$tid = (int) $team['id'];
$parents = is_array($parents ?? null) ? array_values($parents) : [];
$users = is_array($users ?? null) ? $users : [];
$unitTypes = [];
$currentType = 'team';
$formGroup = $team;
$formAction = url('back-office/teams/' . $tid . '/update');
$formSubmit = 'Enregistrer';
$formCancel = url('back-office/teams/' . $tid);
$formIdPrefix = 'team-edit';
require base_path('views/admin/organization/groups/_form.php');
