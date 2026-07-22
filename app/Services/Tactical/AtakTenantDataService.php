<?php

declare(strict_types=1);

namespace App\Services\Tactical;

use App\Core\Database;
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
    private const TENANT_TABLES = [
        'atak_medical_alert_triage',
        'atak_orders',
        'atak_markers',
        'atak_units',
        'atak_chat_messages',
        'atak_pings',
        'atak_nine_line',
        'atak_intel_photos',
        'atak_designator_targets',
        'atak_sigint_reports',
        'atak_last_activity',
        'atak_air_assets',
        'atak_map_shapes',
        'atak_laser_codes',
        'atak_layers',
        'recon_images',
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
