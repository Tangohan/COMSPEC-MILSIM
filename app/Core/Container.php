<?php

declare(strict_types=1);

namespace App\Core;

use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Auth\AuthService;
use App\Services\Rbac\RbacService;

class Container
{
    private static array $instances = [];

    public static function get(string $id): object
    {
        if (!isset(self::$instances[$id])) {
            self::$instances[$id] = self::build($id);
        }
        return self::$instances[$id];
    }

    private static function build(string $id): object
    {
        return match ($id) {
            TenantRepository::class => new TenantRepository(),
            UserRepository::class => new UserRepository(),
            AuthService::class => new AuthService(
                self::get(UserRepository::class),
                self::get(TenantRepository::class)
            ),
            RbacService::class => new RbacService(),
            \App\Repositories\UserProfileRepository::class => new \App\Repositories\UserProfileRepository(),
            \App\Repositories\PasswordResetRepository::class => new \App\Repositories\PasswordResetRepository(),
            \App\Controllers\Auth\AuthController::class => new \App\Controllers\Auth\AuthController(
                self::get(AuthService::class),
                self::get(RbacService::class),
                self::get(TenantRepository::class),
                self::get(UserRepository::class),
                self::get(\App\Repositories\PasswordResetRepository::class)
            ),
            \App\Controllers\Web\AccountController::class => new \App\Controllers\Web\AccountController(
                self::get(AuthService::class),
                self::get(UserRepository::class),
                self::get(\App\Repositories\UserProfileRepository::class),
                self::get(\App\Repositories\PersonnelProfileRepository::class)
            ),
            \App\Controllers\Web\PersonnelController::class => new \App\Controllers\Web\PersonnelController(
                self::get(AuthService::class),
                self::get(UserRepository::class),
                self::get(\App\Repositories\PersonnelExtrasRepository::class),
                self::get(\App\Repositories\PersonnelProfileRepository::class),
                self::get(\App\Repositories\PersonnelQualificationRepository::class),
                self::get(\App\Repositories\PersonnelAssignmentRepository::class),
                self::get(\App\Repositories\PersonnelServiceHistoryRepository::class),
                self::get(\App\Repositories\UnitRepository::class),
                self::get(\App\Repositories\GradeRepository::class),
                self::get(\App\Repositories\PersonnelAdminPanelRepository::class),
                self::get(\App\Repositories\PersonnelAdminDataRepository::class),
                self::get(\App\Repositories\TrainingCertificateRepository::class),
                self::get(\App\Services\Personnel\MatriculeService::class),
                self::get(\App\Services\Personnel\PersonnelCompletenessService::class)
            ),
            \App\Repositories\PersonnelExtrasRepository::class => new \App\Repositories\PersonnelExtrasRepository(),
            \App\Repositories\PersonnelProfileRepository::class => new \App\Repositories\PersonnelProfileRepository(),
            \App\Repositories\PersonnelQualificationRepository::class => new \App\Repositories\PersonnelQualificationRepository(),
            \App\Repositories\PersonnelAssignmentRepository::class => new \App\Repositories\PersonnelAssignmentRepository(),
            \App\Repositories\PersonnelServiceHistoryRepository::class => new \App\Repositories\PersonnelServiceHistoryRepository(),
            \App\Repositories\GradeRepository::class => new \App\Repositories\GradeRepository(),
            \App\Repositories\TenantMatriculeConfigRepository::class => new \App\Repositories\TenantMatriculeConfigRepository(),
            \App\Services\Personnel\MatriculeService::class => new \App\Services\Personnel\MatriculeService(
                self::get(\App\Repositories\TenantMatriculeConfigRepository::class),
                self::get(\App\Repositories\PersonnelExtrasRepository::class),
                self::get(\App\Repositories\PersonnelProfileRepository::class)
            ),
            \App\Services\Personnel\PersonnelCompletenessService::class => new \App\Services\Personnel\PersonnelCompletenessService(
                self::get(\App\Repositories\PersonnelProfileRepository::class),
                self::get(\App\Repositories\PersonnelAssignmentRepository::class),
                self::get(\App\Repositories\PersonnelQualificationRepository::class),
                self::get(\App\Repositories\UnitRepository::class)
            ),
            \App\Repositories\PersonnelAdminPanelRepository::class => new \App\Repositories\PersonnelAdminPanelRepository(),
            \App\Repositories\PersonnelAdminDataRepository::class => new \App\Repositories\PersonnelAdminDataRepository(),
            \App\Repositories\UnitRepository::class => new \App\Repositories\UnitRepository(),
            \App\Repositories\EnlistmentRepository::class => new \App\Repositories\EnlistmentRepository(),
            \App\Controllers\Web\EnlistmentController::class => new \App\Controllers\Web\EnlistmentController(
                self::get(\App\Repositories\EnlistmentRepository::class),
                self::get(TenantRepository::class)
            ),
            \App\Repositories\DocumentRepository::class => new \App\Repositories\DocumentRepository(),
            \App\Repositories\DocumentVersionRepository::class => new \App\Repositories\DocumentVersionRepository(),
            \App\Repositories\DocumentCategoryRepository::class => new \App\Repositories\DocumentCategoryRepository(),
            \App\Repositories\DocumentLinkRepository::class => new \App\Repositories\DocumentLinkRepository(),
            \App\Repositories\DocumentCollaboratorRepository::class => new \App\Repositories\DocumentCollaboratorRepository(),
            \App\Repositories\DocumentPermissionRepository::class => new \App\Repositories\DocumentPermissionRepository(),
            \App\Repositories\DocumentRelationRepository::class => new \App\Repositories\DocumentRelationRepository(),
            \App\Repositories\DocumentAuditRepository::class => new \App\Repositories\DocumentAuditRepository(),
            \App\Repositories\EquipmentClassRepository::class => new \App\Repositories\EquipmentClassRepository(),
            \App\Services\Documents\DocumentUploadService::class => new \App\Services\Documents\DocumentUploadService(
                self::get(\App\Repositories\DocumentRepository::class),
                self::get(\App\Repositories\DocumentVersionRepository::class)
            ),
            \App\Services\Documents\DocumentAccessService::class => new \App\Services\Documents\DocumentAccessService(
                self::get(\App\Repositories\DocumentCollaboratorRepository::class),
                self::get(\App\Repositories\DocumentPermissionRepository::class),
                self::get(UserRepository::class)
            ),
            \App\Services\Audit\AuditService::class => new \App\Services\Audit\AuditService(),
            \App\Repositories\RoleRepository::class => new \App\Repositories\RoleRepository(),
            \App\Repositories\PermissionRepository::class => new \App\Repositories\PermissionRepository(),
            \App\Services\Admin\RolePermissionService::class => new \App\Services\Admin\RolePermissionService(
                self::get(\App\Repositories\RoleRepository::class),
                self::get(\App\Repositories\PermissionRepository::class)
            ),
            \App\Controllers\Admin\System\SystemRoleController::class => new \App\Controllers\Admin\System\SystemRoleController(
                self::get(\App\Services\Admin\RolePermissionService::class),
                self::get(\App\Repositories\PermissionRepository::class)
            ),
            \App\Controllers\Admin\Organization\RoleAdminController::class => new \App\Controllers\Admin\Organization\RoleAdminController(
                self::get(\App\Services\Admin\RolePermissionService::class),
                self::get(\App\Repositories\PermissionRepository::class)
            ),
            \App\Repositories\CategoryRepository::class => new \App\Repositories\CategoryRepository(),
            \App\Controllers\Admin\Organization\CategoryAdminController::class => new \App\Controllers\Admin\Organization\CategoryAdminController(
                self::get(\App\Repositories\CategoryRepository::class)
            ),
            \App\Repositories\GradeCategoryRepository::class => new \App\Repositories\GradeCategoryRepository(),
            \App\Repositories\GradeSystemRepository::class => new \App\Repositories\GradeSystemRepository(),
            \App\Controllers\Admin\Organization\GradeReferentielController::class => new \App\Controllers\Admin\Organization\GradeReferentielController(
                self::get(\App\Repositories\GradeRepository::class),
                self::get(\App\Repositories\GradeCategoryRepository::class),
                self::get(\App\Repositories\GradeSystemRepository::class),
                self::get(\App\Services\GradeDisplayService::class)
            ),
            \App\Controllers\Admin\Organization\GroupAdminController::class => new \App\Controllers\Admin\Organization\GroupAdminController(
                self::get(\App\Repositories\UnitRepository::class),
                self::get(UserRepository::class)
            ),
            \App\Controllers\Admin\Organization\TeamAdminController::class => new \App\Controllers\Admin\Organization\TeamAdminController(
                self::get(\App\Repositories\UnitRepository::class),
                self::get(UserRepository::class)
            ),
            \App\Services\Admin\ProfileCompletenessService::class => new \App\Services\Admin\ProfileCompletenessService(
                self::get(UserRepository::class),
                self::get(\App\Repositories\UserProfileRepository::class),
                self::get(\App\Repositories\PersonnelProfileRepository::class),
                self::get(\App\Repositories\PersonnelAssignmentRepository::class),
                self::get(\App\Repositories\UnitRepository::class)
            ),
            \App\Services\Admin\AdminAuditService::class => new \App\Services\Admin\AdminAuditService(
                self::get(\App\Services\Audit\AuditService::class)
            ),
            \App\Controllers\Admin\Organization\UserAdminController::class => new \App\Controllers\Admin\Organization\UserAdminController(
                self::get(UserRepository::class),
                self::get(\App\Repositories\UserProfileRepository::class),
                self::get(\App\Repositories\PersonnelProfileRepository::class),
                self::get(\App\Repositories\RoleRepository::class),
                self::get(\App\Repositories\GradeRepository::class),
                self::get(\App\Repositories\GradeCategoryRepository::class),
                self::get(\App\Services\Admin\ProfileCompletenessService::class),
                self::get(\App\Services\Admin\AdminAuditService::class),
                self::get(\App\Services\GradeValidationService::class)
            ),
            \App\Controllers\Web\DocumentsController::class => new \App\Controllers\Web\DocumentsController(
                self::get(\App\Repositories\DocumentRepository::class),
                self::get(\App\Repositories\DocumentCategoryRepository::class),
                self::get(\App\Repositories\DocumentLinkRepository::class),
                self::get(\App\Services\Documents\DocumentAccessService::class),
                self::get(\App\Services\Audit\AuditService::class)
            ),
            \App\Repositories\ModpackRepository::class => new \App\Repositories\ModpackRepository(),
            \App\Controllers\Web\ModpackController::class => new \App\Controllers\Web\ModpackController(
                self::get(\App\Repositories\ModpackRepository::class)
            ),
            \App\Repositories\TrainingRepository::class => new \App\Repositories\TrainingRepository(),
            \App\Repositories\TrainingCourseRepository::class => new \App\Repositories\TrainingCourseRepository(),
            \App\Repositories\TrainingModuleRepository::class => new \App\Repositories\TrainingModuleRepository(),
            \App\Repositories\TrainingLessonRepository::class => new \App\Repositories\TrainingLessonRepository(),
            \App\Repositories\TrainingResourceRepository::class => new \App\Repositories\TrainingResourceRepository(),
            \App\Repositories\TrainingEnrollmentRepository::class => new \App\Repositories\TrainingEnrollmentRepository(),
            \App\Repositories\TrainingProgressRepository::class => new \App\Repositories\TrainingProgressRepository(),
            \App\Repositories\TrainingQuizRepository::class => new \App\Repositories\TrainingQuizRepository(),
            \App\Repositories\TrainingCertificateRepository::class => new \App\Repositories\TrainingCertificateRepository(),
            \App\Services\Training\TrainingAuditService::class => new \App\Services\Training\TrainingAuditService(),
            \App\Services\Training\TrainingService::class => new \App\Services\Training\TrainingService(
                self::get(\App\Repositories\TrainingCourseRepository::class),
                self::get(\App\Repositories\TrainingModuleRepository::class),
                self::get(\App\Repositories\TrainingLessonRepository::class),
                self::get(\App\Repositories\TrainingEnrollmentRepository::class),
                self::get(\App\Repositories\TrainingProgressRepository::class)
            ),
            \App\Services\Training\TrainingProgressService::class => new \App\Services\Training\TrainingProgressService(
                self::get(\App\Repositories\TrainingEnrollmentRepository::class),
                self::get(\App\Repositories\TrainingProgressRepository::class),
                self::get(\App\Repositories\TrainingModuleRepository::class),
                self::get(\App\Repositories\TrainingLessonRepository::class),
                self::get(\App\Repositories\TrainingQuizRepository::class),
                self::get(\App\Services\Training\TrainingService::class),
                self::get(\App\Services\Training\TrainingAuditService::class)
            ),
            \App\Services\Training\TrainingQuizService::class => new \App\Services\Training\TrainingQuizService(
                self::get(\App\Repositories\TrainingQuizRepository::class),
                self::get(\App\Repositories\TrainingEnrollmentRepository::class),
                self::get(\App\Repositories\TrainingModuleRepository::class),
                self::get(\App\Services\Training\TrainingAuditService::class)
            ),
            \App\Services\Training\TrainingCertificateService::class => new \App\Services\Training\TrainingCertificateService(
                self::get(\App\Repositories\TrainingCertificateRepository::class),
                self::get(\App\Repositories\TrainingEnrollmentRepository::class),
                self::get(\App\Repositories\TrainingCourseRepository::class),
                self::get(\App\Services\Training\TrainingProgressService::class),
                self::get(\App\Services\Training\TrainingAuditService::class)
            ),
            \App\Services\Training\TrainingAssignmentService::class => new \App\Services\Training\TrainingAssignmentService(
                self::get(\App\Repositories\TrainingEnrollmentRepository::class),
                self::get(\App\Repositories\TrainingCourseRepository::class),
                self::get(UserRepository::class),
                self::get(\App\Services\Training\TrainingAuditService::class)
            ),
            \App\Controllers\Web\TrainingController::class => new \App\Controllers\Web\TrainingController(
                self::get(\App\Repositories\TrainingRepository::class),
                self::get(\App\Repositories\DocumentLinkRepository::class),
                self::get(\App\Repositories\DocumentRepository::class),
                self::get(\App\Services\Training\TrainingService::class),
                self::get(\App\Repositories\TrainingEnrollmentRepository::class),
                self::get(\App\Services\Training\TrainingProgressService::class),
                self::get(\App\Services\Training\TrainingCertificateService::class),
                self::get(\App\Repositories\TrainingLessonRepository::class),
                self::get(\App\Repositories\TrainingResourceRepository::class)
            ),
            \App\Controllers\Web\EquipmentController::class => new \App\Controllers\Web\EquipmentController(
                self::get(\App\Repositories\EquipmentClassRepository::class),
                self::get(\App\Repositories\DocumentLinkRepository::class),
                self::get(\App\Repositories\DocumentRepository::class)
            ),
            \App\Services\Tactical\AtakTokenService::class => new \App\Services\Tactical\AtakTokenService(),
            \App\Repositories\TenantAtakConfigRepository::class => new \App\Repositories\TenantAtakConfigRepository(),
            \App\Repositories\AtakMapRepository::class => new \App\Repositories\AtakMapRepository(),
            \App\Controllers\Web\AtakController::class => new \App\Controllers\Web\AtakController(
                self::get(\App\Services\Tactical\AtakTokenService::class),
                self::get(\App\Repositories\TenantAtakConfigRepository::class),
                self::get(\App\Repositories\AtakMapRepository::class),
                self::get(AuthService::class),
                self::get(\App\Repositories\UserProfileRepository::class),
                self::get(UserRepository::class)
            ),
            \App\Controllers\Admin\AdminUsersController::class => new \App\Controllers\Admin\AdminUsersController(
                self::get(UserRepository::class)
            ),
            \App\Controllers\Admin\AdminUnitsController::class => new \App\Controllers\Admin\AdminUnitsController(
                self::get(\App\Repositories\UnitRepository::class),
                self::get(UserRepository::class)
            ),
            \App\Controllers\Admin\AdminModpackController::class => new \App\Controllers\Admin\AdminModpackController(
                self::get(\App\Repositories\ModpackRepository::class)
            ),
            \App\Controllers\Admin\AdminAtakConfigController::class => new \App\Controllers\Admin\AdminAtakConfigController(
                self::get(\App\Repositories\TenantAtakConfigRepository::class),
                self::get(\App\Repositories\AtakMapRepository::class)
            ),
            \App\Controllers\Admin\AdminAtakModController::class => new \App\Controllers\Admin\AdminAtakModController(),
            \App\Controllers\Admin\AdminForumConfigController::class => new \App\Controllers\Admin\AdminForumConfigController(
                self::get(\App\Repositories\ForumCategoryRepository::class),
                self::get(\App\Repositories\SiteSettingsRepository::class),
                self::get(\App\Repositories\ForumBannedWordRepository::class),
                self::get(\App\Repositories\ForumBlacklistedDomainRepository::class)
            ),
            \App\Controllers\Admin\ForumCategoriesApiController::class => new \App\Controllers\Admin\ForumCategoriesApiController(
                self::get(\App\Repositories\ForumCategoryRepository::class)
            ),
            \App\Controllers\Admin\SiteSettingsApiController::class => new \App\Controllers\Admin\SiteSettingsApiController(
                self::get(\App\Repositories\SiteSettingsRepository::class)
            ),
            \App\Controllers\Admin\ForumModerationAdminApiController::class => new \App\Controllers\Admin\ForumModerationAdminApiController(
                self::get(\App\Repositories\ForumBannedWordRepository::class),
                self::get(\App\Repositories\ForumBlacklistedDomainRepository::class)
            ),
            \App\Controllers\Admin\AdminConfigurationController::class => new \App\Controllers\Admin\AdminConfigurationController(
                self::get(\App\Repositories\UnitRepository::class),
                self::get(\App\Repositories\GradeRepository::class),
                self::get(\App\Repositories\TenantMatriculeConfigRepository::class),
                self::get(\App\Repositories\PersonnelAdminPanelRepository::class)
            ),
            \App\Controllers\Admin\AdminRecruitmentsController::class => new \App\Controllers\Admin\AdminRecruitmentsController(
                self::get(\App\Repositories\EnlistmentRepository::class)
            ),
            \App\Controllers\Admin\AdminTrainingController::class => new \App\Controllers\Admin\AdminTrainingController(
                self::get(\App\Repositories\TrainingCourseRepository::class),
                self::get(\App\Repositories\TrainingEnrollmentRepository::class),
                self::get(\App\Repositories\TrainingCertificateRepository::class),
                self::get(\App\Services\Training\TrainingAssignmentService::class),
                self::get(\App\Services\Training\TrainingAuditService::class)
            ),
            \App\Controllers\Api\TrainingApiController::class => new \App\Controllers\Api\TrainingApiController(
                self::get(\App\Services\Training\TrainingService::class),
                self::get(\App\Services\Training\TrainingProgressService::class),
                self::get(\App\Services\Training\TrainingQuizService::class),
                self::get(\App\Services\Training\TrainingCertificateService::class),
                self::get(\App\Services\Training\TrainingAssignmentService::class),
                self::get(\App\Repositories\TrainingEnrollmentRepository::class),
                self::get(\App\Repositories\TrainingQuizRepository::class),
                self::get(\App\Repositories\TrainingCourseRepository::class)
            ),
            \App\Controllers\Admin\AdminDocumentsController::class => new \App\Controllers\Admin\AdminDocumentsController(
                self::get(\App\Repositories\DocumentRepository::class),
                self::get(\App\Repositories\DocumentCategoryRepository::class),
                self::get(\App\Repositories\DocumentLinkRepository::class),
                self::get(\App\Repositories\DocumentCollaboratorRepository::class),
                self::get(\App\Repositories\DocumentPermissionRepository::class),
                self::get(\App\Repositories\DocumentRelationRepository::class),
                self::get(\App\Repositories\DocumentAuditRepository::class),
                self::get(\App\Services\Documents\DocumentAccessService::class),
                self::get(\App\Repositories\EquipmentClassRepository::class),
                self::get(\App\Repositories\TrainingRepository::class),
                self::get(\App\Repositories\UnitRepository::class),
                self::get(UserRepository::class),
                self::get(\App\Services\Documents\DocumentUploadService::class),
                self::get(\App\Services\Audit\AuditService::class)
            ),
            \App\Repositories\ForumCategoryRepository::class => new \App\Repositories\ForumCategoryRepository(),
            \App\Repositories\SiteSettingsRepository::class => new \App\Repositories\SiteSettingsRepository(),
            \App\Repositories\ForumBannedWordRepository::class => new \App\Repositories\ForumBannedWordRepository(),
            \App\Repositories\ForumBlacklistedDomainRepository::class => new \App\Repositories\ForumBlacklistedDomainRepository(),
            \App\Repositories\ForumTopicRepository::class => new \App\Repositories\ForumTopicRepository(),
            \App\Repositories\ForumPostRepository::class => new \App\Repositories\ForumPostRepository(),
            \App\Repositories\ForumReportRepository::class => new \App\Repositories\ForumReportRepository(),
            \App\Controllers\Web\ForumController::class => new \App\Controllers\Web\ForumController(
                self::get(\App\Repositories\ForumCategoryRepository::class),
                self::get(\App\Repositories\ForumTopicRepository::class),
                self::get(\App\Repositories\ForumPostRepository::class),
                self::get(\App\Repositories\ForumReportRepository::class)
            ),
            \App\Controllers\Web\ForumCategoryController::class => new \App\Controllers\Web\ForumCategoryController(
                self::get(\App\Repositories\ForumCategoryRepository::class),
                self::get(\App\Repositories\ForumTopicRepository::class)
            ),
            \App\Controllers\Web\ForumTopicController::class => new \App\Controllers\Web\ForumTopicController(
                self::get(\App\Repositories\ForumTopicRepository::class),
                self::get(\App\Repositories\ForumPostRepository::class),
                self::get(\App\Repositories\ForumCategoryRepository::class)
            ),
            \App\Controllers\Web\ForumNewTopicController::class => new \App\Controllers\Web\ForumNewTopicController(
                self::get(\App\Repositories\ForumCategoryRepository::class),
                self::get(\App\Repositories\ForumTopicRepository::class),
                self::get(\App\Repositories\ForumPostRepository::class)
            ),
            \App\Controllers\Web\ForumModerationController::class => new \App\Controllers\Web\ForumModerationController(
                self::get(\App\Repositories\ForumTopicRepository::class),
                self::get(\App\Repositories\ForumReportRepository::class)
            ),
            \App\Controllers\Api\ForumApiController::class => new \App\Controllers\Api\ForumApiController(
                self::get(\App\Repositories\ForumCategoryRepository::class),
                self::get(\App\Repositories\ForumTopicRepository::class),
                self::get(\App\Repositories\ForumPostRepository::class),
                self::get(\App\Repositories\ForumReportRepository::class),
                self::get(UserRepository::class)
            ),
            \App\Controllers\Api\ForumModerationApiController::class => new \App\Controllers\Api\ForumModerationApiController(
                self::get(\App\Repositories\ForumTopicRepository::class),
                self::get(\App\Repositories\ForumPostRepository::class)
            ),
            \App\Controllers\Api\ForumUploadController::class => new \App\Controllers\Api\ForumUploadController(),
            \App\Repositories\AtakIntelRepository::class => new \App\Repositories\AtakIntelRepository(),
            \App\Controllers\Api\AtakIntelController::class => new \App\Controllers\Api\AtakIntelController(
                self::get(\App\Repositories\AtakIntelRepository::class)
            ),
            \App\Repositories\AtakDataRepository::class => new \App\Repositories\AtakDataRepository(),
            \App\Repositories\CasNineLineRepository::class => new \App\Repositories\CasNineLineRepository(),
            \App\Repositories\ReconImageRepository::class => new \App\Repositories\ReconImageRepository(),
            \App\Repositories\MapShapeRepository::class => new \App\Repositories\MapShapeRepository(),
            \App\Repositories\LaserCodeRepository::class => new \App\Repositories\LaserCodeRepository(),
            \App\Controllers\Api\AtakApiController::class => new \App\Controllers\Api\AtakApiController(
                self::get(\App\Repositories\AtakDataRepository::class),
                self::get(\App\Repositories\CasNineLineRepository::class),
                self::get(\App\Repositories\ReconImageRepository::class),
                self::get(\App\Repositories\MapShapeRepository::class),
                self::get(\App\Repositories\LaserCodeRepository::class)
            ),
            \App\Repositories\FireUnitRepository::class => new \App\Repositories\FireUnitRepository(),
            \App\Repositories\FireTableRepository::class => new \App\Repositories\FireTableRepository(),
            \App\Services\FireSupport\BallisticCalculatorService::class => new \App\Services\FireSupport\BallisticCalculatorService(
                self::get(\App\Repositories\FireTableRepository::class)
            ),
            \App\Controllers\Api\FireSupportController::class => new \App\Controllers\Api\FireSupportController(
                self::get(\App\Repositories\FireUnitRepository::class),
                self::get(\App\Services\FireSupport\BallisticCalculatorService::class)
            ),
            \App\Repositories\DangerZoneRepository::class => new \App\Repositories\DangerZoneRepository(),
            \App\Services\DangerZone\GeometryService::class => new \App\Services\DangerZone\GeometryService(),
            \App\Services\DangerZone\DangerZoneService::class => new \App\Services\DangerZone\DangerZoneService(
                self::get(\App\Repositories\DangerZoneRepository::class),
                self::get(\App\Services\DangerZone\GeometryService::class)
            ),
            \App\Controllers\Api\DangerZoneController::class => new \App\Controllers\Api\DangerZoneController(
                self::get(\App\Services\DangerZone\DangerZoneService::class)
            ),
            \App\Repositories\AssetLogisticsRepository::class => new \App\Repositories\AssetLogisticsRepository(),
            \App\Services\Logistics\AssetLogisticsEvaluator::class => new \App\Services\Logistics\AssetLogisticsEvaluator(),
            \App\Controllers\Api\LogisticsController::class => new \App\Controllers\Api\LogisticsController(
                self::get(\App\Repositories\AssetLogisticsRepository::class),
                self::get(\App\Services\Logistics\AssetLogisticsEvaluator::class)
            ),
            \App\Repositories\IntelReportRepository::class => new \App\Repositories\IntelReportRepository(),
            \App\Services\Intel\IntelFusionService::class => new \App\Services\Intel\IntelFusionService(
                self::get(\App\Repositories\IntelReportRepository::class)
            ),
            \App\Controllers\Api\IntelController::class => new \App\Controllers\Api\IntelController(
                self::get(\App\Services\Intel\IntelFusionService::class)
            ),
            \App\Repositories\ReplayRepository::class => new \App\Repositories\ReplayRepository(),
            \App\Services\Replay\ReplayService::class => new \App\Services\Replay\ReplayService(
                self::get(\App\Repositories\ReplayRepository::class)
            ),
            \App\Controllers\Api\ReplayController::class => new \App\Controllers\Api\ReplayController(
                self::get(\App\Services\Replay\ReplayService::class)
            ),
            \App\Repositories\IffChallengeRepository::class => new \App\Repositories\IffChallengeRepository(),
            \App\Repositories\IffAssetStatusRepository::class => new \App\Repositories\IffAssetStatusRepository(),
            \App\Services\Iff\IffChallengeService::class => new \App\Services\Iff\IffChallengeService(
                self::get(\App\Repositories\IffChallengeRepository::class)
            ),
            \App\Services\Iff\IffValidationService::class => new \App\Services\Iff\IffValidationService(
                self::get(\App\Repositories\IffAssetStatusRepository::class),
                self::get(\App\Repositories\IffChallengeRepository::class)
            ),
            \App\Controllers\Api\IffController::class => new \App\Controllers\Api\IffController(
                self::get(\App\Services\Iff\IffChallengeService::class),
                self::get(\App\Services\Iff\IffValidationService::class)
            ),
            // Module Courrier (Bureau Courrier / Correspondance Officielle)
            \App\Repositories\Courrier\DocumentPresetRepository::class => new \App\Repositories\Courrier\DocumentPresetRepository(),
            \App\Repositories\Courrier\DocumentTemplateRepository::class => new \App\Repositories\Courrier\DocumentTemplateRepository(),
            \App\Repositories\Courrier\CourrierDocumentRepository::class => new \App\Repositories\Courrier\CourrierDocumentRepository(),
            \App\Repositories\Courrier\DocumentWorkflowRepository::class => new \App\Repositories\Courrier\DocumentWorkflowRepository(),
            \App\Repositories\Courrier\DocumentVariablesCatalogRepository::class => new \App\Repositories\Courrier\DocumentVariablesCatalogRepository(),
            \App\Repositories\Courrier\UserSignatureRepository::class => new \App\Repositories\Courrier\UserSignatureRepository(),
            \App\Services\GradeDisplayService::class => new \App\Services\GradeDisplayService(),
            \App\Services\GradeValidationService::class => new \App\Services\GradeValidationService(
                self::get(\App\Repositories\GradeRepository::class)
            ),
            \App\Services\Courrier\TemplateVariableService::class => new \App\Services\Courrier\TemplateVariableService(
                self::get(UserRepository::class),
                self::get(\App\Repositories\UserProfileRepository::class),
                self::get(\App\Repositories\PersonnelProfileRepository::class),
                self::get(\App\Repositories\GradeRepository::class),
                self::get(\App\Repositories\UnitRepository::class),
                self::get(\App\Repositories\Courrier\DocumentVariablesCatalogRepository::class),
                self::get(\App\Services\GradeDisplayService::class)
            ),
            \App\Services\Courrier\TemplateRenderService::class => new \App\Services\Courrier\TemplateRenderService(
                self::get(\App\Services\Courrier\TemplateVariableService::class)
            ),
            \App\Services\Courrier\DocumentBuilderService::class => new \App\Services\Courrier\DocumentBuilderService(
                self::get(\App\Services\Courrier\TemplateRenderService::class),
                self::get(\App\Repositories\Courrier\DocumentPresetRepository::class),
                self::get(\App\Repositories\Courrier\DocumentTemplateRepository::class)
            ),
            \App\Services\Courrier\DocumentValidationService::class => new \App\Services\Courrier\DocumentValidationService(
                self::get(\App\Services\Courrier\TemplateRenderService::class)
            ),
            \App\Services\Courrier\DocumentWorkflowService::class => new \App\Services\Courrier\DocumentWorkflowService(
                self::get(\App\Repositories\Courrier\CourrierDocumentRepository::class),
                self::get(\App\Repositories\Courrier\DocumentWorkflowRepository::class),
                self::get(\App\Services\Courrier\DocumentValidationService::class)
            ),
            \App\Services\Courrier\DocumentNumberingService::class => new \App\Services\Courrier\DocumentNumberingService(
                self::get(\App\Repositories\Courrier\CourrierDocumentRepository::class)
            ),
            \App\Services\Courrier\DocumentAutoFillService::class => new \App\Services\Courrier\DocumentAutoFillService(
                self::get(\App\Services\Courrier\TemplateVariableService::class),
                self::get(\App\Services\Courrier\DocumentNumberingService::class)
            ),
            \App\Services\Courrier\DocumentExportService::class => new \App\Services\Courrier\DocumentExportService(
                self::get(\App\Services\Courrier\DocumentBuilderService::class),
                self::get(\App\Repositories\Courrier\DocumentPresetRepository::class)
            ),
            \App\Services\Courrier\DocumentSignatureService::class => new \App\Services\Courrier\DocumentSignatureService(
                self::get(\App\Repositories\Courrier\CourrierDocumentRepository::class),
                self::get(\App\Repositories\Courrier\UserSignatureRepository::class)
            ),
            \App\Services\Courrier\DocumentPresetService::class => new \App\Services\Courrier\DocumentPresetService(
                self::get(\App\Repositories\Courrier\DocumentPresetRepository::class)
            ),
            \App\Controllers\Courrier\CourrierDashboardController::class => new \App\Controllers\Courrier\CourrierDashboardController(
                self::get(\App\Repositories\Courrier\CourrierDocumentRepository::class),
                self::get(\App\Repositories\Courrier\DocumentTemplateRepository::class),
                self::get(\App\Repositories\Courrier\DocumentPresetRepository::class)
            ),
            \App\Controllers\Courrier\CourrierEditorController::class => new \App\Controllers\Courrier\CourrierEditorController(
                self::get(\App\Repositories\Courrier\CourrierDocumentRepository::class),
                self::get(\App\Repositories\Courrier\DocumentTemplateRepository::class),
                self::get(\App\Repositories\Courrier\DocumentPresetRepository::class),
                self::get(\App\Services\Courrier\DocumentBuilderService::class),
                self::get(\App\Services\Courrier\DocumentValidationService::class),
                self::get(\App\Services\Courrier\TemplateVariableService::class),
                self::get(\App\Services\Courrier\DocumentAutoFillService::class),
                self::get(\App\Services\Courrier\DocumentWorkflowService::class),
                self::get(\App\Repositories\Courrier\DocumentVariablesCatalogRepository::class)
            ),
            \App\Controllers\Courrier\CourrierReadController::class => new \App\Controllers\Courrier\CourrierReadController(
                self::get(\App\Repositories\Courrier\CourrierDocumentRepository::class),
                self::get(\App\Services\Courrier\DocumentBuilderService::class)
            ),
            \App\Controllers\Courrier\CourrierTemplateController::class => new \App\Controllers\Courrier\CourrierTemplateController(
                self::get(\App\Repositories\Courrier\DocumentTemplateRepository::class),
                self::get(\App\Repositories\Courrier\DocumentPresetRepository::class)
            ),
            \App\Controllers\Courrier\CourrierPresetController::class => new \App\Controllers\Courrier\CourrierPresetController(
                self::get(\App\Services\Courrier\DocumentPresetService::class)
            ),
            \App\Controllers\Courrier\CourrierWorkflowController::class => new \App\Controllers\Courrier\CourrierWorkflowController(
                self::get(\App\Repositories\Courrier\CourrierDocumentRepository::class),
                self::get(\App\Services\Courrier\DocumentWorkflowService::class)
            ),
            \App\Controllers\Courrier\CourrierPdfController::class => new \App\Controllers\Courrier\CourrierPdfController(
                self::get(\App\Repositories\Courrier\CourrierDocumentRepository::class),
                self::get(\App\Services\Courrier\DocumentExportService::class)
            ),
            \App\Controllers\Courrier\CourrierSignatureController::class => new \App\Controllers\Courrier\CourrierSignatureController(
                self::get(\App\Repositories\Courrier\CourrierDocumentRepository::class),
                self::get(\App\Services\Courrier\DocumentSignatureService::class),
                self::get(\App\Repositories\Courrier\UserSignatureRepository::class)
            ),
            default => throw new \InvalidArgumentException("Unknown service: $id"),
        };
    }
}
