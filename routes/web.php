<?php

declare(strict_types=1);

use App\Controllers\Web\HomeController;
use App\Controllers\Web\PersonnelController;
use App\Controllers\Web\EnlistmentController;
use App\Controllers\Web\DocumentsController;
use App\Controllers\Web\TrainingController;
use App\Controllers\Web\AtakController;
use App\Controllers\Admin\AdminDashboardController;
use App\Controllers\Admin\AdminUsersController;
use App\Controllers\Auth\AuthController;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\GuestMiddleware;

return function (Router $router) {
    $router->get('/', [HomeController::class, 'index']);
    $router->get('/login', [AuthController::class, 'showLogin'], [GuestMiddleware::class]);
    $router->post('/login', [AuthController::class, 'login'], [GuestMiddleware::class]);
    $router->post('/logout', [AuthController::class, 'logout']);
    $router->get('/dashboard', [HomeController::class, 'dashboard'], [AuthMiddleware::class]);
    $router->get('/personnel/me', [PersonnelController::class, 'me'], [AuthMiddleware::class]);
    $router->get('/personnel/{id}', [PersonnelController::class, 'show'], [AuthMiddleware::class]);
    $router->get('/orbat', [PersonnelController::class, 'orbat'], [AuthMiddleware::class]);
    $router->get('/enlistment', [EnlistmentController::class, 'show']);
    $router->post('/enlistment', [EnlistmentController::class, 'store']);
    $router->get('/recrutement', [HomeController::class, 'recrutement']);
    $router->get('/documents', [DocumentsController::class, 'index'], [AuthMiddleware::class]);
    $router->get('/documents/{id}/download', [DocumentsController::class, 'download'], [AuthMiddleware::class]);
    $router->get('/formations', [TrainingController::class, 'index'], [AuthMiddleware::class]);
    $router->get('/formations/{slug}', [TrainingController::class, 'show'], [AuthMiddleware::class]);
    $router->get('/atak', [AtakController::class, 'index'], [AuthMiddleware::class]);
    $router->get('/admin', [AdminDashboardController::class, 'index'], [AuthMiddleware::class]);
    $router->get('/admin/users', [AdminUsersController::class, 'index'], [AuthMiddleware::class]);
    $router->get('/admin/users/create', [AdminUsersController::class, 'create'], [AuthMiddleware::class]);
};
