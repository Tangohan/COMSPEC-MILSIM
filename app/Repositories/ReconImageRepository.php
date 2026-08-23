<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\LazyDatabaseConnection;

use App\Core\Database;
use PDO;

class ReconImageRepository
{
    use LazyDatabaseConnection;


    /** @var array<string, bool> */
    private array $columnCache = [];

    public function __construct()
    {
        $this->pdo = Database::getPdo();
        $this->ensureSchema();
    }

    private function ensureSchema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $path = base_path('bootstrap/atak_recon_images_actions_migration.php');
        if (is_file($path)) {
            $migrate = require $path;
            if (is_callable($migrate)) {
                try {
                    $migrate($this->pdo);
                } catch (\Throwable) {
                }
            }
        }
        $done = true;
    }

    private function hasColumn(string $column): bool
    {
        if (array_key_exists($column, $this->columnCache)) {
            return $this->columnCache[$column];
        }
        try {
            $st = $this->pdo()->prepare(
                "SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'recon_images' AND COLUMN_NAME = ? LIMIT 1"
            );
            $st->execute([$column]);
            $this->columnCache[$column] = (bool) $st->fetchColumn();
        } catch (\Throwable) {
            $this->columnCache[$column] = false;
        }

        return $this->columnCache[$column];
    }

    public function tablesReady(): bool
    {
        try {
            $st = $this->pdo()->query(
                "SELECT 1 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'recon_images' LIMIT 1"
            );

            return $st !== false && (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    public function list(int $tenantId, ?string $missionId = null, ?string $author = null, ?string $dateFrom = null, ?string $dateTo = null, ?int $limit = 100): array
    {
        if (!$this->tablesReady() || $tenantId < 1) {
            return [];
        }
        try {
            $sql = 'SELECT * FROM recon_images WHERE tenant_id = ?';
            $params = [$tenantId];
            if ($this->hasColumn('deleted_at')) {
                $sql .= ' AND deleted_at IS NULL';
            }
            if ($missionId !== null && $missionId !== '') {
                $sql .= ' AND mission_id = ?';
                $params[] = $missionId;
            }
            if ($author !== null && $author !== '') {
                $sql .= ' AND author_callsign = ?';
                $params[] = $author;
            }
            if ($dateFrom !== null && $dateFrom !== '') {
                $sql .= ' AND (captured_at >= ? OR created_at >= ?)';
                $params[] = $dateFrom;
                $params[] = $dateFrom;
            }
            if ($dateTo !== null && $dateTo !== '') {
                $sql .= ' AND (captured_at <= ? OR created_at <= ?)';
                $params[] = $dateTo;
                $params[] = $dateTo;
            }
            $sql .= ' ORDER BY created_at DESC LIMIT ' . (int) $limit;
            $stmt = $this->pdo()->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    public function get(int $tenantId, int $id): ?array
    {
        if (!$this->tablesReady() || $tenantId < 1 || $id < 1) {
            return null;
        }
        try {
            $stmt = $this->pdo()->prepare('SELECT * FROM recon_images WHERE tenant_id = ? AND id = ?');
            $stmt->execute([$tenantId, $id]);

            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function create(int $tenantId, array $data): array
    {
        if (!$this->tablesReady() || $tenantId < 1) {
            return [];
        }

        $cols = [
            'tenant_id' => $tenantId,
            'mission_id' => $data['mission_id'] ?? null,
            'author_callsign' => $data['author_callsign'] ?? $data['author'] ?? 'Unknown',
            'unit_name' => $data['unit_name'] ?? null,
            'side' => $data['side'] ?? 'WEST',
            'image_path' => $data['image_path'],
            'thumb_path' => $data['thumb_path'] ?? null,
            'caption' => $data['caption'] ?? null,
            'pos_x' => $data['pos_x'] ?? null,
            'pos_y' => $data['pos_y'] ?? null,
            'pos_z' => $data['pos_z'] ?? null,
            'grid_ref' => $data['grid_ref'] ?? null,
            'heading' => $data['heading'] ?? null,
            'altitude' => $data['altitude'] ?? null,
            'device_type' => $data['device_type'] ?? 'CTAB',
            'captured_at' => isset($data['captured_at']) ? date('Y-m-d H:i:s', (int) $data['captured_at']) : null,
            'atak_cas_id' => $data['atak_cas_id'] ?? null,
        ];
        if ($this->hasColumn('fx_profile')) {
            $cols['fx_profile'] = $data['fx_profile'] ?? null;
        }
        if ($this->hasColumn('fx_intensity')) {
            $cols['fx_intensity'] = $data['fx_intensity'] ?? null;
        }

        $names = array_keys($cols);
        $placeholders = array_fill(0, count($names), '?');
        $sql = 'INSERT INTO recon_images (' . implode(', ', $names) . ') VALUES (' . implode(', ', $placeholders) . ')';
        try {
            $stmt = $this->pdo()->prepare($sql);
            $stmt->execute(array_values($cols));
            $id = (int) $this->pdo()->lastInsertId();

            return $this->get($tenantId, $id) ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    public function linkToCas(int $tenantId, int $id, int $atakCasId): ?array
    {
        try {
            $stmt = $this->pdo()->prepare('UPDATE recon_images SET atak_cas_id = ? WHERE tenant_id = ? AND id = ?');
            $stmt->execute([$atakCasId, $tenantId, $id]);
            if ($stmt->rowCount() === 0) {
                return null;
            }

            return $this->get($tenantId, $id);
        } catch (\Throwable) {
            return null;
        }
    }

    public function updateOps(int $tenantId, int $id, array $data): ?array
    {
        $fields = [];
        $params = ['tenant_id' => $tenantId, 'id' => $id];

        if (array_key_exists('operator_comment', $data) && $this->hasColumn('operator_comment')) {
            $fields[] = 'operator_comment = :operator_comment';
            $comment = trim((string) ($data['operator_comment'] ?? ''));
            $params['operator_comment'] = $comment !== '' ? $comment : null;
        }
        if (array_key_exists('is_blurred', $data) && $this->hasColumn('is_blurred')) {
            $fields[] = 'is_blurred = :is_blurred';
            $params['is_blurred'] = !empty($data['is_blurred']) ? 1 : 0;
        }
        if (array_key_exists('deleted_at', $data) && $this->hasColumn('deleted_at')) {
            $fields[] = 'deleted_at = :deleted_at';
            $params['deleted_at'] = $data['deleted_at'];
        }
        if (array_key_exists('sse_case_id', $data) && $this->hasColumn('sse_case_id')) {
            $fields[] = 'sse_case_id = :sse_case_id';
            $params['sse_case_id'] = $data['sse_case_id'] !== null ? (int) $data['sse_case_id'] : null;
        }
        if (array_key_exists('sse_evidence_id', $data) && $this->hasColumn('sse_evidence_id')) {
            $fields[] = 'sse_evidence_id = :sse_evidence_id';
            $params['sse_evidence_id'] = $data['sse_evidence_id'] !== null ? (int) $data['sse_evidence_id'] : null;
        }
        if (array_key_exists('sse_transferred_at', $data) && $this->hasColumn('sse_transferred_at')) {
            $fields[] = 'sse_transferred_at = :sse_transferred_at';
            $params['sse_transferred_at'] = $data['sse_transferred_at'];
        }

        if ($fields === []) {
            return $this->get($tenantId, $id);
        }

        try {
            $sql = 'UPDATE recon_images SET ' . implode(', ', $fields) . ' WHERE tenant_id = :tenant_id AND id = :id';
            $stmt = $this->pdo()->prepare($sql);
            $stmt->execute($params);

            return $this->get($tenantId, $id);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Dernière image par feed (unit_name = feed_id) ou par couple auteur + type d’appareil.
     *
     * @param list<string> $feedIds
     * @return array{by_feed: array<string, array<string, mixed>>, by_author_device: array<string, array<string, mixed>>, by_author: array<string, array<string, mixed>>, recent: list<array<string, mixed>>}
     */
    public function latestSnapshots(int $tenantId, array $feedIds = [], int $limit = 80): array
    {
        $empty = ['by_feed' => [], 'by_author_device' => [], 'by_author' => [], 'recent' => []];
        if (!$this->tablesReady() || $tenantId < 1) {
            return $empty;
        }
        try {
            $sql = 'SELECT * FROM recon_images WHERE tenant_id = ?';
            if ($this->hasColumn('deleted_at')) {
                $sql .= ' AND deleted_at IS NULL';
            }
            $sql .= ' ORDER BY created_at DESC LIMIT ' . (int) $limit;
            $stmt = $this->pdo()->prepare($sql);
            $stmt->execute([$tenantId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return $empty;
        }

        $byFeed = [];
        $byAuthorDevice = [];
        $byAuthorAny = [];
        $feedSet = [];
        foreach ($feedIds as $fid) {
            $fid = trim((string) $fid);
            if ($fid !== '') {
                $feedSet[$fid] = true;
            }
        }
        foreach ($rows as $row) {
            $unit = trim((string) ($row['unit_name'] ?? ''));
            if ($unit !== '' && !isset($byFeed[$unit])) {
                if ($feedSet === [] || isset($feedSet[$unit])) {
                    $byFeed[$unit] = $row;
                }
            }
            $author = strtoupper(trim((string) ($row['author_callsign'] ?? '')));
            $device = strtoupper(trim((string) ($row['device_type'] ?? 'CTAB')));
            if ($author !== '') {
                $key = $author . ':' . $device;
                if (!isset($byAuthorDevice[$key])) {
                    $byAuthorDevice[$key] = $row;
                }
                if (!isset($byAuthorAny[$author])) {
                    $byAuthorAny[$author] = $row;
                }
            }
        }

        return [
            'by_feed' => $byFeed,
            'by_author_device' => $byAuthorDevice,
            'by_author' => $byAuthorAny,
            'recent' => $rows,
        ];
    }
}
