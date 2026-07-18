<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PlatformAppReleaseRepository;
use App\Services\Deployment\AppVersionStore;
use App\Services\Deployment\HealthCheckService;
use App\Services\Deployment\ReleaseManager;
use App\Services\Deployment\UpdatePackageService;

final class SystemUpdatesController
{
    public function __construct(
        private PlatformAppReleaseRepository $releases,
        private UpdatePackageService $packages,
        private ReleaseManager $manager,
        private AppVersionStore $versions,
        private HealthCheckService $health,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $ready = $this->releases->schemaReady();
        $selectedId = (int) ($request->query['release'] ?? 0);
        $selected = $selectedId > 0 ? $this->releases->find($selectedId) : null;
        $files = $selected ? $this->releases->listFiles((int) $selected['id']) : [];
        $logs = $ready ? $this->releases->listLogs($selected ? (int) $selected['id'] : null, 50) : [];

        return Response::view('layout.main', [
            'content' => 'admin.system.updates',
            'title' => 'Mises à jour de la plateforme',
            'updatesSchemaReady' => $ready,
            'updatesCurrentVersion' => $this->versions->current(),
            'updatesReleases' => $ready ? $this->releases->listRecent(40) : [],
            'updatesSelected' => $selected,
            'updatesFiles' => $files,
            'updatesLogs' => $logs,
            'updatesLock' => $ready ? $this->releases->lockStatus() : null,
            'updatesHealth' => $this->health->run(),
            'updatesCsrf' => Csrf::token(),
            'updatesSignatureRequired' => (new \App\Services\Deployment\PackageSignatureVerifier())->isEnforced(),
        ]);
    }

    public function upload(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('admin/system/updates'));
        }
        if (!$this->releases->schemaReady()) {
            Session::flash('error', 'Les tables de mises à jour ne sont pas installées. Exécutez la migration de la base.');

            return Response::redirect(url('admin/system/updates'));
        }

        $file = $_FILES['package'] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Session::flash('error', 'Sélectionnez une archive de mise à jour valide (.zip).');

            return Response::redirect(url('admin/system/updates'));
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        $name = (string) ($file['name'] ?? 'package.zip');
        if (!str_ends_with(strtolower($name), '.zip')) {
            Session::flash('error', 'Le fichier doit être une archive .zip.');

            return Response::redirect(url('admin/system/updates'));
        }

        try {
            $result = $this->packages->ingestUploadedZip(
                $tmp,
                $name,
                Session::get('user_id') ? (int) Session::get('user_id') : null
            );
            Session::flash('success', 'Package validé : version ' . $result['version'] . '. Vérifiez l’aperçu puis déployez.');

            return Response::redirect(url('admin/system/updates?release=' . $result['release_id']));
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());

            return Response::redirect(url('admin/system/updates'));
        }
    }

    public function deploy(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('admin/system/updates'));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            $this->manager->deploy($id, Session::get('user_id') ? (int) Session::get('user_id') : null);
            Session::flash('success', 'Mise à jour déployée avec succès.');
        } catch (\Throwable $e) {
            Session::flash('error', 'Échec du déploiement : ' . $e->getMessage());
        }

        return Response::redirect(url('admin/system/updates?release=' . $id));
    }

    public function rollback(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('admin/system/updates'));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            $this->manager->rollback($id, Session::get('user_id') ? (int) Session::get('user_id') : null);
            Session::flash('success', 'Version précédente restaurée.');
        } catch (\Throwable $e) {
            Session::flash('error', 'Échec de la restauration : ' . $e->getMessage());
        }

        return Response::redirect(url('admin/system/updates?release=' . $id));
    }
}
