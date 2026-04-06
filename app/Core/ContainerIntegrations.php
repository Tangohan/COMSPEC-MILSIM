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
                Container::get(\App\Services\Notifications\ActivityHubPresentationService::class),
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
            ),
            \App\Controllers\Admin\Organization\ComplianceBundleExportController::class => new \App\Controllers\Admin\Organization\ComplianceBundleExportController(
                Container::get(\App\Services\Auth\AuthService::class),
                Container::get(\App\Repositories\TrainingEnrollmentRepository::class),
                Container::get(\App\Services\Audit\AuditService::class),
            ),
            default => null,
        };
    }
}
