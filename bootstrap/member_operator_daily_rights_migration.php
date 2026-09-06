<?php

declare(strict_types=1);

use App\Services\Rbac\CommunityAccessProfiles;
use App\Services\Rbac\RolePermissionMatrixCatalog;
use App\Support\SqlText;

/**
 * Pack Membre / Opérateur : vie courante (forum, opérations en lecture, ATAK personnel, back-office personnel).
 * N’ouvre ni les finances, ni l’administration, ni le renseignement interpersonnel.
 */
return static function (PDO $pdo): void {
    $slugs = CommunityAccessProfiles::memberPermissionSlugs();
    if ($slugs === []) {
        return;
    }
    $in = SqlText::inLiterals($pdo, 'p.slug', $slugs);
    $sql = "
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p
    ON p.tenant_id = r.tenant_id
   AND {$in}
WHERE r.tenant_id IS NOT NULL
  AND r.slug IN ('member', 'hr')
";
    try {
        $n = $pdo->exec($sql);
        echo 'member_operator_daily_rights : ' . (int) $n . " liaison(s) ajoutée(s).\n";
    } catch (PDOException $e) {
        echo '  [ATTENTION] member_operator_daily_rights : ' . $e->getMessage() . "\n";
    }

    $hasMatrix = $pdo->query(
        "SELECT 1 FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'role_module_access' LIMIT 1"
    );
    if (!$hasMatrix || !$hasMatrix->fetchColumn()) {
        return;
    }

    $saFiche = RolePermissionMatrixCatalog::LEVEL_SA_FICHE;
    try {
        $upd = $pdo->prepare(
            "UPDATE role_module_access rma
             INNER JOIN roles r ON r.id = rma.role_id AND r.tenant_id = rma.tenant_id
             SET rma.access_level = ?, rma.updated_at = NOW()
             WHERE r.slug = 'member'
               AND rma.module_key IN ('atak', 'systems')
               AND rma.access_level IN ('none', '')"
        );
        $upd->execute([$saFiche]);
        echo 'member_operator_daily_rights : ' . (int) $upd->rowCount() . " niveau(x) ATAK/systèmes mis à jour.\n";
    } catch (PDOException $e) {
        echo '  [ATTENTION] member_operator_daily_rights matrice : ' . $e->getMessage() . "\n";
    }

    try {
        $ins = $pdo->prepare(
            "INSERT IGNORE INTO role_module_access (tenant_id, role_id, module_key, access_level, can_delete, can_export, updated_at)
             SELECT r.tenant_id, r.id, m.module_key, ?, 0, 0, NOW()
             FROM roles r
             INNER JOIN (
                SELECT 'atak' AS module_key
                UNION ALL
                SELECT 'systems'
             ) m
             WHERE r.tenant_id IS NOT NULL
               AND r.slug = 'member'"
        );
        $ins->execute([$saFiche]);
    } catch (PDOException $e) {
        echo '  [ATTENTION] member_operator_daily_rights insertion matrice : ' . $e->getMessage() . "\n";
    }
};
