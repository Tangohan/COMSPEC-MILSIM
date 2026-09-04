<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Liaisons DI (intégrations, activité, conformité, terrain) — extrait du gros Container.
 */
final class ContainerIntegrations
{
    public static function tryResolve(string $id): ?object
    {
        return match ($id) {
            Gate::class => Gate::getInstance(),
            \App\Controllers\Admin\Organization\OrganizationProgressionHubController::class => new \App\Controllers\Admin\Organization\OrganizationProgressionHubController(
                Container::get(\App\Services\Auth\AuthService::class),
                Gate::getInstance(),
            ),
            \App\Controllers\Admin\Organization\OrganizationMemberNumberController::class => new \App\Controllers\Admin\Organization\OrganizationMemberNumberController(
                Container::get(\App\Services\Auth\AuthService::class),
                Container::get(\App\Services\Personnel\TenantMemberNumberService::class),
                Container::get(\App\Repositories\UserRepository::class),
                Gate::getInstance(),
            ),
            \App\Controllers\Admin\Organization\OrganizationCallsignSequencesController::class => new \App\Controllers\Admin\Organization\OrganizationCallsignSequencesController(
                Container::get(\App\Services\Auth\AuthService::class),
                Container::get(\App\Services\Personnel\CallsignSequenceService::class),
                Gate::getInstance(),
            ),
            \App\Repositories\OrganizationCatalogRepository::class => new \App\Repositories\OrganizationCatalogRepository(),
            \App\Services\OrganizationCatalog\OrganizationCatalogService::class => new \App\Services\OrganizationCatalog\OrganizationCatalogService(
                Container::get(\App\Repositories\OrganizationCatalogRepository::class),
                Container::get(\App\Repositories\UnitRepository::class),
                Container::get(\App\Repositories\PersonnelJobRoleRepository::class),
                Container::get(\App\Repositories\RoleRepository::class),
                Container::get(\App\Repositories\PermissionRepository::class),
                Container::get(\App\Services\Admin\TenantRolePermissionPresetService::class),
                Container::get(\App\Repositories\TenantRepository::class),
            ),
            \App\Controllers\Admin\Organization\OrganizationCatalogController::class => new \App\Controllers\Admin\Organization\OrganizationCatalogController(
                Container::get(\App\Services\Auth\AuthService::class),
                Container::get(\App\Services\OrganizationCatalog\OrganizationCatalogService::class),
                Gate::getInstance(),
            ),
            \App\Services\Notifications\ActivityHubPresentationService::class => new \App\Services\Notifications\ActivityHubPresentationService(),
            \App\Repositories\AsyncJobRepository::class => new \App\Repositories\AsyncJobRepository(),
            \App\Repositories\TenantApiKeyRepository::class => new \App\Repositories\TenantApiKeyRepository(),
            \App\Controllers\Web\ActivityHubController::class => new \App\Controllers\Web\ActivityHubController(
                Container::get(\App\Repositories\ForumNotificationRepository::class),
                Container::get(\App\Repositories\Courrier\CourrierDocumentNotificationRepository::class),
                Container::get(\App\Repositories\TenantMessageRepository::class),
                Container::get(\App\Services\Notifications\ActivityHubPresentationService::class),
                Container::get(\App\Services\Alerts\AlertPresentationService::class),
            ),
            \App\Services\Alerts\MemberAlertsPageService::class => new \App\Services\Alerts\MemberAlertsPageService(
                Container::get(\App\Services\Alerts\AlertPresentationService::class),
                Container::get(\App\Services\Dashboard\TenantDashboardPinService::class),
                Container::get(\App\Repositories\ForumTopicRepository::class),
            ),
            \App\Controllers\Web\MemberAlertsController::class => new \App\Controllers\Web\MemberAlertsController(
                Container::get(\App\Services\Alerts\MemberAlertsPageService::class),
            ),
            \App\Controllers\Web\CommunityCalendarFeedController::class => new \App\Controllers\Web\CommunityCalendarFeedController(
                Container::get(\App\Repositories\CommunityEventRepository::class),
                Container::get(\App\Services\Platform\FeatureGateService::class),
            ),
            \App\Controllers\Web\OperateurTerrainController::class => new \App\Controllers\Web\OperateurTerrainController(
                Container::get(\App\Services\Auth\AuthService::class),
                Container::get(\App\Repositories\ModpackRepository::class),
                Container::get(\App\Services\Dashboard\TenantDashboardPinService::class),
            ),
            \App\Controllers\Api\IntegrationsPublicEventsController::class => new \App\Controllers\Api\IntegrationsPublicEventsController(
                Container::get(\App\Repositories\CommunityEventRepository::class),
                Container::get(\App\Services\Platform\FeatureGateService::class),
            ),
            \App\Controllers\Admin\Organization\OrganizationIntegrationsController::class => new \App\Controllers\Admin\Organization\OrganizationIntegrationsController(
                Container::get(\App\Services\Auth\AuthService::class),
                Container::get(\App\Repositories\TenantApiKeyRepository::class),
                Container::get(\App\Services\Platform\FeatureGateService::class),
            ),
            \App\Controllers\Admin\Organization\ComplianceBundleExportController::class => new \App\Controllers\Admin\Organization\ComplianceBundleExportController(
                Container::get(\App\Services\Auth\AuthService::class),
                Container::get(\App\Repositories\TrainingEnrollmentRepository::class),
                Container::get(\App\Services\Audit\AuditService::class),
            ),
            \App\Controllers\Web\OperationalBoardController::class => new \App\Controllers\Web\OperationalBoardController(
                new \App\Repositories\PlanningEntryRepository(),
                Container::get(\App\Repositories\UserRepository::class),
                new \App\Repositories\CommunityEventRepository(),
                new \App\Repositories\InterteamMissionRepository(),
                Container::get(\App\Repositories\TrainingCourseRepository::class),
                Container::get(\App\Repositories\UnitRepository::class),
                new \App\Repositories\PersonnelJobRoleRepository(),
            ),
            \App\Services\Communications\TenantEmailDispatchService::class => new \App\Services\Communications\TenantEmailDispatchService(
                new \App\Services\Communications\TenantEmailRecipientResolver(
                    Container::get(\App\Repositories\UnitRepository::class),
                    Container::get(\App\Repositories\UserRepository::class),
                ),
                new \App\Services\Communications\TenantEmailRenderService(
                    Container::get(\App\Services\Courrier\TemplateVariableService::class),
                    Container::get(\App\Repositories\UnitRepository::class),
                ),
                new \App\Repositories\TenantEmailCampaignRepository(),
                Container::get(\App\Repositories\UserRepository::class),
                Container::get(\App\Repositories\UserNotificationPreferencesRepository::class),
                Container::get(\App\Services\EmailService::class),
            ),
            \App\Controllers\Admin\Organization\TenantCommunicationsController::class => new \App\Controllers\Admin\Organization\TenantCommunicationsController(
                new \App\Repositories\TenantEmailTemplateRepository(),
                new \App\Repositories\TenantEmailRecipientGroupRepository(),
                new \App\Repositories\TenantEmailCampaignRepository(),
                Container::get(\App\Services\Communications\TenantEmailDispatchService::class),
                new \App\Services\Communications\TenantEmailRenderService(
                    Container::get(\App\Services\Courrier\TemplateVariableService::class),
                    Container::get(\App\Repositories\UnitRepository::class),
                ),
                Container::get(\App\Repositories\UnitRepository::class),
                Container::get(\App\Repositories\UserRepository::class),
                Container::get(\App\Repositories\RoleRepository::class),
            ),
            \App\Services\Game\GameOverwatchExperienceService::class => new \App\Services\Game\GameOverwatchExperienceService(),
            \App\Repositories\AthenaAccountRepository::class => new \App\Repositories\AthenaAccountRepository(),
            \App\Services\Game\GameAuthService::class => new \App\Services\Game\GameAuthService(
                Container::get(\App\Repositories\AthenaAccountRepository::class),
                Container::get(\App\Repositories\UserRepository::class),
                Container::get(\App\Repositories\TenantRepository::class),
                Container::get(\App\Services\EmailService::class),
                Container::get(\App\Services\Game\GameOverwatchExperienceService::class),
            ),
            \App\Controllers\Api\Game\GameAuthApiController::class => new \App\Controllers\Api\Game\GameAuthApiController(
                Container::get(\App\Services\Game\GameAuthService::class),
            ),
            \App\Repositories\AtakDeviceAuthRepository::class => new \App\Repositories\AtakDeviceAuthRepository(),
            \App\Services\Atak\AtakDeviceAuthService::class => new \App\Services\Atak\AtakDeviceAuthService(
                Container::get(\App\Repositories\AtakDeviceAuthRepository::class),
                Container::get(\App\Services\Game\GameAuthService::class),
                Container::get(\App\Repositories\AtakRealismRepository::class),
            ),
            \App\Controllers\Web\AtakDeviceSecurityController::class => new \App\Controllers\Web\AtakDeviceSecurityController(
                Container::get(\App\Services\Auth\AuthService::class),
                Container::get(\App\Services\Atak\AtakDeviceAuthService::class),
                Container::get(\App\Repositories\AtakDeviceAuthRepository::class),
                new \App\Services\Security\FileRateLimiter(),
            ),
            \App\Controllers\Api\AtakDeviceAuthApiController::class => new \App\Controllers\Api\AtakDeviceAuthApiController(
                Container::get(\App\Services\Atak\AtakDeviceAuthService::class),
                new \App\Services\Security\FileRateLimiter(),
            ),
            \App\Repositories\OperationWorkspaceRepository::class => new \App\Repositories\OperationWorkspaceRepository(),
            \App\Services\Operations\OperationWorkspaceService::class => new \App\Services\Operations\OperationWorkspaceService(
                Container::get(\App\Repositories\OperationWorkspaceRepository::class),
            ),
            \App\Controllers\Web\OperationWorkspaceController::class => new \App\Controllers\Web\OperationWorkspaceController(
                Container::get(\App\Services\Operations\OperationWorkspaceService::class),
                Container::get(\App\Repositories\OperationWorkspaceRepository::class),
            ),
            \App\Controllers\Api\Game\GameOperationsApiController::class => new \App\Controllers\Api\Game\GameOperationsApiController(
                Container::get(\App\Services\Game\GameAuthService::class),
                Container::get(\App\Services\Operations\OperationWorkspaceService::class),
            ),
            default => null,
        };
    }
}
