<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class TenantAtakConfigRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function getByTenantId(int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tenant_atak_config WHERE tenant_id = ? LIMIT 1');
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function createOrUpdate(int $tenantId, array $data): void
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM tenant_atak_config WHERE tenant_id = ? LIMIT 1');
        $stmt->execute([$tenantId]);
        $exists = (bool) $stmt->fetchColumn();

        $fields = [
            'node_url' => $data['node_url'] ?? null,
            'jwt_secret' => $data['jwt_secret'] ?? null,
            'arma_server_host' => $data['arma_server_host'] ?? null,
            'arma_server_port' => isset($data['arma_server_port']) && $data['arma_server_port'] !== '' ? (int) $data['arma_server_port'] : null,
            'arma_mod_credentials' => $data['arma_mod_credentials'] ?? null,
            'instructions' => $data['instructions'] ?? null,
            'default_map_slug' => isset($data['default_map_slug']) && $data['default_map_slug'] !== '' ? (string) $data['default_map_slug'] : 'altis',
        ];

        if ($exists) {
            $stmt = $this->pdo->prepare(
                'UPDATE tenant_atak_config SET node_url = ?, jwt_secret = ?, arma_server_host = ?, arma_server_port = ?, arma_mod_credentials = ?, instructions = ?, default_map_slug = ?, updated_at = NOW() WHERE tenant_id = ?'
            );
            $stmt->execute([
                $fields['node_url'],
                $fields['jwt_secret'],
                $fields['arma_server_host'],
                $fields['arma_server_port'],
                $fields['arma_mod_credentials'],
                $fields['instructions'],
                $fields['default_map_slug'],
                $tenantId,
            ]);
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO tenant_atak_config (tenant_id, node_url, jwt_secret, arma_server_host, arma_server_port, arma_mod_credentials, instructions, default_map_slug, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
            );
            $stmt->execute([
                $tenantId,
                $fields['node_url'],
                $fields['jwt_secret'],
                $fields['arma_server_host'],
                $fields['arma_server_port'],
                $fields['arma_mod_credentials'],
                $fields['instructions'],
                $fields['default_map_slug'],
            ]);
        }
    }
}
