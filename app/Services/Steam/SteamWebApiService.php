<?php

declare(strict_types=1);

namespace App\Services\Steam;

use App\Support\SteamId;

/**
 * Lecture du profil public Steam (Web API officielle).
 * La clé doit être fournie via STEAM_WEB_API_KEY dans l’environnement — jamais en dur dans le code.
 */
final class SteamWebApiService
{
    private function apiKey(): string
    {
        $k = (string) (($_ENV['STEAM_WEB_API_KEY'] ?? null) ?: (getenv('STEAM_WEB_API_KEY') ?: ''));

        return trim($k);
    }

    public function isConfigured(): bool
    {
        return strlen($this->apiKey()) >= 10;
    }

    /**
     * Accepte SteamID64, SteamID2 / SteamID3, une adresse « …/profiles/… »,
     * ou, si la clé serveur Steam est configurée, un pseudo d’URL « …/id/pseudo ».
     * Toujours normalisé vers SteamID64 (chiffres) quand la résolution réussit.
     */
    public function resolveSteamIdFromUserInput(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $offline = SteamId::normalize($raw);
        if ($offline !== null) {
            return $offline;
        }

        if (preg_match('#steamcommunity\.com/id/([^/?\#\s]+)#i', $raw, $m)) {
            $vanity = rawurldecode(trim($m[1]));

            return $this->resolveVanityToSteamId64($vanity);
        }

        if (!str_contains($raw, '/') && !str_starts_with($raw, 'http') && preg_match('#^[a-zA-Z0-9_-]{2,64}$#', $raw)) {
            return $this->resolveVanityToSteamId64($raw);
        }

        return null;
    }

    private function resolveVanityToSteamId64(string $vanity): ?string
    {
        $vanity = trim($vanity);
        if ($vanity === '' || !$this->isConfigured()) {
            return null;
        }

        $url = 'https://api.steampowered.com/ISteamUser/ResolveVanityURL/v0001/?key='
            . rawurlencode($this->apiKey())
            . '&vanityurl=' . rawurlencode($vanity);

        $ctx = stream_context_create([
            'http' => [
                'timeout' => 10,
                'ignore_errors' => true,
                'header' => "Accept: application/json\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false || $raw === '') {
            return null;
        }
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            return null;
        }
        $resp = $json['response'] ?? null;
        if (!is_array($resp) || (int) ($resp['success'] ?? 0) !== 1) {
            return null;
        }
        $sid = isset($resp['steamid']) ? preg_replace('/\D/', '', (string) $resp['steamid']) : '';

        return SteamId::normalize($sid ?? '');
    }

    /**
     * @return array{steam_id: string, personaname: string, avatar_url: string}|null
     */
    public function fetchPublicPlayer(string $steamId64): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }
        $sid = SteamId::normalize($steamId64);
        if ($sid === null) {
            return null;
        }

        $url = 'https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key='
            . rawurlencode($this->apiKey())
            . '&steamids=' . rawurlencode($sid);

        $ctx = stream_context_create([
            'http' => [
                'timeout' => 10,
                'ignore_errors' => true,
                'header' => "Accept: application/json\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false || $raw === '') {
            return null;
        }
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            return null;
        }
        $players = $json['response']['players'] ?? null;
        if (!is_array($players) || $players === []) {
            return null;
        }
        $p = $players[0];
        if (!is_array($p)) {
            return null;
        }
        $outId = isset($p['steamid']) ? (string) $p['steamid'] : '';
        if ($outId === '' || $outId !== $sid) {
            return null;
        }
        $name = isset($p['personaname']) ? trim((string) $p['personaname']) : '';
        $avatar = isset($p['avatarfull']) ? trim((string) $p['avatarfull']) : '';
        if ($avatar === '') {
            $avatar = isset($p['avatarmedium']) ? trim((string) $p['avatarmedium']) : '';
        }
        if ($avatar === '') {
            $avatar = isset($p['avatar']) ? trim((string) $p['avatar']) : '';
        }
        if ($name === '' && $avatar === '') {
            return null;
        }

        return [
            'steam_id' => $outId,
            'personaname' => $name,
            'avatar_url' => $avatar,
        ];
    }
}
