<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PlatformReviewRepository;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use App\Support\PlatformReviewCatalog;

final class SystemPlatformReviewController
{
    public function __construct(
        private PlatformReviewRepository $reviews,
        private AuditService $audit,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        unset($params);
        $tab = $this->tab((string) $request->query('vue', ''));
        $status = trim((string) $request->query('statut', ''));
        if ($status !== '' && !in_array($status, [
            PlatformReviewCatalog::STATUS_PENDING,
            PlatformReviewCatalog::STATUS_ACCEPTED,
            PlatformReviewCatalog::STATUS_DECLINED,
        ], true)) {
            $status = '';
        }
        $ready = $this->reviews->isReady();
        $summary = $ready ? $this->reviews->scoreSummary() : ['count' => 0, 'average' => 0.0];
        $list = $ready ? $this->reviews->listRecent(80) : [];
        $suggestions = $ready ? $this->reviews->listSuggestions($status, 80) : [];
        $pending = $ready ? $this->reviews->countPending() : 0;

        return Response::view('layout.main', [
            'title' => 'Avis et traductions',
            'content' => 'admin.system.platform_review_index',
            'isPlatformAdminShell' => true,
            'backOfficePageCss' => ['platform-admin.css'],
            'prReady' => $ready,
            'prTab' => $tab,
            'prSummary' => $summary,
            'prReviews' => $list,
            'prSuggestions' => $suggestions,
            'prPending' => $pending,
            'prStatusFilter' => $status,
        ]);
    }

    public function acceptTranslation(Request $request, array $params = []): Response
    {
        return $this->moderate($request, $params, PlatformReviewCatalog::STATUS_ACCEPTED);
    }

    public function declineTranslation(Request $request, array $params = []): Response
    {
        return $this->moderate($request, $params, PlatformReviewCatalog::STATUS_DECLINED);
    }

    /** @param array<string, mixed> $params */
    private function moderate(Request $request, array $params, string $status): Response
    {
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'La session a expiré. Recommencez.');

            return Response::redirect(url('admin/system/avis-plateforme') . '?vue=traductions');
        }
        $id = (int) ($params['id'] ?? 0);
        $row = $this->reviews->findSuggestion($id);
        if (!$row) {
            Session::flash('error', 'Cette proposition est introuvable.');

            return Response::redirect(url('admin/system/avis-plateforme') . '?vue=traductions');
        }
        $ok = $this->reviews->setSuggestionStatus($id, $status, (int) Session::get('user_id'));
        if (!$ok) {
            Session::flash('error', 'La proposition n’a pas pu être mise à jour.');

            return Response::redirect(url('admin/system/avis-plateforme') . '?vue=traductions');
        }
        $tenantId = (int) ($row['tenant_id'] ?? 0);
        $this->audit->log(
            $status === PlatformReviewCatalog::STATUS_ACCEPTED
                ? AuditAction::PLATFORM_TRANSLATION_ACCEPTED
                : AuditAction::PLATFORM_TRANSLATION_DECLINED,
            $tenantId > 0 ? $tenantId : null,
            (int) Session::get('user_id') ?: null,
            'translation_suggestion',
            $id
        );
        Session::flash(
            'success',
            $status === PlatformReviewCatalog::STATUS_ACCEPTED
                ? 'Proposition marquée comme reprise. Pensez à l’intégrer dans les textes du site.'
                : 'Proposition marquée comme non retenue.'
        );

        return Response::redirect(url('admin/system/avis-plateforme') . '?vue=traductions');
    }

    private function tab(string $raw): string
    {
        return $raw === 'traductions' ? 'traductions' : 'avis';
    }
}
