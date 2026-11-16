<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Repositories\PlatformSettingsRepository;
use App\Support\SecretCipher;

final class DiscordCompanionSettings
{
    public const API_KEY = 'discord.companion_api_key';
    /** Application Discord du bot (Athena Companion), distincte de celle des rôles liés. */
    public const CLIENT_ID = 'discord.companion_client_id';
    public const CLIENT_SECRET = 'discord.companion_client_secret';

    public function __construct(private ?\Closure $read = null, private ?DiscordLinkedRoles $linked = null)
    {
    }

    public static function validClientId(string $id): bool
    {
        return preg_match('/^\d{17,20}$/D', $id) === 1;
    }

    /** Identifiant public de l'application du bot ; vide tant qu'il n'est pas réglé. */
    public function clientId(): string
    {
        return trim($this->setting(self::CLIENT_ID));
    }

    /** Secret client du bot, déchiffré (jamais à afficher). */
    public function clientSecret(): string
    {
        $raw = $this->setting(self::CLIENT_SECRET);

        return $raw === '' ? '' : (string) (SecretCipher::decrypt($raw) ?? '');
    }

    public function hasClientSecret(): bool
    {
        return $this->clientSecret() !== '';
    }

    /** Identifiant utilisé pour inviter le bot : le sien, sinon (compatibilité) celui des rôles liés. */
    public function inviteClientId(): string
    {
        $own = $this->clientId();

        return $own !== '' ? $own : $this->linked()->clientId();
    }

    /**
     * Ce qui cloche dans le réglage de l'invitation, pour avertir l'administrateur.
     * missing : identifiant du bot absent (repli sur les rôles liés) ; same : identique à celui des rôles liés ;
     * no_secret : identifiant du bot réglé sans son secret (le code OAuth ne pourrait pas être échangé).
     */
    public function inviteWarning(): string
    {
        $own = $this->clientId();
        if ($own === '') {
            return 'missing';
        }
        if ($own === $this->linked()->clientId()) {
            return 'same';
        }

        return $this->hasClientSecret() ? '' : 'no_secret';
    }

    private function setting(string $key): string
    {
        try {
            return $this->read !== null ? (string) ($this->read)($key) : (new PlatformSettingsRepository())->get($key, '');
        } catch (\Throwable) {
            return '';
        }
    }

    private function linked(): DiscordLinkedRoles
    {
        return $this->linked ??= new DiscordLinkedRoles();
    }

    public function key(): string
    {
        return SecretCipher::decrypt($this->read !== null ? ($this->read)(self::API_KEY) : (new PlatformSettingsRepository())->get(self::API_KEY, '')) ?? '';
    }

    public function accepts(string $header): bool
    {
        $key = $this->key();
        return $key !== '' && str_starts_with($header, 'Bearer ') && hash_equals($key, substr($header, 7));
    }

    public function inviteUrl(): string
    {
        $id = $this->inviteClientId();
        return $id === '' ? '' : 'https://discord.com/oauth2/authorize?' . http_build_query([
            'client_id' => $id, 'scope' => 'bot applications.commands', 'permissions' => '85056',
        ], '', '&', PHP_QUERY_RFC3986);
    }
}
