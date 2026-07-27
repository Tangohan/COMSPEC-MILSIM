<?php

declare(strict_types=1);

use App\Authorization\SystemReservedPermissions;

/**
 * Purge les habilitations réservées à la plateforme qui seraient rattachées à une communauté.
 *
 * Le runtime les ignore déjà ({@see \App\Services\Rbac\RbacService}), mais les laisser en base
 * fausse les écrans de rôles et les exports de conformité : un administrateur y verrait une case
 * cochée qui n’accorde rien. Ce script remet la base en accord avec l’invariant.
 *
 * Trois nettoyages, dans cet ordre :
 *  1. liens `role_permissions` d’un rôle de communauté vers une permission réservée ;
 *  2. surcharges `user_permission_overrides` accordant une permission réservée ;
 *  3. lignes `permissions` réservées créées dans le périmètre d’un tenant.
 *
 * Les rôles site (`roles.tenant_id IS NULL`) ne sont jamais touchés : c’est leur rôle légitime
 * de porter ces habilitations.
 *
 * @return callable(PDO): array{role_links: int, user_overrides: int, tenant_permissions: int}
 */
return static function (PDO $pdo): array {
    // run-migrations.php est un moteur procédural sans autoloader Composer :
    // on charge la classe d’invariant directement si besoin.
    if (!class_exists(SystemReservedPermissions::class)) {
        $classFile = dirname(__DIR__) . '/app/Authorization/SystemReservedPermissions.php';
        if (!is_file($classFile)) {
            return ['role_links' => 0, 'user_overrides' => 0, 'tenant_permissions' => 0];
        }
        require_once $classFile;
    }

    $tableExists = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    $report = ['role_links' => 0, 'user_overrides' => 0, 'tenant_permissions' => 0];
    if (!$tableExists('permissions') || !$tableExists('roles')) {
        return $report;
    }

    $reserved = SystemReservedPermissions::sqlCondition('p.slug');

    // 1. Un rôle de communauté ne doit porter aucune habilitation plateforme.
    if ($tableExists('role_permissions')) {
        try {
            $st = $pdo->prepare(
                "DELETE rp FROM role_permissions rp
                 INNER JOIN permissions p ON p.id = rp.permission_id
                 INNER JOIN roles r ON r.id = rp.role_id
                 WHERE r.tenant_id IS NOT NULL AND $reserved"
            );
            $st->execute();
            $report['role_links'] = $st->rowCount();
        } catch (\Throwable) {
        }
    }

    // 2. Une surcharge utilisateur rattachée à un tenant ne doit pas accorder ces habilitations.
    if ($tableExists('user_permission_overrides')) {
        try {
            $st = $pdo->prepare(
                "DELETE o FROM user_permission_overrides o
                 INNER JOIN permissions p ON p.id = o.permission_id
                 WHERE o.tenant_id IS NOT NULL AND o.grant_flag = 1 AND $reserved"
            );
            $st->execute();
            $report['user_overrides'] = $st->rowCount();
        } catch (\Throwable) {
        }
    }

    // 3. Ces slugs n’ont pas d’existence légitime dans le périmètre d’un tenant :
    //    seule la ligne globale (`tenant_id IS NULL`) est conservée.
    try {
        $st = $pdo->prepare(
            "DELETE p FROM permissions p WHERE p.tenant_id IS NOT NULL AND $reserved"
        );
        $st->execute();
        $report['tenant_permissions'] = $st->rowCount();
    } catch (\Throwable) {
    }

    return $report;
};
