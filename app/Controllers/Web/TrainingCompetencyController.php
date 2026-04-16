<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use InvalidArgumentException;
use App\Repositories\PedagogyRepository;
use App\Repositories\RoleRepository;
use App\Repositories\TrainingCompetencyRepository;
use App\Repositories\UserRepository;
use App\Services\Training\CompetencyUserJourneyService;
use App\Services\Training\PedagogyPathwayService;
use App\Services\Community\TenantSeedHelper;
use App\Services\Training\TenantPedagogyChainGuard;
use App\Services\Training\TenantPedagogyStructureService;

final class TrainingCompetencyController
{
    public function __construct(
        private CompetencyUserJourneyService $competencyUserJourneyService,
        private TrainingCompetencyRepository $trainingCompetencyRepository,
        private UserRepository $userRepository,
        private RoleRepository $roleRepository,
        private PedagogyRepository $pedagogyRepository,
        private TenantPedagogyChainGuard $pedagogyChainGuard,
        private PedagogyPathwayService $pedagogyPathwayService,
        private TenantPedagogyStructureService $pedagogyStructureService,
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
            } elseif ($action === 'quick_chain_referent') {
                $targetUserId = (int) $request->input('target_user_id', 0);
                $userRow = $targetUserId > 0 ? $this->userRepository->findById($targetUserId, $tenantId) : null;
                if ($userRow === null) {
                    Session::flash('error', 'Choisissez un membre de votre organisation.');
                } else {
                    TenantSeedHelper::ensureOperationalRolesForTenant(Database::getPdo(), $tenantId);
                    foreach (['instructor_trainer', 'trainer_of_trainers'] as $chainSlug) {
                        $this->roleRepository->ensureCatalogRoleForTenant($tenantId, $chainSlug);
                    }
                    $extraIds = array_values(array_filter([
                        $this->roleRepository->getIdBySlug($tenantId, 'instructor_trainer'),
                        $this->roleRepository->getIdBySlug($tenantId, 'trainer_of_trainers'),
                    ], static fn (?int $id): bool => $id !== null && $id > 0));
                    if ($extraIds === []) {
                        Session::flash('error', 'La désignation automatique n’est pas disponible : les rôles attendus ne sont pas encore créés pour votre organisation.');
                    } else {
                        try {
                            $current = $this->userRepository->listOrganizationRoleIdsForUser($targetUserId);
                            $merged = array_values(array_unique(array_merge($current, $extraIds)));
                            $this->userRepository->syncOrganizationRoles($targetUserId, $tenantId, $merged, $actorUserId);
                        } catch (InvalidArgumentException) {
                            Session::flash('error', 'Cette combinaison de rôles n’est pas autorisée pour ce membre. Ajustez d’abord son profil depuis l’administration des comptes, ou désignez une autre personne.');

                            return Response::redirect(url('back-office/ressources/training/competences/commandement'));
                        }
                        if ($this->trainingCompetencyRepository->competencyTrainerRolesSchemaAvailable()) {
                            $seedIds = array_values(array_filter([
                                $this->roleRepository->getIdBySlug($tenantId, 'officer'),
                                $this->roleRepository->getIdBySlug($tenantId, 'tenant_admin'),
                            ], static fn (?int $id): bool => $id !== null && $id > 0));
                            if ($seedIds !== []) {
                                if ($this->trainingCompetencyRepository->pedagogyRoleIdsForKind($tenantId, 'instructor_certifier') === []) {
                                    $this->trainingCompetencyRepository->savePedagogyRolePicking($tenantId, 'instructor_certifier', $seedIds, $actorUserId);
                                }
                                if ($this->trainingCompetencyRepository->pedagogyRoleIdsForKind($tenantId, 'trainer_certifier') === []) {
                                    $this->trainingCompetencyRepository->savePedagogyRolePicking($tenantId, 'trainer_certifier', $seedIds, $actorUserId);
                                }
                            }
                        }
                        $this->pedagogyRepository->logAudit($tenantId, $actorUserId, 'quick_chain_referent', 'user', $targetUserId, null);
                        Session::flash('success', 'Référent désigné : les rôles de validation de chaîne ont été ajoutés à ce membre. Vous pouvez affiner les habilitations dans l’espace formateur.');
                    }
                }
            } elseif ($action === 'seed_preset_matrices') {
                if (!$matricesReady) {
                    Session::flash('error', 'Les matrices suggérées ne peuvent pas être créées tant que les migrations n’ont pas été exécutées.');
                } else {
                    $presets = [
                        [
                            'name' => 'Encadrement — cadres et coordination',
                            'description' => 'Profils à responsabilité de direction et de coordination.',
                            'slugs' => ['officer', 'tenant_admin'],
                            'min' => 0,
                        ],
                        [
                            'name' => 'Animation et pédagogie',
                            'description' => 'Membres orientés formation, animation ou conception de parcours.',
                            'slugs' => ['instructor', 'trainer', 'hr'],
                            'min' => 0,
                        ],
                        [
                            'name' => 'Parcours confirmé',
                            'description' => 'Membres ayant déjà validé au moins trois formations.',
                            'slugs' => [],
                            'min' => 3,
                        ],
                    ];
                    $created = 0;
                    foreach ($presets as $preset) {
                        if ($this->trainingCompetencyRepository->matrixNameExists($tenantId, $preset['name'])) {
                            continue;
                        }
                        $roleIds = [];
                        foreach ($preset['slugs'] as $slug) {
                            $rid = $this->roleRepository->getIdBySlug($tenantId, $slug);
                            if ($rid !== null && $rid > 0) {
                                $roleIds[] = $rid;
                            }
                        }
                        $matrixId = $this->trainingCompetencyRepository->saveMatrix(
                            $tenantId,
                            $actorUserId,
                            $preset['name'],
                            $preset['description'],
                            [
                                'role_ids_any' => $roleIds,
                                'min_completed_courses' => $preset['min'],
                            ]
                        );
                        if ($matrixId > 0) {
                            ++$created;
                        }
                    }
                    $this->pedagogyRepository->logAudit($tenantId, $actorUserId, 'seed_preset_matrices', 'tenant', $tenantId, ['created' => $created]);
                    Session::flash(
                        'success',
                        $created > 0
                            ? sprintf('%d matrice(s) suggérée(s) ont été ajoutée(s).', $created)
                            : 'Les matrices suggérées existaient déjà ; aucune nouvelle création.'
                    );
                }
            } elseif ($action === 'ensure_org_sections') {
                $this->pedagogyStructureService->ensureMandatorySectionsForTenant($tenantId);
                $this->pedagogyRepository->logAudit($tenantId, $actorUserId, 'ensure_org_sections', 'tenant', $tenantId, null);
                Session::flash('success', 'Structure minimale vérifiée ou complétée.');
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
            'competencyMatricesSchemaReady' => $this->trainingCompetencyRepository->competencyMatricesSchemaAvailable(),
            'competencyTrainerSchemaReady' => $this->trainingCompetencyRepository->competencyTrainerRolesSchemaAvailable(),
            'pedagogyChainAssess' => $this->pedagogyChainGuard->assessTenantChain($tenantId),
        ]);
    }

    public function instructorCenter(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');

        return Response::view('layout.main', [
            'title' => 'Instructeur — Validation',
            'content' => 'admin.training.competency_instructor',
            'trainingAdminNav' => 'dashboard',
            'trainerValidationTail' => $this->pedagogyRepository->listTrainerValidationTail($tenantId, 25),
            'pedagogyPathwayCatalog' => PedagogyPathwayService::stageCatalog(),
            'pedagogyPathwayRows' => $this->pedagogyPathwayService->pathwayRowsForUser($tenantId, $userId),
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
                    Session::flash('success', 'Rôles « conception de parcours » mis à jour.');
                }
            } elseif ($action === 'pick_delivery_instructor_roles') {
                if (!$trainerSchemaReady) {
                    Session::flash('error', 'Enregistrement impossible : exécutez d’abord les migrations compétences.');
                } else {
                    $picked = array_map('intval', (array) $request->input('delivery_role_ids', []));
                    $this->trainingCompetencyRepository->savePedagogyRolePicking($tenantId, 'delivery_instructor', $picked, $actorUserId);
                    Session::flash('success', 'Rôles « animation sur le terrain » mis à jour.');
                }
            } elseif ($action === 'pick_instructor_certifier_roles') {
                if (!$trainerSchemaReady) {
                    Session::flash('error', 'Enregistrement impossible : exécutez d’abord les migrations compétences.');
                } else {
                    $picked = array_map('intval', (array) $request->input('instructor_certifier_role_ids', []));
                    $this->trainingCompetencyRepository->savePedagogyRolePicking($tenantId, 'instructor_certifier', $picked, $actorUserId);
                    Session::flash('success', 'Rôles « validation des encadrants » mis à jour.');
                }
            } elseif ($action === 'pick_trainer_certifier_roles') {
                if (!$trainerSchemaReady) {
                    Session::flash('error', 'Enregistrement impossible : exécutez d’abord les migrations compétences.');
                } else {
                    $picked = array_map('intval', (array) $request->input('trainer_certifier_role_ids', []));
                    $this->trainingCompetencyRepository->savePedagogyRolePicking($tenantId, 'trainer_certifier', $picked, $actorUserId);
                    Session::flash('success', 'Rôles « gouvernance des concepteurs » mis à jour.');
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
                        Session::flash('success', 'Rôles de conception assignés à l’utilisateur.');
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
            'deliveryInstructorRoles' => $this->trainingCompetencyRepository->listPedagogyRoleChecklist($tenantId, 'delivery_instructor'),
            'instructorCertifierRoles' => $this->trainingCompetencyRepository->listPedagogyRoleChecklist($tenantId, 'instructor_certifier'),
            'trainerCertifierRoles' => $this->trainingCompetencyRepository->listPedagogyRoleChecklist($tenantId, 'trainer_certifier'),
            'tenantUsers' => $this->trainingCompetencyRepository->listTenantUsers($tenantId),
            'competencySchemaAvailable' => $this->trainingCompetencyRepository->competencySchemaAvailable(),
        ]);
    }

    public function personnelCompetenciesDashboard(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');

        return Response::view('layout.main', [
            'title' => 'Bureau du personnel et des compétences',
            'content' => 'admin.training.competency_personnel',
            'trainingAdminNav' => 'dashboard',
            'pedagogyAuditTail' => $this->pedagogyRepository->listRecentAudit($tenantId, 40),
            'pedagogyPathwayCatalog' => PedagogyPathwayService::stageCatalog(),
            'pedagogyPathwayRows' => $this->pedagogyPathwayService->pathwayRowsForUser($tenantId, $userId),
        ]);
    }

    public function poleFormationDashboard(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');

        return Response::view('layout.main', [
            'title' => 'Pôle formation',
            'content' => 'admin.training.competency_pole',
            'trainingAdminNav' => 'dashboard',
            'pedagogyChainAssess' => $this->pedagogyChainGuard->assessTenantChain($tenantId),
        ]);
    }

    public function validationCertificationDashboard(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');

        return Response::view('layout.main', [
            'title' => 'Validation et certification',
            'content' => 'admin.training.competency_validation',
            'trainingAdminNav' => 'dashboard',
            'trainerValidationTail' => $this->pedagogyRepository->listTrainerValidationTail($tenantId, 40),
            'pedagogyChainAssess' => $this->pedagogyChainGuard->assessTenantChain($tenantId),
        ]);
    }

    public function sectionsDashboard(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');

        return Response::view('layout.main', [
            'title' => 'Sections organisationnelles',
            'content' => 'admin.training.competency_sections',
            'trainingAdminNav' => 'dashboard',
            'pedagogyChainAssess' => $this->pedagogyChainGuard->assessTenantChain($tenantId),
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
