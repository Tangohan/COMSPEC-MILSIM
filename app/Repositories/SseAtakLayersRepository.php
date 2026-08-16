<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SilentSchemaMigration;
use App\Support\SseAtakLayersCatalog;

/**
 * Persistance tracks + lectures overlay LOT 5.
 */
final class SseAtakLayersRepository
{
    public function __construct(private ?Database $db = null)
    {
        $this->db ??= Database::getInstance();
        SilentSchemaMigration::run(base_path('bootstrap/atak_sse_map_layers_lot5_migration.php'));
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listPirPins(int $tenantId, int $mapId = 0): array
    {
        $sql = 'SELECT r.id, r.case_id, r.req_type, r.reference_code, r.title, r.priority, r.status,
                       r.pos_x, r.pos_y, c.reference_code AS case_ref, c.title AS case_title
                  FROM sse_intel_requirements r
                  LEFT JOIN sse_cases c ON c.id = r.case_id AND c.tenant_id = r.tenant_id
                  LEFT JOIN sse_case_map_state st ON st.case_id = r.case_id AND st.tenant_id = r.tenant_id
                 WHERE r.tenant_id = :t
                   AND r.visible_on_atak = 1
                   AND r.pos_x IS NOT NULL AND r.pos_y IS NOT NULL
                   AND r.status NOT IN (\'satisfait\', \'abandonne\')';
        $params = ['t' => $tenantId];
        if ($mapId > 0) {
            $sql .= ' AND (r.case_id IS NULL OR st.map_id = :m OR st.map_id IS NULL)';
            $params['m'] = $mapId;
        }
        $sql .= ' ORDER BY r.id DESC LIMIT 200';

        try {
            return $this->db->fetchAll($sql, $params);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listTaskingPins(int $tenantId, int $mapId = 0): array
    {
        $sql = 'SELECT t.id, t.case_id, t.requirement_id, t.title, t.priority, t.status,
                       t.tasked_unit, t.tasked_callsign, t.pos_x, t.pos_y,
                       c.reference_code AS case_ref, c.title AS case_title
                  FROM sse_intel_taskings t
                  LEFT JOIN sse_cases c ON c.id = t.case_id AND c.tenant_id = t.tenant_id
                  LEFT JOIN sse_case_map_state st ON st.case_id = t.case_id AND st.tenant_id = t.tenant_id
                 WHERE t.tenant_id = :t
                   AND t.visible_on_atak = 1
                   AND t.pos_x IS NOT NULL AND t.pos_y IS NOT NULL
                   AND t.status NOT IN (\'clos\', \'annule\')';
        $params = ['t' => $tenantId];
        if ($mapId > 0) {
            $sql .= ' AND (t.case_id IS NULL OR st.map_id = :m OR st.map_id IS NULL)';
            $params['m'] = $mapId;
        }
        $sql .= ' ORDER BY t.id DESC LIMIT 200';

        try {
            return $this->db->fetchAll($sql, $params);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listFieldPhotos(int $tenantId, int $mapId = 0): array
    {
        $sql = 'SELECT p.id, p.case_id, p.site_id, p.person_id, p.photo_type, p.quality,
                       p.heading, p.pos_x, p.pos_y, p.image_path, p.created_at,
                       c.reference_code AS case_ref, c.title AS case_title
                  FROM sse_field_photos p
                  LEFT JOIN sse_cases c ON c.id = p.case_id AND c.tenant_id = p.tenant_id
                  LEFT JOIN sse_case_map_state st ON st.case_id = p.case_id AND st.tenant_id = p.tenant_id
                 WHERE p.tenant_id = :t
                   AND p.pos_x IS NOT NULL AND p.pos_y IS NOT NULL
                   AND (p.case_id IS NULL OR st.atak_layer_enabled = 1 OR st.atak_layer_enabled IS NULL)';
        $params = ['t' => $tenantId];
        if ($mapId > 0) {
            $sql .= ' AND (p.case_id IS NULL OR st.map_id = :m OR st.map_id IS NULL)';
            $params['m'] = $mapId;
        }
        $sql .= ' ORDER BY p.id DESC LIMIT 150';

        try {
            return $this->db->fetchAll($sql, $params);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Points d’historique (événements intel avec position terrain).
     *
     * @return list<array<string,mixed>>
     */
    public function listHistoryPoints(int $tenantId, int $mapId = 0, int $limit = 120): array
    {
        $sql = 'SELECT e.id, e.case_id, e.event_type, e.summary, e.event_time, e.author_label,
                       e.pos_x, e.pos_y, e.lat, e.lng,
                       c.reference_code AS case_ref, c.title AS case_title
                  FROM sse_intel_events e
                  LEFT JOIN sse_cases c ON c.id = e.case_id AND c.tenant_id = e.tenant_id
                  LEFT JOIN sse_case_map_state st ON st.case_id = e.case_id AND st.tenant_id = e.tenant_id
                 WHERE e.tenant_id = :t
                   AND (
                        (e.pos_x IS NOT NULL AND e.pos_y IS NOT NULL)
                        OR (e.lat IS NOT NULL AND e.lng IS NOT NULL)
                   )
                   AND (e.case_id IS NULL OR st.atak_layer_enabled = 1 OR st.atak_layer_enabled IS NULL)';
        $params = ['t' => $tenantId];
        if ($mapId > 0) {
            $sql .= ' AND (e.case_id IS NULL OR st.map_id = :m OR st.map_id IS NULL)';
            $params['m'] = $mapId;
        }
        $sql .= ' ORDER BY e.event_time DESC LIMIT ' . max(1, min(300, $limit));

        try {
            return $this->db->fetchAll($sql, $params);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listTracks(int $tenantId, int $mapId = 0, ?string $kind = null): array
    {
        $sql = 'SELECT * FROM sse_atak_tracks
                 WHERE tenant_id = :t AND visible_on_atak = 1';
        $params = ['t' => $tenantId];
        if ($mapId > 0) {
            $sql .= ' AND map_id = :m';
            $params['m'] = $mapId;
        }
        if ($kind !== null && $kind !== '') {
            $sql .= ' AND track_kind = :k';
            $params['k'] = $kind;
        }
        $sql .= ' ORDER BY updated_at DESC LIMIT 100';

        try {
            $rows = $this->db->fetchAll($sql, $params);
        } catch (\Throwable) {
            return [];
        }

        return array_map([$this, 'hydrateTrack'], $rows);
    }

    /**
     * @param array<string,mixed> $data
     * @return array{ok:bool,id?:int,error?:string}
     */
    public function upsertTrack(int $tenantId, array $data): array
    {
        $points = $data['points'] ?? [];
        if (!is_array($points) || count($points) < 2) {
            return ['ok' => false, 'error' => 'Un tracé nécessite au moins deux points.'];
        }
        $clean = [];
        foreach ($points as $p) {
            if (!is_array($p)) {
                continue;
            }
            $x = $p['x'] ?? $p['pos_x'] ?? null;
            $y = $p['y'] ?? $p['pos_y'] ?? null;
            if (!is_numeric($x) || !is_numeric($y)) {
                continue;
            }
            $clean[] = [
                'x' => (float) $x,
                'y' => (float) $y,
                't' => isset($p['t']) ? (string) $p['t'] : null,
            ];
        }
        if (count($clean) < 2) {
            return ['ok' => false, 'error' => 'Points de tracé invalides.'];
        }
        $kind = strtolower(trim((string) ($data['track_kind'] ?? 'live')));
        if (!isset(SseAtakLayersCatalog::TRACK_KINDS[$kind])) {
            $kind = 'live';
        }
        $label = trim((string) ($data['label'] ?? ''));
        if ($label === '') {
            $label = SseAtakLayersCatalog::trackLabel($kind);
        }
        $json = json_encode($clean, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return ['ok' => false, 'error' => 'Impossible d’enregistrer le tracé.'];
        }

        $existingId = (int) ($data['id'] ?? 0);
        try {
            if ($existingId > 0) {
                $this->db->execute(
                    'UPDATE sse_atak_tracks SET
                        case_id = :c, map_id = :m, track_kind = :k, label = :l, callsign = :cs,
                        color = :col, points_json = :pts, visible_on_atak = :vis,
                        source_unit_key = :suk, author_label = :a
                     WHERE tenant_id = :t AND id = :id',
                    [
                        'c' => !empty($data['case_id']) ? (int) $data['case_id'] : null,
                        'm' => max(1, (int) ($data['map_id'] ?? 1)),
                        'k' => $kind,
                        'l' => mb_substr($label, 0, 160),
                        'cs' => $this->nullIfEmpty($data['callsign'] ?? null),
                        'col' => $this->normalizeColor((string) ($data['color'] ?? SseAtakLayersCatalog::colorFor($kind === 'ghost' ? 'ghost_tracks' : 'tracks'))),
                        'pts' => $json,
                        'vis' => !empty($data['visible_on_atak'] ?? true) ? 1 : 0,
                        'suk' => $this->nullIfEmpty($data['source_unit_key'] ?? null),
                        'a' => $this->nullIfEmpty($data['author_label'] ?? null),
                        't' => $tenantId,
                        'id' => $existingId,
                    ]
                );

                return ['ok' => true, 'id' => $existingId];
            }

            $id = (int) $this->db->insert(
                'INSERT INTO sse_atak_tracks (
                    uuid, tenant_id, context_id, case_id, map_id, track_kind, label, callsign,
                    color, points_json, visible_on_atak, source_unit_key, author_label, created_by
                ) VALUES (
                    :uuid, :t, :ctx, :c, :m, :k, :l, :cs,
                    :col, :pts, :vis, :suk, :a, :uid
                )',
                [
                    'uuid' => $this->uuid(),
                    't' => $tenantId,
                    'ctx' => (int) ($data['context_id'] ?? 1),
                    'c' => !empty($data['case_id']) ? (int) $data['case_id'] : null,
                    'm' => max(1, (int) ($data['map_id'] ?? 1)),
                    'k' => $kind,
                    'l' => mb_substr($label, 0, 160),
                    'cs' => $this->nullIfEmpty($data['callsign'] ?? null),
                    'col' => $this->normalizeColor((string) ($data['color'] ?? SseAtakLayersCatalog::colorFor($kind === 'ghost' ? 'ghost_tracks' : 'tracks'))),
                    'pts' => $json,
                    'vis' => !empty($data['visible_on_atak'] ?? true) ? 1 : 0,
                    'suk' => $this->nullIfEmpty($data['source_unit_key'] ?? null),
                    'a' => $this->nullIfEmpty($data['author_label'] ?? null),
                    'uid' => ((int) ($data['created_by'] ?? 0)) ?: null,
                ]
            );

            return ['ok' => true, 'id' => $id];
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'Enregistrement du tracé impossible.'];
        }
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function hydrateTrack(array $row): array
    {
        $kind = (string) ($row['track_kind'] ?? 'live');
        $points = [];
        $raw = $row['points_json'] ?? '[]';
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $points = $decoded;
            }
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'case_id' => isset($row['case_id']) && $row['case_id'] !== null ? (int) $row['case_id'] : null,
            'map_id' => (int) ($row['map_id'] ?? 1),
            'track_kind' => $kind,
            'track_kind_label' => SseAtakLayersCatalog::trackLabel($kind),
            'label' => (string) ($row['label'] ?? ''),
            'callsign' => $row['callsign'] ?? null,
            'color' => $this->normalizeColor((string) ($row['color'] ?? '#67e8f9')),
            'points' => $points,
            'visible_on_atak' => !empty($row['visible_on_atak']),
            'source_unit_key' => $row['source_unit_key'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    private function nullIfEmpty(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);

        return $s === '' ? null : mb_substr($s, 0, 160);
    }

    private function normalizeColor(string $color): string
    {
        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            return strtolower($color);
        }

        return '#67e8f9';
    }
}
