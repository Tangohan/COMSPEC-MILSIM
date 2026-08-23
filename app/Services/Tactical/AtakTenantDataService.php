<?php

declare(strict_types=1);

namespace App\Services\Tactical;

use App\Core\Database;
use App\Support\AtakPlayNight;
use PDO;

/**
 * Export et purge des données opérationnelles ATAK / Tacmap d’une communauté.
 * Ne touche pas à la configuration (clé d’accès, serveur, consignes) ni aux liens opérateurs.
 */
final class AtakTenantDataService
{
    /**
     * Tables opérationnelles scopées par tenant_id.
     * Exclut atak_intel (journal legacy global, sans tenant_id) et atak_operator_ids (indicatifs liés).
     */
    private const MEDIA_TABLES = [
        'atak_intel_photos',
        'recon_images',
        'atak_poi_photos',
    ];

    private const OPERATIONAL_TABLES = [
        'atak_medical_alert_triage',
        'atak_orders',
        'atak_markers',
        'atak_units',
        'atak_chat_messages',
        'atak_pings',
        'atak_nine_line',
        'atak_designator_targets',
        'atak_sigint_reports',
        'atak_last_activity',
        'atak_air_assets',
        'atak_map_shapes',
        'atak_laser_codes',
        'atak_layers',
        'atak_explosive_timers',
        'atak_waypoints',
        'atak_waypoint_routes',
        'atak_qrf_waypoints',
        'atak_medevac_requests',
        'atak_tactical_zones',
        'atak_zone_alerts',
        'atak_poi',
        'atak_poi_observations',
        'atak_vehicle_tracking',
        'atak_tactical_reports',
        'atak_qrf_requests',
    ];

    private const TENANT_TABLES = [
        ...self::OPERATIONAL_TABLES,
        ...self::MEDIA_TABLES,
    ];

    public function __construct(
        private ?PDO $pdo = null,
        private ?AtakActivityLogService $activityLog = null,
    ) {
        $this->pdo ??= Database::getPdo();
        $this->activityLog ??= new AtakActivityLogService();
    }

    /**
     * Export complet (journal + tables) pour téléchargement admin.
     *
     * @return array<string, mixed>
     */
    public function exportAll(int $tenantId): array
    {
        if ($tenantId < 1) {
            return [];
        }

        $tables = [];
        foreach (self::TENANT_TABLES as $table) {
            if (!$this->isTenantScopedTable($table)) {
                continue;
            }
            $stmt = $this->pdo->prepare('SELECT * FROM `' . $table . '` WHERE tenant_id = ?');
            $stmt->execute([$tenantId]);
            $tables[$table] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        return [
            'exported_at' => date('c'),
            'tenant_id' => $tenantId,
            'activity_logs' => $this->activityLog->exportAllForTenant($tenantId),
            'tables' => $tables,
        ];
    }

    /**
     * Purge journal + données de mission. Conserve config et indicatifs liés.
     *
     * @return array{activity_files:int,tables:array<string,int>,photos_removed:int}
     */
    public function purgeAll(int $tenantId): array
    {
        if ($tenantId < 1) {
            return ['activity_files' => 0, 'tables' => [], 'photos_removed' => 0];
        }

        $photosRemoved = $this->purgeIntelPhotoFiles($tenantId);
        $activityFiles = $this->activityLog->purgeAllForTenant($tenantId);
        $tables = [];

        foreach (self::TENANT_TABLES as $table) {
            if (!$this->isTenantScopedTable($table)) {
                continue;
            }
            $stmt = $this->pdo->prepare('DELETE FROM `' . $table . '` WHERE tenant_id = ?');
            $stmt->execute([$tenantId]);
            $tables[$table] = $stmt->rowCount();
        }

        $this->purgeSessionCache($tenantId);

        return [
            'activity_files' => $activityFiles,
            'tables' => $tables,
            'photos_removed' => $photosRemoved,
        ];
    }

    /**
     * Vide la carte / l’historique visible, sans toucher aux photos.
     * Les liaisons opérateurs et les modèles d’ordres sont conservés.
     *
     * @return array{activity_archived:int,tables:array<string,int>}
     */
    public function resetTheatreKeepPhotos(int $tenantId, int $mapId = 0): array
    {
        if ($tenantId < 1) {
            return ['activity_archived' => 0, 'tables' => []];
        }

        $archived = 0;
        if ($this->activityLog !== null) {
            $useMap = $mapId > 0 ? $mapId : 1;
            $archived += $this->activityLog->archiveAll($tenantId, $useMap);
            if ($useMap !== AtakActivityLogService::AUTH_MAP_ID) {
                $archived += $this->activityLog->archiveAll($tenantId, AtakActivityLogService::AUTH_MAP_ID);
            }
        }

        $tables = [];
        foreach (self::OPERATIONAL_TABLES as $table) {
            if (!$this->isTenantScopedTable($table)) {
                continue;
            }
            try {
                $sql = 'DELETE FROM `' . $table . '` WHERE tenant_id = ?';
                $params = [$tenantId];
                if ($mapId > 0 && $this->columnExists($table, 'map_id')) {
                    $sql .= ' AND map_id = ?';
                    $params[] = $mapId;
                }
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
                $tables[$table] = $stmt->rowCount();
            } catch (\Throwable) {
                $tables[$table] = 0;
            }
        }

        return [
            'activity_archived' => $archived,
            'tables' => $tables,
        ];
    }

    /**
     * Supprime uniquement les photos d’une soirée (action manuelle).
     * Les photos déjà passées dans un dossier SSE sont conservées.
     *
     * @return array{recon_hidden:int,intel_removed:int}
     */
    public function purgePhotosForNight(int $tenantId, int $mapId, string $nightKey): array
    {
        if ($tenantId < 1) {
            return ['recon_hidden' => 0, 'intel_removed' => 0];
        }
        $key = AtakPlayNight::normalizeKey($nightKey) ?? AtakPlayNight::currentKey();
        $reconHidden = 0;
        $intelRemoved = 0;

        if ($this->isTenantScopedTable('recon_images')) {
            $cols = 'id, captured_at, created_at';
            $hasSse = $this->columnExists('recon_images', 'sse_case_id');
            if ($hasSse) {
                $cols .= ', sse_case_id';
            }
            $sql = 'SELECT ' . $cols . ' FROM recon_images WHERE tenant_id = ?';
            if ($this->columnExists('recon_images', 'deleted_at')) {
                $sql .= ' AND deleted_at IS NULL';
            }
            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$tenantId]);
                $now = date('Y-m-d H:i:s');
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    if ($hasSse && !empty($row['sse_case_id'])) {
                        continue;
                    }
                    $stamp = (string) (($row['captured_at'] ?? '') ?: ($row['created_at'] ?? ''));
                    if (AtakPlayNight::keyFromSql($stamp) !== $key) {
                        continue;
                    }
                    if ($this->columnExists('recon_images', 'deleted_at')) {
                        $upd = $this->pdo->prepare('UPDATE recon_images SET deleted_at = ? WHERE tenant_id = ? AND id = ?');
                        $upd->execute([$now, $tenantId, (int) $row['id']]);
                    } else {
                        $upd = $this->pdo->prepare('DELETE FROM recon_images WHERE tenant_id = ? AND id = ?');
                        $upd->execute([$tenantId, (int) $row['id']]);
                    }
                    $reconHidden += $upd->rowCount() > 0 ? 1 : 0;
                }
            } catch (\Throwable) {
                // Schéma incomplet : on continue avec les photos intel.
            }
        }

        if ($this->isTenantScopedTable('atak_intel_photos')) {
            $sql = 'SELECT id, path, filename, created_at FROM atak_intel_photos WHERE tenant_id = ?';
            $params = [$tenantId];
            if ($mapId > 0 && $this->columnExists('atak_intel_photos', 'map_id')) {
                $sql .= ' AND map_id = ?';
                $params[] = $mapId;
            }
            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
                $uploadRoot = base_path('public/uploads');
                $ids = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    if (AtakPlayNight::keyFromSql((string) ($row['created_at'] ?? '')) !== $key) {
                        continue;
                    }
                    $ids[] = (int) $row['id'];
                    $rel = trim((string) ($row['path'] ?? ''));
                    $candidates = [];
                    if ($rel !== '') {
                        $candidates[] = $uploadRoot . '/' . ltrim(str_replace('\\', '/', $rel), '/');
                    }
                    $fn = trim((string) ($row['filename'] ?? ''));
                    if ($fn !== '') {
                        $candidates[] = $uploadRoot . '/intel/' . basename($fn);
                    }
                    foreach ($candidates as $path) {
                        if (is_file($path)) {
                            @unlink($path);
                            break;
                        }
                    }
                }
                if ($ids !== []) {
                    $in = implode(',', array_fill(0, count($ids), '?'));
                    $del = $this->pdo->prepare('DELETE FROM atak_intel_photos WHERE tenant_id = ? AND id IN (' . $in . ')');
                    $del->execute(array_merge([$tenantId], $ids));
                    $intelRemoved = $del->rowCount();
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        return [
            'recon_hidden' => $reconHidden,
            'intel_removed' => $intelRemoved,
        ];
    }

    /**
     * @return list<array{key:string,label:string,count:int}>
     */
    public function listPhotoNights(int $tenantId, int $mapId = 0): array
    {
        $counts = [];
        $collect = function (string $sql, array $params, array $fields) use (&$counts): void {
            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $stamp = '';
                    foreach ($fields as $field) {
                        $stamp = trim((string) ($row[$field] ?? ''));
                        if ($stamp !== '') {
                            break;
                        }
                    }
                    $key = AtakPlayNight::keyFromSql($stamp);
                    $counts[$key] = ($counts[$key] ?? 0) + 1;
                }
            } catch (\Throwable) {
                // table absente
            }
        };

        if ($this->isTenantScopedTable('recon_images')) {
            $sql = 'SELECT captured_at, created_at FROM recon_images WHERE tenant_id = ?';
            if ($this->columnExists('recon_images', 'deleted_at')) {
                $sql .= ' AND deleted_at IS NULL';
            }
            $collect($sql, [$tenantId], ['captured_at', 'created_at']);
        }
        if ($this->isTenantScopedTable('atak_intel_photos')) {
            $sql = 'SELECT created_at FROM atak_intel_photos WHERE tenant_id = ?';
            $params = [$tenantId];
            if ($mapId > 0 && $this->columnExists('atak_intel_photos', 'map_id')) {
                $sql .= ' AND map_id = ?';
                $params[] = $mapId;
            }
            $collect($sql, $params, ['created_at']);
        }

        $nights = [];
        foreach ($counts as $key => $count) {
            $nights[] = [
                'key' => $key,
                'label' => AtakPlayNight::label($key),
                'count' => $count,
            ];
        }
        usort($nights, static fn (array $a, array $b): int => strcmp($b['key'], $a['key']));

        return $nights;
    }

    /** @return array<string, int> compteurs par table / fichier */
    public function summarize(int $tenantId): array
    {
        if ($tenantId < 1) {
            return [];
        }
        $out = [
            'activity_events' => $this->activityLog->countAllForTenant($tenantId),
        ];
        foreach (self::TENANT_TABLES as $table) {
            if (!$this->isTenantScopedTable($table)) {
                continue;
            }
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM `' . $table . '` WHERE tenant_id = ?');
            $stmt->execute([$tenantId]);
            $out[$table] = (int) $stmt->fetchColumn();
        }

        return $out;
    }

    private function purgeIntelPhotoFiles(int $tenantId): int
    {
        $removed = 0;
        $uploadRoot = base_path('public/uploads');

        if ($this->isTenantScopedTable('atak_intel_photos')) {
            $stmt = $this->pdo->prepare('SELECT path, filename FROM atak_intel_photos WHERE tenant_id = ?');
            $stmt->execute([$tenantId]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $rel = trim((string) ($row['path'] ?? ''));
                $candidates = [];
                if ($rel !== '') {
                    $candidates[] = $uploadRoot . '/' . ltrim(str_replace('\\', '/', $rel), '/');
                }
                $fn = trim((string) ($row['filename'] ?? ''));
                if ($fn !== '') {
                    $candidates[] = $uploadRoot . '/intel/' . basename($fn);
                }
                foreach ($candidates as $path) {
                    if (is_file($path) && @unlink($path)) {
                        $removed++;
                        break;
                    }
                }
            }
        }

        if ($this->isTenantScopedTable('recon_images')) {
            $stmt = $this->pdo->prepare('SELECT image_path, thumb_path FROM recon_images WHERE tenant_id = ?');
            $stmt->execute([$tenantId]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                foreach (['image_path', 'thumb_path'] as $col) {
                    $rel = trim((string) ($row[$col] ?? ''));
                    if ($rel === '') {
                        continue;
                    }
                    $path = str_starts_with($rel, '/') || preg_match('#^[A-Za-z]:\\\\#', $rel)
                        ? $rel
                        : $uploadRoot . '/' . ltrim(str_replace('\\', '/', $rel), '/');
                    if (is_file($path) && @unlink($path)) {
                        $removed++;
                    }
                }
            }
        }

        return $removed;
    }

    /** Table présente et bien scopée par tenant_id (évite les schémas legacy hors sync). */
    private function isTenantScopedTable(string $table): bool
    {
        return $this->tableExists($table) && $this->columnExists($table, 'tenant_id');
    }

    private function purgeSessionCache(int $tenantId): void
    {
        $dir = base_path('storage/cache/atak_sessions');
        if (!is_dir($dir)) {
            return;
        }
        foreach (glob($dir . '/*.json') ?: [] as $file) {
            $raw = @file_get_contents($file);
            if (!is_string($raw) || $raw === '') {
                continue;
            }
            $data = json_decode($raw, true);
            if (!is_array($data)) {
                continue;
            }
            if ((int) ($data['tenant_id'] ?? 0) === $tenantId) {
                @unlink($file);
            }
        }
    }

    private function tableExists(string $table): bool
    {
        static $cache = [];
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }
        try {
            $st = $this->pdo->prepare(
                'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
            );
            $st->execute([$table]);
            $cache[$table] = (bool) $st->fetchColumn();
        } catch (\Throwable) {
            $cache[$table] = false;
        }

        return $cache[$table];
    }

    private function columnExists(string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . '.' . $column;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        try {
            $st = $this->pdo->prepare(
                'SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
                 LIMIT 1'
            );
            $st->execute([$table, $column]);
            $cache[$key] = (bool) $st->fetchColumn();
        } catch (\Throwable) {
            $cache[$key] = false;
        }

        return $cache[$key];
    }
}
