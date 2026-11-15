<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Bot Discord Athena : réglages par communauté, rattachement du serveur, rappels envoyés, audit des commandes.
 * Le serveur lié vit dans discord_companion_guilds (un serveur pour une communauté) ; le reste dans discord_bot_*
 * (migrations/2026_11_17_100_discord_bot.sql). Toutes les dates sont passées par PHP (compatible sqlite des tests).
 */
final class DiscordBotRepository
{
    public const CHANNEL_KEYS = ['announce_channel_id', 'applications_channel_id', 'events_channel_id', 'log_channel_id'];
    public const ROLE_KEYS = ['member_role_id', 'manager_role_id'];
    /** Le bot signale sa présence toutes les dix minutes : au-delà de deux heures sans signe, il est considéré absent. */
    public const SEEN_TTL = 7200;

    public function __construct(private ?PDO $pdo = null)
    {
    }

    private function db(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    public function schemaReady(): bool
    {
        try {
            $this->db()->query('SELECT 1 FROM discord_bot_settings LIMIT 1');
            $this->db()->query('SELECT 1 FROM discord_companion_guilds LIMIT 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array<string, mixed>|null */
    public function guildForTenant(int $tenant): ?array
    {
        $st = $this->db()->prepare('SELECT * FROM discord_companion_guilds WHERE tenant_id = ?');
        $st->execute([$tenant]);

        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** La communauté d'un serveur : toujours déduite du serveur lié, jamais d'une saisie. */
    public function tenantForGuild(string $guild): ?int
    {
        $st = $this->db()->prepare('SELECT tenant_id FROM discord_companion_guilds WHERE guild_id = ?');
        $st->execute([$guild]);
        $id = $st->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /** @return array<string, mixed> */
    public function settings(int $tenant): array
    {
        $row = [];
        try {
            $st = $this->db()->prepare('SELECT * FROM discord_bot_settings WHERE tenant_id = ?');
            $st->execute([$tenant]);
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
        }
        $row += ['announce_channel_id' => null, 'applications_channel_id' => null, 'events_channel_id' => null, 'log_channel_id' => null,
            'member_role_id' => null, 'manager_role_id' => null, 'roles_json' => null, 'sync_roles' => 0, 'reminders_enabled' => 1,
            'welcome_enabled' => 0, 'bot_seen_at' => null, 'test_requested_at' => null, 'linked_by_user_id' => null, 'linked_at' => null];

        return $row;
    }

    /** @return array<string, string> identifiant => nom */
    public function channels(int $tenant): array
    {
        $g = $this->guildForTenant($tenant);
        $c = $g ? json_decode((string) $g['channels_json'], true) : [];

        return is_array($c) ? array_map('strval', $c) : [];
    }

    /** @return array<string, string> identifiant => nom */
    public function roles(int $tenant): array
    {
        $r = json_decode((string) ($this->settings($tenant)['roles_json'] ?? ''), true);

        return is_array($r) ? array_map('strval', $r) : [];
    }

    /** none : aucun serveur · pending : serveur choisi, bot pas encore vu · connected · absent : bot silencieux ou retiré. */
    public function status(int $tenant, ?int $now = null): string
    {
        if ($this->guildForTenant($tenant) === null) {
            return 'none';
        }
        $seen = $this->settings($tenant)['bot_seen_at'];
        if (!is_string($seen) || $seen === '') {
            return 'pending';
        }

        return (($now ?? time()) - (int) strtotime($seen)) <= self::SEEN_TTL ? 'connected' : 'absent';
    }

    /**
     * Rattache un serveur à la communauté (retour OAuth). Un serveur pour une communauté, dans les deux sens.
     *
     * @throws \RuntimeException companion.already_bound
     */
    public function linkGuild(int $tenant, string $guild, string $name, int $userId, ?string $now = null): void
    {
        $now ??= date('Y-m-d H:i:s');
        $db = $this->db();
        $db->beginTransaction();
        try {
            $st = $db->prepare('SELECT guild_id, tenant_id FROM discord_companion_guilds WHERE tenant_id = ? OR guild_id = ?');
            $st->execute([$tenant, $guild]);
            $exists = false;
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if ((int) $row['tenant_id'] !== $tenant || (string) $row['guild_id'] !== $guild) {
                    throw new \RuntimeException('companion.already_bound');
                }
                $exists = true;
            }
            $name = mb_substr($name, 0, 100);
            if ($exists) {
                $db->prepare('UPDATE discord_companion_guilds SET guild_name = ? WHERE guild_id = ?')->execute([$name, $guild]);
            } else {
                $db->prepare("INSERT INTO discord_companion_guilds (guild_id, tenant_id, guild_name, channel_id, channels_json, synced_at) VALUES (?, ?, ?, '', '{}', ?)")
                    ->execute([$guild, $tenant, $name, $now]);
            }
            $this->touchSettings($tenant, ['linked_by_user_id' => $userId, 'linked_at' => $now, 'updated_at' => $now]);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Le bot a vu le serveur : état « connecté », rôles à jour (les salons sont enregistrés par DiscordCompanionRepository::updateChannels).
     *
     * @param array<string, string>|null $roles
     */
    public function markSeen(string $guild, ?array $roles, ?string $now = null): ?int
    {
        $tenant = $this->tenantForGuild($guild);
        if ($tenant === null) {
            return null;
        }
        $fields = ['bot_seen_at' => $now ?? date('Y-m-d H:i:s')];
        if ($roles !== null) {
            $fields['roles_json'] = json_encode($roles, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }
        $this->touchSettings($tenant, $fields);

        return $tenant;
    }

    /** Le bot a quitté (ou a été retiré du) serveur : il apparaît « absent ». */
    public function markRemoved(string $guild): void
    {
        $tenant = $this->tenantForGuild($guild);
        if ($tenant !== null) {
            $this->touchSettings($tenant, ['bot_seen_at' => '2000-01-01 00:00:00']);
        }
    }

    /**
     * Enregistre salons, rôles et options. Salons et rôles ne sont acceptés que s'ils ont été signalés par le bot sur ce serveur.
     *
     * @param array<string, mixed> $in
     * @return array{ok:bool, error?:string}
     */
    public function saveSettings(int $tenant, array $in): array
    {
        if ($this->guildForTenant($tenant) === null) {
            return ['ok' => false, 'error' => 'no_guild_linked'];
        }
        $channels = $this->channels($tenant);
        $roles = $this->roles($tenant);
        $fields = [];
        foreach (self::CHANNEL_KEYS as $key) {
            $v = trim((string) ($in[$key] ?? ''));
            if ($v !== '' && !isset($channels[$v])) {
                return ['ok' => false, 'error' => 'unknown_channel'];
            }
            $fields[$key] = $v === '' ? null : $v;
        }
        foreach (self::ROLE_KEYS as $key) {
            $v = trim((string) ($in[$key] ?? ''));
            if ($v !== '' && !isset($roles[$v])) {
                return ['ok' => false, 'error' => 'unknown_role'];
            }
            $fields[$key] = $v === '' ? null : $v;
        }
        if ($fields['member_role_id'] !== null && $fields['member_role_id'] === $fields['manager_role_id']) {
            return ['ok' => false, 'error' => 'same_role'];
        }
        foreach (['sync_roles', 'reminders_enabled', 'welcome_enabled'] as $flag) {
            $fields[$flag] = !empty($in[$flag]) ? 1 : 0;
        }
        $fields['updated_at'] = date('Y-m-d H:i:s');
        $this->touchSettings($tenant, $fields);
        // Le salon « événements » est aussi la destination par défaut des annonces d'événements du bot.
        $this->db()->prepare('UPDATE discord_companion_guilds SET channel_id = ? WHERE tenant_id = ?')
            ->execute([(string) ($fields['events_channel_id'] ?? ''), $tenant]);

        return ['ok' => true];
    }

    /** Le bouton « Tester » : le bot postera un message d'essai au prochain passage (une minute au plus). */
    public function requestTest(int $tenant, ?string $now = null): void
    {
        $this->touchSettings($tenant, ['test_requested_at' => $now ?? date('Y-m-d H:i:s')]);
    }

    public function clearTest(int $tenant): void
    {
        $this->touchSettings($tenant, ['test_requested_at' => null]);
    }

    public function unlink(int $tenant): void
    {
        $this->db()->prepare("UPDATE community_event_discord SET channel_id = NULL, message_id = NULL, reactions_seeded = 0 WHERE tenant_id = ? AND destination = 'companion'")->execute([$tenant]);
        $this->db()->prepare('DELETE FROM discord_companion_guilds WHERE tenant_id = ?')->execute([$tenant]);
        $this->db()->prepare('DELETE FROM discord_companion_pairings WHERE tenant_id = ?')->execute([$tenant]);
        $this->db()->prepare('DELETE FROM discord_bot_settings WHERE tenant_id = ?')->execute([$tenant]);
        $this->db()->prepare('DELETE FROM discord_bot_reminders WHERE tenant_id = ?')->execute([$tenant]);
    }

    // ---- Rappels, audit, rappel du tableau de bord ---------------------------------------------------------------

    public function reminderSent(int $tenant, string $kind, string $ref): bool
    {
        $st = $this->db()->prepare('SELECT 1 FROM discord_bot_reminders WHERE tenant_id = ? AND kind = ? AND ref = ?');
        $st->execute([$tenant, $kind, mb_substr($ref, 0, 80)]);

        return (bool) $st->fetchColumn();
    }

    public function markReminder(int $tenant, string $kind, string $ref, ?string $now = null): void
    {
        if (!$this->reminderSent($tenant, $kind, $ref)) {
            $this->db()->prepare('INSERT INTO discord_bot_reminders (tenant_id, kind, ref, sent_at) VALUES (?, ?, ?, ?)')
                ->execute([$tenant, $kind, mb_substr($ref, 0, 80), $now ?? date('Y-m-d H:i:s')]);
        }
    }

    /** Journal sans contenu : qui (identifiant Discord), quelle commande, quel résultat. */
    public function audit(int $tenant, string $guild, string $discordUser, string $command, string $outcome, ?string $now = null): void
    {
        $this->db()->prepare('INSERT INTO discord_bot_audit (tenant_id, guild_id, discord_user_id, command, outcome, created_at) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$tenant, $guild, $discordUser, mb_substr($command, 0, 40), mb_substr($outcome, 0, 24), $now ?? date('Y-m-d H:i:s')]);
    }

    public function recentCommands(string $discordUser, int $seconds, ?int $now = null): int
    {
        $st = $this->db()->prepare('SELECT COUNT(*) FROM discord_bot_audit WHERE discord_user_id = ? AND created_at >= ?');
        $st->execute([$discordUser, date('Y-m-d H:i:s', ($now ?? time()) - $seconds)]);

        return (int) $st->fetchColumn();
    }

    public function dismissed(int $tenant, int $user): bool
    {
        try {
            $st = $this->db()->prepare('SELECT 1 FROM discord_bot_dismissals WHERE tenant_id = ? AND user_id = ?');
            $st->execute([$tenant, $user]);

            return (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return true;
        }
    }

    public function dismiss(int $tenant, int $user, ?string $now = null): void
    {
        if (!$this->dismissed($tenant, $user)) {
            $this->db()->prepare('INSERT INTO discord_bot_dismissals (tenant_id, user_id, dismissed_at) VALUES (?, ?, ?)')
                ->execute([$tenant, $user, $now ?? date('Y-m-d H:i:s')]);
        }
    }

    /** @param array<string, mixed> $fields */
    private function touchSettings(int $tenant, array $fields): void
    {
        $db = $this->db();
        $st = $db->prepare('SELECT 1 FROM discord_bot_settings WHERE tenant_id = ?');
        $st->execute([$tenant]);
        if (!$st->fetchColumn()) {
            $db->prepare('INSERT INTO discord_bot_settings (tenant_id, sync_roles, reminders_enabled, welcome_enabled) VALUES (?, 0, 1, 0)')->execute([$tenant]);
        }
        if ($fields === []) {
            return;
        }
        $set = implode(', ', array_map(static fn (string $k): string => $k . ' = ?', array_keys($fields)));
        $db->prepare('UPDATE discord_bot_settings SET ' . $set . ' WHERE tenant_id = ?')->execute([...array_values($fields), $tenant]);
    }
}
