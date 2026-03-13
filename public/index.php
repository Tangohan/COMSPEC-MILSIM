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

// Mode maintenance : si activé et IP non autorisée, afficher 503 et arrêter
$maintenance = $GLOBALS['__app_config']['maintenance'] ?? [];
if (!empty($maintenance['enabled'])) {
    $clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    if (is_string($clientIp) && str_contains($clientIp, ',')) {
        $clientIp = trim(explode(',', $clientIp)[0]);
    }
    $allowedIps = $maintenance['allowed_ips'] ?? [];
    if (!is_array($allowedIps) || !in_array($clientIp, $allowedIps, true)) {
        $message = $maintenance['message'] ?? 'Maintenance en cours. Merci de réessayer dans quelques minutes.';
        $viewPath = $root . '/views/errors/503.php';
        header('HTTP/1.1 503 Service Unavailable');
        header('Retry-After: 300');
        header('Content-Type: text/html; charset=utf-8');
        if (is_file($viewPath)) {
            require $viewPath;
        } else {
            echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Maintenance</title></head><body><h1>Maintenance</h1><p>' . htmlspecialchars($message) . '</p></body></html>';
        }
        exit;
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
