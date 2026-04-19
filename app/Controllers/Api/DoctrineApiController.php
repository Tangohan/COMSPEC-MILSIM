<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\DoctrineRepository;
use App\Services\Auth\AuthService;
use App\Support\Api\ApiResponder;

final class DoctrineApiController
{
    public function __construct(
        private AuthService $authService,
        private DoctrineRepository $doctrineRepository
    ) {}

    public function list(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        if (!$user) {
            return ApiResponder::error('unauthorized', 'Non autorisé.', 401);
        }
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        if ($tenantId < 1) {
            return ApiResponder::error('tenant_missing', 'Communauté non sélectionnée.', 400);
        }
        $this->doctrineRepository->ensureSchema();

        return ApiResponder::success([
            'items' => $this->doctrineRepository->listDocumentsWithVersions($tenantId),
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        if (!$user || !Gate::getInstance()->allows('admin.organization')) {
            return ApiResponder::error('forbidden', 'Accès refusé.', 403);
        }
        if (!$this->validateCsrf($request)) {
            return ApiResponder::error('csrf_invalid', 'Token CSRF invalide.', 403);
        }
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        if ($tenantId < 1) {
            return ApiResponder::error('tenant_missing', 'Communauté non sélectionnée.', 400);
        }

        $body = $this->jsonBody($request);
        $title = trim((string) ($body['title'] ?? ''));
        $content = trim((string) ($body['content_markdown'] ?? ''));
        if ($title === '' || $content === '') {
            return ApiResponder::error('validation_failed', 'title et content_markdown sont requis.', 422);
        }
        $type = trim((string) ($body['document_type'] ?? 'sop'));
        if (!in_array($type, ['sop', 'checklist', 'report_format'], true)) {
            $type = 'sop';
        }
        $version = trim((string) ($body['version_label'] ?? '1.0.0'));
        $effectiveAt = trim((string) ($body['effective_at'] ?? ''));

        $this->doctrineRepository->ensureSchema();
        $docId = $this->doctrineRepository->createDocumentWithVersion(
            $tenantId,
            (int) ($user['id'] ?? 0),
            mb_substr($title, 0, 180),
            $type,
            mb_substr($version !== '' ? $version : '1.0.0', 0, 20),
            $effectiveAt !== '' ? $effectiveAt : null,
            $content
        );

        return ApiResponder::success(['document_id' => $docId], 201);
    }

    public function activate(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        if (!$user || !Gate::getInstance()->allows('admin.organization')) {
            return ApiResponder::error('forbidden', 'Accès refusé.', 403);
        }
        if (!$this->validateCsrf($request)) {
            return ApiResponder::error('csrf_invalid', 'Token CSRF invalide.', 403);
        }
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $versionId = (int) ($params['versionId'] ?? 0);
        if ($tenantId < 1 || $versionId < 1) {
            return ApiResponder::error('validation_failed', 'Paramètres invalides.', 422);
        }

        $this->doctrineRepository->ensureSchema();
        $ok = $this->doctrineRepository->activateVersion($tenantId, $versionId, (int) ($user['id'] ?? 0));
        if (!$ok) {
            return ApiResponder::error('not_found', 'Version introuvable.', 404);
        }

        return ApiResponder::success(['activated' => true]);
    }

    public function acknowledge(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        if (!$user) {
            return ApiResponder::error('unauthorized', 'Non autorisé.', 401);
        }
        if (!$this->validateCsrf($request)) {
            return ApiResponder::error('csrf_invalid', 'Token CSRF invalide.', 403);
        }
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $versionId = (int) ($params['versionId'] ?? 0);
        if ($tenantId < 1 || $versionId < 1) {
            return ApiResponder::error('validation_failed', 'Paramètres invalides.', 422);
        }
        $this->doctrineRepository->ensureSchema();
        $this->doctrineRepository->acknowledge($tenantId, $versionId, (int) ($user['id'] ?? 0));

        return ApiResponder::success(['acknowledged' => true]);
    }

    private function validateCsrf(Request $request): bool
    {
        $body = $this->jsonBody($request);
        $token = $request->input('_csrf_token')
            ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)
            ?? ($body['_csrf_token'] ?? null);

        return is_string($token) && Csrf::validate($token);
    }

    private function jsonBody(Request $request): array
    {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw ?: '[]', true);
        if (is_array($decoded)) {
            return $decoded;
        }

        return array_merge($request->all(), $_POST);
    }
}
