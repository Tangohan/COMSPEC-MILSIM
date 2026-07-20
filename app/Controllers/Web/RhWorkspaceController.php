<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\HrCharterRepository;
use App\Repositories\PersonnelAssignmentRepository;
use App\Repositories\PlatformModuleReleaseRepository;
use App\Repositories\UserRepository;
use App\Services\Auth\AuthService;
use App\Services\Personnel\SeniorityDossierInferenceSyncService;
use App\Services\Personnel\SeniorityEnrollmentBootstrapService;
use App\Services\Personnel\SenioritySummaryService;
use App\Services\Platform\FeatureGateService;
use App\Support\PersonnelDossierCompleteness;

final class RhWorkspaceController
{
    public function __construct(
        private AuthService $authService,
        private FeatureGateService $featureGate,
        private HrCharterRepository $hrCharterRepository,
        private SenioritySummaryService $senioritySummaryService,
        private PlatformModuleReleaseRepository $platformModuleReleaseRepository,
        private PersonnelAssignmentRepository $personnelAssignmentRepository,
        private SeniorityEnrollmentBootstrapService $seniorityEnrollmentBootstrapService,
        private SeniorityDossierInferenceSyncService $seniorityDossierInferenceSyncService,
        private UserRepository $userRepository,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if (!$user || $tenantId < 1 || $userId < 1) {
            return Response::redirect(url('login'));
        }

        $trainingAllowed = $this->featureGate->allows($tenantId, 'training');
        $charterReady = $this->hrCharterRepository->schemaReady();
        $charterDoc = $charterReady ? $this->hrCharterRepository->getActiveDocumentForTenant($tenantId) : null;
        $charterAccepted = false;
        if ($charterDoc !== null) {
            $charterAccepted = $this->hrCharterRepository->userHasAcceptedDocument($userId, (int) ($charterDoc['id'] ?? 0));
        }

        $seniorityLines = $this->senioritySummaryService->linesForPersonnelFile($tenantId, $userId);

        $richRows = $this->userRepository->listEffectifsRosterByIds($tenantId, [$userId]);
        $rich = $richRows[0] ?? [];
        $dossierCompleteness = PersonnelDossierCompleteness::evaluate(
            $user,
            $rich,
            !empty($rich['unit_id'])
        );

        $testerCommunities = [];
        $rolloutRows = [];
        if ($this->platformModuleReleaseRepository->schemaReady()) {
            $testerCommunities = $this->platformModuleReleaseRepository->listActiveTesterCommunitiesForUser($userId);
            $rawRows = $this->platformModuleReleaseRepository->listModuleAccessRowsForUserTesterCommunities($userId);
            foreach ($rawRows as $row) {
                $mid = (int) ($row['module_id'] ?? 0);
                $byChannel = $mid > 0
                    ? $this->platformModuleReleaseRepository->findCurrentReleasesByChannelForModule($mid)
                    : [];
                $testRelease = $byChannel['TEST'] ?? null;
                $rolloutRows[] = [
                    'module_name' => (string) ($row['module_name'] ?? ''),
                    'module_description' => $row['module_description'] ?? null,
                    'rule_type' => (string) ($row['rule_type'] ?? ''),
                    'rule_label' => $this->accessRuleLabel((string) ($row['rule_type'] ?? '')),
                    'evaluation_version' => $testRelease['version'] ?? null,
                ];
            }
        }

        $greetingName = trim((string) ($user['display_name'] ?? ''));
        if ($greetingName === '') {
            $greetingName = trim((string) ($user['callsign'] ?? ''));
        }

        return Response::view('layout.main', [
            'title' => 'Espace RH et formations',
            'content' => 'personnel.rh_workspace',
            'rhGreetingName' => $greetingName,
            'rhTrainingAllowed' => $trainingAllowed,
            'rhCharterReady' => $charterReady,
            'rhCharterAccepted' => $charterAccepted,
            'rhSeniorityLines' => $seniorityLines,
            'rhDossierCompleteness' => $dossierCompleteness,
            'rhTesterCommunities' => $testerCommunities,
            'rhRolloutRows' => $rolloutRows,
            'rhWorkspaceCsrf' => Csrf::token(),
        ]);
    }

    public function refreshFromDossier(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if (!$user || $tenantId < 1 || $userId < 1) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Votre session a expiré. Rechargez la page puis réessayez.');

            return Response::redirect(url('personnel/mon-espace-rh'));
        }
        try {
            $this->personnelAssignmentRepository->syncMissingFromUserUnitsWhenPossible($userId);
            $this->seniorityEnrollmentBootstrapService->syncTenureCommunityFromEnrollment($tenantId, $userId, null, false);
            $this->seniorityDossierInferenceSyncService->syncForUser($tenantId, $userId, false);
            Session::flash('success', 'Vos indicateurs ont été mis à jour à partir des informations de votre dossier.');
        } catch (\Throwable) {
            Session::flash('error', 'La mise à jour n’a pas pu aboutir. Réessayez dans quelques instants ou contactez l’encadrement si le problème persiste.');
        }

        return Response::redirect(url('personnel/mon-espace-rh'));
    }

    private function accessRuleLabel(string $ruleType): string
    {
        return match ($ruleType) {
            'allow_community' => 'Accès proposé dans le cadre de votre programme',
            'deny_community' => 'Restriction liée à votre programme',
            default => 'Règle associée à votre programme',
        };
    }
}
