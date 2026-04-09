<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Analytics\AnalyticsEventService;

final class AnalyticsBeaconController
{
    public function __construct(
        private AnalyticsEventService $analyticsEventService
    ) {}

    public function post(Request $request, array $params = []): Response
    {
        if (!$request->isPost()) {
            return Response::json(['ok' => false], 405);
        }
        Session::start();
        if (!Csrf::validate($request->input('_csrf_token'))) {
            return Response::json(['ok' => false], 403);
        }
        $tenantId = (int) $request->input('tenant_id', 0);
        $category = trim((string) $request->input('category', ''));
        $name = trim((string) $request->input('name', ''));
        $subjectType = trim((string) $request->input('subject_type', ''));
        $subjectId = (int) $request->input('subject_id', 0);
        $duration = $request->input('duration_seconds');
        $durationSeconds = is_numeric($duration) ? (int) $duration : null;

        $propsRaw = $request->input('props_json');
        $props = null;
        if (is_string($propsRaw) && $propsRaw !== '') {
            $decoded = json_decode($propsRaw, true);
            $props = is_array($decoded) ? $decoded : null;
        }

        $userId = Session::get('user_id');
        $actorUserId = $userId ? (int) $userId : null;

        $subjectTypeNorm = $subjectType === '' ? null : $subjectType;
        $subjectIdNorm = $subjectId > 0 ? $subjectId : null;

        $ok = $this->analyticsEventService->recordBeacon(
            $tenantId,
            $actorUserId,
            $category,
            $name,
            $subjectTypeNorm,
            $subjectIdNorm,
            $durationSeconds,
            $props
        );

        return Response::json(['ok' => $ok]);
    }
}
