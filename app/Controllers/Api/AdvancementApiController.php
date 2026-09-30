<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AdvancementCampaignRepository;
use App\Repositories\AdvancementCandidacyRepository;
use App\Repositories\GradeDefinitionRepository;
use App\Repositories\GradeFiliereDefinitionRepository;
use App\Services\Auth\AuthService;
use App\Services\Personnel\AdvancementEligibilityService;
use App\Services\Personnel\AdvancementPublicationService;
use App\Support\AdvancementCodes;
use App\Support\Api\ApiResponder;
use DateTimeImmutable;
use RuntimeException;

final class AdvancementApiController
{
    public function __construct(
        private AuthService $auth,
        private GradeDefinitionRepository $grades,
        private GradeFiliereDefinitionRepository $filieres,
        private AdvancementCampaignRepository $campaigns,
        private AdvancementCandidacyRepository $candidacies,
        private AdvancementEligibilityService $eligibility,
        private AdvancementPublicationService $publication,
    ) {
    }

    public function grades(Request $request, array $params = []): Response
    {
        $ctx = $this->ctx($request);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId] = $ctx;

        return ApiResponder::success(['grades' => $this->grades->listForTenant($tenantId, true)]);
    }

    public function filieres(Request $request, array $params = []): Response
    {
        $ctx = $this->ctx($request);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId] = $ctx;

        return ApiResponder::success(['filieres' => $this->filieres->listForTenant($tenantId)]);
    }

    public function campaigns(Request $request, array $params = []): Response
    {
        $ctx = $this->ctx($request);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId] = $ctx;

        return ApiResponder::success(['campaigns' => $this->campaigns->listForTenant($tenantId)]);
    }

    public function createCampaign(Request $request, array $params = []): Response
    {
        $ctx = $this->ctx($request, true);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $userId] = $ctx;
        $gradeId = (int) $request->input('grade_id', 0);
        if ($gradeId < 1) {
            return ApiResponder::error('validation_failed', 'grade_id obligatoire.', 422);
        }
        $id = $this->campaigns->create($tenantId, [
            'grade_id' => $gradeId,
            'filiere_id' => (int) $request->input('filiere_id', 0),
            'year' => (int) $request->input('year', date('Y')),
            'opens_at' => $request->input('opens_at'),
            'closes_at' => $request->input('closes_at'),
            'quota_slots' => $request->input('quota_slots'),
        ], $userId);

        return ApiResponder::success(['id' => $id], 201);
    }

    public function volunteer(Request $request, array $params = []): Response
    {
        $ctx = $this->ctx($request, true);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $userId] = $ctx;
        $campaignId = (int) ($params['id'] ?? $request->input('campaign_id', 0));
        $personnelId = (int) $request->input('personnel_id', $userId);
        $campaign = $this->campaigns->find($tenantId, $campaignId);
        if ($campaign === null || (string) $campaign['status'] !== AdvancementCodes::CAMPAIGN_OPEN) {
            return ApiResponder::error('campaign_closed', 'Campagne non ouverte.', 409);
        }
        if ($this->candidacies->findForCampaignPersonnel($campaignId, $personnelId) !== null) {
            return ApiResponder::error('already_exists', 'Candidature déjà déposée.', 409);
        }
        $eval = $this->eligibility->evaluate($tenantId, $personnelId, (int) $campaign['grade_id']);
        $id = $this->candidacies->create($campaignId, $personnelId, [
            'is_eligible' => $eval['is_eligible'],
            'eligibility_reason' => $eval['eligibility_reason'],
            'mobility_requested' => $request->input('mobility_requested') ? 1 : 0,
            'requested_billet_id' => (int) $request->input('requested_billet_id', 0),
            'notes' => $request->input('notes'),
        ]);

        return ApiResponder::success(['id' => $id, 'eligibility' => $eval], 201);
    }

    public function toCommission(Request $request, array $params = []): Response
    {
        $ctx = $this->ctx($request, true);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId] = $ctx;
        $id = (int) ($params['id'] ?? 0);
        $this->campaigns->setStatus($tenantId, $id, AdvancementCodes::CAMPAIGN_IN_COMMISSION);

        return ApiResponder::success(['status' => AdvancementCodes::CAMPAIGN_IN_COMMISSION]);
    }

    public function publish(Request $request, array $params = []): Response
    {
        $ctx = $this->ctx($request, true);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $userId] = $ctx;
        try {
            $out = $this->publication->publish(
                $tenantId,
                (int) ($params['id'] ?? 0),
                $userId,
                new DateTimeImmutable('today')
            );
        } catch (RuntimeException $e) {
            return ApiResponder::error('publish_failed', $e->getMessage(), 409);
        }

        return ApiResponder::success($out);
    }

    public function eligibility(Request $request, array $params = []): Response
    {
        $ctx = $this->ctx($request);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId] = $ctx;
        $personnelId = (int) $request->query('personnel_id', 0);
        $gradeId = (int) $request->query('grade_id', 0);
        if ($personnelId < 1 || $gradeId < 1) {
            return ApiResponder::error('validation_failed', 'personnel_id et grade_id obligatoires.', 422);
        }

        return ApiResponder::success($this->eligibility->evaluate($tenantId, $personnelId, $gradeId));
    }

    /** @return array{0: int, 1: int}|Response */
    private function ctx(Request $request, bool $needCsrf = false): array|Response
    {
        $user = $this->auth->user();
        if (!$user) {
            return ApiResponder::error('unauthorized', 'Non autorisé.', 401);
        }
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return ApiResponder::error('tenant_missing', 'Communauté non sélectionnée.', 400);
        }
        if ($needCsrf) {
            $token = (string) $request->input('_csrf_token', '');
            if ($token === '') {
                $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
            }
            if (!Csrf::validate($token)) {
                return ApiResponder::error('csrf_invalid', 'Token CSRF invalide.', 403);
            }
        }

        return [$tenantId, (int) ($user['id'] ?? 0)];
    }
}
