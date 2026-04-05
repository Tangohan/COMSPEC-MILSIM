<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

/**
 * Contrôleur « admin » documents = back-office / gestion documentaire.
 * Accès : permission documents.view | documents.upload | documents.update | documents.archive (ou admin.access).
 * Le préfixe URL /admin/ désigne la zone de gestion, pas le rôle administrateur.
 */
use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\DocumentAuditRepository;
use App\Repositories\DocumentCategoryRepository;
use App\Repositories\DocumentCollaboratorRepository;
use App\Repositories\DocumentLinkRepository;
use App\Repositories\DocumentPermissionRepository;
use App\Repositories\DocumentRelationRepository;
use App\Repositories\DocumentRepository;
use App\Repositories\EquipmentClassRepository;
use App\Repositories\TrainingRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Services\Audit\AuditService;
use App\Services\Documents\DocumentAccessService;
use App\Services\Documents\DocumentUploadService;
use App\Services\Moderation\ModerationBlockedException;
use App\Services\Moderation\ModerationQuarantineException;

class AdminDocumentsController
{
    public function __construct(
        private DocumentRepository $documentRepository,
        private DocumentCategoryRepository $categoryRepository,
        private DocumentLinkRepository $linkRepository,
        private DocumentCollaboratorRepository $collaboratorRepository,
        private DocumentPermissionRepository $permissionRepository,
        private DocumentRelationRepository $relationRepository,
        private DocumentAuditRepository $documentAuditRepository,
        private DocumentAccessService $documentAccessService,
        private EquipmentClassRepository $equipmentRepository,
        private TrainingRepository $trainingRepository,
        private UnitRepository $unitRepository,
        private UserRepository $userRepository,
        private DocumentUploadService $uploadService,
        private AuditService $auditService
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();
        if ($gate->deny('admin.access') && $gate->deny('documents.upload')) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé.');
        }
        $tenantId = (int) $tenantId;
        $categoryId = $request->input('category') ? (int) $request->input('category') : null;
        $status = $request->input('status') ? trim((string) $request->input('status')) : null;
        $search = $request->input('q') ? trim((string) $request->input('q')) : null;
        $documentType = $request->input('document_type') ? trim((string) $request->input('document_type')) : null;
        $classificationLevel = $request->input('classification_level') ? trim((string) $request->input('classification_level')) : null;
        $documents = $this->documentRepository->listForTenant($tenantId, $categoryId, $status, $search, null, null, $documentType, $classificationLevel);
        $userIds = array_unique(array_filter(array_merge(
            array_column($documents, 'created_by'),
            array_column($documents, 'owner_user_id')
        )));
        $users = [];
        foreach ($userIds as $uid) {
            if (!$uid) continue;
            $u = $this->userRepository->findById((int) $uid);
            if ($u) {
                $users[$uid] = $u['display_name'] ?? $u['email'] ?? '#' . $uid;
            }
        }
        return Response::view('layout.main', [
            'content' => 'admin.documents.index',
            'title' => 'Documents',
            'documents' => $documents,
            'users' => $users,
            'categories' => $this->categoryRepository->listForTenant($tenantId),
            'filters' => [
                'category' => $categoryId,
                'status' => $status ?? '',
                'q' => $search ?? '',
                'document_type' => $documentType ?? '',
                'classification_level' => $classificationLevel ?? '',
            ],
        ]);
    }

    public function uploadForm(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();
        if ($gate->deny('admin.access') && $gate->deny('documents.upload')) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé.');
        }
        $tenantId = (int) $tenantId;
        $userId = (int) Session::get('user_id');
        $allDocuments = $this->documentRepository->listForTenant($tenantId, null, null);
        return Response::view('layout.main', [
            'content' => 'admin.documents.upload',
            'title' => 'Upload document',
            'categories' => $this->categoryRepository->listForTenant($tenantId),
            'trainings' => $this->trainingRepository->listPublishedForTenant($tenantId),
            'equipmentClasses' => $this->equipmentRepository->listForTenant($tenantId),
            'units' => $this->unitRepository->allForTenant($tenantId),
            'users' => $this->userRepository->allForTenant($tenantId),
            'allDocuments' => $allDocuments,
            'currentUserId' => $userId,
        ]);
    }

    public function upload(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('user_id');
        if (!$tenantId || !$userId) {
            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();
        if ($gate->deny('admin.access') && $gate->deny('documents.upload')) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé.');
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::set('error', 'Session expirée.');
            return Response::redirect(url('documents/gestion/ajout'));
        }
        $tenantId = (int) $tenantId;
        $userId = (int) $userId;
        $title = trim((string) $request->input('title'));
        if ($title === '') {
            Session::set('error', 'Le titre est requis.');
            return Response::redirect(url('documents/gestion/ajout'));
        }
        $slug = trim((string) $request->input('slug'));
        $effectiveSlug = $slug !== '' ? $slug : $this->documentRepository->slugify($title);
        if ($this->documentRepository->slugExists($tenantId, $effectiveSlug)) {
            Session::set('error', 'Ce slug existe déjà.');
            return Response::redirect(url('documents/gestion/ajout'));
        }
        $file = $_FILES['file'] ?? null;
        $documentWithoutFile = (bool) $request->input('document_without_file');
        if (!$documentWithoutFile && (!$file || ($file['error'] ?? 0) !== UPLOAD_ERR_OK)) {
            Session::set('error', 'Veuillez sélectionner un fichier ou cocher « Document sans fichier ».');
            return Response::redirect(url('documents/gestion/ajout'));
        }
        if ($file && ($file['error'] ?? 0) === UPLOAD_ERR_OK) {
            try {
                $this->uploadService->validateFile($file);
            } catch (\InvalidArgumentException $e) {
                Session::set('error', $e->getMessage());
                return Response::redirect(url('documents/gestion/ajout'));
            }
        }
        $documentData = $this->documentDataFromRequest($request, $tenantId, $userId);
        $documentData['tenant_id'] = $tenantId;
        $documentData['title'] = $title;
        $documentData['slug'] = $effectiveSlug;
        $documentData['description'] = trim((string) $request->input('description')) ?: null;
        $documentData['document_category_id'] = $request->input('category') ? (int) $request->input('category') : null;
        $documentData['status'] = $request->input('status') ?: 'draft';
        $documentData['created_by'] = $userId;
        $documentData['owner_user_id'] = $documentData['owner_user_id'] ?? $userId;
        $documentData['author_user_id'] = $documentData['author_user_id'] ?? $userId;

        $documentId = $this->documentRepository->create($documentData);

        $versionId = 0;
        if ($file && ($file['error'] ?? 0) === UPLOAD_ERR_OK) {
            try {
                $result = $this->uploadService->uploadNewVersion($tenantId, $documentId, $file, null, $userId);
                $versionId = $result['version_id'] ?? 0;
            } catch (ModerationBlockedException $e) {
                $this->documentRepository->deleteHard($documentId, $tenantId);
                Session::set('error', $e->getMessage());

                return Response::redirect(url('documents/gestion/ajout'));
            } catch (ModerationQuarantineException $e) {
                $this->documentRepository->deleteHard($documentId, $tenantId);
                Session::set('error', $e->getMessage());

                return Response::redirect(url('documents/gestion/ajout'));
            } catch (\Throwable $e) {
                Session::set('error', 'Erreur lors de l\'upload : ' . $e->getMessage());
                return Response::redirect(url('documents/gestion/ajout'));
            }
        }

        $this->saveLinksFromRequest($documentId, $tenantId, $request);
        $this->saveCollaboratorsFromRequest($documentId, $request, $userId);
        $this->savePermissionsFromRequest($documentId, $request);
        $parentId = $request->input('parent_document_id') ? (int) $request->input('parent_document_id') : null;
        $relationType = trim((string) $request->input('relation_type')) ?: 'document_lie';
        if ($parentId && $parentId !== $documentId) {
            $this->relationRepository->link($parentId, $documentId, $relationType, (int) ($request->input('sort_order') ?? 0));
        }

        $this->documentAuditRepository->log($documentId, $userId, 'document_created', null, ['title' => $title, 'version_id' => $versionId]);
        $this->auditService->logDocumentUploaded($tenantId, $userId, $documentId, $versionId);
        Session::set('success', 'Document créé.');
        return Response::redirect(url('documents/gestion'));
    }

    public function show(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('user_id');
        if (!$tenantId || !$userId) {
            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();
        if ($gate->deny('admin.access') && $gate->deny('documents.upload')) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé.');
        }
        $id = (int) ($params['id'] ?? 0);
        $doc = $this->documentRepository->findById($id, (int) $tenantId);
        if (!$doc) {
            return (new Response())->setStatusCode(404)->setBody('Document non trouvé.');
        }
        if (!$this->documentAccessService->canRead($doc, (int) $userId, (int) $tenantId)) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé à ce document.');
        }
        $versions = $this->documentRepository->getVersions($id);
        $collaborators = $this->collaboratorRepository->getByDocument($id);
        $children = $this->documentRepository->listChildren($id, (int) $tenantId);
        $auditEntries = $this->documentAuditRepository->getByDocument($id, 50);
        $userIds = array_unique(array_filter(array_merge(
            [$doc['owner_user_id'] ?? null, $doc['author_user_id'] ?? null],
            array_column($collaborators, 'user_id')
        )));
        $usersMap = [];
        foreach ($userIds as $uid) {
            $u = $this->userRepository->findById((int) $uid);
            if ($u) {
                $usersMap[$uid] = $u['display_name'] ?? $u['email'] ?? '#' . $uid;
            }
        }
        return Response::view('layout.main', [
            'content' => 'admin.documents.show',
            'title' => $doc['title'],
            'document' => $doc,
            'versions' => $versions,
            'collaborators' => $collaborators,
            'children' => $children,
            'auditEntries' => $auditEntries,
            'usersMap' => $usersMap,
        ]);
    }

    public function edit(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('user_id');
        if (!$tenantId || !$userId) {
            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();
        if ($gate->deny('admin.access') && $gate->deny('documents.upload')) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé.');
        }
        $id = (int) ($params['id'] ?? 0);
        $doc = $this->documentRepository->findById($id, (int) $tenantId);
        if (!$doc) {
            return (new Response())->setStatusCode(404)->setBody('Document non trouvé.');
        }
        if (!$this->documentAccessService->canEdit($doc, (int) $userId, (int) $tenantId)) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé à ce document.');
        }
        $versions = $this->documentRepository->getVersions($id);
        $links = $this->linkRepository->getLinksForDocument($id);
        $collaborators = $this->collaboratorRepository->getByDocument($id);
        $permissions = $this->permissionRepository->getByDocument($id);
        $children = $this->documentRepository->listChildren($id, (int) $tenantId);
        $allDocuments = $this->documentRepository->listForTenant((int) $tenantId, null, null);
        $tenantId = (int) $tenantId;
        return Response::view('layout.main', [
            'content' => 'admin.documents.edit',
            'title' => 'Modifier le document',
            'document' => $doc,
            'versions' => $versions,
            'links' => $links,
            'collaborators' => $collaborators,
            'permissions' => $permissions,
            'children' => $children,
            'allDocuments' => $allDocuments,
            'categories' => $this->categoryRepository->listForTenant($tenantId),
            'trainings' => $this->trainingRepository->listPublishedForTenant($tenantId),
            'equipmentClasses' => $this->equipmentRepository->listForTenant($tenantId),
            'units' => $this->unitRepository->allForTenant($tenantId),
            'users' => $this->userRepository->allForTenant($tenantId),
        ]);
    }

    public function update(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('user_id');
        if (!$tenantId || !$userId) {
            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();
        if ($gate->deny('admin.access') && $gate->deny('documents.update')) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé.');
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::set('error', 'Session expirée.');
            return Response::redirect(url('documents/gestion'));
        }
        $id = (int) ($params['id'] ?? 0);
        $doc = $this->documentRepository->findById($id, (int) $tenantId);
        if (!$doc) {
            return (new Response())->setStatusCode(404)->setBody('Document non trouvé.');
        }
        if (!$this->documentAccessService->canEdit($doc, (int) $userId, (int) $tenantId)) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé à ce document.');
        }
        $slug = trim((string) $request->input('slug'));
        $effectiveSlug = $slug !== '' ? $slug : $this->documentRepository->slugify(trim((string) $request->input('title')));
        if ($this->documentRepository->slugExists((int) $tenantId, $effectiveSlug, $id)) {
            Session::set('error', 'Ce slug existe déjà.');
            return Response::redirect(url('documents/gestion/' . $id . '/modifier'));
        }

        $updateData = $this->documentDataFromRequest($request, (int) $tenantId, (int) $userId);
        $updateData['title'] = trim((string) $request->input('title'));
        $updateData['slug'] = $effectiveSlug;
        $updateData['description'] = trim((string) $request->input('description')) ?: null;
        $updateData['document_category_id'] = $request->input('category') ? (int) $request->input('category') : null;
        $updateData['status'] = $request->input('status') ?: 'draft';

        $oldValues = [];
        foreach (array_keys($updateData) as $key) {
            if (array_key_exists($key, $doc) && $doc[$key] != ($updateData[$key] ?? null)) {
                $oldValues[$key] = $doc[$key];
            }
        }
        $this->documentRepository->update($id, (int) $tenantId, $updateData);
        $this->saveLinksFromRequest($id, (int) $tenantId, $request);
        $this->saveCollaboratorsFromRequest($id, $request, (int) $userId);
        $this->savePermissionsFromRequest($id, $request);

        $parentId = $request->input('parent_document_id') ? (int) $request->input('parent_document_id') : null;
        $relationType = trim((string) $request->input('relation_type')) ?: 'document_lie';
        $existingParent = $this->relationRepository->getParent($id);
        if ($parentId && $parentId !== $id) {
            $this->relationRepository->link($parentId, $id, $relationType, (int) ($request->input('sort_order') ?? 0));
        } elseif ($existingParent) {
            $this->relationRepository->unlink((int) $existingParent['parent_document_id'], $id);
        }

        if ($oldValues !== []) {
            $this->documentAuditRepository->log($id, (int) $userId, 'document_updated', $oldValues, $updateData);
        }
        $this->auditService->logDocumentUpdated((int) $tenantId, (int) $userId, $id);
        Session::set('success', 'Document mis à jour.');
        return Response::redirect(url('documents/gestion/' . $id . '/modifier'));
    }

    public function newVersion(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('user_id');
        if (!$tenantId || !$userId) {
            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();
        if ($gate->deny('admin.access') && $gate->deny('documents.upload')) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé.');
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::set('error', 'Session expirée.');
            return Response::redirect(url('documents/gestion'));
        }
        $id = (int) ($params['id'] ?? 0);
        $doc = $this->documentRepository->findById($id, (int) $tenantId);
        if (!$doc) {
            return (new Response())->setStatusCode(404)->setBody('Document non trouvé.');
        }
        $file = $_FILES['file'] ?? null;
        if (!$file || ($file['error'] ?? 0) !== UPLOAD_ERR_OK) {
            Session::set('error', 'Veuillez sélectionner un fichier.');
            return Response::redirect(url('documents/gestion/' . $id . '/modifier'));
        }
        try {
            $result = $this->uploadService->uploadNewVersion(
                (int) $tenantId,
                $id,
                $file,
                trim((string) $request->input('change_notes')) ?: null,
                (int) $userId
            );
            $this->documentAuditRepository->log($id, (int) $userId, 'version_created', null, ['version_id' => $result['version_id'] ?? 0]);
            $this->auditService->logDocumentUploaded((int) $tenantId, (int) $userId, $id, $result['version_id']);
        } catch (ModerationBlockedException $e) {
            Session::set('error', $e->getMessage());

            return Response::redirect(url('documents/gestion/' . $id . '/modifier'));
        } catch (ModerationQuarantineException $e) {
            Session::set('error', $e->getMessage() . ' (réf. artefact #' . $e->artifactId . ').');

            return Response::redirect(url('documents/gestion/' . $id . '/modifier'));
        } catch (\Throwable $e) {
            Session::set('error', 'Erreur : ' . $e->getMessage());
            return Response::redirect(url('documents/gestion/' . $id . '/modifier'));
        }
        Session::set('success', 'Nouvelle version enregistrée.');
        return Response::redirect(url('documents/gestion/' . $id . '/modifier'));
    }

    public function history(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('user_id');
        if (!$tenantId || !$userId) {
            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();
        if ($gate->deny('admin.access') && $gate->deny('documents.view')) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé.');
        }
        $id = (int) ($params['id'] ?? 0);
        $doc = $this->documentRepository->findById($id, (int) $tenantId);
        if (!$doc) {
            return (new Response())->setStatusCode(404)->setBody('Document non trouvé.');
        }
        if (!$this->documentAccessService->canRead($doc, (int) $userId, (int) $tenantId)) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé.');
        }
        $auditEntries = $this->documentAuditRepository->getByDocument($id, 200);
        $userIds = array_unique(array_filter(array_column($auditEntries, 'user_id')));
        $usersMap = [];
        foreach ($userIds as $uid) {
            $u = $this->userRepository->findById((int) $uid);
            if ($u) {
                $usersMap[$uid] = $u['display_name'] ?? $u['email'] ?? '#' . $uid;
            }
        }
        return Response::view('layout.main', [
            'content' => 'admin.documents.history',
            'title' => 'Historique — ' . $doc['title'],
            'document' => $doc,
            'auditEntries' => $auditEntries,
            'usersMap' => $usersMap,
        ]);
    }

    public function access(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('user_id');
        if (!$tenantId || !$userId) {
            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();
        if ($gate->deny('admin.access') && $gate->deny('documents.update')) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé.');
        }
        $id = (int) ($params['id'] ?? 0);
        $doc = $this->documentRepository->findById($id, (int) $tenantId);
        if (!$doc) {
            return (new Response())->setStatusCode(404)->setBody('Document non trouvé.');
        }
        if (!$this->documentAccessService->canManage($doc, (int) $userId, (int) $tenantId)) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé. Droits de gestion requis.');
        }
        $collaborators = $this->collaboratorRepository->getByDocument($id);
        $permissions = $this->permissionRepository->getByDocument($id);
        $users = $this->userRepository->allForTenant((int) $tenantId);
        $roles = [];
        try {
            $stmt = \App\Core\Database::getPdo()->query('SELECT id, name, slug FROM roles WHERE tenant_id = ' . (int) $tenantId . ' ORDER BY name');
            if ($stmt) {
                $roles = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            }
        } catch (\Throwable $e) {
        }
        $units = $this->unitRepository->allForTenant((int) $tenantId);
        return Response::view('layout.main', [
            'content' => 'admin.documents.access',
            'title' => 'Gestion des accès — ' . $doc['title'],
            'document' => $doc,
            'collaborators' => $collaborators,
            'permissions' => $permissions,
            'users' => $users,
            'roles' => $roles,
            'units' => $units,
        ]);
    }

    public function tree(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();
        if ($gate->deny('admin.access') && $gate->deny('documents.view')) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé.');
        }
        $tenantId = (int) $tenantId;
        $allDocs = $this->documentRepository->listForTenant($tenantId, null, null);
        $childrenByParent = [];
        $childIds = [];
        foreach ($allDocs as $d) {
            $id = (int) $d['id'];
            $pid = (int) ($d['parent_document_id'] ?? 0);
            if ($pid === 0) {
                $rel = $this->relationRepository->getParent($id);
                $pid = $rel ? (int) $rel['parent_document_id'] : 0;
            }
            if ($pid > 0) {
                $childIds[$id] = true;
                $childrenByParent[$pid][] = $d;
            }
        }
        $roots = array_filter($allDocs, function ($d) use ($childIds) {
            return !isset($childIds[(int) $d['id']]);
        });
        return Response::view('layout.main', [
            'content' => 'admin.documents.tree',
            'title' => 'Arborescence documentaire',
            'roots' => $roots,
            'childrenByParent' => $childrenByParent,
            'allDocuments' => $allDocs,
        ]);
    }

    public function archive(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('user_id');
        if (!$tenantId || !$userId) {
            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();
        if ($gate->deny('admin.access') && $gate->deny('documents.archive')) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé.');
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::set('error', 'Session expirée.');
            return Response::redirect(url('documents/gestion'));
        }
        $id = (int) ($params['id'] ?? 0);
        $doc = $this->documentRepository->findById($id, (int) $tenantId);
        if (!$doc) {
            return (new Response())->setStatusCode(404)->setBody('Document non trouvé.');
        }
        $this->documentRepository->update($id, (int) $tenantId, ['status' => 'archived']);
        $this->documentAuditRepository->log($id, (int) $userId, 'document_archived', ['status' => $doc['status'] ?? null], ['status' => 'archived']);
        $this->auditService->logDocumentArchived((int) $tenantId, (int) $userId, $id);
        Session::set('success', 'Document archivé.');
        return Response::redirect(url('documents/gestion'));
    }

    private function saveLinksFromRequest(int $documentId, int $tenantId, Request $request): void
    {
        $links = [];
        if ($request->input('link_training')) {
            $links[] = ['entity_type' => 'training', 'entity_id' => (int) $request->input('link_training')];
        }
        if ($request->input('link_equipment_class')) {
            $links[] = ['entity_type' => 'equipment_class', 'entity_id' => (int) $request->input('link_equipment_class')];
        }
        if ($request->input('link_unit')) {
            $links[] = ['entity_type' => 'unit', 'entity_id' => (int) $request->input('link_unit')];
        }
        if ($request->input('link_user')) {
            $links[] = ['entity_type' => 'user', 'entity_id' => (int) $request->input('link_user')];
        }
        $this->linkRepository->setLinksForDocument($documentId, $tenantId, $links);
    }

    /** @return array<string, mixed> Données document étendues depuis la requête (champs optionnels). */
    private function documentDataFromRequest(Request $request, int $tenantId, int $userId): array
    {
        $data = [
            'short_description' => trim((string) $request->input('short_description')) ?: null,
            'document_type' => trim((string) $request->input('document_type')) ?: null,
            'classification_level' => trim((string) $request->input('classification_level')) ?: 'interne',
            'visibility_scope' => trim((string) $request->input('visibility_scope')) ?: 'private',
            'owner_user_id' => $request->input('owner_user_id') !== null && $request->input('owner_user_id') !== '' ? (int) $request->input('owner_user_id') : $userId,
            'author_user_id' => $request->input('author_user_id') !== null && $request->input('author_user_id') !== '' ? (int) $request->input('author_user_id') : $userId,
            'parent_document_id' => $request->input('parent_document_id') !== null && $request->input('parent_document_id') !== '' ? (int) $request->input('parent_document_id') : null,
            'relation_type' => trim((string) $request->input('relation_type')) ?: null,
            'version_label' => trim((string) $request->input('version_label')) ?: null,
            'sort_order' => $request->input('sort_order') !== null && $request->input('sort_order') !== '' ? (int) $request->input('sort_order') : 0,
            'formation_id' => $request->input('link_training') ? (int) $request->input('link_training') : null,
            'equipment_class_id' => $request->input('link_equipment_class') ? (int) $request->input('link_equipment_class') : null,
            'unit_id' => $request->input('link_unit') ? (int) $request->input('link_unit') : null,
            'operator_id' => $request->input('link_user') ? (int) $request->input('link_user') : null,
            'mission_id' => trim((string) $request->input('mission_id')) ?: null,
            'effective_at' => trim((string) $request->input('effective_at')) ?: null,
            'review_due_at' => trim((string) $request->input('review_due_at')) ?: null,
            'expires_at' => trim((string) $request->input('expires_at')) ?: null,
            'download_allowed' => $request->input('download_allowed') !== '0',
            'print_allowed' => $request->input('print_allowed') !== '0',
            'locked' => (bool) $request->input('locked'),
            'inherit_parent_security' => (bool) $request->input('inherit_parent_security'),
        ];
        $tags = $request->input('tags');
        $tagsText = trim((string) $request->input('tags_text'));
        if (is_array($tags)) {
            $data['tags'] = json_encode(array_values(array_filter($tags)));
        } elseif ($tagsText !== '') {
            $data['tags'] = json_encode(array_values(array_filter(array_map('trim', explode(',', $tagsText)))));
        }
        return $data;
    }

    private function saveCollaboratorsFromRequest(int $documentId, Request $request, int $grantedBy): void
    {
        $collaborators = [];
        $raw = $request->input('collaborators');
        if (is_array($raw)) {
            foreach ($raw as $item) {
                $uid = isset($item['user_id']) ? (int) $item['user_id'] : 0;
                $role = $item['role'] ?? 'reader';
                if ($uid > 0) {
                    $collaborators[] = ['user_id' => $uid, 'role' => $role];
                }
            }
        }
        $ownerId = $request->input('owner_user_id') ? (int) $request->input('owner_user_id') : null;
        if ($ownerId) {
            $collaborators[] = ['user_id' => $ownerId, 'role' => 'owner'];
        }
        $this->collaboratorRepository->setForDocument($documentId, $collaborators, $grantedBy);
    }

    private function savePermissionsFromRequest(int $documentId, Request $request): void
    {
        $permissions = [];
        $raw = $request->input('permissions');
        if (is_array($raw)) {
            foreach ($raw as $item) {
                $type = $item['permission_type'] ?? '';
                $value = (string) ($item['permission_value'] ?? '');
                $level = $item['access_level'] ?? 'read';
                if ($type !== '' && $value !== '') {
                    $permissions[] = ['permission_type' => $type, 'permission_value' => $value, 'access_level' => $level];
                }
            }
        }
        $this->permissionRepository->setForDocument($documentId, $permissions);
    }
}
