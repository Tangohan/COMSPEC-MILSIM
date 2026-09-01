<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\LazyDatabaseConnection;

use PDO;

class TenantAtakConfigRepository
{
    use LazyDatabaseConnection;


    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    public function getByTenantId(int $tenantId): ?array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM tenant_atak_config WHERE tenant_id = ? LIMIT 1');
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Récupère la configuration roleplay complète pour une communauté.
     * Retourne les valeurs par défaut si la configuration n'existe pas.
     */
    public function getRoleplayConfig(int $tenantId): array
    {
        $row = $this->getByTenantId($tenantId);
        
        $defaults = [
            // Simulation réseau
            'network_enabled' => false,
            'network_mode' => 'normal',
            'latency_min_ms' => 0,
            'latency_max_ms' => 0,
            'packet_loss_percent' => 0.0,
            'disconnect_enabled' => false,
            'disconnect_min_sec' => 5,
            'disconnect_max_sec' => 30,
            'disconnect_interval_sec' => 600,
            
            // Défauts capteur
            'sensor_enabled' => false,
            'sensor_failure_percent' => 0.0,
            'sensor_error_percent' => 0.0,
            'sensor_missing_percent' => 0.0,
            
            // Zones de dégradation
            'zones_enabled' => false,
            'zones_config' => null,

            // Données chiffrées (certificat / compromission)
            'intel_scramble_enabled' => false,
        ];

        if (!$row || !$this->hasRoleplayColumns()) {
            return $defaults;
        }

        return [
            'network_enabled' => (bool) ($row['roleplay_network_enabled'] ?? $defaults['network_enabled']),
            'network_mode' => (string) ($row['roleplay_network_mode'] ?? $defaults['network_mode']),
            'latency_min_ms' => (int) ($row['roleplay_latency_min_ms'] ?? $defaults['latency_min_ms']),
            'latency_max_ms' => (int) ($row['roleplay_latency_max_ms'] ?? $defaults['latency_max_ms']),
            'packet_loss_percent' => (float) ($row['roleplay_packet_loss_percent'] ?? $defaults['packet_loss_percent']),
            'disconnect_enabled' => (bool) ($row['roleplay_disconnect_enabled'] ?? $defaults['disconnect_enabled']),
            'disconnect_min_sec' => (int) ($row['roleplay_disconnect_min_sec'] ?? $defaults['disconnect_min_sec']),
            'disconnect_max_sec' => (int) ($row['roleplay_disconnect_max_sec'] ?? $defaults['disconnect_max_sec']),
            'disconnect_interval_sec' => (int) ($row['roleplay_disconnect_interval_sec'] ?? $defaults['disconnect_interval_sec']),
            'sensor_enabled' => (bool) ($row['roleplay_sensor_enabled'] ?? $defaults['sensor_enabled']),
            'sensor_failure_percent' => (float) ($row['roleplay_sensor_failure_percent'] ?? $defaults['sensor_failure_percent']),
            'sensor_error_percent' => (float) ($row['roleplay_sensor_error_percent'] ?? $defaults['sensor_error_percent']),
            'sensor_missing_percent' => (float) ($row['roleplay_sensor_missing_percent'] ?? $defaults['sensor_missing_percent']),
            'zones_enabled' => (bool) ($row['roleplay_zones_enabled'] ?? $defaults['zones_enabled']),
            'zones_config' => ($row['roleplay_zones_config'] ?? $defaults['zones_config']),
            'intel_scramble_enabled' => $this->hasTenantAtakConfigColumn('roleplay_intel_scramble_enabled')
                ? (bool) ($row['roleplay_intel_scramble_enabled'] ?? $defaults['intel_scramble_enabled'])
                : false,
            'intel_scramble_reviewed' => $this->hasTenantAtakConfigColumn('roleplay_intel_scramble_reviewed')
                ? (bool) ($row['roleplay_intel_scramble_reviewed'] ?? false)
                : false,
        ];
    }

    /**
     * Met à jour la configuration roleplay d'une communauté.
     */
    public function updateRoleplayConfig(int $tenantId, array $config): void
    {
        if ($tenantId < 1 || !$this->hasRoleplayColumns()) {
            return;
        }

        $stmt = $this->pdo()->prepare('SELECT 1 FROM tenant_atak_config WHERE tenant_id = ? LIMIT 1');
        $stmt->execute([$tenantId]);
        $exists = (bool) $stmt->fetchColumn();

        $fields = [
            'roleplay_network_enabled' => isset($config['network_enabled']) ? (int) $config['network_enabled'] : 0,
            'roleplay_network_mode' => $config['network_mode'] ?? 'normal',
            'roleplay_latency_min_ms' => isset($config['latency_min_ms']) ? (int) $config['latency_min_ms'] : 0,
            'roleplay_latency_max_ms' => isset($config['latency_max_ms']) ? (int) $config['latency_max_ms'] : 0,
            'roleplay_packet_loss_percent' => isset($config['packet_loss_percent']) ? (float) $config['packet_loss_percent'] : 0.0,
            'roleplay_disconnect_enabled' => isset($config['disconnect_enabled']) ? (int) $config['disconnect_enabled'] : 0,
            'roleplay_disconnect_min_sec' => isset($config['disconnect_min_sec']) ? (int) $config['disconnect_min_sec'] : 5,
            'roleplay_disconnect_max_sec' => isset($config['disconnect_max_sec']) ? (int) $config['disconnect_max_sec'] : 30,
            'roleplay_disconnect_interval_sec' => isset($config['disconnect_interval_sec']) ? (int) $config['disconnect_interval_sec'] : 600,
            'roleplay_sensor_enabled' => isset($config['sensor_enabled']) ? (int) $config['sensor_enabled'] : 0,
            'roleplay_sensor_failure_percent' => isset($config['sensor_failure_percent']) ? (float) $config['sensor_failure_percent'] : 0.0,
            'roleplay_sensor_error_percent' => isset($config['sensor_error_percent']) ? (float) $config['sensor_error_percent'] : 0.0,
            'roleplay_sensor_missing_percent' => isset($config['sensor_missing_percent']) ? (float) $config['sensor_missing_percent'] : 0.0,
            'roleplay_zones_enabled' => isset($config['zones_enabled']) ? (int) $config['zones_enabled'] : 0,
            'roleplay_zones_config' => $config['zones_config'] ?? null,
            'roleplay_intel_scramble_enabled' => isset($config['intel_scramble_enabled'])
                ? (int) $config['intel_scramble_enabled']
                : 0,
            'roleplay_intel_scramble_reviewed' => isset($config['intel_scramble_reviewed'])
                ? (int) $config['intel_scramble_reviewed']
                : 1,
        ];

        $scrambleCol = $this->hasTenantAtakConfigColumn('roleplay_intel_scramble_enabled');
        $reviewedCol = $this->hasTenantAtakConfigColumn('roleplay_intel_scramble_reviewed');

        if ($exists) {
            $sql = 'UPDATE tenant_atak_config
                 SET roleplay_network_enabled = ?, roleplay_network_mode = ?,
                     roleplay_latency_min_ms = ?, roleplay_latency_max_ms = ?,
                     roleplay_packet_loss_percent = ?,
                     roleplay_disconnect_enabled = ?, roleplay_disconnect_min_sec = ?,
                     roleplay_disconnect_max_sec = ?, roleplay_disconnect_interval_sec = ?,
                     roleplay_sensor_enabled = ?, roleplay_sensor_failure_percent = ?,
                     roleplay_sensor_error_percent = ?, roleplay_sensor_missing_percent = ?,
                     roleplay_zones_enabled = ?, roleplay_zones_config = ?';
            $params = [
                $fields['roleplay_network_enabled'],
                $fields['roleplay_network_mode'],
                $fields['roleplay_latency_min_ms'],
                $fields['roleplay_latency_max_ms'],
                $fields['roleplay_packet_loss_percent'],
                $fields['roleplay_disconnect_enabled'],
                $fields['roleplay_disconnect_min_sec'],
                $fields['roleplay_disconnect_max_sec'],
                $fields['roleplay_disconnect_interval_sec'],
                $fields['roleplay_sensor_enabled'],
                $fields['roleplay_sensor_failure_percent'],
                $fields['roleplay_sensor_error_percent'],
                $fields['roleplay_sensor_missing_percent'],
                $fields['roleplay_zones_enabled'],
                $fields['roleplay_zones_config'],
            ];
            if ($scrambleCol) {
                $sql .= ', roleplay_intel_scramble_enabled = ?';
                $params[] = $fields['roleplay_intel_scramble_enabled'];
            }
            if ($reviewedCol) {
                $sql .= ', roleplay_intel_scramble_reviewed = ?';
                $params[] = $fields['roleplay_intel_scramble_reviewed'];
            }
            $sql .= ', updated_at = NOW() WHERE tenant_id = ?';
            $params[] = $tenantId;
            $this->pdo()->prepare($sql)->execute($params);
        } else {
            $cols = [
                'tenant_id', 'roleplay_network_enabled', 'roleplay_network_mode',
                'roleplay_latency_min_ms', 'roleplay_latency_max_ms',
                'roleplay_packet_loss_percent',
                'roleplay_disconnect_enabled', 'roleplay_disconnect_min_sec',
                'roleplay_disconnect_max_sec', 'roleplay_disconnect_interval_sec',
                'roleplay_sensor_enabled', 'roleplay_sensor_failure_percent',
                'roleplay_sensor_error_percent', 'roleplay_sensor_missing_percent',
                'roleplay_zones_enabled', 'roleplay_zones_config',
            ];
            $vals = [
                $tenantId,
                $fields['roleplay_network_enabled'],
                $fields['roleplay_network_mode'],
                $fields['roleplay_latency_min_ms'],
                $fields['roleplay_latency_max_ms'],
                $fields['roleplay_packet_loss_percent'],
                $fields['roleplay_disconnect_enabled'],
                $fields['roleplay_disconnect_min_sec'],
                $fields['roleplay_disconnect_max_sec'],
                $fields['roleplay_disconnect_interval_sec'],
                $fields['roleplay_sensor_enabled'],
                $fields['roleplay_sensor_failure_percent'],
                $fields['roleplay_sensor_error_percent'],
                $fields['roleplay_sensor_missing_percent'],
                $fields['roleplay_zones_enabled'],
                $fields['roleplay_zones_config'],
            ];
            if ($scrambleCol) {
                $cols[] = 'roleplay_intel_scramble_enabled';
                $vals[] = $fields['roleplay_intel_scramble_enabled'];
            }
            if ($reviewedCol) {
                $cols[] = 'roleplay_intel_scramble_reviewed';
                $vals[] = $fields['roleplay_intel_scramble_reviewed'];
            }
            $cols[] = 'default_map_slug';
            $cols[] = 'created_at';
            $cols[] = 'updated_at';
            $placeholders = implode(', ', array_fill(0, count($vals), '?')) . ', \'altis\', NOW(), NOW()';
            $sql = 'INSERT INTO tenant_atak_config (' . implode(', ', $cols) . ') VALUES (' . $placeholders . ')';
            $this->pdo()->prepare($sql)->execute($vals);
        }
    }

    /**
     * Détecte la présence d’une colonne sur tenant_atak_config (cache par requête PHP).
     * Évite les méthodes dédiées manquantes lors d’un déploiement partiel.
     */
    private function hasTenantAtakConfigColumn(string $columnName): bool
    {
        static $cache = [];
        if (array_key_exists($columnName, $cache)) {
            return $cache[$columnName];
        }
        try {
            $st = $this->pdo()->prepare(
                'SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
                   AND COLUMN_NAME = ? LIMIT 1'
            );
            $st->execute(['tenant_atak_config', $columnName]);
            $cache[$columnName] = (bool) $st->fetchColumn();
        } catch (\Throwable) {
            $cache[$columnName] = false;
        }

        return $cache[$columnName];
    }

    private function hasRoleplayColumns(): bool
    {
        return $this->hasTenantAtakConfigColumn('roleplay_network_enabled');
    }

    public function createOrUpdate(int $tenantId, array $data): void
    {
        $stmt = $this->pdo()->prepare('SELECT 1 FROM tenant_atak_config WHERE tenant_id = ? LIMIT 1');
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
            $stmt = $this->pdo()->prepare(
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
            $stmt = $this->pdo()->prepare(
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
        $stmt = $this->pdo()->prepare(
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
        $stmt = $this->pdo()->prepare(
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
            $stmt = $this->pdo()->prepare('SELECT 1 FROM tenant_atak_config WHERE tenant_id = ? LIMIT 1');
            $stmt->execute([$tenantId]);
            $exists = (bool) $stmt->fetchColumn();

            if ($exists) {
                $upd = $this->pdo()->prepare(
                    'UPDATE tenant_atak_config
                     SET maintenance_enabled = ?, maintenance_message = ?, updated_at = NOW()
                     WHERE tenant_id = ?'
                );
                $upd->execute([$enabled ? 1 : 0, $msg, $tenantId]);
            } else {
                $ins = $this->pdo()->prepare(
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
     * Une colonne à la fois pour éviter qu’une demi-migration laisse le mode maintenance inutilisable.
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

        foreach ([
            'maintenance_enabled' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'maintenance_message' => 'TEXT DEFAULT NULL',
        ] as $column => $ddl) {
            if ($this->columnExistsOnTenantAtakConfig($column)) {
                continue;
            }
            try {
                $this->pdo()->exec(
                    'ALTER TABLE tenant_atak_config ADD COLUMN ' . $column . ' ' . $ddl
                );
            } catch (\Throwable) {
                // Colonne déjà présente (course) ou droits insuffisants — hasMaintenanceColumns tranchera
            }
        }

        $this->resetMaintenanceColumnsCache();
    }

    private function columnExistsOnTenantAtakConfig(string $column): bool
    {
        try {
            $st = $this->pdo()->prepare(
                "SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenant_atak_config' AND COLUMN_NAME = ? LIMIT 1"
            );
            $st->execute([$column]);
            if ($st->fetchColumn()) {
                return true;
            }
        } catch (\Throwable) {
            // Fallback ci-dessous
        }

        try {
            $st = $this->pdo()->query('SHOW COLUMNS FROM tenant_atak_config LIKE ' . $this->pdo()->quote($column));
            if ($st && $st->fetch(\PDO::FETCH_ASSOC)) {
                return true;
            }
        } catch (\Throwable) {
            // ignore
        }

        return false;
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
            $st = $this->pdo()->query(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenant_atak_config'
                   AND COLUMN_NAME IN ('maintenance_enabled', 'maintenance_message')"
            );
            if ($st && (int) $st->fetchColumn() >= 2) {
                $cached = true;

                return true;
            }
        } catch (\Throwable) {
            // Fallback ci-dessous (hébergeurs qui restreignent information_schema)
        }

        try {
            $st = $this->pdo()->query("SHOW COLUMNS FROM tenant_atak_config LIKE 'maintenance_enabled'");
            $hasEnabled = $st && $st->fetch(PDO::FETCH_ASSOC);
            $st2 = $this->pdo()->query("SHOW COLUMNS FROM tenant_atak_config LIKE 'maintenance_message'");
            $hasMessage = $st2 && $st2->fetch(PDO::FETCH_ASSOC);
            if ($hasEnabled && $hasMessage) {
                $cached = true;

                return true;
            }
        } catch (\Throwable) {
            // Dernier recours : SELECT direct
        }

        try {
            $this->pdo()->query('SELECT maintenance_enabled, maintenance_message FROM tenant_atak_config LIMIT 0');
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

        $stmt = $this->pdo()->prepare('SELECT 1 FROM tenant_atak_config WHERE tenant_id = ? LIMIT 1');
        $stmt->execute([$tenantId]);
        $exists = (bool) $stmt->fetchColumn();

        if ($exists) {
            $upd = $this->pdo()->prepare(
                'UPDATE tenant_atak_config
                 SET access_key = ?, access_key_prefix = ?, access_key_generated_at = NOW(), updated_at = NOW()
                 WHERE tenant_id = ?'
            );
            $upd->execute([$plain, $prefix, $tenantId]);
        } else {
            $ins = $this->pdo()->prepare(
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
        $stmt = $this->pdo()->prepare('SELECT access_key FROM tenant_atak_config WHERE tenant_id = ? LIMIT 1');
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
            $stmt = $this->pdo()->prepare(
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
            $st = $this->pdo()->query(
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
        $stmt = $this->pdo()->prepare(
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
            $stmt = $this->pdo()->prepare('SELECT 1 FROM tenant_atak_config WHERE tenant_id = ? LIMIT 1');
            $stmt->execute([$tenantId]);
            $exists = (bool) $stmt->fetchColumn();
            if ($exists) {
                $upd = $this->pdo()->prepare(
                    'UPDATE tenant_atak_config SET experience_config = ?, updated_at = NOW() WHERE tenant_id = ?'
                );
                $upd->execute([$json, $tenantId]);
            } else {
                $ins = $this->pdo()->prepare(
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
            $this->pdo()->exec(
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
            $st = $this->pdo()->query(
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
            $st = $this->pdo()->query("SHOW COLUMNS FROM tenant_atak_config LIKE 'experience_config'");
            if ($st && $st->fetch(PDO::FETCH_ASSOC)) {
                $cached = true;

                return true;
            }
        } catch (\Throwable) {
        }
        try {
            $this->pdo()->query('SELECT experience_config FROM tenant_atak_config LIMIT 0');
            $cached = true;
        } catch (\Throwable) {
            $cached = false;
        }

        return $cached;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getOverwatchGameExperienceRaw(int $tenantId): ?array
    {
        if ($tenantId < 1) {
            return null;
        }
        $this->ensureOverwatchGameExperienceSchema();
        if (!$this->hasOverwatchGameExperienceColumn()) {
            return null;
        }
        $stmt = $this->pdo()->prepare(
            'SELECT overwatch_game_experience FROM tenant_atak_config WHERE tenant_id = ? LIMIT 1'
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
    public function saveOverwatchGameExperience(int $tenantId, array $config): bool
    {
        if ($tenantId < 1) {
            return false;
        }
        $this->ensureOverwatchGameExperienceSchema();
        if (!$this->hasOverwatchGameExperienceColumn()) {
            return false;
        }
        $json = json_encode($config, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return false;
        }
        try {
            $stmt = $this->pdo()->prepare('SELECT 1 FROM tenant_atak_config WHERE tenant_id = ? LIMIT 1');
            $stmt->execute([$tenantId]);
            if ((bool) $stmt->fetchColumn()) {
                $upd = $this->pdo()->prepare(
                    'UPDATE tenant_atak_config SET overwatch_game_experience = ?, updated_at = NOW() WHERE tenant_id = ?'
                );
                $upd->execute([$json, $tenantId]);
            } else {
                $ins = $this->pdo()->prepare(
                    'INSERT INTO tenant_atak_config (tenant_id, overwatch_game_experience, default_map_slug, created_at, updated_at)
                     VALUES (?, ?, \'altis\', NOW(), NOW())'
                );
                $ins->execute([$tenantId, $json]);
            }
        } catch (\Throwable) {
            return false;
        }

        return true;
    }

    private function ensureOverwatchGameExperienceSchema(): void
    {
        static $attempted = false;
        if ($attempted) {
            return;
        }
        $attempted = true;
        if ($this->hasOverwatchGameExperienceColumn()) {
            return;
        }
        try {
            $this->pdo()->exec(
                'ALTER TABLE tenant_atak_config ADD COLUMN overwatch_game_experience JSON DEFAULT NULL'
            );
            $this->hasOverwatchGameExperienceColumn(true);
        } catch (\Throwable) {
            $this->hasOverwatchGameExperienceColumn(true);
        }
    }

    private function hasOverwatchGameExperienceColumn(bool $resetCache = false): bool
    {
        static $cached = null;
        if ($resetCache) {
            $cached = null;
        }
        if ($cached !== null) {
            return $cached;
        }
        try {
            $st = $this->pdo()->query(
                "SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenant_atak_config'
                   AND COLUMN_NAME = 'overwatch_game_experience' LIMIT 1"
            );
            $cached = $st !== false && (bool) $st->fetchColumn();
        } catch (\Throwable) {
            $cached = false;
        }

        return $cached;
    }
}
