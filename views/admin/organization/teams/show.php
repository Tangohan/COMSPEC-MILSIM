<?php
declare(strict_types=1);

/**
 * Fiche d’une équipe — même fiche que les groupes (views/admin/organization/groups/show.php).
 *
 * @var array<string, mixed>|null $team
 */

$unitKind = 'team';
$group = $team ?? null;
require base_path('views/admin/organization/groups/show.php');
