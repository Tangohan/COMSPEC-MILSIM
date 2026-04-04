<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class SiteSettingsRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /**
     * Retourne les paramètres forum_* pour le tenant sous forme de tableau associatif [key => value].
     * Retourne [] si la table site_settings n'existe pas encore.
     */
    public function getForumSettings(int $tenantId): array
    {
        try {
            $stmt = $this->pdo->prepare('SELECT `key`, `value` FROM site_settings WHERE tenant_id = ? AND `key` LIKE ?');
            $stmt->execute([$tenantId, 'forum_%']);
            $out = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $out[$row['key']] = $row['value'];
            }
            return $out;
        } catch (\PDOException $e) {
            if ($e->getCode() === '42S02' || strpos($e->getMessage(), "doesn't exist") !== false) {
                return [];
            }
            throw $e;
        }
    }

    /**
     * Enregistre les paramètres forum_* pour le tenant. Seules les clés commençant par forum_ sont prises en compte.
     * Ne fait rien si la table site_settings n'existe pas encore.
     */
    public function setForumSettings(int $tenantId, array $settings): void
    {
        foreach ($settings as $key => $value) {
            if (strpos($key, 'forum_') !== 0) {
                continue;
            }
            $key = substr($key, 0, 100);
            try {
                $stmt = $this->pdo->prepare('INSERT INTO site_settings (tenant_id, `key`, `value`, updated_at) VALUES (?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = NOW()');
                $stmt->execute([$tenantId, $key, is_scalar($value) ? (string) $value : json_encode($value)]);
            } catch (\PDOException $e) {
                if ($e->getCode() === '42S02' || strpos($e->getMessage(), "doesn't exist") !== false) {
                    return;
                }
                throw $e;
            }
        }
    }
}
