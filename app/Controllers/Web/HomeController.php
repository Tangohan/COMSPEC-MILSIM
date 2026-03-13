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
        $currentUser = null;
        $personnelExtras = null;
        $grade = null;
        $tenantId = Session::get('tenant_id');
        if ($tenantId) {
            $modpackRepo = \App\Core\Container::get(\App\Repositories\ModpackRepository::class);
            $modpack = $modpackRepo->getPrimaryForTenant((int) $tenantId);
            $auth = \App\Core\Container::get(\App\Services\Auth\AuthService::class);
            $currentUser = $auth->user();
            if ($currentUser) {
                $extrasRepo = \App\Core\Container::get(\App\Repositories\PersonnelExtrasRepository::class);
                $gradeRepo = \App\Core\Container::get(\App\Repositories\GradeRepository::class);
                $personnelExtras = $extrasRepo->getByUserId((int) $currentUser['id']);
                if (!empty($currentUser['grade_id'])) {
                    $grade = $gradeRepo->findById((int) $currentUser['grade_id'], (int) $tenantId);
                }
            }
        }
        return Response::view('dashboard', [
            'title' => 'Dashboard — Athena',
            'modpack' => $modpack,
            'currentUser' => $currentUser,
            'personnelExtras' => $personnelExtras,
            'grade' => $grade,
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
