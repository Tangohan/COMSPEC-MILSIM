<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * État cartographique permanent d’un dossier SSE (vue + pings/marqueurs).
 */
final class SseCaseMapRepository
{
    /** @var array<string,string> */
    public const KIND_LABELS = [
        'ping' => 'Ping',
        'marker' => 'Repère',
        'note' => 'Annotation',
        'site' => 'Site rattaché',
    ];

    public function __construct(private ?Database $db = null)
    {
        $this->db ??= Database::getInstance();
        try {
            $migration = require base_path('bootstrap/atak_sse_case_map_migration.php');
            if (is_callable($migration)) {
                $migration(Database::getPdo());
            }
        } catch (\Throwable) {
        }
    }

    /**
     * @return array<string,mixed>
     */
    public function getState(int $tenantId, int $caseId): array
    {
        try {
            $row = $this->db->fetchOne(
                'SELECT * FROM sse_case_map_state WHERE tenant_id = :t AND case_id = :c LIMIT 1',
                ['t' => $tenantId, 'c' => $caseId]
            );
        } catch (\Throwable) {
            $row = null;
        }

        if ($row === null) {
            return [
                'case_id' => $caseId,
                'map_id' => 1,
                'center_lat' => 48.8566,
                'center_lng' => 2.3522,
                'zoom' => 6,
                'atak_layer_enabled' => true,
                'snapshot_meta' => null,
                'updated_at' => null,
                'exists' => false,
            ];
        }

        $meta = $row['snapshot_meta'] ?? null;
        if (is_string($meta) && $meta !== '') {
            $decoded = json_decode($meta, true);
            $meta = is_array($decoded) ? $decoded : null;
        }

        return [
            'case_id' => $caseId,
            'map_id' => (int) ($row['map_id'] ?? 1),
            'center_lat' => (float) ($row['center_lat'] ?? 48.8566),
            'center_lng' => (float) ($row['center_lng'] ?? 2.3522),
            'zoom' => (int) ($row['zoom'] ?? 6),
            'atak_layer_enabled' => !empty($row['atak_layer_enabled']),
            'snapshot_meta' => $meta,
            'updated_at' => $row['updated_at'] ?? null,
            'exists' => true,
        ];
    }

    /**
     * @param array<string,mixed> $data
     */
    public function saveState(int $tenantId, int $caseId, array $data, ?int $userId = null): bool
    {
        $lat = (float) ($data['center_lat'] ?? 48.8566);
        $lng = (float) ($data['center_lng'] ?? 2.3522);
        $zoom = max(1, min(19, (int) ($data['zoom'] ?? 6)));
        $mapId = max(1, (int) ($data['map_id'] ?? 1));
        $enabled = array_key_exists('atak_layer_enabled', $data)
            ? (!empty($data['atak_layer_enabled']) ? 1 : 0)
            : 1;
        $meta = $data['snapshot_meta'] ?? null;
        $metaJson = null;
        if (is_array($meta)) {
            $metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE);
        } elseif (is_string($meta) && $meta !== '') {
            $metaJson = $meta;
        }

        try {
            $this->db->execute(
                'INSERT INTO sse_case_map_state
                    (case_id, tenant_id, map_id, center_lat, center_lng, zoom, atak_layer_enabled, snapshot_meta, updated_by)
                 VALUES (:c, :t, :m, :lat, :lng, :z, :en, :meta, :u)
                 ON DUPLICATE KEY UPDATE
                    map_id = VALUES(map_id),
                    center_lat = VALUES(center_lat),
                    center_lng = VALUES(center_lng),
                    zoom = VALUES(zoom),
                    atak_layer_enabled = VALUES(atak_layer_enabled),
                    snapshot_meta = COALESCE(VALUES(snapshot_meta), snapshot_meta),
                    updated_by = VALUES(updated_by)',
                [
                    'c' => $caseId,
                    't' => $tenantId,
                    'm' => $mapId,
                    'lat' => $lat,
                    'lng' => $lng,
                    'z' => $zoom,
                    'en' => $enabled,
                    'meta' => $metaJson,
                    'u' => $userId,
                ]
            );

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listFeatures(int $tenantId, int $caseId): array
    {
        try {
            $rows = $this->db->fetchAll(
                'SELECT * FROM sse_case_map_features
                  WHERE tenant_id = :t AND case_id = :c
                  ORDER BY sort_order ASC, id ASC',
                ['t' => $tenantId, 'c' => $caseId]
            );
        } catch (\Throwable) {
            return [];
        }

        return array_map([$this, 'hydrateFeature'], $rows);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function addFeature(int $tenantId, int $caseId, array $data): ?array
    {
        $kind = $this->normalizeKind((string) ($data['kind'] ?? 'ping'));
        $label = trim((string) ($data['label'] ?? ''));
        if ($label === '') {
            $label = self::KIND_LABELS[$kind] ?? 'Point';
        }

        $lat = $this->floatOrNull($data['lat'] ?? null);
        $lng = $this->floatOrNull($data['lng'] ?? null);
        $armaX = $this->floatOrNull($data['arma_x'] ?? null);
        $armaY = $this->floatOrNull($data['arma_y'] ?? null);
        if ($lat === null && $lng === null && $armaX === null && $armaY === null) {
            return null;
        }

        try {
            $id = $this->db->insert(
                'INSERT INTO sse_case_map_features
                    (tenant_id, case_id, kind, label, note, color, lat, lng, arma_x, arma_y, site_id,
                     sort_order, created_by, author_label)
                 VALUES (:t, :c, :k, :l, :n, :col, :lat, :lng, :ax, :ay, :sid, :ord, :by, :a)',
                [
                    't' => $tenantId,
                    'c' => $caseId,
                    'k' => $kind,
                    'l' => mb_substr($label, 0, 160),
                    'n' => ($note = trim((string) ($data['note'] ?? ''))) !== '' ? mb_substr($note, 0, 500) : null,
                    'col' => $this->normalizeColor((string) ($data['color'] ?? '#34d399')),
                    'lat' => $lat,
                    'lng' => $lng,
                    'ax' => $armaX,
                    'ay' => $armaY,
                    'sid' => ((int) ($data['site_id'] ?? 0)) ?: null,
                    'ord' => (int) ($data['sort_order'] ?? 0),
                    'by' => ((int) ($data['created_by'] ?? 0)) ?: null,
                    'a' => mb_substr((string) ($data['author_label'] ?? ''), 0, 160) ?: null,
                ]
            );
        } catch (\Throwable) {
            return null;
        }

        return $this->findFeature($tenantId, $caseId, (int) $id);
    }

    public function deleteFeature(int $tenantId, int $caseId, int $featureId): bool
    {
        try {
            return $this->db->execute(
                'DELETE FROM sse_case_map_features WHERE tenant_id = :t AND case_id = :c AND id = :i',
                ['t' => $tenantId, 'c' => $caseId, 'i' => $featureId]
            ) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array<string,mixed>|null */
    public function findFeature(int $tenantId, int $caseId, int $id): ?array
    {
        try {
            $row = $this->db->fetchOne(
                'SELECT * FROM sse_case_map_features WHERE tenant_id = :t AND case_id = :c AND id = :i LIMIT 1',
                ['t' => $tenantId, 'c' => $caseId, 'i' => $id]
            );
        } catch (\Throwable) {
            return null;
        }

        return $row === null ? null : $this->hydrateFeature($row);
    }

    /**
     * Points publiables sur la Tacmap ATAK (coordonnées terrain connues + calque activé).
     *
     * @return list<array<string,mixed>>
     */
    public function listAtakOverlay(int $tenantId, int $mapId = 0): array
    {
        $sql = 'SELECT f.*, s.reference_code AS case_ref, s.title AS case_title, s.status AS case_status
                  FROM sse_case_map_features f
                  INNER JOIN sse_cases s ON s.id = f.case_id AND s.tenant_id = f.tenant_id
                  INNER JOIN sse_case_map_state st ON st.case_id = f.case_id AND st.tenant_id = f.tenant_id
                 WHERE f.tenant_id = :t
                   AND st.atak_layer_enabled = 1
                   AND f.arma_x IS NOT NULL AND f.arma_y IS NOT NULL';
        $params = ['t' => $tenantId];
        if ($mapId > 0) {
            $sql .= ' AND st.map_id = :m';
            $params['m'] = $mapId;
        }
        $sql .= ' ORDER BY f.case_id ASC, f.id ASC';

        try {
            $rows = $this->db->fetchAll($sql, $params);
        } catch (\Throwable) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $feat = $this->hydrateFeature($row);
            $feat['case_ref'] = (string) ($row['case_ref'] ?? '');
            $feat['case_title'] = (string) ($row['case_title'] ?? '');
            $feat['case_status'] = (string) ($row['case_status'] ?? '');
            $out[] = $feat;
        }

        return $out;
    }

    /**
     * Sites des dossiers avec calque ATAK activé (positions terrain).
     *
     * @return list<array<string,mixed>>
     */
    public function listAtakSites(int $tenantId, int $mapId = 0): array
    {
        $sql = 'SELECT site.id AS site_id, site.case_id, site.name AS designation, site.grid_reference,
                       site.pos_x, site.pos_y, site.status AS site_status,
                       c.reference_code AS case_ref, c.title AS case_title
                  FROM sse_sites site
                  INNER JOIN sse_cases c ON c.id = site.case_id AND c.tenant_id = site.tenant_id
                  INNER JOIN sse_case_map_state st ON st.case_id = site.case_id AND st.tenant_id = site.tenant_id
                 WHERE site.tenant_id = :t
                   AND site.case_id IS NOT NULL
                   AND st.atak_layer_enabled = 1
                   AND site.pos_x IS NOT NULL AND site.pos_y IS NOT NULL';
        $params = ['t' => $tenantId];
        if ($mapId > 0) {
            $sql .= ' AND st.map_id = :m';
            $params['m'] = $mapId;
        }

        try {
            return $this->db->fetchAll($sql, $params);
        } catch (\Throwable) {
            return [];
        }
    }

    private function normalizeKind(string $kind): string
    {
        $kind = strtolower(trim($kind));

        return isset(self::KIND_LABELS[$kind]) ? $kind : 'ping';
    }

    private function normalizeColor(string $color): string
    {
        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            return strtolower($color);
        }

        return '#34d399';
    }

    private function floatOrNull(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (!is_numeric($v)) {
            return null;
        }

        return (float) $v;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function hydrateFeature(array $row): array
    {
        $kind = $this->normalizeKind((string) ($row['kind'] ?? 'ping'));

        return [
            'id' => (int) ($row['id'] ?? 0),
            'case_id' => (int) ($row['case_id'] ?? 0),
            'kind' => $kind,
            'kind_label' => self::KIND_LABELS[$kind] ?? 'Point',
            'label' => (string) ($row['label'] ?? ''),
            'note' => $row['note'] ?? null,
            'color' => $this->normalizeColor((string) ($row['color'] ?? '#34d399')),
            'lat' => isset($row['lat']) && $row['lat'] !== null ? (float) $row['lat'] : null,
            'lng' => isset($row['lng']) && $row['lng'] !== null ? (float) $row['lng'] : null,
            'arma_x' => isset($row['arma_x']) && $row['arma_x'] !== null ? (float) $row['arma_x'] : null,
            'arma_y' => isset($row['arma_y']) && $row['arma_y'] !== null ? (float) $row['arma_y'] : null,
            'site_id' => isset($row['site_id']) && $row['site_id'] !== null ? (int) $row['site_id'] : null,
            'author_label' => $row['author_label'] ?? null,
            'created_at' => $row['created_at'] ?? null,
        ];
    }
}
