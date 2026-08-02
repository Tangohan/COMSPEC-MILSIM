<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Réglages du portail SSE, par communauté.
 *
 * Table clé/valeur volontairement minimale. Le portail n'avait aucun stockage
 * serveur pour un réglage : le thème passe par un cookie, ce qui convient à une
 * préférence d'affichage mais pas à un verrou que l'utilisateur ne doit pas
 * pouvoir desserrer lui-même.
 *
 * Toute lecture retombe sur la valeur par défaut fournie en cas d'erreur : un
 * portail qui refuse de servir parce qu'une table de réglages manque est pire
 * que le réglage manquant.
 */
final class SsePortalSettingsRepository
{
    /**
     * Verrou d'ouverture des dossiers par classification.
     *
     * Désarmé par défaut, et ce défaut est délibéré. La classification existe
     * depuis l'origine du portail sans jamais avoir filtré quoi que ce soit :
     * les valeurs déjà posées sur les dossiers ont été choisies sans conséquence.
     * Les transformer d'office en décisions d'exclusion fermerait des dossiers
     * que personne n'a voulu fermer.
     */
    public const CASE_LOCK = 'case_classification_lock';

    /**
     * Caviardage des écrans de travail (registre des personnes, fiche dossier,
     * corrélations).
     *
     * Désarmé par défaut, pour une raison différente du verrou de dossier : les
     * documents de diffusion sont toujours rabattus, parce que c'est leur objet.
     * Les écrans de travail, eux, sont ce que la cellule renseignement regarde
     * toute la séance. Les caviarder change le quotidien de tout le monde d'un
     * coup, et selon la doctrine retenue pour les catégories, peut retirer les
     * noms à ceux qui en ont besoin pour travailler.
     *
     * À armer une fois que les habilitations sont réellement réparties.
     */
    public const WORKING_REDACTION = 'redact_working_screens';

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->ensureSchema();
    }

    private function ensureSchema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $path = base_path('bootstrap/atak_sse_portal_migration.php');
        if (is_file($path)) {
            $migrate = require $path;
            if (is_callable($migrate)) {
                try {
                    $migrate(Database::getPdo());
                } catch (\Throwable) {
                }
            }
        }
        $done = true;
    }

    public function get(int $tenantId, string $key, string $default = ''): string
    {
        try {
            $row = $this->db->fetchOne(
                'SELECT setting_value FROM sse_portal_settings WHERE tenant_id = :t AND setting_key = :k',
                ['t' => $tenantId, 'k' => $key]
            );
        } catch (\Throwable) {
            return $default;
        }

        return $row ? (string) $row['setting_value'] : $default;
    }

    public function getBool(int $tenantId, string $key, bool $default = false): bool
    {
        $raw = $this->get($tenantId, $key, $default ? '1' : '0');

        return $raw === '1';
    }

    public function setBool(int $tenantId, string $key, bool $value, ?int $userId = null): bool
    {
        try {
            $this->db->execute(
                'INSERT INTO sse_portal_settings (tenant_id, setting_key, setting_value, updated_by)
                 VALUES (:t, :k, :v, :u)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)',
                ['t' => $tenantId, 'k' => $key, 'v' => $value ? '1' : '0', 'u' => $userId]
            );

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
