<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\RoleRepository;
use App\Repositories\TrainingCompetencyRepository;
use App\Repositories\UserRepository;
use App\Services\Training\CompetencyUserJourneyService;

final class TrainingCompetencyController
{
    public function __construct(
        private CompetencyUserJourneyService $competencyUserJourneyService,
        private TrainingCompetencyRepository $trainingCompetencyRepository,
        private UserRepository $userRepository,
        private RoleRepository $roleRepository,
    ) {}

    public function commandCenter(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $actorUserId = (int) Session::get('user_id');

        if ($request->isPost()) {
            if (!Csrf::validate($request->input('_csrf_token'))) {
                Session::flash('error', 'Session expirée, réessayez.');

                return Response::redirect(url('back-office/ressources/training/competences/commandement'));
            }

            $action = (string) $request->input('action', '');
            $matricesReady = $this->trainingCompetencyRepository->competencyMatricesSchemaAvailable();
            if ($action === 'create_matrix') {
                if (!$matricesReady) {
                    Session::flash('error', 'Le pilotage par matrices n’est pas disponible tant que les migrations n’ont pas été exécutées.');
                } else {
                    $roleIds = array_map('intval', (array) $request->input('auto_role_ids', []));
                    $minCompleted = max(0, (int) $request->input('auto_min_completed', 0));
                    $name = trim((string) $request->input('matrix_name', ''));
                    if ($name === '') {
                        Session::flash('error', 'Nom de matrice requis.');
                    } else {
                        $matrixId = $this->trainingCompetencyRepository->saveMatrix(
                            $tenantId,
                            $actorUserId,
                            $name,
                            (string) $request->input('matrix_description', ''),
                            [
                                'role_ids_any' => $roleIds,
                                'min_completed_courses' => $minCompleted,
                            ]
                        );
                        if ($matrixId < 1) {
                            Session::flash('error', 'Création de matrice impossible pour le moment.');
                        } else {
                            Session::flash('success', 'Matrice créée (#' . $matrixId . ').');
                        }
                    }
                }
            } elseif ($action === 'assign_matrix') {
                if (!$matricesReady) {
                    Session::flash('error', 'Assignation impossible : tables de pilotage absentes. Lancez les migrations puis réessayez.');
                } else {
                    $matrixId = (int) $request->input('matrix_id', 0);
                    $userIds = array_map('intval', (array) $request->input('user_ids', []));
                    $count = $this->trainingCompetencyRepository->assignMatrixToUsers($tenantId, $matrixId, $actorUserId, $userIds, 'manual');
                    Session::flash('success', $count . ' assignation(s) matrice ajoutée(s).');
                }
            } elseif ($action === 'auto_detect') {
                if (!$matricesReady) {
                    Session::flash('error', 'Détection automatique indisponible tant que les migrations n’ont pas été exécutées.');
                } else {
                    $matrixId = (int) $request->input('matrix_id', 0);
                    $matrix = $this->trainingCompetencyRepository->findMatrix($tenantId, $matrixId);
                    if ($matrix === null) {
                        Session::flash('error', 'Matrice introuvable.');
                    } else {
                        $rules = json_decode((string) ($matrix['auto_detect_rules_json'] ?? '{}'), true);
                        $candidateIds = $this->trainingCompetencyRepository->autoDetectCandidateUserIds($tenantId, is_array($rules) ? $rules : []);
                        $count = $this->trainingCompetencyRepository->assignMatrixToUsers($tenantId, $matrixId, $actorUserId, $candidateIds, 'auto_detect');
                        Session::flash('success', sprintf('Détection automatique exécutée (%d candidat(s), %d nouvelle(s) assignation(s)).', count($candidateIds), $count));
                    }
                }
            }

            return Response::redirect(url('back-office/ressources/training/competences/commandement'));
        }

        $matrices = $this->trainingCompetencyRepository->listMatrices($tenantId);
        $users = $this->trainingCompetencyRepository->listTenantUsers($tenantId);
        $roles = $this->roleRepository->forTenantOrganization($tenantId);

        return Response::view('layout.main', [
            'title' => 'Commandement — Compétences',
            'content' => 'admin.training.competency_command',
            'trainingAdminNav' => 'dashboard',
            'competencyMatrices' => $matrices,
            'competencyUsers' => $users,
            'competencyRoles' => $roles,
            'competencySchemaAvailable' => $this->trainingCompetencyRepository->competencySchemaAvailable(),
        ]);
    }

    public function instructorCenter(Request $request, array $params = []): Response
    {
        return Response::view('layout.main', [
            'title' => 'Instructeur — Validation',
            'content' => 'admin.training.competency_instructor',
            'trainingAdminNav' => 'dashboard',
        ]);
    }

    public function trainerCenter(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $actorUserId = (int) Session::get('user_id');
        if ($request->isPost()) {
            if (!Csrf::validate($request->input('_csrf_token'))) {
                Session::flash('error', 'Session expirée, réessayez.');

                return Response::redirect(url('back-office/ressources/training/competences/formateur'));
            }

            $action = (string) $request->input('action', '');
            $trainerSchemaReady = $this->trainingCompetencyRepository->competencyTrainerRolesSchemaAvailable();
            if ($action === 'pick_trainer_roles') {
                if (!$trainerSchemaReady) {
                    Session::flash('error', 'Enregistrement impossible : exécutez d’abord les migrations compétences.');
                } else {
                    $picked = array_map('intval', (array) $request->input('trainer_role_ids', []));
                    $this->trainingCompetencyRepository->saveTrainerRolePicking($tenantId, $picked, $actorUserId);
                    Session::flash('success', 'Rôles formateur mis à jour.');
                }
            } elseif ($action === 'assign_trainer_roles') {
                if (!$trainerSchemaReady) {
                    Session::flash('error', 'Assignation impossible : exécutez d’abord les migrations compétences.');
                } else {
                    $targetUserId = (int) $request->input('target_user_id', 0);
                    if ($targetUserId > 0) {
                        $current = $this->userRepository->listOrganizationRoleIdsForUser($targetUserId);
                        $picked = $this->trainingCompetencyRepository->trainerRoleIds($tenantId);
                        $merged = array_values(array_unique(array_merge($current, $picked)));
                        $this->userRepository->syncOrganizationRoles($targetUserId, $tenantId, $merged, $actorUserId);
                        Session::flash('success', 'Rôles formateur assignés à l’utilisateur.');
                    }
                }
            }

            return Response::redirect(url('back-office/ressources/training/competences/formateur'));
        }

        return Response::view('layout.main', [
            'title' => 'Espace formateur',
            'content' => 'admin.training.competency_trainer',
            'trainingAdminNav' => 'dashboard',
            'trainerRoles' => $this->trainingCompetencyRepository->listTrainerRoles($tenantId),
            'tenantUsers' => $this->trainingCompetencyRepository->listTenantUsers($tenantId),
            'competencySchemaAvailable' => $this->trainingCompetencyRepository->competencySchemaAvailable(),
        ]);
    }

    public function userJourney(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        $competencyJourney = $this->competencyUserJourneyService->buildForUser($tenantId, $userId);

        return Response::view('layout.main', [
            'title' => 'Mon parcours compétences',
            'content' => 'training.competency_journey',
            'competencyJourney' => $competencyJourney,
        ]);
    }
}
