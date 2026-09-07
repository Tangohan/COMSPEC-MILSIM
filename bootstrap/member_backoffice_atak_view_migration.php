<?php

declare(strict_types=1);

/**
 * Accorde au socle Membre (et donc RH) la consultation du back-office personnel
 * et des terminaux ATAK, sans droits d’administration.
 */
return static function (PDO $pdo): void {
    $sql = <<<'SQL'
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p
    ON p.tenant_id = r.tenant_id
   AND p.slug IN ('admin.backoffice.view', 'atak.terminals.view')
WHERE r.tenant_id IS NOT NULL
  AND r.slug IN ('member', 'hr')
SQL;
    try {
        $n = $pdo->exec($sql);
        echo 'member_backoffice_atak_view : ' . (int) $n . " liaison(s) ajoutée(s).\n";
    } catch (PDOException $e) {
        echo '  [ATTENTION] member_backoffice_atak_view : ' . $e->getMessage() . "\n";
    }
};
