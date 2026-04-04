<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\ForumCategoryRepository;
use App\Repositories\SiteSettingsRepository;
use App\Repositories\ForumBannedWordRepository;
use App\Repositories\ForumBlacklistedDomainRepository;

class AdminForumConfigController
{
    public function __construct(
        private ForumCategoryRepository $forumCategoryRepository,
        private SiteSettingsRepository $siteSettingsRepository,
        private ForumBannedWordRepository $bannedWordRepository,
        private ForumBlacklistedDomainRepository $blacklistedDomainRepository
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $tenantId = (int) $tenantId;
        $categories = $this->forumCategoryRepository->listForTenantWithChildren($tenantId);
        $forumConfig = config('forum') ?? [];
        $siteSettings = $this->siteSettingsRepository->getForumSettings($tenantId);

        return Response::view('layout.main', [
            'content' => 'admin.forum-config.index',
            'title' => 'Configuration forum — Chambre des Murmures',
            'categories' => $categories,
            'forumConfig' => array_merge($forumConfig, $siteSettings),
            'bannedWords' => $this->bannedWordRepository->listForTenant($tenantId),
            'blacklistedDomains' => $this->blacklistedDomainRepository->listForTenant($tenantId),
        ]);
    }
}
