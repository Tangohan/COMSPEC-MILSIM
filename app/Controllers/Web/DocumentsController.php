<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\DocumentCategoryRepository;
use App\Repositories\DocumentLinkRepository;
use App\Repositories\DocumentRepository;
use App\Repositories\ModerationArtifactRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Services\Audit\AuditService;
use App\Services\Documents\DocumentAccessService;
use App\Services\Documents\DocumentTrainingReferencesService;
use App\Services\Moderation\ModerationArtifactState;

class DocumentsController
{
    private const STORAGE_BASE = 'storage/documents/';

    public function __construct(
        private DocumentRepository $documentRepository,
        private DocumentCategoryRepository $categoryRepository,
        private DocumentLinkRepository $linkRepository,
        private DocumentAccessService $documentAccessService,
        private AuditService $auditService,
        private ModerationArtifactRepository $moderationArtifactRepository,
        private DocumentTrainingReferencesService $documentTrainingReferencesService,
        private PersonnelProfileRepository $personnelProfileRepository
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        if (Gate::getInstance()->deny('documents.view')) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé.');
        }
        $tenantId = (int) $tenantId;
        $categoryId = $request->input('category') ? (int) $request->input('category') : null;
        $search = $request->input('q') ? trim((string) $request->input('q')) : null;
        $documentType = $request->input('document_type') ? trim((string) $request->input('document_type')) : null;
        if ($documentType === '') {
            $documentType = null;
        }
        $sortRaw = $request->input('sort') ? trim((string) $request->input('sort')) : '';
        $allowedSort = ['title_asc', 'title_desc', 'updated_desc', 'updated_asc'];
        $sort = in_array($sortRaw, $allowedSort, true) ? $sortRaw : 'title_asc';
        $entityType = $request->input('entity_type') ? trim((string) $request->input('entity_type')) : null;
        $entityId = $request->input('entity_id') ? (int) $request->input('entity_id') : null;
        $docs = $this->documentRepository->listForTenant(
            $tenantId,
            $categoryId,
            'published',
            $search,
            $entityType,
            $entityId,
            $documentType,
            null,
            $sort
        );
        $userId = (int) Session::get('user_id');
        $docs = array_values(array_filter($docs, function ($d) use ($userId, $tenantId) {
            if (!$this->documentAccessService->canRead($d, $userId, $tenantId)) {
                return false;
            }

            return !$this->isDocumentLifecycleBlocked($d) || $this->viewerMayBypassLifecycleBlock();
        }));
        $categoriesList = $this->categoryRepository->listForTenant($tenantId);
        $documentTrainingRefs = $this->documentTrainingReferencesService->mapByDocumentId($tenantId, $docs);
        $collections = $this->buildCollections($docs, $categoriesList);
        $accreditation = $this->personnelProfileRepository->getByUserId($userId);
        return Response::view('layout.main', [
            'content' => 'documents.index',
            'title' => 'Documents',
            'documents' => $docs,
            'categories' => $categoriesList,
            'currentCategoryId' => $categoryId,
            'search' => $search ?? '',
            'documentType' => $documentType ?? '',
            'sort' => $sort,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'documentTrainingRefs' => $documentTrainingRefs,
            'collections' => $collections,
            'viewerAccreditationLevel' => (string) ($accreditation['clearance_level'] ?? 'interne'),
        ]);
    }

    /**
     * @param list<array<string, mixed>> $docs
     * @param list<array<string, mixed>> $categories
     * @return list<array{title: string, description: string, href: string, count: int}>
     */
    private function buildCollections(array $docs, array $categories): array
    {
        $collections = [];
        $typeCounts = [];
        foreach ($docs as $doc) {
            $type = trim((string) ($doc['document_type'] ?? ''));
            if ($type === '') {
                continue;
            }
            $typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;
        }
        arsort($typeCounts);
        foreach (array_slice($typeCounts, 0, 3, true) as $type => $count) {
            $collections[] = [
                'title' => 'Collection ' . str_replace('_', ' ', ucfirst($type)),
                'description' => 'Regroupe les documents de type "' . str_replace('_', ' ', $type) . '".',
                'href' => url('documents?document_type=' . rawurlencode($type)),
                'count' => (int) $count,
            ];
        }

        foreach (array_slice($categories, 0, 2) as $cat) {
            $catId = (int) ($cat['id'] ?? 0);
            if ($catId <= 0) {
                continue;
            }
            $count = 0;
            foreach ($docs as $doc) {
                if ((int) ($doc['document_category_id'] ?? 0) === $catId) {
                    $count++;
                }
            }
            if ($count > 0) {
                $collections[] = [
                    'title' => 'Dossier ' . (string) ($cat['name'] ?? 'Catégorie'),
                    'description' => 'Collection thématique configurable depuis la gestion documentaire.',
                    'href' => url('documents?category=' . $catId),
                    'count' => $count,
                ];
            }
        }

        return array_slice($collections, 0, 5);
    }

    public function show(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        if (Gate::getInstance()->deny('documents.view')) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé.');
        }
        $slug = $params['slug'] ?? '';
        $doc = $this->documentRepository->findBySlug($slug, (int) $tenantId);
        if (!$doc) {
            return (new Response())->setStatusCode(404)->setBody('Document non trouvé.');
        }
        if (!$this->documentAccessService->canRead($doc, (int) Session::get('user_id'), (int) $tenantId)) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé à ce document.');
        }
        if (($doc['status'] ?? '') !== 'published') {
            return (new Response())->setStatusCode(404)->setBody('Document non disponible.');
        }
        if ($this->isDocumentLifecycleBlocked($doc) && !$this->viewerMayBypassLifecycleBlock()) {
            return (new Response())->setStatusCode(423)->setBody('Document bloqué : revue/correction/suppression requise.');
        }
        if (empty($doc['file_path']) || empty($doc['mime_type'])) {
            return (new Response())->setStatusCode(404)->setBody('Aucune version de fichier.');
        }
        if ($this->isDocumentFileBlockedForViewer($doc)) {
            return (new Response())->setStatusCode(403)->setBody('Fichier non disponible (modération).');
        }
        $viewType = 'pdf';
        if (str_starts_with($doc['mime_type'], 'image/')) {
            $viewType = 'image';
        }
        return Response::view('layout.main', [
            'content' => 'documents.show',
            'title' => $doc['title'],
            'document' => $doc,
            'viewType' => $viewType,
            'lifecycleBlocked' => $this->isDocumentLifecycleBlocked($doc),
        ]);
    }

    /** Stream du fichier pour affichage inline (lecteur PDF / image). */
    public function file(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return (new Response())->setStatusCode(403)->setBody('Non autorisé');
        }
        if (Gate::getInstance()->deny('documents.view')) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé.');
        }
        $id = (int) ($params['id'] ?? 0);
        $doc = $this->documentRepository->findById($id, (int) $tenantId);
        if (!$doc || empty($doc['file_path'])) {
            return (new Response())->setStatusCode(404)->setBody('Document non trouvé');
        }
        if (!$this->documentAccessService->canRead($doc, (int) Session::get('user_id'), (int) $tenantId)) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé');
        }
        if (($doc['status'] ?? '') !== 'published') {
            return (new Response())->setStatusCode(404)->setBody('Document non disponible');
        }
        if ($this->isDocumentLifecycleBlocked($doc) && !$this->viewerMayBypassLifecycleBlock()) {
            return (new Response())->setStatusCode(423)->setBody('Document bloqué : revue/correction/suppression requise.');
        }
        if ($this->isDocumentFileBlockedForViewer($doc)) {
            return (new Response())->setStatusCode(403)->setBody('Fichier non disponible (modération)');
        }
        $fullPath = base_path(self::STORAGE_BASE . $doc['file_path']);
        if (!is_file($fullPath)) {
            return (new Response())->setStatusCode(404)->setBody('Fichier absent');
        }
        $response = new Response();
        $response->header('Content-Type', $doc['mime_type'] ?: 'application/octet-stream');
        $response->header('Content-Disposition', 'inline; filename="' . basename($doc['file_path']) . '"');
        $response->header('Content-Length', (string) filesize($fullPath));
        $response->setBodyStream(static function () use ($fullPath): void {
            $h = fopen($fullPath, 'rb');
            if ($h) {
                fpassthru($h);
                fclose($h);
            }
        });
        return $response;
    }

    public function download(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('user_id');
        if (!$tenantId) {
            return (new Response())->setStatusCode(403)->setBody('Non autorisé');
        }
        if (Gate::getInstance()->deny('documents.view')) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé.');
        }
        $id = (int) ($params['id'] ?? 0);
        $doc = $this->documentRepository->findById($id, (int) $tenantId);
        if (!$doc || empty($doc['file_path'])) {
            return (new Response())->setStatusCode(404)->setBody('Document non trouvé');
        }
        if (!$this->documentAccessService->canRead($doc, (int) Session::get('user_id'), (int) $tenantId)) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé');
        }
        if (($doc['status'] ?? '') !== 'published') {
            return (new Response())->setStatusCode(404)->setBody('Document non disponible');
        }
        if ($this->isDocumentLifecycleBlocked($doc) && !$this->viewerMayBypassLifecycleBlock()) {
            return (new Response())->setStatusCode(423)->setBody('Document bloqué : revue/correction/suppression requise.');
        }
        if ($this->isDocumentFileBlockedForViewer($doc)) {
            return (new Response())->setStatusCode(403)->setBody('Fichier non disponible (modération)');
        }
        $fullPath = base_path(self::STORAGE_BASE . $doc['file_path']);
        if (!is_file($fullPath)) {
            return (new Response())->setStatusCode(404)->setBody('Fichier absent');
        }
        $this->auditService->logDocumentDownloaded((int) $tenantId, $userId ? (int) $userId : 0, $id);
        $response = new Response();
        $response->header('Content-Type', $doc['mime_type'] ?: 'application/octet-stream');
        $response->header('Content-Disposition', 'attachment; filename="' . basename($doc['file_path']) . '"');
        $response->header('Content-Length', (string) filesize($fullPath));
        $response->setBodyStream(static function () use ($fullPath): void {
            $h = fopen($fullPath, 'rb');
            if ($h) {
                fpassthru($h);
                fclose($h);
            }
        });
        return $response;
    }

    /** @param array<string, mixed> $doc */
    private function isDocumentLifecycleBlocked(array $doc): bool
    {
        $status = (string) ($doc['status'] ?? '');
        if ($status !== 'published') {
            return false;
        }
        $reviewDueAt = trim((string) ($doc['review_due_at'] ?? ''));
        if ($reviewDueAt !== '' && strtotime($reviewDueAt) !== false && strtotime($reviewDueAt) < time()) {
            return true;
        }
        $updatedAt = trim((string) ($doc['updated_at'] ?? ''));
        $createdAt = trim((string) ($doc['created_at'] ?? ''));
        $pivot = $updatedAt !== '' ? $updatedAt : $createdAt;
        if ($pivot !== '' && strtotime($pivot) !== false) {
            $staleLimit = strtotime('-180 days');
            if ($staleLimit !== false && strtotime($pivot) < $staleLimit) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $doc
     */
    private function isDocumentFileBlockedForViewer(array $doc): bool
    {
        if (!$this->moderationArtifactRepository->tableExists()) {
            return false;
        }
        $versionId = (int) ($doc['version_id'] ?? 0);
        if ($versionId <= 0) {
            return false;
        }
        if ($this->viewerMayBypassModerationBlock()) {
            return false;
        }
        $row = $this->moderationArtifactRepository->findByDocumentVersionId($versionId);
        if (!$row) {
            return false;
        }
        $st = (string) ($row['state'] ?? '');

        return in_array($st, [
            ModerationArtifactState::PENDING_SCAN,
            ModerationArtifactState::QUARANTINED,
            ModerationArtifactState::REJECTED,
        ], true);
    }

    private function viewerMayBypassLifecycleBlock(): bool
    {
        $gate = Gate::getInstance();

        return (function_exists('can') && (can('documents.update') || can('admin.access')))
            || $gate->allows('admin.access')
            || $gate->allows('admin.organization')
            || $gate->allows('admin.system');
    }

    private function viewerMayBypassModerationBlock(): bool
    {
        $gate = Gate::getInstance();

        return (function_exists('can') && (can('forum.moderate') || can('forum.moderate_organization')))
            || $gate->allows('admin.organization')
            || $gate->allows('admin.access')
            || $gate->allows('admin.system');
    }
}
