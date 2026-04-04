<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Request;
use App\Core\Response;

class SystemSettingsController
{
    public function index(Request $request, array $params = []): Response
    {
        return Response::view('layout.main', [
            'content' => 'admin.system.placeholder',
            'title' => 'Paramètres applicatifs',
            'label' => 'Les paramètres applicatifs',
        ]);
    }
}
