<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Services\GameEquipment\GameItemKinds;
use App\Support\LazyDatabaseConnection;
use PDO;

/**
 * Équipement observé en jeu : catalogue d’objets, usage par joueur et par jour, mods détectés.
 * Tables : migrations/20261018000001_game_item_usage.sql.
 */
final class GameEquipmentRepository
{
    use LazyDatabaseConnection;

    /** @var array<string, bool> */
    private static array $tableCache = [];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    public function tablesReady(): bool
    {
        return $this->tableExists('game_item_usage') && $this->tableExists('game_item_catalog');
    }

    public function modsReady(): bool
    {
        return $this->tableExists('game_detected_mods') && $this->tableExists('game_player_mods');
    }

    private function tableExists(string $table): bool
    {
        if (isset(self::$tableCache[$table])) {
            return self::$tableCache[$table];
        }
        try {
            $st = $this->pdo()->prepare(
                'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
            );
            $st->execute([$table]);
            $ok = (bool) $st->fetchColumn();
        } catch (\Throwable) {
            $ok = false;
        }
        // On ne mémorise que la présence : une migration appliquée en cours de route est vue au prochain appel.
        if ($ok) {
            self::$tableCache[$table] = true;
        }

        return $ok;
    }

    /**
     * Une requête multi-lignes par table : catalogue, usage du jour, puis (si envoyés) mods du joueur.
     *
     * @param list<array{class: string, name: string, kind: string, mod: string, max_ammo: int|null}> $items
     */
    public function recordItems(int $tenantId, string $steamId, array $items, string $now): void
    {
        if ($tenantId < 1 || $steamId === '' || $items === [] || !$this->tablesReady()) {
            return;
        }
        $day = substr($now, 0, 10);
        $this->upsertCatalog($tenantId, $items, $now);
        $useRows = [];
        $useParams = [];
        foreach ($items as $it) {
            $useRows[] = '(?, ?, ?, ?, ?, 1, ?)';
            array_push($useParams, $tenantId, $steamId, $it['class'], $it['kind'], $day, $now);
        }
        $this->pdo()->prepare(
            'INSERT INTO game_item_usage (tenant_id, steam_id, class_name, kind, usage_day, hits, last_seen)
             VALUES ' . implode(', ', $useRows) . '
             ON DUPLICATE KEY UPDATE hits = hits + 1, kind = VALUES(kind), last_seen = VALUES(last_seen)'
        )->execute($useParams);
    }

    /**
     * Noms affichés et mods des objets d’une tenue envoyée depuis l’arsenal (catalogue seul : personne ne les porte
     * encore, aucun usage n’est compté). Un nom déjà connu n’est jamais effacé par un nom vide.
     *
     * @param list<array{class: string, name: string, kind: string, mod: string, max_ammo: int|null}> $items
     */
    public function recordCatalog(int $tenantId, array $items, string $now): void
    {
        if ($tenantId < 1 || $items === [] || !$this->tablesReady()) {
            return;
        }
        $this->upsertCatalog($tenantId, $items, $now);
    }

    /**
     * @param list<array{class: string, name: string, kind: string, mod: string, max_ammo: int|null}> $items
     */
    private function upsertCatalog(int $tenantId, array $items, string $now): void
    {
        $catRows = [];
        $catParams = [];
        foreach ($items as $it) {
            $catRows[] = '(?, ?, ?, ?, ?, ?, ?, ?)';
            array_push($catParams, $tenantId, $it['class'], $it['name'], $it['kind'], $it['mod'], $it['max_ammo'], $now, $now);
        }
        $this->pdo()->prepare(
            'INSERT INTO game_item_catalog (tenant_id, class_name, name, kind, mod_name, max_ammo, first_seen, last_seen)
             VALUES ' . implode(', ', $catRows) . '
             ON DUPLICATE KEY UPDATE
                name = IF(VALUES(name) <> \'\', VALUES(name), name),
                kind = IF(kind = \'item\' OR VALUES(kind) <> \'item\', VALUES(kind), kind),
                mod_name = IF(VALUES(mod_name) <> \'\', VALUES(mod_name), mod_name),
                max_ammo = IF(VALUES(max_ammo) IS NOT NULL AND (max_ammo IS NULL OR VALUES(max_ammo) > max_ammo), VALUES(max_ammo), max_ammo),
                last_seen = VALUES(last_seen)'
        )->execute($catParams);
    }

    /**
     * Remplace la liste des mods du joueur (liste complète envoyée par le mod).
     *
     * @param list<array{dir: string, name: string, workshop_id: string, is_dlc: bool}> $mods
     */
    public function recordMods(int $tenantId, string $steamId, array $mods, string $now): void
    {
        if ($tenantId < 1 || $steamId === '' || !$this->modsReady()) {
            return;
        }
        $pdo = $this->pdo();
        if ($mods !== []) {
            $rows = [];
            $params = [];
            $playerRows = [];
            $playerParams = [];
            foreach ($mods as $m) {
                $rows[] = '(?, ?, ?, ?, ?, ?, ?)';
                array_push($params, $tenantId, $m['dir'], $m['name'], $m['workshop_id'], $m['is_dlc'] ? 1 : 0, $now, $now);
                $playerRows[] = '(?, ?, ?, ?)';
                array_push($playerParams, $tenantId, $steamId, $m['dir'], $now);
            }
            $pdo->prepare(
                'INSERT INTO game_detected_mods (tenant_id, mod_dir, name, workshop_id, is_dlc, first_seen, last_seen)
                 VALUES ' . implode(', ', $rows) . '
                 ON DUPLICATE KEY UPDATE
                    name = IF(VALUES(name) <> \'\', VALUES(name), name),
                    workshop_id = IF(VALUES(workshop_id) <> \'\', VALUES(workshop_id), workshop_id),
                    is_dlc = VALUES(is_dlc),
                    last_seen = VALUES(last_seen)'
            )->execute($params);
            $pdo->prepare(
                'INSERT INTO game_player_mods (tenant_id, steam_id, mod_dir, last_seen)
                 VALUES ' . implode(', ', $playerRows) . '
                 ON DUPLICATE KEY UPDATE last_seen = VALUES(last_seen)'
            )->execute($playerParams);
            $ph = implode(', ', array_fill(0, count($mods), '?'));
            $pdo->prepare(
                'DELETE FROM game_player_mods WHERE tenant_id = ? AND steam_id = ? AND mod_dir NOT IN (' . $ph . ')'
            )->execute(array_merge([$tenantId, $steamId], array_map(static fn (array $m): string => $m['dir'], $mods)));

            return;
        }
        $pdo->prepare('DELETE FROM game_player_mods WHERE tenant_id = ? AND steam_id = ?')->execute([$tenantId, $steamId]);
    }

    /**
     * @return array{players: int, items: int, observations: int}
     */
    public function summary(int $tenantId, int $days): array
    {
        $out = ['players' => 0, 'items' => 0, 'observations' => 0];
        if (!$this->tablesReady()) {
            return $out;
        }
        $st = $this->pdo()->prepare(
            'SELECT COUNT(DISTINCT steam_id) AS players, COUNT(DISTINCT class_name) AS items, COUNT(*) AS observations
             FROM game_item_usage WHERE tenant_id = ? AND usage_day >= ?'
        );
        $st->execute([$tenantId, self::sinceDay($days)]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'players' => (int) ($row['players'] ?? 0),
            'items' => (int) ($row['items'] ?? 0),
            'observations' => (int) ($row['observations'] ?? 0),
        ];
    }

    /**
     * Classement par famille : joueurs distincts, puis jours-joueurs.
     *
     * @param list<string> $kinds
     * @return array<string, list<array{class: string, name: string, kind: string, mod: string, players: int, player_days: int, last_seen: string}>>
     */
    public function topByKind(int $tenantId, int $days, array $kinds, int $limit = 10): array
    {
        $out = [];
        foreach ($kinds as $k) {
            $out[$k] = [];
        }
        if ($kinds === [] || !$this->tablesReady()) {
            return $out;
        }
        $ph = implode(', ', array_fill(0, count($kinds), '?'));
        $st = $this->pdo()->prepare(
            'SELECT c.kind, u.class_name, c.name, c.mod_name,
                    COUNT(DISTINCT u.steam_id) AS players, COUNT(*) AS player_days, MAX(u.last_seen) AS last_seen
             FROM game_item_usage u
             INNER JOIN game_item_catalog c ON c.tenant_id = u.tenant_id AND c.class_name = u.class_name
             WHERE u.tenant_id = ? AND u.usage_day >= ? AND c.kind IN (' . $ph . ')
             GROUP BY c.kind, u.class_name, c.name, c.mod_name
             ORDER BY players DESC, player_days DESC, u.class_name ASC'
        );
        $st->execute(array_merge([$tenantId, self::sinceDay($days)], $kinds));
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $k = (string) $row['kind'];
            if (!isset($out[$k]) || count($out[$k]) >= $limit) {
                continue;
            }
            $out[$k][] = [
                'class' => (string) $row['class_name'],
                'name' => (string) ($row['name'] ?? ''),
                'kind' => $k,
                'mod' => (string) ($row['mod_name'] ?? ''),
                'players' => (int) $row['players'],
                'player_days' => (int) $row['player_days'],
                'last_seen' => (string) ($row['last_seen'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * Objets observés par mod d’origine sur la période (pour la section mods).
     *
     * @return array<string, array{items: int, players: int}>
     */
    public function itemModsUsage(int $tenantId, int $days): array
    {
        if (!$this->tablesReady()) {
            return [];
        }
        $st = $this->pdo()->prepare(
            'SELECT c.mod_name, COUNT(DISTINCT u.class_name) AS items, COUNT(DISTINCT u.steam_id) AS players
             FROM game_item_usage u
             INNER JOIN game_item_catalog c ON c.tenant_id = u.tenant_id AND c.class_name = u.class_name
             WHERE u.tenant_id = ? AND u.usage_day >= ? AND c.mod_name <> \'\'
             GROUP BY c.mod_name
             ORDER BY players DESC, items DESC'
        );
        $st->execute([$tenantId, self::sinceDay($days)]);
        $out = [];
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $out[(string) $row['mod_name']] = ['items' => (int) $row['items'], 'players' => (int) $row['players']];
        }

        return $out;
    }

    /**
     * @return list<array{dir: string, name: string, workshop_id: string, is_dlc: bool, players: int, last_seen: string}>
     */
    public function detectedMods(int $tenantId, int $days): array
    {
        if (!$this->modsReady()) {
            return [];
        }
        $st = $this->pdo()->prepare(
            'SELECT m.mod_dir, m.name, m.workshop_id, m.is_dlc, m.last_seen,
                    (SELECT COUNT(*) FROM game_player_mods p
                     WHERE p.tenant_id = m.tenant_id AND p.mod_dir = m.mod_dir AND p.last_seen >= ?) AS players
             FROM game_detected_mods m
             WHERE m.tenant_id = ?
             ORDER BY players DESC, m.is_dlc ASC, m.name ASC'
        );
        $st->execute([self::sinceDay($days) . ' 00:00:00', $tenantId]);
        $out = [];
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $out[] = [
                'dir' => (string) $row['mod_dir'],
                'name' => (string) ($row['name'] ?? ''),
                'workshop_id' => (string) ($row['workshop_id'] ?? ''),
                'is_dlc' => (int) ($row['is_dlc'] ?? 0) === 1,
                'players' => (int) ($row['players'] ?? 0),
                'last_seen' => (string) ($row['last_seen'] ?? ''),
            ];
        }

        return $out;
    }

    /** Joueurs ayant envoyé une liste de mods sur la période. */
    public function playersWithMods(int $tenantId, int $days): int
    {
        if (!$this->modsReady()) {
            return 0;
        }
        $st = $this->pdo()->prepare(
            'SELECT COUNT(DISTINCT steam_id) FROM game_player_mods WHERE tenant_id = ? AND last_seen >= ?'
        );
        $st->execute([$tenantId, self::sinceDay($days) . ' 00:00:00']);

        return (int) $st->fetchColumn();
    }

    /**
     * Catalogue complet pour les sélecteurs de kits (les plus portés d’abord).
     *
     * @return list<array{class: string, name: string, kind: string, mod: string, max_ammo: int|null, players: int}>
     */
    public function catalogue(int $tenantId, int $limit = 6000): array
    {
        if (!$this->tablesReady()) {
            return [];
        }
        $st = $this->pdo()->prepare(
            'SELECT c.class_name, c.name, c.kind, c.mod_name, c.max_ammo,
                    (SELECT COUNT(DISTINCT u.steam_id) FROM game_item_usage u
                     WHERE u.tenant_id = c.tenant_id AND u.class_name = c.class_name) AS players
             FROM game_item_catalog c
             WHERE c.tenant_id = ?
             ORDER BY players DESC, c.last_seen DESC
             LIMIT ' . max(1, min(20000, $limit))
        );
        $st->execute([$tenantId]);
        $out = [];
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $out[] = [
                'class' => (string) $row['class_name'],
                'name' => (string) ($row['name'] ?? ''),
                'kind' => GameItemKinds::normalize($row['kind'] ?? 'item'),
                'mod' => (string) ($row['mod_name'] ?? ''),
                'max_ammo' => isset($row['max_ammo']) ? (int) $row['max_ammo'] : null,
                'players' => (int) ($row['players'] ?? 0),
            ];
        }

        return $out;
    }

    public function catalogueCount(int $tenantId): int
    {
        if (!$this->tablesReady()) {
            return 0;
        }
        $st = $this->pdo()->prepare('SELECT COUNT(*) FROM game_item_catalog WHERE tenant_id = ?');
        $st->execute([$tenantId]);

        return (int) $st->fetchColumn();
    }

    /**
     * @param list<string> $classes
     * @return array<string, array{class: string, name: string, kind: string, mod: string, max_ammo: int|null}>
     */
    public function catalogueFor(int $tenantId, array $classes): array
    {
        $classes = array_values(array_unique(array_filter($classes, static fn (string $c): bool => $c !== '')));
        if ($classes === [] || !$this->tablesReady()) {
            return [];
        }
        $ph = implode(', ', array_fill(0, count($classes), '?'));
        $st = $this->pdo()->prepare(
            'SELECT class_name, name, kind, mod_name, max_ammo FROM game_item_catalog
             WHERE tenant_id = ? AND class_name IN (' . $ph . ')'
        );
        $st->execute(array_merge([$tenantId], $classes));
        $out = [];
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $out[strtolower((string) $row['class_name'])] = [
                'class' => (string) $row['class_name'],
                'name' => (string) ($row['name'] ?? ''),
                'kind' => GameItemKinds::normalize($row['kind'] ?? 'item'),
                'mod' => (string) ($row['mod_name'] ?? ''),
                'max_ammo' => isset($row['max_ammo']) ? (int) $row['max_ammo'] : null,
            ];
        }

        return $out;
    }

    public static function sinceDay(int $days): string
    {
        $days = max(1, min(3650, $days));

        return date('Y-m-d', time() - ($days - 1) * 86400);
    }
}
