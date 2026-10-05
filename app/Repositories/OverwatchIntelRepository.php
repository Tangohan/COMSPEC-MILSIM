<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SilentSchemaMigration;
use Throwable;

/**
 * Overwatch Beta : acquittements d'alertes, anneaux de géolocalisation et
 * lectures « tolérantes » (table ou colonne absente → résultat vide, jamais d'erreur).
 */
final class OverwatchIntelRepository
{
    private Database $db;

    /** @var array<string, list<string>> */
    private array $columnsCache = [];

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
        SilentSchemaMigration::run(base_path('bootstrap/atak_overwatch_intel_migration.php'));
    }

    /** @return list<string> */
    public function columns(string $table): array
    {
        if (isset($this->columnsCache[$table])) {
            return $this->columnsCache[$table];
        }
        $cols = [];
        try {
            $rows = $this->db->fetchAll(
                'SELECT COLUMN_NAME FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t',
                ['t' => $table]
            );
            foreach ($rows as $row) {
                $cols[] = (string) ($row['COLUMN_NAME'] ?? '');
            }
        } catch (Throwable) {
            $cols = [];
        }

        return $this->columnsCache[$table] = $cols;
    }

    /**
     * Requête de lecture protégée : [] si la table manque ou si la requête échoue.
     *
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    public function safeFetch(string $sql, array $params = []): array
    {
        try {
            return $this->db->fetchAll($sql, $params);
        } catch (Throwable $e) {
            error_log('[overwatch-intel] ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Statistiques d'une source SQL : nombre aujourd'hui, dernière ligne (âge selon l'horloge MySQL).
     *
     * author_value : ne compte que les lignes de cet auteur (indicatif) ; source ignorée sans colonne auteur.
     * mapId < 1 : toutes les cartes.
     *
     * @param array{table:string, time:list<string>, author?:list<string>, author_value?:string, map?:list<string>, where?:string, params?:array<string,mixed>} $spec
     * @return array{available:bool, today:int, total_24h:int, last_at:?string, age_sec:?int, last_author:string}
     */
    public function sourceStats(array $spec, int $tenantId, int $mapId): array
    {
        $empty = ['available' => false, 'today' => 0, 'total_24h' => 0, 'last_at' => null, 'age_sec' => null, 'last_author' => ''];
        $table = (string) $spec['table'];
        $cols = $this->columns($table);
        if ($cols === [] || !in_array('tenant_id', $cols, true)) {
            return $empty;
        }
        $timeCol = $this->firstColumn($cols, $spec['time']);
        if ($timeCol === null) {
            return $empty;
        }
        $authorCol = $this->firstColumn($cols, $spec['author'] ?? []);
        $mapCol = $this->firstColumn($cols, $spec['map'] ?? ['map_id']);
        $where = ['tenant_id = :tenant'];
        $params = ['tenant' => $tenantId];
        $authorValue = trim((string) ($spec['author_value'] ?? ''));
        if ($authorValue !== '') {
            if ($authorCol === null) {
                return $empty;
            }
            $where[] = '`' . $authorCol . '` = :author_value';
            $params['author_value'] = $authorValue;
        }
        if ($mapCol !== null && $mapId > 0) {
            $where[] = '`' . $mapCol . '` = :map';
            $params['map'] = $mapId;
        }
        if (!empty($spec['where'])) {
            $where[] = '(' . $spec['where'] . ')';
            $params += (array) ($spec['params'] ?? []);
        }
        $w = implode(' AND ', $where);
        $tc = '`' . $timeCol . '`';
        $agg = $this->safeFetch(
            "SELECT SUM($tc >= CURDATE()) AS today,
                    SUM($tc >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) AS day,
                    MAX($tc) AS last_at,
                    TIMESTAMPDIFF(SECOND, MAX($tc), NOW()) AS age_sec
             FROM `$table` WHERE $w",
            $params
        );
        $row = $agg[0] ?? [];
        $out = [
            'available' => true,
            'today' => (int) ($row['today'] ?? 0),
            'total_24h' => (int) ($row['day'] ?? 0),
            'last_at' => isset($row['last_at']) && $row['last_at'] !== null ? (string) $row['last_at'] : null,
            'age_sec' => isset($row['age_sec']) && $row['age_sec'] !== null ? max(0, (int) $row['age_sec']) : null,
            'last_author' => '',
        ];
        if ($authorCol !== null && $out['last_at'] !== null) {
            $last = $this->safeFetch(
                "SELECT `$authorCol` AS who FROM `$table` WHERE $w ORDER BY $tc DESC LIMIT 1",
                $params
            );
            $out['last_author'] = trim((string) ($last[0]['who'] ?? ''));
        }

        return $out;
    }

    /**
     * @param list<string> $have
     * @param list<string> $want
     */
    public function firstColumn(array $have, array $want): ?string
    {
        foreach ($want as $c) {
            if (in_array($c, $have, true)) {
                return $c;
            }
        }

        return null;
    }

    // ------------------------------------------------------------------ Acquittements

    /** @return array<string, array{by:string, at:string}> */
    public function acks(int $tenantId, int $mapId): array
    {
        $out = [];
        foreach ($this->safeFetch(
            'SELECT alert_key, acked_by, acked_at FROM atak_overwatch_alert_acks
             WHERE tenant_id = :t AND map_id = :m AND acked_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)',
            ['t' => $tenantId, 'm' => $mapId]
        ) as $row) {
            $out[(string) $row['alert_key']] = ['by' => (string) $row['acked_by'], 'at' => (string) $row['acked_at']];
        }

        return $out;
    }

    public function ack(int $tenantId, int $mapId, string $key, string $by, ?int $userId): bool
    {
        $key = mb_substr(trim($key), 0, 96);
        if ($key === '') {
            return false;
        }
        try {
            $this->db->execute(
                'INSERT INTO atak_overwatch_alert_acks (tenant_id, map_id, alert_key, acked_by, acked_by_user_id)
                 VALUES (:t, :m, :k, :b, :u)
                 ON DUPLICATE KEY UPDATE acked_by = VALUES(acked_by), acked_by_user_id = VALUES(acked_by_user_id), acked_at = NOW()',
                ['t' => $tenantId, 'm' => $mapId, 'k' => $key, 'b' => mb_substr($by, 0, 120), 'u' => $userId]
            );

            return true;
        } catch (Throwable $e) {
            error_log('[overwatch-intel] ack ' . $e->getMessage());

            return false;
        }
    }

    public function unack(int $tenantId, int $mapId, string $key): bool
    {
        try {
            $this->db->execute(
                'DELETE FROM atak_overwatch_alert_acks WHERE tenant_id = :t AND map_id = :m AND alert_key = :k',
                ['t' => $tenantId, 'm' => $mapId, 'k' => mb_substr(trim($key), 0, 96)]
            );

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    // ------------------------------------------------------------------ Anneaux de géolocalisation

    /** @return list<array<string, mixed>> */
    public function rings(int $tenantId, int $mapId): array
    {
        $rows = $this->safeFetch(
            'SELECT id, label, query_ref, pos_x, pos_y, radius_m, grid_ref, source, author, created_at,
                    TIMESTAMPDIFF(SECOND, created_at, NOW()) AS age_sec
             FROM atak_geoloc_rings
             WHERE tenant_id = :t AND map_id = :m AND deleted_at IS NULL
             ORDER BY created_at DESC LIMIT 60',
            ['t' => $tenantId, 'm' => $mapId]
        );

        return array_map(static function (array $r): array {
            return [
                'id' => (int) $r['id'],
                'label' => (string) $r['label'],
                'query_ref' => (string) $r['query_ref'],
                'pos_x' => (float) $r['pos_x'],
                'pos_y' => (float) $r['pos_y'],
                'radius_m' => (int) $r['radius_m'],
                'grid_ref' => (string) $r['grid_ref'],
                'source' => (string) $r['source'],
                'author' => (string) $r['author'],
                'created_at' => (string) $r['created_at'],
                'age_sec' => $r['age_sec'] !== null ? max(0, (int) $r['age_sec']) : null,
            ];
        }, $rows);
    }

    /**
     * @param array{label?:string, query_ref?:string, pos_x:float, pos_y:float, radius_m?:int, grid_ref?:string, source?:string, author?:string} $data
     */
    public function addRing(int $tenantId, int $mapId, array $data): int
    {
        $radius = (int) round((float) ($data['radius_m'] ?? 150));
        $radius = max(5, min(20000, $radius));
        $source = strtolower(trim((string) ($data['source'] ?? 'web')));
        if (!in_array($source, ['web', 'phone', 'hub'], true)) {
            $source = 'web';
        }
        $queryRef = mb_substr(trim((string) ($data['query_ref'] ?? '')), 0, 64);
        try {
            // Même requête téléphone renouvelée : on remplace l'anneau précédent au lieu d'empiler.
            if ($queryRef !== '' && $source === 'phone') {
                $this->db->execute(
                    'UPDATE atak_geoloc_rings SET deleted_at = NOW()
                     WHERE tenant_id = :t AND map_id = :m AND query_ref = :q AND source = :s AND deleted_at IS NULL',
                    ['t' => $tenantId, 'm' => $mapId, 'q' => $queryRef, 's' => $source]
                );
            }

            return $this->db->insert(
                'INSERT INTO atak_geoloc_rings (tenant_id, map_id, label, query_ref, pos_x, pos_y, radius_m, grid_ref, source, author)
                 VALUES (:t, :m, :l, :q, :x, :y, :r, :g, :s, :a)',
                [
                    't' => $tenantId,
                    'm' => $mapId,
                    'l' => mb_substr(trim((string) ($data['label'] ?? '')), 0, 120),
                    'q' => $queryRef,
                    'x' => (float) $data['pos_x'],
                    'y' => (float) $data['pos_y'],
                    'r' => $radius,
                    'g' => mb_substr(trim((string) ($data['grid_ref'] ?? '')), 0, 32),
                    's' => $source,
                    'a' => mb_substr(trim((string) ($data['author'] ?? '')), 0, 120),
                ]
            );
        } catch (Throwable $e) {
            error_log('[overwatch-intel] ring ' . $e->getMessage());

            return 0;
        }
    }

    /**
     * Événement télémétrie « geoloc » émis par le téléphone (app GE, requête GÉOLOC réussie).
     * Champs : x, y, radius, label (numéro / IMEI / MAC saisi), grid, q (clé de requête).
     *
     * @param array<string, mixed> $ev
     * @return array{ok:bool, error?:string, http?:int, call_sign?:string}
     */
    public function ingestTelemetryGeoloc(int $tenantId, int $mapId, array $ev, ?string $actorCallSign): array
    {
        $data = is_array($ev['data'] ?? null) ? $ev['data'] + $ev : $ev;
        $x = $data['x'] ?? $data['pos_x'] ?? null;
        $y = $data['y'] ?? $data['pos_y'] ?? null;
        if (!is_numeric($x) || !is_numeric($y)) {
            return ['ok' => false, 'error' => 'geoloc_position_missing', 'http' => 422];
        }
        $cs = trim((string) ($data['call_sign'] ?? $actorCallSign ?? ''));
        $id = $this->addRing($tenantId, $mapId, [
            'label' => (string) ($data['label'] ?? 'GÉOLOC'),
            'query_ref' => (string) ($data['q'] ?? $data['label'] ?? ''),
            'pos_x' => (float) $x,
            'pos_y' => (float) $y,
            'radius_m' => (int) round((float) ($data['radius'] ?? $data['radius_m'] ?? 150)),
            'grid_ref' => (string) ($data['grid'] ?? ''),
            'source' => 'phone',
            'author' => $cs,
        ]);

        return $id > 0 ? ['ok' => true, 'call_sign' => $cs] : ['ok' => false, 'error' => 'geoloc_store_failed', 'http' => 500];
    }

    public function deleteRing(int $tenantId, int $mapId, int $id): bool
    {
        try {
            return $this->db->execute(
                'UPDATE atak_geoloc_rings SET deleted_at = NOW()
                 WHERE tenant_id = :t AND map_id = :m AND id = :id AND deleted_at IS NULL',
                ['t' => $tenantId, 'm' => $mapId, 'id' => $id]
            ) > 0;
        } catch (Throwable) {
            return false;
        }
    }

    public function clearRings(int $tenantId, int $mapId): int
    {
        try {
            return $this->db->execute(
                'UPDATE atak_geoloc_rings SET deleted_at = NOW()
                 WHERE tenant_id = :t AND map_id = :m AND deleted_at IS NULL',
                ['t' => $tenantId, 'm' => $mapId]
            );
        } catch (Throwable) {
            return 0;
        }
    }
}
