<?php

declare(strict_types=1);

namespace App\Services\Atak;

/**
 * Reconnaît le serveur de jeu d'un joueur parmi ceux déclarés par sa communauté.
 * Le téléphone ATAK envoie l'adresse publique du serveur (connue si le serveur charge COMSPEC Link) et son nom
 * affiché dans la liste des serveurs Arma ; l'adresse prime, le nom sert de repli.
 */
final class CommunityServerMatcher
{
    /** @var callable(string): list<string> */
    private $resolver;

    /** @param (callable(string): list<string>)|null $resolver résolution DNS d'un nom d'hôte (tests) */
    public function __construct(?callable $resolver = null)
    {
        $this->resolver = $resolver ?? static function (string $host): array {
            $ips = @gethostbynamel($host);

            return is_array($ips) ? array_values($ips) : [];
        };
    }

    /** Adresse sans port ni crochets, en minuscules ; "" si ce n'est ni une IP ni un nom d'hôte. */
    public static function normalizeHost(string $raw): string
    {
        $h = strtolower(trim($raw));
        if ($h === '') {
            return '';
        }
        if (preg_match('/^\[([0-9a-f:.]+)\](?::\d+)?$/', $h, $m)) {
            return $m[1];
        }
        if (substr_count($h, ':') === 1) {
            $h = explode(':', $h)[0];
        }
        if (filter_var($h, FILTER_VALIDATE_IP) !== false) {
            return $h;
        }

        return preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $h) ? $h : '';
    }

    /**
     * « 1.2.3.4:2302 », « [::1]:2302 » ou « 1.2.3.4 » en [adresse, port ou null].
     *
     * @return array{0: string, 1: int|null}
     */
    public static function splitAddress(string $raw): array
    {
        $raw = trim($raw);
        $port = null;
        if (preg_match('/^\[[0-9a-f:.]+\]:(\d{1,5})$/i', $raw, $m) || (substr_count($raw, ':') === 1 && preg_match('/:(\d{1,5})$/', $raw, $m))) {
            $p = (int) $m[1];
            $port = $p > 0 && $p < 65536 ? $p : null;
        }

        return [self::normalizeHost($raw), $port];
    }

    public static function normalizeName(string $raw): string
    {
        $n = mb_strtolower(trim($raw), 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $n));
    }

    /**
     * @param list<array<string, mixed>> $servers lignes tenant_game_servers actives
     * @return array<string, mixed>|null le serveur reconnu
     */
    public function match(array $servers, string $serverIp, string $serverName, ?int $serverPort = null): ?array
    {
        [$ip, $ipPort] = self::splitAddress($serverIp);
        $serverPort ??= $ipPort;
        $name = self::normalizeName($serverName);
        if ($ip !== '') {
            foreach ($servers as $s) {
                $host = self::normalizeHost((string) ($s['ip_address'] ?? ''));
                if ($host === '') {
                    continue;
                }
                // Plusieurs serveurs sur la même machine : le port les départage quand les deux sont connus.
                $declaredPort = (int) ($s['port'] ?? 0);
                if ($serverPort !== null && $declaredPort > 0 && $declaredPort !== $serverPort) {
                    continue;
                }
                if ($host === $ip) {
                    return $s;
                }
                if (filter_var($host, FILTER_VALIDATE_IP) === false && in_array($ip, ($this->resolver)($host), true)) {
                    return $s;
                }
            }
        }
        if ($name !== '') {
            foreach ($servers as $s) {
                $want = self::normalizeName((string) ($s['server_name'] ?? ''));
                // Nom déclaré contenu dans le nom affiché : les serveurs y ajoutent souvent la mission ou le nombre de places.
                if ($want !== '' && mb_strlen($want, 'UTF-8') >= 4 && str_contains($name, $want)) {
                    return $s;
                }
            }
        }

        return null;
    }
}
