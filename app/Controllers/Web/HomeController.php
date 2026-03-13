<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;

class HomeController
{
    public function index(Request $request, array $params = []): Response
    {
        return Response::view('layout.main', ['content' => 'home', 'title' => 'Athena — Accueil']);
    }

    public function dashboard(Request $request, array $params = []): Response
    {
        return Response::view('layout.main', ['content' => 'dashboard', 'title' => 'Dashboard']);
    }

    public function enlistment(Request $request, array $params = []): Response
    {
        return Response::view('layout.main', ['content' => 'enlistment', 'title' => 'Enrôlement']);
    }

    public function recrutement(Request $request, array $params = []): Response
    {
        return Response::view('layout.main', ['content' => 'recrutement', 'title' => 'Recrutement']);
    }
}
