<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Request;
use App\Core\Response;

/**
 * Preuve que la requête vient du module officiel, pas d’une copie ou d’un autre programme.
 *
 * La clé d’accès prouve la communauté. Elle ne prouve pas le programme : une copie qui a
 * récupéré cette clé parle au poste comme le module officiel. Ici, chaque requête du jeu
 * porte une empreinte calculée avec un secret connu seulement du module officiel publié
 * et du serveur. Le secret n’est pas dans le dépôt : il est injecté à la compilation
 * (ClientAttestMaterial.Local.cs, ignoré par Git) et lu ici depuis COMSPEC_CLIENT_ATTEST_SECRET.
 *
 * Tant que ce secret est vide, rien ne change (les joueurs déjà en ligne ne sont pas coupés
 * avant la publication du pack). Dès qu’il est posé, une requête du jeu sans preuve valable
 * est refusée. Une session du portail (navigateur) reste acceptée : la carte du poste
 * n’est pas un module de jeu.
 *
 * Limite : quelqu’un qui extrait le secret du module publié peut le réutiliser. Changer le
 * secret et republier le pack coupe cette copie jusqu’à une nouvelle extraction.
 */
final class OfficialClientAttestation
{
    public const KEY_ID = 'ow1';

    public const SKEW_SECONDS = 180;

    /** @var int|null Horloge figée (tests). */
    private static ?int $nowForTests = null;

    public static function setNowForTests(?int $unix): void
    {
        self::$nowForTests = $unix;
    }

    /**
     * Masque partagé avec le module (OfficialClientProof.cs). Ce n’est pas le secret :
     * il empêche seulement que le secret apparaisse en clair dans le fichier généré.
     *
     * @return list<int>
     */
    public static function clientMask(): array
    {
        return [
            0xC3, 0x17, 0x9A, 0x4E, 0x62, 0xD1, 0x08, 0xB7,
            0x55, 0xE4, 0x2C, 0x91, 0x7F, 0x0A, 0xD8, 0x36,
            0xAB, 0x49, 0x13, 0xF0, 0x6C, 0x88, 0x21, 0xDE,
            0x04, 0x5B, 0xC9, 0x77, 0xA2, 0x1E, 0x90, 0x3D,
        ];
    }

    /**
     * Octets à écrire dans le module (secret XOR masque).
     *
     * @return list<int>
     */
    public static function xorPack(string $secretBinary): array
    {
        $mask = self::clientMask();
        $maskLen = count($mask);
        $out = [];
        $len = strlen($secretBinary);
        for ($i = 0; $i < $len; $i++) {
            $out[] = ord($secretBinary[$i]) ^ $mask[$i % $maskLen];
        }

        return $out;
    }

    public static function canonical(string $method, string $pathAndQuery, string $keyId, string $ts, string $nonce): string
    {
        return 'COMSPEC-CLIENT-V1' . "\n"
            . $keyId . "\n"
            . strtoupper($method) . "\n"
            . $pathAndQuery . "\n"
            . $ts . "\n"
            . $nonce . "\n";
    }

    /** off : secret absent. ready : 32 octets. broken : valeur présente mais inutilisable (tout est refusé). */
    public static function secretState(): string
    {
        $hex = trim((string) (($_ENV['COMSPEC_CLIENT_ATTEST_SECRET'] ?? null) ?: (getenv('COMSPEC_CLIENT_ATTEST_SECRET') ?: '')));
        if ($hex === '') {
            return 'off';
        }
        if (!preg_match('/^[0-9a-fA-F]{64}$/', $hex)) {
            return 'broken';
        }

        return 'ready';
    }

    public static function pathRequiresProof(string $path): bool
    {
        if (self::secretState() === 'off') {
            return false;
        }
        if (self::isPublicPath($path)) {
            return false;
        }
        if (ComspecApiKeyAuth::pathRequiresProtection($path)) {
            return true;
        }

        return self::isGameBootstrap($path);
    }

    /**
     * valid : preuve bonne. absent : pas d’en-têtes. invalid : en-têtes faux, périmés ou secret serveur inutilisable.
     */
    public static function presentedStatus(): string
    {
        if (self::secretState() === 'broken') {
            return 'invalid';
        }
        $keyId = trim((string) ($_SERVER['HTTP_X_COMSPEC_CLIENT_KEY'] ?? ''));
        $ts = trim((string) ($_SERVER['HTTP_X_COMSPEC_CLIENT_TS'] ?? ''));
        $nonce = trim((string) ($_SERVER['HTTP_X_COMSPEC_CLIENT_NONCE'] ?? ''));
        $proof = strtolower(trim((string) ($_SERVER['HTTP_X_COMSPEC_CLIENT_PROOF'] ?? '')));
        if ($keyId === '' && $ts === '' && $nonce === '' && $proof === '') {
            return 'absent';
        }
        if ($keyId !== self::KEY_ID
            || !preg_match('/^[0-9]{10,12}$/', $ts)
            || !preg_match('/^[A-Za-z0-9]{16,128}$/', $nonce)
            || !preg_match('/^[0-9a-f]{64}$/', $proof)) {
            return 'invalid';
        }
        $now = self::$nowForTests ?? time();
        if (abs($now - (int) $ts) > self::SKEW_SECONDS) {
            return 'invalid';
        }
        $secret = hex2bin((string) (($_ENV['COMSPEC_CLIENT_ATTEST_SECRET'] ?? null) ?: getenv('COMSPEC_CLIENT_ATTEST_SECRET')));
        if (!is_string($secret) || strlen($secret) !== 32) {
            return 'invalid';
        }
        $expected = hash_hmac(
            'sha256',
            self::canonical(
                (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
                self::canonicalPathAndQuery(),
                $keyId,
                $ts,
                $nonce
            ),
            $secret
        );

        return hash_equals($expected, $proof) ? 'valid' : 'invalid';
    }

    public static function rejection(): Response
    {
        return Response::json([
            'error' => 'client_untrusted',
            'message' => 'Ce module n’est pas reconnu. Installez la version officielle du pack.',
            'message_fr' => 'Ce module n’est pas reconnu. Installez la version officielle du pack.',
        ], 401)->header('Cache-Control', 'no-store');
    }

    /** Chemin vu par le routeur, plus la query brute (sans réécriture). */
    public static function canonicalPathAndQuery(): string
    {
        $path = Request::normalizePathFromServer();
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $query = parse_url($uri, PHP_URL_QUERY);
        if (!is_string($query) || $query === '') {
            return $path;
        }

        return $path . '?' . $query;
    }

    private static function isPublicPath(string $path): bool
    {
        if (in_array($path, [
            '/api/atak/ping',
            '/api/atak/version',
            '/api/atak/whoami',
            '/api/atak/tiles',
            '/api/atak/map-photo/list',
            '/api/atak/map-photo/upload',
        ], true)) {
            return true;
        }
        if (str_starts_with($path, '/api/atak/phone-pairing/') && str_ends_with($path, '/qr.png')) {
            return true;
        }

        return str_starts_with($path, '/api/game/v1/branding/render');
    }

    /** Appels du module avant qu’il ait une clé (liaison, compte, signalement). */
    private static function isGameBootstrap(string $path): bool
    {
        if (in_array($path, [
            '/api/atak/startup-check',
            '/api/atak/beta-register',
            '/api/atak/mod-report',
        ], true)) {
            return true;
        }
        foreach ([
            '/api/atak/game-link/',
            '/api/atak/pair/',
            '/api/atak/recovery/',
            '/api/game/v1/auth/',
        ] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return $path === '/api/game/v1/session/restore' || $path === '/api/game/v1/session/refresh';
    }
}
