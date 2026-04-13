<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Rattache operational.board.view au rôle « Opérateur » (member) pour les tenants existants.
 */
final class OperationalBoardMemberViewPermission extends AbstractMigration
{
    public function up(): void
    {
        if (!$this->hasTable('permissions') || !$this->hasTable('roles') || !$this->hasTable('role_permissions')) {
            return;
        }
        $this->execute(<<<'SQL'
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.tenant_id = r.tenant_id
WHERE r.slug = 'member' AND p.slug = 'operational.board.view'
SQL);
    }

    public function down(): void
    {
        if (!$this->hasTable('permissions') || !$this->hasTable('roles') || !$this->hasTable('role_permissions')) {
            return;
        }
        $this->execute(<<<'SQL'
DELETE rp FROM role_permissions rp
INNER JOIN roles r ON r.id = rp.role_id
INNER JOIN permissions p ON p.id = rp.permission_id
WHERE r.slug = 'member' AND p.slug = 'operational.board.view'
SQL);
    }
}
