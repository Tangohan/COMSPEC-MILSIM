<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Csrf;
use App\Repositories\ForumTopicRepository;
use App\Repositories\ForumReportRepository;

class ForumModerationController
{
    public function __construct(
        private ForumTopicRepository $topicRepository,
        private ForumReportRepository $reportRepository
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        return Response::redirect(url('admin/forum-moderation'));
    }

    public function handleReport(Request $request, array $params = []): Response
    {
        $gate = \App\Core\Gate::getInstance();
        $ok = (function_exists('can') && can('forum.moderate'))
            || (function_exists('can') && can('forum.moderate_organization'))
            || $gate->allows('admin.organization')
            || $gate->allows('admin.access');
        if (!$ok) {
            Session::flash('error', 'Accès refusé.');
            return Response::redirect(url('forum'));
        }

        $tenantId = Session::get('tenant_id');
        $userId = Session::get('user_id');
        if (!$tenantId || !$userId) {
            return Response::redirect(url('login'));
        }

        if ($request->method() !== 'POST' || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Requête invalide.');
            return Response::redirect(url('admin/forum-moderation'));
        }

        $id = (int) ($params['id'] ?? 0);
        $report = $this->reportRepository->findById($id, $tenantId);
        if (!$report) {
            Session::flash('error', 'Signalement introuvable.');
            return Response::redirect(url('admin/forum-moderation'));
        }

        $this->reportRepository->markHandled($id, $tenantId, $userId);
        Session::flash('success', 'Signalement traité.');
        return Response::redirect(url('admin/forum-moderation'));
    }

    public function lockTopic(Request $request, array $params = []): Response
    {
        return $this->setTopicLock($request, $params, true);
    }

    public function unlockTopic(Request $request, array $params = []): Response
    {
        return $this->setTopicLock($request, $params, false);
    }

    public function pinTopic(Request $request, array $params = []): Response
    {
        return $this->setTopicPin($request, $params, true);
    }

    public function unpinTopic(Request $request, array $params = []): Response
    {
        return $this->setTopicPin($request, $params, false);
    }

    private function setTopicLock(Request $request, array $params, bool $locked): Response
    {
        if (!function_exists('can') || (!can('forum.moderate') && !can('forum.moderate_organization'))) {
            Session::flash('error', 'Accès refusé.');
            return Response::redirect(url('forum'));
        }

        $tenantId = Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $topic = $this->topicRepository->findById($id, $tenantId);
        if (!$topic) {
            Session::flash('error', 'Sujet introuvable.');
            return Response::redirect(url('forum'));
        }

        if ($request->method() === 'POST' && Csrf::validate($request->input('_csrf_token'))) {
            $this->topicRepository->update($id, $tenantId, ['is_locked' => $locked ? 1 : 0]);
            Session::flash('success', $locked ? 'Sujet verrouillé.' : 'Sujet déverrouillé.');
        }

        return Response::redirect(url('forum/topic/' . $id));
    }

    private function setTopicPin(Request $request, array $params, bool $pinned): Response
    {
        if (!function_exists('can') || (!can('forum.moderate') && !can('forum.moderate_organization'))) {
            Session::flash('error', 'Accès refusé.');
            return Response::redirect(url('forum'));
        }

        $tenantId = Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $topic = $this->topicRepository->findById($id, $tenantId);
        if (!$topic) {
            Session::flash('error', 'Sujet introuvable.');
            return Response::redirect(url('forum'));
        }

        if ($request->method() === 'POST' && Csrf::validate($request->input('_csrf_token'))) {
            $this->topicRepository->update($id, $tenantId, ['is_pinned' => $pinned ? 1 : 0]);
            Session::flash('success', $pinned ? 'Sujet épinglé.' : 'Sujet désépinglé.');
        }

        return Response::redirect(url('forum/topic/' . $id));
    }
}
