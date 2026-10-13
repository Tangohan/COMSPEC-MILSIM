<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Services\Atak\GameServerAddressLookup;
use PDO;

/**
 * Serveurs de jeu déclarés par une communauté, et dernier serveur vu pour chaque joueur relié à Athena.
 * Tables : migrations/tenant_game_servers.sql (créées ici au besoin si run-migrations n'est pas encore passé).
 */
final class TenantGameServerRepository
{
    private static bool $schemaChecked = false;

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
        $this->ensureSchema();
    }

    private function ensureSchema(): void
    {
        if (self::$schemaChecked) {
            return;
        }
        self::$schemaChecked = true;
        try {
            $sql = (string) @file_get_contents(dirname(__DIR__, 2) . '/migrations/tenant_game_servers.sql');
            foreach (preg_split('/;\s*\n/', $sql) ?: [] as $stmt) {
                $lines = array_filter(
                    explode("\n", $stmt),
                    static fn (string $l): bool => !str_starts_with(ltrim($l), '--')
                );
                $stmt = trim(implode("\n", $lines));
                if ($stmt !== '') {
                    $this->pdo->exec($stmt);
                }
            }
            // Table de présence créée avant l'ajout du port.
            $col = $this->pdo->query("SHOW COLUMNS FROM tenant_game_server_presence LIKE 'server_port'");
            if ($col !== false && $col->fetch() === false) {
                $this->pdo->exec('ALTER TABLE tenant_game_server_presence ADD COLUMN server_port INT UNSIGNED NULL AFTER server_name');
            }
        } catch (\Throwable) {
            // Droits DDL absents : run-migrations.php s'en charge.
        }
    }

    /** Recherche d'adresse par nom de serveur, avec le cache en base. */
    public function addressLookup(): GameServerAddressLookup
    {
        return new GameServerAddressLookup($this->pdo);
    }

    /** Complète l'adresse d'un serveur déclaré sans IP (retrouvée par son nom ou vue par les joueurs). */
    public function fillAddress(int $tenantId, int $id, string $ip, ?int $port): void
    {
        try {
            $st = $this->pdo->prepare(
                "UPDATE tenant_game_servers SET ip_address = ?, port = COALESCE(port, ?)
                 WHERE tenant_id = ? AND id = ? AND (ip_address IS NULL OR ip_address = '')"
            );
            $st->execute([$ip, $port, $tenantId, $id]);
        } catch (\Throwable) {
        }
    }

    /** Complète l'adresse d'une ligne de présence et le serveur reconnu. */
    public function updatePresenceServer(int $presenceId, ?string $ip, ?int $port, ?int $matchedServerId): void
    {
        try {
            $st = $this->pdo->prepare(
                'UPDATE tenant_game_server_presence SET server_ip = COALESCE(?, server_ip), server_port = COALESCE(?, server_port), matched_server_id = ? WHERE id = ?'
            );
            $st->execute([$ip, $port, $matchedServerId, $presenceId]);
        } catch (\Throwable) {
        }
    }

    /** @return list<array<string, mixed>> */
    public function listForTenant(int $tenantId, bool $activeOnly = false): array
    {
        try {
            $st = $this->pdo->prepare(
                'SELECT * FROM tenant_game_servers WHERE tenant_id = ?' . ($activeOnly ? ' AND is_active = 1' : '') . ' ORDER BY label ASC, id ASC'
            );
            $st->execute([$tenantId]);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<string, mixed>|null */
    public function find(int $tenantId, int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM tenant_game_servers WHERE tenant_id = ? AND id = ?');
        $st->execute([$tenantId, $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function create(int $tenantId, string $label, ?string $ip, ?string $name, ?int $port, ?int $userId): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO tenant_game_servers (tenant_id, label, ip_address, server_name, port, is_active, created_by) VALUES (?, ?, ?, ?, ?, 1, ?)'
        );
        $st->execute([$tenantId, $label, $ip, $name, $port, $userId]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $tenantId, int $id, string $label, ?string $ip, ?string $name, ?int $port): void
    {
        $st = $this->pdo->prepare(
            'UPDATE tenant_game_servers SET label = ?, ip_address = ?, server_name = ?, port = ? WHERE tenant_id = ? AND id = ?'
        );
        $st->execute([$label, $ip, $name, $port, $tenantId, $id]);
    }

    public function setActive(int $tenantId, int $id, bool $active): void
    {
        $st = $this->pdo->prepare('UPDATE tenant_game_servers SET is_active = ? WHERE tenant_id = ? AND id = ?');
        $st->execute([$active ? 1 : 0, $tenantId, $id]);
    }

    public function delete(int $tenantId, int $id): void
    {
        $st = $this->pdo->prepare('DELETE FROM tenant_game_servers WHERE tenant_id = ? AND id = ?');
        $st->execute([$tenantId, $id]);
    }

    public function recordPresence(
        int $tenantId,
        string $steamUid,
        ?string $playerName,
        ?string $serverIp,
        ?string $serverName,
        ?string $mission,
        ?string $world,
        ?int $matchedServerId,
        ?int $serverPort = null
    ): void {
        try {
            $st = $this->pdo->prepare(
                'INSERT INTO tenant_game_server_presence (tenant_id, steam_uid, player_name, server_ip, server_port, server_name, mission, world, matched_server_id, seen_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE player_name = VALUES(player_name), server_ip = VALUES(server_ip), server_port = VALUES(server_port),
                   server_name = VALUES(server_name), mission = VALUES(mission), world = VALUES(world),
                   matched_server_id = VALUES(matched_server_id), seen_at = NOW()'
            );
            $st->execute([$tenantId, $steamUid, $playerName, $serverIp, $serverPort, $serverName, $mission, $world, $matchedServerId]);
        } catch (\Throwable) {
            // Colonne server_port pas encore ajoutée (droits DDL) : sans le port.
            try {
                $st = $this->pdo->prepare(
                    'INSERT INTO tenant_game_server_presence (tenant_id, steam_uid, player_name, server_ip, server_name, mission, world, matched_server_id, seen_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                     ON DUPLICATE KEY UPDATE player_name = VALUES(player_name), server_ip = VALUES(server_ip), server_name = VALUES(server_name),
                       mission = VALUES(mission), world = VALUES(world), matched_server_id = VALUES(matched_server_id), seen_at = NOW()'
                );
                $st->execute([$tenantId, $steamUid, $playerName, $serverIp, $serverName, $mission, $world, $matchedServerId]);
            } catch (\Throwable) {
            }
        }
    }

    /**
     * Joueurs vus depuis $hours heures, les plus récents d'abord.
     *
     * @return list<array<string, mixed>>
     */
    public function recentPresence(int $tenantId, int $hours = 72, int $limit = 100): array
    {
        try {
            $st = $this->pdo->prepare(
                'SELECT p.*, s.label AS matched_label FROM tenant_game_server_presence p
                 LEFT JOIN tenant_game_servers s ON s.id = p.matched_server_id
                 WHERE p.tenant_id = ? AND p.seen_at >= (NOW() - INTERVAL ' . max(1, $hours) . ' HOUR)
                 ORDER BY p.seen_at DESC LIMIT ' . max(1, min(500, $limit))
            );
            $st->execute([$tenantId]);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }
}
