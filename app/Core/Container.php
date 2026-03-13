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
            \App\Controllers\Auth\AuthController::class => new \App\Controllers\Auth\AuthController(
                self::get(AuthService::class),
                self::get(RbacService::class),
                self::get(TenantRepository::class)
            ),
            \App\Controllers\Web\PersonnelController::class => new \App\Controllers\Web\PersonnelController(
                self::get(AuthService::class),
                self::get(UserRepository::class),
                self::get(\App\Repositories\PersonnelExtrasRepository::class),
                self::get(UnitRepository::class)
            ),
            \App\Repositories\PersonnelExtrasRepository::class => new \App\Repositories\PersonnelExtrasRepository(),
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
            \App\Repositories\TrainingRepository::class => new \App\Repositories\TrainingRepository(),
            \App\Controllers\Web\TrainingController::class => new \App\Controllers\Web\TrainingController(
                self::get(\App\Repositories\TrainingRepository::class)
            ),
            \App\Services\Tactical\AtakTokenService::class => new \App\Services\Tactical\AtakTokenService(),
            \App\Controllers\Web\AtakController::class => new \App\Controllers\Web\AtakController(
                self::get(\App\Services\Tactical\AtakTokenService::class)
            ),
            \App\Controllers\Admin\AdminUsersController::class => new \App\Controllers\Admin\AdminUsersController(
                self::get(UserRepository::class)
            ),
            default => throw new \InvalidArgumentException("Unknown service: $id"),
        };
    }
}
