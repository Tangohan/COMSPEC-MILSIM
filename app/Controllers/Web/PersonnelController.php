<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Auth\AuthService;
use App\Repositories\UserRepository;
use App\Repositories\PersonnelExtrasRepository;
use App\Repositories\UnitRepository;

class PersonnelController
{
    public function __construct(
        private AuthService $authService,
        private UserRepository $userRepository,
        private PersonnelExtrasRepository $personnelExtrasRepository,
        private UnitRepository $unitRepository
    ) {}

    public function me(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        return $this->show($request, ['id' => (string) $user['id']]);
    }

    public function show(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $id = (int) ($params['id'] ?? 0);
        $target = $this->userRepository->findById($id, (int) $tenantId);
        if (!$target) {
            return (new Response())->setStatusCode(404)->setBody('Utilisateur non trouvé.');
        }
        $extras = $this->personnelExtrasRepository->getByUserId((int) $target['id']);
        $profile = $this->personnelExtrasRepository->getProfileByUserId((int) $target['id']);
        return Response::view('layout.main', [
            'content' => 'personnel.file',
            'title' => 'Fiche personnel',
            'targetUser' => $target,
            'personnelExtras' => $extras,
            'userProfile' => $profile,
        ]);
    }

    public function orbat(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $tree = $this->unitRepository->getTree((int) $tenantId);
        return Response::view('layout.main', [
            'content' => 'personnel.orbat',
            'title' => 'ORBAT',
            'unitsTree' => $tree,
        ]);
    }
}
