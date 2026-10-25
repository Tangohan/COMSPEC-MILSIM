<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Repositories\PlatformSettingsRepository;
use App\Repositories\UserDiscordLinkRepository;
use App\Repositories\UserRepository;
use App\Support\SecretCipher;

/**
 * Rôles liés de Discord (Linked Roles) : le membre relie son compte Discord à son compte du site, puis le site
 * dit à Discord qui il est sur Athena. Le serveur Discord donne lui-même les rôles dont les conditions sont
 * remplies (Paramètres du serveur › Rôles › Liens), et le profil Discord du membre affiche « Athena » avec le nom
 * de ses communautés.
 *
 * Réglages (Administration › Intégrations de la plateforme, enregistrés en base) : identifiant de l'application Discord et son secret
 * client (chiffré). Le jeton du bot n'est jamais sur le site : la déclaration des conditions (METADATA) se fait
 * avec le script tools/discord/comspec_discord_contributors.py, sur le PC qui détient le jeton.
 *
 * Adresses à copier dans le portail des développeurs Discord : verificationUrl() (« Linked Roles Verification
 * URL ») et redirectUri() (OAuth2 › Redirects).
 */
final class DiscordLinkedRoles
{
    public const API = 'https://discord.com/api/v10';
    public const SETTING_CLIENT_ID = 'discord.linked_roles_client_id';
    public const SETTING_SECRET = 'discord.linked_roles_client_secret';
    public const PLATFORM_NAME = 'Athena';

    /**
     * Conditions proposées aux serveurs Discord (au plus 5). Types Discord : 2 = nombre au moins égal,
     * 5 = date au plus tard (« depuis au moins N jours »), 7 = oui/non. Le script du PC déclare la même liste
     * (tools/discord/comspec_discord_contributors.py, LINKED_ROLES_METADATA).
     */
    public const METADATA = [
        ['key' => 'athena_account', 'type' => 7, 'name' => 'Athena account', 'description' => 'Has an Athena account linked',
            'name_localizations' => ['fr' => 'Compte Athena'], 'description_localizations' => ['fr' => 'A relié son compte Athena']],
        ['key' => 'community_member', 'type' => 7, 'name' => 'Community member', 'description' => 'Member of an Athena community',
            'name_localizations' => ['fr' => 'Membre d’une communauté'], 'description_localizations' => ['fr' => 'Membre d’une communauté Athena']],
        ['key' => 'communities', 'type' => 2, 'name' => 'Communities', 'description' => 'Number of Athena communities',
            'name_localizations' => ['fr' => 'Communautés'], 'description_localizations' => ['fr' => 'Nombre de communautés Athena']],
        ['key' => 'member_since', 'type' => 5, 'name' => 'Days on Athena', 'description' => 'Days since the Athena account was created',
            'name_localizations' => ['fr' => 'Jours sur Athena'], 'description_localizations' => ['fr' => 'Jours depuis la création du compte Athena']],
    ];

    private const SCOPES = 'identify role_connections.write';

    /**
     * @param (\Closure(string, string, array<string, string>, ?string): array{status:int, body:string})|null $http
     * @param (\Closure(string): string)|null $read lecture platform_settings
     * @param (\Closure(array<string, string>): void)|null $write écriture platform_settings
     */
    public function __construct(
        private ?\Closure $http = null,
        private ?\Closure $read = null,
        private ?\Closure $write = null,
        private ?UserDiscordLinkRepository $links = null,
        private ?UserRepository $users = null,
    ) {
    }

    // ---- Réglages -----------------------------------------------------------------------------------------------

    public function clientId(): string
    {
        return trim($this->setting(self::SETTING_CLIENT_ID));
    }

    public function clientSecret(): string
    {
        $raw = $this->setting(self::SETTING_SECRET);

        return $raw === '' ? '' : (string) (SecretCipher::decrypt($raw) ?? '');
    }

    public function configured(): bool
    {
        return $this->clientId() !== '' && $this->clientSecret() !== '';
    }

    /** @return array{client_id:string, has_secret:bool, verification_url:string, redirect_uri:string, linked:int} */
    public function adminState(): array
    {
        $linked = 0;
        try {
            $linked = $this->links()->schemaReady() ? $this->links()->count() : 0;
        } catch (\Throwable) {
        }

        return [
            'client_id' => $this->clientId(),
            'has_secret' => $this->clientSecret() !== '',
            'verification_url' => self::verificationUrl(),
            'redirect_uri' => self::redirectUri(),
            'linked' => $linked,
        ];
    }

    /** @return array{ok:bool, message:string} */
    public function saveConfig(string $clientId, string $secret, bool $clearSecret): array
    {
        $clientId = trim($clientId);
        if ($clientId !== '' && preg_match('/^\d{17,20}$/', $clientId) !== 1) {
            return ['ok' => false, 'message' => 'L’identifiant de l’application Discord est un nombre de 17 à 20 chiffres (portail des développeurs › General Information › Application ID).'];
        }
        $pairs = [self::SETTING_CLIENT_ID => $clientId];
        $secret = trim($secret);
        if ($clearSecret) {
            $pairs[self::SETTING_SECRET] = '';
        } elseif ($secret !== '') {
            if (preg_match('/^[A-Za-z0-9_\-]{16,64}$/', $secret) !== 1) {
                return ['ok' => false, 'message' => 'Le secret client ne ressemble pas à un secret Discord (portail des développeurs › OAuth2 › Client Secret).'];
            }
            $pairs[self::SETTING_SECRET] = SecretCipher::encrypt($secret);
        }
        try {
            ($this->write ??= static function (array $pairs): void {
                $repo = new PlatformSettingsRepository();
                if (!$repo->tableExists()) {
                    throw new \RuntimeException('platform_settings');
                }
                $repo->setMany($pairs);
            })($pairs);
        } catch (\Throwable) {
            return ['ok' => false, 'message' => 'Les réglages des rôles liés n’ont pas pu être enregistrés (table platform_settings absente ?).'];
        }

        return ['ok' => true, 'message' => 'Rôles liés de Discord : réglages enregistrés.'];
    }

    public static function verificationUrl(): string
    {
        return url('discord/roles-lies');
    }

    public static function redirectUri(): string
    {
        return url('discord/roles-lies/retour');
    }

    public function authorizeUrl(string $state): string
    {
        return 'https://discord.com/oauth2/authorize?' . http_build_query([
            'client_id' => $this->clientId(),
            'redirect_uri' => self::redirectUri(),
            'response_type' => 'code',
            'scope' => self::SCOPES,
            'state' => $state,
            'prompt' => 'consent',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    // ---- Ce que Discord reçoit ----------------------------------------------------------------------------------

    /**
     * Données envoyées à Discord pour un compte : nom des communautés sur le profil (sinon le nom du membre),
     * conditions METADATA.
     *
     * @param array<string, mixed> $user ligne users
     * @param list<array<string, mixed>> $communities communautés actives du membre (name, slug)
     * @return array{platform_name:string, platform_username:string, metadata:array<string, string>}
     */
    public static function connectionFor(array $user, array $communities): array
    {
        $names = [];
        foreach ($communities as $c) {
            $name = trim((string) ($c['name'] ?? ''));
            if ($name !== '' && (string) ($c['slug'] ?? '') !== 'default' && !in_array($name, $names, true)) {
                $names[] = $name;
            }
        }
        $label = implode(' · ', $names);
        if ($label === '') {
            $label = trim((string) ($user['display_name'] ?? '')) ?: (string) ($user['callsign'] ?? '');
        }
        if (mb_strlen($label) > 100) {
            $label = rtrim(mb_substr($label, 0, 99)) . '…';
        }
        $created = strtotime((string) ($user['created_at'] ?? '')) ?: time();

        return [
            'platform_name' => self::PLATFORM_NAME,
            'platform_username' => $label !== '' ? $label : self::PLATFORM_NAME,
            'metadata' => [
                'athena_account' => '1',
                'community_member' => $names !== [] ? '1' : '0',
                'communities' => (string) count($names),
                'member_since' => gmdate('Y-m-d\TH:i:s\Z', $created),
            ],
        ];
    }

    // ---- Liaison ------------------------------------------------------------------------------------------------

    /**
     * Fin du parcours OAuth : jetons, compte Discord, enregistrement chiffré, premier envoi.
     *
     * @return array{ok:bool, message:string, username?:string}
     */
    public function link(int $userId, string $code): array
    {
        if (!$this->configured()) {
            return ['ok' => false, 'message' => 'Les rôles liés de Discord ne sont pas encore réglés sur le site.'];
        }
        if (!$this->links()->schemaReady()) {
            return ['ok' => false, 'message' => 'La liaison Discord n’est pas encore installée sur le serveur (migrations à lancer).'];
        }
        $tokens = $this->token(['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => self::redirectUri()]);
        if ($tokens === null) {
            return ['ok' => false, 'message' => 'Discord a refusé la liaison. Réessayez depuis le début.'];
        }
        $me = $this->call('GET', '/users/@me', $tokens['access_token']);
        $discordId = (string) ($me['id'] ?? '');
        if (preg_match('/^\d{15,24}$/', $discordId) !== 1) {
            return ['ok' => false, 'message' => 'Discord n’a pas donné le compte. Réessayez depuis le début.'];
        }
        $username = (string) ($me['global_name'] ?? '') ?: (string) ($me['username'] ?? '');
        $this->links()->save($userId, [
            'discord_user_id' => $discordId,
            'discord_username' => $username,
            'access_token' => SecretCipher::encrypt($tokens['access_token']),
            'refresh_token' => SecretCipher::encrypt($tokens['refresh_token']),
            'expires_at' => $tokens['expires_at'],
        ]);
        $sync = $this->sync($userId);

        return $sync['ok']
            ? ['ok' => true, 'message' => 'Compte Discord « ' . $username . ' » relié : vos rôles liés sont à jour.', 'username' => $username]
            : ['ok' => false, 'message' => 'Compte Discord relié, mais l’envoi à Discord a échoué : ' . $sync['message']];
    }

    /** Renvoie à Discord l'état actuel du compte (communautés, ancienneté). @return array{ok:bool, message:string} */
    public function sync(int $userId): array
    {
        $link = $this->links()->find($userId);
        if ($link === null) {
            return ['ok' => false, 'message' => 'Aucun compte Discord relié.'];
        }
        $access = $this->freshAccessToken($userId, $link);
        if ($access === null) {
            $this->links()->markSynced($userId, 'refresh_failed');

            return ['ok' => false, 'message' => 'Discord a retiré l’accès : reliez à nouveau votre compte.'];
        }
        $user = $this->users()->findById($userId, null);
        if ($user === null) {
            return ['ok' => false, 'message' => 'Compte introuvable.'];
        }
        $communities = [];
        try {
            $communities = $this->users()->listTenantsForEmail((string) ($user['email'] ?? ''));
        } catch (\Throwable) {
        }
        $result = $this->call('PUT', '/users/@me/applications/' . $this->clientId() . '/role-connection', $access, self::connectionFor($user, $communities));
        $ok = $result !== null;
        $this->links()->markSynced($userId, $ok ? '' : 'push_failed');

        return $ok ? ['ok' => true, 'message' => 'Rôles liés mis à jour.'] : ['ok' => false, 'message' => 'Discord n’a pas accepté la mise à jour.'];
    }

    /** Retire les données envoyées à Discord, rend l'accès et oublie la liaison. */
    public function unlink(int $userId): void
    {
        $link = $this->links()->find($userId);
        if ($link === null) {
            return;
        }
        $access = $this->freshAccessToken($userId, $link);
        if ($access !== null) {
            $this->call('PUT', '/users/@me/applications/' . $this->clientId() . '/role-connection', $access, [
                'platform_name' => self::PLATFORM_NAME, 'platform_username' => null, 'metadata' => (object) [],
            ]);
            $this->request('POST', self::API . '/oauth2/token/revoke', ['Content-Type' => 'application/x-www-form-urlencoded'], http_build_query([
                'token' => $access, 'token_type_hint' => 'access_token',
                'client_id' => $this->clientId(), 'client_secret' => $this->clientSecret(),
            ]));
        }
        $this->links()->delete($userId);
    }

    /** Tâche automatique : renvoie les comptes pas envoyés depuis 24 h (changement de communauté, ancienneté). */
    public function syncStale(int $limit = 50): array
    {
        if (!$this->configured() || !$this->links()->schemaReady()) {
            return ['ok' => 0, 'failed' => 0];
        }
        $count = ['ok' => 0, 'failed' => 0];
        foreach ($this->links()->staleUserIds(24, $limit) as $uid) {
            $count[$this->sync($uid)['ok'] ? 'ok' : 'failed']++;
        }

        return $count;
    }

    // ---- HTTP ---------------------------------------------------------------------------------------------------

    /** @param array<string, mixed> $link */
    private function freshAccessToken(int $userId, array $link): ?string
    {
        $access = SecretCipher::decrypt((string) ($link['access_token'] ?? ''));
        $expires = strtotime((string) ($link['expires_at'] ?? '')) ?: 0;
        if ($access !== null && $expires > time() + 60) {
            return $access;
        }
        $refresh = SecretCipher::decrypt((string) ($link['refresh_token'] ?? ''));
        if ($refresh === null || !$this->configured()) {
            return null;
        }
        $tokens = $this->token(['grant_type' => 'refresh_token', 'refresh_token' => $refresh]);
        if ($tokens === null) {
            return null;
        }
        $this->links()->updateTokens($userId, SecretCipher::encrypt($tokens['access_token']), SecretCipher::encrypt($tokens['refresh_token']), $tokens['expires_at']);

        return $tokens['access_token'];
    }

    /**
     * @param array<string, string> $form
     * @return array{access_token:string, refresh_token:string, expires_at:string}|null
     */
    private function token(array $form): ?array
    {
        $res = $this->request('POST', self::API . '/oauth2/token', ['Content-Type' => 'application/x-www-form-urlencoded'], http_build_query($form + [
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
        ]));
        $data = $res['status'] === 200 ? json_decode($res['body'], true) : null;
        if (!is_array($data) || !is_string($data['access_token'] ?? null) || !is_string($data['refresh_token'] ?? null)) {
            return null;
        }

        return [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
            'expires_at' => date('Y-m-d H:i:s', time() + max(60, (int) ($data['expires_in'] ?? 604800))),
        ];
    }

    /**
     * @param array<string, mixed>|null $json
     * @return array<string, mixed>|null réponse décodée, null si Discord refuse
     */
    private function call(string $method, string $path, string $access, ?array $json = null): ?array
    {
        $headers = ['Authorization' => 'Bearer ' . $access];
        if ($json !== null) {
            $headers['Content-Type'] = 'application/json';
        }
        $res = $this->request($method, self::API . $path, $headers, $json !== null ? (string) json_encode($json, JSON_UNESCAPED_UNICODE) : null);
        if ($res['status'] < 200 || $res['status'] >= 300) {
            return null;
        }
        $data = json_decode($res['body'], true);

        return is_array($data) ? $data : [];
    }

    /**
     * @param array<string, string> $headers
     * @return array{status:int, body:string}
     */
    private function request(string $method, string $url, array $headers, ?string $body): array
    {
        $http = $this->http ??= static function (string $method, string $url, array $headers, ?string $body): array {
            $ch = curl_init($url);
            $lines = ['User-Agent: Athena (https://athena.ttrd.fr, 1.0)'];
            foreach ($headers as $k => $v) {
                $lines[] = $k . ': ' . $v;
            }
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $lines,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
            ]);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
            $out = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return ['status' => $out === false ? 0 : $status, 'body' => is_string($out) ? $out : ''];
        };

        try {
            return $http($method, $url, $headers, $body);
        } catch (\Throwable) {
            return ['status' => 0, 'body' => ''];
        }
    }

    private function setting(string $key): string
    {
        try {
            return ($this->read ??= static fn (string $k): string => (new PlatformSettingsRepository())->get($k, ''))($key);
        } catch (\Throwable) {
            return '';
        }
    }

    private function links(): UserDiscordLinkRepository
    {
        return $this->links ??= new UserDiscordLinkRepository();
    }

    private function users(): UserRepository
    {
        return $this->users ??= \App\Core\Container::get(UserRepository::class);
    }
}
