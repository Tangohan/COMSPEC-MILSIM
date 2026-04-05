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
        if (!Gate::getInstance()->allows('admin.access') && !(function_exists('can') && can('forum.categories.manage'))) {
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

        $this->siteSettingsRepository->setForumSettings((int) $tenantId, $this->sanitizeForumSettings($settings));
        return Response::json(['success' => true]);
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, string>
     */
    private function sanitizeForumSettings(array $settings): array
    {
        $out = [];
        foreach ($settings as $key => $value) {
            if (!is_string($key) || strpos($key, 'forum_') !== 0) {
                continue;
            }
            if ($key === 'forum_hero_image_url') {
                $t = trim((string) $value);
                if ($t === '') {
                    $out[$key] = '';
                    continue;
                }
                if (strlen($t) > 500 || !$this->isAllowedForumHeroImageUrl($t)) {
                    continue;
                }
                $out[$key] = $t;
                continue;
            }
            $out[$key] = is_scalar($value) ? (string) $value : json_encode($value);
        }

        return $out;
    }

    private function isAllowedForumHeroImageUrl(string $url): bool
    {
        if (stripos($url, 'javascript:') !== false) {
            return false;
        }
        if (preg_match('#^\s*data:#i', $url)) {
            return false;
        }
        if (preg_match('#^/#', $url)) {
            return true;
        }
        $p = parse_url($url);
        if (!is_array($p) || empty($p['scheme'])) {
            return false;
        }
        $scheme = strtolower((string) $p['scheme']);

        return $scheme === 'http' || $scheme === 'https';
    }
}
