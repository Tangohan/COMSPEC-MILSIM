<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

class DefaultTenantSeeder extends AbstractSeed
{
    public function run(): void
    {
        $conn = $this->getAdapter()->getConnection();

        $conn->exec("INSERT INTO tenants (name, slug, logo_url, settings, created_at, updated_at) VALUES ('Pas d\'organisation', 'default', NULL, NULL, NOW(), NOW())");
        $tenantId = (int) $conn->lastInsertId();
        if ($tenantId === 0) {
            $row = $conn->query("SELECT id FROM tenants WHERE slug = 'default' LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
            $tenantId = (int) ($row['id'] ?? 0);
        }
        if ($tenantId === 0) {
            return;
        }

        $conn->exec("INSERT INTO roles (tenant_id, name, slug, description, is_system, created_at) VALUES ($tenantId, 'Administrator', 'tenant_admin', 'Full access', 1, NOW())");
        $roleId = (int) $conn->lastInsertId();
        if ($roleId === 0) {
            $row = $conn->query("SELECT id FROM roles WHERE tenant_id = $tenantId AND slug = 'tenant_admin' LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
            $roleId = (int) ($row['id'] ?? 0);
        }

        $conn->exec("INSERT INTO grades (tenant_id, name, short_name, rank_order, created_at) VALUES ($tenantId, 'Officer', 'OFR', 10, NOW())");
        $gradeId = (int) $conn->lastInsertId();
        if ($gradeId === 0) {
            $row = $conn->query("SELECT id FROM grades WHERE tenant_id = $tenantId LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
            $gradeId = (int) ($row['id'] ?? 0);
        }

        $hash = password_hash('admin', PASSWORD_ARGON2ID);
        $hashQuoted = $conn->quote($hash);
        $conn->exec("INSERT INTO users (tenant_id, email, password_hash, display_name, callsign, role_id, grade_id, status, created_at, updated_at) VALUES ($tenantId, 'admin@athena.local', $hashQuoted, 'Admin', 'ADMIN', $roleId, $gradeId, 'active', NOW(), NOW())");
    }
}
