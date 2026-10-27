<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Database;
use App\Core\Response;
use PDO;

/**
 * Plancher de version des DLL du jeu (SEC-001).
 *
 * Les DLL antérieures au correctif (COMSPEC Link 2.0.95 et avant, DLL native « 1.0.0-native ») joignent les
 * secrets Athena (clé communauté, Bearer, jeton de session, SteamID) à tout hôte qu'un script leur demande.
 * Une fois la version corrigée publiée sur le Workshop, le réglage plateforme « game_min_link_version » fait
 * refuser ces DLL par Athena, avec un message qui demande la mise à jour. Réglage vide (défaut) : rien n'est bloqué.
 *
 * Règle, appliquée à un seul endroit (public/index.php, avant la maintenance, la session et la base) :
 *  - seule une requête dont le User-Agent annonce une DLL COMSPEC est concernée : « COMSPECExtension/<version> »
 *    (COMSPEC Link) ou « COMSPECATAKNativeExtension/<version> » (DLL native). Navigateurs, outils, robots,
 *    intégrations et requêtes sans User-Agent passent toujours ;
 *  - version : celle du User-Agent, suffixes ignorés (« 1.0.0-native » = 1.0.0, « 2.0.96+abc » = 2.0.96) ; absente
 *    ou illisible : champ « extension_version » de la requête ; toujours illisible : refus (une DLL corrigée
 *    annonce toujours un numéro qui commence par des chiffres) ;
 *  - les deux DLL sont comparées au même plancher : la DLL native est compilée depuis le même code que COMSPEC Link
 *    (Extension.cs), elle doit donc annoncer 2.0.96 ou plus ; les DLL natives déjà publiées (1.0.0-native) sont refusées ;
 *  - /api/atak/version reste ouvert : les anciens mods y lisent la dernière version publiée.
 *
 * Réponse de refus (refusal()) :
 *  - 426 « MOD_OUTDATED » sur l'ouverture de session (Game Auth, liaison par code, reprise de session) : même forme
 *    que le refus de version minimale de communauté (GameAuthService), que les mods 2.4.0 affichent déjà
 *    (« mod trop ancien, mettez-le à jour sur Steam ») ;
 *  - 403 « unauthorized » + code « MOD_OUTDATED » + Retry-After sur tout le reste (sondages, envois) : « unauthorized »
 *    est un refus définitif pour la DLL 2.0.95 (Extension_RefusalLatch), qui cesse alors de sonder et d'envoyer
 *    au lieu de boucler sur un 426 qu'elle ne sait pas interpréter.
 */
final class GameClientVersionGate
{
    /** Réglage plateforme : version minimale de la DLL (« 2.0.96 ») ; vide = aucun blocage. */
    public const SETTING_MIN_LINK_VERSION = 'game_min_link_version';

    /** Réglage plateforme facultatif : version COMSPEC ATAK citée aux joueurs refusés (« 2.4.1 »). */
    public const SETTING_MIN_ATAK_VERSION = 'game_min_atak_version';

    public const PRODUCT_LINK = 'COMSPECExtension';
    public const PRODUCT_NATIVE = 'COMSPECATAKNativeExtension';

    public const ERROR_CODE = 'MOD_OUTDATED';

    /** Pause demandée sur les chemins d'exploitation (plafond de l'échelle d'attente des DLL). */
    public const RETRY_AFTER_SEC = 600;

    /** Durée de vie du plancher en cache fichier : aucune requête SQL entre deux relectures. */
    public const CACHE_TTL_SEC = 30;

    /** Chemins (après /api/) jamais refusés : information de mise à jour lue par les anciens mods. */
    private const EXEMPT_PATHS = ['atak/version'];

    /**
     * Ouverture de session (après /api/) : 426. Un préfixe terminé par « / » couvre tout le dossier ;
     * sinon le chemin exact et ses sous-chemins.
     */
    private const SESSION_PATHS = ['game/v1/', 'atak/pair/', 'atak/recovery/', 'atak/game-link/', 'atak/session-restore'];

    /** Plafond du corps relu pour le repli « extension_version » (jamais un envoi de fichier). */
    private const FALLBACK_BODY_MAX_BYTES = 65536;

    /** @var array{link: string, atak: string}|null Plancher déjà lu pendant la requête en cours. */
    private static ?array $memo = null;

    /**
     * Point d'entrée du front controller : réponse de refus à envoyer telle quelle, ou null pour laisser passer.
     * Une requête qui n'annonce pas de DLL COMSPEC ne lit ni le cache ni la base.
     *
     * @param array<string, mixed> $server $_SERVER
     * @param array{link: string, atak: string}|null $floor plancher imposé (tests, outil CLI) ; null = réglage plateforme
     */
    public static function check(string $path, array $server, ?array $floor = null): ?Response
    {
        if (self::isExemptPath($path)) {
            return null;
        }
        $userAgent = (string) ($server['HTTP_USER_AGENT'] ?? '');
        $client = self::parseUserAgent($userAgent);
        if ($client === null) {
            return null;
        }
        $floor ??= self::floor();
        if (($floor['link'] ?? '') === '') {
            return null;
        }
        $fallback = $client['version'] === null ? self::fallbackVersion($server) : '';
        $decision = self::decide($userAgent, $fallback, (string) $floor['link']);
        if (!$decision['blocked']) {
            return null;
        }

        return self::refusal($decision, $path, (string) ($floor['atak'] ?? ''));
    }

    /**
     * Décision pure (sans base ni fichier).
     *
     * reason : not_game_client (pas une DLL COMSPEC), no_floor (plancher vide), invalid_floor (plancher illisible :
     * rien n'est bloqué), up_to_date, outdated, unreadable_version (DLL dont la version est illisible : refusée).
     *
     * @return array{blocked: bool, reason: string, product: ?string, version: ?string, min: string}
     */
    public static function decide(string $userAgent, string $fallbackVersion, string $minLinkVersion): array
    {
        $client = self::parseUserAgent($userAgent);
        if ($client === null) {
            return ['blocked' => false, 'reason' => 'not_game_client', 'product' => null, 'version' => null, 'min' => ''];
        }
        $product = $client['product'];
        $version = $client['version'] ?? self::normalizeVersion($fallbackVersion);
        $minRaw = trim($minLinkVersion);
        if ($minRaw === '') {
            return ['blocked' => false, 'reason' => 'no_floor', 'product' => $product, 'version' => $version, 'min' => ''];
        }
        $min = self::normalizeFloor($minRaw);
        if ($min === null || $min === '') {
            return ['blocked' => false, 'reason' => 'invalid_floor', 'product' => $product, 'version' => $version, 'min' => ''];
        }
        if ($version === null) {
            return ['blocked' => true, 'reason' => 'unreadable_version', 'product' => $product, 'version' => null, 'min' => $min];
        }
        if (self::compareVersions($version, $min) < 0) {
            return ['blocked' => true, 'reason' => 'outdated', 'product' => $product, 'version' => $version, 'min' => $min];
        }

        return ['blocked' => false, 'reason' => 'up_to_date', 'product' => $product, 'version' => $version, 'min' => $min];
    }

    /**
     * DLL COMSPEC annoncée par le User-Agent : produit canonique et version normalisée (null si absente ou illisible).
     * Null si le User-Agent n'annonce aucune DLL COMSPEC (navigateur, outil, robot, intégration, en-tête absent).
     *
     * @return array{product: string, version: ?string}|null
     */
    public static function parseUserAgent(string $userAgent): ?array
    {
        $userAgent = trim($userAgent);
        if ($userAgent === '' || stripos($userAgent, 'COMSPEC') === false) {
            return null;
        }
        foreach (preg_split('/\s+/', $userAgent) ?: [] as $token) {
            $slash = strpos($token, '/');
            $name = $slash === false ? $token : substr($token, 0, $slash);
            foreach ([self::PRODUCT_LINK, self::PRODUCT_NATIVE] as $product) {
                if (strcasecmp($name, $product) === 0) {
                    $raw = $slash === false ? '' : substr($token, $slash + 1);

                    return ['product' => $product, 'version' => self::normalizeVersion($raw)];
                }
            }
        }

        return null;
    }

    /**
     * Version annoncée par une DLL : numéro en tête (1 à 4 nombres séparés par des points), suffixes ignorés
     * (« -native », « +sha », « -rc1 »…), « v » initial toléré. Null si elle ne commence pas par un nombre.
     */
    public static function normalizeVersion(string $raw): ?string
    {
        $v = trim($raw);
        $plus = strpos($v, '+');
        if ($plus !== false) {
            $v = substr($v, 0, $plus);
        }
        $v = trim($v);
        if ($v !== '' && ($v[0] === 'v' || $v[0] === 'V')) {
            $v = substr($v, 1);
        }
        if (preg_match('/^\d+(?:\.\d+)*/', $v, $m) !== 1) {
            return null;
        }

        return self::joinNumericParts($m[0]);
    }

    /**
     * Plancher saisi (CLI, administration) : « 2.0.96 » (1 à 4 nombres, « v » initial toléré), sans suffixe.
     * Chaîne vide = aucun plancher. Null si la valeur n'est pas un numéro de version.
     */
    public static function normalizeFloor(string $raw): ?string
    {
        $v = trim($raw);
        if ($v === '') {
            return '';
        }
        if ($v[0] === 'v' || $v[0] === 'V') {
            $v = substr($v, 1);
        }
        if (preg_match('/^\d+(?:\.\d+)*$/', $v) !== 1) {
            return null;
        }

        return self::joinNumericParts($v);
    }

    /** Comparaison numérique, composantes absentes = 0 (« 2.0.96 » = « 2.0.96.0 », contrairement à version_compare). */
    public static function compareVersions(string $a, string $b): int
    {
        $pa = array_map('intval', explode('.', $a));
        $pb = array_map('intval', explode('.', $b));
        $n = max(count($pa), count($pb));
        for ($i = 0; $i < $n; $i++) {
            $cmp = ($pa[$i] ?? 0) <=> ($pb[$i] ?? 0);
            if ($cmp !== 0) {
                return $cmp;
            }
        }

        return 0;
    }

    /** Chemin jamais refusé (information de mise à jour). */
    public static function isExemptPath(string $path): bool
    {
        return self::matchesApiPath($path, self::EXEMPT_PATHS);
    }

    /** Chemin d'ouverture de session : refus en 426 (sinon 403). */
    public static function isSessionPath(string $path): bool
    {
        return self::matchesApiPath($path, self::SESSION_PATHS);
    }

    /**
     * Réponse de refus. Aucun mot-clé que les anciennes DLL associent à un autre refus (« tenant », « steam_… »,
     * « maintenance », « account_disabled »…) ne doit apparaître dans le corps : client-init le cherche en sous-chaîne.
     *
     * @param array{product?: ?string, version?: ?string, min?: string} $decision
     */
    public static function refusal(array $decision, string $path, string $minAtakVersion = ''): Response
    {
        $session = self::isSessionPath($path);
        $response = Response::json(self::payload($decision, $minAtakVersion, $session), $session ? 426 : 403);
        $response->header('Cache-Control', 'no-store')
            ->header('X-Content-Type-Options', 'nosniff');
        if (!$session) {
            $response->header('Retry-After', (string) self::RETRY_AFTER_SEC);
        }

        return $response;
    }

    /**
     * Corps JSON du refus. « error » en premier : la DLL 2.0.95 lit le premier champ « error » pour décider du verrou.
     * min_mod_version / detected_mod_version sont lus par les mods (GameAuth : CaptureVersionHints) et affichés par
     * COMSPEC Link « Pack actuel : <detected_mod_version> — version exigée : <min_mod_version> » : on y met les numéros
     * de DLL des deux côtés (2.0.95 / 2.0.96), seuls comparables ; le numéro ATAK (2.4.1) est dans le message et
     * dans min_atak_version. detected_mod_version est omis si la version est illisible (le mod garde la sienne).
     *
     * @param array{product?: ?string, version?: ?string, min?: string} $decision
     * @return array<string, mixed>
     */
    public static function payload(array $decision, string $minAtakVersion, bool $session): array
    {
        $minLink = (string) ($decision['min'] ?? '');
        $detected = (string) ($decision['version'] ?? '');
        $minAtak = self::normalizeFloor($minAtakVersion) ?? '';
        $messages = self::messages($minLink, $minAtak);
        $payload = [
            'error' => $session ? self::ERROR_CODE : 'unauthorized',
            'code' => self::ERROR_CODE,
            'message' => $messages['en'],
            'message_fr' => $messages['fr'],
            'update_required' => true,
            'min_mod_version' => $minLink,
        ];
        if ($detected !== '') {
            $payload['detected_mod_version'] = $detected;
        }
        $payload['min_link_version'] = $minLink;
        $payload['detected_link_version'] = $detected;
        if ($minAtak !== '') {
            $payload['min_atak_version'] = $minAtak;
        }
        $payload['client_product'] = (string) ($decision['product'] ?? '');

        return $payload;
    }

    /**
     * Message au joueur (anglais par défaut, français à côté).
     *
     * @return array{en: string, fr: string}
     */
    public static function messages(string $minLinkVersion, string $minAtakVersion = ''): array
    {
        $target = $minAtakVersion !== '' ? $minAtakVersion : trim('COMSPEC Link ' . $minLinkVersion);

        return [
            'en' => 'COMSPEC ATAK is out of date: update it from the Steam Workshop (' . $target . ' or later).',
            'fr' => 'COMSPEC ATAK n\'est plus à jour : mettez-le à jour depuis le Workshop Steam (' . $target . ' ou plus récent).',
        ];
    }

    /**
     * Plancher en vigueur : cache fichier de CACHE_TTL_SEC secondes, puis réglage plateforme.
     * Base injoignable : dernier plancher connu, sinon aucun blocage.
     *
     * @return array{link: string, atak: string}
     */
    public static function floor(): array
    {
        if (self::$memo === null) {
            self::$memo = self::loadFloor(
                self::cacheFile(),
                static fn (): array => self::readFloorFromDatabase(Database::getPdo())
            );
        }

        return self::$memo;
    }

    /**
     * @param callable(): array{link: string, atak: string} $readDatabase
     * @return array{link: string, atak: string}
     */
    public static function loadFloor(?string $cacheFile, callable $readDatabase, ?int $now = null): array
    {
        $now ??= time();
        $cached = null;
        if ($cacheFile !== null) {
            // Date du fichier toujours relue (le cache stat de PHP garderait celle d'un appel précédent du processus).
            clearstatcache(true, $cacheFile);
            $mtime = @filemtime($cacheFile);
            if ($mtime !== false) {
                $cached = self::decodeFloor(@file_get_contents($cacheFile));
                if ($cached !== null && abs($now - $mtime) < self::CACHE_TTL_SEC) {
                    return $cached;
                }
            }
        }
        try {
            $floor = $readDatabase();
        } catch (\Throwable) {
            if ($cached !== null && $cacheFile !== null) {
                // Base injoignable : le dernier plancher connu reste en vigueur, relu dans CACHE_TTL_SEC.
                @touch($cacheFile);
            }

            return $cached ?? ['link' => '', 'atak' => ''];
        }
        if ($cacheFile !== null) {
            try {
                self::writeCache($cacheFile, $floor);
            } catch (\Throwable) {
                // Cache facultatif : la requête suivante relira la base.
            }
        }

        return $floor;
    }

    /**
     * Réglages plateforme (une requête). Une valeur qui n'est pas un numéro de version compte comme vide.
     *
     * @return array{link: string, atak: string}
     */
    public static function readFloorFromDatabase(PDO $pdo): array
    {
        $raw = self::readRawSettings($pdo);

        return [
            'link' => self::normalizeFloor($raw[self::SETTING_MIN_LINK_VERSION] ?? '') ?? '',
            'atak' => self::normalizeFloor($raw[self::SETTING_MIN_ATAK_VERSION] ?? '') ?? '',
        ];
    }

    /**
     * Valeurs brutes des deux réglages (absent = clé absente du tableau).
     *
     * @return array<string, string>
     */
    public static function readRawSettings(PDO $pdo): array
    {
        $st = $pdo->prepare('SELECT setting_key, value FROM platform_settings WHERE setting_key IN (?, ?)');
        $st->execute([self::SETTING_MIN_LINK_VERSION, self::SETTING_MIN_ATAK_VERSION]);
        $out = [];
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $out[(string) $row['setting_key']] = (string) ($row['value'] ?? '');
        }

        return $out;
    }

    /**
     * Après une modification du plancher (CLI, administration) : le cache est retiré, la requête suivante relit
     * la base. Le fichier n'est jamais écrit par la CLI (root) : seul PHP-FPM le recrée, avec ses droits.
     */
    public static function forgetCache(): void
    {
        self::$memo = null;
        $file = self::cachePath();
        if ($file !== null && is_file($file)) {
            @unlink($file);
        }
    }

    /** Fichier de cache du plancher (null si les helpers de l'application ne sont pas chargés). */
    public static function cachePath(): ?string
    {
        return function_exists('base_path') ? base_path('storage/cache/atak-gate/client-version-floor.json') : null;
    }

    /** Cache utilisé par floor() : désactivé en CLI (lecture directe, aucun fichier créé avec les droits root). */
    private static function cacheFile(): ?string
    {
        return PHP_SAPI === 'cli' ? null : self::cachePath();
    }

    /**
     * @return array{link: string, atak: string}|null
     */
    private static function decodeFloor(string|false $raw): ?array
    {
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || !is_string($data['link'] ?? null) || !is_string($data['atak'] ?? null)) {
            return null;
        }

        return ['link' => self::normalizeFloor($data['link']) ?? '', 'atak' => self::normalizeFloor($data['atak']) ?? ''];
    }

    /** @param array{link: string, atak: string} $floor */
    private static function writeCache(string $file, array $floor): void
    {
        $dir = dirname($file);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return;
        }
        $json = json_encode(['link' => $floor['link'], 'atak' => $floor['atak']]);
        if (!is_string($json)) {
            return;
        }
        // Écriture atomique : un lecteur concurrent voit l'ancien fichier ou le nouveau, jamais un fichier à moitié écrit.
        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, $json) === false || !@rename($tmp, $file)) {
            @unlink($tmp);
        }
    }

    /**
     * Repli quand le User-Agent ne donne pas de version lisible : « extension_version » de la query string,
     * puis du corps JSON (petit, jamais multipart). Ne sert qu'aux DLL déjà identifiées par leur User-Agent.
     *
     * @param array<string, mixed> $server
     */
    private static function fallbackVersion(array $server): string
    {
        $query = [];
        parse_str((string) ($server['QUERY_STRING'] ?? ''), $query);
        $fromQuery = $query['extension_version'] ?? '';
        if (is_string($fromQuery) && trim($fromQuery) !== '') {
            return trim($fromQuery);
        }
        $method = strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET'));
        if (!in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            return '';
        }
        $raw = HttpJsonBody::rawJson(self::FALLBACK_BODY_MAX_BYTES);
        $body = $raw !== '' ? json_decode($raw, true) : null;
        if (!is_array($body)) {
            return '';
        }
        foreach (['extension_version', 'dll_version'] as $key) {
            $v = $body[$key] ?? '';
            if (is_string($v) && trim($v) !== '') {
                return trim($v);
            }
        }

        return '';
    }

    /** « 02.0.096 » → « 2.0.96 » ; null au-delà de 4 composantes ou de 9 chiffres par composante. */
    private static function joinNumericParts(string $dotted): ?string
    {
        $parts = explode('.', $dotted);
        if (count($parts) > 4) {
            return null;
        }
        foreach ($parts as $i => $part) {
            if ($part === '' || strlen($part) > 9) {
                return null;
            }
            $parts[$i] = (string) (int) $part;
        }

        return implode('.', $parts);
    }

    /**
     * @param list<string> $prefixes chemins après /api/ ; « x/ » = tout le dossier, sinon chemin exact et sous-chemins
     */
    private static function matchesApiPath(string $path, array $prefixes): bool
    {
        $path = strtolower((string) parse_url($path, PHP_URL_PATH));
        $at = strpos($path, '/api/');
        if ($at === false) {
            return false;
        }
        $rel = substr($path, $at + 5);
        foreach ($prefixes as $prefix) {
            if (str_ends_with($prefix, '/')) {
                if (str_starts_with($rel, $prefix)) {
                    return true;
                }
            } elseif ($rel === $prefix || str_starts_with($rel, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }
}
