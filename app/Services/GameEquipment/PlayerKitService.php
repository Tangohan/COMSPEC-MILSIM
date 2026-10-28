<?php

declare(strict_types=1);

namespace App\Services\GameEquipment;

use App\Repositories\ArsenalWardrobeRepository;
use App\Repositories\GameEquipmentRepository;
use App\Support\ArsenalLoadoutItems;
use App\Support\LazyDatabaseConnection;
use PDO;

/**
 * Kits réellement portés en jeu : le dernier loadout (getUnitLoadout) remonté par le mod pour chaque joueur
 * (operator_game_profiles.loadout_json), lu avec les noms du catalogue d’objets vus en jeu.
 *
 * Une seule source pour trois pages : « Équipement des joueurs » (back-office), « Mon kit en jeu » (page
 * Équipement du membre) et les tenues d’arsenal, qu’un kit porté alimente sans saisie (même format de loadout).
 * Ne lève jamais : une base sans ces tables affiche simplement des listes vides.
 */
final class PlayerKitService
{
    use LazyDatabaseConnection;

    public const COLLECTION_SLUG = 'kits-des-joueurs';

    public const COLLECTION_NAME = 'Kits des joueurs';

    public const MEMBER_SLUG = 'mon-kit-en-jeu';

    public function __construct(
        private ?GameEquipmentRepository $equipment = null,
        private ?ArsenalWardrobeRepository $wardrobes = null,
        ?PDO $pdo = null,
    ) {
        $this->equipment ??= new GameEquipmentRepository();
        $this->wardrobes ??= new ArsenalWardrobeRepository();
        $this->pdo = $pdo;
    }

    /**
     * Derniers kits portés, joueur par joueur (le plus récent d’abord).
     *
     * @return list<array<string, mixed>>
     */
    public function recent(int $tenantId, int $days, int $limit = 60): array
    {
        try {
            $st = $this->pdo()->prepare(
                'SELECT p.steam_id, p.user_id, p.role, p.group_name, p.mission_name, p.last_seen_at, p.loadout_json,
                        COALESCE(NULLIF(TRIM(u.callsign), \'\'), NULLIF(TRIM(p.callsign), \'\'),
                                 NULLIF(TRIM(u.display_name), \'\'), NULLIF(TRIM(p.display_name), \'\'),
                                 NULLIF(TRIM(p.arma_player_name), \'\'), p.steam_id) AS player_name
                 FROM operator_game_profiles p
                 LEFT JOIN users u ON u.id = p.user_id AND u.tenant_id = p.tenant_id
                 WHERE p.tenant_id = ? AND p.last_seen_at >= ?
                   AND p.loadout_json IS NOT NULL AND p.loadout_json NOT IN (\'\', \'[]\', \'{}\')
                 ORDER BY p.last_seen_at DESC
                 LIMIT ' . max(1, min(300, $limit))
            );
            $st->execute([$tenantId, GameEquipmentRepository::sinceDay($days) . ' 00:00:00']);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }

        return $this->decorate($tenantId, $rows, $days);
    }

    /**
     * Dernier kit porté par un membre (compte lié à son Steam).
     *
     * @return array<string, mixed>|null
     */
    public function forUser(int $tenantId, int $userId): ?array
    {
        if ($userId < 1) {
            return null;
        }
        try {
            $st = $this->pdo()->prepare(
                'SELECT p.steam_id, p.user_id, p.role, p.group_name, p.mission_name, p.last_seen_at, p.loadout_json,
                        COALESCE(NULLIF(TRIM(p.callsign), \'\'), NULLIF(TRIM(p.arma_player_name), \'\'), p.steam_id) AS player_name
                 FROM operator_game_profiles p
                 WHERE p.tenant_id = ? AND (p.user_id = ? OR p.steam_id = (
                     SELECT u.steam_id FROM users u WHERE u.id = ? AND u.tenant_id = ? LIMIT 1))
                 ORDER BY p.last_seen_at DESC
                 LIMIT 1'
            );
            $st->execute([$tenantId, $userId, $userId, $tenantId]);
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable) {
            return null;
        }
        if ($row === null) {
            return null;
        }

        return $this->decorate($tenantId, [$row], 90)[0] ?? null;
    }

    /**
     * Enregistre le dernier kit porté d’un joueur comme tenue d’arsenal. Un même joueur garde une seule tenue :
     * un nouvel enregistrement la met à jour au lieu d’en créer une autre.
     *
     * @param bool $forCommunity true : collection « Kits des joueurs », visible de toute la communauté ;
     *                           false : tenue personnelle du membre (« Mon kit en jeu »)
     * @return array{ok: bool, id?: int, error?: string}
     */
    public function saveAsWardrobe(int $tenantId, int $actorUserId, string $steamId, bool $forCommunity): array
    {
        if (!$this->wardrobes->tablesReady()) {
            return ['ok' => false, 'error' => 'Les tenues d’arsenal ne sont pas encore disponibles sur cette instance.'];
        }
        $kit = $forCommunity ? $this->findBySteam($tenantId, $steamId) : $this->forUser($tenantId, $actorUserId);
        if ($kit === null || ($kit['sections'] ?? []) === []) {
            return ['ok' => false, 'error' => 'Aucun kit porté en jeu n’a encore été remonté pour ce joueur.'];
        }
        try {
            $collectionId = null;
            if ($forCommunity) {
                $collectionId = $this->wardrobes->ensureTenantCollection(
                    $tenantId,
                    self::COLLECTION_SLUG,
                    self::COLLECTION_NAME,
                    'Kits réellement portés en jeu, enregistrés depuis le back-office (Équipement des joueurs).',
                    ['jeu'],
                );
            }
            $player = (string) $kit['player_name'];
            // Tenue déjà enregistrée par un autre responsable : on la met à jour à son nom (pas de doublon).
            $owner = $actorUserId;
            if ($forCommunity && $kit['wardrobe_id'] !== null) {
                $current = $this->wardrobes->findWardrobe($tenantId, (int) $kit['wardrobe_id']);
                if ($current !== null) {
                    $owner = (int) ($current['user_id'] ?? $actorUserId);
                }
            }
            $wardrobe = $this->wardrobes->upsertWardrobe($tenantId, $owner, [
                'name' => $forCommunity ? 'Kit en jeu · ' . $player : 'Mon kit en jeu',
                'slug' => $forCommunity ? 'kit-joueur-' . $kit['steam_id'] : self::MEMBER_SLUG,
                'payload_text' => (string) $kit['payload'],
                'payload_format' => 'arma_loadout_str',
                'source' => 'game_loadout',
                'steam_uid' => (string) $kit['steam_id'],
                'collection_id' => $collectionId !== null && $collectionId > 0 ? $collectionId : null,
                'notes' => 'Porté en jeu le ' . date('d/m/Y H:i', strtotime((string) $kit['last_seen_at']) ?: time())
                    . ((string) ($kit['role'] ?? '') !== '' ? ' · ' . $kit['role'] : ''),
            ]);
            $id = (int) ($wardrobe['id'] ?? 0);
            if ($id < 1) {
                return ['ok' => false, 'error' => 'Enregistrement impossible.'];
            }
            if ($collectionId !== null && $collectionId > 0) {
                $this->wardrobes->assignWardrobeCollection($tenantId, $owner, $id, $collectionId);
            }

            return ['ok' => true, 'id' => $id];
        } catch (\Throwable $e) {
            error_log('[player-kits] save as wardrobe failed: ' . $e->getMessage());

            return ['ok' => false, 'error' => 'Enregistrement impossible.'];
        }
    }

    /**
     * Joueurs qui ont porté chaque objet sur la période (clé = classe en minuscules).
     *
     * @param list<string> $classes
     * @return array<string, int>
     */
    public function playersPerClass(int $tenantId, array $classes, int $days): array
    {
        $classes = array_values(array_unique(array_filter($classes, static fn (string $c): bool => $c !== '')));
        if ($classes === [] || !$this->equipment->tablesReady()) {
            return [];
        }
        $out = [];
        try {
            foreach (array_chunk($classes, 500) as $chunk) {
                $st = $this->pdo()->prepare(
                    'SELECT class_name, COUNT(DISTINCT steam_id) AS players FROM game_item_usage
                     WHERE tenant_id = ? AND usage_day >= ? AND class_name IN (' . implode(', ', array_fill(0, count($chunk), '?')) . ')
                     GROUP BY class_name'
                );
                $st->execute(array_merge([$tenantId, GameEquipmentRepository::sinceDay($days)], $chunk));
                while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                    $key = strtolower((string) $row['class_name']);
                    $out[$key] = ($out[$key] ?? 0) + (int) $row['players'];
                }
            }
        } catch (\Throwable) {
            return $out;
        }

        return $out;
    }

    /**
     * Catalogue pour ArsenalLoadoutItems::grouped() : nom affiché en jeu et joueurs qui portent l’objet.
     *
     * @param list<string> $classes
     * @return array<string, array{name: string, players: int}>
     */
    public function catalogFor(int $tenantId, array $classes, int $days = 90): array
    {
        try {
            $known = $this->equipment->catalogueFor($tenantId, $classes);
        } catch (\Throwable) {
            $known = [];
        }
        $players = $this->playersPerClass($tenantId, $classes, $days);
        $out = [];
        foreach ($classes as $class) {
            $key = strtolower($class);
            if (!isset($known[$key]) && !isset($players[$key])) {
                continue;
            }
            $out[$key] = ['name' => (string) ($known[$key]['name'] ?? ''), 'players' => (int) ($players[$key] ?? 0)];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findBySteam(int $tenantId, string $steamId): ?array
    {
        $steamId = trim($steamId);
        if ($steamId === '' || strlen($steamId) > 32) {
            return null;
        }
        try {
            $st = $this->pdo()->prepare(
                'SELECT p.steam_id, p.user_id, p.role, p.group_name, p.mission_name, p.last_seen_at, p.loadout_json,
                        COALESCE(NULLIF(TRIM(u.callsign), \'\'), NULLIF(TRIM(p.callsign), \'\'),
                                 NULLIF(TRIM(u.display_name), \'\'), NULLIF(TRIM(p.arma_player_name), \'\'), p.steam_id) AS player_name
                 FROM operator_game_profiles p
                 LEFT JOIN users u ON u.id = p.user_id AND u.tenant_id = p.tenant_id
                 WHERE p.tenant_id = ? AND p.steam_id = ?
                 LIMIT 1'
            );
            $st->execute([$tenantId, $steamId]);
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable) {
            return null;
        }

        return $row === null ? null : ($this->decorate($tenantId, [$row], 90)[0] ?? null);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function decorate(int $tenantId, array $rows, int $days): array
    {
        $payloads = [];
        $classes = [];
        foreach ($rows as $i => $row) {
            $payload = self::payloadFrom((string) ($row['loadout_json'] ?? ''));
            if ($payload === '') {
                continue;
            }
            $payloads[$i] = $payload;
            foreach (ArsenalLoadoutItems::classes($payload) as $c) {
                $classes[strtolower($c)] = $c;
            }
        }
        $catalog = $this->catalogFor($tenantId, array_values($classes), $days);
        $wardrobeIds = $this->savedWardrobes($tenantId);
        $out = [];
        foreach ($payloads as $i => $payload) {
            $row = $rows[$i];
            $sections = ArsenalLoadoutItems::grouped($payload, $catalog);
            if ($sections === []) {
                continue;
            }
            $weapon = '';
            $count = 0;
            foreach ($sections as $sec) {
                $count += count($sec['items']);
                if ($weapon === '' && $sec['title'] === 'Arme' && isset($sec['items'][0]['name'])) {
                    $weapon = (string) $sec['items'][0]['name'];
                }
            }
            $steam = (string) ($row['steam_id'] ?? '');
            $out[] = [
                'steam_id' => $steam,
                'user_id' => isset($row['user_id']) ? (int) $row['user_id'] : null,
                'player_name' => (string) ($row['player_name'] ?? $steam),
                'role' => trim((string) ($row['role'] ?? '')),
                'group_name' => trim((string) ($row['group_name'] ?? '')),
                'mission_name' => trim((string) ($row['mission_name'] ?? '')),
                'last_seen_at' => (string) ($row['last_seen_at'] ?? ''),
                'primary_weapon' => $weapon,
                'item_count' => $count,
                'sections' => $sections,
                'payload' => $payload,
                'wardrobe_id' => $wardrobeIds['kit-joueur-' . strtolower($steam)] ?? null,
            ];
        }

        return $out;
    }

    /**
     * Tenues déjà enregistrées depuis un kit porté (slug → id), pour proposer « Mettre à jour » plutôt qu’un doublon.
     *
     * @return array<string, int>
     */
    private function savedWardrobes(int $tenantId): array
    {
        try {
            $st = $this->pdo()->prepare(
                'SELECT id, slug FROM arsenal_wardrobes WHERE tenant_id = ? AND slug LIKE \'kit-joueur-%\''
            );
            $st->execute([$tenantId]);
            $out = [];
            while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                $out[strtolower((string) $row['slug'])] = (int) $row['id'];
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * loadout_json brut (getUnitLoadout, éventuellement [loadout, extended]) → texte de tenue d’arsenal.
     */
    public static function payloadFrom(string $loadoutJson): string
    {
        $decoded = json_decode(trim($loadoutJson), true);
        if (!is_array($decoded) || !array_is_list($decoded)) {
            return '';
        }
        if (count($decoded) === 2 && is_array($decoded[0] ?? null) && count($decoded[0]) >= 9) {
            $decoded = $decoded[0];
        }
        if (count($decoded) < 9) {
            return '';
        }

        return (string) json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
