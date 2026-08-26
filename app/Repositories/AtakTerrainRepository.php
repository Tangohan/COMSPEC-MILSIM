<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Services\Tactical\AtakTerrainMath;
use App\Support\AtakCopTerrainSchema;
use App\Support\LazyDatabaseConnection;
use PDO;
use Throwable;

final class AtakTerrainRepository
{
    use LazyDatabaseConnection;

    private ?string $rowCountColumn = null;

    public function __construct(?PDO $pdo = null)
    {
        AtakCopTerrainSchema::ensure();
        $this->pdo = $pdo;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getGrid(int $tenantId, int $mapId, bool $withHeights = true): ?array
    {
        if ($tenantId < 1 || $mapId < 1) {
            return null;
        }
        $rowCol = $this->rowCountColumn();
        $cols = [
            'id', 'tenant_id', 'map_id', 'world_name', 'world_size',
            'origin_x', 'origin_y', 'cell_m', 'cols', $rowCol,
            'min_z', 'max_z', 'filled_cells', 'ready', 'sampled_at', 'updated_at',
        ];
        if ($withHeights) {
            $cols[] = 'heights';
        }
        $select = implode(', ', array_map(static function (string $c): string {
            $safe = str_replace('`', '', $c);
            $expr = '`' . $safe . '`';
            if ($safe === 'rows') {
                return $expr . ' AS `grid_rows`';
            }

            return $expr;
        }, $cols));
        try {
            $st = $this->pdo()->prepare(
                "SELECT {$select} FROM `atak_terrain_grids` WHERE `tenant_id` = ? AND `map_id` = ? LIMIT 1"
            );
            $st->execute([$tenantId, $mapId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return null;
        }

        return is_array($row) ? $this->normalizeGridRow($row) : null;
    }

    /** @return array{terrain_filled: int, terrain_total: int, terrain_chunks: int, terrain_coverage_pct: int} */
    public function coverageSummary(int $tenantId, int $mapId): array
    {
        $empty = [
            'terrain_filled' => 0,
            'terrain_total' => 0,
            'terrain_chunks' => 0,
            'terrain_coverage_pct' => 0,
        ];
        if ($tenantId < 1 || $mapId < 1) {
            return $empty;
        }
        $grid = $this->getGrid($tenantId, $mapId, false);
        if (!is_array($grid)) {
            return $empty;
        }
        $cols = (int) ($grid['cols'] ?? 0);
        $rows = (int) ($grid['rows'] ?? $grid['grid_rows'] ?? 0);
        $filled = (int) ($grid['filled_cells'] ?? 0);
        $total = max(0, $cols * $rows);
        $chunks = 0;
        try {
            $st = $this->pdo()->prepare(
                'SELECT COUNT(*) FROM `atak_terrain_chunks` WHERE `tenant_id` = ? AND `map_id` = ?'
            );
            $st->execute([$tenantId, $mapId]);
            $chunks = (int) $st->fetchColumn();
        } catch (Throwable) {
        }

        return [
            'terrain_filled' => $filled,
            'terrain_total' => $total,
            'terrain_chunks' => $chunks,
            'terrain_coverage_pct' => $total > 0 ? (int) round(100 * $filled / $total) : 0,
        ];
    }

    /**
     * @param array<string, mixed> $meta
     * @param list<int|float> $heights
     * @return array<string, mixed>
     */
    public function upsertChunk(int $tenantId, int $mapId, array $meta, int $col0, int $row0, int $cw, int $rh, array $heights): array
    {
        $cell = (int) ($meta['cell_m'] ?? 50);
        if ($cell < 10) {
            $cell = 25;
        }
        if ($cell > 200) {
            $cell = 200;
        }
        $worldSize = (int) ($meta['world_size'] ?? 0);
        if ($worldSize < 1024) {
            $worldSize = 30720;
        }
        $cols = (int) ($meta['cols'] ?? (int) floor($worldSize / $cell) + 1);
        $rows = (int) ($meta['rows'] ?? $meta['grid_rows'] ?? $cols);
        $cols = max(8, min(2048, $cols));
        $rows = max(8, min(2048, $rows));
        $cw = max(1, min(64, $cw));
        $rh = max(1, min(64, $rh));
        $col0 = max(0, min($cols - 1, $col0));
        $row0 = max(0, min($rows - 1, $row0));
        $worldName = substr(trim((string) ($meta['world_name'] ?? '')), 0, 64);
        $ox = (float) ($meta['origin_x'] ?? 0);
        $oy = (float) ($meta['origin_y'] ?? 0);

        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $grid = $this->getGrid($tenantId, $mapId, true);
            if ($grid === null) {
                $blob = AtakTerrainMath::emptyBlob($cols * $rows);
                $rowCol = $this->rowCountColumn();
                $ins = $pdo->prepare(
                    "INSERT INTO `atak_terrain_grids` (
                        `tenant_id`, `map_id`, `world_name`, `world_size`, `origin_x`, `origin_y`,
                        `cell_m`, `cols`, `{$rowCol}`, `heights`, `filled_cells`, `ready`
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0)"
                );
                $ins->execute([$tenantId, $mapId, $worldName, $worldSize, $ox, $oy, $cell, $cols, $rows, $blob]);
                $grid = $this->getGrid($tenantId, $mapId, true);
            }
            if (!is_array($grid)) {
                $pdo->rollBack();

                return ['ok' => false, 'error' => 'Grille introuvable.'];
            }
            $gridId = (int) $grid['id'];
            $gCols = (int) $grid['cols'];
            $gRows = (int) ($grid['rows'] ?? $grid['grid_rows'] ?? 0);
            $blob = is_string($grid['heights'] ?? null) ? (string) $grid['heights'] : AtakTerrainMath::emptyBlob($gCols * $gRows);
            if (strlen($blob) < $gCols * $gRows * 2) {
                $blob = AtakTerrainMath::emptyBlob($gCols * $gRows);
            }

            $expected = $cw * $rh;
            $wrote = 0;
            $minZ = $grid['min_z'] !== null ? (int) $grid['min_z'] : null;
            $maxZ = $grid['max_z'] !== null ? (int) $grid['max_z'] : null;
            for ($r = 0; $r < $rh; $r++) {
                for ($c = 0; $c < $cw; $c++) {
                    $gc = $col0 + $c;
                    $gr = $row0 + $r;
                    if ($gc >= $gCols || $gr >= $gRows) {
                        continue;
                    }
                    $idx = $r * $cw + $c;
                    $raw = $heights[$idx] ?? null;
                    if (!is_numeric($raw)) {
                        continue;
                    }
                    $z = (int) round((float) $raw);
                    $off = ($gr * $gCols + $gc) * 2;
                    $prev = AtakTerrainMath::unpackInt16Le($blob, $gr * $gCols + $gc);
                    $packed = AtakTerrainMath::packInt16Le([$z]);
                    $blob = substr($blob, 0, $off) . $packed . substr($blob, $off + 2);
                    if ($prev === null) {
                        $wrote++;
                    }
                    $minZ = $minZ === null ? $z : min($minZ, $z);
                    $maxZ = $maxZ === null ? $z : max($maxZ, $z);
                }
            }

            $filled = (int) ($grid['filled_cells'] ?? 0) + $wrote;
            $total = $gCols * $gRows;
            $ready = $filled >= 9 ? 1 : 0;

            $upd = $pdo->prepare(
                'UPDATE `atak_terrain_grids`
                 SET `heights` = ?, `min_z` = ?, `max_z` = ?, `filled_cells` = ?, `ready` = ?, `sampled_at` = NOW(),
                     `world_name` = IF(`world_name` = "", ?, `world_name`)
                 WHERE `id` = ? AND `tenant_id` = ?'
            );
            $upd->execute([$blob, $minZ, $maxZ, $filled, $ready, $worldName, $gridId, $tenantId]);

            $chk = $pdo->prepare(
                'INSERT INTO `atak_terrain_chunks` (`tenant_id`, `map_id`, `grid_id`, `col0`, `row0`, `cw`, `rh`)
                 VALUES (?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE `cw` = VALUES(`cw`), `rh` = VALUES(`rh`), `received_at` = CURRENT_TIMESTAMP'
            );
            $chk->execute([$tenantId, $mapId, $gridId, $col0, $row0, $cw, $rh]);

            $pdo->commit();

            return [
                'ok' => true,
                'ready' => $ready === 1,
                'filled_cells' => $filled,
                'total_cells' => $total,
                'progress' => $total > 0 ? round($filled / $total, 3) : 0,
                'min_z' => $minZ,
                'max_z' => $maxZ,
                'wrote' => $wrote,
                'expected' => $expected,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return ['ok' => false, 'error' => 'Enregistrement du relief impossible.'];
        }
    }

    /**
     * Fusion d’échantillons ponctuels (x, y, z) dans la grille DEM.
     *
     * @param array<string, mixed> $meta
     * @param list<array<string, mixed>> $points
     * @return array<string, mixed>
     */
    public function upsertSamples(int $tenantId, int $mapId, array $meta, array $points): array
    {
        $cell = (int) ($meta['resolution'] ?? $meta['cell_m'] ?? 50);
        if ($cell < 10) {
            $cell = 25;
        }
        if ($cell > 200) {
            $cell = 200;
        }
        $worldSize = (int) ($meta['world_size'] ?? $meta['worldSize'] ?? 0);
        if ($worldSize < 1024) {
            $worldSize = 30720;
        }
        $worldName = substr(trim((string) ($meta['world'] ?? $meta['world_name'] ?? '')), 0, 64);
        $ox = (float) ($meta['origin_x'] ?? 0);
        $oy = (float) ($meta['origin_y'] ?? 0);
        $cols = (int) ($meta['cols'] ?? (int) floor($worldSize / $cell) + 1);
        $rows = (int) ($meta['rows'] ?? $meta['grid_rows'] ?? $cols);
        $cols = max(8, min(2048, $cols));
        $rows = max(8, min(2048, $rows));

        $chunkMeta = [
            'cell_m' => $cell,
            'world_size' => $worldSize,
            'world_name' => $worldName,
            'origin_x' => $ox,
            'origin_y' => $oy,
            'cols' => $cols,
            'rows' => $rows,
        ];

        $wroteTotal = 0;
        $last = ['ok' => true, 'wrote' => 0, 'ready' => false, 'filled_cells' => 0, 'total_cells' => $cols * $rows, 'progress' => 0];
        $bucket = [];
        foreach ($points as $p) {
            if (!is_array($p)) {
                continue;
            }
            $x = $p['x'] ?? null;
            $y = $p['y'] ?? null;
            $z = $p['z'] ?? $p['terrain_z'] ?? null;
            if (!is_numeric($x) || !is_numeric($y) || !is_numeric($z)) {
                continue;
            }
            $c = (int) round(((float) $x - $ox) / $cell);
            $r = (int) round(((float) $y - $oy) / $cell);
            if ($c < 0 || $r < 0 || $c >= $cols || $r >= $rows) {
                continue;
            }
            $col0 = intdiv($c, 32) * 32;
            $row0 = intdiv($r, 32) * 32;
            $key = $col0 . ':' . $row0;
            if (!isset($bucket[$key])) {
                $cw = min(32, $cols - $col0);
                $rh = min(32, $rows - $row0);
                $bucket[$key] = ['col0' => $col0, 'row0' => $row0, 'cw' => $cw, 'rh' => $rh, 'heights' => array_fill(0, $cw * $rh, null)];
            }
            $cw = $bucket[$key]['cw'];
            $idx = ($r - $row0) * $cw + ($c - $col0);
            if ($idx >= 0 && $idx < count($bucket[$key]['heights'])) {
                $bucket[$key]['heights'][$idx] = (float) $z;
            }
        }
        foreach ($bucket as $block) {
            $last = $this->upsertChunk(
                $tenantId,
                $mapId,
                $chunkMeta,
                (int) $block['col0'],
                (int) $block['row0'],
                (int) $block['cw'],
                (int) $block['rh'],
                $block['heights']
            );
            $wroteTotal += (int) ($last['wrote'] ?? 0);
            if (empty($last['ok'])) {
                return $last;
            }
        }
        $last['wrote'] = $wroteTotal;
        $last['points'] = count($points);

        return $last;
    }

    /**
     * L’API et le JS parlent encore de « rows ».
     * En base : `grid_rows` (schéma actuel) ou `rows` (tenants historiques, mot réservé MariaDB).
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeGridRow(array $row): array
    {
        if (!isset($row['rows']) && isset($row['grid_rows'])) {
            $row['rows'] = $row['grid_rows'];
        }
        if (!isset($row['grid_rows']) && isset($row['rows'])) {
            $row['grid_rows'] = $row['rows'];
        }

        return $row;
    }

    private function rowCountColumn(): string
    {
        if ($this->rowCountColumn !== null) {
            return $this->rowCountColumn;
        }
        $this->rowCountColumn = 'grid_rows';
        try {
            $st = $this->pdo()->query('SHOW COLUMNS FROM `atak_terrain_grids`');
            $fields = $st ? $st->fetchAll(PDO::FETCH_COLUMN, 0) : [];
            $names = [];
            foreach ($fields as $field) {
                $names[strtolower((string) $field)] = true;
            }
            if (isset($names['grid_rows'])) {
                $this->rowCountColumn = 'grid_rows';
            } elseif (isset($names['rows'])) {
                $this->rowCountColumn = 'rows';
            }
        } catch (Throwable) {
        }

        return $this->rowCountColumn;
    }
}
