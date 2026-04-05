<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\ForumReportRepository;

/**
 * Console centralisée /back-office/forum-moderation (modérateurs + admins).
 */
final class ForumModerationDashboardController
{
    public function __construct(
        private ForumReportRepository $reportRepository
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
                return ($r['category_scope'] ?? '') === 'organization';
            }));
        } elseif ($scopeFilter === 'organization') {
            $pendingReports = array_values(array_filter($pendingReports, static function (array $r): bool {
                return ($r['category_scope'] ?? '') === 'organization';
            }));
        }

        $handledReports = $this->reportRepository->listHandled($tenantId, 15);

        return Response::view('layout.forum', [
            'content' => 'admin.forum_moderation',
            'title' => 'Modération forum',
            'forumConfig' => forum_config_for_tenant($tenantId),
            'pendingReports' => $pendingReports,
            'handledReports' => $handledReports,
            'modScopeFilter' => $scopeFilter,
        ]);
    }
}
