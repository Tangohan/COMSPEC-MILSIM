<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/bootstrap/env.php';
load_env($root);

$appDebug = filter_var($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOLEAN);
$showErrors = $appDebug;

if ($showErrors) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}

// Erreur fatale : journaliser, alerte e-mail, réponse HTTP
register_shutdown_function(function () use ($showErrors, $root) {
    $err = error_get_last();
    if ($err === null || !in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    $msg = 'ERREUR FATALE: ' . ($err['message'] ?? '') . ' — ' . ($err['file'] ?? '') . ':' . ($err['line'] ?? '');
    error_log($msg);

    if (class_exists(\App\Services\Monitoring\ErrorReportMailer::class)) {
        try {
            $rid = (string) (getenv('REQUEST_ID') ?: ($_ENV['REQUEST_ID'] ?? ''));
            (new \App\Services\Monitoring\ErrorReportMailer())->reportFatal($err, $rid !== '' ? $rid : null);
        } catch (Throwable) {
        }
    }

    if (headers_sent()) {
        return;
    }

    $path = class_exists(\App\Core\Request::class)
        ? \App\Core\Request::normalizePathFromServer()
        : '/';
    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
    $wantsJson = str_starts_with($path, '/api/') || str_contains($accept, 'application/json');

    if ($wantsJson) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'error' => 'server_error',
            'message' => 'Une erreur est survenue. Merci de réessayer plus tard.',
        ], JSON_UNESCAPED_UNICODE);

        return;
    }

    header('Content-Type: text/html; charset=utf-8');
    if (!$showErrors) {
        http_response_code(500);
        $view500 = $root . '/views/errors/500.php';
        if (is_file($view500)) {
            require $view500;

            return;
        }
        echo '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><title>Erreur</title></head><body><p>Une erreur est survenue. Réessayez plus tard.</p></body></html>';

        return;
    }
    echo '<pre style="background:#fdd;padding:1em;white-space:pre-wrap;">';
    echo 'ERREUR FATALE: ' . htmlspecialchars($err['message'] ?? '') . "\n";
    echo 'Fichier: ' . htmlspecialchars($err['file'] ?? '') . "\nLigne: " . ($err['line'] ?? '');
    echo '</pre>';
});

require $root . '/bootstrap/app.php';

$requestPath = \App\Core\Request::normalizePathFromServer();
$maintenanceSafelist = [
    '/api/stripe/webhook',
    '/api/health',
];
$maintenancePrefixSafelist = [
    '/calendrier/abonnement/',
];
$maintenanceSkipped = in_array($requestPath, $maintenanceSafelist, true);
if (!$maintenanceSkipped) {
    foreach ($maintenancePrefixSafelist as $pfx) {
        if (str_starts_with($requestPath, $pfx)) {
            $maintenanceSkipped = true;
            break;
        }
    }
}
if (!$maintenanceSkipped) {
    try {
        $pdo = \App\Core\Database::getPdo();
        $maintenanceRepo = new \App\Repositories\MaintenanceRepository($pdo);
        if ($maintenanceRepo->tableExists()) {
            \App\Core\Session::start();
            $userContext = null;
            if (\App\Core\Session::get('user_id')) {
                $rbac = \App\Core\Container::get(\App\Services\Rbac\RbacService::class);
                $userRepo = \App\Core\Container::get(\App\Repositories\UserRepository::class);
                $uid = (int) \App\Core\Session::get('user_id');
                $u = $userRepo->findById($uid, null);
                if ($u) {
                    $rbac->setPermissionsForGateFromUserRow($u, $userRepo);
                    $slug = $userRepo->getRoleSlugForUser($uid);
                    $userContext = ['role_slug' => $slug];
                }
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
    error_log($e->getMessage() . ' — ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());
    try {
        $rid = (string) (getenv('REQUEST_ID') ?: ($_ENV['REQUEST_ID'] ?? ''));
        (new \App\Services\Monitoring\ErrorReportMailer())->reportThrowable($e, $rid !== '' ? $rid : null);
    } catch (Throwable) {
    }

    $path = \App\Core\Request::normalizePathFromServer();
    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
    $wantsJson = str_starts_with($path, '/api/') || str_contains($accept, 'application/json');

    if (!headers_sent()) {
        if ($wantsJson) {
            header('Content-Type: application/json; charset=utf-8');
        } else {
            header('Content-Type: text/html; charset=utf-8');
        }
    }

    if ($wantsJson) {
        http_response_code(500);
        echo json_encode([
            'error' => 'server_error',
            'message' => 'Une erreur est survenue. Merci de réessayer plus tard.',
        ], JSON_UNESCAPED_UNICODE);
    } elseif ($showErrors) {
        echo '<pre style="background:#fdd;padding:1em;white-space:pre-wrap;">';
        echo 'ERREUR: ' . htmlspecialchars($e->getMessage()) . "\n\n";
        echo htmlspecialchars($e->getFile() . ':' . $e->getLine() . "\n\n" . $e->getTraceAsString());
        echo '</pre>';
    } else {
        http_response_code(500);
        echo '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><title>Erreur</title></head><body><p>Une erreur est survenue. Réessayez plus tard.</p></body></html>';
    }
}
