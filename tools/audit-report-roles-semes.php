<?php

declare(strict_types=1);

/**
 * RD-03 — Rôles semés non visibles dans « Niveaux d'accès », et membres qui en dépendent (« qui perd quoi »).
 *
 * L'écran Niveaux d'accès ne montre que Membre, RH, Gestionnaire (sauf RH masqué) et les niveaux personnalisés
 * (CommunityAccessProfiles : slugs member, hr, community_owner, access-custom-*). Les autres rôles d'une communauté
 * (rôles militaires et d'affichage semés, positions administratives « status_* », anciens rôles) restent en base et
 * continuent de donner leurs droits via `tenant_user_roles` (RbacService). Ce rapport, par communauté :
 *   A. liste les rôles non visibles qui portent au moins un droit, avec le nombre de membres actifs qui les tiennent ;
 *   B. liste, pour chaque membre actif, les droits qu'il ne tiendrait plus si ces rôles disparaissaient, c'est-à-dire
 *      ceux qui ne lui viennent d'aucun niveau visible (« qui perd quoi » du lot 3.9).
 * Les habilitations réservées à la plateforme (SystemReservedPermissions) sont ignorées, comme dans RbacService.
 * Un membre sans ligne `tenant_user_roles` est lu par son rôle historique `users.role_id`, comme RbacService.
 *
 * LECTURE SEULE : aucun rôle n'est supprimé, masqué ni migré.
 *
 *   php tools/audit-report-roles-semes.php [--tenant=ID] [--format=text|csv|json] [--limit=N]
 */

require_once __DIR__ . '/lib/audit-report-kit.php';

function audit_report_roles_semes(AuditReportKit $kit, AuditReadOnlyDb $db): int
{
    $tenant = $kit->options['tenant'];
    $now = date('Y-m-d H:i:s');

    foreach (['roles', 'permissions', 'role_permissions', 'tenant_user_roles', 'users'] as $table) {
        if (!$db->hasTable($table)) {
            fwrite(STDERR, "Table absente : $table\n");
            return 1;
        }
    }
    $hasActive = $db->hasColumn('roles', 'is_active');

    // Rôles de communauté (un rôle sans tenant est un rôle de site : hors sujet).
    $roles = [];
    foreach ($db->rows(
        'SELECT r.id, r.tenant_id, r.slug, r.name, r.role_layer, r.is_system, ' . ($hasActive ? 'r.is_active' : '1') . ' AS is_active
         FROM roles r WHERE r.tenant_id IS NOT NULL' . ($tenant > 0 ? ' AND r.tenant_id = ?' : ''),
        $tenant > 0 ? [$tenant] : []
    ) as $r) {
        $slug = strtolower(trim((string) $r['slug']));
        $access = in_array($slug, ['member', 'hr', 'community_owner'], true) || str_starts_with($slug, 'access-custom-');
        // RH masqué par la communauté : n'est plus un niveau visible (RoleRepository::isHiddenAccessRow).
        $hidden = $slug === 'hr' && (int) $r['is_active'] === 0;
        $r['visible'] = $access && !$hidden;
        $roles[(int) $r['id']] = $r;
    }

    // Droits portés par chaque rôle (hors habilitations plateforme).
    $perms = [];
    foreach ($db->rows(
        'SELECT rp.role_id, p.slug FROM role_permissions rp
         INNER JOIN permissions p ON p.id = rp.permission_id
         INNER JOIN roles r ON r.id = rp.role_id
         WHERE r.tenant_id IS NOT NULL' . ($tenant > 0 ? ' AND r.tenant_id = ?' : ''),
        $tenant > 0 ? [$tenant] : []
    ) as $row) {
        $slug = (string) $row['slug'];
        if ($slug !== '' && !\App\Authorization\SystemReservedPermissions::isReserved($slug)) {
            $perms[(int) $row['role_id']][$slug] = true;
        }
    }

    // Rôles tenus : lignes valides de tenant_user_roles, sinon rôle historique du compte.
    $held = [];
    foreach ($db->rows(
        'SELECT tur.tenant_id, tur.user_id, tur.role_id, tur.org_unit_id, tur.valid_from, tur.valid_until
         FROM tenant_user_roles tur' . ($tenant > 0 ? ' WHERE tur.tenant_id = ?' : ''),
        $tenant > 0 ? [$tenant] : []
    ) as $row) {
        $from = (string) ($row['valid_from'] ?? '');
        $until = (string) ($row['valid_until'] ?? '');
        if (($from !== '' && $from > $now) || ($until !== '' && $until <= $now)) {
            continue;
        }
        $held[(int) $row['tenant_id']][(int) $row['user_id']][(int) $row['role_id']] = $row['org_unit_id'] !== null ? (int) $row['org_unit_id'] : 0;
    }
    $users = [];
    foreach ($db->rows(
        "SELECT u.id, u.tenant_id, u.display_name, u.status, u.role_id FROM users u WHERE u.status = 'active'" . ($tenant > 0 ? ' AND u.tenant_id = ?' : ''),
        $tenant > 0 ? [$tenant] : []
    ) as $u) {
        $users[(int) $u['tenant_id']][(int) $u['id']] = $u;
        if (!isset($held[(int) $u['tenant_id']][(int) $u['id']]) && (int) ($u['role_id'] ?? 0) > 0) {
            $held[(int) $u['tenant_id']][(int) $u['id']][(int) $u['role_id']] = 0;
        }
    }

    $hiddenRoles = [];
    $losses = [];
    $lostMembers = 0;
    $tenantIds = array_keys($users);
    sort($tenantIds);
    foreach ($tenantIds as $tid) {
        $holders = [];
        foreach ($users[$tid] as $uid => $u) {
            $visiblePerms = [];
            $hiddenPerms = [];
            $viaRoles = [];
            foreach ($held[$tid][$uid] ?? [] as $rid => $unit) {
                $role = $roles[$rid] ?? null;
                if ($role === null || (int) $role['tenant_id'] !== $tid) {
                    continue;
                }
                $slugs = array_keys($perms[$rid] ?? []);
                if ($role['visible']) {
                    foreach ($slugs as $s) {
                        $visiblePerms[$s] = true;
                    }
                    continue;
                }
                if ($slugs === []) {
                    continue;
                }
                $holders[$rid][$uid] = true;
                $viaRoles[$rid] = $role['slug'];
                foreach ($slugs as $s) {
                    $hiddenPerms[$s] = ($unit > 0 ? $s . ' (unité ' . $unit . ')' : $s);
                }
            }
            $lost = array_diff_key($hiddenPerms, $visiblePerms);
            if ($lost !== []) {
                ++$lostMembers;
                ksort($lost);
                $losses[] = [
                    'tenant_id' => $tid, 'user_id' => $uid, 'display_name' => $u['display_name'],
                    'roles' => implode(', ', $viaRoles), 'count' => count($lost),
                    'sensitive' => count(array_filter(array_keys($lost), static fn (string $s): bool => str_starts_with($s, 'admin.'))) > 0 ? 'oui (admin.*)' : '',
                    'lost' => implode(', ', array_values($lost)),
                ];
            }
        }
        foreach ($roles as $rid => $role) {
            if ((int) $role['tenant_id'] !== $tid || $role['visible'] || !isset($perms[$rid])) {
                continue;
            }
            $hiddenRoles[] = [
                'tenant_id' => $tid, 'role_id' => $rid, 'slug' => $role['slug'], 'name' => $role['name'], 'layer' => $role['role_layer'],
                'is_system' => (int) $role['is_system'] === 1 ? 'oui' : '', 'permissions' => count($perms[$rid]), 'members' => count($holders[$rid] ?? []),
            ];
        }
    }
    usort($losses, static fn (array $a, array $b): int => [$a['tenant_id'], -$a['count']] <=> [$b['tenant_id'], -$b['count']]);

    $kit->say(count($hiddenRoles) . ' rôle(s) non visible(s) qui portent des droits ; ' . $lostMembers . ' membre(s) actif(s) perdraient au moins un droit.');
    $kit->table(
        'A. Rôles non visibles qui portent des droits (RD-03)',
        ['tenant_id' => 'communauté', 'slug' => 'slug', 'name' => 'nom', 'layer' => 'couche', 'is_system' => 'système', 'permissions' => 'droits', 'members' => 'membres actifs'],
        $hiddenRoles,
        'Ces rôles n’apparaissent pas dans Niveaux d’accès mais donnent encore leurs droits.'
    );
    $kit->table(
        'B. Qui perd quoi si ces rôles disparaissent (droits qui ne viennent d’aucun niveau visible)',
        ['tenant_id' => 'communauté', 'user_id' => 'membre', 'display_name' => 'nom', 'roles' => 'via les rôles', 'count' => 'nb droits perdus', 'sensitive' => 'sensible', 'lost' => 'droits perdus'],
        $losses,
        'Prévenir ces membres, ou leur attribuer un niveau visible équivalent, avant le lot 3.9.'
    );
    return $hiddenRoles === [] ? 0 : 3;
}

if (isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    $kit = new AuditReportKit(
        'audit-report-roles-semes',
        'rôles semés non visibles et membres qui en dépendent (qui perd quoi)',
        'docs/audit-backoffice RD-03 et RD-70 ; plan lot 3.9 et 6 (rapport par communauté avant suppression)'
    );
    exit(audit_report_roles_semes($kit, $kit->start($argv)));
}
