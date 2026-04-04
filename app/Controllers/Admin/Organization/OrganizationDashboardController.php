<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Request;
use App\Core\Response;

class OrganizationDashboardController
{
    public function index(Request $request, array $params = []): Response
    {
        return Response::view('layout.main', [
            'content' => 'admin.organization.dashboard',
            'title' => 'Administration organisationnelle',
        ]);
    }
}
