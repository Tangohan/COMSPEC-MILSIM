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
            $ready = $total > 0 && $filled >= (int) floor($total * 0.96) ? 1 : 0;

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
