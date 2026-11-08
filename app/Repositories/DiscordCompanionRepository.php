<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SilentSchemaMigration;
use PDO;

final class DiscordCompanionRepository
{
    private PDO $pdo;
    public function __construct()
    {
        $this->pdo = Database::getPdo();
        SilentSchemaMigration::run(base_path('bootstrap/discord_companion_migration.php'), $this->pdo);
    }

    /** @return array<string, mixed>|null */
    public function forTenant(int $tenant): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM discord_companion_guilds WHERE tenant_id = ?');
        $st->execute([$tenant]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function pairingCode(int $tenant): string
    {
        $code = 'ATHENA-' . strtoupper(bin2hex(random_bytes(12)));
        $this->pdo->prepare('DELETE FROM discord_companion_pairings WHERE tenant_id = ? OR expires_at < NOW()')->execute([$tenant]);
        $this->pdo->prepare('INSERT INTO discord_companion_pairings (token_hash, tenant_id, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))')->execute([hash('sha256', $code), $tenant]);
        return $code;
    }

    /** @param array<string, string> $channels */
    public function pair(string $code, string $guild, string $name, string $channel, array $channels): void
    {
        $this->pdo->beginTransaction();
        try {
            $st = $this->pdo->prepare('SELECT tenant_id FROM discord_companion_pairings WHERE token_hash = ? AND expires_at >= NOW() FOR UPDATE');
            $st->execute([hash('sha256', strtoupper(trim($code)))]);
            $tenant = (int) $st->fetchColumn();
            if ($tenant < 2) throw new \RuntimeException('companion.invalid_code');
            $st = $this->pdo->prepare('SELECT guild_id, tenant_id FROM discord_companion_guilds WHERE tenant_id = ? OR guild_id = ? FOR UPDATE');
            $st->execute([$tenant, $guild]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $existing) {
                if ((int) $existing['tenant_id'] !== $tenant || $existing['guild_id'] !== $guild) throw new \RuntimeException('companion.already_bound');
            }
            $this->pdo->prepare('INSERT INTO discord_companion_guilds (guild_id, tenant_id, guild_name, channel_id, channels_json) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE guild_name = VALUES(guild_name), channel_id = VALUES(channel_id), channels_json = VALUES(channels_json), synced_at = NOW()')
                ->execute([$guild, $tenant, mb_substr($name, 0, 100), $channel, json_encode($channels, JSON_THROW_ON_ERROR)]);
            $this->pdo->prepare('DELETE FROM discord_companion_pairings WHERE tenant_id = ?')->execute([$tenant]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /** @param array<string, string> $channels */
    public function updateChannels(string $guild, string $name, array $channels): void
    {
        $this->pdo->prepare('UPDATE discord_companion_guilds SET guild_name = ?, channels_json = ?, synced_at = NOW() WHERE guild_id = ?')->execute([mb_substr($name, 0, 100), json_encode($channels, JSON_THROW_ON_ERROR), $guild]);
    }

    public function knownChannel(int $tenant, string $id): ?string
    {
        $row = $this->forTenant($tenant);
        $channels = $row ? json_decode((string) $row['channels_json'], true) : [];
        return isset($channels[$id]) ? (string) $channels[$id] : null;
    }

    public function unlink(int $tenant): void
    {
        $this->pdo->prepare("UPDATE community_event_discord SET channel_id = NULL, message_id = NULL, reactions_seeded = 0 WHERE tenant_id = ? AND destination = 'companion'")->execute([$tenant]);
        $this->pdo->prepare('DELETE FROM discord_companion_guilds WHERE tenant_id = ?')->execute([$tenant]);
        $this->pdo->prepare('DELETE FROM discord_companion_pairings WHERE tenant_id = ?')->execute([$tenant]);
    }

    /** @return list<array<string, mixed>> */
    public function jobs(): array
    {
        return $this->pdo->query("SELECT d.*, e.title, e.description, e.location, e.starts_at, e.ends_at, e.cancelled_at, g.guild_id, g.channel_id AS default_channel_id
            FROM community_event_discord d JOIN community_events e ON e.id = d.event_id AND e.tenant_id = d.tenant_id
            JOIN discord_companion_guilds g ON g.tenant_id = d.tenant_id
            WHERE d.destination = 'companion' AND e.starts_at < DATE_ADD(NOW(), INTERVAL 30 DAY)
              AND COALESCE(e.ends_at, e.starts_at) >= DATE_SUB(NOW(), INTERVAL 1 DAY)
            ORDER BY e.starts_at ASC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, mixed>|null */
    public function eventForGuild(int $event, string $guild): ?array
    {
        $st = $this->pdo->prepare("SELECT d.*, e.cancelled_at, e.ends_at, e.starts_at, g.channel_id AS default_channel_id FROM community_event_discord d
            JOIN community_events e ON e.id = d.event_id AND e.tenant_id = d.tenant_id
            JOIN discord_companion_guilds g ON g.tenant_id = d.tenant_id
            WHERE d.event_id = ? AND g.guild_id = ? AND d.destination = 'companion'");
        $st->execute([$event, $guild]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
