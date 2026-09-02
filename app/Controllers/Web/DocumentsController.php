<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\DocumentCategoryRepository;
use App\Repositories\DocumentLinkRepository;
use App\Repositories\DocumentRepository;
use App\Repositories\DocumentSecurityRepository;
use App\Repositories\ModerationArtifactRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Services\Audit\AuditService;
use App\Services\Documents\DocumentAccessService;
use App\Services\Documents\DocumentTrainingReferencesService;
use App\Services\Moderation\ModerationArtifactState;
use App\Repositories\Doctrine\DocumentDoctrineRepository;
use App\Services\Doctrine\DoctrineDocumentAccessService;
use App\Services\Doctrine\DocumentComplianceService;
use App\Support\DocumentManuscript;

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
        private PersonnelProfileRepository $personnelProfileRepository,
        private DocumentSecurityRepository $documentSecurityRepository,
        private DocumentDoctrineRepository $documentDoctrineRepository,
        private DocumentComplianceService $documentComplianceService,
        private DoctrineDocumentAccessService $doctrineDocumentAccessService,
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
        $tenantForOrgCheck = (new \App\Repositories\TenantRepository())->findById($tenantId);
        if (is_array($tenantForOrgCheck) && ($tenantForOrgCheck['slug'] ?? '') === 'default') {
            return Response::view('layout.main', [
                'title' => 'Documents',
                'content' => 'partials.no_organization_empty_state',
                'noOrgTitle' => 'Aucune organisation',
                'noOrgMessage' => 'La documentation est propre à chaque communauté. Rejoignez une communauté avec un code d’invitation, ou créez la vôtre, pour y accéder.',
            ]);
        }
        $categoryId = $request->input('category') ? (int) $request->input('category') : null;
        $categorySlugFilter = trim((string) ($request->input('category_slug') ?? ''));
        $doctrineQuick = trim((string) ($request->input('doctrine_filter') ?? ''));
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
        $userId = (int) Session::get('user_id');
        $canManageCollections = Gate::getInstance()->allows('documents.upload') || Gate::getInstance()->allows('admin.access');
        $categoriesList = $this->categoryRepository->listForTenant($tenantId);
        $doctrineCategory = $this->categoryRepository->findBySlug('doctrine', $tenantId);
        $isDoctrineView = ($categoryId !== null && $doctrineCategory !== null && (int) ($doctrineCategory['id'] ?? 0) === $categoryId)
            || $categorySlugFilter === 'doctrine';

        if ($isDoctrineView && $this->documentDoctrineRepository->tableExists()) {
            return $this->doctrineReferentialIndex($tenantId, $userId, $search, $doctrineQuick, $categoriesList, $doctrineCategory, $canManageCollections);
        }

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
        $docs = array_values(array_filter($docs, function ($d) use ($userId, $tenantId) {
            if (!$this->documentAccessService->canRead($d, $userId, $tenantId)) {
                return false;
            }

            return !$this->isDocumentLifecycleBlocked($d) || $this->viewerMayBypassLifecycleBlock();
        }));
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
            'canManageCollections' => $canManageCollections,
            'focus' => (string) ($request->input('focus') ?? ''),
        ]);
    }

    public function collections(Request $request, array $params = []): Response
    {
        if (Gate::getInstance()->deny('documents.view')) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé.');
        }

        return Response::redirect(url('documents?focus=collections'));
    }

    public function accreditation(Request $request, array $params = []): Response
    {
        if (Gate::getInstance()->deny('documents.view')) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé.');
        }

        return Response::redirect(url('dossier-operateur/accreditation'));
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
        $authored = DocumentManuscript::isAuthored($doc);
        if (!$authored && (empty($doc['file_path']) || empty($doc['mime_type']))) {
            return (new Response())->setStatusCode(404)->setBody('Aucune version de fichier.');
        }
        if (!$authored && $this->isDocumentFileBlockedForViewer($doc)) {
            return (new Response())->setStatusCode(403)->setBody('Fichier non disponible (modération).');
        }
        $viewType = 'pdf';
        if ($authored) {
            $viewType = 'manuscript';
        } elseif (str_starts_with((string) ($doc['mime_type'] ?? ''), 'image/')) {
            $viewType = 'image';
        }
        $userId = (int) Session::get('user_id');
        $signatureRequired = !empty($doc['require_account_signature']);
        $sessionToken = $this->documentSecurityRepository->createSession((int) $tenantId, (int) $doc['id'], $userId, $signatureRequired);
        $this->documentSecurityRepository->logEvent($sessionToken, (int) $doc['id'], $userId, 'document_opened');

        return Response::view('layout.main', [
            'content' => 'documents.show',
            'title' => $doc['title'],
            'document' => $doc,
            'viewType' => $viewType,
            'manuscript' => $authored ? DocumentManuscript::forView($doc) : null,
            'documentFmPage' => $authored,
            'lifecycleBlocked' => $this->isDocumentLifecycleBlocked($doc),
            'securitySessionToken' => $sessionToken,
            'requiresAccessCode' => !empty($doc['require_access_code']),
            'requiresSignature' => $signatureRequired,
            'signatureBeforeDownload' => !empty($doc['signature_mandatory_before_download']),
            'isAccessCodeUnlocked' => $this->isAccessCodeUnlocked((int) $doc['id']),
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
        if (!$doc) {
            return $this->missingDocumentFilePage(null, $id);
        }
        if (empty($doc['file_path'])) {
            return $this->missingDocumentFilePage($doc, $id);
        }
        if (!empty($doc['require_access_code']) && !$this->isAccessCodeUnlocked($id)) {
            return (new Response())->setStatusCode(423)->setBody('Code d\'accès requis');
        }
        if ((int) ($doc['download_allowed'] ?? 1) !== 1) {
            return (new Response())->setStatusCode(403)->setBody('Téléchargement non autorisé');
        }
        if (!$this->canReadDocumentOrDoctrine($doc, (int) Session::get('user_id'), (int) $tenantId)) {
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
            return $this->missingDocumentFilePage($doc, $id);
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
        if (!$doc) {
            return $this->missingDocumentFilePage(null, $id);
        }
        if (empty($doc['file_path'])) {
            return $this->missingDocumentFilePage($doc, $id);
        }
        if (!empty($doc['require_access_code']) && !$this->isAccessCodeUnlocked($id)) {
            return (new Response())->setStatusCode(423)->setBody('Code d\'accès requis');
        }
        if ((int) ($doc['download_allowed'] ?? 1) !== 1) {
            return (new Response())->setStatusCode(403)->setBody('Téléchargement non autorisé');
        }
        if (!$this->canReadDocumentOrDoctrine($doc, (int) Session::get('user_id'), (int) $tenantId)) {
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
        $securitySessionToken = trim((string) $request->input('security_session_token'));
        if (!empty($doc['require_account_signature']) && !empty($doc['signature_mandatory_before_download'])) {
            $session = $securitySessionToken !== '' ? $this->documentSecurityRepository->findSessionByToken($securitySessionToken) : null;
            if (!$session || (int) ($session['document_id'] ?? 0) !== $id || empty($session['signature_completed_at'])) {
                return (new Response())->setStatusCode(423)->setBody('Signature numérique requise avant téléchargement');
            }
        }
        $fullPath = base_path(self::STORAGE_BASE . $doc['file_path']);
        if (!is_file($fullPath)) {
            return $this->missingDocumentFilePage($doc, $id);
        }
        $this->auditService->logDocumentDownloaded((int) $tenantId, $userId ? (int) $userId : 0, $id);
        if ($securitySessionToken !== '') {
            $this->documentSecurityRepository->markDownloaded($securitySessionToken);
            $this->documentSecurityRepository->logEvent($securitySessionToken, $id, $userId ? (int) $userId : null, 'document_downloaded');
        }
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

    /**
     * Garde CSRF des points POST JSON du lecteur de documents (déverrouillage, signature,
     * traçage de lecture). Le middleware global ne couvre que /back-office/ : ces routes
     * doivent donc valider le jeton elles-mêmes.
     */
    private function csrfGuard(Request $request): ?Response
    {
        if (Csrf::validate($request->input('_csrf_token'))) {
            return null;
        }

        return (new Response())->setStatusCode(419)->json([
            'ok' => false,
            'message' => 'Session expirée. Rechargez la page.',
        ]);
    }

    public function unlock(Request $request, array $params = []): Response
    {
        if (($deny = $this->csrfGuard($request)) !== null) {
            return $deny;
        }
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId <= 0 || $userId <= 0) {
            return (new Response())->setStatusCode(403)->json(['ok' => false, 'message' => 'Non autorisé']);
        }
        $id = (int) ($params['id'] ?? 0);
        $doc = $this->documentRepository->findById($id, $tenantId);
        if (!$doc) {
            return (new Response())->setStatusCode(404)->json(['ok' => false, 'message' => 'Document introuvable']);
        }
        $code = trim((string) $request->input('access_code'));
        $sessionToken = trim((string) $request->input('security_session_token'));
        $hash = (string) ($doc['access_code_hash'] ?? '');
        if ($code === '' || $hash === '' || !password_verify($code, $hash)) {
            if ($sessionToken !== '') {
                $this->documentSecurityRepository->logEvent($sessionToken, $id, $userId, 'access_code_failed');
            }

            return (new Response())->setStatusCode(422)->json(['ok' => false, 'message' => 'Code invalide']);
        }
        Session::set('doc_access_code_unlocked_' . $id, true);
        if ($sessionToken !== '') {
            $this->documentSecurityRepository->logEvent($sessionToken, $id, $userId, 'access_code_validated');
        }

        return (new Response())->json(['ok' => true]);
    }

    public function signature(Request $request, array $params = []): Response
    {
        if (($deny = $this->csrfGuard($request)) !== null) {
            return $deny;
        }
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId <= 0 || $userId <= 0) {
            return (new Response())->setStatusCode(403)->json(['ok' => false, 'message' => 'Non autorisé']);
        }
        $id = (int) ($params['id'] ?? 0);
        $sessionToken = trim((string) $request->input('security_session_token'));
        $signatureDataUrl = trim((string) $request->input('signature_data_url'));
        $signatureName = trim((string) $request->input('signature_name'));
        if ($sessionToken === '' || $signatureDataUrl === '' || $signatureName === '') {
            return (new Response())->setStatusCode(422)->json(['ok' => false, 'message' => 'Signature incomplète']);
        }
        $session = $this->documentSecurityRepository->findSessionByToken($sessionToken);
        if (!$session || (int) ($session['document_id'] ?? 0) !== $id || (int) ($session['tenant_id'] ?? 0) !== $tenantId) {
            return (new Response())->setStatusCode(404)->json(['ok' => false, 'message' => 'Session de sécurité invalide']);
        }
        if (!preg_match('#^data:image/png;base64,#', $signatureDataUrl)) {
            return (new Response())->setStatusCode(422)->json(['ok' => false, 'message' => 'Format de signature invalide']);
        }
        $raw = base64_decode(substr($signatureDataUrl, strlen('data:image/png;base64,')), true);
        if ($raw === false || strlen($raw) < 100) {
            return (new Response())->setStatusCode(422)->json(['ok' => false, 'message' => 'Signature vide']);
        }
        $relativeDir = 'signatures/' . date('Y/m');
        $baseDir = base_path(self::STORAGE_BASE . $relativeDir);
        if (!is_dir($baseDir)) {
            @mkdir($baseDir, 0775, true);
        }
        $filename = 'doc_' . $id . '_u_' . $userId . '_' . time() . '.png';
        $fullPath = $baseDir . '/' . $filename;
        file_put_contents($fullPath, $raw);
        $relativePath = 'documents/' . $relativeDir . '/' . $filename;

        $this->documentSecurityRepository->completeSignature($sessionToken, $signatureName, $relativePath);
        $this->documentSecurityRepository->logEvent($sessionToken, $id, $userId, 'signature_completed', ['signature_name' => $signatureName]);

        return (new Response())->json(['ok' => true]);
    }

    public function accessTrack(Request $request, array $params = []): Response
    {
        if (($deny = $this->csrfGuard($request)) !== null) {
            return $deny;
        }
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId <= 0 || $userId <= 0) {
            return (new Response())->setStatusCode(403)->json(['ok' => false]);
        }
        $id = (int) ($params['id'] ?? 0);
        $token = trim((string) $request->input('security_session_token'));
        if ($token === '') {
            return (new Response())->setStatusCode(422)->json(['ok' => false]);
        }
        $session = $this->documentSecurityRepository->findSessionByToken($token);
        if (!$session || (int) ($session['document_id'] ?? 0) !== $id || (int) ($session['tenant_id'] ?? 0) !== $tenantId) {
            return (new Response())->setStatusCode(404)->json(['ok' => false]);
        }
        $seconds = (int) $request->input('read_seconds');
        $eventType = trim((string) $request->input('event_type')) ?: 'heartbeat';
        if ($seconds > 0) {
            $this->documentSecurityRepository->addReadSeconds($token, $seconds);
        }
        $this->documentSecurityRepository->logEvent($token, $id, $userId, $eventType);
        if ($eventType === 'closed') {
            $this->documentSecurityRepository->closeSession($token);
        }

        return (new Response())->json(['ok' => true]);
    }

    private function isAccessCodeUnlocked(int $documentId): bool
    {
        return (bool) Session::get('doc_access_code_unlocked_' . $documentId);
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

    /**
     * @param list<array<string, mixed>> $categoriesList
     * @param array<string, mixed>|null $doctrineCategory
     */
    private function doctrineReferentialIndex(
        int $tenantId,
        int $userId,
        ?string $search,
        string $doctrineQuick,
        array $categoriesList,
        ?array $doctrineCategory,
        bool $canManage,
    ): Response {
        $rows = $this->documentDoctrineRepository->listPublishedForTenant(
            $tenantId,
            $doctrineQuick === 'archived' ? 'archived' : null,
            $search
        );

        $tableRows = [];
        foreach ($rows as $row) {
            $documentId = (int) ($row['document_id'] ?? 0);
            $versionId = (int) ($row['version_id'] ?? 0);
            $doc = $this->documentRepository->findById($documentId, null);
            if ($doc === null || !$this->canReadDocumentOrDoctrine($doc, $userId, $tenantId, $row)) {
                continue;
            }
            $badge = $versionId > 0
                ? $this->documentComplianceService->memberBadge($tenantId, $userId, $row, $versionId)
                : ['badge' => 'NOT_APPLICABLE', 'label' => '—', 'tone' => 'neutral', 'sort_priority' => 9];

            if ($doctrineQuick === 'action' && !in_array($badge['badge'], ['ACK_REQUIRED', 'ACK_OUTDATED', 'OVERDUE', 'UNREAD'], true)) {
                continue;
            }
            if ($doctrineQuick === 'current' && !in_array($badge['badge'], ['ACKNOWLEDGED', 'READ'], true)) {
                continue;
            }
            if ($doctrineQuick === 'new' && $badge['badge'] !== 'UNREAD' && $badge['badge'] !== 'ACK_REQUIRED') {
                continue;
            }

            $domainLabel = trim((string) ($row['domain_prefix'] ?? '') . ' / ' . (string) ($row['subdomain_code'] ?? ''));
            $domainLabel = trim($domainLabel, ' /');
            $versionLabel = trim((string) ($row['version_label'] ?? ''));
            if ($versionLabel === '' && isset($row['version_major'], $row['version_minor'])) {
                $versionLabel = 'v' . (int) $row['version_major'] . '.' . (int) $row['version_minor'];
            }

            $tableRows[] = [
                'document_id' => $documentId,
                'reference' => (string) ($row['reference_code'] ?? ''),
                'title' => (string) ($row['title'] ?? ''),
                'domain' => $domainLabel !== '' ? $domainLabel : (string) ($row['domain_label'] ?? ''),
                'version' => $versionLabel !== '' ? $versionLabel : '—',
                'authority' => (string) ($row['issuing_label'] ?? '—'),
                'diffusion' => (string) ($row['diffusion_label'] ?? '—'),
                'published_at' => (string) ($row['published_at'] ?? $row['effective_at'] ?? ''),
                'updated_at' => (string) ($row['document_updated_at'] ?? ''),
                'reading_required' => !empty($row['reading_required']) || !empty($row['acknowledgment_required']),
                'requirement_level' => (string) ($row['requirement_level'] ?? 'informative'),
                'status_badge' => $badge,
                'scope' => (string) ($row['scope'] ?? 'tenant'),
                'href' => url('documents/doctrine/' . $documentId),
            ];
        }

        usort($tableRows, static function (array $a, array $b): int {
            $pa = (int) ($a['status_badge']['sort_priority'] ?? 9);
            $pb = (int) ($b['status_badge']['sort_priority'] ?? 9);
            if ($pa !== $pb) {
                return $pa <=> $pb;
            }

            return strcmp((string) ($a['reference'] ?? ''), (string) ($b['reference'] ?? ''));
        });

        return Response::view('layout.main', [
            'content' => 'documents/doctrine_index',
            'title' => 'Référentiel doctrinal',
            'doctrineRows' => $tableRows,
            'categories' => $categoriesList,
            'doctrineCategory' => $doctrineCategory,
            'search' => $search ?? '',
            'doctrineQuick' => $doctrineQuick,
            'canManage' => $canManage,
        ]);
    }

    /**
     * @param array<string, mixed>|null $document
     */
    private function missingDocumentFilePage(?array $document, int $documentId): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $doctrine = null;
        if ($document !== null && $this->documentDoctrineRepository->tableExists()) {
            $doctrine = $this->documentDoctrineRepository->findByDocumentId((int) ($document['id'] ?? $documentId), $tenantId);
        }
        $isDoctrine = is_array($doctrine);
        $backHref = $isDoctrine
            ? url('documents/doctrine/' . $documentId)
            : ($documentId > 0 ? url('documents/' . $documentId) : url('documents'));
        $response = Response::view('layout.main', [
            'title' => 'Document indisponible',
            'content' => 'documents/file_missing',
            'missingHeading' => $document === null ? 'Document introuvable' : 'Fichier non consultable',
            'missingLead' => $document === null
                ? 'Cette fiche n’existe pas, ou elle n’est plus accessible dans votre communauté.'
                : 'Le fichier n’est pas encore déposé, ou il n’est plus disponible. Vous pouvez revenir à la fiche ou ouvrir le référentiel.',
            'missingTitle' => (string) ($document['title'] ?? ''),
            'missingReference' => (string) ($doctrine['reference_code'] ?? ''),
            'documentBackHref' => $backHref,
            'referentialHref' => url('documents') . '?category_slug=doctrine',
        ]);
        $response->setStatusCode(404);

        return $response;
    }

    /**
     * @param array<string, mixed> $document
     * @param array<string, mixed>|null $doctrineRow
     */
    private function canReadDocumentOrDoctrine(array $document, int $userId, int $tenantId, ?array $doctrineRow = null): bool
    {
        if ($this->documentAccessService->canRead($document, $userId, $tenantId)) {
            return true;
        }
        if (!$this->documentDoctrineRepository->tableExists()) {
            return false;
        }
        $doctrine = $doctrineRow;
        if ($doctrine === null) {
            $doctrine = $this->documentDoctrineRepository->findByDocumentId((int) ($document['id'] ?? 0), $tenantId);
        }
        if ($doctrine === null) {
            return false;
        }

        return $this->doctrineDocumentAccessService->canMemberView($tenantId, $userId, $document, $doctrine);
    }
}
