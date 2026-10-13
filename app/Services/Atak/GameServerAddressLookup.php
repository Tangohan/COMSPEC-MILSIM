<?php

declare(strict_types=1);

namespace App\Services\Atak;

use PDO;

/**
 * Adresse publique (IP et port de jeu) d'un serveur Arma 3 retrouvée à partir de son nom dans la liste des serveurs.
 * Sert quand le serveur ne charge pas COMSPEC Link (pas d'adresse diffusée aux joueurs) : liste maître Steam
 * (IGameServersService, clé STEAM_WEB_API_KEY), sinon BattleMetrics (sans clé). Seul un nom identique est retenu.
 * Résultats gardés dans game_server_address_lookup (24 h trouvé, 6 h introuvable) pour ne pas interroger à chaque appel.
 */
final class GameServerAddressLookup
{
    private const APP_ID = 107410;
    private const TTL_FOUND = 86400;
    private const TTL_MISS = 21600;

    /** @var callable(string): ?string */
    private $http;

    private ?PDO $pdo;

    /** @param (callable(string): ?string)|null $http lecture d'une URL (tests) */
    public function __construct(?PDO $pdo = null, ?callable $http = null, private ?string $steamKey = null)
    {
        $this->pdo = $pdo;
        $this->http = $http ?? static function (string $url): ?string {
            $ctx = stream_context_create([
                'http' => [
                    'timeout' => 4,
                    'ignore_errors' => true,
                    'header' => "Accept: application/json\r\nUser-Agent: AthenaGameServerLookup/1.0\r\n",
                ],
                'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
            ]);
            $raw = @file_get_contents($url, false, $ctx);

            return is_string($raw) && $raw !== '' ? $raw : null;
        };
        if ($this->steamKey === null) {
            $k = (string) (($_ENV['STEAM_WEB_API_KEY'] ?? null) ?: (getenv('STEAM_WEB_API_KEY') ?: ''));
            $this->steamKey = trim($k);
        }
    }

    /**
     * @return array{ip: string, port: int|null, source: string}|null
     */
    public function lookupByName(string $serverName, bool $useCache = true): ?array
    {
        $key = CommunityServerMatcher::normalizeName($serverName);
        if (mb_strlen($key, 'UTF-8') < 4) {
            return null;
        }
        $key = mb_substr($key, 0, 190, 'UTF-8');
        if ($useCache) {
            $cached = $this->cached($key);
            if ($cached !== false) {
                return $cached;
            }
        }
        $found = $this->fromSteam($serverName, $key) ?? $this->fromBattleMetrics($serverName, $key);
        $this->store($key, $found);

        return $found;
    }

    /** @return array{ip: string, port: int|null, source: string}|null */
    private function fromSteam(string $name, string $key): ?array
    {
        if ($this->steamKey === null || strlen($this->steamKey) < 10) {
            return null;
        }
        // « \\ » sépare les champs du filtre Steam ; seul un nom identique est retenu ensuite.
        $filter = '\\appid\\' . self::APP_ID . '\\name_match\\' . str_replace('\\', '', $name);
        $raw = ($this->http)('https://api.steampowered.com/IGameServersService/GetServerList/v1/?key='
            . rawurlencode($this->steamKey) . '&limit=20&filter=' . rawurlencode($filter));
        $json = $raw !== null ? json_decode($raw, true) : null;
        $list = is_array($json) ? ($json['response']['servers'] ?? []) : [];
        if (!is_array($list)) {
            return null;
        }
        foreach ($list as $s) {
            if (!is_array($s) || CommunityServerMatcher::normalizeName((string) ($s['name'] ?? '')) !== $key) {
                continue;
            }
            $addr = (string) ($s['addr'] ?? '');
            $ip = CommunityServerMatcher::normalizeHost($addr);
            if ($ip === '') {
                continue;
            }
            $port = (int) ($s['gameport'] ?? 0);

            return ['ip' => $ip, 'port' => $port > 0 ? $port : null, 'source' => 'steam'];
        }

        return null;
    }

    /** @return array{ip: string, port: int|null, source: string}|null */
    private function fromBattleMetrics(string $name, string $key): ?array
    {
        $raw = ($this->http)('https://api.battlemetrics.com/servers?filter%5Bgame%5D=arma3&page%5Bsize%5D=10&filter%5Bsearch%5D='
            . rawurlencode('"' . str_replace('"', '', $name) . '"'));
        $json = $raw !== null ? json_decode($raw, true) : null;
        $list = is_array($json) ? ($json['data'] ?? []) : [];
        if (!is_array($list)) {
            return null;
        }
        foreach ($list as $s) {
            $a = is_array($s) ? ($s['attributes'] ?? null) : null;
            if (!is_array($a) || CommunityServerMatcher::normalizeName((string) ($a['name'] ?? '')) !== $key) {
                continue;
            }
            $ip = CommunityServerMatcher::normalizeHost((string) ($a['ip'] ?? ''));
            if ($ip === '') {
                continue;
            }
            $port = (int) ($a['port'] ?? 0);

            return ['ip' => $ip, 'port' => $port > 0 ? $port : null, 'source' => 'battlemetrics'];
        }

        return null;
    }

    /** @return array{ip: string, port: int|null, source: string}|null|false false : pas en cache */
    private function cached(string $key): array|null|false
    {
        if ($this->pdo === null) {
            return false;
        }
        try {
            $st = $this->pdo->prepare('SELECT ip, port, source, looked_up_at FROM game_server_address_lookup WHERE name_key = ?');
            $st->execute([$key]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return false;
        }
        if (!is_array($row)) {
            return false;
        }
        $ip = (string) ($row['ip'] ?? '');
        $age = time() - (int) strtotime((string) $row['looked_up_at']);
        if ($age > ($ip !== '' ? self::TTL_FOUND : self::TTL_MISS)) {
            return false;
        }

        return $ip === '' ? null : ['ip' => $ip, 'port' => $row['port'] !== null ? (int) $row['port'] : null, 'source' => (string) $row['source']];
    }

    /** @param array{ip: string, port: int|null, source: string}|null $found */
    private function store(string $key, ?array $found): void
    {
        if ($this->pdo === null) {
            return;
        }
        try {
            $st = $this->pdo->prepare(
                'INSERT INTO game_server_address_lookup (name_key, ip, port, source, looked_up_at) VALUES (?, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE ip = VALUES(ip), port = VALUES(port), source = VALUES(source), looked_up_at = NOW()'
            );
            $st->execute([$key, $found['ip'] ?? null, $found['port'] ?? null, $found['source'] ?? null]);
        } catch (\Throwable) {
        }
    }
}
