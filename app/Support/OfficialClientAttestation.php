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
 * Depuis COMSPEC Link 2.0.125, un secret par version : HMAC(COMSPEC_CLIENT_ATTEST_MASTER, version). Le maître ne
 * quitte jamais le serveur ni la machine de build ; le module publié ne porte que le secret de sa version et
 * l’annonce (« v2:2.0.125 »). Le plancher de version (GameClientVersionGate, réglage d’administration) s’applique
 * à cette version prouvée, plus seulement à celle du User-Agent : relever le plancher périme d’un coup les secrets
 * de toutes les versions plus anciennes, sans toucher au serveur ni couper les joueurs à jour.
 * L’ancien secret unique (COMSPEC_CLIENT_ATTEST_SECRET, « ow1 », Link 2.0.124 et avant) reste accepté tant que le
 * plancher est sous 2.0.125.
 *
 * Limite : quelqu’un qui extrait le secret du module publié peut le réutiliser, jusqu’à ce que le plancher
 * dépasse cette version. Il doit recommencer l’extraction à chaque version.
 */
final class OfficialClientAttestation
{
    /** Ancien secret unique (Link 2.0.124 et avant). */
    public const KEY_ID = 'ow1';

    /** Secret par version : identifiant « v2:<version> », en-têtes X-Comspec-Client-Key2 / -Proof2. */
    public const KEY_PREFIX = 'v2:';

    /** Première version de COMSPEC Link à secret par version : plancher à partir duquel « ow1 » est refusé. */
    public const FIRST_VERSIONED = '2.0.125';

    public const SKEW_SECONDS = 180;

    /** @var int|null Horloge figée (tests). */
    private static ?int $nowForTests = null;

    public static function setNowForTests(?int $unix): void
    {
        self::$nowForTests = $unix;
    }

    /** @var string|null Plancher figé (tests) ; null = réglage plateforme. */
    private static ?string $floorForTests = null;

    public static function setFloorForTests(?string $floor): void
    {
        self::$floorForTests = $floor;
    }

    /** Secret (32 octets) de la version donnée. Même calcul dans tools/embed-client-attest.php. */
    public static function versionSecret(string $masterBinary, string $version): string
    {
        return hash_hmac('sha256', "COMSPEC-CLIENT-KEY-V2\n" . $version, $masterBinary, true);
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

    /** off : ni maître ni ancien secret. ready : 32 octets. broken : une valeur présente mais inutilisable (tout est refusé). */
    public static function secretState(): string
    {
        $legacy = self::envHex('COMSPEC_CLIENT_ATTEST_SECRET');
        $master = self::envHex('COMSPEC_CLIENT_ATTEST_MASTER');
        if ($legacy === '' && $master === '') {
            return 'off';
        }
        foreach ([$legacy, $master] as $hex) {
            if ($hex !== '' && !preg_match('/^[0-9a-fA-F]{64}$/', $hex)) {
                return 'broken';
            }
        }

        return 'ready';
    }

    private static function envHex(string $name): string
    {
        return trim((string) (($_ENV[$name] ?? null) ?: (getenv($name) ?: '')));
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
        $keyId2 = trim((string) ($_SERVER['HTTP_X_COMSPEC_CLIENT_KEY2'] ?? ''));
        $proof2 = strtolower(trim((string) ($_SERVER['HTTP_X_COMSPEC_CLIENT_PROOF2'] ?? '')));
        if ($keyId === '' && $ts === '' && $nonce === '' && $proof === '' && $keyId2 === '' && $proof2 === '') {
            return 'absent';
        }
        if (!preg_match('/^[0-9]{10,12}$/', $ts) || !preg_match('/^[A-Za-z0-9]{16,128}$/', $nonce)) {
            return 'invalid';
        }
        $now = self::$nowForTests ?? time();
        if (abs($now - (int) $ts) > self::SKEW_SECONDS) {
            return 'invalid';
        }
        $master = @hex2bin(self::envHex('COMSPEC_CLIENT_ATTEST_MASTER'));
        $hasMaster = is_string($master) && strlen($master) === 32;
        $floor = $hasMaster ? self::floor() : '';

        // Preuve par version : elle décide dès qu'elle est présentée et que le maître est posé. Sans maître, le
        // serveur ne sait pas la vérifier : l'ancienne preuve, envoyée à côté par le module, décide comme avant.
        if ($hasMaster && ($keyId2 !== '' || $proof2 !== '')) {
            if (!preg_match('/^v2:(\d+(?:\.\d+){0,3})$/', $keyId2, $m) || !preg_match('/^[0-9a-f]{64}$/', $proof2)) {
                return 'invalid';
            }
            $version = $m[1];
            if (GameClientVersionGate::normalizeVersion($version) !== $version) {
                return 'invalid';
            }
            // Plancher appliqué à la version prouvée : le secret d'une version retirée ne sert plus.
            if ($floor !== '' && GameClientVersionGate::compareVersions($version, $floor) < 0) {
                return 'invalid';
            }

            return self::proofMatches($keyId2, $ts, $nonce, $proof2, self::versionSecret($master, $version));
        }

        if ($keyId !== self::KEY_ID || !preg_match('/^[0-9a-f]{64}$/', $proof)) {
            return 'invalid';
        }
        // Ancien secret unique : retiré dès que le plancher atteint la première version à secret par version.
        if ($floor !== '' && GameClientVersionGate::compareVersions($floor, self::FIRST_VERSIONED) >= 0) {
            return 'invalid';
        }
        $secret = @hex2bin(self::envHex('COMSPEC_CLIENT_ATTEST_SECRET'));
        if (!is_string($secret) || strlen($secret) !== 32) {
            return 'invalid';
        }

        return self::proofMatches($keyId, $ts, $nonce, $proof, $secret);
    }

    private static function proofMatches(string $keyId, string $ts, string $nonce, string $proof, string $secret): string
    {
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

    /** Plancher de version des DLL (« » : aucun). Base injoignable : dernier plancher connu, sinon aucun. */
    private static function floor(): string
    {
        if (self::$floorForTests !== null) {
            return self::$floorForTests;
        }
        try {
            return (string) (GameClientVersionGate::floor()['link'] ?? '');
        } catch (\Throwable) {
            return '';
        }
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
