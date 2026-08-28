<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use App\Services\Platform\PlatformStorageService;
use App\Support\MaintenanceGuard;
use App\Support\PlatformStorageCatalog;

final class SystemStorageController
{
    public function __construct(
        private ?PlatformStorageService $storage = null,
        private ?AuditService $audit = null,
    ) {
        $this->storage ??= new PlatformStorageService();
        $this->audit ??= new AuditService();
    }

    public function index(Request $request, array $params = []): Response
    {
        $groups = [];
        foreach (PlatformStorageCatalog::purgeGroups() as $group) {
            $usage = $this->storage->groupUsage($group);
            $bytes = 0;
            foreach ($usage['tables'] as $t) {
                $bytes += (int) ($t['bytes'] ?? 0);
            }
            foreach ($usage['directories'] as $d) {
                $bytes += (int) ($d['bytes'] ?? 0);
            }
            $groups[] = $group + ['usage' => $usage, 'bytes' => $bytes];
        }

        return Response::view('layout.main', [
            'content' => 'admin.system.storage',
            'title' => 'Espace disque et historiques',
            'disk' => $this->storage->diskSnapshot(),
            'directories' => $this->storage->directorySnapshots(),
            'largestTables' => $this->storage->largestTables(20),
            'purgeGroups' => $groups,
            'confirmWord' => PlatformStorageCatalog::CONFIRM_WORD,
        ]);
    }

    public function purge(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('admin/system/storage'));
        }

        $key = trim((string) $request->input('group_key', ''));
        $typed = trim((string) $request->input('confirm_word', ''));
        $ack = $request->input('acknowledge') === '1';

        if (!$ack) {
            Session::flash('error', 'Cochez la confirmation : l’action concerne toutes les communautés.');

            return Response::redirect(url('admin/system/storage'));
        }
        if (strcasecmp($typed, PlatformStorageCatalog::CONFIRM_WORD) !== 0) {
            Session::flash('error', 'Saisissez le mot de confirmation indiqué pour valider le vidage.');

            return Response::redirect(url('admin/system/storage'));
        }

        $group = PlatformStorageCatalog::groupByKey($key);
        if ($group === null) {
            Session::flash('error', 'Lot introuvable.');

            return Response::redirect(url('admin/system/storage'));
        }

        @set_time_limit(300);
        $result = $this->storage->purgeGroup($key);
        $actorId = Session::get('user_id') ? (int) Session::get('user_id') : null;
        try {
            $this->audit->log(
                AuditAction::PLATFORM_STORAGE_PURGED,
                null,
                $actorId,
                'storage_purge',
                null,
                $group['title'],
                (string) ($result['files_removed'] ?? 0),
                MaintenanceGuard::resolveClientIp()
            );
        } catch (\Throwable) {
        }

        if (!empty($result['ok'])) {
            $files = (int) ($result['files_removed'] ?? 0);
            Session::flash(
                'success',
                $files > 0
                    ? 'Lot « ' . $group['title'] . ' » vidé. ' . $files . ' fichier(s) retiré(s) du disque.'
                    : 'Lot « ' . $group['title'] . ' » vidé.'
            );
        } else {
            Session::flash('error', (string) ($result['message'] ?? 'Vidage impossible.'));
        }

        return Response::redirect(url('admin/system/storage'));
    }
}
