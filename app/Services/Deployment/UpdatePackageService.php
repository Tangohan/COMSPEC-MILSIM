<?php

declare(strict_types=1);

namespace App\Services\Deployment;

use App\Repositories\PlatformAppReleaseRepository;
use ZipArchive;

/**
 * Dépôt, validation et prévisualisation d’un package ZIP de mise à jour.
 */
final class UpdatePackageService
{
    public function __construct(
        private PlatformAppReleaseRepository $releases,
        private PackageSignatureVerifier $signatures,
        private AppVersionStore $versions,
    ) {}

    /**
     * @return array{release_id:int, version:string, preview:array<string,mixed>}
     */
    public function ingestUploadedZip(string $tmpPath, string $originalName, ?int $actorUserId): array
    {
        if (!is_file($tmpPath)) {
            throw new \InvalidArgumentException('Fichier de mise à jour introuvable.');
        }

        $updatesDir = base_path('storage/updates');
        $this->ensureDir($updatesDir);

        $safeName = 'upload-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.zip';
        $destZip = $updatesDir . DIRECTORY_SEPARATOR . $safeName;
        if (!move_uploaded_file($tmpPath, $destZip) && !rename($tmpPath, $destZip)) {
            if (!copy($tmpPath, $destZip)) {
                throw new \RuntimeException('Impossible d’enregistrer le package.');
            }
            @unlink($tmpPath);
        }

        $zip = new ZipArchive();
        if ($zip->open($destZip) !== true) {
            @unlink($destZip);
            throw new \InvalidArgumentException('Le fichier n’est pas une archive ZIP valide.');
        }

        $manifestRaw = $zip->getFromName('manifest.json');
        if ($manifestRaw === false) {
            // parfois à la racine d’un dossier unique
            $manifestRaw = $this->findManifestInZip($zip);
        }
        if ($manifestRaw === false) {
            $zip->close();
            @unlink($destZip);
            throw new \InvalidArgumentException('manifest.json manquant dans le package.');
        }

        $manifest = json_decode($manifestRaw, true);
        if (!is_array($manifest)) {
            $zip->close();
            @unlink($destZip);
            throw new \InvalidArgumentException('manifest.json illisible.');
        }

        $version = trim((string) ($manifest['version'] ?? ''));
        if ($version === '' || !preg_match('/^\d+\.\d+\.\d+/', $version)) {
            $zip->close();
            @unlink($destZip);
            throw new \InvalidArgumentException('Version du package invalide.');
        }

        if ($this->releases->findByVersion($version) !== null) {
            $zip->close();
            @unlink($destZip);
            throw new \InvalidArgumentException('Cette version a déjà été déposée.');
        }

        $current = $this->versions->current();
        $minimum = trim((string) ($manifest['minimum_version'] ?? ''));
        if ($minimum !== '' && !VersionCompatibility::satisfiesMinimum($current, $minimum)) {
            $zip->close();
            @unlink($destZip);
            throw new \InvalidArgumentException(
                'Version installée insuffisante. Cette mise à jour exige au minimum la version ' . $minimum . '.'
            );
        }

        if (!VersionCompatibility::isNewerThan($version, $current)) {
            $zip->close();
            @unlink($destZip);
            throw new \InvalidArgumentException(
                'Le package doit être plus récent que la version installée (' . $current . ').'
            );
        }

        $phpMin = isset($manifest['php_min']) ? (string) $manifest['php_min'] : null;
        $phpMax = isset($manifest['php_max']) ? (string) $manifest['php_max'] : null;
        if (!VersionCompatibility::phpCompatible($phpMin, $phpMax)) {
            $zip->close();
            @unlink($destZip);
            throw new \InvalidArgumentException(
                'Cette mise à jour n’est pas compatible avec la version PHP du serveur (' . PHP_VERSION . ').'
            );
        }

        $extractRoot = base_path('storage/releases/' . $version);
        $this->removeTree($extractRoot);
        $this->ensureDir($extractRoot);
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (!is_string($name) || $name === '') {
                continue;
            }
            $norm = str_replace('\\', '/', $name);
            if (str_contains($norm, '..') || str_starts_with($norm, '/') || preg_match('#^[a-zA-Z]:#', $norm)) {
                $zip->close();
                $this->removeTree($extractRoot);
                @unlink($destZip);
                throw new \InvalidArgumentException('Archive refusée : chemin de fichier non sûr.');
            }
        }
        if (!$zip->extractTo($extractRoot)) {
            $zip->close();
            $this->removeTree($extractRoot);
            @unlink($destZip);
            throw new \RuntimeException('Échec de l’extraction du package.');
        }
        $zip->close();

        $packageRoot = $this->resolvePackageRoot($extractRoot);
        $filesRoot = $packageRoot . DIRECTORY_SEPARATOR . 'files';
        if (!is_dir($filesRoot)) {
            $this->removeTree($extractRoot);
            @unlink($destZip);
            throw new \InvalidArgumentException('Le dossier files/ est manquant dans le package.');
        }

        $payloadChecksum = $this->computePayloadChecksum($packageRoot);
        $declared = strtolower(trim((string) ($manifest['checksum'] ?? '')));
        if ($declared === '' || !hash_equals($declared, $payloadChecksum)) {
            $this->removeTree($extractRoot);
            @unlink($destZip);
            throw new \InvalidArgumentException('Le contrôle d’intégrité du package a échoué.');
        }

        if (!$this->signatures->verifyManifest($manifest, $payloadChecksum)) {
            $this->removeTree($extractRoot);
            @unlink($destZip);
            throw new \InvalidArgumentException('La signature du package est invalide ou absente.');
        }

        $this->assertNoProtectedPayload($filesRoot);

        $releaseId = $this->releases->insert([
            'version' => $version,
            'minimum_version' => $minimum !== '' ? $minimum : null,
            'status' => 'validated',
            'package_path' => 'storage/updates/' . $safeName,
            'extract_path' => 'storage/releases/' . $version,
            'payload_checksum' => $payloadChecksum,
            'manifest_json' => $manifest,
            'maintenance_required' => !empty($manifest['maintenance_required']) ? 1 : 0,
            'uploaded_by' => $actorUserId,
        ]);

        $preview = $this->buildPreview($releaseId, $packageRoot, $manifest);
        $this->releases->update($releaseId, ['status' => 'previewed']);

        $this->releases->log($releaseId, 'upload', 'Package déposé et validé : ' . $version, 'info', [
            'original_name' => $originalName,
            'payload_checksum' => $payloadChecksum,
        ], $actorUserId);

        return [
            'release_id' => $releaseId,
            'version' => $version,
            'preview' => $preview,
        ];
    }

    /**
     * @param array<string, mixed> $manifest
     * @return array{files:list<array<string,mixed>>, summary:array<string,int>, conflicts:int}
     */
    public function buildPreview(int $releaseId, string $packageRoot, array $manifest): array
    {
        $filesRoot = $packageRoot . DIRECTORY_SEPARATOR . 'files';
        $liveRoot = base_path();
        $rows = [];
        $summary = ['add' => 0, 'update' => 0, 'delete' => 0, 'unchanged' => 0, 'blocked' => 0];

        foreach ($this->iterateFiles($filesRoot) as $abs => $rel) {
            if (ProtectedPathPolicy::isProtected($rel)) {
                $summary['blocked']++;
                $rows[] = [
                    'relative_path' => $rel,
                    'action' => 'unchanged',
                    'source_checksum' => null,
                    'target_checksum' => null,
                    'conflict' => 1,
                ];
                continue;
            }
            $target = $liveRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            $srcHash = is_file($abs) ? hash_file('sha256', $abs) : null;
            $tgtHash = is_file($target) ? hash_file('sha256', $target) : null;
            if ($tgtHash === null) {
                $action = 'add';
            } elseif ($srcHash === $tgtHash) {
                $action = 'unchanged';
            } else {
                $action = 'update';
            }
            $summary[$action]++;
            $rows[] = [
                'relative_path' => $rel,
                'action' => $action,
                'source_checksum' => $srcHash,
                'target_checksum' => $tgtHash,
                'conflict' => 0,
            ];
        }

        $toDelete = $manifest['files_to_delete'] ?? [];
        if (is_array($toDelete)) {
            foreach ($toDelete as $path) {
                $rel = ProtectedPathPolicy::normalize((string) $path);
                if ($rel === '' || ProtectedPathPolicy::isProtected($rel)) {
                    $summary['blocked']++;
                    $rows[] = [
                        'relative_path' => $rel,
                        'action' => 'delete',
                        'source_checksum' => null,
                        'target_checksum' => null,
                        'conflict' => 1,
                    ];
                    continue;
                }
                $target = $liveRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
                $action = is_file($target) ? 'delete' : 'unchanged';
                if ($action === 'delete') {
                    $summary['delete']++;
                } else {
                    $summary['unchanged']++;
                }
                $rows[] = [
                    'relative_path' => $rel,
                    'action' => $action,
                    'source_checksum' => null,
                    'target_checksum' => is_file($target) ? hash_file('sha256', $target) : null,
                    'conflict' => 0,
                ];
            }
        }

        $this->releases->deleteFilesForRelease($releaseId);
        $this->releases->insertFiles($releaseId, $rows);

        $conflicts = 0;
        foreach ($rows as $r) {
            if (!empty($r['conflict'])) {
                $conflicts++;
            }
        }

        return [
            'files' => $rows,
            'summary' => $summary,
            'conflicts' => $conflicts,
        ];
    }

    public function packageRootForRelease(array $release): string
    {
        $extract = base_path((string) ($release['extract_path'] ?? ''));
        if (!is_dir($extract)) {
            throw new \RuntimeException('Package extrait introuvable.');
        }

        return $this->resolvePackageRoot($extract);
    }

    public function computePayloadChecksum(string $packageRoot): string
    {
        $parts = [];
        foreach (['files', 'migrations', 'scripts'] as $dir) {
            $base = $packageRoot . DIRECTORY_SEPARATOR . $dir;
            if (!is_dir($base)) {
                continue;
            }
            foreach ($this->iterateFiles($base) as $abs => $rel) {
                $parts[] = $dir . '/' . $rel . ':' . hash_file('sha256', $abs);
            }
        }
        sort($parts, SORT_STRING);

        return hash('sha256', implode("\n", $parts));
    }

    private function assertNoProtectedPayload(string $filesRoot): void
    {
        foreach ($this->iterateFiles($filesRoot) as $_ => $rel) {
            if (ProtectedPathPolicy::isProtected($rel)) {
                throw new \InvalidArgumentException(
                    'Le package tente de modifier un chemin protégé : ' . $rel
                );
            }
        }
    }

    /**
     * @return \Generator<string, string> absolute => relative under root
     */
    private function iterateFiles(string $root): \Generator
    {
        if (!is_dir($root)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        $rootLen = strlen(rtrim($root, '/\\')) + 1;
        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $abs = $file->getPathname();
            $rel = ProtectedPathPolicy::normalize(substr($abs, $rootLen));
            if ($rel === '') {
                continue;
            }
            yield $abs => $rel;
        }
    }

    private function resolvePackageRoot(string $extractRoot): string
    {
        if (is_file($extractRoot . DIRECTORY_SEPARATOR . 'manifest.json')) {
            return $extractRoot;
        }
        $entries = array_values(array_filter(scandir($extractRoot) ?: [], static fn ($e) => $e !== '.' && $e !== '..'));
        if (count($entries) === 1) {
            $candidate = $extractRoot . DIRECTORY_SEPARATOR . $entries[0];
            if (is_dir($candidate) && is_file($candidate . DIRECTORY_SEPARATOR . 'manifest.json')) {
                return $candidate;
            }
        }

        return $extractRoot;
    }

    private function findManifestInZip(ZipArchive $zip): string|false
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (!is_string($name)) {
                continue;
            }
            $norm = str_replace('\\', '/', $name);
            if ($norm === 'manifest.json' || preg_match('#^[^/]+/manifest\.json$#', $norm)) {
                $raw = $zip->getFromIndex($i);

                return $raw === false ? false : $raw;
            }
        }

        return false;
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Impossible de créer le dossier : ' . $dir);
        }
    }

    private function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $item) {
            $path = $item->getPathname();
            if ($item->isDir()) {
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
