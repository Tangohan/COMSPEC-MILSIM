<?php
declare(strict_types=1);

// Afficher les erreurs en cas de 500 (désactiver en production une fois stable)
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

$root = dirname(__DIR__);
// Erreur fatale : l’afficher au lieu d’une page 500 vide
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err !== null && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }
        echo '<pre style="background:#fdd;padding:1em;white-space:pre-wrap;">';
        echo 'ERREUR FATALE: ' . htmlspecialchars($err['message'] ?? '') . "\n";
        echo "Fichier: " . htmlspecialchars($err['file'] ?? '') . "\nLigne: " . ($err['line'] ?? '');
        echo '</pre>';
    }
});

require $root . '/bootstrap/app.php';

$requestPath = \App\Core\Request::normalizePathFromServer();
$maintenanceSafelist = [
    '/api/stripe/webhook',
    '/api/health',
];
if (!in_array($requestPath, $maintenanceSafelist, true)) {
    try {
        $pdo = \App\Core\Database::getPdo();
        $maintenanceRepo = new \App\Repositories\MaintenanceRepository($pdo);
        if ($maintenanceRepo->tableExists()) {
            \App\Core\Session::start();
            $userContext = null;
            if (\App\Core\Session::get('user_id')) {
                $rbac = \App\Core\Container::get(\App\Services\Rbac\RbacService::class);
                $roleId = \App\Core\Session::get('role_id');
                $email = \App\Core\Session::get('email');
                $rbac->setPermissionsForGate(
                    $roleId ? (int) $roleId : null,
                    $email !== null && $email !== '' ? (string) $email : null
                );
                $userRepo = \App\Core\Container::get(\App\Repositories\UserRepository::class);
                $slug = $userRepo->getRoleSlugForUser((int) \App\Core\Session::get('user_id'));
                $userContext = ['role_slug' => $slug];
            }
            $module = detect_current_module($requestPath);
            $guard = new \App\Support\MaintenanceGuard(new \App\Support\MaintenanceService($pdo));
            $guard->enforce($requestPath, $module, $userContext);
        }
    } catch (\Throwable) {
        // BDD indisponible ou erreur transitoire : ne pas verrouiller tout le site
    }
}

try {
    $config = $GLOBALS['__app_config'];

    $app = new \App\Core\Application();
    $routes = require $root . '/routes/web.php';
    $routes($app->router());
    $app->run();
} catch (Throwable $e) {
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<pre style="background:#fdd;padding:1em;white-space:pre-wrap;">';
    echo 'ERREUR: ' . htmlspecialchars($e->getMessage()) . "\n\n";
    echo htmlspecialchars($e->getFile() . ':' . $e->getLine() . "\n\n" . $e->getTraceAsString());
    echo '</pre>';
}
