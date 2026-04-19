<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\DoctrineRepository;
use App\Services\Auth\AuthService;

final class DoctrineAdminController
{
    public function __construct(
        private AuthService $authService,
        private DoctrineRepository $doctrineRepository
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        if (!Gate::getInstance()->allows('admin.organization')) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé.');
        }

        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        if ($tenantId < 1) {
            return Response::redirect(url('dashboard'));
        }

        $this->doctrineRepository->ensureSchema();
        $documents = $this->doctrineRepository->listDocumentsWithVersions($tenantId);

        return Response::view('layout.main', [
            'title' => 'Doctrine & SOP',
            'content' => 'admin.organization.doctrine',
            'doctrine_documents' => $documents,
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/doctrine'));
        }
        $user = $this->authService->user();
        if (!$user || !Gate::getInstance()->allows('admin.organization')) {
            return Response::redirect(url('back-office/doctrine'));
        }

        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        if ($tenantId < 1) {
            return Response::redirect(url('dashboard'));
        }
        $this->doctrineRepository->ensureSchema();

        $title = trim((string) $request->input('title', ''));
        $type = trim((string) $request->input('document_type', 'sop'));
        $version = trim((string) $request->input('version_label', '1.0.0'));
        $effectiveAt = trim((string) $request->input('effective_at', ''));
        $content = trim((string) $request->input('content_markdown', ''));

        if ($title === '' || $content === '') {
            Session::flash('error', 'Titre et contenu requis.');

            return Response::redirect(url('back-office/doctrine'));
        }

        if (!in_array($type, ['sop', 'checklist', 'report_format'], true)) {
            $type = 'sop';
        }

        $this->doctrineRepository->createDocumentWithVersion(
            $tenantId,
            (int) ($user['id'] ?? 0),
            mb_substr($title, 0, 180),
            $type,
            mb_substr($version !== '' ? $version : '1.0.0', 0, 20),
            $effectiveAt !== '' ? $effectiveAt . ' 00:00:00' : null,
            $content
        );

        Session::flash('success', 'Document doctrine créé.');

        return Response::redirect(url('back-office/doctrine'));
    }

    public function activate(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/doctrine'));
        }
        $user = $this->authService->user();
        if (!$user || !Gate::getInstance()->allows('admin.organization')) {
            return Response::redirect(url('back-office/doctrine'));
        }

        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $versionId = (int) ($params['versionId'] ?? 0);
        if ($tenantId > 0 && $versionId > 0) {
            $this->doctrineRepository->ensureSchema();
            $ok = $this->doctrineRepository->activateVersion($tenantId, $versionId, (int) ($user['id'] ?? 0));
            if ($ok) {
                Session::flash('success', 'Version activée.');
            } else {
                Session::flash('error', 'Version introuvable.');
            }
        }

        return Response::redirect(url('back-office/doctrine'));
    }
}
