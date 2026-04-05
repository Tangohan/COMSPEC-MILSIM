<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Request;
use App\Core\Response;

class SystemSettingsController
{
    public function index(Request $request, array $params = []): Response
    {
        $appConfig = [
            'name' => config('app.name'),
            'env' => config('app.env'),
            'debug' => config('app.debug'),
            'url' => config('app.url') ?: '—',
            'timezone' => config('app.timezone'),
            'locale' => config('app.locale'),
            'maintenance' => 'Tables app_maintenance — /admin/maintenance (plus de simple booléen .env)',
            'log_channel' => config('app.log.channel'),
            'log_level' => config('app.log.level'),
        ];

        return Response::view('layout.main', [
            'content' => 'admin.system.settings',
            'title' => 'Paramètres applicatifs',
            'appConfig' => $appConfig,
        ]);
    }
}
