<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\UserRepository;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use App\Services\Backup\CompleteDataSnapshotService;
use App\Services\Ops\OpsAccountDeletionService;
use App\Services\Ops\OpsConsoleStepUp;
use App\Services\Ops\OpsHealthService;
use App\Support\Migrations\MigrationRunLog;
use App\Support\Ops\OpsMigrationTicket;

/**
 * Console d'exploitation du site : état, migrations, sauvegardes, suppressions de comptes.
 *
 * Réservée à l'administration du site (SystemAdminMiddleware) et ouverte seulement après une double
 * vérification (OpsConsoleStepUp). Chaque action passe par un jeton CSRF et laisse une trace d'audit ;
 * restaurer ou supprimer demande en plus une confirmation saisie à la main.
 */
final class SystemOpsConsoleController
{
    public const TABS = ['etat', 'migrations', 'sauvegardes', 'donnees'];
    public const RESTORE_WORD = 'RESTAURER';
    public const DELETE_BACKUP_WORD = 'SUPPRIMER';

    public function __construct(
        private ?UserRepository $users = null,
        private ?AuditService $audit = null,
        private ?OpsConsoleStepUp $stepUp = null,
        private ?CompleteDataSnapshotService $snapshots = null,
        private ?OpsAccountDeletionService $deletions = null,
    ) {
        $this->users ??= Container::get(UserRepository::class);
        $this->audit ??= new AuditService();
    }

    // ------------------------------------------------------------------ pages

    public function index(Request $request, array $params = []): Response
    {
        $me = $this->me();
        if ($me === null) {
            return Response::redirect(url('login'));
        }
        if (!$this->stepUp()->isVerified((int) $me['id'])) {
            return $this->view('Console d’exploitation', [
                'opsLocked' => true,
                'opsMethod' => $this->stepUp()->method($me),
                'opsPendingCode' => $this->stepUp()->pendingEmailCode((int) $me['id']),
                'opsEmailMasked' => OpsConsoleStepUp::maskEmail((string) ($me['email'] ?? '')),
            ]);
        }

        $tab = (string) $request->query('onglet', 'etat');
        if (!in_array($tab, self::TABS, true)) {
            $tab = 'etat';
        }
        $data = [
            'opsLocked' => false,
            'opsTab' => $tab,
            'opsRemaining' => $this->stepUp()->remainingSeconds((int) $me['id']),
            'opsRestoreWord' => self::RESTORE_WORD,
            'opsDeleteBackupWord' => self::DELETE_BACKUP_WORD,
        ];
        $health = new OpsHealthService();
        $sections = $health->sections();
        $data['opsHealth'] = $sections;
        $data['opsSummary'] = OpsHealthService::summarize($sections);

        if ($tab === 'migrations') {
            $data['opsMigrations'] = $this->migrationStatus();
            $data['opsMigrationOutput'] = null;
            $stepId = trim((string) $request->query('sortie', ''));
            if ($stepId !== '') {
                $data['opsMigrationOutput'] = ['step' => $stepId, 'text' => $this->stepOutput($stepId)];
            }
        } elseif ($tab === 'sauvegardes') {
            $data['opsBackups'] = $this->backupRows();
            $data['opsBackupRoot'] = $this->snapshots()->snapshotRoot();
            $data['opsBackupKeep'] = $this->snapshots()->defaultKeep();
            $data['opsZipAvailable'] = class_exists(\ZipArchive::class);
        } elseif ($tab === 'donnees') {
            $q = trim((string) $request->query('q', ''));
            $data['opsQuery'] = $q;
            $data['opsAccounts'] = $q !== '' ? $this->deletions()->search($q) : [];
            $data['opsDeletions'] = $this->deletions()->overview();
            $data['opsDefaultDays'] = OpsAccountDeletionService::DEFAULT_DAYS;
            $data['opsMaxDays'] = OpsAccountDeletionService::MAX_DAYS;
        }

        return $this->view('Console d’exploitation', $data);
    }

    // ------------------------------------------------------------------ double vérification

    public function sendCode(Request $request, array $params = []): Response
    {
        $me = $this->me();
        if ($me === null || !Csrf::verify($request)) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return $this->back();
        }
        if ($this->stepUp()->sendEmailCode($me)) {
            Session::flash('success', 'Code envoyé à ' . OpsConsoleStepUp::maskEmail((string) $me['email']) . '. Il reste valable 10 minutes.');
        } else {
            Session::flash('error', 'Le code n’a pas pu partir (e-mails indisponibles). Activez une application d’authentification dans votre compte.');
        }

        return $this->back();
    }

    public function verify(Request $request, array $params = []): Response
    {
        $me = $this->me();
        if ($me === null || !Csrf::verify($request)) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return $this->back();
        }
        $result = $this->stepUp()->verify($me, (string) $request->input('code', ''));
        if (!$result['ok']) {
            usleep(300000);
            Session::flash('error', $result['message']);

            return $this->back();
        }
        $this->log(AuditAction::PLATFORM_OPS_UNLOCKED, null, [], ['method' => $this->stepUp()->method($me)]);
        Session::flash('success', 'Console ouverte pour 30 minutes.');

        return $this->back();
    }

    public function lock(Request $request, array $params = []): Response
    {
        if (Csrf::verify($request)) {
            $this->stepUp()->forget();
            Session::flash('success', 'Console verrouillée.');
        }

        return Response::redirect(url('admin/system/exploitation'));
    }

    // ------------------------------------------------------------------ migrations

    public function launchMigrations(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard($request)) !== null) {
            return $deny;
        }
        $mode = $request->inputString('mode', 'run');
        require_once base_path('bootstrap/migrations_web_ui.php');
        $queueError = migrations_web_queue_error(base_path(), $mode, $request->inputString('step', ''));
        if ($queueError !== null) {
            Session::flash($mode === 'run' ? 'success' : 'error', $queueError);

            return $this->back('migrations');
        }
        $payload = ['action' => $mode, 'user_id' => (int) Session::get('user_id'), 'return' => url('admin/system/exploitation') . '?onglet=migrations'];
        if ($mode === 'run_step') {
            $payload['step'] = trim((string) $request->input('step', ''));
        }

        if ($request->input('backup') === '1') {
            try {
                $manifest = $this->createSnapshot('avant-migrations', true, false);
                $payload['snapshot'] = (string) $manifest['id'];
            } catch (\Throwable $e) {
                Session::flash('error', 'Sauvegarde préalable impossible, migrations non lancées : ' . $e->getMessage());

                return $this->back('migrations');
            }
        }

        try {
            $ticket = OpsMigrationTicket::mint(base_path(), $payload);
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());

            return $this->back('migrations');
        }
        $this->log(AuditAction::PLATFORM_OPS_MIGRATIONS_LAUNCHED, null, [], array_diff_key($payload, ['user_id' => 1, 'return' => 1]));

        return Response::redirect(url('run-migrations.php') . '?ticket=' . $ticket, 303);
    }

    // ------------------------------------------------------------------ sauvegardes

    public function createBackup(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard($request)) !== null) {
            return $deny;
        }
        $db = $request->input('database') === '1';
        $files = $request->input('files') === '1';
        try {
            $manifest = $this->createSnapshot((string) $request->input('label', 'manuelle'), $db, $files);
            Session::flash('success', 'Sauvegarde ' . $manifest['id'] . ' créée.');
        } catch (\Throwable $e) {
            Session::flash('error', 'Sauvegarde impossible : ' . $e->getMessage());
        }

        return $this->back('sauvegardes');
    }

    public function verifyBackup(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard($request)) !== null) {
            return $deny;
        }
        try {
            $check = $this->snapshots()->verify((string) $request->input('id', ''));
            $check['ok']
                ? Session::flash('success', 'Sauvegarde intacte : toutes les empreintes correspondent.')
                : Session::flash('error', 'Sauvegarde altérée : ' . implode(' ', array_slice($check['errors'], 0, 5)));
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        return $this->back('sauvegardes');
    }

    public function downloadBackup(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard($request)) !== null) {
            return $deny;
        }
        $part = (string) $request->input('part', 'database');
        try {
            $id = $this->snapshots()->assertSnapshotId((string) $request->input('id', ''));
            $dir = $this->snapshots()->snapshotDir($id);
            if ($part === 'database') {
                $path = $dir . '/database.sql.gz';
                $name = 'athena-' . $id . '-base.sql.gz';
                $type = 'application/gzip';
                $temp = false;
            } elseif ($part === 'files') {
                $path = $this->zipFiles($id, $dir);
                $name = 'athena-' . $id . '-fichiers.zip';
                $type = 'application/zip';
                $temp = true;
            } else {
                throw new \RuntimeException('Élément inconnu.');
            }
            if (!is_file($path)) {
                throw new \RuntimeException('Cette sauvegarde ne contient pas cet élément.');
            }
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());

            return $this->back('sauvegardes');
        }
        $this->log(AuditAction::PLATFORM_OPS_BACKUP_DOWNLOADED, null, [], ['id' => $id, 'part' => $part]);

        $response = new Response();
        $response->header('Content-Type', $type);
        $response->header('Content-Disposition', 'attachment; filename="' . $name . '"');
        $response->header('Content-Length', (string) filesize($path));
        $response->header('Cache-Control', 'no-store');
        $response->setBodyStream(static function () use ($path, $temp): void {
            readfile($path);
            if ($temp) {
                @unlink($path);
            }
        });

        return $response;
    }

    public function restoreBackup(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard($request)) !== null) {
            return $deny;
        }
        if (strtoupper(trim((string) $request->input('confirm', ''))) !== self::RESTORE_WORD) {
            Session::flash('error', 'Tapez ' . self::RESTORE_WORD . ' pour confirmer : rien n’a été restauré.');

            return $this->back('sauvegardes');
        }
        $db = $request->input('database') === '1';
        $files = $request->input('files') === '1';
        if (!$db && !$files) {
            Session::flash('error', 'Choisissez la base, les fichiers, ou les deux.');

            return $this->back('sauvegardes');
        }
        $this->longRunning();
        $lines = [];
        try {
            $id = $this->snapshots()->assertSnapshotId((string) $request->input('id', ''));
            $check = $this->snapshots()->verify($id);
            if (!$check['ok']) {
                throw new \RuntimeException('Sauvegarde altérée, restauration refusée : ' . implode(' ', array_slice($check['errors'], 0, 3)));
            }
            // Filet : l'état actuel est sauvegardé avant d'être écrasé, la restauration se défait donc.
            $safety = $this->createSnapshot('avant-restauration', $db, $files);
            $this->snapshots()->restore($id, $db, $files, false, false, static function (string $l) use (&$lines): void {
                $lines[] = $l;
            });
            $this->log(AuditAction::PLATFORM_OPS_BACKUP_RESTORED, null, [], ['id' => $id, 'database' => $db, 'files' => $files, 'safety_snapshot' => $safety['id']]);
            // La base restaurée peut ne plus connaître cette session : on la referme proprement.
            $this->stepUp()->forget();
            Session::flash('success', 'Sauvegarde ' . $id . ' restaurée. L’état d’avant est gardé dans ' . $safety['id'] . '.');
        } catch (\Throwable $e) {
            Session::flash('error', 'Restauration interrompue : ' . $e->getMessage() . ($lines !== [] ? ' (dernière étape : ' . end($lines) . ')' : ''));
        }

        return $this->back('sauvegardes');
    }

    public function deleteBackup(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard($request)) !== null) {
            return $deny;
        }
        if (strtoupper(trim((string) $request->input('confirm', ''))) !== self::DELETE_BACKUP_WORD) {
            Session::flash('error', 'Tapez ' . self::DELETE_BACKUP_WORD . ' pour confirmer.');

            return $this->back('sauvegardes');
        }
        try {
            $id = $this->snapshots()->assertSnapshotId((string) $request->input('id', ''));
            if (!$this->snapshots()->delete($id)) {
                throw new \RuntimeException('Sauvegarde introuvable ou impossible à effacer.');
            }
            $this->log(AuditAction::PLATFORM_OPS_BACKUP_DELETED, null, [], ['id' => $id]);
            Session::flash('success', 'Sauvegarde ' . $id . ' supprimée.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        return $this->back('sauvegardes');
    }

    // ------------------------------------------------------------------ comptes

    public function scheduleDeletion(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard($request)) !== null) {
            return $deny;
        }
        $userId = (int) $request->input('user_id');
        $tenantId = (int) $request->input('tenant_id');
        $days = (int) $request->input('days', (string) OpsAccountDeletionService::DEFAULT_DAYS);
        $result = $this->deletions()->schedule($userId, $tenantId, (int) Session::get('user_id'), $days, (string) $request->input('reason', ''));
        if ($result['ok']) {
            $this->log(AuditAction::PLATFORM_OPS_DELETION_SCHEDULED, $tenantId, [], ['user_id' => $userId, 'days' => OpsAccountDeletionService::clampDays($days)], 'user', $userId);
        }
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);

        return $this->back('donnees', $request);
    }

    public function cancelDeletion(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard($request)) !== null) {
            return $deny;
        }
        $id = (int) $request->input('id');
        $row = $this->deletions()->find($id);
        $result = $this->deletions()->cancel($id, (int) Session::get('user_id'));
        if ($result['ok'] && $row !== null) {
            $this->log(AuditAction::PLATFORM_OPS_DELETION_CANCELLED, (int) $row['tenant_id'], [], ['user_id' => (int) $row['user_id']], 'user', (int) $row['user_id']);
        }
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);

        return $this->back('donnees', $request);
    }

    /** Applique tout de suite une suppression programmée, ou programme puis applique (« Supprimer maintenant »). */
    public function executeDeletion(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard($request)) !== null) {
            return $deny;
        }
        $actorId = (int) Session::get('user_id');
        $id = (int) $request->input('id');
        if ($id > 0) {
            $row = $this->deletions()->find($id);
            if ($row === null || $row['cancelled_at'] !== null || $row['executed_at'] !== null) {
                Session::flash('error', 'Cette suppression n’est plus en attente.');

                return $this->back('donnees', $request);
            }
            $target = ['email' => $row['email']];
        } else {
            $target = $this->users->findById((int) $request->input('user_id'), (int) $request->input('tenant_id'));
            $refusal = $this->deletions()->refusal($target, $actorId);
            if ($refusal !== null) {
                Session::flash('error', $refusal);

                return $this->back('donnees', $request);
            }
        }
        if (!$this->emailConfirmed($request, $target)) {
            return $this->back('donnees', $request);
        }
        // Sauvegarde d'abord : si elle échoue, le compte n'est pas touché du tout.
        try {
            $snapshot = $this->createSnapshot('avant-suppression', true, false);
        } catch (\Throwable $e) {
            Session::flash('error', 'Sauvegarde préalable impossible, compte non supprimé : ' . $e->getMessage());

            return $this->back('donnees', $request);
        }
        if ($id < 1) {
            $scheduled = $this->deletions()->schedule((int) $target['id'], (int) $target['tenant_id'], $actorId, 1, (string) $request->input('reason', ''));
            if (!$scheduled['ok']) {
                Session::flash('error', $scheduled['message']);

                return $this->back('donnees', $request);
            }
            $id = (int) $scheduled['id'];
        }
        $row = $this->deletions()->find($id) ?? [];
        $result = $this->deletions()->executeNow($id, $actorId, (string) $snapshot['id']);
        if ($result['ok']) {
            $this->log(
                AuditAction::PLATFORM_OPS_DELETION_EXECUTED,
                (int) ($row['tenant_id'] ?? 0) ?: null,
                ['email' => (string) ($row['email'] ?? ''), 'display_name' => (string) ($row['display_name'] ?? '')],
                ['status' => 'deleted', 'snapshot' => $snapshot['id']],
                'user',
                (int) ($row['user_id'] ?? 0) ?: null
            );
            $result['message'] .= ' Sauvegarde de la base juste avant : ' . $snapshot['id'] . '.';
        }
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);

        return $this->back('donnees', $request);
    }

    // ------------------------------------------------------------------ outils

    /** @return array<string, mixed> */
    private function migrationStatus(): array
    {
        require_once base_path('bootstrap/migration_pdo.php');
        require_once base_path('bootstrap/migrations_web_ui.php');
        $status = migrations_web_collect_status(base_path());
        $log = migrations_web_last_log_text(base_path());
        $lines = $log === '' ? [] : (preg_split('/\r\n|\r|\n/', rtrim($log)) ?: []);
        $status['log_tail'] = array_slice($lines, -250);
        $status['log_lines'] = count($lines);

        return $status;
    }

    private function stepOutput(string $stepId): string
    {
        try {
            return \App\Support\Migrations\MigrationStepTracker::redact((string) MigrationRunLog::lastOutput(Database::getPdo(), $stepId));
        } catch (\Throwable) {
            return '';
        }
    }

    /** @return list<array<string, mixed>> */
    private function backupRows(): array
    {
        $rows = [];
        foreach ($this->snapshots()->list() as $m) {
            $id = (string) ($m['id'] ?? '');
            $m['size'] = $id !== '' ? $this->snapshots()->sizeOnDisk($id) : 0;
            $rows[] = $m;
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    private function createSnapshot(string $label, bool $db, bool $files): array
    {
        $this->longRunning();
        $manifest = $this->snapshots()->create($label, $db, $files);
        $this->log(AuditAction::PLATFORM_OPS_BACKUP_CREATED, null, [], [
            'id' => (string) $manifest['id'],
            'database' => $db,
            'files' => $files,
            'method' => (string) ($manifest['database']['method'] ?? ''),
        ]);

        return $manifest;
    }

    private function zipFiles(string $id, string $dir): string
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('Extension PHP zip absente : téléchargez les fichiers depuis le serveur.');
        }
        $source = $dir . '/files';
        if (!is_dir($source)) {
            throw new \RuntimeException('Cette sauvegarde ne contient pas les fichiers.');
        }
        $this->longRunning();
        $tmpDir = base_path('storage/cache/ops-downloads');
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0770, true);
        }
        foreach (glob($tmpDir . '/*.zip') ?: [] as $old) {
            if (@filemtime($old) < time() - 3600) {
                @unlink($old);
            }
        }
        $zipPath = $tmpDir . '/' . $id . '-' . bin2hex(random_bytes(4)) . '.zip';
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Archive impossible à créer.');
        }
        $base = strlen(rtrim($source, '/')) + 1;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->isFile()) {
                $zip->addFile($file->getPathname(), str_replace('\\', '/', substr($file->getPathname(), $base)));
            }
        }
        $zip->close();

        return $zipPath;
    }

    /** @param array<string, mixed>|null $target */
    private function emailConfirmed(Request $request, ?array $target): bool
    {
        $email = strtolower(trim((string) ($target['email'] ?? '')));
        $typed = strtolower(trim((string) $request->input('confirm', '')));
        if ($email === '' || !hash_equals($email, $typed)) {
            Session::flash('error', 'Recopiez l’adresse e-mail exacte du compte pour confirmer : rien n’a été supprimé.');

            return false;
        }

        return true;
    }

    /** Refus commun : CSRF, session et double vérification. */
    private function guard(Request $request): ?Response
    {
        if (!Csrf::verify($request)) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return $this->back();
        }
        $uid = (int) Session::get('user_id');
        if (!$this->stepUp()->isVerified($uid)) {
            Session::flash('error', 'La console s’est verrouillée : confirmez de nouveau votre identité.');

            return Response::redirect(url('admin/system/exploitation'));
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    private function me(): ?array
    {
        $uid = (int) Session::get('user_id');

        return $uid > 0 ? $this->users->findById($uid, null) : null;
    }

    private function back(string $tab = '', ?Request $request = null): Response
    {
        $url = url('admin/system/exploitation');
        $query = [];
        if ($tab !== '') {
            $query['onglet'] = $tab;
        }
        $q = $request !== null ? trim((string) $request->input('q', '')) : '';
        if ($q !== '') {
            $query['q'] = $q;
        }

        return Response::redirect($url . ($query !== [] ? '?' . http_build_query($query) : ''));
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, mixed> $new
     */
    private function log(string $action, ?int $tenantId, array $old, array $new, ?string $entityType = 'ops_console', ?int $entityId = null): void
    {
        try {
            $this->audit->logChange($action, $tenantId, (int) Session::get('user_id'), $entityType, $entityId, $old, $new);
        } catch (\Throwable $e) {
            error_log('[ops_console] audit : ' . $e->getMessage());
        }
    }

    private function longRunning(): void
    {
        @set_time_limit(0);
        @ignore_user_abort(true);
    }

    /** @param array<string, mixed> $data */
    private function view(string $title, array $data): Response
    {
        return Response::view('layout.main', $data + [
            'title' => $title,
            'content' => 'admin.system.ops_console',
            'backOfficePageCss' => ['platform-admin.css', 'ops-console.css'],
        ]);
    }

    private function stepUp(): OpsConsoleStepUp
    {
        return $this->stepUp ??= new OpsConsoleStepUp(
            $this->users,
            Container::get(\App\Services\Auth\LoginSecurityOtpService::class),
            Container::get(\App\Services\EmailService::class),
        );
    }

    private function snapshots(): CompleteDataSnapshotService
    {
        return $this->snapshots ??= CompleteDataSnapshotService::fromApp();
    }

    private function deletions(): OpsAccountDeletionService
    {
        return $this->deletions ??= new OpsAccountDeletionService(
            $this->users,
            Container::get(\App\Services\Account\AccountDeletionService::class),
        );
    }
}
