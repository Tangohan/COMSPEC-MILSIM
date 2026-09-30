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
use App\Services\Personnel\AssignmentTargetCatalog;
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
        private ?AssignmentTargetCatalog $targets = null,
    ) {
        $this->mobility ??= new PersonnelMobilityRequestRepository();
        $this->units ??= new UnitRepository();
        $this->targets ??= new AssignmentTargetCatalog();
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

        $targetGroups = ['postes' => [], 'aav' => [], 'offres' => []];
        try {
            $targetGroups = $this->targets->grouped($tenantId);
        } catch (Throwable) {
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
            'assignmentTargets' => $targetGroups,
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
                'mobility_requested' => $request->input('mobility_requested') === '1' || $request->input('target_ref', '') !== '',
                'requested_billet_id' => $this->billetIdFromRef($tenantId, (string) $request->input('target_ref', '')),
                'notes' => $this->notesWithTarget($tenantId, $request),
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
        $campaignId = (int) $request->input('campaign_id', 0);
        $target = $this->resolveTarget($tenantId, (string) $request->input('target_ref', ''));
        if ($target !== null && (int) ($target['campaign_id'] ?? 0) > 0 && $campaignId < 1) {
            $campaignId = (int) $target['campaign_id'];
        }
        if ($campaignId < 1 && is_array($next)) {
            $campaignId = (int) ($next['campaign_id'] ?? 0);
        }
        if ($campaignId > 0) {
            try {
                $this->workflow->createCandidacy($tenantId, $campaignId, $userId, $userId, [
                    'mobility_requested' => $request->input('mobility_requested') === '1' || $request->input('target_ref', '') !== '',
                    'requested_billet_id' => $this->billetIdFromRef($tenantId, (string) $request->input('target_ref', '')),
                    'notes' => $this->notesWithTarget($tenantId, $request),
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
        if ($gradeLabel === '' && $target !== null) {
            $gradeLabel = (string) ($target['label'] ?? '');
        }
        $motivation = trim((string) $request->input('notes', $request->input('motivation', '')));
        if ($gradeLabel === '' && $motivation === '' && $target === null) {
            Session::flash('error', 'Indiquez le grade visé, une cible ou une motivation.');

            return Response::redirect(url('back-office/ma-situation/avancement'));
        }
        $id = $this->createMobility(
            $tenantId,
            $userId,
            'advancement',
            'career_wish',
            $target !== null ? ($target['unit_id'] ?? null) : null,
            $gradeLabel !== '' ? $gradeLabel : null,
            $motivation !== '' ? $motivation : null,
            $target
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
        $target = $this->resolveTarget($tenantId, (string) $request->input('target_ref', ''));
        if ($target !== null) {
            if ($unitId < 1 && !empty($target['unit_id'])) {
                $unitId = (int) $target['unit_id'];
            }
            if ($targetLabel === '') {
                $targetLabel = (string) ($target['label'] ?? '');
            }
        }
        if ($unitId < 1 && $targetLabel === '' && $motivation === '' && $target === null) {
            Session::flash('error', 'Indiquez un poste, un AAV, une offre, une unité ou une motivation.');

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
        if ($target !== null && (int) ($target['campaign_id'] ?? 0) > 0) {
            try {
                $this->workflow->createCandidacy($tenantId, (int) $target['campaign_id'], $userId, $userId, [
                    'mobility_requested' => true,
                    'requested_billet_id' => $target['billet_id'] ?? null,
                    'notes' => $motivation !== '' ? $motivation : ($target['label'] ?? null),
                ]);
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
            $motivation !== '' ? $motivation : null,
            $target
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
        ?string $motivation,
        ?array $target = null
    ): int {
        $jobRoleId = $target !== null && !empty($target['job_role_id']) ? (int) $target['job_role_id'] : null;
        $extra = [];
        if ($target !== null) {
            $extra = [
                'target_kind' => $target['kind'] ?? null,
                'target_billet_id' => $target['billet_id'] ?? null,
                'target_opening_id' => $target['opening_id'] ?? null,
                'target_campaign_id' => $target['campaign_id'] ?? null,
            ];
        }
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
                $jobRoleId,
                $targetLabel,
                $motivation !== null ? mb_substr($motivation, 0, 2000) : null,
                $userId,
                $extra
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
                $jobRoleId,
                $targetLabel,
                $motivation !== null ? mb_substr($motivation, 0, 2000) : null,
                $userId,
                $extra
            );
        } catch (Throwable) {
            return 0;
        }
    }

    /** @return array<string, mixed>|null */
    private function resolveTarget(int $tenantId, string $ref): ?array
    {
        $ref = trim($ref);
        if ($ref === '') {
            return null;
        }
        try {
            return $this->targets->resolve($tenantId, $ref);
        } catch (Throwable) {
            return null;
        }
    }

    private function billetIdFromRef(int $tenantId, string $ref): ?int
    {
        $target = $this->resolveTarget($tenantId, $ref);
        $id = (int) ($target['billet_id'] ?? 0);

        return $id > 0 ? $id : null;
    }

    private function notesWithTarget(int $tenantId, Request $request): ?string
    {
        $notes = trim((string) $request->input('notes', ''));
        $target = $this->resolveTarget($tenantId, (string) $request->input('target_ref', ''));
        if ($target !== null) {
            $prefix = (string) ($target['label'] ?? '');
            if ($prefix !== '') {
                $notes = $notes !== '' ? $prefix . ' — ' . $notes : $prefix;
            }
        }

        return $notes !== '' ? $notes : null;
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
