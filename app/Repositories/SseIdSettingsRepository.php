<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * COMSPEC IDENTITY — réglages par communauté, envoyés au serveur de jeu dans le paquet (settings).
 * Sans ligne : valeurs par défaut (envoi automatique des contrôles activé).
 */
final class SseIdSettingsRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
        SseIdentityRepository::ensureSchema();
    }

    public function autoTransmit(int $tenantId): bool
    {
        $row = $this->db->fetchOne('SELECT auto_transmit FROM sse_id_settings WHERE tenant_id = :t', ['t' => $tenantId]);

        return $row === null || (int) $row['auto_transmit'] === 1;
    }

    public function setAutoTransmit(int $tenantId, bool $enabled): void
    {
        $params = ['t' => $tenantId, 'v' => $enabled ? 1 : 0, 'now' => gmdate('Y-m-d H:i:s')];
        $exists = $this->db->fetchOne('SELECT tenant_id FROM sse_id_settings WHERE tenant_id = :t', ['t' => $tenantId]);
        $this->db->execute(
            $exists !== null
                ? 'UPDATE sse_id_settings SET auto_transmit = :v, updated_at = :now WHERE tenant_id = :t'
                : 'INSERT INTO sse_id_settings (tenant_id, auto_transmit, updated_at) VALUES (:t, :v, :now)',
            $params
        );
    }
}
