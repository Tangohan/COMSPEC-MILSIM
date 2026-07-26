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

    public function isMaintenanceEnabled(int $tenantId): bool
    {
        if ($tenantId < 1) {
            return false;
        }
        $this->ensureMaintenanceSchema();
        if (!$this->hasMaintenanceColumns()) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'SELECT maintenance_enabled FROM tenant_atak_config WHERE tenant_id = ? LIMIT 1'
        );
        $stmt->execute([$tenantId]);
        $val = $stmt->fetchColumn();

        return (int) $val === 1;
    }

    public function getMaintenanceMessage(int $tenantId): string
    {
        if ($tenantId < 1) {
            return '';
        }
        $this->ensureMaintenanceSchema();
        if (!$this->hasMaintenanceColumns()) {
            return '';
        }
        $stmt = $this->pdo->prepare(
            'SELECT maintenance_message FROM tenant_atak_config WHERE tenant_id = ? LIMIT 1'
        );
        $stmt->execute([$tenantId]);
        $msg = $stmt->fetchColumn();

        return is_string($msg) ? trim($msg) : '';
    }

    /**
     * Active / désactive le mode maintenance pour une communauté.
     * @return bool true si l’écriture a réussi ; false si schéma absent ou tenant invalide
     */
    public function setMaintenance(int $tenantId, bool $enabled, ?string $message = null): bool
    {
        if ($tenantId < 1) {
            return false;
        }
        $this->ensureMaintenanceSchema();
        if (!$this->hasMaintenanceColumns()) {
            return false;
        }
        $msg = $message !== null ? trim($message) : null;
        if ($msg === '') {
            $msg = null;
        }

        try {
            $stmt = $this->pdo->prepare('SELECT 1 FROM tenant_atak_config WHERE tenant_id = ? LIMIT 1');
            $stmt->execute([$tenantId]);
            $exists = (bool) $stmt->fetchColumn();

            if ($exists) {
                $upd = $this->pdo->prepare(
                    'UPDATE tenant_atak_config
                     SET maintenance_enabled = ?, maintenance_message = ?, updated_at = NOW()
                     WHERE tenant_id = ?'
                );
                $upd->execute([$enabled ? 1 : 0, $msg, $tenantId]);
            } else {
                $ins = $this->pdo->prepare(
                    'INSERT INTO tenant_atak_config
                        (tenant_id, maintenance_enabled, maintenance_message, default_map_slug, created_at, updated_at)
                     VALUES (?, ?, ?, \'altis\', NOW(), NOW())'
                );
                $ins->execute([$tenantId, $enabled ? 1 : 0, $msg]);
            }
        } catch (\Throwable) {
            return false;
        }

        return true;
    }

    /** Colonnes maintenance présentes (migration jouée ou auto-appliquée). */
    public function isMaintenanceSchemaReady(): bool
    {
        $this->ensureMaintenanceSchema();

        return $this->hasMaintenanceColumns();
    }

    /**
     * Applique les colonnes maintenance si absentes (idempotent, même DDL que la migration bootstrap).
     */
    private function ensureMaintenanceSchema(): void
    {
        static $attempted = false;
        if ($attempted) {
            return;
        }
        $attempted = true;

        if ($this->hasMaintenanceColumns()) {
            return;
        }

        try {
            $this->pdo->exec(
                'ALTER TABLE tenant_atak_config
                 ADD COLUMN maintenance_enabled TINYINT(1) NOT NULL DEFAULT 0,
                 ADD COLUMN maintenance_message TEXT DEFAULT NULL'
            );
            // Invalider le cache de détection
            $this->resetMaintenanceColumnsCache();
        } catch (\Throwable) {
            // Colonnes déjà présentes (course) ou droits insuffisants — hasMaintenanceColumns tranchera
            $this->resetMaintenanceColumnsCache();
        }
    }

    private function resetMaintenanceColumnsCache(): void
    {
        // Relance la détection au prochain appel (static locale dans hasMaintenanceColumns)
        $this->hasMaintenanceColumns(true);
    }

    private function hasMaintenanceColumns(bool $resetCache = false): bool
    {
        static $cached = null;
        if ($resetCache) {
            $cached = null;
        }
        if ($cached !== null) {
            return $cached;
        }
        try {
            $st = $this->pdo->query(
                "SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenant_atak_config' AND COLUMN_NAME = 'maintenance_enabled' LIMIT 1"
            );
            if ($st && $st->fetchColumn()) {
                $cached = true;

                return true;
            }
        } catch (\Throwable) {
            // Fallback ci-dessous (hébergeurs qui restreignent information_schema)
        }

        try {
            $st = $this->pdo->query("SHOW COLUMNS FROM tenant_atak_config LIKE 'maintenance_enabled'");
            if ($st && $st->fetch(PDO::FETCH_ASSOC)) {
                $cached = true;

                return true;
            }
        } catch (\Throwable) {
            // Dernier recours : SELECT direct
        }

        try {
            $this->pdo->query('SELECT maintenance_enabled FROM tenant_atak_config LIMIT 0');
            $cached = true;
        } catch (\Throwable) {
            $cached = false;
        }

        return $cached;
    }

    /**
     * Génère (ou régénère) la clé d’accès Overwatch pour une communauté.
     * La clé en clair n’est renvoyée qu’une fois ; elle est stockée pour le redeem / l’auth.
     *
     * @return array{plain_key: string, prefix: string}|null
     */
    public function generateAccessKey(int $tenantId): ?array
    {
        if ($tenantId < 1) {
            return null;
        }
        $plain = 'ow_' . bin2hex(random_bytes(24));
        $prefix = substr($plain, 0, 10);

        $stmt = $this->pdo->prepare('SELECT 1 FROM tenant_atak_config WHERE tenant_id = ? LIMIT 1');
        $stmt->execute([$tenantId]);
        $exists = (bool) $stmt->fetchColumn();

        if ($exists) {
            $upd = $this->pdo->prepare(
                'UPDATE tenant_atak_config
                 SET access_key = ?, access_key_prefix = ?, access_key_generated_at = NOW(), updated_at = NOW()
                 WHERE tenant_id = ?'
            );
            $upd->execute([$plain, $prefix, $tenantId]);
        } else {
            $ins = $this->pdo->prepare(
                'INSERT INTO tenant_atak_config
                    (tenant_id, access_key, access_key_prefix, access_key_generated_at, default_map_slug, created_at, updated_at)
                 VALUES (?, ?, ?, NOW(), \'altis\', NOW(), NOW())'
            );
            $ins->execute([$tenantId, $plain, $prefix]);
        }

        return ['plain_key' => $plain, 'prefix' => $prefix];
    }

    /** Clé d’accès Overwatch de la communauté (vide si non générée). */
    public function getAccessKey(int $tenantId): string
    {
        if ($tenantId < 1 || !$this->hasAccessKeyColumn()) {
            return '';
        }
        $stmt = $this->pdo->prepare('SELECT access_key FROM tenant_atak_config WHERE tenant_id = ? LIMIT 1');
        $stmt->execute([$tenantId]);
        $key = $stmt->fetchColumn();

        return is_string($key) ? trim($key) : '';
    }

    /**
     * Vérifie si une clé présentée correspond à une clé de communauté.
     * @return int|null tenant_id si trouvé
     */
    public function findTenantIdByAccessKey(string $presented): ?int
    {
        $presented = trim($presented);
        if ($presented === '' || !$this->hasAccessKeyColumn()) {
            return null;
        }
        try {
            $stmt = $this->pdo->prepare(
                'SELECT tenant_id, access_key FROM tenant_atak_config
                 WHERE access_key IS NOT NULL AND access_key != \'\' LIMIT 500'
            );
            $stmt->execute();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $stored = trim((string) ($row['access_key'] ?? ''));
                if ($stored !== '' && hash_equals($stored, $presented)) {
                    $tid = (int) ($row['tenant_id'] ?? 0);

                    return $tid > 0 ? $tid : null;
                }
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    private function hasAccessKeyColumn(): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        try {
            $st = $this->pdo->query(
                "SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenant_atak_config' AND COLUMN_NAME = 'access_key' LIMIT 1"
            );
            $cached = (bool) $st?->fetchColumn();
        } catch (\Throwable) {
            $cached = false;
        }

        return $cached;
    }

    /** Colonne experience_config présente (migration jouée ou auto-appliquée). */
    public function isExperienceSchemaReady(): bool
    {
        $this->ensureExperienceSchema();

        return $this->hasExperienceColumn();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getExperienceConfigRaw(int $tenantId): ?array
    {
        if ($tenantId < 1) {
            return null;
        }
        $this->ensureExperienceSchema();
        if (!$this->hasExperienceColumn()) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT experience_config FROM tenant_atak_config WHERE tenant_id = ? LIMIT 1'
        );
        $stmt->execute([$tenantId]);
        $raw = $stmt->fetchColumn();
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }
        $data = json_decode($raw, true);

        return is_array($data) ? $data : null;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function saveExperienceConfig(int $tenantId, array $config): bool
    {
        if ($tenantId < 1) {
            return false;
        }
        $this->ensureExperienceSchema();
        if (!$this->hasExperienceColumn()) {
            return false;
        }
        $json = json_encode($config, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return false;
        }

        try {
            $stmt = $this->pdo->prepare('SELECT 1 FROM tenant_atak_config WHERE tenant_id = ? LIMIT 1');
            $stmt->execute([$tenantId]);
            $exists = (bool) $stmt->fetchColumn();
            if ($exists) {
                $upd = $this->pdo->prepare(
                    'UPDATE tenant_atak_config SET experience_config = ?, updated_at = NOW() WHERE tenant_id = ?'
                );
                $upd->execute([$json, $tenantId]);
            } else {
                $ins = $this->pdo->prepare(
                    'INSERT INTO tenant_atak_config (tenant_id, experience_config, default_map_slug, created_at, updated_at)
                     VALUES (?, ?, \'altis\', NOW(), NOW())'
                );
                $ins->execute([$tenantId, $json]);
            }
        } catch (\Throwable) {
            return false;
        }

        return true;
    }

    private function ensureExperienceSchema(): void
    {
        static $attempted = false;
        if ($attempted) {
            return;
        }
        $attempted = true;
        if ($this->hasExperienceColumn()) {
            return;
        }
        try {
            $this->pdo->exec(
                'ALTER TABLE tenant_atak_config ADD COLUMN experience_config JSON DEFAULT NULL'
            );
            $this->resetExperienceColumnCache();
        } catch (\Throwable) {
            $this->resetExperienceColumnCache();
        }
    }

    private function resetExperienceColumnCache(): void
    {
        $this->hasExperienceColumn(true);
    }

    private function hasExperienceColumn(bool $resetCache = false): bool
    {
        static $cached = null;
        if ($resetCache) {
            $cached = null;
        }
        if ($cached !== null) {
            return $cached;
        }
        try {
            $st = $this->pdo->query(
                "SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenant_atak_config' AND COLUMN_NAME = 'experience_config' LIMIT 1"
            );
            if ($st && $st->fetchColumn()) {
                $cached = true;

                return true;
            }
        } catch (\Throwable) {
        }
        try {
            $st = $this->pdo->query("SHOW COLUMNS FROM tenant_atak_config LIKE 'experience_config'");
            if ($st && $st->fetch(PDO::FETCH_ASSOC)) {
                $cached = true;

                return true;
            }
        } catch (\Throwable) {
        }
        try {
            $this->pdo->query('SELECT experience_config FROM tenant_atak_config LIMIT 0');
            $cached = true;
        } catch (\Throwable) {
            $cached = false;
        }

        return $cached;
    }
}
