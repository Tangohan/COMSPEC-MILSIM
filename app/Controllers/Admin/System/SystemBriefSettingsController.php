<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\PlatformSettingsRepository;

class SystemBriefSettingsController
{
    public function __construct(
        private PlatformSettingsRepository $platformSettingsRepository
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $open = true;
        $message = '';
        if ($this->platformSettingsRepository->tableExists()) {
            $open = $this->platformSettingsRepository->getBool('brief_member_access', true);
            $message = $this->platformSettingsRepository->get('brief_member_closed_message', '');
        }

        return Response::view('layout.main', [
            'content' => 'admin.system.brief_settings',
            'title' => 'Brief — accès membres',
            'briefMemberAccessOpen' => $open,
            'briefMemberClosedMessage' => $message,
            'csrfToken' => \App\Core\Csrf::token(),
        ]);
    }
}
