<?php

declare(strict_types=1);

namespace App\Services\Backup;

use App\Core\Database;
use PDO;
use RuntimeException;

/**
 * Copie complète des données (MySQL + fichiers métier) et restauration pour rollback.
 *
 * Réutilisable depuis le CLI (`scripts/data-snapshot.php`) ou un autre service PHP.
 */
final class CompleteDataSnapshotService
{
    public const MANIFEST_VERSION = 1;

    /** @var list<string> Chemins relatifs à la racine du projet. */
    public const STORAGE_TREES = [
        'storage/uploads',
        'storage/documents',
        'storage/intel',
        'storage/atak-mod',
        'storage/atak_terrain',
        'storage/mail-outbox',
        'public/uploads',
    ];

    private const ID_PATTERN = '/^[0-9]{8}T[0-9]{6}Z(?:_[a-z0-9][a-z0-9._-]{0,40})?$/';

    public function __construct(
        private string $projectRoot,
        private ?PDO $pdo = null,
        /** @var array{host?:string,port?:int|string,database?:string,username?:string,password?:string,charset?:string}|null */
        private ?array $dbConfig = null,
        private ?MysqlSnapshotIo $mysql = null,
        private ?string $snapshotRootOverride = null,
    ) {
        $this->projectRoot = rtrim($this->projectRoot, '/\\');
        $resolved = realpath($this->projectRoot);
        if (is_string($resolved) && $resolved !== '') {
            $this->projectRoot = $resolved;
        }
        $this->mysql ??= new MysqlSnapshotIo();
    }

    public static function fromApp(): self
    {
        $cfg = config('database.connections.mysql');
        if (!is_array($cfg)) {
            $cfg = [];
        }

        return new self(base_path(), Database::getPdo(), $cfg);
    }

    /**
     * @param callable(string):void|null $log
     * @return array<string, mixed>
     */
    public function create(
        string $label = '',
        bool $includeDatabase = true,
        bool $includeStorage = true,
        ?int $keep = null,
        ?callable $log = null,
    ): array {
        $log ??= static function (string $line): void {};
        if (!$includeDatabase && !$includeStorage) {
            throw new RuntimeException('Il faut copier la base, les fichiers, ou les deux.');
        }

        $id = gmdate('Ymd\THis\Z');
        $cleanLabel = self::sanitizeLabel($label);
        if ($cleanLabel !== '') {
            $id .= '_' . $cleanLabel;
        }

        $dir = $this->snapshotRoot() . DIRECTORY_SEPARATOR . $id;
        if (is_dir($dir)) {
            throw new RuntimeException('Une copie porte déjà cet identifiant : ' . $id);
        }
        if (!mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('Impossible de créer le dossier de copie.');
        }

        $manifest = [
            'version' => self::MANIFEST_VERSION,
            'id' => $id,
            'created_at' => gmdate('c'),
            'label' => $cleanLabel,
            'app_version' => function_exists('platform_app_version') ? platform_app_version() : '',
            'git_sha' => $this->gitSha(),
            'database' => ['included' => false],
            'storage' => ['included' => false],
        ];

        try {
            if ($includeDatabase) {
                $gz = $dir . DIRECTORY_SEPARATOR . 'database.sql.gz';
                $db = $this->dumpDatabase($gz, $log);
                $manifest['database'] = [
                    'included' => true,
                    'name' => $db['name'],
                    'method' => $db['method'],
                    'file' => 'database.sql.gz',
                    'sha256' => self::sha256File($gz),
                    'bytes' => $db['bytes'],
                ];
            }
            if ($includeStorage) {
                $filesDir = $dir . DIRECTORY_SEPARATOR . 'files';
                $indexPath = $dir . DIRECTORY_SEPARATOR . 'files-index.json';
                $stored = $this->archiveStorage($filesDir, $indexPath, $log);
                $manifest['storage'] = [
                    'included' => true,
                    'file' => 'files-index.json',
                    'sha256' => is_file($indexPath) ? self::sha256File($indexPath) : '',
                    'bytes' => (int) ($stored['bytes'] ?? 0),
                    'trees' => self::STORAGE_TREES,
                    'file_count' => (int) ($stored['count'] ?? 0),
                ];
            }
            $this->writeManifest($dir, $manifest);
            $this->writeChecksums($dir, $manifest);
        } catch (\Throwable $e) {
            $this->deleteDirectory($dir);
            throw $e;
        }

        $kept = $this->prune($keep);
        if ($kept > 0) {
            $log('Anciennes copies retirées : ' . $kept . '.');
        }

        $log('Copie prête : ' . $id);

        return $manifest;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        $root = $this->snapshotRoot();
        if (!is_dir($root)) {
            return [];
        }
        $out = [];
        $entries = scandir($root);
        if ($entries === false) {
            return [];
        }
        rsort($entries, SORT_STRING);
        foreach ($entries as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $dir = $root . DIRECTORY_SEPARATOR . $name;
            if (!is_dir($dir)) {
                continue;
            }
            try {
                $manifest = $this->readManifest($name);
            } catch (\RuntimeException) {
                continue;
            }
            if ($manifest === null) {
                continue;
            }
            $out[] = $manifest;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function readManifest(string $id): ?array
    {
        $id = $this->assertSnapshotId($id);
        $path = $this->snapshotDir($id) . DIRECTORY_SEPARATOR . 'manifest.json';
        if (!is_file($path)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : null;
    }

    /**
     * @return array{ok: bool, errors: list<string>}
     */
    public function verify(string $id): array
    {
        $manifest = $this->readManifest($id);
        if ($manifest === null) {
            return ['ok' => false, 'errors' => ['Copie introuvable.']];
        }
        $dir = $this->snapshotDir($id);
        $errors = [];

        $dbMeta = is_array($manifest['database'] ?? null) ? $manifest['database'] : [];
        if (!empty($dbMeta['included'])) {
            $gz = $dir . DIRECTORY_SEPARATOR . 'database.sql.gz';
            if (!is_file($gz)) {
                $errors[] = 'Fichier manquant : database.sql.gz';
            } else {
                $expected = (string) ($dbMeta['sha256'] ?? '');
                if ($expected !== '' && !hash_equals($expected, self::sha256File($gz))) {
                    $errors[] = 'Empreinte altérée : database.sql.gz';
                }
            }
        }

        $stMeta = is_array($manifest['storage'] ?? null) ? $manifest['storage'] : [];
        if (!empty($stMeta['included'])) {
            $indexPath = $dir . DIRECTORY_SEPARATOR . 'files-index.json';
            if (!is_file($indexPath)) {
                $errors[] = 'Fichier manquant : files-index.json';
            } else {
                $expected = (string) ($stMeta['sha256'] ?? '');
                if ($expected !== '' && !hash_equals($expected, self::sha256File($indexPath))) {
                    $errors[] = 'Empreinte altérée : files-index.json';
                }
                $index = json_decode((string) file_get_contents($indexPath), true);
                if (!is_array($index)) {
                    $errors[] = 'Index des fichiers illisible.';
                } else {
                    $filesRoot = $dir . DIRECTORY_SEPARATOR . 'files';
                    foreach ($index as $rel => $meta) {
                        if (!is_string($rel) || !$this->isAllowedStoragePath($rel)) {
                            continue;
                        }
                        $path = $filesRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
                        if (!is_file($path)) {
                            $errors[] = 'Fichier manquant : ' . $rel;
                            continue;
                        }
                        $want = is_array($meta) ? (string) ($meta['sha256'] ?? '') : '';
                        if ($want !== '' && !hash_equals($want, self::sha256File($path))) {
                            $errors[] = 'Empreinte altérée : ' . $rel;
                        }
                    }
                }
            }
        }

        return ['ok' => $errors === [], 'errors' => $errors];
    }

    /**
     * @param callable(string):void|null $log
     * @return array<string, mixed>
     */
    public function restore(
        string $id,
        bool $database = true,
        bool $storage = true,
        bool $pruneStorage = false,
        bool $dryRun = false,
        ?callable $log = null,
    ): array {
        $log ??= static function (string $line): void {};
        $id = $this->assertSnapshotId($id);
        $manifest = $this->readManifest($id);
        if ($manifest === null) {
            throw new RuntimeException('Copie introuvable : ' . $id);
        }
        $check = $this->verify($id);
        if (!$check['ok']) {
            throw new RuntimeException('Copie altérée : ' . implode(' ', $check['errors']));
        }

        $dir = $this->snapshotDir($id);
        $didDb = false;
        $didStorage = false;
        $dbIncluded = !empty($manifest['database']['included']);
        $storageIncluded = !empty($manifest['storage']['included']);

        if ($database && !$dbIncluded) {
            $log('Cette copie ne contient pas la base — ignorée.');
            $database = false;
        }
        if ($storage && !$storageIncluded) {
            $log('Cette copie ne contient pas les fichiers — ignorés.');
            $storage = false;
        }
        if (!$database && !$storage) {
            throw new RuntimeException('Rien à restaurer dans cette copie.');
        }

        if ($dryRun) {
            $log('Simulation : base=' . ($database ? 'oui' : 'non') . ' fichiers=' . ($storage ? 'oui' : 'non') . ' prune=' . ($pruneStorage ? 'oui' : 'non'));
            $log('Dossier : ' . $dir);

            return ['id' => $id, 'dry_run' => true, 'database' => $database, 'storage' => $storage];
        }

        if ($database) {
            $this->restoreDatabase($dir . DIRECTORY_SEPARATOR . 'database.sql.gz', $log);
            $didDb = true;
        }
        if ($storage) {
            $this->restoreStorage($dir . DIRECTORY_SEPARATOR . 'files', $dir . DIRECTORY_SEPARATOR . 'files-index.json', $pruneStorage, $log);
            $didStorage = true;
        }

        $log('Rollback terminé depuis ' . $id);

        return [
            'id' => $id,
            'dry_run' => false,
            'database' => $didDb,
            'storage' => $didStorage,
        ];
    }

    public function prune(?int $keep = null): int
    {
        $keep = $keep ?? $this->defaultKeep();
        if ($keep < 1) {
            return 0;
        }
        $all = $this->list();
        if (count($all) <= $keep) {
            return 0;
        }
        $removed = 0;
        foreach (array_slice($all, $keep) as $row) {
            $id = (string) ($row['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $this->deleteDirectory($this->snapshotDir($id));
            $removed++;
        }

        return $removed;
    }

    public function snapshotRoot(): string
    {
        if ($this->snapshotRootOverride !== null && $this->snapshotRootOverride !== '') {
            $path = rtrim($this->snapshotRootOverride, '/\\');
            $this->assertNotPublic($path);

            return $path;
        }
        $env = trim((string) $this->env('DATA_SNAPSHOT_DIR', ''));
        if ($env !== '') {
            $path = $this->isAbsolutePath($env)
                ? rtrim($env, '/\\')
                : $this->projectRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $env);
        } else {
            $path = $this->projectRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'snapshots';
        }
        $this->assertNotPublic($path);

        return $path;
    }

    public function snapshotDir(string $id): string
    {
        $id = $this->assertSnapshotId($id);

        return $this->snapshotRoot() . DIRECTORY_SEPARATOR . $id;
    }

    public static function sanitizeLabel(string $label): string
    {
        $label = strtolower(trim($label));
        $label = preg_replace('/[^a-z0-9._-]+/', '-', $label) ?? '';
        $label = trim($label, '.-_');
        if (strlen($label) > 40) {
            $label = substr($label, 0, 40);
        }

        return $label;
    }

    public function assertSnapshotId(string $id): string
    {
        $id = trim($id);
        if (preg_match(self::ID_PATTERN, $id) !== 1) {
            throw new RuntimeException('Identifiant de copie invalide.');
        }

        return $id;
    }

    /**
     * @return list<string>
     */
    public function allowedZipEntryPrefixes(): array
    {
        return self::STORAGE_TREES;
    }

    public function isAllowedStoragePath(string $name): bool
    {
        $norm = str_replace('\\', '/', ltrim($name, '/'));
        if ($norm === '' || str_contains($norm, '..')) {
            return false;
        }
        foreach (self::STORAGE_TREES as $tree) {
            if ($norm === $tree || str_starts_with($norm, $tree . '/')) {
                return true;
            }
        }

        return false;
    }

    /** @deprecated Use isAllowedStoragePath */
    public function isAllowedZipEntry(string $name): bool
    {
        return $this->isAllowedStoragePath($name);
    }

    public function defaultKeep(): int
    {
        $raw = trim((string) $this->env('DATA_SNAPSHOT_KEEP', '10'));
        if ($raw === '' || !ctype_digit($raw)) {
            return 10;
        }

        return (int) $raw;
    }

    /**
     * @param callable(string):void $log
     * @return array{method: string, bytes: int, name: string}
     */
    private function dumpDatabase(string $gzPath, callable $log): array
    {
        $cfg = $this->normalizedDbConfig();
        $pdo = $this->requirePdo();
        $result = $this->mysql->dumpToGzip($pdo, $cfg, $gzPath, $log);

        return ['method' => $result['method'], 'bytes' => $result['bytes'], 'name' => $cfg['database']];
    }

    /**
     * @param callable(string):void $log
     */
    private function restoreDatabase(string $gzPath, callable $log): void
    {
        $cfg = $this->normalizedDbConfig();
        $pdo = $this->requirePdo();
        $this->mysql->restoreFromGzip($pdo, $cfg, $gzPath, $log);
    }

    /**
     * @param callable(string):void $log
     * @return array{count: int, bytes: int}
     */
    private function archiveStorage(string $filesDir, string $indexPath, callable $log): array
    {
        if (!is_dir($filesDir) && !mkdir($filesDir, 0750, true) && !is_dir($filesDir)) {
            throw new RuntimeException('Impossible de créer le dossier des fichiers copiés.');
        }

        $index = [];
        $bytes = 0;
        $rootReal = realpath($this->projectRoot);
        if ($rootReal === false) {
            throw new RuntimeException('Racine du projet introuvable.');
        }

        foreach (self::STORAGE_TREES as $tree) {
            $abs = $this->projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $tree);
            if (!is_dir($abs)) {
                continue;
            }
            $dirReal = realpath($abs);
            if ($dirReal === false || !$this->isInside($rootReal, $dirReal)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dirReal, \FilesystemIterator::SKIP_DOTS)
            );
            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $real = $file->getRealPath();
                if ($real === false || !$this->isInside($dirReal, $real)) {
                    continue;
                }
                $rel = substr($real, strlen($dirReal));
                $rel = ltrim(str_replace('\\', '/', $rel), '/');
                $entry = $tree . ($rel !== '' ? '/' . $rel : '');
                if (!$this->isAllowedStoragePath($entry)) {
                    continue;
                }
                $dest = $filesDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $entry);
                $parent = dirname($dest);
                if (!is_dir($parent) && !mkdir($parent, 0750, true) && !is_dir($parent)) {
                    throw new RuntimeException('Impossible de copier ' . $entry);
                }
                if (!copy($real, $dest)) {
                    throw new RuntimeException('Impossible de copier ' . $entry);
                }
                $size = (int) filesize($dest);
                $index[$entry] = [
                    'bytes' => $size,
                    'sha256' => self::sha256File($dest),
                ];
                $bytes += $size;
            }
        }

        $json = json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false || file_put_contents($indexPath, $json . "\n") === false) {
            throw new RuntimeException('Impossible d’écrire l’index des fichiers.');
        }
        $log('Fichiers copiés : ' . count($index) . '.');

        return ['count' => count($index), 'bytes' => $bytes];
    }

    /**
     * @param callable(string):void $log
     */
    private function restoreStorage(string $filesDir, string $indexPath, bool $prune, callable $log): void
    {
        if (!is_file($indexPath) || !is_dir($filesDir)) {
            throw new RuntimeException('Copie de fichiers introuvable.');
        }
        $index = json_decode((string) file_get_contents($indexPath), true);
        if (!is_array($index)) {
            throw new RuntimeException('Index des fichiers illisible.');
        }

        $restored = 0;
        $known = [];
        foreach ($index as $name => $meta) {
            if (!is_string($name) || !$this->isAllowedStoragePath($name)) {
                $log('Entrée ignorée (chemin refusé) : ' . (string) $name);
                continue;
            }
            $source = $filesDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
            if (!is_file($source)) {
                throw new RuntimeException('Fichier absent de la copie : ' . $name);
            }
            $target = $this->projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
            $parent = dirname($target);
            if (!is_dir($parent) && !mkdir($parent, 0755, true) && !is_dir($parent)) {
                throw new RuntimeException('Impossible de recréer ' . $name);
            }
            $realParent = realpath($parent);
            if ($realParent === false || !$this->isInside($this->projectRoot, $realParent)) {
                $log('Entrée ignorée (cible hors projet) : ' . $name);
                continue;
            }
            if (!copy($source, $target)) {
                throw new RuntimeException('Impossible d’écrire ' . $name);
            }
            unset($meta);
            $known[$name] = true;
            $restored++;
        }
        $log('Fichiers restaurés : ' . $restored . '.');

        if ($prune) {
            $removed = $this->pruneStorageFiles(array_keys($known));
            $log('Fichiers absents de la copie, retirés : ' . $removed . '.');
        }
    }

    /**
     * @param list<string> $keepRelative
     */
    public function pruneStorageFiles(array $keepRelative): int
    {
        $keep = [];
        foreach ($keepRelative as $rel) {
            $keep[str_replace('\\', '/', $rel)] = true;
        }
        $removed = 0;
        foreach (self::STORAGE_TREES as $tree) {
            $abs = $this->projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $tree);
            if (!is_dir($abs)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($abs, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                $real = $file->getRealPath();
                if ($real === false) {
                    continue;
                }
                $rel = str_replace('\\', '/', substr($real, strlen($this->projectRoot) + 1));
                if ($file->isDir()) {
                    @rmdir($real);
                    continue;
                }
                if ($file->getFilename() === '.gitkeep' || $file->getFilename() === '.gitignore') {
                    continue;
                }
                if (!isset($keep[$rel])) {
                    if (@unlink($real)) {
                        $removed++;
                    }
                }
            }
        }

        return $removed;
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function writeManifest(string $dir, array $manifest): void
    {
        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || file_put_contents($dir . DIRECTORY_SEPARATOR . 'manifest.json', $json . "\n") === false) {
            throw new RuntimeException('Impossible d’écrire le manifeste.');
        }
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function writeChecksums(string $dir, array $manifest): void
    {
        $lines = [];
        foreach (['database', 'storage'] as $section) {
            $meta = is_array($manifest[$section] ?? null) ? $manifest[$section] : [];
            if (empty($meta['included']) || empty($meta['sha256']) || empty($meta['file'])) {
                continue;
            }
            $lines[] = $meta['sha256'] . '  ' . $meta['file'];
        }
        if ($lines !== []) {
            file_put_contents($dir . DIRECTORY_SEPARATOR . 'SHA256SUMS', implode("\n", $lines) . "\n");
        }
    }

    private function requirePdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }
        throw new RuntimeException('Connexion à la base indisponible.');
    }

    /**
     * @return array{host:string,port:int,database:string,username:string,password:string,charset:string}
     */
    private function normalizedDbConfig(): array
    {
        $c = $this->dbConfig ?? [];
        $name = (string) ($c['database'] ?? '');
        $user = (string) ($c['username'] ?? '');
        if ($name === '' || $user === '') {
            throw new RuntimeException('Identifiants de base incomplets (DB_NAME / DB_USER).');
        }

        return [
            'host' => (string) ($c['host'] ?? '127.0.0.1'),
            'port' => (int) ($c['port'] ?? 3306),
            'database' => $name,
            'username' => $user,
            'password' => (string) ($c['password'] ?? ''),
            'charset' => (string) ($c['charset'] ?? 'utf8mb4'),
        ];
    }

    private function gitSha(): string
    {
        $head = $this->projectRoot . DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR . 'HEAD';
        if (!is_file($head)) {
            return '';
        }
        $raw = trim((string) file_get_contents($head));
        if (str_starts_with($raw, 'ref: ')) {
            $ref = $this->projectRoot . DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR . trim(substr($raw, 5));
            if (is_file($ref)) {
                return trim((string) file_get_contents($ref));
            }
        }
        if (preg_match('/^[0-9a-f]{40}$/', $raw) === 1) {
            return $raw;
        }

        return '';
    }

    private function env(string $key, string $default = ''): string
    {
        if (function_exists('env')) {
            $v = env($key, $default);

            return is_scalar($v) ? (string) $v : $default;
        }

        return (string) ($_ENV[$key] ?? $default);
    }

    private function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }
        if ($path[0] === '/' || $path[0] === '\\') {
            return true;
        }

        return strlen($path) >= 3 && ctype_alpha($path[0]) && $path[1] === ':' && ($path[2] === '\\' || $path[2] === '/');
    }

    private function assertNotPublic(string $path): void
    {
        $public = $this->projectRoot . DIRECTORY_SEPARATOR . 'public';
        if ($this->isInside($public, $path)) {
            throw new RuntimeException('Le dossier des copies ne doit pas être sous public/.');
        }
    }

    private function isInside(string $parent, string $child): bool
    {
        $norm = static function (string $p): string {
            $p = str_replace('\\', '/', $p);
            $p = rtrim($p, '/');
            if (PHP_OS_FAMILY === 'Windows') {
                $p = strtolower($p);
            }

            return $p;
        };
        $p = $norm($parent);
        $c = $norm($child);

        return $c === $p || str_starts_with($c, $p . '/');
    }

    public static function sha256File(string $path): string
    {
        $hash = hash_file('sha256', $path);

        return is_string($hash) ? $hash : '';
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir) || !$this->isInside($this->snapshotRoot(), $dir)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            $real = $file->getRealPath();
            if ($real === false) {
                continue;
            }
            $file->isDir() ? @rmdir($real) : @unlink($real);
        }
        @rmdir($dir);
    }
}
