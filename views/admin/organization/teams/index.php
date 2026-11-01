<?php
declare(strict_types=1);

/**
 * Équipes tactiques — même liste en cartes que les groupes (views/admin/organization/groups/index.php).
 *
 * @var list<array<string, mixed>> $teams
 */

$unitKind = 'team';
$groups = is_array($teams ?? null) ? $teams : [];
require base_path('views/admin/organization/groups/index.php');
