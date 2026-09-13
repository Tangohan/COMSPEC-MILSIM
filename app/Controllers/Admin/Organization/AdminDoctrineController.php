<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\Doctrine\DocumentAcknowledgmentRepository;
use App\Repositories\Doctrine\DocumentAudienceRepository;
use App\Repositories\Doctrine\DocumentDoctrineRepository;
use App\Repositories\Doctrine\DocumentReferenceDomainRepository;
use App\Repositories\Doctrine\DocumentTypeRepository;
use App\Repositories\Doctrine\DocumentViewRepository;
use App\Repositories\DocumentRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\RoleRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Services\Doctrine\DocumentAudienceResolver;
use App\Services\Doctrine\DocumentComplianceService;
use App\Services\Doctrine\DoctrineNotificationService;
use App\Services\Doctrine\DoctrinePublicationService;
use App\Support\Doctrine\DoctrineWorkflowStatus;

final class AdminDoctrineController
{
    public function __construct(
        private DocumentReferenceDomainRepository $domainRepository,
        private DocumentDoctrineRepository $doctrineRepository,
        private DocumentAudienceResolver $audienceResolver,
        private DocumentComplianceService $complianceService,
        private DocumentAcknowledgmentRepository $acknowledgmentRepository,
        private DocumentViewRepository $viewRepository,
        private UserRepository $userRepository,
        private DocumentTypeRepository $typeRepository,
        private DoctrinePublicationService $publicationService,
        private DoctrineNotificationService $notificationService,
        private DocumentAudienceRepository $audienceRepository,
        private DocumentRepository $documentRepository,
        private UnitRepository $unitRepository,
        private PersonnelJobRoleRepository $jobRoleRepository,
        private RoleRepository $roleRepository,
    ) {}

    public function nomenclature(Request $request, array $params = []): Response
    {
        if ($this->denyManage()) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé.');
        }
        $tenantId = (int) Session::get('tenant_id');
        $domains = $this->domainRepository->listAllForTenant($tenantId);
        foreach ($domains as &$d) {
            $d['subdomains'] = $this->domainRepository->listSubdomainsForDomain($tenantId, (int) ($d['id'] ?? 0), false);
        }
        unset($d);

        return Response::view('layout.back_office', [
            'content' => 'admin/documents/doctrine_nomenclature',
            'title' => 'Nomenclature documentaire',
            'domains' => $domains,
            'csrf_token' => Csrf::token(),
        ]);
    }

    public function nomenclatureSave(Request $request, array $params = []): Response
    {
        if ($this->denyManage()) {
            return Response::redirect(url('back-office'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée.');
            return Response::redirect(url('back-office/documents/nomenclature'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $code = trim((string) $request->input('code', ''));
        $label = trim((string) $request->input('label', ''));
        $prefix = trim((string) $request->input('doc_prefix', ''));
        if ($code === '' || $label === '' || $prefix === '') {
            Session::flash('error', 'Code, libellé et abréviation requis.');
            return Response::redirect(url('back-office/documents/nomenclature'));
        }
        $this->domainRepository->create($tenantId, $code, $label, $prefix, trim((string) $request->input('color', '')) ?: null, 50);
        Session::flash('success', 'Entrée de nomenclature ajoutée.');

        return Response::redirect(url('back-office/documents/nomenclature'));
    }

    public function types(Request $request, array $params = []): Response
    {
        if ($this->denyManage()) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé.');
        }
        $tenantId = (int) Session::get('tenant_id');

        return Response::view('layout.back_office', [
            'content' => 'admin/documents/document_types',
            'title' => 'Types de documents',
            'types' => $this->typeRepository->listForTenant($tenantId, false),
            'csrf_token' => Csrf::token(),
        ]);
    }

    public function typesSave(Request $request, array $params = []): Response
    {
        if ($this->denyManage()) {
            return Response::redirect(url('back-office'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée.');
            return Response::redirect(url('back-office/documents/types'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) $request->input('id', 0);
        $label = trim((string) $request->input('label', ''));
        $prefix = strtoupper(trim((string) $request->input('code_prefix', '')));
        if ($label === '' || $prefix === '') {
            Session::flash('error', 'Le nom et l’abréviation de numérotation sont obligatoires.');
            return Response::redirect(url('back-office/documents/types'));
        }

        $payload = [
            'label' => $label,
            'description' => trim((string) $request->input('description', '')) ?: null,
            'color' => trim((string) $request->input('color', '')) ?: null,
            'code_prefix' => $prefix,
            'default_reading_required' => $request->input('default_reading_required') ? 1 : 0,
            'default_acknowledgment_required' => $request->input('default_acknowledgment_required') ? 1 : 0,
            'default_require_validation' => $request->input('default_require_validation') ? 1 : 0,
            'is_active' => $request->input('is_active') ? 1 : 0,
            'sort_order' => (int) $request->input('sort_order', 100),
        ];

        if ($id > 0) {
            $this->typeRepository->update($id, $tenantId, $payload);
            Session::flash('success', 'Type de document mis à jour.');
        } else {
            $code = strtolower(trim((string) $request->input('code', '')));
            if ($code === '') {
                $code = preg_replace('/[^a-z0-9_]+/', '_', strtolower($label)) ?: 'type';
            }
            $payload['code'] = $code;
            $this->typeRepository->create($tenantId, $payload);
            Session::flash('success', 'Type de document ajouté.');
        }

        return Response::redirect(url('back-office/documents/types'));
    }

    public function publishForm(Request $request, array $params = []): Response
    {
        if ($this->denyCreate()) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé.');
        }
        $tenantId = (int) Session::get('tenant_id');
        $documentId = (int) ($params['id'] ?? $request->input('id', 0));
        $document = null;
        $doctrine = null;
        $audiences = [];
        if ($documentId > 0) {
            $document = $this->documentRepository->findById($documentId, $tenantId);
            $doctrine = $this->doctrineRepository->findByDocumentId($documentId, $tenantId);
            $audiences = $this->audienceRepository->listForDocument($documentId, $tenantId);
        }

        return Response::view('layout.back_office', [
            'content' => 'admin/documents/publish_form',
            'title' => $documentId > 0 ? 'Modifier le document' : 'Publier un document',
            'document' => $document,
            'doctrine' => $doctrine,
            'audiences' => $audiences,
            'types' => $this->typeRepository->listForTenant($tenantId, true),
            'domains' => $this->domainRepository->listAllForTenant($tenantId),
            'units' => $this->unitRepository->allForTenant($tenantId),
            'jobRoles' => $this->jobRoleRepository->listRoleOptionsForSelect($tenantId),
            'roles' => $this->roleRepository->allForTenant($tenantId),
            'users' => $this->userRepository->allForTenant($tenantId),
            'publishedDocs' => $this->doctrineRepository->listPublishedForTenant($tenantId),
            'csrf_token' => Csrf::token(),
        ]);
    }

    public function publishSave(Request $request, array $params = []): Response
    {
        if ($this->denyCreate()) {
            return Response::redirect(url('back-office'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée.');
            return Response::redirect(url('back-office/documents/publier'));
        }

        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        $documentId = (int) $request->input('document_id', 0);
        $action = trim((string) $request->input('action', 'save'));

        $input = [
            'title' => $request->input('title'),
            'short_title' => $request->input('short_title'),
            'object' => $request->input('object'),
            'body_html' => $request->input('body_html'),
            'document_type_id' => $request->input('document_type_id'),
            'domain_id' => $request->input('domain_id'),
            'subdomain_id' => $request->input('subdomain_id'),
            'reference_code' => $request->input('reference_code'),
            'issuing_label' => $request->input('issuing_label'),
            'issuing_unit_id' => $request->input('issuing_unit_id'),
            'issuing_authority_type' => $request->input('issuing_authority_type'),
            'classification_level' => $request->input('classification_level'),
            'visibility_mode' => $request->input('visibility_mode'),
            'visibility_scope' => $request->input('visibility_mode') === 'library' ? 'tenant' : 'private',
            'effective_at' => $request->input('effective_at'),
            'expires_at' => $request->input('expires_at'),
            'acknowledgment_deadline_at' => $request->input('acknowledgment_deadline_at'),
            'confirmation_text' => $request->input('confirmation_text'),
            'reading_required' => $request->input('reading_required'),
            'acknowledgment_required' => $request->input('acknowledgment_required'),
            'is_permanent' => $request->input('is_permanent'),
            'require_validation' => $request->input('require_validation'),
            'reminder_on_publish' => $request->input('reminder_on_publish'),
            'include_future_members' => $request->input('include_future_members'),
            'replaces_document_id' => $request->input('replaces_document_id'),
        ];
        $audiences = $this->parseAudiences($request);

        if ($documentId > 0) {
            $result = $this->publicationService->updateDraft($tenantId, $userId, $documentId, $input, $audiences);
        } else {
            $result = $this->publicationService->createDraft($tenantId, $userId, $input, $audiences);
            $documentId = (int) ($result['document_id'] ?? 0);
        }

        if (empty($result['ok'])) {
            Session::flash('error', (string) ($result['error'] ?? 'Enregistrement impossible.'));
            return Response::redirect($documentId > 0
                ? url('back-office/documents/publier/' . $documentId)
                : url('back-office/documents/publier'));
        }

        if ($action === 'submit_validation') {
            $this->doctrineRepository->updateByDocumentId($documentId, $tenantId, [
                'doctrine_status' => DoctrineWorkflowStatus::REVIEW,
            ]);
            Session::flash('success', 'Document soumis à validation.');
        } elseif ($action === 'publish') {
            if (Gate::getInstance()->deny('doctrine.publish') && Gate::getInstance()->deny('documents.publish')) {
                Session::flash('error', 'Vous n’avez pas le droit de publier.');
                return Response::redirect(url('back-office/documents/publier/' . $documentId));
            }
            $pub = $this->publicationService->publish($tenantId, $userId, $documentId, true);
            if (empty($pub['ok'])) {
                Session::flash('error', (string) ($pub['error'] ?? 'Publication impossible.'));
            } else {
                Session::flash('success', 'Document publié et destinataires informés.');
            }
        } else {
            Session::flash('success', 'Brouillon enregistré.');
        }

        return Response::redirect(url('back-office/documents/publier/' . $documentId));
    }

    public function newVersionForm(Request $request, array $params = []): Response
    {
        if ($this->denyCreate()) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé.');
        }
        $tenantId = (int) Session::get('tenant_id');
        $documentId = (int) ($params['id'] ?? 0);
        $document = $this->documentRepository->findById($documentId, $tenantId);
        $doctrine = $this->doctrineRepository->findByDocumentId($documentId, $tenantId);
        if ($document === null || $doctrine === null) {
            return (new Response())->setStatusCode(404)->setBody('Document introuvable.');
        }

        return Response::view('layout.back_office', [
            'content' => 'admin/documents/new_version_form',
            'title' => 'Nouvelle version',
            'document' => $document,
            'doctrine' => $doctrine,
            'csrf_token' => Csrf::token(),
        ]);
    }

    public function newVersionSave(Request $request, array $params = []): Response
    {
        if ($this->denyCreate()) {
            return Response::redirect(url('back-office'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée.');
            return Response::redirect(url('back-office/documents/compliance'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        $documentId = (int) ($params['id'] ?? 0);
        $reset = (string) $request->input('ack_policy', 'reset') === 'reset';
        $result = $this->publicationService->publishNewVersion($tenantId, $userId, $documentId, [
            'body_html' => $request->input('body_html'),
            'change_summary' => $request->input('change_summary'),
            'change_notes' => $request->input('change_summary'),
            'confirmation_text' => $request->input('confirmation_text'),
            'object' => $request->input('object'),
            'bump_major' => $request->input('bump_major'),
        ], $reset);
        if (empty($result['ok'])) {
            Session::flash('error', (string) ($result['error'] ?? 'Version impossible.'));
        } else {
            Session::flash('success', $reset
                ? 'Nouvelle version publiée. Une nouvelle lecture est exigée.'
                : 'Nouvelle version publiée. Les accusés de lecture sont conservés.');
        }

        return Response::redirect(url('documents/doctrine/' . $documentId));
    }

    public function compliance(Request $request, array $params = []): Response
    {
        if (Gate::getInstance()->deny('doctrine.view_compliance') && $this->denyManage()) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé.');
        }
        $tenantId = (int) Session::get('tenant_id');
        $documentFilter = (int) $request->input('document_id', 0);
        $statusFilter = trim((string) $request->input('statut', ''));
        $unitFilter = (int) $request->input('unit_id', 0);
        $doctrines = $this->doctrineRepository->listPublishedForTenant($tenantId);
        $rows = [];
        $stats = [
            'concerned' => 0,
            'acknowledged' => 0,
            'pending' => 0,
            'overdue' => 0,
            'opened' => 0,
            'read' => 0,
        ];
        $commandStats = [
            'active_docs' => count($doctrines),
            'pending_validation' => 0,
            'approaching_deadline' => 0,
        ];

        foreach ($doctrines as $doc) {
            $documentId = (int) ($doc['document_id'] ?? 0);
            if ($documentFilter > 0 && $documentId !== $documentFilter) {
                continue;
            }
            $deadline = $doc['acknowledgment_deadline_at'] ?? null;
            if ($deadline) {
                $ts = strtotime((string) $deadline);
                if ($ts !== false && $ts > time() && $ts < time() + 7 * 86400) {
                    ++$commandStats['approaching_deadline'];
                }
            }
            $versionId = (int) ($doc['version_id'] ?? 0);
            $userIds = $this->audienceResolver->resolveUserIds($tenantId, $documentId);
            foreach ($userIds as $uid) {
                $user = $this->userRepository->findById($uid, $tenantId);
                if ($user === null) {
                    continue;
                }
                $badge = $versionId > 0
                    ? $this->complianceService->memberBadge($tenantId, $uid, $doc, $versionId)
                    : ['badge' => 'NOT_APPLICABLE', 'label' => '—', 'tone' => 'neutral'];
                if ($badge['badge'] === 'NOT_APPLICABLE') {
                    continue;
                }
                ++$stats['concerned'];
                if ($badge['badge'] === 'ACKNOWLEDGED') {
                    ++$stats['acknowledged'];
                    ++$stats['read'];
                } elseif ($badge['badge'] === 'READ') {
                    ++$stats['read'];
                } elseif ($badge['badge'] === 'OVERDUE') {
                    ++$stats['overdue'];
                    ++$stats['pending'];
                } elseif (in_array($badge['badge'], ['ACK_REQUIRED', 'ACK_OUTDATED', 'UNREAD'], true)) {
                    ++$stats['pending'];
                }
                $view = $versionId > 0 ? $this->viewRepository->findForUserVersion($tenantId, $uid, $versionId) : null;
                if ($view !== null) {
                    ++$stats['opened'];
                }
                $ack = $versionId > 0 ? $this->acknowledgmentRepository->findForUserVersion($tenantId, $uid, $versionId) : null;

                if ($statusFilter !== '' && $badge['badge'] !== $statusFilter) {
                    continue;
                }

                $rows[] = [
                    'user_id' => $uid,
                    'display_name' => (string) ($user['display_name'] ?? ''),
                    'unit_label' => (string) ($user['unit_name'] ?? $user['primary_unit_name'] ?? '—'),
                    'reference' => (string) ($doc['reference_code'] ?? ''),
                    'title' => (string) ($doc['title'] ?? ''),
                    'document_id' => $documentId,
                    'version_id' => $versionId,
                    'version_label' => trim((string) ($doc['version_label'] ?? ''))
                        ?: ('v' . (int) ($doc['version_major'] ?? 1) . '.' . (int) ($doc['version_minor'] ?? 0)),
                    'status' => $badge['label'],
                    'status_code' => $badge['badge'],
                    'first_viewed_at' => $view['first_viewed_at'] ?? null,
                    'viewed_at' => $view['last_viewed_at'] ?? null,
                    'signed_at' => $ack['signed_at'] ?? null,
                    'deadline' => $doc['acknowledgment_deadline_at'] ?? null,
                ];
            }
        }

        $compliancePct = $stats['concerned'] > 0
            ? round(100 * $stats['read'] / $stats['concerned'], 1)
            : 100.0;

        return Response::view('layout.back_office', [
            'content' => 'admin/documents/doctrine_compliance',
            'title' => 'Suivi de diffusion',
            'rows' => $rows,
            'stats' => $stats,
            'commandStats' => $commandStats,
            'compliancePct' => $compliancePct,
            'doctrines' => $doctrines,
            'documentFilter' => $documentFilter,
            'statusFilter' => $statusFilter,
            'unitFilter' => $unitFilter,
            'csrf_token' => Csrf::token(),
        ]);
    }

    public function sendReminders(Request $request, array $params = []): Response
    {
        if (Gate::getInstance()->deny('doctrine.send_reminders') && $this->denyManage()) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé.');
        }
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée.');
            return Response::redirect(url('back-office/documents/compliance'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        $documentId = (int) $request->input('document_id', 0);
        $note = trim((string) $request->input('note', ''));
        $result = $this->notificationService->sendReminders($tenantId, $userId, $documentId, $note);
        if (empty($result['ok'])) {
            Session::flash('error', (string) ($result['error'] ?? 'Relance impossible.'));
        } else {
            Session::flash('success', (int) ($result['count'] ?? 0) . ' rappel(s) envoyé(s).');
        }

        return Response::redirect(url('back-office/documents/compliance') . '?document_id=' . $documentId);
    }

    /** @return list<array{audience_type: string, audience_value: string, include_children: int}> */
    private function parseAudiences(Request $request): array
    {
        $mode = trim((string) $request->input('audience_mode', 'all_members'));
        if ($mode === 'all_members') {
            return [['audience_type' => 'all_members', 'audience_value' => '*', 'include_children' => 0]];
        }

        $rows = [];
        $unitIds = $request->input('audience_units', []);
        if (!is_array($unitIds)) {
            $unitIds = $unitIds !== '' && $unitIds !== null ? [$unitIds] : [];
        }
        $includeChildren = $request->input('include_children') ? 1 : 0;
        foreach ($unitIds as $uid) {
            $uid = (int) $uid;
            if ($uid > 0) {
                $rows[] = [
                    'audience_type' => 'unit',
                    'audience_value' => (string) $uid,
                    'include_children' => $includeChildren,
                ];
            }
        }

        $jobRoles = $request->input('audience_job_roles', []);
        if (!is_array($jobRoles)) {
            $jobRoles = $jobRoles !== '' && $jobRoles !== null ? [$jobRoles] : [];
        }
        foreach ($jobRoles as $jr) {
            $jr = trim((string) $jr);
            if ($jr !== '') {
                $rows[] = ['audience_type' => 'job_role', 'audience_value' => $jr, 'include_children' => 0];
            }
        }

        $roles = $request->input('audience_roles', []);
        if (!is_array($roles)) {
            $roles = $roles !== '' && $roles !== null ? [$roles] : [];
        }
        foreach ($roles as $role) {
            $role = trim((string) $role);
            if ($role !== '') {
                $rows[] = ['audience_type' => 'role', 'audience_value' => $role, 'include_children' => 0];
            }
        }

        $users = $request->input('audience_users', []);
        if (!is_array($users)) {
            $users = $users !== '' && $users !== null ? [$users] : [];
        }
        foreach ($users as $u) {
            $u = (int) $u;
            if ($u > 0) {
                $rows[] = ['audience_type' => 'user', 'audience_value' => (string) $u, 'include_children' => 0];
            }
        }

        $grades = $request->input('audience_grades', []);
        if (!is_array($grades)) {
            $grades = $grades !== '' && $grades !== null ? [$grades] : [];
        }
        foreach ($grades as $g) {
            $g = trim((string) $g);
            if ($g !== '') {
                $rows[] = ['audience_type' => 'grade', 'audience_value' => $g, 'include_children' => 0];
            }
        }

        return $rows !== []
            ? $rows
            : [['audience_type' => 'all_members', 'audience_value' => '*', 'include_children' => 0]];
    }

    private function denyManage(): bool
    {
        $gate = Gate::getInstance();

        return $gate->deny('doctrine.edit')
            && $gate->deny('doctrine.create')
            && $gate->deny('documents.upload')
            && $gate->deny('admin.access');
    }

    private function denyCreate(): bool
    {
        $gate = Gate::getInstance();

        return $gate->deny('doctrine.create')
            && $gate->deny('doctrine.edit')
            && $gate->deny('documents.upload')
            && $gate->deny('documents.publish')
            && $gate->deny('admin.access');
    }
}
