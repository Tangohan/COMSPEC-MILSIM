<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\Courrier\CourrierDocumentNotificationRepository;
use App\Repositories\ForumNotificationRepository;
use App\Repositories\TenantMessageRepository;
use App\Services\Notifications\ActivityHubPresentationService;

final class ActivityHubController
{
    public function __construct(
        private ForumNotificationRepository $forumNotifications,
        private CourrierDocumentNotificationRepository $courrierNotifications,
        private TenantMessageRepository $tenantMessages,
        private ActivityHubPresentationService $presentation,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $userId = (int) (Session::get('user_id') ?? 0);
        if ($tenantId < 1 || $userId < 1) {
            return Response::redirect(url('login'));
        }

        $forumRows = $this->forumNotifications->listRecentForUser($tenantId, $userId, 40);
        $forumItems = [];
        foreach ($forumRows as $r) {
            $forumItems[] = $this->presentation->formatForumRow($r);
        }

        $courrierRows = $this->courrierNotifications->listRecentForUser($tenantId, $userId, 40);
        $courrierItems = [];
        foreach ($courrierRows as $r) {
            $courrierItems[] = $this->presentation->formatCourrierRow($r);
        }

        $messageRows = $this->tenantMessages->listActivityThreadsForUser($tenantId, $userId, 40);
        $messageItems = [];
        foreach ($messageRows as $r) {
            $messageItems[] = $this->presentation->formatTenantMessageThreadRow($r, $userId);
        }

        return Response::view('layout.main', [
            'title' => 'Mon activité',
            'content' => 'notifications.hub',
            'activity_forum_items' => $forumItems,
            'activity_courrier_items' => $courrierItems,
            'activity_message_items' => $messageItems,
        ]);
    }

    public function markForumRead(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('activite'));
        }
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $userId = (int) (Session::get('user_id') ?? 0);
        if ($tenantId < 1 || $userId < 1) {
            return Response::redirect(url('login'));
        }
        $this->forumNotifications->markAllRead($tenantId, $userId);
        Session::flash('success', 'Notifications du forum marquées comme lues.');

        return Response::redirect(url('activite'));
    }

    public function markCourrierRead(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('activite'));
        }
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $userId = (int) (Session::get('user_id') ?? 0);
        if ($tenantId < 1 || $userId < 1) {
            return Response::redirect(url('login'));
        }
        $this->courrierNotifications->markAllReadForUser($tenantId, $userId);
        Session::flash('success', 'Notifications du courrier marquées comme lues.');

        return Response::redirect(url('activite'));
    }

    public function markTenantMessagesRead(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('activite'));
        }
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $userId = (int) (Session::get('user_id') ?? 0);
        if ($tenantId < 1 || $userId < 1) {
            return Response::redirect(url('login'));
        }
        $this->tenantMessages->markAllThreadsReadForUser($tenantId, $userId);
        Session::flash('success', 'Conversations de la messagerie interne marquées comme lues.');

        return Response::redirect(url('activite'));
    }
}
