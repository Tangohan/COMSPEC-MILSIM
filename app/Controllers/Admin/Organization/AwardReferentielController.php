<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AwardDefinitionRepository;
use App\Repositories\PersonnelAwardRepository;
use App\Repositories\PersonnelCareerEventRepository;
use App\Repositories\UserRepository;

final class AwardReferentielController
{
    public function __construct(
        private AwardDefinitionRepository $definitions,
        private PersonnelAwardRepository $awards,
        private UserRepository $users,
        private PersonnelCareerEventRepository $careerEvents,
    ) {
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenant();
        if ($tenantId instanceof Response) {
            return $tenantId;
        }

        return Response::view('layout.main', [
            'content' => 'admin.organization.advancement.awards_index',
            'title' => 'Décorations',
            'isBackOfficeShell' => true,
            'boPageTitle' => 'Décorations et citations',
            'boPageKicker' => 'ORGANISATION · DÉCORATIONS',
            'boPageSubtitle' => 'Ce que l’on reconnaît avoir fait — distinct des qualifications (ce que l’on sait faire).',
            'definitions' => $this->definitions->listForTenant($tenantId, true),
            'members' => $this->users->listForTenant($tenantId, null, 'active', null, 200, 0, true),
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $tenantId = $this->post($request);
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $code = strtoupper(trim((string) $request->input('code', '')));
        $name = trim((string) $request->input('name', ''));
        if ($code === '' || $name === '') {
            Session::flash('error', 'Code et nom obligatoires.');

            return Response::redirect(url('back-office/referentiels/decorations'));
        }
        $this->definitions->create($tenantId, [
            'code' => $code,
            'name' => $name,
            'decoration_grade' => $request->input('decoration_grade'),
            'award_criterion' => $request->input('award_criterion'),
            'sort_order' => (int) $request->input('sort_order', 0),
        ], (int) Session::get('user_id'));
        Session::flash('success', 'Décoration créée.');

        return Response::redirect(url('back-office/referentiels/decorations'));
    }

    public function archive(Request $request, array $params = []): Response
    {
        $tenantId = $this->post($request);
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $this->definitions->archive($tenantId, (int) ($params['id'] ?? 0));
        Session::flash('success', 'Décoration archivée.');

        return Response::redirect(url('back-office/referentiels/decorations'));
    }

    public function grant(Request $request, array $params = []): Response
    {
        $tenantId = $this->post($request);
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $personnelId = (int) $request->input('personnel_id', 0);
        $definitionId = (int) $request->input('definition_id', 0);
        if ($personnelId < 1 || $definitionId < 1) {
            Session::flash('error', 'Personnel et décoration obligatoires.');

            return Response::redirect(url('back-office/referentiels/decorations'));
        }
        $this->awards->create($tenantId, [
            'personnel_id' => $personnelId,
            'definition_id' => $definitionId,
            'citation_text' => $request->input('citation_text'),
            'authority' => $request->input('authority'),
            'awarded_at' => $request->input('awarded_at', date('Y-m-d')),
        ], (int) Session::get('user_id'));
        $this->careerEvents->record($tenantId, $personnelId, 'decoration_awarded', (int) Session::get('user_id'), [
            'definition_id' => $definitionId,
        ]);
        Session::flash('success', 'Citation enregistrée.');

        return Response::redirect(url('back-office/referentiels/decorations'));
    }

    private function tenant(): int|Response
    {
        $id = (int) Session::get('tenant_id');

        return $id > 0 ? $id : Response::redirect(url('login'));
    }

    private function post(Request $request): int|Response
    {
        $tenantId = $this->tenant();
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/referentiels/decorations'));
        }

        return $tenantId;
    }
}
