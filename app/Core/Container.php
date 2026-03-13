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
                self::get(\App\Repositories\UserProfileRepository::class)
            ),
            \App\Controllers\Web\PersonnelController::class => new \App\Controllers\Web\PersonnelController(
                self::get(AuthService::class),
                self::get(UserRepository::class),
                self::get(\App\Repositories\PersonnelExtrasRepository::class),
                self::get(\App\Repositories\UnitRepository::class),
                self::get(\App\Repositories\GradeRepository::class),
                self::get(\App\Repositories\PersonnelAdminPanelRepository::class),
                self::get(\App\Repositories\PersonnelAdminDataRepository::class),
                self::get(\App\Services\Personnel\MatriculeService::class)
            ),
            \App\Repositories\PersonnelExtrasRepository::class => new \App\Repositories\PersonnelExtrasRepository(),
            \App\Repositories\GradeRepository::class => new \App\Repositories\GradeRepository(),
            \App\Repositories\TenantMatriculeConfigRepository::class => new \App\Repositories\TenantMatriculeConfigRepository(),
            \App\Services\Personnel\MatriculeService::class => new \App\Services\Personnel\MatriculeService(
                self::get(\App\Repositories\TenantMatriculeConfigRepository::class),
                self::get(\App\Repositories\PersonnelExtrasRepository::class)
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
            \App\Controllers\Web\DocumentsController::class => new \App\Controllers\Web\DocumentsController(
                self::get(\App\Repositories\DocumentRepository::class)
            ),
            \App\Repositories\ModpackRepository::class => new \App\Repositories\ModpackRepository(),
            \App\Controllers\Web\ModpackController::class => new \App\Controllers\Web\ModpackController(
                self::get(\App\Repositories\ModpackRepository::class)
            ),
            \App\Repositories\TrainingRepository::class => new \App\Repositories\TrainingRepository(),
            \App\Controllers\Web\TrainingController::class => new \App\Controllers\Web\TrainingController(
                self::get(\App\Repositories\TrainingRepository::class)
            ),
            \App\Services\Tactical\AtakTokenService::class => new \App\Services\Tactical\AtakTokenService(),
            \App\Repositories\TenantAtakConfigRepository::class => new \App\Repositories\TenantAtakConfigRepository(),
            \App\Controllers\Web\AtakController::class => new \App\Controllers\Web\AtakController(
                self::get(\App\Services\Tactical\AtakTokenService::class),
                self::get(\App\Repositories\TenantAtakConfigRepository::class)
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
            \App\Repositories\ForumCategoryRepository::class => new \App\Repositories\ForumCategoryRepository(),
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
                self::get(\App\Repositories\ForumPostRepository::class)
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
            default => throw new \InvalidArgumentException("Unknown service: $id"),
        };
    }
}
