<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Csrf;
use App\Core\Gate;
use App\Repositories\SiteSettingsRepository;

class SiteSettingsApiController
{
    public function __construct(
        private SiteSettingsRepository $siteSettingsRepository
    ) {}

    public function handle(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::json(['success' => false, 'message' => 'Non authentifié'], 401);
        }
        if (!Gate::getInstance()->allows('admin.access') && !(function_exists('can') && can('forum.manage_categories'))) {
            return Response::json(['success' => false, 'message' => 'Non autorisé'], 403);
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            return Response::json(['success' => false, 'message' => 'Jeton CSRF invalide'], 403);
        }

        $settingsJson = $request->input('settings');
        if (is_string($settingsJson)) {
            $settings = json_decode($settingsJson, true);
        } else {
            $settings = [];
        }
        if (!is_array($settings)) {
            return Response::json(['success' => false, 'message' => 'Paramètres invalides'], 400);
        }

        $this->siteSettingsRepository->setForumSettings((int) $tenantId, $settings);
        return Response::json(['success' => true]);
    }
}
