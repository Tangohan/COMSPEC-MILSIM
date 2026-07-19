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
            \App\Services\Notifications\ActivityHubPresentationService::class => new \App\Services\Notifications\ActivityHubPresentationService(),
            \App\Repositories\AsyncJobRepository::class => new \App\Repositories\AsyncJobRepository(),
            \App\Repositories\TenantApiKeyRepository::class => new \App\Repositories\TenantApiKeyRepository(),
            \App\Controllers\Web\ActivityHubController::class => new \App\Controllers\Web\ActivityHubController(
                Container::get(\App\Repositories\ForumNotificationRepository::class),
                Container::get(\App\Repositories\Courrier\CourrierDocumentNotificationRepository::class),
                Container::get(\App\Repositories\TenantMessageRepository::class),
                Container::get(\App\Services\Notifications\ActivityHubPresentationService::class),
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
            default => null,
        };
    }
}
