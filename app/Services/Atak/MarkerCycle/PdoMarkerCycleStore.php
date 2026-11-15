<?php

declare(strict_types=1);

namespace App\Services\Atak\MarkerCycle;

use App\Core\Database;
use App\Support\SchemaReadyCache;
use App\Services\AtakToc\Purge\TocElementStore;
use App\Services\AtakToc\Purge\TocSoftDelete;
use App\Support\ShortCache;
use PDO;

/**
 * Dépôt MySQL du cycle des marqueurs : atak_markers et atak_map_shapes (éléments affichés), atak_marker_cycle_settings,
 * atak_marker_archives et atak_marker_archive_items (missions archivées). Toutes les requêtes portent le tenant_id.
 *
 * Retrait d'un marqueur venu du jeu : avec la migration 4780, le marqueur reste 48 h en pierre tombale (deleted_at) et le jeu
 * le retire de sa carte native (fn_markerWebSweep) ; sans elle, DELETE simple, le jeu ne le retire pas (docs/TOC-WEB.md).
 */
final class PdoMarkerCycleStore implements MarkerCycleStore
{
    private ?PDO $pdo;

    /** Marqueur ou tracé épinglé = « permanent » du poste (web_permanent), déjà préservé quand le jeu renvoie le marqueur. */
    public const MARKER_PINNED_LIKE = '%"web_permanent":true%';

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    private function db(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    public function ready(): bool
    {
        return SchemaReadyCache::remember('atak_marker_cycles.tables', function (): bool {
            try {
                $st = $this->db()->query(
                    "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()
                     AND TABLE_NAME IN ('atak_marker_cycle_settings', 'atak_marker_archives', 'atak_marker_archive_items')"
                );

                return $st !== false && (int) $st->fetchColumn() === 3;
            } catch (\Throwable) {
                return false;
            }
        });
    }

    public function settingsRow(int $tenantId): ?array
    {
        $st = $this->db()->prepare('SELECT * FROM atak_marker_cycle_settings WHERE tenant_id = ?');
        $st->execute([$tenantId]);
        $r = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($r) ? $r : null;
    }

    public function saveSettings(int $tenantId, array $settings, ?int $userId): void
    {
        $this->db()->prepare(
            'INSERT INTO atak_marker_cycle_settings (tenant_id, enabled, switch_time, duration_days, timezone, retention_days, updated_by, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE enabled = VALUES(enabled), switch_time = VALUES(switch_time), duration_days = VALUES(duration_days),
               timezone = VALUES(timezone), retention_days = VALUES(retention_days), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)'
        )->execute([
            $tenantId, !empty($settings['enabled']) ? 1 : 0, (string) $settings['switch_time'], (int) $settings['duration_days'],
            (string) $settings['timezone'], (int) $settings['retention_days'], $userId,
        ]);
        ShortCache::bump('mcycle:' . $tenantId);
    }

    public function tenantTimezone(int $tenantId): ?string
    {
        try {
            $st = $this->db()->prepare('SELECT default_timezone FROM tenants WHERE id = ?');
            $st->execute([$tenantId]);
            $v = $st->fetchColumn();

            return is_string($v) && $v !== '' ? $v : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function liveScopes(): array
    {
        $rows = $this->db()->query(
            'SELECT tenant_id, map_id FROM atak_markers GROUP BY tenant_id, map_id
             UNION SELECT tenant_id, map_id FROM atak_map_shapes GROUP BY tenant_id, map_id
             ORDER BY tenant_id, map_id'
        );
        $out = [];
        foreach ($rows ? $rows->fetchAll(PDO::FETCH_ASSOC) : [] as $r) {
            $out[] = ['tenant' => (int) $r['tenant_id'], 'map' => (int) $r['map_id']];
        }

        return $out;
    }

    public function staleItems(int $tenantId, int $mapId, string $beforeUtc, int $limit): array
    {
        $limit = max(1, min(1000, $limit));
        $items = [];
        $st = $this->db()->prepare(
            "SELECT id, layer_id, marker_data, arma_name, created_at, updated_at,
                    COALESCE(updated_at, created_at) AS at_utc
             FROM atak_markers
             WHERE tenant_id = ? AND map_id = ? AND COALESCE(updated_at, created_at) < ?
               AND marker_data NOT LIKE ? AND marker_data NOT LIKE '%\"suppressed\":true%' AND marker_data NOT LIKE '%\"suppressed\":1%'
             ORDER BY COALESCE(updated_at, created_at), id LIMIT " . $limit
        );
        $st->execute([$tenantId, $mapId, $beforeUtc, self::MARKER_PINNED_LIKE]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $at = (string) $r['at_utc'];
            unset($r['at_utc']);
            $r['id'] = (int) $r['id'];
            $r['layer_id'] = (int) $r['layer_id'];
            $items[] = ['kind' => 'marker', 'id' => $r['id'], 'at' => $at, 'payload' => $r];
        }
        $st = $this->db()->prepare(
            "SELECT * FROM atak_map_shapes
             WHERE tenant_id = ? AND map_id = ? AND COALESCE(updated_at, created_at) < ?
               AND (meta IS NULL OR COALESCE(JSON_UNQUOTE(JSON_EXTRACT(meta, '$.web_permanent')), '') <> 'true')"
            . TocSoftDelete::liveSql($this->db()) . "
             ORDER BY COALESCE(updated_at, created_at), id LIMIT " . $limit
        );
        $st->execute([$tenantId, $mapId, $beforeUtc]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $at = (string) ($r['updated_at'] ?? $r['created_at']);
            $r['id'] = (int) $r['id'];
            foreach (['visible_to', 'geometry', 'meta'] as $k) {
                if (isset($r[$k]) && is_string($r[$k])) {
                    $d = json_decode($r[$k], true);
                    $r[$k] = is_array($d) ? $d : $r[$k];
                }
            }
            $items[] = ['kind' => 'shape', 'id' => $r['id'], 'at' => $at, 'payload' => $r];
        }
        usort($items, static fn (array $a, array $b): int => [$a['at'], $a['kind'], $a['id']] <=> [$b['at'], $b['kind'], $b['id']]);

        return array_slice($items, 0, $limit);
    }

    public function writeArchive(int $tenantId, int $mapId, array $window, string $source, ?int $userId, array $items): int
    {
        $pdo = $this->db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT INTO atak_marker_archives (tenant_id, map_id, cycle_key, label, starts_at, ends_at, source, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE starts_at = LEAST(starts_at, VALUES(starts_at)), ends_at = GREATEST(ends_at, VALUES(ends_at)), id = LAST_INSERT_ID(id)'
            )->execute([$tenantId, $mapId, $window['key'], mb_substr($window['label'], 0, 160), $window['start_utc'], $window['end_utc'], $source, $userId]);
            $archiveId = (int) $pdo->lastInsertId();
            if ($archiveId < 1) {
                throw new \RuntimeException('mission archivée introuvable après écriture');
            }
            $ins = $pdo->prepare('INSERT IGNORE INTO atak_marker_archive_items (archive_id, kind, source_id, item_at, payload) VALUES (?, ?, ?, ?, ?)');
            foreach ($items as $item) {
                $json = json_encode($item['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
                if (!is_string($json)) {
                    throw new \RuntimeException('contenu illisible pour ' . $item['kind'] . ' ' . $item['id']);
                }
                $ins->execute([$archiveId, $item['kind'], $item['id'], $item['at'], $json]);
            }
            // Contrôle avant de valider : chaque élément annoncé est bien présent dans l'instantané.
            $chk = $pdo->prepare('SELECT COUNT(*) FROM atak_marker_archive_items WHERE archive_id = ? AND kind = ? AND source_id = ?');
            foreach ($items as $item) {
                $chk->execute([$archiveId, $item['kind'], $item['id']]);
                if ((int) $chk->fetchColumn() !== 1) {
                    throw new \RuntimeException('élément ' . $item['kind'] . ' ' . $item['id'] . ' absent de l\'instantané');
                }
            }
            $pdo->prepare(
                "UPDATE atak_marker_archives SET
                   markers_count = (SELECT COUNT(*) FROM atak_marker_archive_items WHERE archive_id = ? AND kind = 'marker'),
                   shapes_count = (SELECT COUNT(*) FROM atak_marker_archive_items WHERE archive_id = ? AND kind = 'shape')
                 WHERE id = ? AND tenant_id = ?"
            )->execute([$archiveId, $archiveId, $archiveId, $tenantId]);
            $pdo->commit();

            return $archiveId;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function deleteLive(int $tenantId, int $mapId, array $refs): int
    {
        $n = 0;
        $soft = TocSoftDelete::available($this->db());
        $toc = new TocElementStore($this->db(), $soft);
        $gameRow = $this->db()->prepare("SELECT 1 FROM atak_markers WHERE tenant_id = ? AND map_id = ? AND id = ? AND arma_name IS NOT NULL AND arma_name <> '' AND marker_data NOT LIKE ?");
        $markerDel = $this->db()->prepare('DELETE FROM atak_markers WHERE tenant_id = ? AND map_id = ? AND id = ? AND marker_data NOT LIKE ?');
        $shapeDel = $this->db()->prepare(
            "DELETE FROM atak_map_shapes WHERE tenant_id = ? AND map_id = ? AND id = ?
             AND (meta IS NULL OR COALESCE(JSON_UNQUOTE(JSON_EXTRACT(meta, '$.web_permanent')), '') <> 'true')"
        );
        foreach ($refs as $ref) {
            if ($ref['kind'] === 'marker') {
                if ($soft) {
                    // Marqueur du jeu : pierre tombale (« archive » : ni corbeille ni annulation), le jeu le retire de sa carte.
                    $gameRow->execute([$tenantId, $mapId, $ref['id'], self::MARKER_PINNED_LIKE]);
                    if ($gameRow->fetchColumn() !== false) {
                        $n += $toc->softDelete($tenantId, $mapId, [['table' => 'marker', 'id' => $ref['id']]], null, 'archive', time());
                        continue;
                    }
                }
                $markerDel->execute([$tenantId, $mapId, $ref['id'], self::MARKER_PINNED_LIKE]);
                $n += $markerDel->rowCount();
            } elseif ($ref['kind'] === 'shape') {
                $shapeDel->execute([$tenantId, $mapId, $ref['id']]);
                $n += $shapeDel->rowCount();
            }
        }
        ShortCache::bump('markers:' . $tenantId);

        return $n;
    }

    public function insertLive(int $tenantId, int $mapId, string $kind, array $payload): ?int
    {
        $pdo = $this->db();
        if ($kind === 'marker') {
            $pdo->prepare('INSERT INTO atak_markers (tenant_id, map_id, layer_id, marker_data, arma_name) VALUES (?, ?, ?, ?, NULL)')
                ->execute([$tenantId, $mapId, max(1, (int) ($payload['layer_id'] ?? 1)), (string) ($payload['marker_data'] ?? '{}')]);
            ShortCache::bump('markers:' . $tenantId);

            return (int) $pdo->lastInsertId();
        }
        if ($kind === 'shape') {
            $enc = static fn (mixed $v): ?string => $v === null ? null : (is_string($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $pdo->prepare(
                'INSERT INTO atak_map_shapes (tenant_id, map_id, mission_id, shape_uid, type, label, color, stroke, fill_opacity, created_by, visible_to, geometry, meta)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $tenantId, $mapId, $payload['mission_id'] ?? null, (string) $payload['shape_uid'], (string) ($payload['type'] ?? 'POINT'),
                $payload['label'] ?? null, (string) ($payload['color'] ?? '#3388ff'), (int) ($payload['stroke'] ?? 2),
                (float) ($payload['fill_opacity'] ?? 0.15), $payload['created_by'] ?? null, $enc($payload['visible_to'] ?? null),
                $enc($payload['geometry'] ?? '{}') ?? '{}', $enc($payload['meta'] ?? null),
            ]);
            ShortCache::bump('markers:' . $tenantId);

            return (int) $pdo->lastInsertId();
        }

        return null;
    }

    public function setPinned(int $tenantId, string $kind, int $id, bool $pinned): bool
    {
        $pdo = $this->db();
        if ($kind === 'marker') {
            $st = $pdo->prepare('SELECT marker_data FROM atak_markers WHERE tenant_id = ? AND id = ?');
            $st->execute([$tenantId, $id]);
            $raw = $st->fetchColumn();
            if (!is_string($raw)) {
                return false;
            }
            $d = json_decode($raw, true);
            $d = is_array($d) ? $d : [];
            if ($pinned) {
                $d['web_permanent'] = true;
            } else {
                unset($d['web_permanent']);
            }
            $pdo->prepare('UPDATE atak_markers SET marker_data = ?, updated_at = UTC_TIMESTAMP() WHERE tenant_id = ? AND id = ?')
                ->execute([json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $tenantId, $id]);
            ShortCache::bump('markers:' . $tenantId);

            return true;
        }
        $st = $pdo->prepare('SELECT meta FROM atak_map_shapes WHERE tenant_id = ? AND id = ?');
        $st->execute([$tenantId, $id]);
        $row = $st->fetch(PDO::FETCH_NUM);
        if ($row === false) {
            return false;
        }
        $m = is_string($row[0]) ? json_decode($row[0], true) : null;
        $m = is_array($m) ? $m : [];
        if ($pinned) {
            $m['web_permanent'] = true;
        } else {
            unset($m['web_permanent']);
        }
        $pdo->prepare('UPDATE atak_map_shapes SET meta = ?, updated_at = UTC_TIMESTAMP() WHERE tenant_id = ? AND id = ?')
            ->execute([json_encode($m === [] ? new \stdClass() : $m, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $tenantId, $id]);
        ShortCache::bump('markers:' . $tenantId);

        return true;
    }

    public function listArchives(int $tenantId, array $filter, int $limit, int $offset): array
    {
        $sql = 'SELECT id, tenant_id, map_id, cycle_key, label, starts_at, ends_at, markers_count, shapes_count, source, created_by, created_at
                FROM atak_marker_archives WHERE tenant_id = ?';
        $p = [$tenantId];
        if (!empty($filter['map'])) {
            $sql .= ' AND map_id = ?';
            $p[] = (int) $filter['map'];
        }
        if (!empty($filter['from'])) {
            $sql .= ' AND ends_at >= ?';
            $p[] = (string) $filter['from'];
        }
        if (!empty($filter['to'])) {
            $sql .= ' AND starts_at <= ?';
            $p[] = (string) $filter['to'];
        }
        if (!empty($filter['q'])) {
            $sql .= ' AND label LIKE ?';
            $p[] = '%' . addcslashes((string) $filter['q'], '%_\\') . '%';
        }
        $sql .= ' ORDER BY ends_at DESC, id DESC LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset);
        $st = $this->db()->prepare($sql);
        $st->execute($p);

        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getArchive(int $tenantId, int $archiveId): ?array
    {
        $st = $this->db()->prepare(
            'SELECT id, tenant_id, map_id, cycle_key, label, starts_at, ends_at, markers_count, shapes_count, source, created_by, created_at
             FROM atak_marker_archives WHERE tenant_id = ? AND id = ?'
        );
        $st->execute([$tenantId, $archiveId]);
        $r = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($r) ? $r : null;
    }

    public function archiveItems(int $tenantId, int $archiveId, int $limit, int $offset): array
    {
        $st = $this->db()->prepare(
            'SELECT i.kind, i.source_id, i.item_at, i.payload FROM atak_marker_archive_items i
             JOIN atak_marker_archives a ON a.id = i.archive_id
             WHERE a.tenant_id = ? AND a.id = ? ORDER BY i.id LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset)
        );
        $st->execute([$tenantId, $archiveId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $payload = json_decode((string) $r['payload'], true);
            $out[] = ['kind' => (string) $r['kind'], 'id' => (int) $r['source_id'], 'at' => (string) $r['item_at'], 'payload' => is_array($payload) ? $payload : []];
        }

        return $out;
    }

    public function deleteArchive(int $tenantId, int $archiveId): bool
    {
        $st = $this->db()->prepare('DELETE FROM atak_marker_archives WHERE tenant_id = ? AND id = ?');
        $st->execute([$tenantId, $archiveId]);

        return $st->rowCount() > 0;
    }

    public function purgeArchivesEndedBefore(int $tenantId, string $beforeUtc, int $limit): int
    {
        $st = $this->db()->prepare('DELETE FROM atak_marker_archives WHERE tenant_id = ? AND ends_at < ? ORDER BY ends_at LIMIT ' . max(1, $limit));
        $st->execute([$tenantId, $beforeUtc]);

        return $st->rowCount();
    }

    public function tenantsWithArchives(): array
    {
        $st = $this->db()->query('SELECT DISTINCT tenant_id FROM atak_marker_archives');

        return array_map('intval', $st ? $st->fetchAll(PDO::FETCH_COLUMN) : []);
    }

    public function lock(string $name): bool
    {
        $st = $this->db()->prepare('SELECT GET_LOCK(?, 0)');
        $st->execute([$name]);

        return (int) $st->fetchColumn() === 1;
    }

    public function unlock(string $name): void
    {
        try {
            $this->db()->prepare('SELECT RELEASE_LOCK(?)')->execute([$name]);
        } catch (\Throwable) {
        }
    }
}
