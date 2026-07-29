<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantMessageRepository;
use App\Repositories\UserRepository;
use App\Services\Auth\AuthService;
use App\Services\Community\TenantInternalMessageNotificationService;
use App\Services\Rbac\RbacService;

final class TenantMessagesController
{
    public function __construct(
        private AuthService $authService,
        private RbacService $rbacService,
        private UserRepository $userRepository,
        private TenantMessageRepository $messageRepository,
        private TenantInternalMessageNotificationService $internalMessageNotifications,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if (!$tenantId || !$userId) {
            return Response::redirect(url('dashboard'));
        }
        $featureGate = \App\Core\Container::get(\App\Services\Platform\FeatureGateService::class);
        if (!$featureGate->allows($tenantId, 'messages')) {
            return \App\Support\PlanFeatureDenial::upgradeView('messages');
        }
        $user = $this->authService->user();
        if ($user) {
            $this->rbacService->setPermissionsForGateFromUserRow($user, $this->userRepository);
        }
        $threads = $this->messageRepository->listThreadsForUser($tenantId, $userId);
        $staffIds = $this->messageRepository->findStaffUserIdsForTenant($tenantId);

        return Response::view('layout.main', [
            'title' => 'Messagerie',
            'content' => 'messages.index',
            'msgThreads' => $threads,
            'msgRecipientsConfigured' => $staffIds !== [],
        ]);
    }

    public function show(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        $threadId = (int) ($params['id'] ?? 0);
        if (!$tenantId || !$userId || $threadId <= 0) {
            return Response::redirect(url('messages'));
        }
        $user = $this->authService->user();
        if ($user) {
            $this->rbacService->setPermissionsForGateFromUserRow($user, $this->userRepository);
        }
        $thread = $this->messageRepository->findThread($threadId, $tenantId);
        if (!$thread || !$this->messageRepository->userInThread($threadId, $userId)) {
            Session::flash('error', 'Fil introuvable.');

            return Response::redirect(url('messages'));
        }
        $this->messageRepository->markThreadRead($threadId, $userId);
        $messages = $this->messageRepository->listMessages($threadId);

        return Response::view('layout.main', [
            'title' => (string) ($thread['subject'] ?? 'Conversation'),
            'content' => 'messages.thread',
            'msgThread' => $thread,
            'msgMessages' => $messages,
            'msgCurrentUserId' => $userId,
        ]);
    }

    public function create(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('messages'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if (!$tenantId || !$userId) {
            return Response::redirect(url('dashboard'));
        }
        $subject = trim((string) $request->input('subject', ''));
        $body = trim((string) $request->input('body', ''));
        if ($body === '') {
            Session::flash('error', 'Message vide.');

            return Response::redirect(url('messages'));
        }
        $staffIds = $this->messageRepository->findStaffUserIdsForTenant($tenantId);
        if ($staffIds === []) {
            Session::flash(
                'error',
                'Aucun contact n’est désigné pour recevoir les messages internes sur cette communauté. Utilisez le forum ou contactez un administrateur.'
            );

            return Response::redirect(url('messages'));
        }

        $threadId = null;
        if ($subject === '') {
            $threadId = $this->messageRepository->findRecentOpenAuthorOnlyThreadId($tenantId, $userId);
        }
        if ($threadId !== null) {
            $this->messageRepository->addMessage($threadId, $userId, $body);
            $this->internalMessageNotifications->notifyAfterMessage($tenantId, $threadId, $userId, $body);
            Session::flash('success', 'Message envoyé.');

            return Response::redirect(url('messages/' . $threadId));
        }

        $threadId = $this->messageRepository->createThread($tenantId, $userId, $subject !== '' ? $subject : 'Échange avec l’encadrement', $staffIds);
        $this->messageRepository->addMessage($threadId, $userId, $body);
        $this->internalMessageNotifications->notifyAfterMessage($tenantId, $threadId, $userId, $body);
        Session::flash('success', 'Votre message a été transmis à l’encadrement.');

        return Response::redirect(url('messages/' . $threadId));
    }

    public function reply(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('messages'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        $threadId = (int) ($params['id'] ?? 0);
        if (!$tenantId || !$userId || $threadId <= 0) {
            return Response::redirect(url('messages'));
        }
        $thread = $this->messageRepository->findThread($threadId, $tenantId);
        if (!$thread || !$this->messageRepository->userInThread($threadId, $userId)) {
            Session::flash('error', 'Fil introuvable.');

            return Response::redirect(url('messages'));
        }
        $body = trim((string) $request->input('body', ''));
        if ($body === '') {
            return Response::redirect(url('messages/' . $threadId));
        }
        $this->messageRepository->addMessage($threadId, $userId, $body);
        $this->internalMessageNotifications->notifyAfterMessage($tenantId, $threadId, $userId, $body);
        Session::flash('success', 'Réponse envoyée.');

        return Response::redirect(url('messages/' . $threadId));
    }
}
