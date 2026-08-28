<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Core\Database;
use App\Support\PlatformStorageCatalog;
use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

final class PlatformStorageService
{
    public function __construct(
        private ?PDO $pdo = null
    ) {
        $this->pdo ??= Database::getPdo();
    }

    /**
     * @return array{
     *   path: string,
     *   total: ?int,
     *   free: ?int,
     *   used: ?int,
     *   percent_used: ?float
     * }
     */
    public function diskSnapshot(): array
    {
        $path = base_path();
        $total = @disk_total_space($path);
        $free = @disk_free_space($path);
        $totalI = is_float($total) || is_int($total) ? (int) $total : null;
        $freeI = is_float($free) || is_int($free) ? (int) $free : null;
        $used = ($totalI !== null && $freeI !== null) ? max(0, $totalI - $freeI) : null;
        $pct = ($totalI !== null && $totalI > 0 && $used !== null)
            ? round(($used / $totalI) * 100, 1)
            : null;

        return [
            'path' => $path,
            'total' => $totalI,
            'free' => $freeI,
            'used' => $used,
            'percent_used' => $pct,
        ];
    }

    /**
     * @return list<array{path: string, label: string, purgeable: bool, bytes: ?int, exists: bool}>
     */
    public function directorySnapshots(): array
    {
        $out = [];
        foreach (PlatformStorageCatalog::watchedDirectories() as $row) {
            $abs = base_path($row['path']);
            $exists = is_dir($abs);
            $out[] = [
                'path' => $row['path'],
                'label' => $row['label'],
                'purgeable' => $row['purgeable'],
                'exists' => $exists,
                'bytes' => $exists ? $this->directoryBytes($abs) : 0,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{name: string, rows: int, bytes: int, engine: string}>
     */
    public function largestTables(int $limit = 25): array
    {
        $limit = max(5, min(80, $limit));
        try {
            $sql = 'SELECT TABLE_NAME AS name, ENGINE AS engine,
                           COALESCE(TABLE_ROWS, 0) AS row_est,
                           COALESCE(DATA_LENGTH, 0) + COALESCE(INDEX_LENGTH, 0) AS bytes
                    FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA = DATABASE()
                    ORDER BY bytes DESC
                    LIMIT ' . $limit;
            $stmt = $this->pdo->query($sql);
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $name = (string) ($row['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $out[] = [
                'name' => $name,
                'rows' => (int) ($row['row_est'] ?? 0),
                'bytes' => (int) ($row['bytes'] ?? 0),
                'engine' => (string) ($row['engine'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * @return array{tables: list<array{name: string, exists: bool, rows: int, bytes: int}>, directories: list<array{path: string, bytes: ?int, exists: bool}>}
     */
    public function groupUsage(array $group): array
    {
        $tables = [];
        foreach ($group['tables'] as $name) {
            $tables[] = $this->tableUsage((string) $name);
        }
        $dirs = [];
        foreach ($group['directories'] as $rel) {
            $abs = base_path((string) $rel);
            $exists = is_dir($abs);
            $dirs[] = [
                'path' => (string) $rel,
                'exists' => $exists,
                'bytes' => $exists ? $this->directoryBytes($abs) : 0,
            ];
        }

        return ['tables' => $tables, 'directories' => $dirs];
    }

    /**
     * @return array{ok: bool, message: string, truncated: list<string>, files_removed: int}
     */
    public function purgeGroup(string $key): array
    {
        $group = PlatformStorageCatalog::groupByKey($key);
        if ($group === null) {
            return ['ok' => false, 'message' => 'Lot inconnu.', 'truncated' => [], 'files_removed' => 0];
        }

        $protected = array_fill_keys(PlatformStorageCatalog::protectedTables(), true);
        $truncated = [];
        foreach ($group['tables'] as $table) {
            $table = (string) $table;
            if ($table === '' || isset($protected[$table])) {
                continue;
            }
            if (!$this->tableExists($table)) {
                continue;
            }
            $this->emptyTable($table);
            $truncated[] = $table;
        }

        $files = 0;
        foreach ($group['directories'] as $rel) {
            $files += $this->emptyDirectory((string) $rel);
        }

        return [
            'ok' => true,
            'message' => 'Lot vidé.',
            'truncated' => $truncated,
            'files_removed' => $files,
        ];
    }

    public static function formatBytes(?int $bytes): string
    {
        if ($bytes === null) {
            return '—';
        }
        if ($bytes < 1024) {
            return $bytes . ' o';
        }
        $units = ['Ko', 'Mo', 'Go', 'To'];
        $value = (float) $bytes;
        foreach ($units as $unit) {
            $value /= 1024;
            if ($value < 1024) {
                return ($value >= 10 ? (string) round($value) : (string) round($value, 1)) . ' ' . $unit;
            }
        }

        return round($value, 1) . ' To';
    }

    /**
     * @return array{name: string, exists: bool, rows: int, bytes: int}
     */
    private function tableUsage(string $name): array
    {
        $empty = ['name' => $name, 'exists' => false, 'rows' => 0, 'bytes' => 0];
        if (!$this->tableExists($name)) {
            return $empty;
        }
        try {
            $stmt = $this->pdo->prepare(
                'SELECT COALESCE(TABLE_ROWS, 0) AS row_est,
                        COALESCE(DATA_LENGTH, 0) + COALESCE(INDEX_LENGTH, 0) AS bytes
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
                 LIMIT 1'
            );
            $stmt->execute([$name]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            return [
                'name' => $name,
                'exists' => true,
                'rows' => (int) ($row['row_est'] ?? 0),
                'bytes' => (int) ($row['bytes'] ?? 0),
            ];
        } catch (Throwable) {
            return ['name' => $name, 'exists' => true, 'rows' => 0, 'bytes' => 0];
        }
    }

    private function tableExists(string $name): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
            );
            $stmt->execute([$name]);

            return (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }

    private function emptyTable(string $table): void
    {
        $quoted = '`' . str_replace('`', '``', $table) . '`';
        try {
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            $this->pdo->exec('TRUNCATE TABLE ' . $quoted);
        } catch (Throwable) {
            try {
                $this->pdo->exec('DELETE FROM ' . $quoted);
            } catch (Throwable) {
            }
        } finally {
            try {
                $this->pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            } catch (Throwable) {
            }
        }
    }

    private function emptyDirectory(string $relative): int
    {
        $relative = str_replace('\\', '/', $relative);
        $relative = ltrim($relative, '/');
        if ($relative === '' || str_contains($relative, '..')) {
            return 0;
        }
        $root = realpath(base_path());
        $abs = base_path($relative);
        if ($root === false || !is_dir($abs)) {
            return 0;
        }
        $real = realpath($abs);
        if ($real === false || !str_starts_with($real, $root)) {
            return 0;
        }

        $removed = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($real, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            $path = $item->getPathname();
            $base = $item->getBasename();
            if ($base === '.gitignore' || $base === '.gitkeep') {
                continue;
            }
            if ($item->isFile() || $item->isLink()) {
                if (@unlink($path)) {
                    $removed++;
                }
            } elseif ($item->isDir()) {
                @rmdir($path);
            }
        }

        return $removed;
    }

    private function directoryBytes(string $abs): ?int
    {
        $real = realpath($abs);
        if ($real === false || !is_dir($real)) {
            return 0;
        }
        if (function_exists('shell_exec')) {
            $cmd = 'du -sb ' . escapeshellarg($real) . ' 2>/dev/null';
            $out = @shell_exec($cmd);
            if (is_string($out) && preg_match('/^(\d+)/', $out, $m) === 1) {
                return (int) $m[1];
            }
        }

        $bytes = 0;
        $deadline = microtime(true) + 2.5;
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($real, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iterator as $item) {
                if (microtime(true) > $deadline) {
                    return $bytes;
                }
                if ($item instanceof SplFileInfo && $item->isFile()) {
                    $bytes += (int) $item->getSize();
                }
            }
        } catch (Throwable) {
            return $bytes;
        }

        return $bytes;
    }
}
