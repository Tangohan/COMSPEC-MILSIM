<?php

declare(strict_types=1);

namespace App\Controllers\Courrier;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\Courrier\CourrierDocumentRepository;
use App\Repositories\Courrier\DocumentPresetRepository;
use App\Repositories\Courrier\DocumentTemplateRepository;

class CourrierDashboardController
{
    public function __construct(
        private CourrierDocumentRepository $documentRepository,
        private DocumentTemplateRepository $templateRepository,
        private DocumentPresetRepository $presetRepository
    ) {
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $userId = (int) (Session::get('user_id') ?? 0);
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }

        $draftCount = $this->documentRepository->countByStatus($tenantId, 'draft');
        $pendingCount = $this->documentRepository->countByStatus($tenantId, 'pending_validation');
        $sentCount = $this->documentRepository->countByStatus($tenantId, 'sent');
        $rejectedCount = $this->documentRepository->countByStatus($tenantId, 'rejected');
        $todayCount = $this->documentRepository->countCreatedToday($tenantId);
        $archivedCount = $this->documentRepository->countByStatus($tenantId, 'archived');

        $templates = $this->templateRepository->listForTenant($tenantId, true);
        $presets = $this->presetRepository->listForTenant($tenantId);

        $recentDrafts = $this->documentRepository->listForTenant($tenantId, 'draft', null, $userId, null, 5, 0);
        $recentPending = $this->documentRepository->listForTenant($tenantId, 'pending_validation', null, null, null, 5, 0);

        return Response::view('layout.main', [
            'title' => 'Bureau Courrier',
            'content' => 'courrier/dashboard',
            'courrier' => [
                'draft_count' => $draftCount,
                'pending_count' => $pendingCount,
                'sent_count' => $sentCount,
                'rejected_count' => $rejectedCount,
                'today_count' => $todayCount,
                'archived_count' => $archivedCount,
                'templates' => $templates,
                'presets' => $presets,
                'recent_drafts' => $recentDrafts,
                'recent_pending' => $recentPending,
            ],
        ]);
    }

    public function history(Request $request, array $params = []): Response
    {
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $userId = (int) (Session::get('user_id') ?? 0);
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $documents = $this->documentRepository->listForTenant($tenantId, null, null, $userId, null, 50, 0);
        return Response::view('layout.main', [
            'title' => 'Historique — Bureau Courrier',
            'content' => 'courrier/history',
            'courrier' => ['documents' => $documents],
        ]);
    }

    public function archives(Request $request, array $params = []): Response
    {
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $documents = $this->documentRepository->listForTenant($tenantId, 'archived', null, null, null, 50, 0);
        return Response::view('layout.main', [
            'title' => 'Archives — Bureau Courrier',
            'content' => 'courrier/archives',
            'courrier' => ['documents' => $documents],
        ]);
    }
}
