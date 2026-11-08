<?php

declare(strict_types=1);

namespace App\Services\Organization;

use App\Core\Database;
use App\Support\PlatformStorageCatalog;
use PDO;
use Throwable;

/**
 * Place prise par une communauté, et téléchargement / suppression de ses photos du jeu et de ses données SSE.
 * Tout est filtré par tenant_id (ou par la table parente) : aucune autre communauté n'est lue ni modifiée.
 * Les fichiers ne sont lus ou effacés que s'ils se trouvent réellement dans un dossier autorisé par le catalogue.
 */
final class CommunityDataStorageService
{
    private const BATCH = 500;

    public function __construct(private ?PDO $pdo = null)
    {
        $this->pdo ??= Database::getPdo();
    }

    /**
     * @return array{sets: list<array<string, mixed>>, shared_files: array{count: int, bytes: int}, total_bytes: int}
     */
    public function overview(int $tenantId): array
    {
        $sets = [];
        $total = 0;
        foreach (PlatformStorageCatalog::communityDataSets() as $set) {
            $stats = $this->setStats($set, $tenantId);
            $total += $stats['bytes'];
            $sets[] = ['key' => $set['key'], 'title' => $set['title'], 'blurb' => $set['blurb']] + $stats;
        }
        $shared = $this->sharedFiles($tenantId);

        return ['sets' => $sets, 'shared_files' => $shared, 'total_bytes' => $total + $shared['bytes']];
    }

    /**
     * Archive ZIP (données en JSON + fichiers) d'un lot de la communauté.
     *
     * @return array{path: string, rows: int, files: int}|null chemin d'un fichier temporaire à supprimer par l'appelant
     */
    public function buildArchive(int $tenantId, string $setKey): ?array
    {
        $set = PlatformStorageCatalog::communitySetByKey($setKey);
        if ($set === null || $tenantId < 1 || !class_exists(\ZipArchive::class)) {
            return null;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'cs_data_');
        if ($tmp === false) {
            return null;
        }
        @unlink($tmp);
        $zip = new \ZipArchive();
        if ($zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return null;
        }
        $rows = 0;
        $files = 0;
        $used = [];
        foreach ($set['sources'] as $source) {
            $lines = [];
            foreach ($this->eachRow($source, $tenantId, ['t.*']) as $row) {
                $lines[] = $row;
                $rows++;
                foreach ($this->filesOf($source, $row) as $abs) {
                    $name = 'fichiers/' . $source['table'] . '/' . ((int) $row[$source['id']]) . '_' . basename($abs);
                    if (isset($used[$name])) {
                        continue;
                    }
                    $used[$name] = true;
                    if ($zip->addFile($abs, $name)) {
                        $files++;
                    }
                }
            }
            $zip->addFromString(
                'donnees/' . $source['table'] . '.json',
                (string) json_encode($lines, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR)
            );
        }
        $zip->addFromString('lisez-moi.txt', $set['title'] . "\nExport du " . date('d/m/Y H:i') . ".\nDonnées de votre communauté uniquement.\n");
        $zip->close();

        return ['path' => $tmp, 'rows' => $rows, 'files' => $files];
    }

    /**
     * Supprime les lignes puis les fichiers d'un lot de la communauté.
     *
     * @return array{rows: int, files: int, bytes: int}|null null si le lot est inconnu
     */
    public function purge(int $tenantId, string $setKey): ?array
    {
        $set = PlatformStorageCatalog::communitySetByKey($setKey);
        if ($set === null || $tenantId < 1) {
            return null;
        }
        $rows = 0;
        $files = 0;
        $bytes = 0;
        foreach ($set['sources'] as $source) {
            $cols = array_merge(['t.`' . $source['id'] . '`'], array_map(static fn (string $c): string => 't.`' . $c . '`', $source['file_columns']));
            do {
                $batch = [];
                foreach ($this->eachRow($source, $tenantId, $cols, self::BATCH) as $row) {
                    $batch[] = $row;
                }
                if ($batch === []) {
                    break;
                }
                $ids = array_map(static fn (array $r): int => (int) $r[$source['id']], $batch);
                $marks = implode(',', array_fill(0, count($ids), '?'));
                try {
                    $del = $this->pdo->prepare('DELETE FROM `' . $source['table'] . '` WHERE `' . $source['id'] . '` IN (' . $marks . ')');
                    $del->execute($ids);
                } catch (Throwable $e) {
                    error_log('[community data purge] ' . $source['table'] . ' : ' . $e->getMessage());
                    break;
                }
                $rows += count($ids);
                foreach ($batch as $row) {
                    foreach ($this->filesOf($source, $row) as $abs) {
                        $size = (int) @filesize($abs);
                        if (@unlink($abs)) {
                            $files++;
                            $bytes += $size;
                        }
                    }
                }
            } while (count($batch) >= self::BATCH);
        }

        return ['rows' => $rows, 'files' => $files, 'bytes' => $bytes];
    }

    /**
     * Chemin absolu d'un fichier lié à une ligne, seulement s'il est dans un dossier autorisé (aucun « .. »).
     *
     * @param list<string> $roots dossiers relatifs à la racine du site
     */
    public static function safeFile(string $relative, array $roots): ?string
    {
        $rel = trim(str_replace('\\', '/', $relative));
        if ($rel === '' || str_contains($rel, "\0") || preg_match('#(^|/)\.\.(/|$)#', $rel) === 1 || preg_match('#^[a-z][a-z0-9+.-]*:#i', $rel) === 1) {
            return null;
        }
        $rel = ltrim($rel, '/');
        foreach ($roots as $root) {
            $realRoot = realpath(base_path($root));
            if ($realRoot === false) {
                continue;
            }
            foreach ([base_path('public/' . $rel), base_path($rel), base_path('storage/' . $rel)] as $candidate) {
                $real = realpath($candidate);
                if ($real !== false && is_file($real) && str_starts_with($real, $realRoot . DIRECTORY_SEPARATOR)) {
                    return $real;
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $set
     * @return array{rows: int, files: int, bytes: int}
     */
    private function setStats(array $set, int $tenantId): array
    {
        $rows = 0;
        $files = 0;
        $bytes = 0;
        foreach ($set['sources'] as $source) {
            try {
                $st = $this->pdo->prepare('SELECT COUNT(*) FROM `' . $source['table'] . '` t WHERE ' . $source['tenant_sql']);
                $st->execute(['t' => $tenantId]);
                $rows += (int) $st->fetchColumn();
            } catch (Throwable) {
                continue;
            }
            if ($source['file_columns'] === []) {
                continue;
            }
            $cols = array_merge(['t.`' . $source['id'] . '`'], array_map(static fn (string $c): string => 't.`' . $c . '`', $source['file_columns']));
            foreach ($this->eachRow($source, $tenantId, $cols) as $row) {
                foreach ($this->filesOf($source, $row) as $abs) {
                    $files++;
                    $bytes += (int) @filesize($abs);
                }
            }
        }

        return ['rows' => $rows, 'files' => $files, 'bytes' => $bytes];
    }

    /** @return array{count: int, bytes: int} */
    private function sharedFiles(int $tenantId): array
    {
        try {
            $st = $this->pdo->prepare('SELECT COUNT(*) AS n, COALESCE(SUM(size_bytes), 0) AS b FROM tenant_share_files WHERE tenant_id = ? AND deleted_at IS NULL');
            $st->execute([$tenantId]);
            $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];

            return ['count' => (int) ($r['n'] ?? 0), 'bytes' => (int) ($r['b'] ?? 0)];
        } catch (Throwable) {
            return ['count' => 0, 'bytes' => 0];
        }
    }

    /**
     * Parcourt les lignes de la communauté par paquets (clé primaire croissante).
     *
     * @param array<string, mixed> $source
     * @param list<string> $columns
     * @return \Generator<int, array<string, mixed>>
     */
    private function eachRow(array $source, int $tenantId, array $columns, int $maxRows = 0): \Generator
    {
        $last = 0;
        $sent = 0;
        $idCol = '`' . $source['id'] . '`';
        $sql = 'SELECT ' . implode(', ', $columns) . ' FROM `' . $source['table'] . '` t WHERE (' . $source['tenant_sql']
            . ') AND t.' . $idCol . ' > :last ORDER BY t.' . $idCol . ' ASC LIMIT ' . self::BATCH;
        while (true) {
            try {
                $st = $this->pdo->prepare($sql);
                $st->execute(['t' => $tenantId, 'last' => $last]);
                $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable) {
                return;
            }
            foreach ($rows as $row) {
                $last = max($last, (int) ($row[$source['id']] ?? 0));
                yield $row;
                $sent++;
                if ($maxRows > 0 && $sent >= $maxRows) {
                    return;
                }
            }
            if (count($rows) < self::BATCH) {
                return;
            }
        }
    }

    /**
     * @param array<string, mixed> $source
     * @param array<string, mixed> $row
     * @return list<string>
     */
    private function filesOf(array $source, array $row): array
    {
        $out = [];
        foreach ($source['file_columns'] as $col) {
            $abs = self::safeFile((string) ($row[$col] ?? ''), $source['roots']);
            if ($abs !== null) {
                $out[$abs] = $abs;
            }
        }

        return array_values($out);
    }
}
