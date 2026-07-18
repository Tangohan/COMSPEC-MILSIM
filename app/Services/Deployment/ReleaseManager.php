<?php

declare(strict_types=1);

namespace App\Services\Deployment;

use App\Repositories\PlatformAppReleaseRepository;
use PDO;

/**
 * Application / rollback d’une mise à jour sur racine web fixe (overlay + sauvegarde).
 */
final class ReleaseManager
{
    public function __construct(
        private PlatformAppReleaseRepository $releases,
        private UpdatePackageService $packages,
        private AppVersionStore $versions,
        private HealthCheckService $health,
        private ?PDO $pdo = null,
    ) {
        $this->pdo = $pdo ?? \App\Core\Database::getPdo();
    }

    /**
     * @return array{ok:bool, health:array<string,mixed>, backup_id:?int}
     */
    public function deploy(int $releaseId, ?int $actorUserId): array
    {
        $release = $this->releases->find($releaseId);
        if ($release === null) {
            throw new \InvalidArgumentException('Mise à jour introuvable.');
        }
        $status = (string) ($release['status'] ?? '');
        if (!in_array($status, ['validated', 'previewed', 'failed'], true)) {
            throw new \InvalidArgumentException('Cette mise à jour ne peut pas être déployée dans son état actuel.');
        }

        $fileRows = $this->releases->listFiles($releaseId);
        foreach ($fileRows as $row) {
            if (!empty($row['conflict'])) {
                throw new \InvalidArgumentException(
                    'Des chemins protégés ou en conflit empêchent le déploiement. Corrigez le package.'
                );
            }
        }

        if (!$this->releases->tryAcquireLock($actorUserId, $releaseId)) {
            throw new \RuntimeException('Une autre mise à jour est déjà en cours.');
        }

        $previousVersion = $this->versions->current();
        $maintenanceOn = false;
        $backupId = null;

        try {
            $this->releases->update($releaseId, ['status' => 'deploying', 'error_message' => null]);
            $manifest = $this->decodeManifest($release);
            $needMaintenance = !empty($manifest['maintenance_required']) || (int) ($release['maintenance_required'] ?? 1) === 1;

            if ($needMaintenance) {
                $this->enableMaintenance('Mise à jour de la plateforme en cours. Merci de réessayer dans quelques minutes.');
                $maintenanceOn = true;
            }

            $this->releases->log($releaseId, 'deploy_start', 'Démarrage du déploiement ' . $release['version'], 'info', null, $actorUserId);

            $packageRoot = $this->packages->packageRootForRelease($release);
            $this->runScript($packageRoot, 'pre_update.php', $releaseId, $actorUserId);

            $backupMeta = $this->createFileBackup($releaseId, $fileRows, $previousVersion);
            $backupId = $backupMeta['id'];

            $this->applyOverlay($packageRoot, $fileRows, $manifest);
            $this->runPackageMigrations($packageRoot, $manifest, $releaseId, $actorUserId);
            $this->runScript($packageRoot, 'post_update.php', $releaseId, $actorUserId);

            $this->versions->write((string) $release['version']);
            $this->purgeCaches();

            $health = $this->health->run();
            if (!$health['ok']) {
                throw new \RuntimeException(
                    'Contrôle de santé échoué : ' . implode(' ', $health['messages'])
                );
            }

            $this->releases->update($releaseId, [
                'status' => 'deployed',
                'deployed_by' => $actorUserId,
                'deployed_at' => (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
                'error_message' => null,
            ]);
            $this->releases->log($releaseId, 'deploy_success', 'Déploiement réussi vers ' . $release['version'], 'info', [
                'previous_version' => $previousVersion,
                'backup_id' => $backupId,
            ], $actorUserId);

            if ($maintenanceOn) {
                $this->disableMaintenance();
                $maintenanceOn = false;
            }
            $this->releases->releaseLock();

            return ['ok' => true, 'health' => $health, 'backup_id' => $backupId];
        } catch (\Throwable $e) {
            $this->releases->update($releaseId, [
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            $this->releases->log($releaseId, 'deploy_failed', $e->getMessage(), 'error', null, $actorUserId);

            try {
                if ($backupId !== null) {
                    $this->restoreFromBackupId($backupId);
                    $this->versions->write($previousVersion);
                    $this->releases->update($releaseId, [
                        'status' => 'rolled_back',
                        'rolled_back_at' => (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
                    ]);
                    $this->releases->log($releaseId, 'auto_rollback', 'Restauration automatique après échec.', 'warning', [
                        'previous_version' => $previousVersion,
                    ], $actorUserId);
                }
            } catch (\Throwable $rollbackError) {
                $this->releases->log($releaseId, 'rollback_failed', $rollbackError->getMessage(), 'error', null, $actorUserId);
            }

            if ($maintenanceOn) {
                $this->disableMaintenance();
            }
            $this->releases->releaseLock();

            throw $e;
        }
    }

    /**
     * @return array{ok:bool}
     */
    public function rollback(int $releaseId, ?int $actorUserId): array
    {
        $release = $this->releases->find($releaseId);
        if ($release === null) {
            throw new \InvalidArgumentException('Mise à jour introuvable.');
        }
        $backup = $this->releases->findLatestBackupForRelease($releaseId);
        if ($backup === null) {
            throw new \InvalidArgumentException('Aucune sauvegarde disponible pour cette mise à jour.');
        }

        if (!$this->releases->tryAcquireLock($actorUserId, $releaseId)) {
            throw new \RuntimeException('Une autre mise à jour est déjà en cours.');
        }

        $maintenanceOn = false;
        try {
            $this->enableMaintenance('Restauration de la version précédente en cours.');
            $maintenanceOn = true;
            $this->restoreFromBackupId((int) $backup['id']);
            $prev = (string) ($backup['previous_version'] ?? '1.0.0');
            $this->versions->write($prev);
            $this->purgeCaches();
            $this->releases->update($releaseId, [
                'status' => 'rolled_back',
                'rolled_back_at' => (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
            ]);
            $this->releases->log($releaseId, 'rollback', 'Restauration manuelle vers ' . $prev, 'info', [
                'backup_id' => (int) $backup['id'],
            ], $actorUserId);
            $this->disableMaintenance();
            $maintenanceOn = false;
            $this->releases->releaseLock();

            return ['ok' => true];
        } catch (\Throwable $e) {
            if ($maintenanceOn) {
                $this->disableMaintenance();
            }
            $this->releases->releaseLock();
            throw $e;
        }
    }

    /**
     * @param list<array<string, mixed>> $fileRows
     * @return array{id:int, path:string, count:int}
     */
    private function createFileBackup(int $releaseId, array $fileRows, string $previousVersion): array
    {
        $stamp = date('YmdHis');
        $relDir = 'storage/backups/app-updates/' . $releaseId . '-' . $stamp;
        $absDir = base_path($relDir);
        $this->ensureDir($absDir);
        $manifest = ['files' => [], 'previous_version' => $previousVersion];
        $count = 0;
        $liveRoot = base_path();

        foreach ($fileRows as $row) {
            $action = (string) ($row['action'] ?? '');
            if (!in_array($action, ['add', 'update', 'delete'], true)) {
                continue;
            }
            $rel = ProtectedPathPolicy::normalize((string) ($row['relative_path'] ?? ''));
            if ($rel === '' || ProtectedPathPolicy::isProtected($rel)) {
                continue;
            }
            $live = $liveRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            if ($action === 'add') {
                $manifest['files'][] = ['path' => $rel, 'action' => 'add', 'had_content' => false];
                continue;
            }
            if (!is_file($live)) {
                $manifest['files'][] = ['path' => $rel, 'action' => $action, 'had_content' => false];
                continue;
            }
            $dest = $absDir . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            $this->ensureDir(dirname($dest));
            if (!copy($live, $dest)) {
                throw new \RuntimeException('Échec de sauvegarde : ' . $rel);
            }
            $manifest['files'][] = ['path' => $rel, 'action' => $action, 'had_content' => true];
            $count++;
        }

        $manifestPath = $absDir . DIRECTORY_SEPARATOR . 'backup.json';
        file_put_contents(
            $manifestPath,
            json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n"
        );

        $id = $this->releases->insertBackup($releaseId, $relDir, $previousVersion, $count);

        return ['id' => $id, 'path' => $relDir, 'count' => $count];
    }

    private function restoreFromBackupId(int $backupId): void
    {
        $st = $this->pdo->prepare('SELECT * FROM platform_app_deployment_backups WHERE id = ? LIMIT 1');
        $st->execute([$backupId]);
        $backup = $st->fetch(PDO::FETCH_ASSOC);
        if (!$backup) {
            throw new \RuntimeException('Sauvegarde introuvable.');
        }
        $dir = base_path((string) $backup['backup_path']);
        $manifestPath = $dir . DIRECTORY_SEPARATOR . 'backup.json';
        if (!is_file($manifestPath)) {
            throw new \RuntimeException('Manifeste de sauvegarde manquant.');
        }
        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        if (!is_array($manifest) || !is_array($manifest['files'] ?? null)) {
            throw new \RuntimeException('Manifeste de sauvegarde invalide.');
        }

        $liveRoot = base_path();
        foreach ($manifest['files'] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $rel = ProtectedPathPolicy::normalize((string) ($entry['path'] ?? ''));
            if ($rel === '' || ProtectedPathPolicy::isProtected($rel)) {
                continue;
            }
            $live = $liveRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            $action = (string) ($entry['action'] ?? '');
            $had = !empty($entry['had_content']);

            if ($action === 'add') {
                if (is_file($live)) {
                    @unlink($live);
                }
                continue;
            }

            if ($had) {
                $src = $dir . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
                if (!is_file($src)) {
                    throw new \RuntimeException('Fichier de sauvegarde manquant : ' . $rel);
                }
                $this->ensureDir(dirname($live));
                if (!copy($src, $live)) {
                    throw new \RuntimeException('Échec de restauration : ' . $rel);
                }
            }
        }
    }

    /**
     * @param list<array<string, mixed>> $fileRows
     * @param array<string, mixed> $manifest
     */
    private function applyOverlay(string $packageRoot, array $fileRows, array $manifest): void
    {
        $filesRoot = $packageRoot . DIRECTORY_SEPARATOR . 'files';
        $liveRoot = base_path();

        foreach ($fileRows as $row) {
            $action = (string) ($row['action'] ?? '');
            $rel = ProtectedPathPolicy::normalize((string) ($row['relative_path'] ?? ''));
            if ($rel === '' || ProtectedPathPolicy::isProtected($rel)) {
                continue;
            }
            $live = $liveRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);

            if ($action === 'delete') {
                if (is_file($live)) {
                    @unlink($live);
                }
                continue;
            }
            if (!in_array($action, ['add', 'update'], true)) {
                continue;
            }
            $src = $filesRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            if (!is_file($src)) {
                throw new \RuntimeException('Fichier source manquant dans le package : ' . $rel);
            }
            $this->ensureDir(dirname($live));
            if (!copy($src, $live)) {
                throw new \RuntimeException('Échec de copie : ' . $rel);
            }
        }
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function runPackageMigrations(string $packageRoot, array $manifest, int $releaseId, ?int $actorUserId): void
    {
        $list = $manifest['migrations'] ?? [];
        if (!is_array($list) || $list === []) {
            return;
        }
        $migDir = $packageRoot . DIRECTORY_SEPARATOR . 'migrations';
        foreach ($list as $name) {
            $file = $migDir . DIRECTORY_SEPARATOR . basename((string) $name);
            if (!is_file($file)) {
                throw new \RuntimeException('Migration manquante : ' . $name);
            }
            $sql = (string) file_get_contents($file);
            $sql = preg_replace('/--[^\r\n]*/', '', $sql) ?? $sql;
            $chunks = preg_split('/;\s*[\r\n]+/', trim($sql)) ?: [];
            foreach ($chunks as $chunk) {
                $chunk = trim($chunk);
                if ($chunk === '') {
                    continue;
                }
                $this->pdo->exec($chunk . (str_ends_with($chunk, ';') ? '' : ';'));
            }
            $this->releases->log($releaseId, 'migration', 'Migration exécutée : ' . basename($file), 'info', null, $actorUserId);
        }
    }

    private function runScript(string $packageRoot, string $scriptName, int $releaseId, ?int $actorUserId): void
    {
        $path = $packageRoot . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . $scriptName;
        if (!is_file($path)) {
            return;
        }
        $this->releases->log($releaseId, 'script', 'Exécution de ' . $scriptName, 'info', null, $actorUserId);
        (static function (string $__script): void {
            require $__script;
        })($path);
    }

    private function purgeCaches(): void
    {
        $cacheDir = base_path('storage/cache');
        if (!is_dir($cacheDir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($cacheDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $item) {
            $p = $item->getPathname();
            if ($item->isDir()) {
                @rmdir($p);
            } else {
                @unlink($p);
            }
        }
    }

    private function enableMaintenance(string $message): void
    {
        $path = base_path('storage/maintenance.json');
        $this->ensureDir(dirname($path));
        $data = [
            'enabled' => true,
            'message' => $message,
            'allowed_ips' => [],
            'reason' => 'app_update',
            'at' => (new \DateTimeImmutable('now'))->format(\DateTimeInterface::ATOM),
        ];
        file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");
    }

    private function disableMaintenance(): void
    {
        $path = base_path('storage/maintenance.json');
        if (!is_file($path)) {
            return;
        }
        $raw = json_decode((string) file_get_contents($path), true);
        if (!is_array($raw)) {
            @unlink($path);

            return;
        }
        if (($raw['reason'] ?? '') === 'app_update' || !empty($raw['enabled'])) {
            $raw['enabled'] = false;
            $raw['disabled_at'] = (new \DateTimeImmutable('now'))->format(\DateTimeInterface::ATOM);
            file_put_contents($path, json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");
        }
    }

    /**
     * @param array<string, mixed> $release
     * @return array<string, mixed>
     */
    private function decodeManifest(array $release): array
    {
        $raw = $release['manifest_json'] ?? null;
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Impossible de créer le dossier : ' . $dir);
        }
    }
}
