<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Repositories\DiscordBotRepository;

/**
 * Invitation guidée du bot Athena sur le serveur Discord d'une communauté.
 *
 * Le gestionnaire est envoyé chez Discord (scopes bot, applications.commands, identify, guilds). Au retour, Athena vérifie
 * que le state signé est bien celui de sa session et de sa communauté (anti-CSRF), lit les serveurs de la personne
 * et n'accepte le serveur que si elle y a « Gérer le serveur » (ou en est propriétaire). Le serveur est alors rattaché à la
 * communauté : un serveur pour une communauté.
 */
final class DiscordBotInvite
{
    public const STATE_TTL = 900;

    /** Permissions minimales (jamais Administrateur) : bit Discord, clé de langue de la raison. */
    public const PERMISSIONS = [
        'view_channel' => [1 << 10, 'discord_bot.perm_view'],
        'send_messages' => [1 << 11, 'discord_bot.perm_send'],
        'embed_links' => [1 << 14, 'discord_bot.perm_embed'],
        'read_message_history' => [1 << 16, 'discord_bot.perm_history'],
        'add_reactions' => [1 << 6, 'discord_bot.perm_reactions'],
    ];
    /** Facultative : seulement pour la synchronisation des rôles, demandée à part. */
    public const MANAGE_ROLES = ['manage_roles', 1 << 28, 'discord_bot.perm_roles'];
    private const MANAGE_GUILD = 0x20;
    private const ADMINISTRATOR = 0x8;

    public function __construct(
        private ?DiscordLinkedRoles $discord = null,
        private ?DiscordBotRepository $repo = null,
        private ?string $key = null,
    ) {
    }

    public static function redirectUri(): string
    {
        return url('back-office/discord-bot/retour');
    }

    public static function permissionBits(bool $withRoles = false): int
    {
        $bits = 0;
        foreach (self::PERMISSIONS as [$bit]) {
            $bits |= $bit;
        }

        return $withRoles ? $bits | self::MANAGE_ROLES[1] : $bits;
    }

    public function configured(): bool
    {
        return $this->discord()->configured();
    }

    /** Nonce à garder en session et state signé qui l'embarque. */
    public function newNonce(): string
    {
        return bin2hex(random_bytes(16));
    }

    public function state(int $tenant, int $user, string $nonce, ?int $now = null): string
    {
        $payload = rtrim(strtr(base64_encode($tenant . '|' . $user . '|' . (($now ?? time()) + self::STATE_TTL) . '|' . $nonce), '+/', '-_'), '=');

        return $payload . '.' . hash_hmac('sha256', $payload, $this->secret());
    }

    /** @return array{tenant:int, user:int}|null */
    public function verifyState(string $state, string $nonce, ?int $now = null): ?array
    {
        $parts = explode('.', $state);
        if (count($parts) !== 2 || $nonce === '') {
            return null;
        }
        if (!hash_equals(hash_hmac('sha256', $parts[0], $this->secret()), $parts[1])) {
            return null;
        }
        $raw = base64_decode(strtr($parts[0], '-_', '+/'), true);
        $f = $raw === false ? [] : explode('|', $raw);
        if (count($f) !== 4 || !hash_equals($nonce, $f[3]) || (int) $f[2] < ($now ?? time())) {
            return null;
        }

        return ['tenant' => (int) $f[0], 'user' => (int) $f[1]];
    }

    public function inviteUrl(string $state, bool $withRoles = false): string
    {
        $id = $this->discord()->clientId();
        if ($id === '') {
            return '';
        }

        return 'https://discord.com/oauth2/authorize?' . http_build_query([
            'client_id' => $id,
            'scope' => 'bot applications.commands identify guilds',
            'permissions' => (string) self::permissionBits($withRoles),
            'response_type' => 'code',
            'redirect_uri' => self::redirectUri(),
            'state' => $state,
            'disable_guild_select' => 'false',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Retour OAuth de Discord.
     *
     * @return array{ok:bool, error?:string, guild?:string}
     *   erreurs : state, no_guild, oauth, not_member, not_manager, already_bound, schema
     */
    public function complete(string $code, string $state, string $guildId, string $sessionNonce, int $sessionTenant, int $sessionUser): array
    {
        $checked = $this->verifyState($state, $sessionNonce);
        if ($checked === null || $checked['tenant'] !== $sessionTenant || $checked['user'] !== $sessionUser || $sessionTenant < 2) {
            return ['ok' => false, 'error' => 'state'];
        }
        if (preg_match('/^\d{15,24}$/', $guildId) !== 1 || $code === '') {
            return ['ok' => false, 'error' => 'no_guild'];
        }
        if (!$this->repo()->schemaReady()) {
            return ['ok' => false, 'error' => 'schema'];
        }
        $guilds = $this->discord()->guildsOfInviter($code, self::redirectUri());
        if ($guilds === null) {
            return ['ok' => false, 'error' => 'oauth'];
        }
        $found = null;
        foreach ($guilds as $g) {
            if ($g['id'] === $guildId) {
                $found = $g;
            }
        }
        if ($found === null) {
            return ['ok' => false, 'error' => 'not_member'];
        }
        if (!$found['owner'] && ($found['permissions'] & (self::MANAGE_GUILD | self::ADMINISTRATOR)) === 0) {
            return ['ok' => false, 'error' => 'not_manager'];
        }
        try {
            $this->repo()->linkGuild($sessionTenant, $guildId, $found['name'], $sessionUser);
        } catch (\RuntimeException $e) {
            return ['ok' => false, 'error' => $e->getMessage() === 'companion.already_bound' ? 'already_bound' : 'schema'];
        }

        return ['ok' => true, 'guild' => $found['name']];
    }

    private function secret(): string
    {
        $k = $this->key ?? (function_exists('env') ? (string) env('APP_KEY', '') : '');

        return hash('sha256', 'athena-discord-bot-state|' . ($k !== '' ? $k : 'athena-dev-key'));
    }

    private function discord(): DiscordLinkedRoles
    {
        return $this->discord ??= new DiscordLinkedRoles();
    }

    private function repo(): DiscordBotRepository
    {
        return $this->repo ??= new DiscordBotRepository();
    }
}
