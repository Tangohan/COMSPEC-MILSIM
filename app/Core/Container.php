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
        $early = ContainerIntegrations::tryResolve($id);
        if ($early !== null) {
            return $early;
        }

        return match ($id) {
            TenantRepository::class => new TenantRepository(),
            \App\Repositories\SubscriptionPlanRepository::class => new \App\Repositories\SubscriptionPlanRepository(),
            \App\Services\Platform\FeatureGateService::class => new \App\Services\Platform\FeatureGateService(
                self::get(TenantRepository::class),
                self::get(\App\Repositories\SubscriptionPlanRepository::class),
                self::get(UserRepository::class),
                self::get(\App\Repositories\TenantUsageCounterRepository::class),
                self::get(\App\Repositories\PlatformUsageRepository::class),
                self::get(\App\Repositories\CommunityEventRepository::class),
            ),
            \App\Repositories\TenantUsageCounterRepository::class => new \App\Repositories\TenantUsageCounterRepository(),
            \App\Repositories\ReferralRepository::class => new \App\Repositories\ReferralRepository(),
            \App\Repositories\PendingCommunityCreateRepository::class => new \App\Repositories\PendingCommunityCreateRepository(),
            \App\Services\Billing\StripeCheckoutService::class => new \App\Services\Billing\StripeCheckoutService(),
            \App\Services\Moderation\SystemModeratorAccountService::class => new \App\Services\Moderation\SystemModeratorAccountService(
                self::get(UserRepository::class)
            ),
            \App\Services\Community\TenantBootstrapService::class => new \App\Services\Community\TenantBootstrapService(
                self::get(TenantRepository::class),
                self::get(UserRepository::class),
                self::get(\App\Repositories\ReferralRepository::class),
                self::get(\App\Services\Moderation\SystemModeratorAccountService::class)
            ),
            \App\Services\Community\DefaultTenantSessionService::class => new \App\Services\Community\DefaultTenantSessionService(
                self::get(UserRepository::class),
                self::get(AuthService::class),
            ),
            \App\Controllers\Web\CommunityController::class => new \App\Controllers\Web\CommunityController(
                self::get(TenantRepository::class),
                self::get(UserRepository::class),
                self::get(\App\Repositories\UnitRepository::class),
                self::get(AuthService::class),
                self::get(\App\Services\Community\TenantBootstrapService::class),
                self::get(RbacService::class),
                self::get(\App\Repositories\ReferralRepository::class),
                self::get(\App\Repositories\PendingCommunityCreateRepository::class),
                self::get(\App\Services\Billing\StripeCheckoutService::class),
                self::get(\App\Repositories\SubscriptionPlanRepository::class),
                self::get(\App\Services\EmailService::class),
                new \App\Services\Community\CommunityWizardUploadService(),
            ),
            \App\Controllers\Web\JoinController::class => new \App\Controllers\Web\JoinController(
                self::get(TenantRepository::class),
                self::get(AuthService::class),
            ),
            \App\Controllers\Web\ReferralInviteController::class => new \App\Controllers\Web\ReferralInviteController(
                self::get(\App\Repositories\ReferralRepository::class),
            ),
            \App\Services\Community\TenantCommunityProfileService::class => new \App\Services\Community\TenantCommunityProfileService(),
            \App\Controllers\Admin\Organization\OrganizationCommunityController::class => new \App\Controllers\Admin\Organization\OrganizationCommunityController(
                self::get(AuthService::class),
                self::get(TenantRepository::class),
                self::get(\App\Services\Community\TenantCommunityProfileService::class),
            ),
            \App\Repositories\TenantMessageRepository::class => new \App\Repositories\TenantMessageRepository(),
            \App\Controllers\Web\TenantMessagesController::class => new \App\Controllers\Web\TenantMessagesController(
                self::get(AuthService::class),
                self::get(RbacService::class),
                self::get(UserRepository::class),
                self::get(\App\Repositories\TenantMessageRepository::class),
            ),
            \App\Controllers\Api\StripeWebhookController::class => new \App\Controllers\Api\StripeWebhookController(
                self::get(TenantRepository::class),
                self::get(\App\Repositories\ReferralRepository::class),
                self::get(\App\Repositories\PendingCommunityCreateRepository::class),
                self::get(\App\Services\Community\TenantBootstrapService::class),
            ),
            UserRepository::class => new UserRepository(),
            AuthService::class => new AuthService(
                self::get(UserRepository::class),
                self::get(TenantRepository::class)
            ),
            RbacService::class => new RbacService(),
            \App\Repositories\UserProfileRepository::class => new \App\Repositories\UserProfileRepository(),
            \App\Repositories\PasswordResetRepository::class => new \App\Repositories\PasswordResetRepository(),
            \App\Repositories\EmailDeliveryRepository::class => new \App\Repositories\EmailDeliveryRepository(),
            \App\Repositories\EmailTokenRepository::class => new \App\Repositories\EmailTokenRepository(),
            \App\Repositories\LoginAttemptRepository::class => new \App\Repositories\LoginAttemptRepository(),
            \App\Repositories\UserLoginDeviceRepository::class => new \App\Repositories\UserLoginDeviceRepository(),
            \App\Services\EmailTemplateEngine::class => new \App\Services\EmailTemplateEngine(),
            \App\Services\EmailTransportResolver::class => new \App\Services\EmailTransportResolver(),
            \App\Services\EmailService::class => new \App\Services\EmailService(
                self::get(\App\Services\EmailTransportResolver::class),
                self::get(\App\Services\EmailTemplateEngine::class),
                self::get(\App\Repositories\EmailDeliveryRepository::class)
            ),
            \App\Services\Email\GeoIpLookupService::class => new \App\Services\Email\GeoIpLookupService(),
            \App\Services\Auth\LoginSecurityNotificationService::class => new \App\Services\Auth\LoginSecurityNotificationService(
                self::get(\App\Repositories\LoginAttemptRepository::class),
                self::get(\App\Services\EmailService::class),
                self::get(\App\Repositories\EmailDeliveryRepository::class),
                self::get(\App\Repositories\UserLoginDeviceRepository::class),
                self::get(\App\Repositories\EmailTokenRepository::class),
                self::get(UserRepository::class),
                self::get(\App\Services\Email\GeoIpLookupService::class),
                self::get(\App\Repositories\UserNotificationPreferencesRepository::class)
            ),
            \App\Services\Email\SecurityAlertService::class => new \App\Services\Email\SecurityAlertService(
                self::get(\App\Services\EmailService::class)
            ),
            \App\Controllers\Auth\AuthController::class => new \App\Controllers\Auth\AuthController(
                self::get(AuthService::class),
                self::get(RbacService::class),
                self::get(TenantRepository::class),
                self::get(UserRepository::class),
                self::get(\App\Repositories\PasswordResetRepository::class),
                self::get(\App\Services\Audit\AuditService::class),
                self::get(\App\Services\EmailService::class),
                self::get(\App\Services\Auth\LoginSecurityNotificationService::class),
                self::get(\App\Services\Moderation\IndicatorBlocklistService::class)
            ),
            \App\Controllers\Web\RegisterController::class => new \App\Controllers\Web\RegisterController(
                self::get(AuthService::class),
                self::get(TenantRepository::class),
                self::get(UserRepository::class),
                self::get(\App\Repositories\RoleRepository::class),
                self::get(\App\Repositories\PersonnelProfileRepository::class),
                self::get(RbacService::class),
                self::get(\App\Services\Audit\AuditService::class),
                self::get(\App\Services\EmailService::class),
                self::get(\App\Repositories\EmailTokenRepository::class),
                self::get(\App\Services\Moderation\IndicatorBlocklistService::class)
            ),
            \App\Controllers\Web\VerifyEmailController::class => new \App\Controllers\Web\VerifyEmailController(
                self::get(\App\Repositories\EmailTokenRepository::class),
                self::get(UserRepository::class),
                self::get(TenantRepository::class),
                self::get(\App\Services\EmailService::class),
                self::get(\App\Repositories\UserNotificationPreferencesRepository::class)
            ),
            \App\Controllers\Web\SecurityDeviceController::class => new \App\Controllers\Web\SecurityDeviceController(
                self::get(\App\Repositories\EmailTokenRepository::class),
                self::get(UserRepository::class),
                self::get(\App\Services\Audit\AuditService::class)
            ),
            \App\Repositories\ModerationRepository::class => new \App\Repositories\ModerationRepository(),
            \App\Repositories\BlockedIndicatorRepository::class => new \App\Repositories\BlockedIndicatorRepository(),
            \App\Services\Moderation\IndicatorBlocklistService::class => new \App\Services\Moderation\IndicatorBlocklistService(
                self::get(\App\Repositories\BlockedIndicatorRepository::class),
                self::get(UserRepository::class),
                self::get(\App\Services\Audit\AuditService::class)
            ),
            \App\Services\Moderation\ModerationRestrictionResolver::class => new \App\Services\Moderation\ModerationRestrictionResolver(
                self::get(\App\Repositories\ModerationRepository::class)
            ),
            \App\Services\Moderation\ModerationService::class => new \App\Services\Moderation\ModerationService(
                self::get(\App\Repositories\ModerationRepository::class),
                self::get(\App\Services\Audit\AuditService::class),
                self::get(\App\Services\Moderation\IndicatorBlocklistService::class),
                self::get(\App\Repositories\BlockedIndicatorRepository::class)
            ),
            \App\Repositories\CommunityInvitationRepository::class => new \App\Repositories\CommunityInvitationRepository(),
            \App\Repositories\PlatformUsageRepository::class => new \App\Repositories\PlatformUsageRepository(),
            \App\Repositories\CommunityEventRepository::class => new \App\Repositories\CommunityEventRepository(),
            \App\Controllers\Web\InvitationAcceptController::class => new \App\Controllers\Web\InvitationAcceptController(
                self::get(\App\Repositories\CommunityInvitationRepository::class),
                self::get(TenantRepository::class),
                self::get(UserRepository::class),
                self::get(AuthService::class),
                self::get(RbacService::class),
                self::get(\App\Services\Audit\AuditService::class),
                self::get(\App\Services\Platform\FeatureGateService::class),
                self::get(\App\Services\EmailService::class),
                self::get(\App\Repositories\UnitRepository::class),
                self::get(\App\Repositories\PersonnelAssignmentRepository::class),
                self::get(\App\Repositories\PersonnelProfileRepository::class),
                self::get(\App\Repositories\PersonnelJobRoleRepository::class),
                self::get(\App\Services\Moderation\IndicatorBlocklistService::class),
                self::get(\App\Repositories\UserNotificationPreferencesRepository::class)
            ),
            \App\Controllers\Admin\Organization\InvitationAdminController::class => new \App\Controllers\Admin\Organization\InvitationAdminController(
                self::get(AuthService::class),
                self::get(TenantRepository::class),
                self::get(\App\Repositories\CommunityInvitationRepository::class),
                self::get(\App\Repositories\RoleRepository::class),
                self::get(\App\Repositories\UnitRepository::class),
                self::get(\App\Repositories\PersonnelJobRoleRepository::class),
                self::get(\App\Services\Platform\FeatureGateService::class),
                self::get(\App\Services\Audit\AuditService::class),
                self::get(\App\Services\EmailService::class)
            ),
            \App\Repositories\TenantDashboardPinRepository::class => new \App\Repositories\TenantDashboardPinRepository(),
            \App\Services\Dashboard\TenantDashboardPinService::class => new \App\Services\Dashboard\TenantDashboardPinService(
                self::get(\App\Repositories\TenantDashboardPinRepository::class),
                self::get(\App\Repositories\DocumentRepository::class),
                self::get(\App\Repositories\DocumentCategoryRepository::class),
                self::get(\App\Repositories\Courrier\CourrierDocumentRepository::class),
                self::get(\App\Services\Documents\DocumentAccessService::class)
            ),
            \App\Services\Dashboard\MemberMissionBriefingService::class => new \App\Services\Dashboard\MemberMissionBriefingService(
                self::get(\App\Repositories\CommunityEventRepository::class),
                self::get(\App\Services\Platform\FeatureGateService::class),
                self::get(\App\Repositories\TrainingEnrollmentRepository::class),
                self::get(\App\Services\Training\TrainingService::class),
            ),
            \App\Controllers\Admin\Organization\DashboardPinsAdminController::class => new \App\Controllers\Admin\Organization\DashboardPinsAdminController(
                self::get(\App\Repositories\TenantDashboardPinRepository::class),
                self::get(\App\Repositories\DocumentRepository::class),
                self::get(\App\Repositories\DocumentCategoryRepository::class),
                self::get(\App\Repositories\Courrier\CourrierDocumentRepository::class),
                self::get(\App\Services\Dashboard\TenantDashboardPinService::class)
            ),
            \App\Controllers\Admin\Organization\ModerationOrganizationController::class => new \App\Controllers\Admin\Organization\ModerationOrganizationController(
                self::get(AuthService::class),
                self::get(\App\Repositories\ModerationRepository::class),
                self::get(\App\Services\Moderation\ModerationService::class),
                self::get(UserRepository::class),
                self::get(\App\Services\Moderation\IndicatorBlocklistService::class),
                self::get(\App\Repositories\BlockedIndicatorRepository::class)
            ),
            \App\Controllers\Admin\System\SystemIndicatorBlocklistController::class => new \App\Controllers\Admin\System\SystemIndicatorBlocklistController(
                self::get(AuthService::class),
                self::get(\App\Services\Moderation\IndicatorBlocklistService::class),
                self::get(\App\Repositories\BlockedIndicatorRepository::class)
            ),
            \App\Controllers\Admin\System\SystemUserLookupApiController::class => new \App\Controllers\Admin\System\SystemUserLookupApiController(
                self::get(UserRepository::class)
            ),
            \App\Controllers\Admin\Organization\OrganizationAnalyticsController::class => new \App\Controllers\Admin\Organization\OrganizationAnalyticsController(
                self::get(\App\Services\Platform\FeatureGateService::class),
                self::get(\App\Repositories\PlatformUsageRepository::class)
            ),
            \App\Services\Attendance\CommunityEventAttendanceService::class => new \App\Services\Attendance\CommunityEventAttendanceService(
                self::get(\App\Repositories\CommunityEventRepository::class),
                self::get(\App\Services\EmailService::class),
                self::get(TenantRepository::class),
                self::get(UserRepository::class),
                self::get(\App\Repositories\UserNotificationPreferencesRepository::class),
                self::get(\App\Repositories\ForumNotificationRepository::class)
            ),
            \App\Controllers\Admin\Organization\CommunityEventsAdminController::class => new \App\Controllers\Admin\Organization\CommunityEventsAdminController(
                self::get(\App\Repositories\CommunityEventRepository::class),
                self::get(UserRepository::class),
                self::get(AuthService::class),
                self::get(\App\Services\Platform\FeatureGateService::class),
                self::get(\App\Services\Attendance\CommunityEventAttendanceService::class)
            ),
            \App\Controllers\Web\CommunityEventsController::class => new \App\Controllers\Web\CommunityEventsController(
                self::get(\App\Repositories\CommunityEventRepository::class),
                self::get(AuthService::class),
                self::get(\App\Services\Platform\FeatureGateService::class),
                self::get(\App\Services\Attendance\CommunityEventAttendanceService::class)
            ),
            \App\Controllers\Web\PointageController::class => new \App\Controllers\Web\PointageController(
                self::get(\App\Repositories\CommunityEventRepository::class),
                self::get(AuthService::class),
                self::get(\App\Services\Platform\FeatureGateService::class),
                self::get(\App\Services\Attendance\CommunityEventAttendanceService::class)
            ),
            \App\Services\Profile\RecruitmentPresetPayloadService::class => new \App\Services\Profile\RecruitmentPresetPayloadService(),
            \App\Controllers\Web\AccountController::class => new \App\Controllers\Web\AccountController(
                self::get(AuthService::class),
                self::get(UserRepository::class),
                self::get(\App\Repositories\UserProfileRepository::class),
                self::get(\App\Repositories\PersonnelProfileRepository::class),
                self::get(\App\Repositories\RecruitmentPresetRepository::class),
                new \App\Services\Profile\RecruitmentPresetPayloadService(),
                self::get(\App\Repositories\UserUiPreferencesRepository::class),
                self::get(\App\Repositories\UserNotificationPreferencesRepository::class),
                self::get(\App\Services\Profile\UserUiPreferencesValidationService::class)
            ),
            \App\Repositories\PersonnelJobRoleRepository::class => new \App\Repositories\PersonnelJobRoleRepository(),
            \App\Controllers\Admin\Organization\PersonnelJobRoleAdminController::class => new \App\Controllers\Admin\Organization\PersonnelJobRoleAdminController(
                self::get(\App\Repositories\PersonnelJobRoleRepository::class),
                self::get(\App\Repositories\PermissionRepository::class),
                self::get(\App\Repositories\PersonnelProfileRepository::class),
                self::get(UserRepository::class),
                self::get(\App\Repositories\PersonnelAssignmentRepository::class),
                self::get(TenantRepository::class)
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
                self::get(\App\Repositories\TrainingEnrollmentRepository::class),
                self::get(\App\Services\Training\TrainingService::class),
                self::get(\App\Services\Personnel\MatriculeService::class),
                self::get(\App\Services\Personnel\PersonnelCompletenessService::class),
                self::get(\App\Repositories\UserProfileDisplaySettingsRepository::class),
                self::get(\App\Repositories\UserProfileRepository::class),
                self::get(\App\Repositories\EnlistmentRepository::class),
                self::get(\App\Repositories\PersonnelJobRoleRepository::class),
                self::get(\App\Repositories\RoleRepository::class),
                self::get(TenantRepository::class)
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
                self::get(\App\Repositories\TrainingCertificateRepository::class)
            ),
            \App\Repositories\PersonnelAdminPanelRepository::class => new \App\Repositories\PersonnelAdminPanelRepository(),
            \App\Repositories\PersonnelAdminDataRepository::class => new \App\Repositories\PersonnelAdminDataRepository(),
            \App\Repositories\UnitRepository::class => new \App\Repositories\UnitRepository(),
            \App\Repositories\EnlistmentRepository::class => new \App\Repositories\EnlistmentRepository(),
            \App\Repositories\EnlistmentCannedMessageRepository::class => new \App\Repositories\EnlistmentCannedMessageRepository(),
            \App\Repositories\RecruitmentPresetRepository::class => new \App\Repositories\RecruitmentPresetRepository(),
            \App\Controllers\Web\EnlistmentController::class => new \App\Controllers\Web\EnlistmentController(
                self::get(\App\Repositories\EnlistmentRepository::class),
                self::get(TenantRepository::class),
                self::get(AuthService::class),
                self::get(UserRepository::class),
                self::get(\App\Repositories\UserProfileRepository::class),
                self::get(\App\Repositories\RecruitmentPresetRepository::class),
                self::get(\App\Services\Profile\RecruitmentPresetPayloadService::class),
                self::get(\App\Services\EmailService::class),
                self::get(\App\Services\Moderation\IndicatorBlocklistService::class),
                self::get(\App\Repositories\UserNotificationPreferencesRepository::class),
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
            \App\Services\Moderation\ContentModerationConfig::class => \App\Services\Moderation\ContentModerationConfig::fromEnv(),
            \App\Services\Moderation\HtmlTextExtractor::class => new \App\Services\Moderation\HtmlTextExtractor(),
            \App\Services\Moderation\HeuristicTextModerator::class => new \App\Services\Moderation\HeuristicTextModerator(
                self::get(\App\Services\Moderation\HtmlTextExtractor::class)
            ),
            \App\Services\Moderation\ClamAvScanner::class => new \App\Services\Moderation\ClamAvScanner(
                self::get(\App\Services\Moderation\ContentModerationConfig::class)
            ),
            \App\Services\Moderation\ContentModerationOrchestrator::class => new \App\Services\Moderation\ContentModerationOrchestrator(
                self::get(\App\Services\Moderation\ContentModerationConfig::class),
                self::get(\App\Services\Moderation\ClamAvScanner::class),
                self::get(\App\Services\Moderation\HeuristicTextModerator::class)
            ),
            \App\Repositories\ModerationArtifactRepository::class => new \App\Repositories\ModerationArtifactRepository(),
            \App\Repositories\ModerationDecisionRepository::class => new \App\Repositories\ModerationDecisionRepository(),
            \App\Services\Documents\DocumentUploadService::class => new \App\Services\Documents\DocumentUploadService(
                self::get(\App\Repositories\DocumentRepository::class),
                self::get(\App\Repositories\DocumentVersionRepository::class),
                self::get(\App\Services\Moderation\ContentModerationOrchestrator::class),
                self::get(\App\Repositories\ModerationArtifactRepository::class),
                self::get(\App\Services\Moderation\ContentModerationConfig::class)
            ),
            \App\Services\Documents\DocumentAccessService::class => new \App\Services\Documents\DocumentAccessService(
                self::get(\App\Repositories\DocumentCollaboratorRepository::class),
                self::get(\App\Repositories\DocumentPermissionRepository::class),
                self::get(UserRepository::class)
            ),
            \App\Services\Audit\AuditService::class => new \App\Services\Audit\AuditService(),
            \App\Repositories\RoleRepository::class => new \App\Repositories\RoleRepository(),
            \App\Repositories\PositionRepository::class => new \App\Repositories\PositionRepository(),
            \App\Repositories\RoleSetRepository::class => new \App\Repositories\RoleSetRepository(),
            \App\Repositories\PermissionRepository::class => new \App\Repositories\PermissionRepository(),
            \App\Services\Admin\TenantRolePermissionPresetService::class => new \App\Services\Admin\TenantRolePermissionPresetService(
                self::get(\App\Repositories\PermissionRepository::class)
            ),
            \App\Services\Admin\RolePermissionService::class => new \App\Services\Admin\RolePermissionService(
                self::get(\App\Repositories\RoleRepository::class),
                self::get(\App\Repositories\PermissionRepository::class)
            ),
            \App\Controllers\Admin\System\SystemRoleController::class => new \App\Controllers\Admin\System\SystemRoleController(
                self::get(\App\Services\Admin\RolePermissionService::class),
                self::get(\App\Repositories\PermissionRepository::class),
                self::get(\App\Repositories\RoleRepository::class),
                self::get(\App\Services\Audit\AuditService::class)
            ),
            \App\Repositories\SiteRoleAssignmentRepository::class => new \App\Repositories\SiteRoleAssignmentRepository(),
            \App\Controllers\Admin\System\SystemSiteRoleAssignmentController::class => new \App\Controllers\Admin\System\SystemSiteRoleAssignmentController(
                self::get(\App\Repositories\SiteRoleAssignmentRepository::class),
                self::get(\App\Services\Audit\AuditService::class)
            ),
            \App\Controllers\Admin\Organization\RoleAdminController::class => new \App\Controllers\Admin\Organization\RoleAdminController(
                self::get(\App\Services\Admin\RolePermissionService::class),
                self::get(\App\Repositories\PermissionRepository::class),
                self::get(\App\Repositories\RoleRepository::class),
                self::get(\App\Services\Admin\TenantRolePermissionPresetService::class)
            ),
            \App\Controllers\Admin\Organization\OrganizationPositionsController::class => new \App\Controllers\Admin\Organization\OrganizationPositionsController(
                self::get(\App\Repositories\PositionRepository::class)
            ),
            \App\Controllers\Admin\Organization\RolesFunctionsAdminController::class => new \App\Controllers\Admin\Organization\RolesFunctionsAdminController(
                self::get(\App\Repositories\RoleRepository::class),
                self::get(\App\Repositories\UnitRepository::class),
                self::get(\App\Services\Admin\TenantRolePermissionPresetService::class)
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
                self::get(\App\Repositories\PersonnelExtrasRepository::class),
                self::get(\App\Repositories\RoleRepository::class),
                self::get(\App\Repositories\GradeRepository::class),
                self::get(\App\Repositories\GradeCategoryRepository::class),
                self::get(\App\Services\Admin\ProfileCompletenessService::class),
                self::get(\App\Services\Personnel\PersonnelCompletenessService::class),
                self::get(\App\Services\Admin\AdminAuditService::class),
                self::get(\App\Services\GradeValidationService::class),
                self::get(\App\Services\EmailService::class),
                self::get(TenantRepository::class),
                self::get(\App\Repositories\PasswordResetRepository::class),
                self::get(\App\Repositories\UserNotificationPreferencesRepository::class),
                self::get(\App\Repositories\EmailTokenRepository::class),
                self::get(\App\Repositories\PositionRepository::class),
                self::get(\App\Repositories\RoleSetRepository::class),
                self::get(\App\Services\Moderation\IndicatorBlocklistService::class)
            ),
            \App\Services\Documents\DocumentTrainingReferencesService::class => new \App\Services\Documents\DocumentTrainingReferencesService(
                self::get(\App\Repositories\TrainingResourceRepository::class),
                self::get(\App\Repositories\TrainingRepository::class),
            ),
            \App\Controllers\Web\DocumentsController::class => new \App\Controllers\Web\DocumentsController(
                self::get(\App\Repositories\DocumentRepository::class),
                self::get(\App\Repositories\DocumentCategoryRepository::class),
                self::get(\App\Repositories\DocumentLinkRepository::class),
                self::get(\App\Services\Documents\DocumentAccessService::class),
                self::get(\App\Services\Audit\AuditService::class),
                self::get(\App\Repositories\ModerationArtifactRepository::class),
                self::get(\App\Services\Documents\DocumentTrainingReferencesService::class)
            ),
            \App\Controllers\Web\PortalSearchController::class => new \App\Controllers\Web\PortalSearchController(
                self::get(\App\Repositories\DocumentRepository::class),
                self::get(\App\Services\Documents\DocumentAccessService::class),
                self::get(\App\Repositories\ForumTopicRepository::class),
                self::get(UserRepository::class)
            ),
            \App\Controllers\Web\DocumentationController::class => new \App\Controllers\Web\DocumentationController(),
            \App\Controllers\Web\DossierOperateurController::class => new \App\Controllers\Web\DossierOperateurController(
                self::get(AuthService::class),
                self::get(\App\Repositories\PersonnelProfileRepository::class),
                self::get(\App\Repositories\PersonnelExtrasRepository::class),
                self::get(\App\Repositories\UserProfileRepository::class),
                self::get(\App\Repositories\PersonnelQualificationRepository::class),
                self::get(\App\Repositories\TrainingCertificateRepository::class),
                self::get(\App\Services\Personnel\PersonnelCompletenessService::class)
            ),
            \App\Repositories\ModpackRepository::class => new \App\Repositories\ModpackRepository(),
            \App\Controllers\Web\ModpackController::class => new \App\Controllers\Web\ModpackController(
                self::get(\App\Repositories\ModpackRepository::class)
            ),
            \App\Repositories\TrainingRepository::class => new \App\Repositories\TrainingRepository(),
            \App\Repositories\TrainingCourseRepository::class => new \App\Repositories\TrainingCourseRepository(),
            \App\Repositories\TenantCommunityFeedRepository::class => new \App\Repositories\TenantCommunityFeedRepository(),
            \App\Repositories\TrainingStaffPingRepository::class => new \App\Repositories\TrainingStaffPingRepository(),
            \App\Repositories\TrainingModuleRepository::class => new \App\Repositories\TrainingModuleRepository(),
            \App\Repositories\TrainingLessonRepository::class => new \App\Repositories\TrainingLessonRepository(),
            \App\Repositories\TrainingResourceRepository::class => new \App\Repositories\TrainingResourceRepository(),
            \App\Repositories\TrainingEnrollmentRepository::class => new \App\Repositories\TrainingEnrollmentRepository(),
            \App\Repositories\TrainingProgressRepository::class => new \App\Repositories\TrainingProgressRepository(),
            \App\Repositories\TrainingQuizRepository::class => new \App\Repositories\TrainingQuizRepository(),
            \App\Repositories\TrainingCertificateRepository::class => new \App\Repositories\TrainingCertificateRepository(),
            \App\Repositories\TrainingCertificateTemplateRepository::class => new \App\Repositories\TrainingCertificateTemplateRepository(),
            \App\Services\Training\TrainingCertificateAssetStorageService::class => new \App\Services\Training\TrainingCertificateAssetStorageService(),
            \App\Services\Training\TrainingLessonResourceStorageService::class => new \App\Services\Training\TrainingLessonResourceStorageService(),
            \App\Services\Training\TrainingCertificateShareService::class => new \App\Services\Training\TrainingCertificateShareService(),
            \App\Services\Training\TrainingCertificatePdfService::class => new \App\Services\Training\TrainingCertificatePdfService(
                self::get(\App\Repositories\TrainingCertificateRepository::class),
                self::get(\App\Repositories\TrainingCertificateTemplateRepository::class),
                self::get(UserRepository::class),
                self::get(\App\Services\Training\TrainingCertificateAssetStorageService::class),
            ),
            \App\Services\Training\TrainingAuditService::class => new \App\Services\Training\TrainingAuditService(),
            \App\Services\Training\TrainingService::class => new \App\Services\Training\TrainingService(
                self::get(\App\Repositories\TrainingCourseRepository::class),
                self::get(\App\Repositories\TrainingModuleRepository::class),
                self::get(\App\Repositories\TrainingLessonRepository::class),
                self::get(\App\Repositories\TrainingEnrollmentRepository::class),
                self::get(\App\Repositories\TrainingProgressRepository::class),
                self::get(\App\Repositories\TrainingQuizRepository::class)
            ),
            \App\Services\Training\TrainingCourseExchangeService::class => new \App\Services\Training\TrainingCourseExchangeService(
                self::get(\App\Repositories\TrainingCourseRepository::class),
                self::get(\App\Repositories\TrainingModuleRepository::class),
                self::get(\App\Repositories\TrainingLessonRepository::class),
                self::get(\App\Repositories\TrainingQuizRepository::class),
                self::get(\App\Repositories\TrainingResourceRepository::class),
                self::get(\App\Services\Training\TrainingService::class),
                self::get(\App\Repositories\DocumentRepository::class),
            ),
            \App\Services\Training\TrainingProgressService::class => new \App\Services\Training\TrainingProgressService(
                self::get(\App\Repositories\TrainingEnrollmentRepository::class),
                self::get(\App\Repositories\TrainingProgressRepository::class),
                self::get(\App\Repositories\TrainingModuleRepository::class),
                self::get(\App\Repositories\TrainingLessonRepository::class),
                self::get(\App\Repositories\TrainingQuizRepository::class),
                self::get(\App\Services\Training\TrainingService::class),
                self::get(\App\Services\Training\TrainingAuditService::class),
                self::get(\App\Services\EmailService::class),
                self::get(TenantRepository::class),
                self::get(UserRepository::class),
                self::get(\App\Repositories\TrainingCourseRepository::class),
                self::get(\App\Services\Training\TrainingStaffAlertService::class),
                self::get(\App\Repositories\UserNotificationPreferencesRepository::class)
            ),
            \App\Services\Training\TrainingQuizService::class => new \App\Services\Training\TrainingQuizService(
                self::get(\App\Repositories\TrainingQuizRepository::class),
                self::get(\App\Repositories\TrainingEnrollmentRepository::class),
                self::get(\App\Repositories\TrainingModuleRepository::class),
                self::get(\App\Services\Training\TrainingAuditService::class),
                self::get(\App\Services\Training\TrainingProgressService::class)
            ),
            \App\Services\Training\TrainingCertificateService::class => new \App\Services\Training\TrainingCertificateService(
                self::get(\App\Repositories\TrainingCertificateRepository::class),
                self::get(\App\Repositories\TrainingEnrollmentRepository::class),
                self::get(\App\Repositories\TrainingCourseRepository::class),
                self::get(\App\Services\Training\TrainingProgressService::class),
                self::get(\App\Services\Training\TrainingAuditService::class),
                self::get(\App\Services\Training\TrainingCertificatePdfService::class),
            ),
            \App\Services\Training\TrainingEnrollmentPolicyService::class => new \App\Services\Training\TrainingEnrollmentPolicyService(
                self::get(\App\Repositories\TrainingEnrollmentRepository::class),
                self::get(UserRepository::class),
                self::get(\App\Repositories\TrainingCourseRepository::class),
            ),
            \App\Services\Training\TrainingStaffAlertService::class => new \App\Services\Training\TrainingStaffAlertService(
                self::get(\App\Repositories\TenantCommunityFeedRepository::class),
                self::get(\App\Repositories\TrainingStaffPingRepository::class),
                self::get(\App\Services\EmailService::class),
                self::get(UserRepository::class),
                self::get(TenantRepository::class),
                self::get(\App\Services\Training\TrainingEnrollmentPolicyService::class),
                self::get(\App\Repositories\TrainingCourseRepository::class),
                self::get(\App\Repositories\UserNotificationPreferencesRepository::class)
            ),
            \App\Repositories\TrainingCourseLmsSocialRepository::class => new \App\Repositories\TrainingCourseLmsSocialRepository(),
            \App\Services\Training\TrainingAssignmentService::class => new \App\Services\Training\TrainingAssignmentService(
                self::get(\App\Repositories\TrainingEnrollmentRepository::class),
                self::get(\App\Repositories\TrainingCourseRepository::class),
                self::get(UserRepository::class),
                self::get(\App\Services\Training\TrainingAuditService::class),
                self::get(\App\Services\Training\TrainingEnrollmentPolicyService::class),
                self::get(\App\Services\EmailService::class),
                self::get(TenantRepository::class),
                self::get(\App\Services\Training\TrainingStaffAlertService::class),
                self::get(\App\Repositories\UserNotificationPreferencesRepository::class)
            ),
            \App\Services\Training\TrainingCourseSessionNotificationService::class => new \App\Services\Training\TrainingCourseSessionNotificationService(
                self::get(\App\Services\EmailService::class),
                self::get(\App\Repositories\TrainingEnrollmentRepository::class),
                self::get(\App\Repositories\TrainingCourseRepository::class),
                self::get(TenantRepository::class),
                self::get(\App\Repositories\UserNotificationPreferencesRepository::class)
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
                self::get(\App\Repositories\TrainingResourceRepository::class),
                self::get(\App\Services\Training\TrainingQuizService::class),
                self::get(\App\Services\Platform\FeatureGateService::class),
                self::get(\App\Services\Training\TrainingAssignmentService::class),
                self::get(\App\Services\Training\TrainingEnrollmentPolicyService::class),
                self::get(\App\Repositories\TrainingCourseLmsSocialRepository::class),
                self::get(\App\Repositories\TrainingCourseRepository::class),
                self::get(\App\Services\Training\TrainingStaffAlertService::class),
                self::get(\App\Repositories\TrainingQuizRepository::class),
                self::get(\App\Services\Training\TrainingCertificateShareService::class),
                self::get(\App\Services\Training\TrainingAuditService::class),
                self::get(\App\Repositories\PersonnelProfileRepository::class),
                self::get(\App\Repositories\PersonnelAssignmentRepository::class),
                self::get(UserRepository::class),
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
                self::get(UserRepository::class),
                self::get(\App\Services\Platform\FeatureGateService::class)
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
                self::get(\App\Repositories\ForumBannedWordRepository::class),
                self::get(\App\Repositories\ForumBlacklistedDomainRepository::class)
            ),
            \App\Controllers\Admin\ForumCategoriesApiController::class => new \App\Controllers\Admin\ForumCategoriesApiController(
                self::get(\App\Repositories\ForumCategoryRepository::class),
                self::get(TenantRepository::class),
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
                self::get(\App\Repositories\PersonnelAdminPanelRepository::class),
                self::get(TenantRepository::class),
                self::get(\App\Repositories\EnlistmentRepository::class),
                self::get(UserRepository::class),
                self::get(\App\Repositories\RoleRepository::class),
                self::get(\App\Repositories\PersonnelAssignmentRepository::class),
                self::get(\App\Repositories\PersonnelProfileRepository::class)
            ),
            \App\Services\Recruitment\EnlistmentAcceptanceProvisioningService::class => new \App\Services\Recruitment\EnlistmentAcceptanceProvisioningService(
                self::get(\App\Repositories\EnlistmentRepository::class),
                self::get(UserRepository::class),
                self::get(\App\Repositories\RoleRepository::class),
                self::get(TenantRepository::class),
                self::get(\App\Repositories\PersonnelProfileRepository::class),
                self::get(\App\Repositories\PasswordResetRepository::class),
                self::get(\App\Services\EmailService::class),
                self::get(\App\Services\Platform\FeatureGateService::class),
                self::get(\App\Services\Admin\AdminAuditService::class),
                self::get(\App\Repositories\UserNotificationPreferencesRepository::class)
            ),
            \App\Controllers\Admin\AdminRecruitmentsController::class => new \App\Controllers\Admin\AdminRecruitmentsController(
                self::get(\App\Repositories\EnlistmentRepository::class),
                self::get(\App\Repositories\EnlistmentCannedMessageRepository::class),
                self::get(\App\Services\Recruitment\EnlistmentAcceptanceProvisioningService::class)
            ),
            \App\Controllers\Admin\AdminTrainingController::class => new \App\Controllers\Admin\AdminTrainingController(
                self::get(\App\Repositories\TrainingCourseRepository::class),
                self::get(\App\Repositories\TrainingEnrollmentRepository::class),
                self::get(\App\Repositories\TrainingCertificateRepository::class),
                self::get(\App\Repositories\TrainingCertificateTemplateRepository::class),
                self::get(\App\Services\Training\TrainingCertificateAssetStorageService::class),
                self::get(\App\Services\Training\TrainingAssignmentService::class),
                self::get(\App\Services\Training\TrainingAuditService::class),
                self::get(TenantRepository::class),
                self::get(\App\Services\Training\TrainingProgressService::class),
                self::get(\App\Services\EmailService::class),
                self::get(UserRepository::class),
                self::get(\App\Services\Training\TrainingEnrollmentPolicyService::class),
                self::get(\App\Services\Training\TrainingCourseExchangeService::class),
                self::get(\App\Services\Platform\FeatureGateService::class),
                self::get(\App\Repositories\UserNotificationPreferencesRepository::class)
            ),
            \App\Controllers\Admin\AdminTrainingStudioController::class => new \App\Controllers\Admin\AdminTrainingStudioController(
                self::get(\App\Repositories\TrainingCourseRepository::class),
                self::get(\App\Repositories\TrainingModuleRepository::class),
                self::get(\App\Repositories\TrainingLessonRepository::class),
                self::get(\App\Services\Training\TrainingService::class),
                self::get(\App\Services\Training\TrainingAuditService::class),
                self::get(\App\Services\Platform\FeatureGateService::class),
                self::get(TenantRepository::class),
                self::get(\App\Repositories\RoleRepository::class),
                self::get(\App\Repositories\GradeRepository::class),
                self::get(\App\Repositories\TrainingCourseLmsSocialRepository::class),
                self::get(\App\Repositories\TrainingResourceRepository::class),
                self::get(UserRepository::class),
                self::get(\App\Services\Training\TrainingCourseSessionNotificationService::class),
                self::get(\App\Repositories\DocumentRepository::class),
                self::get(\App\Services\Training\TrainingLessonResourceStorageService::class),
            ),
            \App\Controllers\Admin\AdminTrainingStudioExchangeController::class => new \App\Controllers\Admin\AdminTrainingStudioExchangeController(
                self::get(\App\Services\Training\TrainingCourseExchangeService::class),
                self::get(\App\Repositories\TrainingCourseRepository::class),
                self::get(\App\Services\Training\TrainingAuditService::class),
                self::get(\App\Services\Platform\FeatureGateService::class),
            ),
            \App\Controllers\Api\TrainingApiController::class => new \App\Controllers\Api\TrainingApiController(
                self::get(\App\Services\Training\TrainingService::class),
                self::get(\App\Services\Training\TrainingProgressService::class),
                self::get(\App\Services\Training\TrainingQuizService::class),
                self::get(\App\Services\Training\TrainingCertificateService::class),
                self::get(\App\Services\Training\TrainingAssignmentService::class),
                self::get(\App\Repositories\TrainingEnrollmentRepository::class),
                self::get(\App\Repositories\TrainingQuizRepository::class),
                self::get(\App\Repositories\TrainingCourseRepository::class),
                self::get(\App\Services\Platform\FeatureGateService::class),
                self::get(\App\Repositories\TrainingResourceRepository::class),
                self::get(\App\Repositories\TrainingLessonRepository::class),
                self::get(\App\Repositories\TrainingModuleRepository::class),
                self::get(\App\Services\Training\TrainingCertificateShareService::class),
                self::get(\App\Repositories\DocumentRepository::class),
                self::get(\App\Services\Documents\DocumentAccessService::class),
                self::get(\App\Repositories\ModerationArtifactRepository::class),
                self::get(\App\Services\Audit\AuditService::class),
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
                self::get(\App\Repositories\TrainingCourseRepository::class),
                self::get(\App\Repositories\UnitRepository::class),
                self::get(UserRepository::class),
                self::get(\App\Services\Documents\DocumentUploadService::class),
                self::get(\App\Services\Audit\AuditService::class),
                self::get(\App\Repositories\RoleRepository::class)
            ),
            \App\Repositories\ForumCategoryRepository::class => new \App\Repositories\ForumCategoryRepository(),
            \App\Repositories\ForumAuthorIdentityRepository::class => new \App\Repositories\ForumAuthorIdentityRepository(),
            \App\Services\Profile\ProfilePublicIdentityService::class => new \App\Services\Profile\ProfilePublicIdentityService(),
            \App\Repositories\UserProfileDisplaySettingsRepository::class => new \App\Repositories\UserProfileDisplaySettingsRepository(),
            \App\Repositories\UserUiPreferencesRepository::class => new \App\Repositories\UserUiPreferencesRepository(),
            \App\Repositories\UserNotificationPreferencesRepository::class => new \App\Repositories\UserNotificationPreferencesRepository(),
            \App\Repositories\TenantBrandingRepository::class => new \App\Repositories\TenantBrandingRepository(),
            \App\Services\Profile\UserUiPreferencesValidationService::class => new \App\Services\Profile\UserUiPreferencesValidationService(),
            \App\Controllers\Api\MePreferencesApiController::class => new \App\Controllers\Api\MePreferencesApiController(
                self::get(AuthService::class),
                self::get(\App\Repositories\UserUiPreferencesRepository::class),
                self::get(\App\Repositories\UserNotificationPreferencesRepository::class),
                self::get(\App\Services\Profile\UserUiPreferencesValidationService::class)
            ),
            \App\Repositories\SiteSettingsRepository::class => new \App\Repositories\SiteSettingsRepository(),
            \App\Repositories\PlatformSettingsRepository::class => new \App\Repositories\PlatformSettingsRepository(),
            \App\Repositories\InterteamMissionRepository::class => new \App\Repositories\InterteamMissionRepository(),
            \App\Controllers\Admin\System\SystemBriefSettingsController::class => new \App\Controllers\Admin\System\SystemBriefSettingsController(
                self::get(\App\Repositories\PlatformSettingsRepository::class),
            ),
            \App\Controllers\Admin\System\SystemSettingsController::class => new \App\Controllers\Admin\System\SystemSettingsController(
                self::get(\App\Repositories\PlatformSettingsRepository::class),
            ),
            \App\Controllers\Admin\PlatformBriefSettingsApiController::class => new \App\Controllers\Admin\PlatformBriefSettingsApiController(
                self::get(\App\Repositories\PlatformSettingsRepository::class),
            ),
            \App\Repositories\PlatformAlertRepository::class => new \App\Repositories\PlatformAlertRepository(),
            \App\Repositories\TenantAlertRepository::class => new \App\Repositories\TenantAlertRepository(),
            \App\Repositories\UserAlertDismissalRepository::class => new \App\Repositories\UserAlertDismissalRepository(),
            \App\Services\Alerts\AccountProfileAlertsBuilder::class => new \App\Services\Alerts\AccountProfileAlertsBuilder(
                self::get(UserRepository::class),
                self::get(\App\Repositories\UserProfileRepository::class),
                self::get(\App\Repositories\PersonnelProfileRepository::class),
                self::get(\App\Repositories\EnlistmentRepository::class),
            ),
            \App\Services\Alerts\AlertPresentationService::class => new \App\Services\Alerts\AlertPresentationService(
                self::get(\App\Repositories\PlatformAlertRepository::class),
                self::get(\App\Repositories\TenantAlertRepository::class),
                self::get(\App\Repositories\UserAlertDismissalRepository::class),
                self::get(TenantRepository::class),
            ),
            \App\Controllers\Admin\System\SystemPlatformAlertsController::class => new \App\Controllers\Admin\System\SystemPlatformAlertsController(
                self::get(\App\Repositories\PlatformAlertRepository::class),
            ),
            \App\Controllers\Admin\Organization\TenantAlertsController::class => new \App\Controllers\Admin\Organization\TenantAlertsController(
                self::get(\App\Repositories\TenantAlertRepository::class),
            ),
            \App\Controllers\Api\AlertDismissController::class => new \App\Controllers\Api\AlertDismissController(
                self::get(\App\Repositories\UserAlertDismissalRepository::class),
            ),
            \App\Repositories\ForumBannedWordRepository::class => new \App\Repositories\ForumBannedWordRepository(),
            \App\Repositories\ForumBlacklistedDomainRepository::class => new \App\Repositories\ForumBlacklistedDomainRepository(),
            \App\Repositories\ForumTopicRepository::class => new \App\Repositories\ForumTopicRepository(),
            \App\Repositories\ForumPostRepository::class => new \App\Repositories\ForumPostRepository(),
            \App\Repositories\ForumReportRepository::class => new \App\Repositories\ForumReportRepository(),
            \App\Repositories\ForumModerationLogRepository::class => new \App\Repositories\ForumModerationLogRepository(),
            \App\Controllers\Web\ForumController::class => new \App\Controllers\Web\ForumController(
                self::get(\App\Repositories\ForumCategoryRepository::class),
                self::get(\App\Repositories\ForumTopicRepository::class),
                self::get(\App\Repositories\ForumPostRepository::class),
                self::get(\App\Repositories\ForumReportRepository::class),
                self::get(\App\Repositories\ModerationArtifactRepository::class),
                self::get(\App\Repositories\ForumAuthorIdentityRepository::class),
                self::get(\App\Services\Profile\ProfilePublicIdentityService::class),
                self::get(\App\Repositories\InterteamMissionRepository::class),
                self::get(TenantRepository::class)
            ),
            \App\Controllers\Web\ForumCoopTopicController::class => new \App\Controllers\Web\ForumCoopTopicController(
                self::get(\App\Repositories\ForumTopicRepository::class),
                self::get(\App\Repositories\ForumPostRepository::class),
                self::get(\App\Repositories\ForumCategoryRepository::class),
                self::get(\App\Services\Profile\ProfilePublicIdentityService::class),
                self::get(\App\Repositories\ForumAuthorIdentityRepository::class),
                self::get(\App\Repositories\ForumVoteRepository::class),
                self::get(\App\Repositories\ForumAttachmentRepository::class),
                self::get(TenantRepository::class),
                self::get(\App\Repositories\InterteamMissionRepository::class)
            ),
            \App\Controllers\Web\InterteamMissionWebController::class => new \App\Controllers\Web\InterteamMissionWebController(
                self::get(\App\Repositories\InterteamMissionRepository::class),
                self::get(TenantRepository::class),
                self::get(\App\Repositories\ForumTopicRepository::class)
            ),
            \App\Controllers\Web\ForumCategoryController::class => new \App\Controllers\Web\ForumCategoryController(
                self::get(\App\Repositories\ForumCategoryRepository::class),
                self::get(\App\Repositories\ForumTopicRepository::class),
                self::get(\App\Repositories\ForumAuthorIdentityRepository::class),
                self::get(\App\Services\Profile\ProfilePublicIdentityService::class),
                self::get(TenantRepository::class)
            ),
            \App\Controllers\Web\ForumTopicController::class => new \App\Controllers\Web\ForumTopicController(
                self::get(\App\Repositories\ForumTopicRepository::class),
                self::get(\App\Repositories\ForumPostRepository::class),
                self::get(\App\Repositories\ForumCategoryRepository::class),
                self::get(\App\Services\Profile\ProfilePublicIdentityService::class),
                self::get(\App\Repositories\ForumAuthorIdentityRepository::class),
                self::get(\App\Repositories\ForumVoteRepository::class),
                self::get(\App\Repositories\ForumAttachmentRepository::class),
                self::get(\App\Repositories\ForumNotificationRepository::class),
                self::get(\App\Repositories\UserForumStatsRepository::class),
                self::get(TenantRepository::class),
                self::get(\App\Services\Forum\ForumPostAttachmentService::class),
                self::get(UserRepository::class),
                self::get(\App\Repositories\RoleRepository::class),
                self::get(\App\Repositories\UserProfileDisplaySettingsRepository::class),
                self::get(\App\Repositories\SiteRoleAssignmentRepository::class)
            ),
            \App\Controllers\Web\ForumNewTopicController::class => new \App\Controllers\Web\ForumNewTopicController(
                self::get(\App\Repositories\ForumCategoryRepository::class),
                self::get(\App\Repositories\ForumTopicRepository::class),
                self::get(\App\Repositories\ForumPostRepository::class),
                self::get(\App\Repositories\UserForumStatsRepository::class),
                self::get(TenantRepository::class)
            ),
            \App\Controllers\Web\ForumModerationController::class => new \App\Controllers\Web\ForumModerationController(
                self::get(\App\Repositories\ForumTopicRepository::class),
                self::get(\App\Repositories\ForumReportRepository::class),
                self::get(\App\Repositories\ForumPostRepository::class),
                self::get(\App\Services\Audit\AuditService::class),
                self::get(\App\Services\Moderation\ModerationService::class),
                self::get(UserRepository::class),
                self::get(\App\Services\Community\CommunityReportNotificationService::class)
            ),
            \App\Controllers\Web\ForumModerationDashboardController::class => new \App\Controllers\Web\ForumModerationDashboardController(
                self::get(\App\Repositories\ForumReportRepository::class),
                self::get(\App\Repositories\ForumModerationLogRepository::class)
            ),
            \App\Controllers\Web\ContentModerationController::class => new \App\Controllers\Web\ContentModerationController(
                self::get(\App\Repositories\ModerationArtifactRepository::class),
                self::get(\App\Repositories\ModerationDecisionRepository::class),
                self::get(\App\Services\Documents\DocumentUploadService::class)
            ),
            \App\Repositories\ForumVoteRepository::class => new \App\Repositories\ForumVoteRepository(),
            \App\Repositories\ForumAttachmentRepository::class => new \App\Repositories\ForumAttachmentRepository(),
            \App\Repositories\UserForumStatsRepository::class => new \App\Repositories\UserForumStatsRepository(),
            \App\Repositories\ForumNotificationRepository::class => new \App\Repositories\ForumNotificationRepository(),
            \App\Services\Forum\ForumModerationEngine::class => new \App\Services\Forum\ForumModerationEngine(
                self::get(\App\Repositories\ForumBannedWordRepository::class),
                self::get(\App\Repositories\ForumBlacklistedDomainRepository::class)
            ),
            \App\Controllers\Api\ForumApiController::class => new \App\Controllers\Api\ForumApiController(
                self::get(\App\Repositories\ForumCategoryRepository::class),
                self::get(\App\Repositories\ForumTopicRepository::class),
                self::get(\App\Repositories\ForumPostRepository::class),
                self::get(\App\Repositories\ForumReportRepository::class),
                self::get(UserRepository::class),
                self::get(\App\Repositories\ForumVoteRepository::class),
                self::get(\App\Repositories\ForumNotificationRepository::class),
                self::get(\App\Services\Forum\ForumPostAttachmentService::class),
                self::get(\App\Repositories\UserForumStatsRepository::class),
                self::get(\App\Repositories\UserProfileDisplaySettingsRepository::class),
                self::get(\App\Services\Community\CommunityReportNotificationService::class),
                self::get(TenantRepository::class)
            ),
            \App\Services\Community\CommunityReportNotificationService::class => new \App\Services\Community\CommunityReportNotificationService(
                self::get(\App\Services\EmailService::class),
                self::get(UserRepository::class),
                self::get(\App\Repositories\UserNotificationPreferencesRepository::class),
                self::get(\App\Repositories\ForumNotificationRepository::class),
                self::get(\App\Repositories\TenantCommunityFeedRepository::class),
                self::get(TenantRepository::class)
            ),
            \App\Services\Community\CommunityReportService::class => new \App\Services\Community\CommunityReportService(
                self::get(\App\Repositories\ForumReportRepository::class),
                self::get(\App\Repositories\TrainingCourseRepository::class),
                self::get(UserRepository::class),
            ),
            \App\Controllers\Api\CommunityReportController::class => new \App\Controllers\Api\CommunityReportController(
                self::get(\App\Services\Community\CommunityReportService::class),
                self::get(\App\Services\Community\CommunityReportNotificationService::class)
            ),
            \App\Controllers\Api\ForumRestController::class => new \App\Controllers\Api\ForumRestController(
                self::get(\App\Repositories\ForumCategoryRepository::class),
                self::get(\App\Repositories\ForumTopicRepository::class),
                self::get(\App\Repositories\ForumPostRepository::class),
                self::get(\App\Repositories\ForumReportRepository::class),
                self::get(\App\Repositories\ForumVoteRepository::class),
                self::get(\App\Services\Profile\ProfilePublicIdentityService::class)
            ),
            \App\Controllers\Api\OrbatApiController::class => new \App\Controllers\Api\OrbatApiController(
                self::get(\App\Repositories\UnitRepository::class),
                self::get(UserRepository::class)
            ),
            \App\Controllers\Api\ForumModerationApiController::class => new \App\Controllers\Api\ForumModerationApiController(
                self::get(\App\Repositories\ForumTopicRepository::class),
                self::get(\App\Repositories\ForumPostRepository::class),
                self::get(\App\Services\Audit\AuditService::class)
            ),
            \App\Controllers\Api\ForumUploadController::class => new \App\Controllers\Api\ForumUploadController(
                self::get(\App\Services\Moderation\ContentModerationOrchestrator::class),
                self::get(\App\Repositories\ModerationArtifactRepository::class),
                self::get(\App\Services\Moderation\ContentModerationConfig::class),
                self::get(TenantRepository::class)
            ),
            \App\Services\Forum\ForumPostAttachmentService::class => new \App\Services\Forum\ForumPostAttachmentService(
                self::get(\App\Repositories\ForumAttachmentRepository::class),
                self::get(\App\Repositories\ModerationArtifactRepository::class)
            ),
            \App\Services\Forum\ExternalLeaveService::class => new \App\Services\Forum\ExternalLeaveService(),
            \App\Controllers\Web\ExternalLeaveController::class => new \App\Controllers\Web\ExternalLeaveController(
                self::get(\App\Services\Forum\ExternalLeaveService::class)
            ),
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
                self::get(\App\Repositories\LaserCodeRepository::class),
                self::get(TenantRepository::class)
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
            \App\Repositories\Courrier\CourrierDocumentNotificationRepository::class => new \App\Repositories\Courrier\CourrierDocumentNotificationRepository(),
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
                self::get(\App\Services\Courrier\DocumentBuilderService::class)
            ),
            \App\Services\Courrier\CourrierSnippetService::class => new \App\Services\Courrier\CourrierSnippetService(),
            \App\Controllers\Courrier\CourrierSnippetController::class => new \App\Controllers\Courrier\CourrierSnippetController(
                self::get(\App\Services\Courrier\CourrierSnippetService::class),
                self::get(\App\Services\Courrier\TemplateRenderService::class),
                self::get(\App\Repositories\Courrier\CourrierDocumentRepository::class)
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
                self::get(\App\Repositories\Courrier\DocumentPresetRepository::class),
                self::get(\App\Repositories\Courrier\CourrierDocumentNotificationRepository::class)
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
                self::get(\App\Repositories\Courrier\DocumentVariablesCatalogRepository::class),
                self::get(\App\Services\Moderation\ContentModerationOrchestrator::class),
                self::get(\App\Repositories\ModerationArtifactRepository::class),
                self::get(\App\Services\Moderation\ContentModerationConfig::class)
            ),
            \App\Controllers\Courrier\CourrierReadController::class => new \App\Controllers\Courrier\CourrierReadController(
                self::get(\App\Repositories\Courrier\CourrierDocumentRepository::class),
                self::get(\App\Services\Courrier\DocumentBuilderService::class),
                self::get(UserRepository::class),
                self::get(\App\Repositories\Courrier\CourrierDocumentNotificationRepository::class)
            ),
            \App\Controllers\Courrier\CourrierNotificationController::class => new \App\Controllers\Courrier\CourrierNotificationController(
                self::get(\App\Repositories\Courrier\CourrierDocumentRepository::class),
                self::get(\App\Repositories\Courrier\CourrierDocumentNotificationRepository::class)
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
