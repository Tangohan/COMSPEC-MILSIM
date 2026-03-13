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
