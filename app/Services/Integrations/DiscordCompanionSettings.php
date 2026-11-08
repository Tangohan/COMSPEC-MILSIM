<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Repositories\PlatformSettingsRepository;
use App\Support\SecretCipher;

final class DiscordCompanionSettings
{
    public function __construct(private ?\Closure $read = null)
    {
    }

    public const API_KEY = 'discord.companion_api_key';

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
        $id = (new DiscordLinkedRoles())->clientId();
        return $id === '' ? '' : 'https://discord.com/oauth2/authorize?' . http_build_query([
            'client_id' => $id, 'scope' => 'bot applications.commands', 'permissions' => '85056',
        ], '', '&', PHP_QUERY_RFC3986);
    }
}
