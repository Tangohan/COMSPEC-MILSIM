<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;

class AdminHubController
{
    public function index(Request $request, array $params = []): Response
    {
        $gate = \App\Core\Gate::getInstance();
        $canSystem = $gate->allows('admin.system');
        $canOrganization = $gate->allows('admin.organization') || $gate->allows('admin.access');

        if ($canSystem && !$canOrganization) {
            return Response::redirect(url('admin/system'));
        }
        if ($canOrganization && !$canSystem) {
            return Response::redirect(url('admin/organization'));
        }
        if (!$canSystem && !$canOrganization) {
            \App\Core\Session::flash('error', 'Vous n\'avez pas accès à l\'administration.');
            return Response::redirect(url('dashboard'));
        }

        return Response::view('layout.main', [
            'content' => 'admin.hub',
            'title' => 'Centre d\'administration',
            'canSystem' => $canSystem,
            'canOrganization' => $canOrganization,
        ]);
    }
}
