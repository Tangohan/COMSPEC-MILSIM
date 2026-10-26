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
        // Le schéma (config/realism-schema.json) est la seule source des domaines et des bornes.
        // L'ancienne liste en dur exigeait « waypoints_routes » (le schéma dit « waypoints ») et des bornes
        // plus étroites que le schéma : aucun enregistrement depuis l'écran ne pouvait aboutir.
        return \App\Services\ConfigSchemaService::validateConfig($configJson);
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
