<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\UserRepository;

class AdminUsersController
{
    public function __construct(
        private UserRepository $userRepository
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $users = $this->userRepository->allForTenant((int) $tenantId);
        return Response::view('layout.main', [
            'content' => 'admin.users.index',
            'title' => 'Utilisateurs',
            'users' => $users,
        ]);
    }

    public function create(Request $request, array $params = []): Response
    {
        return Response::view('layout.main', ['content' => 'admin.users.create', 'title' => 'Nouvel utilisateur']);
    }
}
