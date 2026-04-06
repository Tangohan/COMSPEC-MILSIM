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
            if ($key === 'forum_moderation_tutorial_html') {
                $html = $this->sanitizeForumTutorialHtml((string) $value);
                if (strlen($html) > 20000) {
                    $html = substr($html, 0, 20000);
                }
                $out[$key] = $html;
                continue;
            }
            if ($key === 'forum_name' || $key === 'forum_subtitle' || $key === 'forum_tagline' || $key === 'forum_context') {
                $t = trim((string) $value);
                if (strlen($t) > 500) {
                    $t = substr($t, 0, 500);
                }
                $out[$key] = $t;
                continue;
            }
            if ($key === 'forum_role_read_label' || $key === 'forum_role_write_label') {
                $t = trim((string) $value);
                if (strlen($t) > 120) {
                    $t = substr($t, 0, 120);
                }
                $out[$key] = $t;
                continue;
            }
            if ($key === 'forum_enabled') {
                continue;
            }
            if ($key === 'forum_community_section_enabled') {
                $v = strtolower(trim((string) $value));
                $out[$key] = in_array($v, ['1', 'true', 'yes', 'on'], true) ? '1' : '0';
                continue;
            }
            if ($key === 'forum_community_section_notice') {
                $t = trim(strip_tags((string) $value));
                if (strlen($t) > 2000) {
                    $t = substr($t, 0, 2000);
                }
                $out[$key] = $t;
                continue;
            }
            if ($key === 'forum_attachments_allowed_ext') {
                $out[$key] = $this->sanitizeForumAttachmentExtList((string) $value);
                continue;
            }
            if ($key === 'forum_topics_per_page' || $key === 'forum_posts_per_page') {
                $n = (int) $value;
                $out[$key] = (string) max(1, min(200, $n > 0 ? $n : 20));
                continue;
            }
            if ($key === 'forum_cooldown_seconds') {
                $n = (int) $value;
                $out[$key] = (string) max(0, min(86400, $n));
                continue;
            }
            if ($key === 'forum_antispam_min_length') {
                $n = (int) $value;
                $out[$key] = (string) max(1, min(5000, $n > 0 ? $n : 20));
                continue;
            }
            if ($key === 'forum_attachments_max_size') {
                $n = (int) $value;
                if ($n <= 0) {
                    $out[$key] = '0';
                } else {
                    $out[$key] = (string) max(1024, min(52_428_800, $n));
                }
                continue;
            }
            if ($key === 'forum_max_post_length') {
                $n = (int) $value;
                $out[$key] = (string) max(500, min(200000, $n > 0 ? $n : 10000));
                continue;
            }
            $out[$key] = is_scalar($value) ? (string) $value : json_encode($value);
        }

        return $out;
    }

    private function sanitizeForumTutorialHtml(string $html): string
    {
        $html = preg_replace('#<(script|iframe|object|embed|style)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;
        $html = preg_replace('/\bon\w+\s*=\s*(["\']).*?\1/iu', '', $html) ?? $html;

        return strip_tags($html, '<p><br><strong><b><em><i><u><ul><ol><li><a><h2><h3><h4><blockquote><span><div>');
    }

    private function sanitizeForumAttachmentExtList(string $raw): string
    {
        $safe = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
        $parts = preg_split('/[\s,]+/', strtolower(trim($raw)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $p = preg_replace('/[^a-z0-9]/', '', $p) ?? '';
            if ($p !== '' && in_array($p, $safe, true)) {
                $out[] = $p;
            }
        }

        return implode(',', array_values(array_unique($out)));
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
