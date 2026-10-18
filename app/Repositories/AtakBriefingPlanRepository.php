<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\LazyDatabaseConnection;
use PDO;

/**
 * Plan de briefing partagé de chaque camp (app Plan de l'ATAK natif : zones, lignes, points), envoyé en entier
 * par les téléphones (COMSPEC Link « BriefingPlan.Push », POST /api/atak/briefing-plan) et affiché en lecture
 * seule sur la carte du site (calque « Plan de briefing »). Une ligne par carte et par camp, remplacée à chaque envoi.
 * Serveur Arma sans le mod natif (pas de plan partagé) : chaque joueur envoie son brouillon, une ligne par joueur (owner).
 */
class AtakBriefingPlanRepository
{
    use LazyDatabaseConnection;

    /** Types d'éléments de l'app Plan (fn_planCatalog). */
    public const KINDS = [
        'ENY', 'INFIL', 'EXFIL', 'OBJ', 'AO', 'NAI', 'EA', 'AA', 'HLZ', 'NFA', 'ZONE',
        'PL', 'LD', 'LOA', 'BDY', 'ROUTE', 'MOVE', 'AXIS', 'ATK', 'SPT', 'FREE',
        'CLEARED', 'UNCLEARED', 'UNITF', 'UNITH', 'NOTE', 'CP', 'RP', 'TRP', 'OBJPT', 'SBF', 'ABF',
    ];

    public const COLORS = ['RED', 'CYAN', 'GREEN', 'YELLOW', 'BLUE', 'WHITE', 'ORANGE', 'PURPLE', 'BLACK'];

    public const SIDES = ['WEST', 'EAST', 'GUER', 'CIV'];

    /** Un plan plus ancien n'est plus affiché (mission terminée). */
    public const MAX_AGE_HOURS = 12;

    private static ?bool $ready = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    public function ensureSchema(): bool
    {
        if (self::$ready) {
            return true;
        }
        try {
            $this->pdo()->exec(self::createTableSql());
            self::$ready = true;
        } catch (\Throwable) {
            self::$ready = false;
        }

        return self::$ready;
    }

    public static function createTableSql(): string
    {
        return "CREATE TABLE IF NOT EXISTS atak_briefing_plans (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                map_id INT UNSIGNED NOT NULL DEFAULT 1,
                side VARCHAR(8) NOT NULL,
                owner VARCHAR(32) NOT NULL DEFAULT '',
                mission_key VARCHAR(190) NOT NULL DEFAULT '',
                world VARCHAR(64) NOT NULL DEFAULT '',
                rev INT UNSIGNED NOT NULL DEFAULT 0,
                name VARCHAR(64) NOT NULL DEFAULT '',
                items_json MEDIUMTEXT NOT NULL,
                item_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                reported_by VARCHAR(64) NOT NULL DEFAULT '',
                reported_by_user_id INT UNSIGNED DEFAULT NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_atak_briefing_plans_side (tenant_id, map_id, side, owner)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    }

    private static function clean(mixed $v, int $max): string
    {
        $s = trim(preg_replace('/[\x00-\x1F\x7F]/u', ' ', is_scalar($v) ? (string) $v : '') ?? '');

        return mb_substr($s, 0, $max);
    }

    /**
     * Garde les éléments valides d'un envoi du jeu (type et couleur connus, points bornés).
     *
     * @return list<array<string, mixed>>
     */
    public static function normalizeItems(mixed $items): array
    {
        if (!is_array($items)) {
            return [];
        }
        $out = [];
        foreach (array_slice($items, 0, 400) as $it) {
            if (!is_array($it)) {
                continue;
            }
            $kind = strtoupper(self::clean($it['kind'] ?? '', 16));
            if (!in_array($kind, self::KINDS, true) || !is_array($it['pts'] ?? null)) {
                continue;
            }
            $pts = [];
            foreach (array_slice($it['pts'], 0, 250) as $p) {
                if (is_array($p) && isset($p[0], $p[1]) && is_numeric($p[0]) && is_numeric($p[1])) {
                    $pts[] = [round(max(-2000.0, min(60000.0, (float) $p[0])), 1), round(max(-2000.0, min(60000.0, (float) $p[1])), 1)];
                }
            }
            if ($pts === []) {
                continue;
            }
            $color = strtoupper(self::clean($it['color'] ?? '', 8));
            $out[] = [
                'id' => self::clean($it['id'] ?? '', 24),
                'kind' => $kind,
                'pts' => $pts,
                'text' => self::clean($it['text'] ?? '', 40),
                'color' => in_array($color, self::COLORS, true) ? $color : 'WHITE',
                'dashed' => !empty($it['dashed']),
                'arrow' => !empty($it['arrow']),
                'width' => max(1, min(3, (int) ($it['width'] ?? 2))),
                'num' => max(0, min(999, (int) ($it['num'] ?? 0))),
                'author' => self::clean($it['author'] ?? '', 40),
                'desc' => self::clean($it['desc'] ?? '', 300),
            ];
        }

        return $out;
    }

    /**
     * Remplace le plan d'un camp. Un envoi plus ancien de la même mission (révision inférieure) est ignoré.
     *
     * @param list<array<string, mixed>> $items
     */
    public function save(int $tenantId, int $mapId, string $side, string $owner, string $missionKey, string $world, int $rev, string $name, array $items, string $reportedBy, ?int $userId): bool
    {
        if (!$this->ensureSchema()) {
            return false;
        }
        $st = $this->pdo()->prepare('SELECT mission_key, rev FROM atak_briefing_plans WHERE tenant_id = ? AND map_id = ? AND side = ? AND owner = ? LIMIT 1');
        $st->execute([$tenantId, $mapId, $side, $owner]);
        $prev = $st->fetch(PDO::FETCH_ASSOC);
        if (is_array($prev) && (string) $prev['mission_key'] === $missionKey && (int) $prev['rev'] > $rev) {
            return false;
        }
        $json = json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $up = $this->pdo()->prepare(
            'INSERT INTO atak_briefing_plans (tenant_id, map_id, side, owner, mission_key, world, rev, name, items_json, item_count, reported_by, reported_by_user_id, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE mission_key = VALUES(mission_key), world = VALUES(world), rev = VALUES(rev), name = VALUES(name),
                items_json = VALUES(items_json), item_count = VALUES(item_count), reported_by = VALUES(reported_by),
                reported_by_user_id = VALUES(reported_by_user_id), updated_at = NOW()'
        );

        return $up->execute([$tenantId, $mapId, $side, $owner, $missionKey, $world, $rev, $name, $json === false ? '[]' : $json, count($items), $reportedBy, $userId]);
    }

    /**
     * Plans récents (non vides) d'une carte : plan partagé de chaque camp, ou brouillons des joueurs (serveur sans le mod).
     *
     * @return list<array<string, mixed>>
     */
    public function recent(int $tenantId, int $mapId): array
    {
        if (!$this->ensureSchema()) {
            return [];
        }
        $st = $this->pdo()->prepare(
            'SELECT side, owner, world, rev, name, items_json, reported_by, updated_at FROM atak_briefing_plans
             WHERE tenant_id = ? AND map_id = ? AND item_count > 0 AND updated_at >= (NOW() - INTERVAL ' . self::MAX_AGE_HOURS . ' HOUR)
             ORDER BY side, owner'
        );
        $st->execute([$tenantId, $mapId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $items = json_decode((string) $row['items_json'], true);
            $out[] = [
                'side' => (string) $row['side'],
                'personal' => (string) $row['owner'] !== '',
                'world' => (string) $row['world'],
                'rev' => (int) $row['rev'],
                'name' => (string) $row['name'],
                'reported_by' => (string) $row['reported_by'],
                'updated_at' => (string) $row['updated_at'],
                'items' => is_array($items) ? $items : [],
            ];
        }

        return $out;
    }
}
