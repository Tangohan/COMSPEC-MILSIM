<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;

class AdminDashboardController
{
    public function index(Request $request, array $params = []): Response
    {
        return Response::view('layout.main', [
            'content' => 'admin.dashboard',
            'title' => 'Administration',
        ]);
    }
}
