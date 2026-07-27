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
            \App\Repositories\PlatformModuleReleaseRepository::class => new \App\Repositories\PlatformModuleReleaseRepository(),
            \App\Repositories\DeploymentCampaignRepository::class => new \App\Repositories\DeploymentCampaignRepository(),
            \App\Services\Platform\DeploymentChannelReleaseService::class => new \App\Services\Platform\DeploymentChannelReleaseService(
                self::get(\App\Repositories\PlatformModuleReleaseRepository::class),
                self::get(\App\Services\Audit\AuditService::class),
            ),
            \App\Services\Platform\DeploymentCampaignProcessor::class => new \App\Services\Platform\DeploymentCampaignProcessor(
                self::get(\App\Repositories\DeploymentCampaignRepository::class),
                self::get(\App\Services\Platform\DeploymentChannelReleaseService::class),
            ),
            \App\Services\Platform\ModuleReleaseAccessResolver::class => new \App\Services\Platform\ModuleReleaseAccessResolver(),
            \App\Services\Platform\PlatformFeatureDeploymentEvaluator::class => new \App\Services\Platform\PlatformFeatureDeploymentEvaluator(
                self::get(\App\Repositories\PlatformModuleReleaseRepository::class),
                self::get(\App\Services\Platform\ModuleReleaseAccessResolver::class),
            ),
            \App\Repositories\HrCharterRepository::class => new \App\Repositories\HrCharterRepository(),
            \App\Repositories\DoctrineRepository::class => new \App\Repositories\DoctrineRepository(),
            \App\Repositories\SeniorityRepository::class => new \App\Repositories\SeniorityRepository(),
            \App\Services\Personnel\SenioritySummaryService::class => new \App\Services\Personnel\SenioritySummaryService(
                self::get(\App\Repositories\SeniorityRepository::class),
                new \App\Services\Personnel\SeniorityEngine(),
            ),
            \App\Services\Personnel\SeniorityEnrollmentBootstrapService::class => new \App\Services\Personnel\SeniorityEnrollmentBootstrapService(
                self::get(\App\Repositories\SeniorityRepository::class),
                new \App\Services\Personnel\SeniorityTenantDefaultsService(self::get(\App\Repositories\SeniorityRepository::class)),
                self::get(\App\Repositories\PersonnelProfileRepository::class),
                self::get(\App\Repositories\PersonnelExtrasRepository::class),
                self::get(\App\Repositories\EnlistmentRepository::class),
                self::get(UserRepository::class),
            ),
            \App\Services\Personnel\SeniorityDossierInferenceSyncService::class => new \App\Services\Personnel\SeniorityDossierInferenceSyncService(
                self::get(\App\Repositories\SeniorityRepository::class),
                new \App\Services\Personnel\SeniorityTenantDefaultsService(self::get(\App\Repositories\SeniorityRepository::class)),
                self::get(\App\Repositories\PersonnelAssignmentRepository::class),
                new \App\Repositories\RoleAssignmentLogRepository(),
                self::get(\App\Repositories\PersonnelOrgHistoryRepository::class),
                new \App\Repositories\AuditLogRepository(),
                self::get(UserRepository::class),
                self::get(\App\Repositories\PersonnelQualificationRepository::class),
                self::get(\App\Repositories\TrainingCertificateRepository::class),
            ),
            \App\Services\Platform\FeatureGateService::class => new \App\Services\Platform\FeatureGateService(
                self::get(TenantRepository::class),
                self::get(\App\Repositories\SubscriptionPlanRepository::class),
                self::get(UserRepository::class),
                self::get(\App\Repositories\TenantUsageCounterRepository::class),
                self::get(\App\Repositories\PlatformUsageRepository::class),
                self::get(\App\Repositories\CommunityEventRepository::class),
                self::get(\App\Repositories\TrainingCourseRepository::class),
                self::get(\App\Services\Platform\PlatformFeatureDeploymentEvaluator::class),
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
            \App\Services\Community\LeaveCommunityService::class => new \App\Services\Community\LeaveCommunityService(
                self::get(UserRepository::class),
                self::get(TenantRepository::class),
                self::get(\App\Repositories\RoleRepository::class),
                self::get(AuthService::class),
                self::get(\App\Services\EmailService::class),
                self::get(\App\Repositories\UserNotificationPreferencesRepository::class),
                self::get(\App\Repositories\TenantCommunityFeedRepository::class),
                self::get(\App\Services\Admin\AdminAuditService::class),
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
                self::get(\App\Repositories\RecruitmentOpeningRepository::class),
                self::get(\App\Repositories\PersonnelJobRoleRepository::class),
                self::get(\App\Services\Analytics\AnalyticsEventService::class),
                self::get(\App\Repositories\CommunityMediaRepository::class),
                self::get(\App\Repositories\TenantBrandingRepository::class),
                self::get(\App\Repositories\CommunityEventRepository::class),
            ),
            \App\Controllers\Web\AnalyticsBeaconController::class => new \App\Controllers\Web\AnalyticsBeaconController(
                self::get(\App\Services\Analytics\AnalyticsEventService::class)
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
            \App\Services\Community\TenantTypeSwitchService::class => new \App\Services\Community\TenantTypeSwitchService(
                self::get(TenantRepository::class),
            ),
            \App\Controllers\Admin\Organization\OrganizationSettingsController::class => new \App\Controllers\Admin\Organization\OrganizationSettingsController(
                self::get(AuthService::class),
                self::get(TenantRepository::class),
                self::get(\App\Repositories\TenantBrandingRepository::class),
                self::get(\App\Services\Integrations\DiscordWebhookService::class),
                self::get(\App\Services\Community\TenantTypeSwitchService::class),
            ),
            \App\Services\Community\TenantInitialSetupService::class => new \App\Services\Community\TenantInitialSetupService(
                self::get(TenantRepository::class),
                self::get(\App\Repositories\TenantBrandingRepository::class),
                self::get(\App\Repositories\RoleRepository::class),
            ),
            \App\Controllers\Admin\Organization\TenantInitialSetupController::class => new \App\Controllers\Admin\Organization\TenantInitialSetupController(
                self::get(AuthService::class),
                self::get(TenantRepository::class),
                self::get(\App\Repositories\TenantBrandingRepository::class),
                self::get(\App\Services\Community\TenantInitialSetupService::class),
                self::get(\App\Services\Admin\RolePermissionService::class),
                self::get(\App\Services\Audit\AuditService::class),
            ),
            \App\Repositories\TenantMessageRepository::class => new \App\Repositories\TenantMessageRepository(),
            \App\Services\Community\TenantInternalMessageNotificationService::class => new \App\Services\Community\TenantInternalMessageNotificationService(
                self::get(\App\Services\EmailService::class),
                self::get(UserRepository::class),
                self::get(\App\Repositories\UserNotificationPreferencesRepository::class),
                self::get(\App\Repositories\TenantMessageRepository::class),
                self::get(TenantRepository::class),
            ),
            \App\Controllers\Web\TenantMessagesController::class => new \App\Controllers\Web\TenantMessagesController(
                self::get(AuthService::class),
                self::get(RbacService::class),
                self::get(UserRepository::class),
                self::get(\App\Repositories\TenantMessageRepository::class),
                self::get(\App\Services\Community\TenantInternalMessageNotificationService::class),
            ),
            \App\Controllers\Api\StripeWebhookController::class => new \App\Controllers\Api\StripeWebhookController(
                self::get(TenantRepository::class),
                self::get(\App\Repositories\ReferralRepository::class),
                self::get(\App\Repositories\PendingCommunityCreateRepository::class),
                self::get(\App\Services\Community\TenantBootstrapService::class),
                self::get(\App\Services\Billing\AtakDonationFulfillmentService::class),
            ),
            \App\Repositories\AtakDonationRepository::class => new \App\Repositories\AtakDonationRepository(),
            \App\Services\Billing\AtakDonationFulfillmentService::class => new \App\Services\Billing\AtakDonationFulfillmentService(
                self::get(\App\Repositories\AtakDonationRepository::class),
                self::get(\App\Repositories\BadgeRepository::class),
                self::get(UserRepository::class),
                self::get(\App\Services\Billing\StripeCheckoutService::class),
            ),
            \App\Controllers\Web\AtakSupportController::class => new \App\Controllers\Web\AtakSupportController(
                self::get(\App\Repositories\AtakDonationRepository::class),
                self::get(\App\Services\Billing\StripeCheckoutService::class),
                self::get(\App\Services\Billing\AtakDonationFulfillmentService::class),
                self::get(UserRepository::class),
            ),
            UserRepository::class => new UserRepository(),
            AuthService::class => new AuthService(
                self::get(UserRepository::class),
                self::get(TenantRepository::class),
                self::get(\App\Services\Personnel\SeniorityEnrollmentBootstrapService::class),
            ),
            RbacService::class => new RbacService(),
            \App\Repositories\UserProfileRepository::class => new \App\Repositories\UserProfileRepository(),
            \App\Repositories\PasswordResetRepository::class => new \App\Repositories\PasswordResetRepository(),
            \App\Repositories\EmailDeliveryRepository::class => new \App\Repositories\EmailDeliveryRepository(),
            \App\Repositories\NewsletterSubscriberRepository::class => new \App\Repositories\NewsletterSubscriberRepository(),
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
            \App\Services\Auth\LoginSecurityOtpService::class => new \App\Services\Auth\LoginSecurityOtpService(
                self::get(UserRepository::class),
                self::get(\App\Repositories\EmailTokenRepository::class),
                self::get(TenantRepository::class),
                self::get(\App\Services\EmailService::class)
            ),
            \App\Services\Email\SecurityAlertService::class => new \App\Services\Email\SecurityAlertService(
                self::get(\App\Services\EmailService::class)
            ),
            \App\Controllers\Auth\AuthController::class => new \App\Controllers\Auth\AuthController(
                self::get(AuthService::class),
                self::get(RbacService::class),
                self::get(TenantRepository::class),
                self::get(UserRepository::class),
                self::get(\App\Repositories\EmailTokenRepository::class),
                self::get(\App\Repositories\PasswordResetRepository::class),
                self::get(\App\Services\Audit\AuditService::class),
                self::get(\App\Services\EmailService::class),
                self::get(\App\Services\Auth\LoginSecurityNotificationService::class),
                self::get(\App\Services\Moderation\IndicatorBlocklistService::class),
                self::get(\App\Services\Auth\LoginSecurityOtpService::class)
            ),
            \App\Controllers\Web\RegisterController::class => new \App\Controllers\Web\RegisterController(
                self::get(AuthService::class),
                self::get(TenantRepository::class),
                self::get(UserRepository::class),
                self::get(\App\Repositories\UserProfileRepository::class),
                self::get(\App\Repositories\RoleRepository::class),
                self::get(\App\Repositories\PersonnelProfileRepository::class),
                self::get(RbacService::class),
                self::get(\App\Services\Audit\AuditService::class),
                self::get(\App\Services\EmailService::class),
                self::get(\App\Repositories\EmailTokenRepository::class),
                self::get(\App\Services\Moderation\IndicatorBlocklistService::class),
                self::get(\App\Services\Steam\SteamWebApiService::class),
                self::get(\App\Services\Account\AccountDeletionService::class),
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
            \App\Repositories\AnalyticsEventRepository::class => new \App\Repositories\AnalyticsEventRepository(),
            \App\Services\Analytics\AnalyticsEventService::class => new \App\Services\Analytics\AnalyticsEventService(
                self::get(\App\Repositories\AnalyticsEventRepository::class)
            ),
            \App\Repositories\TenantAnalyticsRepository::class => new \App\Repositories\TenantAnalyticsRepository(),
            \App\Repositories\CommunityEventRepository::class => new \App\Repositories\CommunityEventRepository(),
            \App\Repositories\CommunityEventSlotRepository::class => new \App\Repositories\CommunityEventSlotRepository(),
            \App\Repositories\CommunityEventSlotAssignmentRepository::class => new \App\Repositories\CommunityEventSlotAssignmentRepository(),
            \App\Repositories\CommunityMediaRepository::class => new \App\Repositories\CommunityMediaRepository(),
            \App\Services\Community\CommunityMediaUploadService::class => new \App\Services\Community\CommunityMediaUploadService(),
            \App\Controllers\Admin\Organization\CommunityMediaAdminController::class => new \App\Controllers\Admin\Organization\CommunityMediaAdminController(
                self::get(\App\Repositories\CommunityMediaRepository::class),
                self::get(\App\Services\Community\CommunityMediaUploadService::class),
                self::get(AuthService::class),
            ),
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
                self::get(\App\Repositories\TrainingProgressRepository::class),
                self::get(\App\Repositories\TrainingCourseLmsSocialRepository::class),
                self::get(UserRepository::class),
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
                self::get(UserRepository::class)
            ),
            \App\Controllers\Admin\Organization\OrganizationSecurityIndicatorsController::class => new \App\Controllers\Admin\Organization\OrganizationSecurityIndicatorsController(
                self::get(AuthService::class),
                self::get(\App\Repositories\BlockedIndicatorRepository::class),
                self::get(\App\Services\Moderation\IndicatorBlocklistService::class)
            ),
            \App\Controllers\Admin\System\SystemIndicatorBlocklistController::class => new \App\Controllers\Admin\System\SystemIndicatorBlocklistController(
                self::get(AuthService::class),
                self::get(\App\Services\Moderation\IndicatorBlocklistService::class),
                self::get(\App\Repositories\BlockedIndicatorRepository::class)
            ),
            \App\Repositories\DemoNdaVisitRepository::class => new \App\Repositories\DemoNdaVisitRepository(),
            \App\Services\DemoNda\DemoNdaGateService::class => new \App\Services\DemoNda\DemoNdaGateService(
                self::get(\App\Repositories\DemoNdaVisitRepository::class),
                self::get(\App\Repositories\PlatformSettingsRepository::class)
            ),
            \App\Middleware\DemoNdaGateMiddleware::class => new \App\Middleware\DemoNdaGateMiddleware(
                self::get(\App\Services\DemoNda\DemoNdaGateService::class)
            ),
            \App\Controllers\Web\DemoNdaController::class => new \App\Controllers\Web\DemoNdaController(
                self::get(\App\Services\DemoNda\DemoNdaGateService::class)
            ),
            \App\Controllers\Admin\System\SystemDemoNdaController::class => new \App\Controllers\Admin\System\SystemDemoNdaController(
                self::get(\App\Services\DemoNda\DemoNdaGateService::class)
            ),
            \App\Repositories\CronJobRunRepository::class => new \App\Repositories\CronJobRunRepository(),
            \App\Services\Cron\Jobs\TrainingExpireCronJob::class => new \App\Services\Cron\Jobs\TrainingExpireCronJob(
                \App\Core\Database::getPdo()
            ),
            \App\Services\Cron\Jobs\ModerationQuarantineExpireCronJob::class => new \App\Services\Cron\Jobs\ModerationQuarantineExpireCronJob(
                \App\Core\Database::getPdo(),
                self::get(UserRepository::class),
                self::get(\App\Repositories\ModerationDecisionRepository::class),
                dirname(__DIR__, 2)
            ),
            \App\Services\Cron\Jobs\RecruitmentRetroRemindersCronJob::class => new \App\Services\Cron\Jobs\RecruitmentRetroRemindersCronJob(
                self::get(\App\Repositories\EnlistmentRecruitmentEngagementRepository::class),
                self::get(\App\Repositories\EnlistmentRepository::class),
                self::get(TenantRepository::class),
                self::get(UserRepository::class),
                self::get(\App\Services\EmailService::class),
                self::get(\App\Repositories\TenantCommunityFeedRepository::class),
                self::get(\App\Repositories\CronJobRunRepository::class)
            ),
            \App\Services\Cron\Jobs\HrWeeklyDigestCronJob::class => new \App\Services\Cron\Jobs\HrWeeklyDigestCronJob(
                self::get(TenantRepository::class),
                self::get(UserRepository::class),
                self::get(\App\Repositories\PersonnelProfileRepository::class),
                self::get(\App\Repositories\ElevationRequestRepository::class),
                self::get(\App\Services\Effectifs\EffectifsStaffAlertService::class),
                self::get(\App\Repositories\UserNotificationPreferencesRepository::class),
                self::get(\App\Services\EmailService::class),
                self::get(\App\Repositories\CronJobRunRepository::class)
            ),
            \App\Services\Cron\Jobs\AccountDeletionAnonymizeCronJob::class => new \App\Services\Cron\Jobs\AccountDeletionAnonymizeCronJob(
                self::get(\App\Services\Account\AccountDeletionService::class)
            ),
            \App\Services\Cron\Jobs\RoleplayBilanDueCronJob::class => new \App\Services\Cron\Jobs\RoleplayBilanDueCronJob(
                self::get(TenantRepository::class),
                self::get(UserRepository::class),
                self::get(\App\Repositories\PersonnelProfileRepository::class),
                self::get(\App\Services\Effectifs\EffectifsStaffAlertService::class),
                self::get(\App\Repositories\UserNotificationPreferencesRepository::class),
                self::get(\App\Services\EmailService::class),
                self::get(\App\Repositories\CronJobRunRepository::class)
            ),
            \App\Services\Cron\Jobs\TrainingForgottenDocsDigestCronJob::class => new \App\Services\Cron\Jobs\TrainingForgottenDocsDigestCronJob(
                self::get(TenantRepository::class),
                self::get(UserRepository::class),
                self::get(\App\Repositories\TrainingFormationCustomPageRepository::class),
                self::get(\App\Repositories\UserNotificationPreferencesRepository::class),
                self::get(\App\Services\EmailService::class),
                self::get(\App\Repositories\CronJobRunRepository::class)
            ),
            \App\Services\Cron\Jobs\AttendanceRemindersCronJob::class => new \App\Services\Cron\Jobs\AttendanceRemindersCronJob(
                self::get(\App\Repositories\CommunityEventRepository::class),
                self::get(\App\Services\EmailService::class),
                self::get(TenantRepository::class),
                self::get(\App\Repositories\UserNotificationPreferencesRepository::class)
            ),
            \App\Services\Cron\CronRunner::class => new \App\Services\Cron\CronRunner(
                [
                    self::get(\App\Services\Cron\Jobs\TrainingExpireCronJob::class),
                    self::get(\App\Services\Cron\Jobs\ModerationQuarantineExpireCronJob::class),
                    self::get(\App\Services\Cron\Jobs\RecruitmentRetroRemindersCronJob::class),
                    self::get(\App\Services\Cron\Jobs\HrWeeklyDigestCronJob::class),
                    self::get(\App\Services\Cron\Jobs\AccountDeletionAnonymizeCronJob::class),
                    self::get(\App\Services\Cron\Jobs\TrainingForgottenDocsDigestCronJob::class),
                    self::get(\App\Services\Cron\Jobs\RoleplayBilanDueCronJob::class),
                    self::get(\App\Services\Cron\Jobs\AttendanceRemindersCronJob::class),
                ],
                self::get(\App\Repositories\CronJobRunRepository::class)
            ),
            \App\Controllers\Web\CronController::class => new \App\Controllers\Web\CronController(
                self::get(\App\Services\Cron\CronRunner::class)
            ),
            \App\Controllers\Admin\System\SystemCronController::class => new \App\Controllers\Admin\System\SystemCronController(
                self::get(\App\Services\Cron\CronRunner::class),
                self::get(\App\Repositories\CronJobRunRepository::class)
            ),
            \App\Controllers\Admin\System\SystemMemberSanctionsController::class => new \App\Controllers\Admin\System\SystemMemberSanctionsController(
                self::get(AuthService::class),
                self::get(TenantRepository::class),
                self::get(UserRepository::class),
                self::get(\App\Repositories\ModerationRepository::class),
                self::get(\App\Services\Moderation\ModerationService::class)
            ),
            \App\Controllers\Admin\System\SystemUserLookupApiController::class => new \App\Controllers\Admin\System\SystemUserLookupApiController(
                self::get(UserRepository::class)
            ),
            \App\Controllers\Admin\Organization\OrganizationAnalyticsController::class => new \App\Controllers\Admin\Organization\OrganizationAnalyticsController(
                self::get(\App\Services\Platform\FeatureGateService::class),
                self::get(\App\Repositories\PlatformUsageRepository::class),
                self::get(\App\Repositories\TenantAnalyticsRepository::class)
            ),
            \App\Services\Attendance\CommunityEventAttendanceService::class => new \App\Services\Attendance\CommunityEventAttendanceService(
                self::get(\App\Repositories\CommunityEventRepository::class),
                self::get(\App\Services\EmailService::class),
                self::get(TenantRepository::class),
                self::get(UserRepository::class),
                self::get(\App\Repositories\UserNotificationPreferencesRepository::class),
                self::get(\App\Repositories\ForumNotificationRepository::class)
            ),
            \App\Services\Attendance\CommunityEventSlotService::class => new \App\Services\Attendance\CommunityEventSlotService(
                self::get(\App\Repositories\CommunityEventSlotRepository::class),
                self::get(\App\Repositories\CommunityEventSlotAssignmentRepository::class),
                self::get(\App\Repositories\CommunityEventRepository::class),
                self::get(\App\Services\Attendance\CommunityEventAttendanceService::class),
                self::get(\App\Repositories\PersonnelQualificationRepository::class),
                self::get(\App\Repositories\TrainingCourseRepository::class)
            ),
            \App\Controllers\Admin\Organization\CommunityEventsAdminController::class => new \App\Controllers\Admin\Organization\CommunityEventsAdminController(
                self::get(\App\Repositories\CommunityEventRepository::class),
                self::get(UserRepository::class),
                self::get(AuthService::class),
                self::get(\App\Services\Platform\FeatureGateService::class),
                self::get(\App\Services\Attendance\CommunityEventAttendanceService::class),
                self::get(\App\Repositories\CommunityEventSlotRepository::class),
                self::get(\App\Repositories\CommunityEventSlotAssignmentRepository::class),
                self::get(\App\Repositories\UnitRepository::class),
                self::get(\App\Repositories\TrainingCourseRepository::class),
                self::get(\App\Repositories\AarReportRepository::class)
            ),
            \App\Controllers\Web\CommunityEventsController::class => new \App\Controllers\Web\CommunityEventsController(
                self::get(\App\Repositories\CommunityEventRepository::class),
                self::get(AuthService::class),
                self::get(\App\Services\Platform\FeatureGateService::class),
                self::get(\App\Services\Attendance\CommunityEventAttendanceService::class),
                self::get(\App\Repositories\CommunityEventSlotRepository::class),
                self::get(\App\Repositories\CommunityEventSlotAssignmentRepository::class),
                self::get(\App\Services\Attendance\CommunityEventSlotService::class)
            ),
            \App\Controllers\Api\IntegrationsPublicEventsController::class => new \App\Controllers\Api\IntegrationsPublicEventsController(
                self::get(\App\Repositories\CommunityEventRepository::class),
                self::get(\App\Services\Platform\FeatureGateService::class),
            ),
            \App\Controllers\Web\CommunityCalendarFeedController::class => new \App\Controllers\Web\CommunityCalendarFeedController(
                self::get(\App\Repositories\CommunityEventRepository::class),
                self::get(\App\Services\Platform\FeatureGateService::class),
            ),
            \App\Controllers\Web\PointageController::class => new \App\Controllers\Web\PointageController(
                self::get(\App\Repositories\CommunityEventRepository::class),
                self::get(AuthService::class),
                self::get(\App\Services\Platform\FeatureGateService::class),
                self::get(\App\Services\Attendance\CommunityEventAttendanceService::class)
            ),
            \App\Services\Profile\RecruitmentPresetPayloadService::class => new \App\Services\Profile\RecruitmentPresetPayloadService(),
            \App\Services\Steam\SteamWebApiService::class => new \App\Services\Steam\SteamWebApiService(),
            \App\Controllers\Web\AccountController::class => new \App\Controllers\Web\AccountController(
                self::get(AuthService::class),
                self::get(UserRepository::class),
                self::get(\App\Repositories\UserLegalIdentityRepository::class),
                self::get(\App\Repositories\UserProfileRepository::class),
                self::get(\App\Repositories\PersonnelProfileRepository::class),
                self::get(\App\Repositories\RecruitmentPresetRepository::class),
                new \App\Services\Profile\RecruitmentPresetPayloadService(),
                self::get(\App\Repositories\UserUiPreferencesRepository::class),
                self::get(\App\Repositories\UserNotificationPreferencesRepository::class),
                self::get(\App\Services\Profile\UserUiPreferencesValidationService::class),
                self::get(\App\Services\Steam\SteamWebApiService::class),
                self::get(\App\Services\Auth\LoginSecurityOtpService::class),
                self::get(\App\Services\Community\LeaveCommunityService::class),
            ),
            \App\Services\Account\AccountDataExportService::class => new \App\Services\Account\AccountDataExportService(
                self::get(UserRepository::class),
                self::get(\App\Repositories\UserProfileRepository::class),
                self::get(\App\Repositories\UserLegalIdentityRepository::class),
                self::get(\App\Repositories\PersonnelProfileRepository::class),
                self::get(\App\Repositories\TrainingEnrollmentRepository::class),
                self::get(\App\Repositories\TrainingCertificateRepository::class),
                self::get(\App\Repositories\CommunityEventRepository::class),
                self::get(\App\Repositories\ForumPostRepository::class),
                self::get(\App\Repositories\TenantMessageRepository::class)
            ),
            \App\Services\Account\AccountDeletionService::class => new \App\Services\Account\AccountDeletionService(
                self::get(UserRepository::class),
                self::get(\App\Repositories\UserProfileRepository::class),
                self::get(\App\Repositories\UserLegalIdentityRepository::class),
                self::get(\App\Repositories\PersonnelProfileRepository::class),
                self::get(\App\Repositories\UserProfileDisplaySettingsRepository::class),
                self::get(\App\Repositories\PersonnelExtrasRepository::class),
                self::get(\App\Repositories\RecruitmentPresetRepository::class),
            ),
            \App\Controllers\Web\AccountPrivacyController::class => new \App\Controllers\Web\AccountPrivacyController(
                self::get(AuthService::class),
                self::get(\App\Services\Account\AccountDataExportService::class),
                self::get(\App\Services\Account\AccountDeletionService::class)
            ),
            \App\Controllers\Web\HrCharterController::class => new \App\Controllers\Web\HrCharterController(
                self::get(AuthService::class),
                self::get(\App\Services\Platform\FeatureGateService::class),
                self::get(\App\Repositories\HrCharterRepository::class),
            ),
            \App\Repositories\PersonnelAbsenceRepository::class => new \App\Repositories\PersonnelAbsenceRepository(),
            \App\Controllers\Web\RhWorkspaceController::class => new \App\Controllers\Web\RhWorkspaceController(
                self::get(AuthService::class),
                self::get(\App\Services\Platform\FeatureGateService::class),
                self::get(\App\Repositories\HrCharterRepository::class),
                self::get(\App\Services\Personnel\SenioritySummaryService::class),
                self::get(\App\Repositories\PlatformModuleReleaseRepository::class),
                self::get(\App\Repositories\PersonnelAssignmentRepository::class),
                self::get(\App\Services\Personnel\SeniorityEnrollmentBootstrapService::class),
                self::get(\App\Services\Personnel\SeniorityDossierInferenceSyncService::class),
                self::get(UserRepository::class),
                self::get(\App\Repositories\PersonnelAbsenceRepository::class),
            ),
            \App\Controllers\Admin\System\PlatformDeploymentAdminController::class => new \App\Controllers\Admin\System\PlatformDeploymentAdminController(
                self::get(\App\Repositories\PlatformModuleReleaseRepository::class),
                self::get(UserRepository::class),
                self::get(\App\Services\Audit\AuditService::class),
                self::get(\App\Repositories\DeploymentCampaignRepository::class),
                self::get(\App\Services\Platform\DeploymentCampaignProcessor::class),
                self::get(\App\Services\Platform\DeploymentChannelReleaseService::class),
            ),
            \App\Repositories\PlatformAppReleaseRepository::class => new \App\Repositories\PlatformAppReleaseRepository(),
            \App\Services\Deployment\PackageSignatureVerifier::class => new \App\Services\Deployment\PackageSignatureVerifier(),
            \App\Services\Deployment\AppVersionStore::class => new \App\Services\Deployment\AppVersionStore(),
            \App\Services\Deployment\HealthCheckService::class => new \App\Services\Deployment\HealthCheckService(),
            \App\Services\Deployment\UpdatePackageService::class => new \App\Services\Deployment\UpdatePackageService(
                self::get(\App\Repositories\PlatformAppReleaseRepository::class),
                self::get(\App\Services\Deployment\PackageSignatureVerifier::class),
                self::get(\App\Services\Deployment\AppVersionStore::class),
            ),
            \App\Services\Deployment\ReleaseManager::class => new \App\Services\Deployment\ReleaseManager(
                self::get(\App\Repositories\PlatformAppReleaseRepository::class),
                self::get(\App\Services\Deployment\UpdatePackageService::class),
                self::get(\App\Services\Deployment\AppVersionStore::class),
                self::get(\App\Services\Deployment\HealthCheckService::class),
            ),
            \App\Controllers\Admin\System\SystemUpdatesController::class => new \App\Controllers\Admin\System\SystemUpdatesController(
                self::get(\App\Repositories\PlatformAppReleaseRepository::class),
                self::get(\App\Services\Deployment\UpdatePackageService::class),
                self::get(\App\Services\Deployment\ReleaseManager::class),
                self::get(\App\Services\Deployment\AppVersionStore::class),
                self::get(\App\Services\Deployment\HealthCheckService::class),
            ),
            \App\Controllers\Admin\System\SystemTenantsController::class => new \App\Controllers\Admin\System\SystemTenantsController(
                self::get(TenantRepository::class),
                self::get(\App\Repositories\SubscriptionPlanRepository::class),
                self::get(\App\Services\Audit\AuditService::class),
            ),
            \App\Controllers\Admin\System\SystemUsersController::class => new \App\Controllers\Admin\System\SystemUsersController(
                self::get(UserRepository::class),
                self::get(TenantRepository::class),
                self::get(\App\Services\Audit\AuditService::class),
                self::get(\App\Services\Account\AccountDeletionService::class),
            ),
            \App\Repositories\PersonnelJobRoleRepository::class => new \App\Repositories\PersonnelJobRoleRepository(),
            \App\Repositories\PlanningEntryRepository::class => new \App\Repositories\PlanningEntryRepository(),
            \App\Controllers\Admin\Organization\PersonnelJobRoleAdminController::class => new \App\Controllers\Admin\Organization\PersonnelJobRoleAdminController(
                self::get(\App\Repositories\PersonnelJobRoleRepository::class),
                self::get(\App\Repositories\PermissionRepository::class),
                self::get(\App\Repositories\PersonnelProfileRepository::class),
                self::get(UserRepository::class),
                self::get(\App\Repositories\PersonnelAssignmentRepository::class),
                self::get(TenantRepository::class),
                self::get(\App\Services\Personnel\PersonnelStructureChangeNotificationService::class)
            ),
            \App\Controllers\Admin\Organization\HrCharterDocumentAdminController::class => new \App\Controllers\Admin\Organization\HrCharterDocumentAdminController(
                self::get(\App\Repositories\HrCharterRepository::class),
                self::get(\App\Repositories\TrainingCourseRepository::class),
            ),
            \App\Controllers\Admin\Organization\DoctrineAdminController::class => new \App\Controllers\Admin\Organization\DoctrineAdminController(
                self::get(AuthService::class),
                self::get(\App\Repositories\DoctrineRepository::class),
            ),
            \App\Controllers\Api\DoctrineApiController::class => new \App\Controllers\Api\DoctrineApiController(
                self::get(AuthService::class),
                self::get(\App\Repositories\DoctrineRepository::class),
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
                self::get(\App\Repositories\UserLegalIdentityRepository::class),
                self::get(\App\Repositories\EnlistmentRepository::class),
                self::get(\App\Repositories\PersonnelJobRoleRepository::class),
                self::get(\App\Repositories\PlanningEntryRepository::class),
                self::get(\App\Repositories\RoleRepository::class),
                self::get(TenantRepository::class),
                self::get(\App\Services\Personnel\SenioritySummaryService::class),
                self::get(\App\Repositories\ArmaPlaytimeRepository::class),
                self::get(\App\Services\Steam\SteamWebApiService::class),
                self::get(\App\Repositories\PersonnelOrgHistoryRepository::class),
                self::get(\App\Repositories\PersonnelRoleplayTimelineRepository::class),
                self::get(\App\Services\Personnel\RoleplayFollowupNotificationService::class),
                self::get(\App\Repositories\PersonnelStageBilanRepository::class),
                self::get(\App\Repositories\PersonnelAbsenceRepository::class),
                self::get(\App\Repositories\PositionRepository::class),
                self::get(\App\Repositories\EnlistmentRecruitmentEngagementRepository::class),
                self::get(\App\Repositories\BadgeRepository::class),
                self::get(\App\Services\Personnel\PersonnelStructureChangeNotificationService::class),
            ),
            \App\Repositories\BadgeRepository::class => new \App\Repositories\BadgeRepository(),
            \App\Repositories\PersonnelStageBilanRepository::class => new \App\Repositories\PersonnelStageBilanRepository(),
            \App\Controllers\Admin\Organization\RoleplayFollowupAdminController::class => new \App\Controllers\Admin\Organization\RoleplayFollowupAdminController(
                self::get(UserRepository::class),
                self::get(\App\Repositories\PersonnelProfileRepository::class),
                self::get(\App\Repositories\PersonnelRoleplayTimelineRepository::class),
                self::get(TenantRepository::class),
            ),
            \App\Controllers\Web\RoleplayPageController::class => new \App\Controllers\Web\RoleplayPageController(
                self::get(UserRepository::class),
                self::get(\App\Repositories\PersonnelProfileRepository::class),
                self::get(\App\Repositories\PersonnelRoleplayTimelineRepository::class),
            ),
            \App\Controllers\Web\PersonnelDeploymentController::class => new \App\Controllers\Web\PersonnelDeploymentController(
                self::get(AuthService::class),
                self::get(UserRepository::class),
                new \App\Repositories\PersonnelDeploymentRepository(),
                self::get(\App\Repositories\PersonnelProfileRepository::class),
                self::get(TenantRepository::class),
                self::get(\App\Repositories\UnitRepository::class),
                self::get(\App\Repositories\CommunityEventRepository::class),
                self::get(\App\Services\EmailService::class),
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
            \App\Repositories\OrbatChartTypeRepository::class => new \App\Repositories\OrbatChartTypeRepository(),
            \App\Repositories\EnlistmentRepository::class => new \App\Repositories\EnlistmentRepository(),
            \App\Repositories\EnlistmentCannedMessageRepository::class => new \App\Repositories\EnlistmentCannedMessageRepository(),
            \App\Repositories\EnlistmentTimelineRepository::class => new \App\Repositories\EnlistmentTimelineRepository(),
            \App\Repositories\EnlistmentRecruitmentEngagementRepository::class => new \App\Repositories\EnlistmentRecruitmentEngagementRepository(),
            \App\Repositories\RecruitmentPresetRepository::class => new \App\Repositories\RecruitmentPresetRepository(),
            \App\Controllers\Web\EnlistmentController::class => new \App\Controllers\Web\EnlistmentController(
                self::get(\App\Repositories\EnlistmentRepository::class),
                self::get(\App\Repositories\EnlistmentTimelineRepository::class),
                self::get(TenantRepository::class),
                self::get(AuthService::class),
                self::get(UserRepository::class),
                self::get(\App\Repositories\UserProfileRepository::class),
                self::get(\App\Repositories\RecruitmentPresetRepository::class),
                self::get(\App\Services\Profile\RecruitmentPresetPayloadService::class),
                self::get(\App\Services\EmailService::class),
                self::get(\App\Services\Moderation\IndicatorBlocklistService::class),
                self::get(\App\Repositories\UserNotificationPreferencesRepository::class),
                self::get(\App\Repositories\RecruitmentOpeningRepository::class),
                self::get(\App\Services\Analytics\AnalyticsEventService::class),
                self::get(\App\Repositories\PersonnelProfileRepository::class),
                self::get(\App\Repositories\PersonnelAssignmentRepository::class),
                self::get(\App\Repositories\PersonnelJobRoleRepository::class),
                self::get(\App\Repositories\UnitRepository::class),
                self::get(\App\Repositories\TenantBrandingRepository::class),
                self::get(\App\Repositories\RecruitmentDiscordQuestionRepository::class),
            ),
            \App\Repositories\RecruitmentDiscordQuestionRepository::class => new \App\Repositories\RecruitmentDiscordQuestionRepository(),
            \App\Repositories\DocumentRepository::class => new \App\Repositories\DocumentRepository(),
            \App\Repositories\DocumentVersionRepository::class => new \App\Repositories\DocumentVersionRepository(),
            \App\Repositories\DocumentCategoryRepository::class => new \App\Repositories\DocumentCategoryRepository(),
            \App\Repositories\DocumentLinkRepository::class => new \App\Repositories\DocumentLinkRepository(),
            \App\Repositories\DocumentCollaboratorRepository::class => new \App\Repositories\DocumentCollaboratorRepository(),
            \App\Repositories\DocumentPermissionRepository::class => new \App\Repositories\DocumentPermissionRepository(),
            \App\Repositories\DocumentRelationRepository::class => new \App\Repositories\DocumentRelationRepository(),
            \App\Repositories\DocumentAuditRepository::class => new \App\Repositories\DocumentAuditRepository(),
            \App\Repositories\DocumentSecurityRepository::class => new \App\Repositories\DocumentSecurityRepository(),
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
                self::get(UserRepository::class),
                self::get(\App\Repositories\PersonnelProfileRepository::class)
            ),
            \App\Services\Audit\AuditService::class => new \App\Services\Audit\AuditService(),
            \App\Repositories\AuditLogRepository::class => new \App\Repositories\AuditLogRepository(),
            \App\Services\Audit\AuditRollbackService::class => new \App\Services\Audit\AuditRollbackService(
                self::get(UserRepository::class),
                self::get(\App\Repositories\PlatformSettingsRepository::class),
                self::get(\App\Repositories\SubscriptionPlanRepository::class),
                self::get(TenantRepository::class),
                self::get(\App\Services\Audit\AuditService::class),
                self::get(\App\Services\Email\SecurityAlertService::class),
            ),
            \App\Controllers\Admin\System\SystemAuditController::class => new \App\Controllers\Admin\System\SystemAuditController(
                self::get(\App\Repositories\AuditLogRepository::class),
                self::get(\App\Services\Audit\AuditRollbackService::class),
            ),
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
                self::get(\App\Services\Admin\TenantRolePermissionPresetService::class),
                self::get(\App\Repositories\TenantRepository::class),
                self::get(\App\Services\Audit\AuditService::class)
            ),
            \App\Services\Security\AccessControlService::class => new \App\Services\Security\AccessControlService(),
            \App\Controllers\Admin\Organization\AccessManagementController::class => new \App\Controllers\Admin\Organization\AccessManagementController(
                self::get(\App\Services\Security\AccessControlService::class),
                self::get(UserRepository::class)
            ),
            \App\Controllers\Admin\Organization\OrganizationPositionsController::class => new \App\Controllers\Admin\Organization\OrganizationPositionsController(
                self::get(\App\Repositories\PositionRepository::class),
                self::get(\App\Repositories\RoleSetRepository::class),
            ),
            \App\Repositories\RecruitmentOpeningRepository::class => new \App\Repositories\RecruitmentOpeningRepository(),
            \App\Services\Recruitment\RecruitmentOpeningForumPublisher::class => new \App\Services\Recruitment\RecruitmentOpeningForumPublisher(),
            \App\Controllers\Admin\Organization\RecruitmentOffersController::class => new \App\Controllers\Admin\Organization\RecruitmentOffersController(
                self::get(\App\Repositories\RecruitmentOpeningRepository::class),
                self::get(\App\Repositories\UnitRepository::class),
                self::get(TenantRepository::class),
                self::get(\App\Repositories\PersonnelJobRoleRepository::class),
                self::get(\App\Services\Recruitment\RecruitmentOpeningForumPublisher::class),
                self::get(UserRepository::class),
                self::get(\App\Services\EmailService::class),
                self::get(\App\Repositories\UserNotificationPreferencesRepository::class),
                self::get(\App\Repositories\EnlistmentRepository::class)
            ),
            \App\Repositories\TenantRequiredRoleDefinitionRepository::class => new \App\Repositories\TenantRequiredRoleDefinitionRepository(),
            \App\Controllers\Admin\Organization\RolesFunctionsAdminController::class => new \App\Controllers\Admin\Organization\RolesFunctionsAdminController(
                self::get(\App\Repositories\RoleRepository::class),
                self::get(\App\Repositories\UnitRepository::class),
                self::get(\App\Services\Admin\TenantRolePermissionPresetService::class),
                self::get(\App\Repositories\TenantRequiredRoleDefinitionRepository::class),
                self::get(UserRepository::class)
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
            \App\Repositories\CompetencyGradeRequirementRepository::class => new \App\Repositories\CompetencyGradeRequirementRepository(),
            \App\Controllers\Admin\Organization\CompetencyMatrixController::class => new \App\Controllers\Admin\Organization\CompetencyMatrixController(
                self::get(\App\Repositories\CompetencyGradeRequirementRepository::class),
                self::get(\App\Repositories\GradeRepository::class)
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
            \App\Repositories\MemberDepartureRepository::class => new \App\Repositories\MemberDepartureRepository(),
            \App\Services\Effectifs\MemberOffboardingService::class => new \App\Services\Effectifs\MemberOffboardingService(
                self::get(UserRepository::class),
                self::get(\App\Repositories\PersonnelProfileRepository::class),
                self::get(\App\Repositories\MemberDepartureRepository::class),
                self::get(\App\Services\Admin\AdminAuditService::class)
            ),
            \App\Repositories\PersonnelOrgHistoryRepository::class => new \App\Repositories\PersonnelOrgHistoryRepository(),
            \App\Repositories\UserLegalIdentityRepository::class => new \App\Repositories\UserLegalIdentityRepository(),
            \App\Repositories\PersonnelRoleplayTimelineRepository::class => new \App\Repositories\PersonnelRoleplayTimelineRepository(),
            \App\Services\Personnel\RoleplayFollowupNotificationService::class => new \App\Services\Personnel\RoleplayFollowupNotificationService(
                self::get(\App\Services\EmailService::class),
                self::get(UserRepository::class),
                self::get(\App\Repositories\UserNotificationPreferencesRepository::class),
                self::get(\App\Repositories\ForumNotificationRepository::class),
                self::get(TenantRepository::class),
            ),
            \App\Services\Personnel\PersonnelStructureChangeNotificationService::class => new \App\Services\Personnel\PersonnelStructureChangeNotificationService(
                self::get(\App\Services\EmailService::class),
                self::get(UserRepository::class),
                self::get(TenantRepository::class),
                self::get(\App\Repositories\GradeRepository::class),
                self::get(\App\Repositories\UnitRepository::class),
                self::get(\App\Repositories\PersonnelProfileRepository::class),
                self::get(\App\Repositories\PersonnelAssignmentRepository::class),
                self::get(\App\Repositories\PersonnelJobRoleRepository::class),
                self::get(\App\Repositories\UserNotificationPreferencesRepository::class),
            ),
            \App\Services\Personnel\PersonnelOrgHistoryRecorder::class => new \App\Services\Personnel\PersonnelOrgHistoryRecorder(
                self::get(\App\Repositories\PersonnelOrgHistoryRepository::class),
                self::get(\App\Repositories\RoleRepository::class),
                self::get(\App\Repositories\GradeRepository::class)
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
                self::get(\App\Services\Moderation\IndicatorBlocklistService::class),
                self::get(\App\Services\Personnel\PersonnelOrgHistoryRecorder::class),
                self::get(\App\Services\Personnel\PersonnelStructureChangeNotificationService::class),
                self::get(\App\Services\Steam\SteamWebApiService::class)
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
                self::get(\App\Services\Documents\DocumentTrainingReferencesService::class),
                self::get(\App\Repositories\PersonnelProfileRepository::class),
                self::get(\App\Repositories\DocumentSecurityRepository::class)
            ),
            \App\Services\Portal\PortalSearchService::class => new \App\Services\Portal\PortalSearchService(
                self::get(\App\Repositories\DocumentRepository::class),
                self::get(\App\Services\Documents\DocumentAccessService::class),
                self::get(\App\Repositories\ForumTopicRepository::class),
                self::get(UserRepository::class),
                self::get(\App\Repositories\CommunityEventRepository::class),
                self::get(\App\Repositories\TrainingCourseRepository::class),
            ),
            \App\Services\Portal\AssistantAnswerService::class => new \App\Services\Portal\AssistantAnswerService(
                self::get(\App\Services\Portal\PortalSearchService::class),
            ),
            \App\Controllers\Web\PortalSearchController::class => new \App\Controllers\Web\PortalSearchController(
                self::get(\App\Services\Portal\PortalSearchService::class),
            ),
            \App\Controllers\Web\AssistantController::class => new \App\Controllers\Web\AssistantController(
                self::get(\App\Services\Portal\AssistantAnswerService::class),
            ),
            \App\Services\Notifications\PersonalMessageUnreadCounter::class => new \App\Services\Notifications\PersonalMessageUnreadCounter(
                self::get(\App\Repositories\ForumNotificationRepository::class),
                self::get(\App\Repositories\Courrier\CourrierDocumentNotificationRepository::class),
                self::get(\App\Repositories\TenantMessageRepository::class),
            ),
            \App\Services\Portal\UnifiedActionDigestService::class => new \App\Services\Portal\UnifiedActionDigestService(
                self::get(\App\Repositories\EnlistmentRepository::class),
                self::get(\App\Services\Notifications\PersonalMessageUnreadCounter::class),
            ),
            \App\Services\Portal\BackOfficeSidebarBadgeService::class => new \App\Services\Portal\BackOfficeSidebarBadgeService(
                self::get(\App\Repositories\EnlistmentRepository::class),
                self::get(\App\Repositories\ForumReportRepository::class),
                self::get(\App\Repositories\ModerationArtifactRepository::class),
                self::get(UserRepository::class),
                self::get(\App\Services\Notifications\PersonalMessageUnreadCounter::class),
            ),
            \App\Controllers\Web\ActionCenterController::class => new \App\Controllers\Web\ActionCenterController(
                self::get(\App\Services\Portal\UnifiedActionDigestService::class),
                self::get(UserRepository::class),
            ),
            \App\Services\Notifications\ActivityHubPresentationService::class => new \App\Services\Notifications\ActivityHubPresentationService(),
            \App\Controllers\Web\ActivityHubController::class => new \App\Controllers\Web\ActivityHubController(
                self::get(\App\Repositories\ForumNotificationRepository::class),
                self::get(\App\Repositories\Courrier\CourrierDocumentNotificationRepository::class),
                self::get(\App\Repositories\TenantMessageRepository::class),
                self::get(\App\Services\Notifications\ActivityHubPresentationService::class),
            ),
            \App\Controllers\Web\DocumentationController::class => new \App\Controllers\Web\DocumentationController(),
            \App\Controllers\Web\DossierOperateurController::class => new \App\Controllers\Web\DossierOperateurController(
                self::get(AuthService::class),
                self::get(\App\Repositories\PersonnelProfileRepository::class),
                self::get(\App\Repositories\PersonnelExtrasRepository::class),
                self::get(\App\Repositories\UserProfileRepository::class),
                self::get(\App\Repositories\PersonnelQualificationRepository::class),
                self::get(\App\Repositories\TrainingCertificateRepository::class),
                self::get(\App\Services\Personnel\PersonnelCompletenessService::class),
                self::get(\App\Repositories\Courrier\UserSignatureRepository::class),
                self::get(\App\Repositories\PersonnelAdminDataRepository::class)
            ),
            \App\Controllers\Api\DossierOperateurAccreditationApiController::class => new \App\Controllers\Api\DossierOperateurAccreditationApiController(
                self::get(AuthService::class),
                self::get(\App\Repositories\PersonnelAdminDataRepository::class),
                self::get(\App\Repositories\Courrier\UserSignatureRepository::class)
            ),
            \App\Repositories\ModpackRepository::class => new \App\Repositories\ModpackRepository(),
            \App\Controllers\Web\ModpackController::class => new \App\Controllers\Web\ModpackController(
                self::get(\App\Repositories\ModpackRepository::class)
            ),
            \App\Controllers\Web\OperateurTerrainController::class => new \App\Controllers\Web\OperateurTerrainController(
                self::get(AuthService::class),
                self::get(\App\Repositories\ModpackRepository::class),
                self::get(\App\Services\Dashboard\TenantDashboardPinService::class),
            ),
            \App\Repositories\TrainingRepository::class => new \App\Repositories\TrainingRepository(),
            \App\Repositories\TrainingCourseRepository::class => new \App\Repositories\TrainingCourseRepository(),
            \App\Repositories\TrainingFormationCustomPageRepository::class => new \App\Repositories\TrainingFormationCustomPageRepository(),
            \App\Repositories\TrainingFormationCustomPageFeedbackRepository::class => new \App\Repositories\TrainingFormationCustomPageFeedbackRepository(),
            \App\Repositories\ContentTagRepository::class => new \App\Repositories\ContentTagRepository(),
            \App\Services\Training\TrainingHtmlPageService::class => new \App\Services\Training\TrainingHtmlPageService(
                self::get(\App\Repositories\TrainingFormationCustomPageRepository::class),
            ),
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
            \App\Services\Training\TrainingCourseMediaUploadService::class => new \App\Services\Training\TrainingCourseMediaUploadService(),
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
                self::get(\App\Services\Platform\FeatureGateService::class),
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
                self::get(\App\Services\EmailService::class),
                self::get(UserRepository::class),
                self::get(TenantRepository::class),
                self::get(\App\Repositories\UserNotificationPreferencesRepository::class),
                self::get(\App\Repositories\PersonnelQualificationRepository::class),
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
            \App\Services\Effectifs\EffectifsStaffAlertService::class => new \App\Services\Effectifs\EffectifsStaffAlertService(
                self::get(\App\Repositories\TenantCommunityFeedRepository::class),
                self::get(\App\Repositories\TrainingStaffPingRepository::class),
                self::get(\App\Services\EmailService::class),
                self::get(UserRepository::class),
                self::get(TenantRepository::class),
                self::get(\App\Repositories\UserNotificationPreferencesRepository::class),
                self::get(\App\Repositories\ElevationRequestRepository::class),
                self::get(\App\Services\Effectifs\ElevationApprovalService::class),
            ),
            \App\Services\Effectifs\ElevationApprovalService::class => new \App\Services\Effectifs\ElevationApprovalService(
                self::get(UserRepository::class),
                self::get(\App\Repositories\RoleRepository::class),
                self::get(\App\Repositories\GradeRepository::class),
                self::get(\App\Repositories\PersonnelJobRoleRepository::class),
                self::get(\App\Repositories\PersonnelAssignmentRepository::class),
                self::get(\App\Repositories\UnitRepository::class),
                self::get(\App\Services\Rbac\RbacService::class),
                self::get(\App\Repositories\PersonnelProfileRepository::class),
                self::get(\App\Services\Personnel\PersonnelStructureChangeNotificationService::class),
            ),
            \App\Repositories\ElevationRequestRepository::class => new \App\Repositories\ElevationRequestRepository(),
            \App\Repositories\TrainingCourseLmsSocialRepository::class => new \App\Repositories\TrainingCourseLmsSocialRepository(),
            \App\Repositories\TrainingLessonFeedbackRepository::class => new \App\Repositories\TrainingLessonFeedbackRepository(),
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
            \App\Repositories\CompetencyUserProgressRepository::class => new \App\Repositories\CompetencyUserProgressRepository(),
            \App\Services\Training\CompetencyUserJourneyService::class => new \App\Services\Training\CompetencyUserJourneyService(
                self::get(\App\Repositories\CompetencyUserProgressRepository::class),
            ),
            \App\Repositories\TrainingCompetencyRepository::class => new \App\Repositories\TrainingCompetencyRepository(),
            \App\Repositories\PedagogyRepository::class => new \App\Repositories\PedagogyRepository(),
            \App\Services\Training\TenantPedagogyChainGuard::class => new \App\Services\Training\TenantPedagogyChainGuard(
                self::get(\App\Repositories\PedagogyRepository::class),
            ),
            \App\Services\Training\TrainingCoursePublicationGuard::class => new \App\Services\Training\TrainingCoursePublicationGuard(
                self::get(\App\Repositories\PedagogyRepository::class),
                self::get(\App\Services\Training\TenantPedagogyChainGuard::class),
            ),
            \App\Services\Training\TrainingSessionInstructorGuard::class => new \App\Services\Training\TrainingSessionInstructorGuard(
                self::get(\App\Repositories\PedagogyRepository::class),
            ),
            \App\Services\Training\PedagogyCapabilityResolver::class => new \App\Services\Training\PedagogyCapabilityResolver(
                self::get(\App\Repositories\PedagogyRepository::class),
                self::get(\App\Services\Training\TenantPedagogyChainGuard::class),
            ),
            \App\Services\Training\PedagogyPathwayService::class => new \App\Services\Training\PedagogyPathwayService(),
            \App\Services\Training\TenantPedagogyStructureService::class => new \App\Services\Training\TenantPedagogyStructureService(
                self::get(\App\Repositories\UnitRepository::class),
            ),
            \App\Controllers\Web\TrainingCompetencyController::class => new \App\Controllers\Web\TrainingCompetencyController(
                self::get(\App\Services\Training\CompetencyUserJourneyService::class),
                self::get(\App\Repositories\TrainingCompetencyRepository::class),
                self::get(UserRepository::class),
                self::get(\App\Repositories\RoleRepository::class),
                self::get(\App\Repositories\PedagogyRepository::class),
                self::get(\App\Services\Training\TenantPedagogyChainGuard::class),
                self::get(\App\Services\Training\PedagogyPathwayService::class),
                self::get(\App\Services\Training\TenantPedagogyStructureService::class),
                self::get(\App\Repositories\TrainingCourseRepository::class),
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
                self::get(\App\Repositories\TrainingLessonFeedbackRepository::class),
                self::get(\App\Repositories\PersonnelProfileRepository::class),
                self::get(\App\Repositories\PersonnelAssignmentRepository::class),
                self::get(UserRepository::class),
                self::get(\App\Services\Analytics\AnalyticsEventService::class),
                self::get(\App\Repositories\HrCharterRepository::class),
                self::get(\App\Repositories\TrainingFormationCustomPageRepository::class),
                self::get(\App\Repositories\RoleRepository::class),
                self::get(\App\Services\Training\TrainingFormationCustomPageExportPdfService::class),
                self::get(\App\Repositories\TrainingFormationCustomPageFeedbackRepository::class),
            ),
            \App\Controllers\Web\EquipmentController::class => new \App\Controllers\Web\EquipmentController(
                self::get(\App\Repositories\EquipmentClassRepository::class),
                self::get(\App\Repositories\DocumentLinkRepository::class),
                self::get(\App\Repositories\DocumentRepository::class)
            ),
            \App\Services\Tactical\AtakTokenService::class => new \App\Services\Tactical\AtakTokenService(),
            \App\Repositories\TenantAtakConfigRepository::class => new \App\Repositories\TenantAtakConfigRepository(),
            \App\Repositories\AtakMapRepository::class => new \App\Repositories\AtakMapRepository(),
            \App\Repositories\TacticalGameLinkRepository::class => new \App\Repositories\TacticalGameLinkRepository(),
            \App\Repositories\AtakMapGatewayRepository::class => new \App\Repositories\AtakMapGatewayRepository(),
            \App\Services\Tactical\AtakMapGatewayService::class => new \App\Services\Tactical\AtakMapGatewayService(
                self::get(\App\Repositories\AtakMapGatewayRepository::class),
                self::get(TenantRepository::class),
            ),
            \App\Controllers\Web\AtakMapGatewayController::class => new \App\Controllers\Web\AtakMapGatewayController(
                self::get(AuthService::class),
                self::get(\App\Services\Tactical\AtakMapGatewayService::class),
                self::get(\App\Services\Platform\FeatureGateService::class),
                self::get(\App\Repositories\AtakMapRepository::class),
            ),
            \App\Controllers\Web\AtakController::class => new \App\Controllers\Web\AtakController(
                self::get(\App\Services\Tactical\AtakTokenService::class),
                self::get(\App\Repositories\TenantAtakConfigRepository::class),
                self::get(\App\Repositories\AtakMapRepository::class),
                self::get(AuthService::class),
                self::get(\App\Repositories\UserProfileRepository::class),
                self::get(UserRepository::class),
                self::get(\App\Services\Platform\FeatureGateService::class),
                self::get(\App\Repositories\TacticalGameLinkRepository::class)
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
            \App\Repositories\FireTeamRepository::class => new \App\Repositories\FireTeamRepository(),
            \App\Controllers\Admin\AdminFireTeamsController::class => new \App\Controllers\Admin\AdminFireTeamsController(
                self::get(\App\Repositories\FireTeamRepository::class),
                self::get(\App\Repositories\UnitRepository::class),
                self::get(UserRepository::class),
                self::get(\App\Repositories\AtakMapRepository::class),
                new \App\Services\Tactical\FireTeamActivityLogger(
                    self::get(\App\Services\Tactical\AtakActivityLogService::class)
                )
            ),
            \App\Controllers\Admin\AdminAtakOperatorsController::class => new \App\Controllers\Admin\AdminAtakOperatorsController(
                self::get(\App\Repositories\AtakDataRepository::class),
                self::get(\App\Repositories\AtakMapRepository::class),
                self::get(\App\Repositories\AtakOperatorIdRepository::class),
                self::get(\App\Repositories\FireTeamRepository::class),
                self::get(UserRepository::class),
                self::get(\App\Repositories\UserProfileRepository::class),
                self::get(\App\Repositories\AtakRealismRepository::class)
            ),
            \App\Controllers\Api\FireTeamApiController::class => new \App\Controllers\Api\FireTeamApiController(
                self::get(\App\Repositories\FireTeamRepository::class),
                self::get(TenantRepository::class),
                self::get(\App\Repositories\UnitRepository::class),
                new \App\Services\Tactical\FireTeamActivityLogger(
                    self::get(\App\Services\Tactical\AtakActivityLogService::class)
                )
            ),
            \App\Controllers\Admin\AdminAtakModController::class => new \App\Controllers\Admin\AdminAtakModController(
                self::get(\App\Services\Integrations\ModUpdateDiscordNotifier::class),
            ),
            \App\Services\Integrations\ModUpdateDiscordNotifier::class => new \App\Services\Integrations\ModUpdateDiscordNotifier(
                self::get(\App\Services\Integrations\DiscordWebhookService::class),
                self::get(\App\Repositories\TenantRepository::class),
            ),
            \App\Controllers\Admin\AdminAtakModBlocklistController::class => new \App\Controllers\Admin\AdminAtakModBlocklistController(
                self::get(AuthService::class),
                self::get(\App\Services\Moderation\IndicatorBlocklistService::class),
                self::get(\App\Repositories\BlockedIndicatorRepository::class),
                self::get(UserRepository::class),
            ),
            \App\Repositories\AtakBetaRegistrationRepository::class => new \App\Repositories\AtakBetaRegistrationRepository(),
            \App\Repositories\AtakRealismRepository::class => new \App\Repositories\AtakRealismRepository(
                null,
                self::get(\App\Repositories\AtakOperatorIdRepository::class)
            ),
            \App\Repositories\AtakModReportRepository::class => new \App\Repositories\AtakModReportRepository(),
            \App\Controllers\Admin\AdminAtakBetaRegistrationsController::class => new \App\Controllers\Admin\AdminAtakBetaRegistrationsController(
                self::get(\App\Repositories\AtakBetaRegistrationRepository::class),
                self::get(AuthService::class),
                self::get(\App\Services\Moderation\IndicatorBlocklistService::class),
                self::get(\App\Repositories\BlockedIndicatorRepository::class),
            ),
            \App\Controllers\Admin\AdminAtakModReportsController::class => new \App\Controllers\Admin\AdminAtakModReportsController(
                self::get(\App\Repositories\AtakModReportRepository::class),
                self::get(AuthService::class),
            ),
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
            \App\Repositories\TenantAdminSettingsRepository::class => new \App\Repositories\TenantAdminSettingsRepository(
                self::get(TenantRepository::class)
            ),
            \App\Controllers\Admin\Organization\AdminRuntimeSettingsApiController::class => new \App\Controllers\Admin\Organization\AdminRuntimeSettingsApiController(
                self::get(\App\Repositories\TenantAdminSettingsRepository::class)
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
            \App\Services\Recruitment\EnlistmentPortalAttachmentService::class => new \App\Services\Recruitment\EnlistmentPortalAttachmentService(
                self::get(\App\Repositories\EnlistmentRepository::class)
            ),
            \App\Services\Recruitment\EnlistmentPortalTextModerationScanner::class => new \App\Services\Recruitment\EnlistmentPortalTextModerationScanner(),
            \App\Services\Recruitment\EnlistmentPortalAutoModerationCoordinator::class => new \App\Services\Recruitment\EnlistmentPortalAutoModerationCoordinator(
                self::get(\App\Services\Recruitment\EnlistmentPortalTextModerationScanner::class),
                self::get(\App\Repositories\BlockedIndicatorRepository::class),
                self::get(UserRepository::class),
                self::get(\App\Services\EmailService::class),
                self::get(\App\Repositories\EnlistmentTimelineRepository::class),
                self::get(\App\Repositories\PlatformSettingsRepository::class)
            ),
            \App\Repositories\RecruitmentTeamWallRepository::class => new \App\Repositories\RecruitmentTeamWallRepository(),
            \App\Services\Recruitment\EnlistmentCandidatePortalJourneyService::class => new \App\Services\Recruitment\EnlistmentCandidatePortalJourneyService(),
            \App\Controllers\Admin\AdminRecruitmentsController::class => new \App\Controllers\Admin\AdminRecruitmentsController(
                self::get(\App\Repositories\EnlistmentRepository::class),
                self::get(\App\Repositories\EnlistmentCannedMessageRepository::class),
                self::get(\App\Repositories\EnlistmentTimelineRepository::class),
                self::get(\App\Services\Recruitment\EnlistmentAcceptanceProvisioningService::class),
                self::get(TenantRepository::class),
                self::get(\App\Repositories\RecruitmentOpeningRepository::class),
                self::get(UserRepository::class),
                self::get(\App\Services\EmailService::class),
                self::get(\App\Services\Recruitment\EnlistmentPortalAttachmentService::class),
                self::get(\App\Services\Recruitment\EnlistmentPortalAutoModerationCoordinator::class),
                self::get(\App\Repositories\EnlistmentRecruitmentEngagementRepository::class),
                self::get(\App\Services\Analytics\AnalyticsEventService::class),
                self::get(\App\Repositories\RecruitmentTeamWallRepository::class),
                self::get(\App\Services\Recruitment\EnlistmentCandidatePortalJourneyService::class)
            ),
            \App\Controllers\Admin\AdminRecruitmentDiscordQuestionsController::class => new \App\Controllers\Admin\AdminRecruitmentDiscordQuestionsController(
                self::get(\App\Repositories\RecruitmentDiscordQuestionRepository::class),
                self::get(\App\Repositories\EnlistmentRepository::class)
            ),
            \App\Services\Recruitment\EnlistmentPortalMessagingNotificationService::class => new \App\Services\Recruitment\EnlistmentPortalMessagingNotificationService(
                self::get(UserRepository::class),
                self::get(\App\Services\EmailService::class),
                self::get(\App\Repositories\UserNotificationPreferencesRepository::class),
                self::get(TenantRepository::class),
                self::get(\App\Repositories\EnlistmentTimelineRepository::class)
            ),
            \App\Controllers\Web\EnlistmentCandidatePortalController::class => new \App\Controllers\Web\EnlistmentCandidatePortalController(
                self::get(\App\Repositories\EnlistmentRepository::class),
                self::get(TenantRepository::class),
                self::get(UserRepository::class),
                self::get(\App\Services\Recruitment\EnlistmentPortalMessagingNotificationService::class),
                self::get(\App\Services\Recruitment\EnlistmentPortalAttachmentService::class),
                self::get(\App\Services\Recruitment\EnlistmentPortalAutoModerationCoordinator::class),
                self::get(\App\Repositories\EnlistmentTimelineRepository::class),
                self::get(\App\Repositories\EnlistmentRecruitmentEngagementRepository::class),
                self::get(\App\Services\Analytics\AnalyticsEventService::class),
                self::get(\App\Services\Recruitment\EnlistmentCandidatePortalJourneyService::class)
            ),
            \App\Controllers\Admin\RecruitmentWorkspaceController::class => new \App\Controllers\Admin\RecruitmentWorkspaceController(
                self::get(\App\Repositories\EnlistmentRepository::class),
                self::get(TenantRepository::class),
                self::get(\App\Repositories\RecruitmentOpeningRepository::class),
                self::get(\App\Repositories\EnlistmentTimelineRepository::class),
                self::get(\App\Repositories\BlockedIndicatorRepository::class),
                self::get(AuthService::class),
                self::get(\App\Services\EmailService::class),
                self::get(UserRepository::class)
            ),
            \App\Controllers\Admin\EffectifsWorkspaceController::class => new \App\Controllers\Admin\EffectifsWorkspaceController(
                self::get(UserRepository::class),
                self::get(\App\Repositories\RoleRepository::class),
                self::get(\App\Repositories\UnitRepository::class),
                self::get(\App\Repositories\PersonnelProfileRepository::class),
                self::get(\App\Repositories\PersonnelAssignmentRepository::class),
                self::get(\App\Repositories\PersonnelJobRoleRepository::class),
                self::get(\App\Services\Admin\AdminAuditService::class),
                self::get(\App\Services\Effectifs\EffectifsStaffAlertService::class),
                self::get(TenantRepository::class),
                self::get(\App\Repositories\GradeRepository::class),
                self::get(\App\Services\Effectifs\ElevationApprovalService::class),
                self::get(\App\Services\Effectifs\MemberOffboardingService::class),
                self::get(\App\Repositories\MemberDepartureRepository::class),
                self::get(\App\Services\Personnel\PersonnelStructureChangeNotificationService::class),
                self::get(\App\Repositories\ElevationRequestRepository::class),
            ),
            \App\Controllers\Admin\System\SystemRecruitmentPortalToolsController::class => new \App\Controllers\Admin\System\SystemRecruitmentPortalToolsController(
                self::get(AuthService::class),
                self::get(\App\Repositories\BlockedIndicatorRepository::class),
                self::get(\App\Services\Moderation\IndicatorBlocklistService::class),
                self::get(\App\Repositories\EnlistmentRepository::class),
                self::get(TenantRepository::class),
                self::get(\App\Services\EmailService::class),
                self::get(\App\Repositories\EnlistmentTimelineRepository::class),
                self::get(\App\Repositories\PlatformSettingsRepository::class)
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
                self::get(\App\Repositories\UserNotificationPreferencesRepository::class),
                self::get(\App\Services\Training\TrainingCertificateService::class),
                self::get(\App\Services\Training\TrainingCertificatePdfService::class),
                self::get(\App\Repositories\TrainingLessonFeedbackRepository::class),
                self::get(\App\Services\Training\TrainingCourseMediaUploadService::class),
                self::get(\App\Repositories\ContentTagRepository::class),
                self::get(\App\Repositories\TrainingFormationCustomPageRepository::class),
            ),
            \App\Services\Training\TrainingPublicSiteImageCatalog::class => new \App\Services\Training\TrainingPublicSiteImageCatalog(),
            \App\Services\Training\TrainingPresentationKitService::class => new \App\Services\Training\TrainingPresentationKitService(
                self::get(TenantRepository::class),
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
                self::get(\App\Services\Documents\DocumentUploadService::class),
                self::get(\App\Services\Training\TrainingCoursePublicationGuard::class),
                self::get(\App\Services\Training\TrainingSessionInstructorGuard::class),
                self::get(\App\Repositories\PedagogyRepository::class),
                self::get(\App\Services\Training\TrainingCourseMediaUploadService::class),
                self::get(\App\Services\Training\TrainingPublicSiteImageCatalog::class),
                self::get(\App\Services\Training\TrainingPresentationKitService::class),
                self::get(\App\Services\Training\TrainingStaffAlertService::class),
                self::get(\App\Repositories\TrainingFormationCustomPageRepository::class),
                self::get(\App\Repositories\ContentTagRepository::class),
            ),
            \App\Controllers\Admin\AdminTrainingStudioExchangeController::class => new \App\Controllers\Admin\AdminTrainingStudioExchangeController(
                self::get(\App\Services\Training\TrainingCourseExchangeService::class),
                self::get(\App\Repositories\TrainingCourseRepository::class),
                self::get(\App\Services\Training\TrainingAuditService::class),
                self::get(\App\Services\Platform\FeatureGateService::class),
            ),
            \App\Repositories\TrainingPublicationRepository::class => new \App\Repositories\TrainingPublicationRepository(),
            \App\Repositories\TrainingPublicationRevisionRepository::class => new \App\Repositories\TrainingPublicationRevisionRepository(),
            \App\Repositories\TrainingPublicationReadReceiptRepository::class => new \App\Repositories\TrainingPublicationReadReceiptRepository(),
            \App\Repositories\TrainingPublicationAnnexRepository::class => new \App\Repositories\TrainingPublicationAnnexRepository(),
            \App\Repositories\TrainingPublicationEvidenceRepository::class => new \App\Repositories\TrainingPublicationEvidenceRepository(),
            \App\Services\TrainingPublication\LmsSourceService::class => new \App\Services\TrainingPublication\LmsSourceService(
                self::get(\App\Services\Training\TrainingService::class),
            ),
            \App\Services\TrainingPublication\DocumentBuildService::class => new \App\Services\TrainingPublication\DocumentBuildService(),
            \App\Services\TrainingPublication\SecurityPolicyService::class => new \App\Services\TrainingPublication\SecurityPolicyService(),
            \App\Services\TrainingPublication\PublicationWorkflowService::class => new \App\Services\TrainingPublication\PublicationWorkflowService(),
            \App\Services\TrainingPublication\TrainingPublicationService::class => new \App\Services\TrainingPublication\TrainingPublicationService(
                self::get(\App\Repositories\TrainingPublicationRepository::class),
                self::get(\App\Repositories\TrainingPublicationRevisionRepository::class),
                self::get(\App\Repositories\TrainingPublicationReadReceiptRepository::class),
                self::get(\App\Repositories\TrainingPublicationAnnexRepository::class),
                self::get(\App\Repositories\TrainingPublicationEvidenceRepository::class),
                self::get(\App\Services\TrainingPublication\LmsSourceService::class),
                self::get(\App\Services\TrainingPublication\DocumentBuildService::class),
                self::get(\App\Services\TrainingPublication\SecurityPolicyService::class),
                self::get(\App\Services\TrainingPublication\PublicationWorkflowService::class),
            ),
            \App\Controllers\Api\TrainingPublicationApiController::class => new \App\Controllers\Api\TrainingPublicationApiController(
                self::get(\App\Services\TrainingPublication\TrainingPublicationService::class),
            ),
            \App\Controllers\Admin\AdminTrainingPublicationController::class => new \App\Controllers\Admin\AdminTrainingPublicationController(
                self::get(\App\Repositories\TrainingPublicationRepository::class),
                self::get(\App\Repositories\TrainingPublicationRevisionRepository::class),
                self::get(\App\Repositories\TrainingCourseRepository::class),
                self::get(\App\Services\TrainingPublication\TrainingPublicationService::class),
            ),
            \App\Services\Training\TrainingFormationCustomPageExportPdfService::class => new \App\Services\Training\TrainingFormationCustomPageExportPdfService(),
            \App\Controllers\Admin\AdminTrainingCustomPageController::class => new \App\Controllers\Admin\AdminTrainingCustomPageController(
                self::get(\App\Repositories\TrainingFormationCustomPageRepository::class),
                self::get(\App\Services\Training\TrainingHtmlPageService::class),
                self::get(\App\Services\Platform\FeatureGateService::class),
                self::get(\App\Services\Training\TrainingFormationCustomPageExportPdfService::class),
                self::get(\App\Services\Audit\AuditService::class),
                self::get(\App\Repositories\TrainingFormationCustomPageFeedbackRepository::class),
                self::get(\App\Repositories\ContentTagRepository::class),
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
                self::get(\App\Repositories\TrainingCourseLmsSocialRepository::class),
                self::get(\App\Repositories\TrainingLessonFeedbackRepository::class),
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
                self::get(\App\Repositories\RoleRepository::class),
                self::get(\App\Repositories\DocumentSecurityRepository::class)
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
            \App\Controllers\Api\TenantAccessRequestApiController::class => new \App\Controllers\Api\TenantAccessRequestApiController(
                self::get(AuthService::class),
                self::get(UserRepository::class),
                self::get(TenantRepository::class),
                self::get(\App\Services\EmailService::class)
            ),
            \App\Repositories\SiteSettingsRepository::class => new \App\Repositories\SiteSettingsRepository(),
            \App\Repositories\PlatformSettingsRepository::class => new \App\Repositories\PlatformSettingsRepository(),
            \App\Repositories\InterteamMissionRepository::class => new \App\Repositories\InterteamMissionRepository(),
            \App\Repositories\CooperationCatalogRepository::class => new \App\Repositories\CooperationCatalogRepository(),
            \App\Repositories\CooperationAnnouncementTemplateRepository::class => new \App\Repositories\CooperationAnnouncementTemplateRepository(),
            \App\Repositories\CooperationForumAnnouncementLogRepository::class => new \App\Repositories\CooperationForumAnnouncementLogRepository(),
            \App\Services\Cooperation\CooperationAnnouncementRenderer::class => new \App\Services\Cooperation\CooperationAnnouncementRenderer(),
            \App\Services\Cooperation\CooperationCatalogService::class => new \App\Services\Cooperation\CooperationCatalogService(
                self::get(\App\Repositories\CooperationCatalogRepository::class)
            ),
            \App\Services\Cooperation\CooperationAnnouncementDispatcher::class => new \App\Services\Cooperation\CooperationAnnouncementDispatcher(
                self::get(\App\Repositories\InterteamMissionRepository::class),
                self::get(TenantRepository::class),
                self::get(\App\Repositories\CooperationAnnouncementTemplateRepository::class),
                self::get(\App\Services\Cooperation\CooperationAnnouncementRenderer::class),
                self::get(UserRepository::class),
                self::get(\App\Repositories\UserNotificationPreferencesRepository::class),
                self::get(\App\Services\EmailService::class),
                self::get(\App\Repositories\ForumNotificationRepository::class),
                self::get(\App\Repositories\ForumPostRepository::class),
                self::get(\App\Repositories\ForumTopicRepository::class),
                self::get(\App\Repositories\CooperationForumAnnouncementLogRepository::class)
            ),
            \App\Controllers\Admin\System\SystemBriefSettingsController::class => new \App\Controllers\Admin\System\SystemBriefSettingsController(
                self::get(\App\Repositories\PlatformSettingsRepository::class),
            ),
            \App\Controllers\Admin\System\SystemSettingsController::class => new \App\Controllers\Admin\System\SystemSettingsController(
                self::get(\App\Repositories\PlatformSettingsRepository::class),
            ),
            \App\Controllers\Admin\PlatformBriefSettingsApiController::class => new \App\Controllers\Admin\PlatformBriefSettingsApiController(
                self::get(\App\Repositories\PlatformSettingsRepository::class),
                self::get(\App\Services\Audit\AuditService::class),
            ),
            \App\Repositories\PlatformAlertRepository::class => new \App\Repositories\PlatformAlertRepository(),
            \App\Repositories\TenantAlertRepository::class => new \App\Repositories\TenantAlertRepository(),
            \App\Repositories\TenantCustomMapRepository::class => new \App\Repositories\TenantCustomMapRepository(),
            \App\Services\Maps\TenantCustomMapStorage::class => new \App\Services\Maps\TenantCustomMapStorage(),
            \App\Controllers\Api\CustomMapsApiController::class => new \App\Controllers\Api\CustomMapsApiController(
                self::get(AuthService::class),
                self::get(\App\Repositories\TenantCustomMapRepository::class),
                self::get(\App\Services\Maps\TenantCustomMapStorage::class),
            ),
            \App\Repositories\UserAlertDismissalRepository::class => new \App\Repositories\UserAlertDismissalRepository(),
            \App\Services\Alerts\AccountProfileAlertsBuilder::class => new \App\Services\Alerts\AccountProfileAlertsBuilder(
                self::get(UserRepository::class),
                self::get(\App\Repositories\PersonnelProfileRepository::class),
            ),
            \App\Services\Alerts\RecruitmentRetroAlertsBuilder::class => new \App\Services\Alerts\RecruitmentRetroAlertsBuilder(
                self::get(\App\Repositories\EnlistmentRecruitmentEngagementRepository::class),
                self::get(\App\Repositories\EnlistmentRepository::class),
            ),
            \App\Repositories\ProbationOversightRepository::class => new \App\Repositories\ProbationOversightRepository(),
            \App\Services\Alerts\ProbationOverdueAlertsBuilder::class => new \App\Services\Alerts\ProbationOverdueAlertsBuilder(
                self::get(\App\Repositories\ProbationOversightRepository::class),
                self::get(\App\Repositories\RoleRepository::class),
            ),
            \App\Services\Alerts\AlertPresentationService::class => new \App\Services\Alerts\AlertPresentationService(
                self::get(\App\Repositories\PlatformAlertRepository::class),
                self::get(\App\Repositories\TenantAlertRepository::class),
                self::get(\App\Repositories\UserAlertDismissalRepository::class),
                self::get(TenantRepository::class),
            ),
            \App\Services\Alerts\MemberAlertsPageService::class => new \App\Services\Alerts\MemberAlertsPageService(
                self::get(\App\Services\Alerts\AlertPresentationService::class),
                self::get(\App\Services\Dashboard\TenantDashboardPinService::class),
                self::get(\App\Repositories\ForumTopicRepository::class),
            ),
            \App\Controllers\Web\MemberAlertsController::class => new \App\Controllers\Web\MemberAlertsController(
                self::get(\App\Services\Alerts\MemberAlertsPageService::class),
            ),
            \App\Controllers\Admin\System\SystemPlatformAlertsController::class => new \App\Controllers\Admin\System\SystemPlatformAlertsController(
                self::get(\App\Repositories\PlatformAlertRepository::class),
                self::get(\App\Repositories\UserRepository::class),
                self::get(\App\Services\EmailService::class),
            ),
            \App\Controllers\Admin\System\SystemAnalyticsController::class => new \App\Controllers\Admin\System\SystemAnalyticsController(
                self::get(\App\Repositories\TenantAnalyticsRepository::class),
            ),
            \App\Controllers\Admin\System\SystemNewsletterAdminController::class => new \App\Controllers\Admin\System\SystemNewsletterAdminController(
                self::get(\App\Repositories\NewsletterSubscriberRepository::class),
            ),
            \App\Controllers\Admin\System\SystemSubscriptionPlansController::class => new \App\Controllers\Admin\System\SystemSubscriptionPlansController(
                self::get(\App\Repositories\SubscriptionPlanRepository::class),
                new \App\Services\Audit\AuditService(),
            ),
            \App\Services\Integrations\DiscordWebhookService::class => new \App\Services\Integrations\DiscordWebhookService(),
            \App\Controllers\Admin\Organization\TenantAlertsController::class => new \App\Controllers\Admin\Organization\TenantAlertsController(
                self::get(\App\Repositories\TenantAlertRepository::class),
                self::get(TenantRepository::class),
                self::get(\App\Services\Integrations\DiscordWebhookService::class),
            ),
            \App\Controllers\Api\AlertDismissController::class => new \App\Controllers\Api\AlertDismissController(
                self::get(\App\Repositories\UserAlertDismissalRepository::class),
                self::get(\App\Repositories\PlatformAlertRepository::class),
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
                self::get(\App\Repositories\ForumPostReactionRepository::class),
                self::get(\App\Repositories\TrainingCertificateRepository::class),
                self::get(\App\Repositories\ForumAttachmentRepository::class),
                self::get(TenantRepository::class),
                self::get(\App\Repositories\InterteamMissionRepository::class),
                self::get(\App\Repositories\ForumNotificationRepository::class),
                self::get(UserRepository::class)
            ),
            \App\Services\Cooperation\CooperationWorkflowService::class => new \App\Services\Cooperation\CooperationWorkflowService(
                self::get(\App\Repositories\InterteamMissionRepository::class),
                self::get(\App\Repositories\UnitRepository::class),
            ),
            \App\Services\Interteam\InterteamCoopForumService::class => new \App\Services\Interteam\InterteamCoopForumService(
                self::get(\App\Repositories\InterteamMissionRepository::class),
                self::get(\App\Repositories\ForumCategoryRepository::class),
                self::get(\App\Repositories\ForumTopicRepository::class),
                self::get(\App\Repositories\ForumPostRepository::class),
                self::get(\App\Services\Cooperation\CooperationWorkflowService::class)
            ),
            \App\Controllers\Web\InterteamMissionWebController::class => new \App\Controllers\Web\InterteamMissionWebController(
                self::get(\App\Repositories\InterteamMissionRepository::class),
                self::get(TenantRepository::class),
                self::get(\App\Repositories\ForumTopicRepository::class),
                self::get(\App\Services\Interteam\InterteamCoopForumService::class),
                self::get(\App\Repositories\UnitRepository::class),
                self::get(UserRepository::class),
                self::get(\App\Services\EmailService::class),
                self::get(\App\Repositories\EmailTokenRepository::class),
                self::get(\App\Services\Cooperation\CooperationWorkflowService::class),
                self::get(\App\Services\Cooperation\CooperationCatalogService::class),
                self::get(\App\Services\Cooperation\CooperationAnnouncementDispatcher::class)
            ),
            \App\Controllers\Web\OperationalBoardController::class => new \App\Controllers\Web\OperationalBoardController(
                self::get(\App\Repositories\PlanningEntryRepository::class),
                self::get(UserRepository::class),
                self::get(\App\Repositories\CommunityEventRepository::class),
                self::get(\App\Repositories\InterteamMissionRepository::class),
                self::get(\App\Repositories\TrainingCourseRepository::class),
                self::get(\App\Repositories\UnitRepository::class),
                self::get(\App\Repositories\PersonnelJobRoleRepository::class),
            ),
            \App\Controllers\Admin\System\SystemCooperationCatalogController::class => new \App\Controllers\Admin\System\SystemCooperationCatalogController(
                self::get(\App\Repositories\CooperationCatalogRepository::class)
            ),
            \App\Controllers\Admin\System\SystemCooperationAnnouncementsController::class => new \App\Controllers\Admin\System\SystemCooperationAnnouncementsController(
                self::get(\App\Repositories\CooperationAnnouncementTemplateRepository::class)
            ),
            \App\Controllers\Web\CooperationCatalogWebController::class => new \App\Controllers\Web\CooperationCatalogWebController(
                self::get(\App\Repositories\CooperationCatalogRepository::class)
            ),
            \App\Controllers\Web\CooperationAnnouncementsWebController::class => new \App\Controllers\Web\CooperationAnnouncementsWebController(
                self::get(\App\Repositories\CooperationAnnouncementTemplateRepository::class)
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
                self::get(\App\Repositories\ForumPostReactionRepository::class),
                self::get(\App\Repositories\TrainingCertificateRepository::class),
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
                self::get(\App\Services\Documents\DocumentUploadService::class),
                self::get(\App\Repositories\ForumPostRepository::class),
                self::get(UserRepository::class),
                self::get(\App\Services\Moderation\ModerationService::class),
                self::get(\App\Services\Auth\AuthService::class)
            ),
            \App\Repositories\ForumVoteRepository::class => new \App\Repositories\ForumVoteRepository(),
            \App\Repositories\ForumPostReactionRepository::class => new \App\Repositories\ForumPostReactionRepository(),
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
                self::get(\App\Repositories\ForumPostReactionRepository::class),
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
                self::get(TenantRepository::class),
                self::get(\App\Repositories\SiteRoleAssignmentRepository::class)
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
                self::get(UserRepository::class),
                self::get(\App\Repositories\OrbatChartTypeRepository::class),
                self::get(\App\Repositories\PersonnelOrgHistoryRepository::class)
            ),
            \App\Controllers\Api\ForumModerationApiController::class => new \App\Controllers\Api\ForumModerationApiController(
                self::get(\App\Repositories\ForumTopicRepository::class),
                self::get(\App\Repositories\ForumPostRepository::class),
                self::get(\App\Services\Audit\AuditService::class)
            ),
            \App\Controllers\Api\ForumModerationReportInsightApiController::class => new \App\Controllers\Api\ForumModerationReportInsightApiController(
                self::get(\App\Repositories\ForumReportRepository::class),
                self::get(\App\Repositories\ForumPostRepository::class),
                self::get(UserRepository::class),
                self::get(\App\Services\Forum\ForumModerationEngine::class)
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
            \App\Repositories\ReconTransmissionSessionRepository::class => new \App\Repositories\ReconTransmissionSessionRepository(),
            \App\Repositories\ReconPvEntryRepository::class => new \App\Repositories\ReconPvEntryRepository(),
            \App\Repositories\ReconPoeDocumentRepository::class => new \App\Repositories\ReconPoeDocumentRepository(),
            \App\Controllers\Web\TransmissionController::class => new \App\Controllers\Web\TransmissionController(
                self::get(\App\Repositories\ReconTransmissionSessionRepository::class),
                self::get(\App\Repositories\ReconPvEntryRepository::class),
                self::get(\App\Repositories\ReconPoeDocumentRepository::class),
                self::get(\App\Repositories\CommunityEventRepository::class),
                self::get(AuthService::class),
            ),
            \App\Repositories\MapShapeRepository::class => new \App\Repositories\MapShapeRepository(),
            \App\Repositories\LaserCodeRepository::class => new \App\Repositories\LaserCodeRepository(),
            \App\Repositories\ArmaPlaytimeRepository::class => new \App\Repositories\ArmaPlaytimeRepository(),
            \App\Repositories\TacticalBriefingSlideRepository::class => new \App\Repositories\TacticalBriefingSlideRepository(),
            \App\Repositories\TacticalBriefingSlideCommentRepository::class => new \App\Repositories\TacticalBriefingSlideCommentRepository(),
            \App\Repositories\TacticalPhonePairingRepository::class => new \App\Repositories\TacticalPhonePairingRepository(),
            \App\Repositories\AarReportRepository::class => new \App\Repositories\AarReportRepository(),
            \App\Repositories\AtakOrderRepository::class => new \App\Repositories\AtakOrderRepository(),
            \App\Repositories\AtakOrderTemplateRepository::class => new \App\Repositories\AtakOrderTemplateRepository(),
            \App\Repositories\AtakOrderTypeRepository::class => new \App\Repositories\AtakOrderTypeRepository(),
            \App\Repositories\AtakOperatorIdRepository::class => new \App\Repositories\AtakOperatorIdRepository(),
            \App\Repositories\AtakMedicalTriageRepository::class => new \App\Repositories\AtakMedicalTriageRepository(),
            \App\Services\Tactical\AtakActivityLogService::class => new \App\Services\Tactical\AtakActivityLogService(),
            \App\Controllers\Admin\AdminAtakRealismController::class => new \App\Controllers\Admin\AdminAtakRealismController(
                self::get(\App\Repositories\AtakRealismRepository::class)
            ),
            \App\Controllers\Api\AtakRealismApiController::class => new \App\Controllers\Api\AtakRealismApiController(
                self::get(\App\Repositories\AtakRealismRepository::class),
                self::get(\App\Repositories\TacticalPhonePairingRepository::class)
            ),
            \App\Controllers\Admin\AdminAarReportsController::class => new \App\Controllers\Admin\AdminAarReportsController(
                self::get(\App\Repositories\AarReportRepository::class),
                new \App\Repositories\TheatreMissionCycleRepository()
            ),
            \App\Controllers\Api\AarReportsApiController::class => new \App\Controllers\Api\AarReportsApiController(
                self::get(\App\Repositories\AarReportRepository::class)
            ),
            \App\Repositories\RolePermissionMatrixRepository::class => new \App\Repositories\RolePermissionMatrixRepository(
                null,
                self::get(\App\Repositories\RoleRepository::class),
                self::get(\App\Repositories\PermissionRepository::class)
            ),
            \App\Services\Rbac\RolePermissionMatrixService::class => new \App\Services\Rbac\RolePermissionMatrixService(
                self::get(\App\Repositories\RolePermissionMatrixRepository::class),
                self::get(\App\Repositories\RoleRepository::class),
                self::get(\App\Repositories\PermissionRepository::class),
                self::get(\App\Services\Admin\RolePermissionService::class)
            ),
            \App\Controllers\Admin\Organization\RolePermissionMatrixController::class => new \App\Controllers\Admin\Organization\RolePermissionMatrixController(
                self::get(\App\Repositories\RolePermissionMatrixRepository::class),
                self::get(\App\Services\Rbac\RolePermissionMatrixService::class)
            ),
            \App\Controllers\Api\RolePermissionMatrixApiController::class => new \App\Controllers\Api\RolePermissionMatrixApiController(
                self::get(\App\Repositories\RolePermissionMatrixRepository::class),
                self::get(\App\Services\Rbac\RolePermissionMatrixService::class)
            ),
            \App\Repositories\EventRsvpNominativeRepository::class => new \App\Repositories\EventRsvpNominativeRepository(
                null,
                self::get(\App\Repositories\CommunityEventRepository::class)
            ),
            \App\Services\Attendance\EventRsvpNominativeService::class => new \App\Services\Attendance\EventRsvpNominativeService(
                self::get(\App\Repositories\EventRsvpNominativeRepository::class)
            ),
            \App\Controllers\Admin\Organization\EventRsvpNominativeAdminController::class => new \App\Controllers\Admin\Organization\EventRsvpNominativeAdminController(
                self::get(\App\Repositories\CommunityEventRepository::class),
                self::get(\App\Repositories\EventRsvpNominativeRepository::class),
                self::get(\App\Services\Attendance\EventRsvpNominativeService::class),
                self::get(\App\Services\Platform\FeatureGateService::class)
            ),
            \App\Controllers\Api\EventRsvpNominativeApiController::class => new \App\Controllers\Api\EventRsvpNominativeApiController(
                self::get(\App\Repositories\CommunityEventRepository::class),
                self::get(\App\Repositories\EventRsvpNominativeRepository::class),
                self::get(\App\Services\Attendance\EventRsvpNominativeService::class)
            ),
            \App\Controllers\Api\AtakApiController::class => new \App\Controllers\Api\AtakApiController(
                atak: self::get(\App\Repositories\AtakDataRepository::class),
                casRepo: self::get(\App\Repositories\CasNineLineRepository::class),
                reconRepo: self::get(\App\Repositories\ReconImageRepository::class),
                mapShapeRepo: self::get(\App\Repositories\MapShapeRepository::class),
                laserCodeRepo: self::get(\App\Repositories\LaserCodeRepository::class),
                tenantRepository: self::get(TenantRepository::class),
                userRepository: self::get(UserRepository::class),
                armaPlaytimeRepository: self::get(\App\Repositories\ArmaPlaytimeRepository::class),
                briefingSlideRepository: self::get(\App\Repositories\TacticalBriefingSlideRepository::class),
                briefingSlideCommentRepository: self::get(\App\Repositories\TacticalBriefingSlideCommentRepository::class),
                phonePairingRepository: self::get(\App\Repositories\TacticalPhonePairingRepository::class),
                activityLog: self::get(\App\Services\Tactical\AtakActivityLogService::class),
                gameLinkRepository: self::get(\App\Repositories\TacticalGameLinkRepository::class),
                tenantAtakConfigRepository: self::get(\App\Repositories\TenantAtakConfigRepository::class),
                unitRepository: self::get(\App\Repositories\UnitRepository::class),
                orderRepository: self::get(\App\Repositories\AtakOrderRepository::class),
                orderTemplateRepository: self::get(\App\Repositories\AtakOrderTemplateRepository::class),
                orderTypeRepository: self::get(\App\Repositories\AtakOrderTypeRepository::class),
                fireTeamRepository: self::get(\App\Repositories\FireTeamRepository::class),
                operatorIdRepository: self::get(\App\Repositories\AtakOperatorIdRepository::class),
                medicalTriageRepository: self::get(\App\Repositories\AtakMedicalTriageRepository::class),
                betaRegistrationRepository: self::get(\App\Repositories\AtakBetaRegistrationRepository::class),
                // modReportRepository lazy dans AtakApiController (pas au boot de toutes les routes ATAK).
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
            \App\Controllers\Api\OperationsApiController::class => new \App\Controllers\Api\OperationsApiController(
                self::get(\App\Services\Replay\ReplayService::class),
                self::get(\App\Services\Intel\IntelFusionService::class),
                self::get(\App\Repositories\AssetLogisticsRepository::class),
                self::get(\App\Services\Logistics\AssetLogisticsEvaluator::class),
                self::get(\App\Repositories\AtakDataRepository::class)
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
                self::get(\App\Services\Iff\IffValidationService::class),
                self::get(\App\Repositories\IffAssetStatusRepository::class)
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
                self::get(\App\Repositories\Courrier\CourrierDocumentNotificationRepository::class),
                self::get(\App\Services\Courrier\DocumentWorkflowService::class),
                self::get(\App\Services\Courrier\DocumentValidationService::class)
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
