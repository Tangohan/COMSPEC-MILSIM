<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\DocumentRepository;
use App\Repositories\DocumentVersionRepository;
use App\Repositories\Doctrine\DocumentDoctrineRepository;
use App\Repositories\Doctrine\DocumentViewRepository;
use App\Services\Doctrine\DoctrineAcknowledgmentService;
use App\Services\Doctrine\DoctrineDocumentAccessService;
use App\Services\Doctrine\DocumentComplianceService;
use App\Support\Doctrine\DoctrineComplianceStatus;
use App\Support\Doctrine\DoctrineWorkflowStatus;

final class DoctrineDocumentsController
{
    public function __construct(
        private DocumentRepository $documentRepository,
        private DocumentDoctrineRepository $doctrineRepository,
        private DocumentVersionRepository $versionRepository,
        private DoctrineDocumentAccessService $doctrineDocumentAccessService,
        private DocumentComplianceService $complianceService,
        private DocumentViewRepository $viewRepository,
        private DoctrineAcknowledgmentService $acknowledgmentService,
    ) {}

    public function show(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId < 1 || $userId < 1) {
            return Response::redirect(url('login'));
        }
        if (Gate::getInstance()->deny('doctrine.view') && Gate::getInstance()->deny('documents.view')) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé.');
        }

        $documentId = (int) ($params['id'] ?? 0);
        if ($documentId < 1) {
            return (new Response())->setStatusCode(404)->setBody('Doctrine introuvable.');
        }

        $doc = $this->documentRepository->findById($documentId, null);
        if ($doc === null) {
            return (new Response())->setStatusCode(404)->setBody('Doctrine introuvable.');
        }

        $scope = (string) ($doc['scope'] ?? 'tenant');
        if ($scope === 'tenant' && (int) ($doc['tenant_id'] ?? 0) !== $tenantId) {
            return (new Response())->setStatusCode(404)->setBody('Doctrine introuvable.');
        }

        $doctrine = $this->doctrineRepository->findByDocumentId($documentId, $tenantId);
        if ($doctrine === null) {
            return (new Response())->setStatusCode(404)->setBody('Ce document n’est pas une doctrine référencée.');
        }

        if (!$this->doctrineDocumentAccessService->canMemberView($tenantId, $userId, $doc, $doctrine)) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé.');
        }

        if ((string) ($doctrine['doctrine_status'] ?? '') !== DoctrineWorkflowStatus::PUBLISHED) {
            $canManage = Gate::getInstance()->allows('doctrine.edit') || Gate::getInstance()->allows('documents.upload');
            if (!$canManage) {
                return (new Response())->setStatusCode(403)->setBody('Cette doctrine n’est pas encore publiée.');
            }
        }

        $versions = $this->versionRepository->listForDocument($documentId);
        $currentVersionId = (int) ($doc['version_id'] ?? 0);
        $currentVersion = null;
        foreach ($versions as $v) {
            if ((int) ($v['id'] ?? 0) === $currentVersionId) {
                $currentVersion = $v;
                break;
            }
        }
        if ($currentVersion === null && $versions !== []) {
            $currentVersion = $versions[0];
            $currentVersionId = (int) ($currentVersion['id'] ?? 0);
        }

        if ($currentVersionId > 0) {
            $this->viewRepository->recordView($tenantId, $documentId, $currentVersionId, $userId);
        }

        $compliance = $currentVersionId > 0
            ? $this->complianceService->memberBadge($tenantId, $userId, $doctrine, $currentVersionId)
            : ['badge' => DoctrineComplianceStatus::NOT_APPLICABLE, 'label' => '—', 'tone' => 'neutral', 'sort_priority' => 9];

        $needsAckModal = in_array($compliance['badge'], [
            DoctrineComplianceStatus::ACK_REQUIRED,
            DoctrineComplianceStatus::ACK_OUTDATED,
            DoctrineComplianceStatus::OVERDUE,
        ], true);

        return Response::view('layout.main', [
            'content' => 'documents/doctrine_show',
            'title' => (string) ($doctrine['reference_code'] ?? $doc['title'] ?? 'Doctrine'),
            'document' => $doc,
            'doctrine' => $doctrine,
            'versions' => $versions,
            'currentVersion' => $currentVersion,
            'compliance' => $compliance,
            'needsAckModal' => $needsAckModal,
            'deadlineLabel' => $this->complianceService->deadlineLabel($doctrine['acknowledgment_deadline_at'] ?? null),
            'csrf_token' => Csrf::token(),
        ]);
    }

    public function acknowledge(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId < 1 || $userId < 1) {
            return Response::json(['success' => false, 'error' => 'Connexion requise.'], 401);
        }

        $token = (string) ($request->input('_csrf_token') ?? $request->input('csrf_token') ?? '');
        if (!Csrf::validate($token)) {
            return Response::json(['success' => false, 'error' => 'Session expirée.'], 403);
        }

        $documentId = (int) ($params['id'] ?? 0);
        if ($documentId < 1) {
            return Response::json(['success' => false, 'error' => 'Document invalide.'], 400);
        }

        if (empty($request->input('certify'))) {
            return Response::json(['success' => false, 'error' => 'Vous devez certifier avoir pris connaissance du document.'], 400);
        }

        $doc = $this->documentRepository->findById($documentId, null);
        if ($doc === null) {
            return Response::json(['success' => false, 'error' => 'Document introuvable.'], 404);
        }
        $scope = (string) ($doc['scope'] ?? 'tenant');
        if ($scope === 'tenant' && (int) ($doc['tenant_id'] ?? 0) !== $tenantId) {
            return Response::json(['success' => false, 'error' => 'Document introuvable.'], 404);
        }

        $doctrine = $this->doctrineRepository->findByDocumentId($documentId, $tenantId);
        if ($doctrine === null) {
            return Response::json(['success' => false, 'error' => 'Doctrine introuvable.'], 404);
        }

        if (!$this->doctrineDocumentAccessService->canMemberView($tenantId, $userId, $doc, $doctrine)) {
            return Response::json(['success' => false, 'error' => 'Accès refusé.'], 403);
        }

        $versionId = (int) ($request->input('version_id') ?? $doc['version_id'] ?? 0);
        $version = $this->versionRepository->findById($versionId, $documentId);
        if ($version === null) {
            return Response::json(['success' => false, 'error' => 'Version invalide.'], 400);
        }

        $ip = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
        if (str_contains($ip, ',')) {
            $ip = trim(explode(',', $ip)[0]);
        }
        $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');

        $result = $this->acknowledgmentService->sign($tenantId, $userId, $doctrine, $version, $ip, $ua);
        if (!$result['ok']) {
            return Response::json(['success' => false, 'error' => $result['error']], 400);
        }

        return Response::json(['success' => true]);
    }
}
