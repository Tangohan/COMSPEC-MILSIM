<?php

declare(strict_types=1);

namespace App\Support;

use App\Repositories\AtakDataRepository;
use PDO;

/**
 * Cloisonnement des parties de la communauté par défaut (tenants.slug = « default »).
 *
 * Tous les comptes sans communauté y écrivent : sans cloisonnement, chaque joueur recevait les pistes, marqueurs,
 * tracés et messages de parties sans rapport (autres serveurs, autres terrains). Les données ATAK sont toutes
 * rangées par (communauté, carte) : dans la communauté par défaut, la carte demandée par le client est donc
 * remplacée, côté serveur, par une carte réservée à la partie.
 *
 *  - Clé de partie : « mission@terrain#AAAAMMJJHHMM » (COMSPEC_ATAK_MissionKey, posée par le serveur de jeu et
 *    diffusée à ses joueurs ; le jeu l'envoie déjà dans chaque position). Même clé = même partie = même carte.
 *  - Sans clé de partie récente : carte réservée au compte (ses propres pistes, IA et balises seulement).
 *
 * Les communautés réelles ne sont pas concernées par les cartes réservées. Elles profitent seulement des filtres
 * de lecture des pistes (terrain différent, contacts relayés trop anciens).
 */
final class AtakPartyScope
{
    /** Au-delà, la clé de partie d'un compte n'est plus tenue pour sa partie en cours. Aligné sur la fenêtre de présence. */
    public const PARTY_FRESH_SECONDS = AtakDataRepository::UNIT_RECENT_WINDOW_SECONDS;

    /** Communauté par défaut : une piste muette depuis plus longtemps n'est plus servie (GET /api/units). */
    public const DEFAULT_SERVE_MAX_AGE = AtakDataRepository::UNIT_RECENT_WINDOW_SECONDS;

    /** Communauté réelle : contact relayé (IA alliée, balise, téléphone) muet depuis plus longtemps, plus servi. */
    public const PROXY_SERVE_MAX_AGE = 43200;

    /** Communauté par défaut : pistes sans signe de vie supprimées au-delà (tâche d'entretien et outil de rattrapage). */
    public const DEFAULT_PURGE_AFTER_SECONDS = 86400;

    /** Cartes réservées : au-dessus de toute carte choisie à la main (réglage du jeu : 1 à 99). */
    private const OWNER_MAP_BASE = 100_000_000;
    private const PARTY_MAP_BASE = 1_000_000_000;
    private const MAP_SPAN = 900_000_000;

    /** @var array{tenant: int, owner: string, key: string, from_request: bool, map_id: ?int}|null */
    private static ?array $requestScope = null;

    private static string $requestMemo = '';

    public static function resetForTests(): void
    {
        self::$requestScope = null;
        self::$requestMemo = '';
    }

    /**
     * Portée de la requête en cours, calculée une fois : compte, clé de partie et carte réservée (null hors de la
     * communauté par défaut). Commun à tous les contrôleurs ATAK : chacun remplace la carte du client par map_id.
     *
     * @param array<mixed> $body corps JSON déjà décodé de la requête
     * @return array{tenant: int, owner: string, key: string, from_request: bool, map_id: ?int}
     */
    public static function forRequest(int $tenantId, array $body, string $ip, ?PDO $pdo = null): array
    {
        $scope = ['tenant' => $tenantId, 'owner' => '', 'key' => '', 'from_request' => false, 'map_id' => null];
        try {
            $userId = ComspecApiKeyAuth::matchedUserId();
            if ($userId === null) {
                $sessionUser = (int) (\App\Core\Session::get('user_id') ?? 0);
                $userId = $sessionUser > 0 ? $sessionUser : null;
            }
            $steam = ComspecApiKeyAuth::matchedSteamId() ?? (string) ($_SERVER['HTTP_X_COMSPEC_STEAM'] ?? '');
            $owner = self::ownerKey($userId, $steam, trim((string) \App\Core\Session::get('atak_phone_pairing_token', '')), $ip);
            $scope['owner'] = $owner;
            $key = self::missionKeyFromBody($body);
            // Même communauté, même compte, même clé portée : déjà résolu pour cette requête (plusieurs contrôleurs).
            $memo = $tenantId . '|' . $owner . '|' . $key;
            if (self::$requestScope !== null && self::$requestMemo === $memo) {
                return self::$requestScope;
            }
            self::$requestMemo = $memo;
            $pdo ??= \App\Core\Database::getPdo();
            if (self::isDefaultCommunity($pdo, $tenantId)) {
                $scope['from_request'] = $key !== '';
                if ($key === '') {
                    $key = self::cachedPartyKey($pdo, $tenantId, $owner);
                }
                $scope['key'] = $key;
                $scope['map_id'] = self::mapIdFor($owner, $key);
            }
        } catch (\Throwable) {
            // Base indisponible : la requête échouera d'elle-même à sa première lecture.
        }

        return self::$requestScope = $scope;
    }

    /** Partie en cours d'un compte, relue en base toutes les 5 s au plus. */
    public static function cachedPartyKey(PDO $pdo, int $tenantId, string $owner): string
    {
        if ($owner === '') {
            return '';
        }

        return (string) ShortCache::remember(
            'party-key:' . $tenantId . ':' . sha1($owner),
            5,
            static fn (): string => self::currentPartyKey($pdo, $tenantId, $owner)
        );
    }

    public static function isDefaultCommunity(PDO $pdo, int $tenantId): bool
    {
        if ($tenantId < 1) {
            return false;
        }
        return ShortCache::remember('party-default-tenant:' . $tenantId, 60, static function () use ($pdo, $tenantId): int {
            try {
                $st = $pdo->prepare('SELECT slug FROM tenants WHERE id = ?');
                $st->execute([$tenantId]);

                return (string) ($st->fetchColumn() ?: '') === 'default' ? 1 : 0;
            } catch (\Throwable) {
                return 0;
            }
        }) === 1;
    }

    /** Identifiants des communautés par défaut (outil de rattrapage, entretien). @return list<int> */
    public static function defaultTenantIds(PDO $pdo): array
    {
        try {
            $st = $pdo->query("SELECT id FROM tenants WHERE slug = 'default'");

            return array_map('intval', $st ? ($st->fetchAll(PDO::FETCH_COLUMN) ?: []) : []);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Compte derrière la requête, lu côté serveur : compte du jeton de jeu ou de la session du site, sinon Steam
     * validé, sinon téléphone appairé, sinon adresse du client. Jamais vide : deux inconnus ne partagent pas de carte.
     */
    public static function ownerKey(?int $userId, ?string $steam, string $pairingToken, string $ip): string
    {
        if ($userId !== null && $userId > 0) {
            return 'u:' . $userId;
        }
        $steam = preg_replace('/\D/', '', (string) $steam) ?? '';
        if ($steam !== '') {
            return 's:' . $steam;
        }
        if ($pairingToken !== '') {
            return 'pair:' . substr(sha1($pairingToken), 0, 20);
        }

        return 'ip:' . substr(sha1($ip !== '' ? $ip : 'inconnu'), 0, 20);
    }

    /**
     * Clé de partie portée par le corps d'une requête du jeu : mission_key à la racine, dans extra, ou dans les
     * événements d'un lot de télémétrie. '' si absente ou mal formée.
     *
     * @param array<mixed> $body
     */
    public static function missionKeyFromBody(array $body, int $depth = 0): string
    {
        $direct = self::normalizeKey($body['mission_key'] ?? null);
        if ($direct !== '') {
            return $direct;
        }
        if ($depth >= 4) {
            return '';
        }
        foreach (['extra', 'data', 'events', 'vehicle'] as $child) {
            $value = $body[$child] ?? null;
            if (is_string($value) && $value !== '' && $value[0] === '{' && str_contains($value, 'mission_key')) {
                $value = json_decode($value, true);
            }
            if (!is_array($value)) {
                continue;
            }
            $items = array_is_list($value) ? array_slice($value, 0, 40) : [$value];
            foreach ($items as $item) {
                if (is_array($item)) {
                    $found = self::missionKeyFromBody($item, $depth + 1);
                    if ($found !== '') {
                        return $found;
                    }
                }
            }
        }

        return '';
    }

    public static function normalizeKey(mixed $value): string
    {
        $key = AtakMissionKey::normalize($value);
        // Clé du serveur de jeu attendue : « mission@terrain#horodatage ». Sans horodatage, deux serveurs qui jouent
        // la même mission sur le même terrain partageraient leur carte.
        if ($key === '' || !str_contains($key, '#')) {
            return '';
        }

        return $key;
    }

    /** Terrain (worldName) d'une clé de partie, en minuscules ; '' si illisible. */
    public static function worldOf(string $missionKey): string
    {
        $hash = strrpos($missionKey, '#');
        $base = $hash === false ? $missionKey : substr($missionKey, 0, $hash);
        $at = strrpos($base, '@');
        if ($at === false) {
            return '';
        }

        return self::normalizeWorld(substr($base, $at + 1));
    }

    public static function normalizeWorld(mixed $world): string
    {
        return is_scalar($world) ? strtolower((string) preg_replace('/[^A-Za-z0-9_.-]/', '', (string) $world)) : '';
    }

    /** Carte réservée : à la partie si sa clé est connue, sinon au compte. */
    public static function mapIdFor(string $owner, string $partyKey): int
    {
        if ($partyKey !== '') {
            return self::PARTY_MAP_BASE + (int) (hexdec(substr(sha1('party:' . $partyKey), 0, 12)) % self::MAP_SPAN);
        }

        return self::OWNER_MAP_BASE + (int) (hexdec(substr(sha1('owner:' . $owner), 0, 12)) % self::MAP_SPAN);
    }

    public static function isReservedMapId(int $mapId): bool
    {
        return $mapId >= self::OWNER_MAP_BASE;
    }

    /**
     * Marque une piste : compte qui l'a remontée, clé de partie, terrain, instant (horloge PHP, époque).
     *
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    public static function stampExtra(array $extra, string $owner, string $partyKey, bool $keyFromRequest, ?int $now = null): array
    {
        $extra['party_owner'] = $owner;
        $extra['party_at'] = $now ?? time();
        // « key » : clé lue dans la requête elle-même (position du joueur) ; « ctx » : héritée de sa partie en cours
        // (IA alliée, balise). Seule une clé « key » fait foi pour retrouver la partie en cours d'un compte.
        $extra['party_src'] = ($partyKey !== '' && $keyFromRequest) ? 'key' : 'ctx';
        if ($partyKey !== '') {
            $extra['party_key'] = $partyKey;
            $world = self::worldOf($partyKey);
            if ($world !== '') {
                $extra['party_world'] = $world;
            }
        } else {
            unset($extra['party_key'], $extra['party_world']);
        }

        return $extra;
    }

    /**
     * Marque une piste avant son enregistrement, d'après la portée de la requête : compte, clé de partie (celle de
     * la position, sinon la partie en cours du compte pour une IA alliée, une balise ou un téléphone), terrain.
     *
     * @param array{tenant: int, owner: string, key: string, from_request: bool, map_id: ?int} $scope
     * @param array<string, mixed> $extra
     * @param array<mixed> $body
     * @return array<string, mixed>
     */
    public static function stampForScope(array $scope, array $extra, array $body, bool $isProxy, ?PDO $pdo = null, ?int $now = null): array
    {
        $owner = $scope['owner'];
        if ($owner === '') {
            return $extra;
        }
        $key = self::normalizeKey($extra['mission_key'] ?? $body['mission_key'] ?? null);
        $fromRequest = $key !== '' && !$isProxy;
        if ($key === '' && $scope['map_id'] !== null) {
            $key = $scope['key'];
        } elseif ($key === '' && $isProxy) {
            try {
                $key = self::cachedPartyKey($pdo ?? \App\Core\Database::getPdo(), $scope['tenant'], $owner);
            } catch (\Throwable) {
                $key = '';
            }
        }

        return self::stampExtra($extra, $owner, $key, $fromRequest, $now);
    }

    /**
     * Pistes servies à un demandeur (GET /api/units), d'après la portée de sa requête :
     *  - communauté par défaut (carte réservée) : rien au-delà de DEFAULT_SERVE_MAX_AGE sans signe de vie ;
     *  - partout : contacts relayés muets depuis plus de PROXY_SERVE_MAX_AGE retirés, pistes d'un autre terrain
     *    retirées (terrain du demandeur : world_name envoyé, sinon celui de sa propre piste quand c'est le jeu) ;
     *  - les marques internes (compte) ne sont pas publiées.
     *
     * @param list<mixed> $rows
     * @param array{tenant: int, owner: string, key: string, from_request: bool, map_id: ?int} $scope
     * @return list<mixed>
     */
    public static function servedUnits(array $rows, array $scope, mixed $worldQuery, bool $gameClient, ?int $now = null): array
    {
        $viewerWorld = self::normalizeWorld($worldQuery);
        if ($viewerWorld === '' && $scope['key'] !== '') {
            $viewerWorld = self::worldOf($scope['key']);
        }
        if ($viewerWorld === '' && $gameClient) {
            $viewerWorld = self::viewerWorldFromRows($rows, $scope['owner'], $now);
        }

        return self::publicRows(self::filterUnits(
            $rows,
            $scope['map_id'] !== null ? self::DEFAULT_SERVE_MAX_AGE : null,
            $viewerWorld,
            $now
        ));
    }

    /**
     * Partie en cours d'un compte : clé de la piste la plus récente qu'il a remontée dans la communauté, si elle a
     * moins de PARTY_FRESH_SECONDS. '' sinon (aucune clé fiable : carte réservée au compte).
     */
    public static function currentPartyKey(PDO $pdo, int $tenantId, string $owner, ?int $now = null): string
    {
        if ($owner === '') {
            return '';
        }
        $now ??= time();
        try {
            // extra est une colonne JSON (MySQL en réécrit l'espacement) : préfiltre sur la valeur seule, contrôle exact en PHP.
            $st = $pdo->prepare(
                'SELECT extra FROM atak_units WHERE tenant_id = ? AND extra LIKE ? AND extra LIKE ?
                 ORDER BY updated_at DESC, id DESC LIMIT 40'
            );
            // $owner ne contient que des lettres, des chiffres et « : » (ownerKey) : rien à échapper pour LIKE.
            // Guillemet fermant : « u:28 » ne ramène pas les pistes de « u:287 ». Second motif : party_src = « key ».
            $st->execute([$tenantId, '%' . $owner . '"%', '%"key"%']);
            $bestAt = 0;
            $bestKey = '';
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) ?: [] as $raw) {
                $extra = AtakDataRepository::decodeExtra($raw);
                if ((string) ($extra['party_owner'] ?? '') !== $owner) {
                    continue;
                }
                $at = (int) ($extra['party_at'] ?? 0);
                $key = self::normalizeKey($extra['party_key'] ?? null);
                if ($key === '' || $at <= $bestAt || (string) ($extra['party_src'] ?? '') !== 'key') {
                    continue;
                }
                $bestAt = $at;
                $bestKey = $key;
            }

            return ($bestKey !== '' && ($now - $bestAt) <= self::PARTY_FRESH_SECONDS) ? $bestKey : '';
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Lecture des pistes (GET /api/units) : retire ce qui ne doit plus être servi.
     *  - $maxAge : âge maximal de toute piste (communauté par défaut), null = sans limite ;
     *  - contacts relayés (IA alliée, balise, téléphone) muets depuis plus de PROXY_SERVE_MAX_AGE : retirés partout ;
     *  - $viewerWorld : terrain du demandeur ; une piste dont le terrain est connu et différent est retirée.
     *
     * @param list<mixed> $rows
     * @return list<mixed>
     */
    public static function filterUnits(array $rows, ?int $maxAge, string $viewerWorld = '', ?int $now = null): array
    {
        $now ??= time();
        $viewerWorld = self::normalizeWorld($viewerWorld);
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                $out[] = $row;
                continue;
            }
            $extra = AtakDataRepository::decodeExtra($row['extra'] ?? null);
            $age = self::ageOf($row, $now);
            if ($maxAge !== null && ($age === null || $age > $maxAge)) {
                continue;
            }
            if ($age !== null && $age > self::PROXY_SERVE_MAX_AGE && self::isProxy($row, $extra)) {
                continue;
            }
            if ($viewerWorld !== '') {
                $rowWorld = self::rowWorld($extra);
                if ($rowWorld !== '' && $rowWorld !== $viewerWorld) {
                    continue;
                }
            }
            $out[] = $row;
        }

        return $out;
    }

    /**
     * Terrain du demandeur déduit de sa propre piste (la plus récente, encore fraîche) ; '' si inconnu.
     *
     * @param list<mixed> $rows
     */
    public static function viewerWorldFromRows(array $rows, string $owner, ?int $now = null): string
    {
        if ($owner === '') {
            return '';
        }
        $now ??= time();
        $bestAt = 0;
        $world = '';
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $extra = AtakDataRepository::decodeExtra($row['extra'] ?? null);
            if ((string) ($extra['party_owner'] ?? '') !== $owner) {
                continue;
            }
            $at = (int) ($extra['party_at'] ?? 0);
            $rowWorld = self::rowWorld($extra);
            if ($rowWorld === '' || $at <= $bestAt || ($now - $at) > self::PARTY_FRESH_SECONDS) {
                continue;
            }
            $bestAt = $at;
            $world = $rowWorld;
        }

        return $world;
    }

    /** @param array<string, mixed> $extra */
    private static function rowWorld(array $extra): string
    {
        $world = self::normalizeWorld($extra['party_world'] ?? '');
        if ($world !== '') {
            return $world;
        }
        $key = AtakMissionKey::normalize($extra['party_key'] ?? $extra['mission_key'] ?? null);

        return $key !== '' ? self::worldOf($key) : '';
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $extra
     */
    private static function isProxy(array $row, array $extra): bool
    {
        $callSign = (string) ($row['call_sign'] ?? '');

        return AtakDataRepository::isProxyContactExtra($extra) || AtakDataRepository::callSignLooksLikeProxy($callSign);
    }

    /** @param array<string, mixed> $row */
    private static function ageOf(array $row, int $now): ?int
    {
        if (isset($row['age_seconds']) && is_numeric($row['age_seconds'])) {
            return max(0, (int) $row['age_seconds']);
        }
        $ts = isset($row['updated_at']) && is_string($row['updated_at']) ? strtotime($row['updated_at']) : false;

        return $ts === false ? null : max(0, $now - $ts);
    }

    /**
     * Pistes de la communauté par défaut à purger : sans signe de vie depuis DEFAULT_PURGE_AFTER_SECONDS.
     * Simulation tant que $apply est faux. Idempotent : un second passage ne trouve plus rien.
     *
     * @return array{tenant: int, cutoff: string, found: int, deleted: int, by_kind: array<string, int>}
     */
    public static function purgeStaleDefaultUnits(PDO $pdo, int $tenantId, bool $apply, ?int $now = null): array
    {
        $now ??= time();
        $cutoff = date('Y-m-d H:i:s', $now - self::DEFAULT_PURGE_AFTER_SECONDS);
        $report = ['tenant' => $tenantId, 'cutoff' => $cutoff, 'found' => 0, 'deleted' => 0, 'by_kind' => ['ia_alliee' => 0, 'compte' => 0, 'indicatif' => 0]];
        if (!self::isDefaultCommunity($pdo, $tenantId)) {
            return $report;
        }
        // MySQL : borne sur l'horloge de la base (celle qui écrit updated_at). Ailleurs (SQLite des tests) : borne calculée ici.
        $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $stale = $mysql
            ? '(updated_at IS NULL OR updated_at < DATE_SUB(NOW(), INTERVAL ' . self::DEFAULT_PURGE_AFTER_SECONDS . ' SECOND))'
            : '(updated_at IS NULL OR updated_at < ?)';
        $bound = $mysql ? [] : [$cutoff];
        $st = $pdo->prepare('SELECT id, call_sign FROM atak_units WHERE tenant_id = ? AND ' . $stale);
        $st->execute(array_merge([$tenantId], $bound));
        $ids = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $ids[] = (int) $row['id'];
            $cs = strtoupper(trim((string) ($row['call_sign'] ?? '')));
            $kind = str_starts_with($cs, 'ALLY-') ? 'ia_alliee' : (preg_match('/^U-\d+$/', $cs) === 1 ? 'compte' : 'indicatif');
            $report['by_kind'][$kind]++;
        }
        $report['found'] = count($ids);
        if (!$apply || $ids === []) {
            return $report;
        }
        foreach (array_chunk($ids, 500) as $chunk) {
            $marks = implode(',', array_fill(0, count($chunk), '?'));
            // La borne est revérifiée : une piste revenue en liaison entre la lecture et la suppression est gardée.
            $del = $pdo->prepare("DELETE FROM atak_units WHERE tenant_id = ? AND id IN ({$marks}) AND {$stale}");
            $del->execute(array_merge([$tenantId], $chunk, $bound));
            $report['deleted'] += $del->rowCount();
        }

        return $report;
    }

    /**
     * Lignes servies au client sans les marques internes de cloisonnement (le compte d'un joueur n'est pas publié).
     *
     * @param list<mixed> $rows
     * @return list<mixed>
     */
    public static function publicRows(array $rows): array
    {
        foreach ($rows as $i => $row) {
            if (is_array($row) && is_array($row['extra'] ?? null)) {
                unset($row['extra']['party_owner'], $row['extra']['party_at'], $row['extra']['party_src']);
                $rows[$i] = $row;
            }
        }

        return $rows;
    }

    /**
     * Fichiers d'état par carte (journal d'activité, télémétrie, météo… : storage/cache/<dossier>/t<communauté>_m<carte>…)
     * laissés par les cartes réservées de la communauté par défaut : supprimés quand ils n'ont plus bougé depuis
     * DEFAULT_PURGE_AFTER_SECONDS. Les cartes choisies à la main (1 à 99) ne sont jamais touchées.
     *
     * @param list<int> $tenantIds
     * @return array{found: int, deleted: int}
     */
    public static function sweepReservedMapFiles(string $cacheDir, array $tenantIds, bool $apply, ?int $now = null): array
    {
        $now ??= time();
        $report = ['found' => 0, 'deleted' => 0];
        $tenantIds = array_map('intval', $tenantIds);
        if ($tenantIds === [] || !is_dir($cacheDir)) {
            return $report;
        }
        $dirs = array_merge(glob($cacheDir . '/*', GLOB_ONLYDIR) ?: [], glob($cacheDir . '/*/*', GLOB_ONLYDIR) ?: []);
        foreach ($dirs as $dir) {
            foreach (glob($dir . '/t*_m*') ?: [] as $file) {
                if (!is_file($file) || preg_match('/^t(\d+)_m(\d+)(?:[._]|$)/', basename($file), $m) !== 1) {
                    continue;
                }
                if (!in_array((int) $m[1], $tenantIds, true) || !self::isReservedMapId((int) $m[2])) {
                    continue;
                }
                $mtime = @filemtime($file);
                if ($mtime === false || ($now - $mtime) < self::DEFAULT_PURGE_AFTER_SECONDS) {
                    continue;
                }
                $report['found']++;
                if ($apply && @unlink($file)) {
                    $report['deleted']++;
                }
            }
        }

        return $report;
    }
}
