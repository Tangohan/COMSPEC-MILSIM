<?php

declare(strict_types=1);

use App\Controllers\Web\HomeController;
use App\Controllers\Web\HubController;
use App\Controllers\Web\PersonnelController;
use App\Controllers\Web\EnlistmentController;
use App\Controllers\Web\DocumentsController;
use App\Controllers\Web\EquipmentController;
use App\Controllers\Web\TrainingController;
use App\Controllers\Web\AtakController;
use App\Controllers\Web\AccountController;
use App\Controllers\Web\ModpackController;
use App\Controllers\Web\ForumController;
use App\Controllers\Web\ForumCategoryController;
use App\Controllers\Web\ForumTopicController;
use App\Controllers\Web\ForumNewTopicController;
use App\Controllers\Web\ForumModerationController;
use App\Controllers\Api\ForumApiController;
use App\Controllers\Api\ForumModerationApiController;
use App\Controllers\Api\ForumUploadController;
use App\Controllers\Api\AtakIntelController;
use App\Controllers\Api\AtakApiController;
use App\Controllers\Api\FireSupportController;
use App\Controllers\Api\DangerZoneController;
use App\Controllers\Api\LogisticsController;
use App\Controllers\Api\IntelController;
use App\Controllers\Api\ReplayController;
use App\Controllers\Api\IffController;
use App\Controllers\Api\HealthController;
use App\Controllers\Admin\AdminDashboardController;
use App\Controllers\Admin\AdminHubController;
use App\Controllers\Admin\AdminUsersController;
use App\Controllers\Admin\AdminUnitsController;
use App\Controllers\Admin\AdminModpackController;
use App\Controllers\Admin\AdminAtakConfigController;
use App\Controllers\Admin\AdminAtakModController;
use App\Controllers\Admin\AdminConfigurationController;
use App\Controllers\Admin\AdminRecruitmentsController;
use App\Controllers\Admin\AdminDocumentsController;
use App\Controllers\Admin\AdminTrainingController;
use App\Controllers\Courrier\CourrierDashboardController;
use App\Controllers\Courrier\CourrierEditorController;
use App\Controllers\Courrier\CourrierTemplateController;
use App\Controllers\Courrier\CourrierPresetController;
use App\Controllers\Courrier\CourrierWorkflowController;
use App\Controllers\Courrier\CourrierPdfController;
use App\Controllers\Courrier\CourrierSignatureController;
use App\Controllers\Admin\System\SystemDashboardController;
use App\Controllers\Admin\System\SystemRoleController;
use App\Controllers\Admin\System\SystemSettingsController;
use App\Controllers\Admin\System\SystemAuditController;
use App\Controllers\Admin\Organization\OrganizationDashboardController;
use App\Controllers\Admin\Organization\OrganizationPlaceholderController;
use App\Controllers\Admin\Organization\UserAdminController;
use App\Controllers\Admin\Organization\RoleAdminController;
use App\Controllers\Admin\Organization\CategoryAdminController;
use App\Controllers\Admin\Organization\GradeReferentielController;
use App\Controllers\Admin\Organization\GroupAdminController;
use App\Controllers\Admin\Organization\TeamAdminController;
use App\Controllers\Auth\AuthController;
use App\Controllers\Api\TrainingApiController;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\GuestMiddleware;
use App\Middleware\ForumModerateMiddleware;
use App\Middleware\SystemAdminMiddleware;
use App\Middleware\OrganizationAdminMiddleware;

return function (Router $router) {
    $router->get('/', [HomeController::class, 'index']);
    $router->get('/login', [AuthController::class, 'showLogin'], [GuestMiddleware::class]);
    $router->post('/login', [AuthController::class, 'login'], [GuestMiddleware::class]);
    $router->post('/logout', [AuthController::class, 'logout']);
    $router->get('/forgot-password', [AuthController::class, 'showForgotPassword'], [GuestMiddleware::class]);
    $router->post('/forgot-password', [AuthController::class, 'sendResetLink'], [GuestMiddleware::class]);
    $router->get('/reset-password', [AuthController::class, 'showResetPassword'], [GuestMiddleware::class]);
    $router->post('/reset-password', [AuthController::class, 'processResetPassword'], [GuestMiddleware::class]);
    $router->get('/dashboard', [HomeController::class, 'dashboard'], [AuthMiddleware::class]);
    $router->get('/hub', [HubController::class, 'index'], [AuthMiddleware::class]);
    $router->get('/account', [AccountController::class, 'index'], [AuthMiddleware::class]);
    $router->get('/account/preferences', [AccountController::class, 'preferences'], [AuthMiddleware::class]);
    $router->post('/account/preferences', [AccountController::class, 'preferences'], [AuthMiddleware::class]);
    $router->get('/account/mail', [AccountController::class, 'mail'], [AuthMiddleware::class]);
    $router->post('/account/mail', [AccountController::class, 'mail'], [AuthMiddleware::class]);
    $router->get('/account/image', [AccountController::class, 'image'], [AuthMiddleware::class]);
    $router->post('/account/image', [AccountController::class, 'image'], [AuthMiddleware::class]);
    $router->get('/account/portrait', [AccountController::class, 'portrait'], [AuthMiddleware::class]);
    $router->post('/account/portrait', [AccountController::class, 'portrait'], [AuthMiddleware::class]);
    $router->get('/account/password', [AccountController::class, 'password'], [AuthMiddleware::class]);
    $router->post('/account/password', [AccountController::class, 'password'], [AuthMiddleware::class]);
    $router->get('/personnel/me', [PersonnelController::class, 'me'], [AuthMiddleware::class]);
    $router->get('/personnel/me/edit', [PersonnelController::class, 'edit'], [AuthMiddleware::class]);
    $router->get('/personnel/{id}', [PersonnelController::class, 'show'], [AuthMiddleware::class]);
    $router->get('/personnel/{id}/edit', [PersonnelController::class, 'edit'], [AuthMiddleware::class]);
    $router->post('/personnel/{id}/update', [PersonnelController::class, 'update'], [AuthMiddleware::class]);
    $router->post('/personnel/{id}/generate-matricule', [PersonnelController::class, 'generateMatricule'], [AuthMiddleware::class]);
    $router->post('/personnel/{id}/notes', [PersonnelController::class, 'updateNotes'], [AuthMiddleware::class]);
    $router->get('/orbat', [PersonnelController::class, 'orbat'], [AuthMiddleware::class]);
    $router->get('/enlistment', [EnlistmentController::class, 'show']);
    $router->post('/enlistment', [EnlistmentController::class, 'store']);
    $router->get('/enlistment/success', [EnlistmentController::class, 'success']);
    $router->get('/enlistment/error', [EnlistmentController::class, 'error']);
    $router->get('/recrutement', [HomeController::class, 'recrutement']);
    $router->get('/equipement', [HomeController::class, 'equipement']);
    $router->get('/documents', [DocumentsController::class, 'index'], [AuthMiddleware::class]);
    // Gestion documentaire (liste, détail, édition, historique, accès, arborescence) — accès par permissions documents.*
    $router->get('/documents/gestion', [AdminDocumentsController::class, 'index'], [AuthMiddleware::class]);
    $router->get('/documents/gestion/ajout', [AdminDocumentsController::class, 'uploadForm'], [AuthMiddleware::class]);
    $router->post('/documents/gestion/ajout', [AdminDocumentsController::class, 'upload'], [AuthMiddleware::class]);
    $router->get('/documents/gestion/arborescence', [AdminDocumentsController::class, 'tree'], [AuthMiddleware::class]);
    $router->get('/documents/gestion/{id}', [AdminDocumentsController::class, 'show'], [AuthMiddleware::class]);
    $router->get('/documents/gestion/{id}/modifier', [AdminDocumentsController::class, 'edit'], [AuthMiddleware::class]);
    $router->post('/documents/gestion/{id}/modifier', [AdminDocumentsController::class, 'update'], [AuthMiddleware::class]);
    $router->post('/documents/gestion/{id}/nouvelle-version', [AdminDocumentsController::class, 'newVersion'], [AuthMiddleware::class]);
    $router->post('/documents/gestion/{id}/archiver', [AdminDocumentsController::class, 'archive'], [AuthMiddleware::class]);
    $router->get('/documents/gestion/{id}/historique', [AdminDocumentsController::class, 'history'], [AuthMiddleware::class]);
    $router->get('/documents/gestion/{id}/acces', [AdminDocumentsController::class, 'access'], [AuthMiddleware::class]);
    $router->get('/documents/{id}/file', [DocumentsController::class, 'file'], [AuthMiddleware::class]);
    $router->get('/documents/{id}/download', [DocumentsController::class, 'download'], [AuthMiddleware::class]);
    $router->get('/documents/{slug}', [DocumentsController::class, 'show'], [AuthMiddleware::class]);
    $router->get('/equipment', [EquipmentController::class, 'index'], [AuthMiddleware::class]);
    $router->get('/equipment/{slug}', [EquipmentController::class, 'show'], [AuthMiddleware::class]);
    $router->get('/modpacks', [ModpackController::class, 'index'], [AuthMiddleware::class]);
    $router->get('/modpacks/images/{id}', [ModpackController::class, 'image'], [AuthMiddleware::class]);
    $router->get('/modpacks/{id}/download', [ModpackController::class, 'download'], [AuthMiddleware::class]);
    $router->get('/modpacks/{slug}', [ModpackController::class, 'show'], [AuthMiddleware::class]);
    $router->get('/formations', [TrainingController::class, 'index'], [AuthMiddleware::class]);
    $router->get('/formations/mes-formations', [TrainingController::class, 'myTraining'], [AuthMiddleware::class]);
    $router->get('/formations/lesson/{id}', [TrainingController::class, 'lesson'], [AuthMiddleware::class]);
    $router->get('/formations/quiz/{id}', [TrainingController::class, 'quiz'], [AuthMiddleware::class]);
    $router->get('/formations/certificate/{id}', [TrainingController::class, 'certificate'], [AuthMiddleware::class]);
    $router->get('/formations/{slug}', [TrainingController::class, 'showBySlug'], [AuthMiddleware::class]);
    $router->get('/atak', [AtakController::class, 'index'], [AuthMiddleware::class]);
    $router->get('/atak/setup', [AtakController::class, 'setup'], [AuthMiddleware::class]);
    $router->get('/atak/mod/download', [AtakController::class, 'downloadMod'], [AuthMiddleware::class]);
    $router->get('/atak/tuto', [AtakController::class, 'tuto'], [AuthMiddleware::class]);
    $router->get('/tacmap', [HomeController::class, 'tacmap'], [AuthMiddleware::class]);
    $router->get('/overwatch', [HomeController::class, 'overwatch'], [AuthMiddleware::class]);
    // Hub admin (choix système / organisation) — redirige si un seul accès
    $router->get('/admin', [AdminHubController::class, 'index'], [AuthMiddleware::class]);
    // Administration système (super-admin uniquement)
    $router->get('/admin/system', [SystemDashboardController::class, 'index'], [AuthMiddleware::class, SystemAdminMiddleware::class]);
    $router->get('/admin/system/roles', [SystemRoleController::class, 'index'], [AuthMiddleware::class, SystemAdminMiddleware::class]);
    $router->get('/admin/system/roles/{id}', [SystemRoleController::class, 'show'], [AuthMiddleware::class, SystemAdminMiddleware::class]);
    $router->get('/admin/system/roles/{id}/edit', [SystemRoleController::class, 'edit'], [AuthMiddleware::class, SystemAdminMiddleware::class]);
    $router->post('/admin/system/roles/{id}/update', [SystemRoleController::class, 'update'], [AuthMiddleware::class, SystemAdminMiddleware::class]);
    $router->get('/admin/system/settings', [SystemSettingsController::class, 'index'], [AuthMiddleware::class, SystemAdminMiddleware::class]);
    $router->get('/admin/system/audit', [SystemAuditController::class, 'index'], [AuthMiddleware::class, SystemAdminMiddleware::class]);
    // Administration organisationnelle
    $router->get('/admin/organization', [OrganizationDashboardController::class, 'index'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]);
    $router->get('/admin/organization/users', [UserAdminController::class, 'index'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]);
    $router->get('/admin/organization/users/create', [UserAdminController::class, 'create'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]);
    $router->post('/admin/organization/users/store', [UserAdminController::class, 'store'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]);
    $router->get('/admin/organization/users/{id}', [UserAdminController::class, 'show'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]);
    $router->get('/admin/organization/users/{id}/edit', [UserAdminController::class, 'edit'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]);
    $router->post('/admin/organization/users/{id}/update', [UserAdminController::class, 'update'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]);
    $router->post('/admin/organization/users/{id}/deactivate', [UserAdminController::class, 'deactivate'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]);
    $router->get('/admin/organization/roles', [RoleAdminController::class, 'index'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]);
    $router->get('/admin/organization/roles/{id}', [RoleAdminController::class, 'show'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]);
    $router->get('/admin/organization/categories', [CategoryAdminController::class, 'index'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]);
    $router->get('/admin/organization/categories/create', [CategoryAdminController::class, 'create'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]);
    $router->post('/admin/organization/categories/store', [CategoryAdminController::class, 'store'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]);
    $router->get('/admin/organization/categories/{id}/edit', [CategoryAdminController::class, 'edit'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]);
    $router->post('/admin/organization/categories/{id}/update', [CategoryAdminController::class, 'update'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]);
    $router->get('/admin/organization/referentiels/grades', [GradeReferentielController::class, 'index'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]);
    $router->get('/admin/organization/referentiels/grades/create', [GradeReferentielController::class, 'create'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]);
    $router->post('/admin/organization/referentiels/grades/store', [GradeReferentielController::class, 'store'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]);
    $router->get('/admin/organization/referentiels/grades/{id}/edit', [GradeReferentielController::class, 'edit'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]);
    $router->post('/admin/organization/referentiels/grades/{id}/update', [GradeReferentielController::class, 'update'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]);
    $router->post('/admin/organization/referentiels/grades/{id}/deactivate', [GradeReferentielController::class, 'deactivate'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]);
    $router->get('/admin/organization/groups', [GroupAdminController::class, 'index'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]);
    $router->get('/admin/organization/groups/create', [GroupAdminController::class, 'create'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]);
    $router->post('/admin/organization/groups/store', [GroupAdminController::class, 'store'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]);
    $router->get('/admin/organization/groups/{id}', [GroupAdminController::class, 'show'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]);
    $router->get('/admin/organization/groups/{id}/edit', [GroupAdminController::class, 'edit'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]);
    $router->post('/admin/organization/groups/{id}/update', [GroupAdminController::class, 'update'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]);
    $router->post('/admin/organization/groups/{id}/delete', [GroupAdminController::class, 'delete'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]);
    $router->get('/admin/organization/teams', [TeamAdminController::class, 'index'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]);
    $router->get('/admin/organization/teams/create', [TeamAdminController::class, 'create'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]);
    $router->post('/admin/organization/teams/store', [TeamAdminController::class, 'store'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]);
    $router->get('/admin/organization/teams/{id}', [TeamAdminController::class, 'show'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]);
    $router->get('/admin/organization/teams/{id}/edit', [TeamAdminController::class, 'edit'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]);
    $router->post('/admin/organization/teams/{id}/update', [TeamAdminController::class, 'update'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]);
    $router->post('/admin/organization/teams/{id}/delete', [TeamAdminController::class, 'delete'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]);
    // Anciennes routes admin — redirections vers le centre organisationnel
    $router->get('/admin/users', fn (\App\Core\Request $r, array $p) => \App\Core\Response::redirect(url('admin/organization/users')), [AuthMiddleware::class]);
    $router->get('/admin/users/create', fn (\App\Core\Request $r, array $p) => \App\Core\Response::redirect(url('admin/organization/users/create')), [AuthMiddleware::class]);
    $router->get('/admin/units', fn (\App\Core\Request $r, array $p) => \App\Core\Response::redirect(url('admin/organization/groups')), [AuthMiddleware::class]);
    $router->get('/admin/units/create', fn (\App\Core\Request $r, array $p) => \App\Core\Response::redirect(url('admin/organization/groups/create')), [AuthMiddleware::class]);
    $router->get('/admin/units/{id}/edit', fn (\App\Core\Request $r, array $p) => \App\Core\Response::redirect(url('admin/organization/groups/' . ($p['id'] ?? '') . '/edit')), [AuthMiddleware::class]);
    $router->post('/admin/units/store', [AdminUnitsController::class, 'store'], [AuthMiddleware::class]);
    $router->post('/admin/units/{id}/update', [AdminUnitsController::class, 'update'], [AuthMiddleware::class]);
    $router->post('/admin/units/{id}/delete', [AdminUnitsController::class, 'delete'], [AuthMiddleware::class]);
    $router->get('/admin/modpacks', [AdminModpackController::class, 'index'], [AuthMiddleware::class]);
    $router->get('/admin/modpacks/create', [AdminModpackController::class, 'create'], [AuthMiddleware::class]);
    $router->post('/admin/modpacks/store', [AdminModpackController::class, 'store'], [AuthMiddleware::class]);
    $router->get('/admin/modpacks/{id}/edit', [AdminModpackController::class, 'edit'], [AuthMiddleware::class]);
    $router->post('/admin/modpacks/{id}/update', [AdminModpackController::class, 'update'], [AuthMiddleware::class]);
    $router->post('/admin/modpacks/{id}/delete', [AdminModpackController::class, 'delete'], [AuthMiddleware::class]);
    $router->get('/admin/atak-config', [AdminAtakConfigController::class, 'index'], [AuthMiddleware::class]);
    $router->post('/admin/atak-config', [AdminAtakConfigController::class, 'store'], [AuthMiddleware::class]);
    $router->get('/admin/atak-mod', [AdminAtakModController::class, 'index'], [AuthMiddleware::class]);
    $router->post('/admin/atak-mod/upload', [AdminAtakModController::class, 'upload'], [AuthMiddleware::class]);
    $router->post('/admin/atak-mod/delete', [AdminAtakModController::class, 'delete'], [AuthMiddleware::class]);
    $router->get('/admin/configuration', [AdminConfigurationController::class, 'index'], [AuthMiddleware::class]);
    $router->get('/admin/recruitments', [AdminRecruitmentsController::class, 'index'], [AuthMiddleware::class]);
    $router->get('/admin/training', [AdminTrainingController::class, 'dashboard'], [AuthMiddleware::class]);
    $router->get('/admin/training/courses', [AdminTrainingController::class, 'courses'], [AuthMiddleware::class]);
    $router->get('/admin/training/enrollments', [AdminTrainingController::class, 'enrollments'], [AuthMiddleware::class]);
    $router->get('/admin/training/reports', [AdminTrainingController::class, 'reports'], [AuthMiddleware::class]);
    $router->get('/admin/training/certificates', [AdminTrainingController::class, 'certificates'], [AuthMiddleware::class]);
    $router->get('/admin/training/audit', [AdminTrainingController::class, 'audit'], [AuthMiddleware::class]);
    $router->get('/admin/forum-config', [\App\Controllers\Admin\AdminForumConfigController::class, 'index'], [AuthMiddleware::class]);

    // Bureau Courrier / Correspondance Officielle
    $router->get('/courrier', [CourrierDashboardController::class, 'index'], [AuthMiddleware::class]);
    $router->get('/courrier/editor', [CourrierEditorController::class, 'index'], [AuthMiddleware::class]);
    $router->get('/courrier/editor/{id}', [CourrierEditorController::class, 'edit'], [AuthMiddleware::class]);
    $router->get('/courrier/read/{id}', [\App\Controllers\Courrier\CourrierReadController::class, 'show'], [AuthMiddleware::class]);
    $router->post('/courrier/editor/save', [CourrierEditorController::class, 'save'], [AuthMiddleware::class]);
    $router->get('/courrier/templates', [CourrierTemplateController::class, 'index'], [AuthMiddleware::class]);
    $router->get('/courrier/templates/create', [CourrierTemplateController::class, 'create'], [AuthMiddleware::class]);
    $router->post('/courrier/templates', [CourrierTemplateController::class, 'store'], [AuthMiddleware::class]);
    $router->get('/courrier/templates/{id}/edit', [CourrierTemplateController::class, 'edit'], [AuthMiddleware::class]);
    $router->post('/courrier/templates/{id}', [CourrierTemplateController::class, 'update'], [AuthMiddleware::class]);
    $router->get('/courrier/presets', [CourrierPresetController::class, 'index'], [AuthMiddleware::class]);
    $router->post('/courrier/presets/{id}/default', [CourrierPresetController::class, 'setDefault'], [AuthMiddleware::class]);
    $router->post('/courrier/documents/{id}/workflow', [CourrierWorkflowController::class, 'transition'], [AuthMiddleware::class]);
    $router->post('/courrier/documents/{id}/sign', [CourrierSignatureController::class, 'sign'], [AuthMiddleware::class]);
    $router->get('/courrier/documents/{id}/verify', [CourrierSignatureController::class, 'verify'], [AuthMiddleware::class]);
    $router->get('/courrier/documents/{id}/signature-image', [CourrierSignatureController::class, 'documentSignatureImage'], [AuthMiddleware::class]);
    $router->get('/courrier/verify', [CourrierSignatureController::class, 'verifyByUuid']);
    $router->get('/courrier/my-signatures', [CourrierSignatureController::class, 'mySignatures'], [AuthMiddleware::class]);
    $router->get('/courrier/signatures/{id}/image', [CourrierSignatureController::class, 'signatureImage'], [AuthMiddleware::class]);
    $router->get('/courrier/documents/{id}/print', [CourrierPdfController::class, 'print'], [AuthMiddleware::class]);
    $router->get('/courrier/history', [CourrierDashboardController::class, 'history'], [AuthMiddleware::class]);
    $router->get('/courrier/archives', [CourrierDashboardController::class, 'archives'], [AuthMiddleware::class]);

    // Forum
    $router->get('/forum', [ForumController::class, 'index'], [AuthMiddleware::class]);
    $router->get('/forum/category/{slug}', [ForumCategoryController::class, 'show'], [AuthMiddleware::class]);
    $router->get('/forum/topic/{id}', [ForumTopicController::class, 'show'], [AuthMiddleware::class]);
    $router->post('/forum/topic/{id}/reply', [ForumTopicController::class, 'reply'], [AuthMiddleware::class]);
    $router->post('/forum/topic/{id}/subscribe', [ForumTopicController::class, 'subscribe'], [AuthMiddleware::class]);
    $router->post('/forum/topic/{id}/unsubscribe', [ForumTopicController::class, 'unsubscribe'], [AuthMiddleware::class]);
    $router->get('/forum/new-topic', [ForumNewTopicController::class, 'form'], [AuthMiddleware::class]);
    $router->post('/forum/new-topic', [ForumNewTopicController::class, 'store'], [AuthMiddleware::class]);
    $router->get('/forum/moderation', [ForumModerationController::class, 'index'], [AuthMiddleware::class, ForumModerateMiddleware::class]);
    $router->post('/forum/report/{id}/handle', [ForumModerationController::class, 'handleReport'], [AuthMiddleware::class, ForumModerateMiddleware::class]);
    $router->post('/forum/topic/{id}/lock', [ForumModerationController::class, 'lockTopic'], [AuthMiddleware::class, ForumModerateMiddleware::class]);
    $router->post('/forum/topic/{id}/unlock', [ForumModerationController::class, 'unlockTopic'], [AuthMiddleware::class, ForumModerateMiddleware::class]);
    $router->post('/forum/topic/{id}/pin', [ForumModerationController::class, 'pinTopic'], [AuthMiddleware::class, ForumModerateMiddleware::class]);
    $router->post('/forum/topic/{id}/unpin', [ForumModerationController::class, 'unpinTopic'], [AuthMiddleware::class, ForumModerateMiddleware::class]);

    // API Health (BDD) — pour la page ATAK État de santé
    $router->get('/api/health', [HealthController::class, 'index'], [AuthMiddleware::class]);

    // API Training (LMS)
    $router->get('/api/training/catalogue', [TrainingApiController::class, 'catalogue'], [AuthMiddleware::class]);
    $router->get('/api/training/courses/{id}', [TrainingApiController::class, 'courseDetail'], [AuthMiddleware::class]);
    $router->post('/api/training/enroll', [TrainingApiController::class, 'enroll'], [AuthMiddleware::class]);
    $router->get('/api/training/enrollments/{id}/progress', [TrainingApiController::class, 'progress'], [AuthMiddleware::class]);
    $router->post('/api/training/progress/lesson', [TrainingApiController::class, 'progressLesson'], [AuthMiddleware::class]);
    $router->post('/api/training/quiz/start', [TrainingApiController::class, 'quizStart'], [AuthMiddleware::class]);
    $router->get('/api/training/quiz/attempts/{id}', [TrainingApiController::class, 'quizAttempt'], [AuthMiddleware::class]);
    $router->post('/api/training/quiz/submit', [TrainingApiController::class, 'quizSubmit'], [AuthMiddleware::class]);
    $router->get('/api/training/certificates/enrollment/{id}', [TrainingApiController::class, 'certificateByEnrollment'], [AuthMiddleware::class]);
    $router->get('/api/training/certificates/{id}/download', [TrainingApiController::class, 'certificateDownload'], [AuthMiddleware::class]);
    $router->get('/api/training/admin/courses', [TrainingApiController::class, 'adminCourses'], [AuthMiddleware::class]);
    $router->post('/api/training/admin/courses', [TrainingApiController::class, 'adminCourseSave'], [AuthMiddleware::class]);
    $router->post('/api/training/admin/courses/{id}', [TrainingApiController::class, 'adminCourseSave'], [AuthMiddleware::class]);
    $router->post('/api/training/admin/assign', [TrainingApiController::class, 'adminAssign'], [AuthMiddleware::class]);

    // API ATAK Full PHP (parité Node — polling, pas de Socket.IO)
    $router->get('/api/atak/ping', [AtakApiController::class, 'ping']);
    $router->get('/api/atak/whoami', [AtakApiController::class, 'whoami']);
    $router->get('/api/atak/stats', [AtakApiController::class, 'stats']);
    $router->get('/api/markers', [AtakApiController::class, 'markersIndex']);
    $router->post('/api/markers', [AtakApiController::class, 'markersStore']);
    $router->delete('/api/markers/{id}', [AtakApiController::class, 'markersDelete']);
    $router->get('/api/atak/markers', [AtakApiController::class, 'markersIndex']);
    $router->post('/api/atak/marker', [AtakApiController::class, 'markerUpsert']);
    $router->get('/api/units', [AtakApiController::class, 'unitsIndex']);
    $router->post('/api/units', [AtakApiController::class, 'unitsStore']);
    $router->patch('/api/units/{id}', [AtakApiController::class, 'unitsUpdate']);
    $router->post('/api/atak/position', [AtakApiController::class, 'position']);
    $router->get('/api/chat', [AtakApiController::class, 'chatIndex']);
    $router->post('/api/chat', [AtakApiController::class, 'chatStore']);
    $router->get('/api/pings', [AtakApiController::class, 'pingsIndex']);
    $router->post('/api/pings', [AtakApiController::class, 'pingsStore']);
    $router->get('/api/nine-line', [AtakApiController::class, 'nineLineIndex']);
    $router->post('/api/nine-line', [AtakApiController::class, 'nineLineStore']);
    $router->patch('/api/nine-line/{id}', [AtakApiController::class, 'nineLineUpdate']);
    // CAS / 9-Line C2
    $router->get('/api/cas', [AtakApiController::class, 'casIndex']);
    $router->post('/api/cas', [AtakApiController::class, 'casStore']);
    $router->get('/api/cas/{id}', [AtakApiController::class, 'casShow']);
    $router->patch('/api/cas/{id}', [AtakApiController::class, 'casUpdate']);
    $router->post('/api/cas/{id}/ack', [AtakApiController::class, 'casAck']);
    $router->post('/api/cas/{id}/check-line', [AtakApiController::class, 'casCheckLine']);
    $router->post('/api/cas/{id}/status', [AtakApiController::class, 'casStatus']);
    // Recon images (Cams)
    $router->get('/api/recon/images', [AtakApiController::class, 'reconImagesIndex']);
    $router->post('/api/recon/images', [AtakApiController::class, 'reconImagesStore']);
    $router->get('/api/recon/images/{id}', [AtakApiController::class, 'reconImagesShow']);
    $router->post('/api/recon/images/{id}/link-cas', [AtakApiController::class, 'reconImagesLinkCas']);
    // Map shapes (live drawing)
    $router->get('/api/map-shapes', [AtakApiController::class, 'mapShapesIndex']);
    $router->post('/api/map-shapes', [AtakApiController::class, 'mapShapesStore']);
    $router->patch('/api/map-shapes/{id}', [AtakApiController::class, 'mapShapesUpdate']);
    $router->delete('/api/map-shapes/{id}', [AtakApiController::class, 'mapShapesDelete']);
    // Laser codes
    $router->get('/api/atak/laser-codes', [AtakApiController::class, 'laserCodesIndex']);
    $router->post('/api/atak/laser-codes', [AtakApiController::class, 'laserCodesStore']);
    // Flight manifest
    $router->get('/api/flight-manifest', [AtakApiController::class, 'airAssetsIndex']);
    $router->get('/api/atak/designator', [AtakApiController::class, 'designatorIndex']);
    $router->post('/api/atak/designator', [AtakApiController::class, 'designatorStore']);
    $router->post('/api/atak/sigint', [AtakApiController::class, 'sigintStore']);
    $router->get('/api/atak/sigint/zones', [AtakApiController::class, 'sigintZones']);
    $router->get('/api/intel/photos', [AtakApiController::class, 'intelPhotosIndex']);
    $router->post('/api/intel/photos', [AtakApiController::class, 'intelPhotosStore']);
    $router->post('/api/atak/flight-manifest', [AtakApiController::class, 'flightManifestStore']);
    $router->get('/api/atak/air-assets', [AtakApiController::class, 'airAssetsIndex']);
    $router->patch('/api/atak/air-assets/{callsign}/pilot-status', [AtakApiController::class, 'airAssetsPilotStatus']);

    // API C2 — Fire Support
    $router->post('/api/fire-support/calculate', [FireSupportController::class, 'calculate']);
    $router->get('/api/fire-support/units', [FireSupportController::class, 'units']);

    // API C2 — Danger Zones
    $router->get('/api/danger-zones', [DangerZoneController::class, 'index']);
    $router->post('/api/danger-zones', [DangerZoneController::class, 'store']);
    $router->put('/api/danger-zones/{id}', [DangerZoneController::class, 'update']);
    $router->delete('/api/danger-zones/{id}', [DangerZoneController::class, 'delete']);

    // API C2 — Logistics
    $router->post('/api/logistics/update', [LogisticsController::class, 'update']);
    $router->get('/api/logistics/assets', [LogisticsController::class, 'assets']);

    // API C2 — Intel (SITREP fusion)
    $router->post('/api/intel/report', [IntelController::class, 'report']);
    $router->get('/api/intel/fused', [IntelController::class, 'fused']);

    // API C2 — Replay
    $router->get('/api/replay/mission/{missionId}', [ReplayController::class, 'mission']);
    $router->get('/api/replay/events/{missionId}', [ReplayController::class, 'events']);

    // API C2 — IFF
    $router->post('/api/iff/respond', [IffController::class, 'respond']);
    $router->get('/api/iff/current', [IffController::class, 'current']);
    $router->get('/api/iff/assets', [IffController::class, 'assets']);

    // API ATAK Intel (legacy — redirige vers atak_intel, gardé pour compat)
    $router->post('/api/atak/intel', [AtakIntelController::class, 'storeIntel']);

    // API Forum (JSON)
    $router->get('/api/forum', [ForumApiController::class, 'handle'], [AuthMiddleware::class]);
    $router->post('/api/forum', [ForumApiController::class, 'handle'], [AuthMiddleware::class]);
    $router->post('/api/forum-moderation', [ForumModerationApiController::class, 'handle'], [AuthMiddleware::class, ForumModerateMiddleware::class]);
    $router->post('/api/forum-upload', [ForumUploadController::class, 'handle'], [AuthMiddleware::class]);
    $router->post('/api/admin/forum-categories', [\App\Controllers\Admin\ForumCategoriesApiController::class, 'handle'], [AuthMiddleware::class]);
    $router->post('/api/admin/site-settings', [\App\Controllers\Admin\SiteSettingsApiController::class, 'handle'], [AuthMiddleware::class]);
    $router->post('/api/admin/forum-moderation', [\App\Controllers\Admin\ForumModerationAdminApiController::class, 'handle'], [AuthMiddleware::class]);
};
