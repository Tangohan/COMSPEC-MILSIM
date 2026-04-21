<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\ForumReportRepository;
use App\Repositories\ForumModerationLogRepository;

/**
 * Console centralisée /back-office/forum-moderation (modérateurs + admins).
 */
final class ForumModerationDashboardController
{
    public function __construct(
        private ForumReportRepository $reportRepository,
        private ForumModerationLogRepository $moderationLogRepository
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if (!$tenantId || !$userId) {
            return Response::redirect(url('login'));
        }

        $scopeFilter = (string) $request->query('scope', '');
        $pendingReports = $this->reportRepository->listPending($tenantId);
        $onlyOrgMod = function_exists('can') && can('forum.moderate_organization') && !can('forum.moderate');
        if ($onlyOrgMod) {
            $pendingReports = array_values(array_filter($pendingReports, static function (array $r): bool {
                return in_array((string) ($r['category_scope'] ?? ''), ['organization', 'tenant'], true);
            }));
        } elseif ($scopeFilter === 'organization') {
            $pendingReports = array_values(array_filter($pendingReports, static function (array $r): bool {
                return in_array((string) ($r['category_scope'] ?? ''), ['organization', 'tenant'], true);
            }));
        }

        $handledReports = $this->reportRepository->listHandled($tenantId, 15);
        $timelineIds = [];
        foreach ($pendingReports as $row) {
            $rid = (int) ($row['id'] ?? 0);
            if ($rid > 0) {
                $timelineIds[] = $rid;
            }
        }
        foreach ($handledReports as $row) {
            $rid = (int) ($row['id'] ?? 0);
            if ($rid > 0) {
                $timelineIds[] = $rid;
            }
        }
        $reportTimelines = $this->reportRepository->timelineByReportIds($tenantId, $timelineIds, 8);

        $forumModerationLogsAvailable = $this->moderationLogRepository->tableExists();
        $forumModerationLogs = $forumModerationLogsAvailable
            ? $this->moderationLogRepository->listRecentForTenant($tenantId, 40)
            : [];

        return Response::view('layout.forum', [
            'content' => 'admin.forum_moderation',
            'title' => 'Modération forum',
            'forumConfig' => forum_config_for_tenant($tenantId),
            'pendingReports' => $pendingReports,
            'handledReports' => $handledReports,
            'reportTimelines' => $reportTimelines,
            'modScopeFilter' => $scopeFilter,
            'forumModerationLogs' => $forumModerationLogs,
            'forumModerationLogsAvailable' => $forumModerationLogsAvailable,
        ]);
    }
}
