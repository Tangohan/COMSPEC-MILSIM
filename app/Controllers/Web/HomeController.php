<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

class HomeController
{
    public function index(Request $request, array $params = []): Response
    {
        return Response::view('home.index', ['title' => 'Athena — Commandement Aérien MILSIM']);
    }

    public function dashboard(Request $request, array $params = []): Response
    {
        $modpack = null;
        $tenantId = Session::get('tenant_id');
        if ($tenantId) {
            $repo = \App\Core\Container::get(\App\Repositories\ModpackRepository::class);
            $modpack = $repo->getPrimaryForTenant((int) $tenantId);
        }
        return Response::view('dashboard', [
            'title' => 'Dashboard — Athena',
            'modpack' => $modpack,
        ]);
    }

    public function enlistment(Request $request, array $params = []): Response
    {
        return Response::view('layout.main', ['content' => 'enlistment', 'title' => 'Enrôlement']);
    }

    public function recrutement(Request $request, array $params = []): Response
    {
        return Response::view('recrutement');
    }

    public function equipement(Request $request, array $params = []): Response
    {
        return Response::view('equipement');
    }

    public function tacmap(Request $request, array $params = []): Response
    {
        return Response::view('tacmap');
    }
}
