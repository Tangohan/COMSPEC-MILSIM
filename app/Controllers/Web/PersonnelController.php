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
use App\Repositories\GradeRepository;
use App\Repositories\PersonnelAdminPanelRepository;
use App\Repositories\PersonnelAdminDataRepository;
use App\Services\Personnel\MatriculeService;

class PersonnelController
{
    public function __construct(
        private AuthService $authService,
        private UserRepository $userRepository,
        private PersonnelExtrasRepository $personnelExtrasRepository,
        private UnitRepository $unitRepository,
        private GradeRepository $gradeRepository,
        private PersonnelAdminPanelRepository $adminPanelRepository,
        private PersonnelAdminDataRepository $adminDataRepository,
        private MatriculeService $matriculeService
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
        $grades = $this->gradeRepository->listForTenant((int) $tenantId);
        $grade = null;
        if (!empty($target['grade_id'])) {
            foreach ($grades as $g) {
                if ((int) $g['id'] === (int) $target['grade_id']) {
                    $grade = $g;
                    break;
                }
            }
        }
        $adminPanels = $this->adminPanelRepository->listForTenant((int) $tenantId);
        $adminDataByPanel = $this->adminDataRepository->getAllForUser((int) $target['id']);
        return Response::view('layout.main', [
            'content' => 'personnel.file',
            'title' => 'Fiche personnel',
            'targetUser' => $target,
            'personnelExtras' => $extras,
            'userProfile' => $profile,
            'grade' => $grade,
            'grades' => $grades,
            'adminPanels' => $adminPanels,
            'adminDataByPanel' => $adminDataByPanel,
        ]);
    }

    public function generateMatricule(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $id = (int) ($params['id'] ?? 0);
        $target = $this->userRepository->findById($id, $tenantId);
        if (!$target) {
            return (new Response())->setStatusCode(404)->setBody('Utilisateur non trouvé.');
        }
        $currentUserId = (int) Session::get('user_id');
        $isSelf = ($currentUserId === (int) $target['id']);
        $isAdmin = \App\Core\Gate::getInstance()->allows('admin.access');
        if (!$isSelf && !$isAdmin) {
            return (new Response())->setStatusCode(403)->setBody('Non autorisé.');
        }
        $this->matriculeService->assignNextForUser((int) $target['id'], $tenantId);
        $redirect = $isSelf ? url('personnel/me') : url('personnel/' . $id);
        return Response::redirect($redirect);
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
