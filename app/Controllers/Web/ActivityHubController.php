<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\Courrier\CourrierDocumentNotificationRepository;
use App\Repositories\ForumNotificationRepository;
use App\Repositories\TenantMessageRepository;
use App\Services\Alerts\AlertPresentationService;
use App\Services\Notifications\ActivityHubPresentationService;

final class ActivityHubController
{
    private AlertPresentationService $alertPresentation;

    public function __construct(
        private ForumNotificationRepository $forumNotifications,
        private CourrierDocumentNotificationRepository $courrierNotifications,
        private TenantMessageRepository $tenantMessages,
        private ActivityHubPresentationService $presentation,
        ?AlertPresentationService $alertPresentation = null,
    ) {
        // Repli si un ancien câblage DI n’injecte que 4 services (500 sur /activite).
        $this->alertPresentation = $alertPresentation
            ?? Container::get(AlertPresentationService::class);
    }

    /**
     * L’ancienne page « Votre activité » est fusionnée dans la boîte de réception unifiée.
     * Les liens existants (/activite) y mènent directement.
     */
    public function index(Request $request, array $params = []): Response
    {
        return Response::redirect(url('boite-reception'));
    }

    public function markForumRead(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('boite-reception'));
        }
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $userId = (int) (Session::get('user_id') ?? 0);
        if ($tenantId < 1 || $userId < 1) {
            return Response::redirect(url('login'));
        }
        $this->forumNotifications->markAllRead($tenantId, $userId);
        Session::flash('success', 'Notifications du forum marquées comme lues.');

        return Response::redirect(url('boite-reception'));
    }

    public function markCourrierRead(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('boite-reception'));
        }
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $userId = (int) (Session::get('user_id') ?? 0);
        if ($tenantId < 1 || $userId < 1) {
            return Response::redirect(url('login'));
        }
        $this->courrierNotifications->markAllReadForUser($tenantId, $userId);
        Session::flash('success', 'Notifications du courrier marquées comme lues.');

        return Response::redirect(url('boite-reception'));
    }

    public function markTenantMessagesRead(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('boite-reception'));
        }
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $userId = (int) (Session::get('user_id') ?? 0);
        if ($tenantId < 1 || $userId < 1) {
            return Response::redirect(url('login'));
        }
        $this->tenantMessages->markAllThreadsReadForUser($tenantId, $userId);
        Session::flash('success', 'Conversations de la messagerie interne marquées comme lues.');

        return Response::redirect(url('boite-reception'));
    }
}
