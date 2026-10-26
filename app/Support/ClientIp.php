<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Adresse IP du client : seule source pour les limites de débit, les blocages par IP, la liste blanche de
 * maintenance et les journaux.
 *
 * Le VPS sert PHP par FastCGI directement derrière nginx (enregistrements A vers le serveur, ni CDN ni
 * mandataire, voir docs/nginx.example.conf) : REMOTE_ADDR est l'adresse réelle, alors que X-Forwarded-For,
 * Client-IP et CF-Connecting-IP sont écrits par le client lui-même. Les lire en premier laissait n'importe qui
 * choisir son adresse (contourner la limite de /login, faire bloquer l'adresse d'un autre joueur).
 *
 * Ces en-têtes ne sont donc lus que si la connexion vient d'un mandataire de confiance : la boucle locale, ou
 * une adresse listée dans TRUSTED_PROXIES (.env, séparées par des virgules, CIDR accepté, par exemple
 * « 10.0.0.0/8,2001:db8::/32 »). À renseigner si un CDN ou un mandataire est un jour placé devant le site.
 *
 * Seule exception, Cloudflare : une connexion qui vient de ses adresses publiées porte un CF-Connecting-IP écrit
 * par Cloudflare lui-même, que le visiteur ne peut pas choisir. Il est lu d'office, pour qu'un domaine passé
 * derrière Cloudflare (DNS en mode proxy) ne fasse pas partager une seule adresse à tous les joueurs (429 en
 * masse sur /api/atak/position, /login…) avant que TRUSTED_PROXIES soit renseigné.
 *
 * TRUST_CF_CONNECTING_IP=0 (.env) coupe toute lecture de CF-Connecting-IP : ni d'office depuis les adresses de
 * Cloudflare, ni depuis un mandataire de confiance sans X-Forwarded-For (tunnel cloudflared sur la boucle
 * locale). Absent ou 1 : comportement inchangé.
 */
final class ClientIp
{
    /** https://www.cloudflare.com/ips/ */
    private const CLOUDFLARE = [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18',
        '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17',
        '162.158.0.0/15', '104.16.0.0/13', '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32',
        '2a06:98c0::/29', '2c0f:f248::/32',
    ];

    /** @var list<array{0: string, 1: int}>|null */
    private static ?array $trusted = null;

    /** @var list<array{0: string, 1: int}>|null */
    private static ?array $cloudflare = null;

    /** TRUST_CF_CONNECTING_IP, lu une fois ; null : à relire. */
    private static ?bool $readsCfConnectingIp = null;

    /** @param array<string, mixed>|null $server */
    public static function resolve(?array $server = null): string
    {
        $server ??= $_SERVER;
        $remote = self::normalize($server['REMOTE_ADDR'] ?? null);
        if ($remote === null) {
            return '';
        }
        $readsCf = self::readsCfConnectingIp();
        if ($readsCf && self::inAnyRange($remote, self::$cloudflare ??= self::parseRanges(self::CLOUDFLARE))) {
            return self::normalize($server['HTTP_CF_CONNECTING_IP'] ?? null) ?? $remote;
        }
        if (!self::isTrustedProxy($remote)) {
            return $remote;
        }

        $forwarded = $server['HTTP_X_FORWARDED_FOR'] ?? null;
        if (is_string($forwarded) && trim($forwarded) !== '') {
            // De droite à gauche : chaque mandataire ajoute l'adresse qu'il a vue. La première adresse qui n'est
            // pas un mandataire de confiance est le client ; tout ce qui est à sa gauche vient du client.
            foreach (array_reverse(explode(',', $forwarded)) as $hop) {
                $ip = self::normalize($hop);
                if ($ip === null) {
                    break;
                }
                if (!self::isTrustedProxy($ip)) {
                    return $ip;
                }
            }
        }

        // Mandataire de confiance sans X-Forwarded-For utilisable (tunnel cloudflared sur la boucle locale).
        return ($readsCf ? self::normalize($server['HTTP_CF_CONNECTING_IP'] ?? null) : null) ?? $remote;
    }

    public static function isTrustedProxy(string $ip): bool
    {
        return self::inAnyRange($ip, self::trustedRanges());
    }

    /** Pour les tests : relit TRUSTED_PROXIES et TRUST_CF_CONNECTING_IP au prochain appel. */
    public static function resetTrustedProxies(): void
    {
        self::$trusted = null;
        self::$readsCfConnectingIp = null;
    }

    /** CF-Connecting-IP lu sauf si TRUST_CF_CONNECTING_IP vaut 0, false, off ou no. */
    private static function readsCfConnectingIp(): bool
    {
        if (self::$readsCfConnectingIp !== null) {
            return self::$readsCfConnectingIp;
        }
        $raw = $_ENV['TRUST_CF_CONNECTING_IP'] ?? getenv('TRUST_CF_CONNECTING_IP');
        $value = is_string($raw) ? strtolower(trim($raw)) : '';

        return self::$readsCfConnectingIp = !in_array($value, ['0', 'false', 'off', 'no'], true);
    }

    /** Adresse valide (IPv4 dans une IPv6 mappée ramenée en IPv4), sinon null. */
    private static function normalize(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $ip = trim($value);
        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }
        $bin = inet_pton($ip);
        if ($bin !== false && strlen($bin) === 16 && str_starts_with($bin, str_repeat("\0", 10) . "\xff\xff")) {
            return (string) inet_ntop(substr($bin, 12));
        }

        return $ip;
    }

    /** @return list<array{0: string, 1: int}> */
    private static function trustedRanges(): array
    {
        if (self::$trusted !== null) {
            return self::$trusted;
        }
        $entries = ['127.0.0.0/8', '::1/128'];
        $raw = $_ENV['TRUSTED_PROXIES'] ?? getenv('TRUSTED_PROXIES');
        if (is_string($raw) && trim($raw) !== '') {
            $entries = array_merge($entries, explode(',', $raw));
        }

        return self::$trusted = self::parseRanges($entries);
    }

    /**
     * @param list<string> $entries adresses ou CIDR ; les entrées illisibles sont ignorées
     * @return list<array{0: string, 1: int}>
     */
    private static function parseRanges(array $entries): array
    {
        $ranges = [];
        foreach ($entries as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }
            [$addr, $bits] = str_contains($entry, '/') ? explode('/', $entry, 2) : [$entry, null];
            $bin = @inet_pton(trim($addr));
            if ($bin === false) {
                continue;
            }
            $max = strlen($bin) * 8;
            $len = $bits === null ? $max : (ctype_digit(trim($bits)) ? (int) trim($bits) : -1);
            if ($len < 0 || $len > $max) {
                continue;
            }
            $ranges[] = [$bin, $len];
        }

        return $ranges;
    }

    /** @param list<array{0: string, 1: int}> $ranges */
    private static function inAnyRange(string $ip, array $ranges): bool
    {
        $bin = @inet_pton($ip);
        if ($bin === false) {
            return false;
        }
        foreach ($ranges as [$net, $bits]) {
            if (strlen($net) === strlen($bin) && self::inRange($bin, $net, $bits)) {
                return true;
            }
        }

        return false;
    }

    private static function inRange(string $ip, string $net, int $bits): bool
    {
        $bytes = intdiv($bits, 8);
        if ($bytes > 0 && strncmp($ip, $net, $bytes) !== 0) {
            return false;
        }
        $rest = $bits % 8;
        if ($rest === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $rest)) & 0xFF;

        return (ord($ip[$bytes]) & $mask) === (ord($net[$bytes]) & $mask);
    }
}
