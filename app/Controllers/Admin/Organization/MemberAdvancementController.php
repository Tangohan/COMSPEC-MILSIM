<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AdvancementRepository;
use App\Repositories\PersonnelMobilityRequestRepository;
use App\Repositories\UnitRepository;
use App\Services\Advancement\AdvancementWorkflowService;
use Throwable;

/**
 * Espace opérateur : éligibilité, demandes d’affectation / d’avancement, avis de commandement.
 */
final class MemberAdvancementController
{
    public function __construct(
        private AdvancementRepository $repository,
        private AdvancementWorkflowService $workflow,
        private ?PersonnelMobilityRequestRepository $mobility = null,
        private ?UnitRepository $units = null,
    ) {
        $this->mobility ??= new PersonnelMobilityRequestRepository();
        $this->units ??= new UnitRepository();
    }

    public function index(Request $request, array $params = []): Response
    {
        $ctx = $this->requireUser();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $userId] = $ctx;
        $panel = $this->workflow->personnelPanel($tenantId, $userId);

        $mobilityRows = [];
        $units = [];
        $mobilityReady = false;
        try {
            $mobilityReady = $this->mobility->tableExists();
            if ($mobilityReady) {
                $mobilityRows = $this->mobility->listForUser($tenantId, $userId, 20);
            }
        } catch (Throwable) {
            $mobilityReady = false;
        }
        try {
            foreach ($this->units->listFlatForStructure($tenantId) as $unit) {
                if (!is_array($unit) || empty($unit['assignable'])) {
                    continue;
                }
                $units[] = $unit;
            }
        } catch (Throwable) {
            $units = [];
        }

        return Response::view('layout.main', [
            'title' => 'Mon avancement',
            'content' => 'admin.member_situation.avancement',
            'isBackOfficeShell' => true,
            'boPageGroup' => 'Opérateur',
            'boPageKicker' => 'OPÉRATEUR · AVANCEMENT',
            'boPageTitle' => 'Mon avancement',
            'boPageSubtitle' => 'Prochain grade, conditions, demandes d’affectation ou d’avancement, avis de commandement.',
            'backOfficePageCss' => ['back-office-member-situation.css', 'back-office-advancement.css'],
            'panel' => $panel,
            'mobilityRequests' => $mobilityRows,
            'mobilityReady' => $mobilityReady,
            'mobilityTypeLabels' => PersonnelMobilityRequestRepository::TYPE_LABELS,
            'mobilityStatusLabels' => PersonnelMobilityRequestRepository::STATUS_LABELS,
            'assignmentUnits' => $units,
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

    public function requestAdvancement(Request $request, array $params = []): Response
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
        $panel = $this->workflow->personnelPanel($tenantId, $userId);
        $next = is_array($panel['next'] ?? null) ? $panel['next'] : null;
        $campaignId = (int) ($request->input('campaign_id', 0));
        if ($campaignId < 1 && is_array($next)) {
            $campaignId = (int) ($next['campaign_id'] ?? 0);
        }
        if ($campaignId > 0) {
            try {
                $this->workflow->createCandidacy($tenantId, $campaignId, $userId, $userId, [
                    'mobility_requested' => $request->input('mobility_requested') === '1',
                    'notes' => $request->input('notes'),
                ]);
                Session::flash('success', 'Votre demande d’avancement est enregistrée.');
            } catch (Throwable $e) {
                Session::flash('error', $e->getMessage());
            }

            return Response::redirect(url('back-office/ma-situation/avancement'));
        }

        $gradeLabel = trim((string) ($request->input('target_label', '')));
        if ($gradeLabel === '' && is_array($next)) {
            $gradeLabel = trim((string) ($next['grade_label'] ?? ''));
        }
        $motivation = trim((string) $request->input('notes', $request->input('motivation', '')));
        if ($gradeLabel === '' && $motivation === '') {
            Session::flash('error', 'Indiquez le grade visé ou une motivation.');

            return Response::redirect(url('back-office/ma-situation/avancement'));
        }
        $id = $this->createMobility(
            $tenantId,
            $userId,
            'advancement',
            'career_wish',
            null,
            $gradeLabel !== '' ? $gradeLabel : null,
            $motivation !== '' ? $motivation : null
        );
        if ($id !== -1) {
            Session::flash($id > 0 ? 'success' : 'error', $id > 0
                ? 'Votre demande d’avancement a été transmise à l’encadrement.'
                : 'La demande d’avancement n’a pas pu être enregistrée.');
        }

        return Response::redirect(url('back-office/ma-situation/avancement'));
    }

    public function requestAssignment(Request $request, array $params = []): Response
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
        $unitId = (int) $request->input('target_unit_id', 0);
        $targetLabel = trim((string) $request->input('target_label', ''));
        $motivation = trim((string) $request->input('motivation', ''));
        if ($unitId < 1 && $targetLabel === '' && $motivation === '') {
            Session::flash('error', 'Indiquez une unité, un poste ou une motivation.');

            return Response::redirect(url('back-office/ma-situation/avancement'));
        }
        if ($targetLabel === '' && $unitId > 0) {
            try {
                foreach ($this->units->listFlatForStructure($tenantId) as $unit) {
                    if ((int) ($unit['id'] ?? 0) === $unitId) {
                        $targetLabel = trim((string) ($unit['name'] ?? ''));
                        break;
                    }
                }
            } catch (Throwable) {
            }
        }
        $id = $this->createMobility(
            $tenantId,
            $userId,
            'assignment',
            'unit_change',
            $unitId > 0 ? $unitId : null,
            $targetLabel !== '' ? $targetLabel : null,
            $motivation !== '' ? $motivation : null
        );
        if ($id !== -1) {
            Session::flash($id > 0 ? 'success' : 'error', $id > 0
                ? 'Votre demande d’affectation a été transmise à l’encadrement.'
                : 'La demande d’affectation n’a pas pu être enregistrée.');
        }

        return Response::redirect(url('back-office/ma-situation/avancement'));
    }

    private function createMobility(
        int $tenantId,
        int $userId,
        string $preferredType,
        string $fallbackType,
        ?int $unitId,
        ?string $targetLabel,
        ?string $motivation
    ): int {
        try {
            if (!$this->mobility->tableExists()) {
                return 0;
            }
            if ($this->mobility->hasPending($tenantId, $userId, $preferredType)
                || $this->mobility->hasPending($tenantId, $userId, $fallbackType)
            ) {
                Session::flash('error', 'Une demande du même type est déjà en attente.');

                return -1;
            }
            $id = $this->mobility->create(
                $tenantId,
                $userId,
                $preferredType,
                $unitId,
                null,
                $targetLabel,
                $motivation !== null ? mb_substr($motivation, 0, 2000) : null,
                $userId
            );
            if ($id > 0) {
                return $id;
            }
        } catch (Throwable) {
        }
        try {
            return $this->mobility->create(
                $tenantId,
                $userId,
                $fallbackType,
                $unitId,
                null,
                $targetLabel,
                $motivation !== null ? mb_substr($motivation, 0, 2000) : null,
                $userId
            );
        } catch (Throwable) {
            return 0;
        }
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
