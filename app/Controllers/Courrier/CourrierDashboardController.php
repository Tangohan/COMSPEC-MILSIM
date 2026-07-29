<?php

declare(strict_types=1);

namespace App\Controllers\Courrier;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\Courrier\CourrierDocumentNotificationRepository;
use App\Repositories\Courrier\CourrierDocumentRepository;
use App\Repositories\Courrier\DocumentPresetRepository;
use App\Repositories\Courrier\DocumentTemplateRepository;
use App\Services\Platform\FeatureGateService;
use App\Support\PlanFeatureDenial;

class CourrierDashboardController
{
    public function __construct(
        private CourrierDocumentRepository $documentRepository,
        private DocumentTemplateRepository $templateRepository,
        private DocumentPresetRepository $presetRepository,
        private CourrierDocumentNotificationRepository $notificationRepository,
        private ?FeatureGateService $featureGate = null,
    ) {
        $this->featureGate ??= \App\Core\Container::get(FeatureGateService::class);
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $userId = (int) (Session::get('user_id') ?? 0);
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        if (!$this->featureGate->allows($tenantId, 'courrier')) {
            return PlanFeatureDenial::upgradeView('courrier', 'Standard');
        }

        $draftCount = $this->documentRepository->countByStatus($tenantId, 'draft');
        $pendingCount = $this->documentRepository->countByStatus($tenantId, 'pending_validation');
        $validatedCount = $this->documentRepository->countByStatus($tenantId, 'validated');
        $signedCount = $this->documentRepository->countByStatus($tenantId, 'signed');
        $sentCount = $this->documentRepository->countByStatus($tenantId, 'sent');
        $rejectedCount = $this->documentRepository->countByStatus($tenantId, 'rejected');
        $todayCount = $this->documentRepository->countCreatedToday($tenantId);
        $archivedCount = $this->documentRepository->countByStatus($tenantId, 'archived');

        $templates = $this->templateRepository->listForTenant($tenantId, true);
        $presets = $this->presetRepository->listForTenant($tenantId);

        $recentDrafts = $this->documentRepository->listForTenant($tenantId, 'draft', null, $userId, null, 5, 0);
        $recentPending = $this->documentRepository->listForTenant($tenantId, 'pending_validation', null, null, null, 5, 0);
        $recentSigned = $userId > 0
            ? $this->documentRepository->listForUserInvolvement($tenantId, $userId, 'signed', 5, 0)
            : [];
        $courrierNotifUnread = $this->notificationRepository->countUnread($tenantId, $userId);

        return Response::view('layout.main', [
            'title' => 'Bureau Courrier',
            'content' => 'courrier/dashboard',
            'courrier' => [
                'draft_count' => $draftCount,
                'pending_count' => $pendingCount,
                'validated_count' => $validatedCount,
                'signed_count' => $signedCount,
                'sent_count' => $sentCount,
                'rejected_count' => $rejectedCount,
                'today_count' => $todayCount,
                'archived_count' => $archivedCount,
                'templates' => $templates,
                'presets' => $presets,
                'recent_drafts' => $recentDrafts,
                'recent_pending' => $recentPending,
                'recent_signed' => $recentSigned,
                'courrier_notif_unread' => $courrierNotifUnread,
            ],
        ]);
    }

    public function history(Request $request, array $params = []): Response
    {
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $userId = (int) (Session::get('user_id') ?? 0);
        if (!$tenantId || !$userId) {
            return Response::redirect(url('login'));
        }
        $statusFilter = $request->query('status');
        $statusFilter = is_string($statusFilter) ? trim($statusFilter) : '';
        $statusFilter = $statusFilter !== '' ? $statusFilter : null;

        $documents = $this->documentRepository->listForUserInvolvement($tenantId, $userId, $statusFilter, 80, 0);
        return Response::view('layout.main', [
            'title' => 'Historique — Bureau Courrier',
            'content' => 'courrier/history',
            'courrier' => [
                'documents' => $documents,
                'status_filter' => $statusFilter,
            ],
        ]);
    }

    public function archives(Request $request, array $params = []): Response
    {
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $userId = (int) (Session::get('user_id') ?? 0);
        $documents = $userId > 0
            ? $this->documentRepository->listForUserInvolvement($tenantId, $userId, 'archived', 80, 0)
            : $this->documentRepository->listForTenant($tenantId, 'archived', null, null, null, 80, 0);
        return Response::view('layout.main', [
            'title' => 'Archives — Bureau Courrier',
            'content' => 'courrier/archives',
            'courrier' => ['documents' => $documents],
        ]);
    }

    public function traceability(Request $request, array $params = []): Response
    {
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }

        $recent = $this->documentRepository->listForTenant($tenantId, null, null, null, null, 80, 0);
        $pendingCount = $this->documentRepository->countByStatus($tenantId, 'pending_validation');
        $validatedCount = $this->documentRepository->countByStatus($tenantId, 'validated');
        $signedCount = $this->documentRepository->countByStatus($tenantId, 'signed');
        $sentCount = $this->documentRepository->countByStatus($tenantId, 'sent');
        $rejectedCount = $this->documentRepository->countByStatus($tenantId, 'rejected');

        return Response::view('layout.main', [
            'title' => 'Traçabilité décisionnelle — Bureau Courrier',
            'content' => 'courrier/traceability',
            'courrier' => [
                'recent_documents' => $recent,
                'pending_count' => $pendingCount,
                'validated_count' => $validatedCount,
                'signed_count' => $signedCount,
                'sent_count' => $sentCount,
                'rejected_count' => $rejectedCount,
            ],
        ]);
    }
}
