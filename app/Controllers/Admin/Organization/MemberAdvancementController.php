<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AdvancementRepository;
use App\Services\Advancement\AdvancementWorkflowService;
use Throwable;

/**
 * Encart personnel : éligibilité, volontariat, historique de grade.
 */
final class MemberAdvancementController
{
    public function __construct(
        private AdvancementRepository $repository,
        private AdvancementWorkflowService $workflow,
    ) {
    }

    public function index(Request $request, array $params = []): Response
    {
        $ctx = $this->requireUser();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $userId] = $ctx;
        $panel = $this->workflow->personnelPanel($tenantId, $userId);

        return Response::view('layout.main', [
            'title' => 'Mon avancement',
            'content' => 'admin.member_situation.avancement',
            'isBackOfficeShell' => true,
            'boPageGroup' => 'Opérateur',
            'boPageKicker' => 'OPÉRATEUR · AVANCEMENT',
            'boPageTitle' => 'Mon avancement',
            'boPageSubtitle' => 'Grade détenu, voie d’obtention et campagne au choix ouverte pour le grade suivant.',
            'backOfficePageCss' => ['back-office-member-situation.css', 'back-office-advancement.css'],
            'panel' => $panel,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
        ]);
    }

    public function volunteer(Request $request, array $params = []): Response
    {
        $ctx = $this->requireUser();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $userId] = $ctx;
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/ma-situation/avancement'));
        }
        $campaignId = (int) ($params['campaignId'] ?? $request->input('campaign_id', 0));
        $campaign = $this->repository->findCampaign($campaignId, $tenantId);
        if ($campaign === null) {
            Session::flash('error', 'Campagne introuvable.');

            return Response::redirect(url('back-office/ma-situation/avancement'));
        }
        try {
            $this->workflow->createCandidacy($tenantId, $campaignId, $userId, $userId, [
                'mobility_requested' => $request->input('mobility_requested') === '1',
                'notes' => $request->input('notes'),
            ]);
            Session::flash('success', 'Votre candidature est enregistrée.');
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        return Response::redirect(url('back-office/ma-situation/avancement'));
    }

    /**
     * @return array{0:int,1:int}|Response
     */
    private function requireUser(): array|Response
    {
        $userId = (int) Session::get('user_id');
        $tenantId = (int) Session::get('tenant_id');
        if ($userId < 1 || $tenantId < 1) {
            return Response::redirect(url('login'));
        }

        return [$tenantId, $userId];
    }
}
