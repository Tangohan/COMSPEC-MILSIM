<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PlatformSettingsRepository;

class PlatformBriefSettingsApiController
{
    public function __construct(
        private PlatformSettingsRepository $platformSettingsRepository
    ) {}

    public function handle(Request $request, array $params = []): Response
    {
        if (!Session::get('user_id')) {
            return Response::json(['success' => false, 'message' => 'Non authentifié'], 401);
        }
        if (Gate::getInstance()->deny('admin.system')) {
            return Response::json(['success' => false, 'message' => 'Non autorisé'], 403);
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            return Response::json(['success' => false, 'message' => 'Jeton CSRF invalide'], 403);
        }

        $openRaw = $request->input('brief_member_access');
        $open = in_array(strtolower(trim((string) $openRaw)), ['1', 'true', 'yes', 'on'], true);
        $msg = trim(strip_tags((string) $request->input('brief_member_closed_message', '')));
        if (strlen($msg) > 4000) {
            $msg = substr($msg, 0, 4000);
        }

        $this->platformSettingsRepository->setMany([
            'brief_member_access' => $open ? '1' : '0',
            'brief_member_closed_message' => $msg,
        ]);

        return Response::json(['success' => true]);
    }
}
