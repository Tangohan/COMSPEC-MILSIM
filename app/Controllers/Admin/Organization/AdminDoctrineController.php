<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\Doctrine\DocumentAcknowledgmentRepository;
use App\Repositories\Doctrine\DocumentDoctrineRepository;
use App\Repositories\Doctrine\DocumentReferenceDomainRepository;
use App\Repositories\Doctrine\DocumentViewRepository;
use App\Services\Doctrine\DocumentAudienceResolver;
use App\Services\Doctrine\DocumentComplianceService;
use App\Repositories\UserRepository;

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

    public function compliance(Request $request, array $params = []): Response
    {
        if (Gate::getInstance()->deny('doctrine.view_compliance') && $this->denyManage()) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé.');
        }
        $tenantId = (int) Session::get('tenant_id');
        $documentFilter = (int) $request->input('document_id', 0);
        $doctrines = $this->doctrineRepository->listPublishedForTenant($tenantId);
        $rows = [];
        $stats = ['concerned' => 0, 'acknowledged' => 0, 'pending' => 0, 'overdue' => 0];

        foreach ($doctrines as $doc) {
            $documentId = (int) ($doc['document_id'] ?? 0);
            if ($documentFilter > 0 && $documentId !== $documentFilter) {
                continue;
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
                } elseif ($badge['badge'] === 'OVERDUE') {
                    ++$stats['overdue'];
                    ++$stats['pending'];
                } elseif (in_array($badge['badge'], ['ACK_REQUIRED', 'ACK_OUTDATED', 'UNREAD'], true)) {
                    ++$stats['pending'];
                }
                $view = $versionId > 0 ? $this->viewRepository->findForUserVersion($tenantId, $uid, $versionId) : null;
                $ack = $versionId > 0 ? $this->acknowledgmentRepository->findForUserVersion($tenantId, $uid, $versionId) : null;
                $rows[] = [
                    'user_id' => $uid,
                    'display_name' => (string) ($user['display_name'] ?? ''),
                    'reference' => (string) ($doc['reference_code'] ?? ''),
                    'title' => (string) ($doc['title'] ?? ''),
                    'version_id' => $versionId,
                    'status' => $badge['label'],
                    'viewed_at' => $view['last_viewed_at'] ?? null,
                    'signed_at' => $ack['signed_at'] ?? null,
                    'deadline' => $doc['acknowledgment_deadline_at'] ?? null,
                ];
            }
        }

        $compliancePct = $stats['concerned'] > 0
            ? round(100 * $stats['acknowledged'] / $stats['concerned'], 1)
            : 100.0;

        return Response::view('layout.back_office', [
            'content' => 'admin/documents/doctrine_compliance',
            'title' => 'Suivi des prises en compte',
            'rows' => $rows,
            'stats' => $stats,
            'compliancePct' => $compliancePct,
            'doctrines' => $doctrines,
            'documentFilter' => $documentFilter,
        ]);
    }

    private function denyManage(): bool
    {
        $gate = Gate::getInstance();

        return $gate->deny('doctrine.edit')
            && $gate->deny('doctrine.create')
            && $gate->deny('documents.upload')
            && $gate->deny('admin.access');
    }
}
