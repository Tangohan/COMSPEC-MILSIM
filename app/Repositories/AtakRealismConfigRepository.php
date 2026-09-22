<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\LazyDatabaseConnection;
use PDO;

/**
 * Repository pour la configuration centralisée réalisme ATAK.
 * Gère la lecture, validation et historisation des paramètres de réalisme
 * (relais, certificats, dommages terminal, simulation réseau, zones, etc.).
 */
final class AtakRealismConfigRepository
{
    use LazyDatabaseConnection;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    /**
     * Récupère la configuration active pour un tenant.
     *
     * @return array|null ['id' => int, 'config_json' => string (JSON), 'config_version' => string, 'updated_at' => string, ...]
     */
    public function getActiveConfig(int $tenantId): ?array
    {
        if ($tenantId < 1) {
            return null;
        }

        $st = $this->pdo()->prepare(
            'SELECT id, tenant_id, config_version, config_name, is_active, config_json, 
                    created_at, updated_at, created_by, updated_by
             FROM atak_realism_config
             WHERE tenant_id = ? AND is_active = 1
             LIMIT 1'
        );
        $st->execute([$tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Crée ou met à jour la configuration active pour un tenant.
     * Désactive l'ancienne config si elle existe et en crée une nouvelle.
     *
     * @param int $tenantId ID du tenant
     * @param array $configJson Configuration complète (sera encodée en JSON)
     * @param int|null $updatedBy ID de l'utilisateur qui fait la modification
     * @param string $configName Nom descriptif de la configuration
     * @param string $configVersion Version du schéma (défaut '1.0.0')
     * @return array La nouvelle configuration créée
     */
    public function upsertConfig(
        int $tenantId,
        array $configJson,
        ?int $updatedBy = null,
        string $configName = 'Configuration par défaut',
        string $configVersion = '1.0.0'
    ): array {
        if ($tenantId < 1) {
            throw new \InvalidArgumentException('Invalid tenant_id');
        }

        // Valider la config avant de l'insérer
        $validation = $this->validateConfigJson($configJson);
        if (!$validation['valid']) {
            throw new \InvalidArgumentException('Invalid config: ' . implode(', ', $validation['errors']));
        }

        $pdo = $this->pdo();
        $pdo->beginTransaction();

        try {
            // Désactiver l'ancienne config active si elle existe
            $st = $pdo->prepare(
                'UPDATE atak_realism_config 
                 SET is_active = 0 
                 WHERE tenant_id = ? AND is_active = 1'
            );
            $st->execute([$tenantId]);

            // Insérer la nouvelle config
            $st = $pdo->prepare(
                'INSERT INTO atak_realism_config 
                    (tenant_id, config_version, config_name, is_active, config_json, created_by, updated_by)
                 VALUES (?, ?, ?, 1, ?, ?, ?)'
            );
            $st->execute([
                $tenantId,
                $configVersion,
                $configName,
                json_encode($configJson, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                $updatedBy,
                $updatedBy,
            ]);

            $newId = (int) $pdo->lastInsertId();
            $pdo->commit();

            return $this->getConfigById($tenantId, $newId) ?? [];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Valide le JSON de configuration contre le schéma attendu et les bornes de valeurs.
     *
     * @return array{valid: bool, errors: list<string>}
     */
    public function validateConfigJson(array $configJson): array
    {
        $errors = [];

        // Vérifier la présence de tous les domaines obligatoires
        $requiredDomains = [
            'radio_relays',
            'zones_roleplay',
            'network_simulation',
            'certificates',
            'terminal_damage',
            'waypoints_routes',
            'symbology_map',
            'control_measures',
            'coverage_viewshed',
            'experience_ambiance',
            'other_settings',
        ];

        foreach ($requiredDomains as $domain) {
            if (!isset($configJson[$domain]) || !is_array($configJson[$domain])) {
                $errors[] = "Missing or invalid domain: {$domain}";
            }
        }

        if ($errors !== []) {
            return ['valid' => false, 'errors' => $errors];
        }

        // Validation des bornes critiques
        $relays = $configJson['radio_relays'];
        if (isset($relays['relay_range_m'])) {
            $range = (int) $relays['relay_range_m'];
            if ($range < 50 || $range > 8000) {
                $errors[] = 'relay_range_m must be between 50 and 8000';
            }
        }

        $certs = $configJson['certificates'];
        if (isset($certs['certificate_duration_days'])) {
            $days = (int) $certs['certificate_duration_days'];
            if ($days < 1 || $days > 1825) {
                $errors[] = 'certificate_duration_days must be between 1 and 1825';
            }
        }

        $viewshed = $configJson['coverage_viewshed'];
        if (isset($viewshed['viewshed_radius_default_m'])) {
            $radius = (int) $viewshed['viewshed_radius_default_m'];
            if ($radius < 25 || $radius > 2000) {
                $errors[] = 'viewshed_radius_default_m must be between 25 and 2000';
            }
        }

        return ['valid' => $errors === [], 'errors' => $errors];
    }

    /**
     * Liste l'historique des configurations pour un tenant (actives + anciennes).
     *
     * @param int $tenantId
     * @param int $limit Nombre max de résultats
     * @return list<array> Configurations triées par updated_at DESC
     */
    public function listConfigHistory(int $tenantId, int $limit = 20): array
    {
        if ($tenantId < 1) {
            return [];
        }

        $st = $this->pdo()->prepare(
            'SELECT id, tenant_id, config_version, config_name, is_active, 
                    created_at, updated_at, created_by, updated_by
             FROM atak_realism_config
             WHERE tenant_id = ?
             ORDER BY updated_at DESC
             LIMIT ?'
        );
        $st->execute([$tenantId, $limit]);

        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère une version spécifique de la configuration par son ID.
     *
     * @return array|null
     */
    public function getConfigById(int $tenantId, int $configId): ?array
    {
        if ($tenantId < 1 || $configId < 1) {
            return null;
        }

        $st = $this->pdo()->prepare(
            'SELECT id, tenant_id, config_version, config_name, is_active, config_json, 
                    created_at, updated_at, created_by, updated_by
             FROM atak_realism_config
             WHERE tenant_id = ? AND id = ?
             LIMIT 1'
        );
        $st->execute([$tenantId, $configId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
