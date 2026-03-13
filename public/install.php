<?php
declare(strict_types=1);

/**
 * Point d'entrée web pour l'installation — ne charge pas vendor/ ni l'app.
 * Envoie 200 tout de suite pour que l'hébergeur ne remplace pas par une page 500.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('log_errors', '1');

http_response_code(200);
header('Content-Type: text/html; charset=utf-8');

$installRoot = dirname(__DIR__);

// En cas d'erreur fatale, afficher le message avant que PHP n'envoie le 500
register_shutdown_function(function () use ($installRoot) {
    $err = error_get_last();
    if ($err !== null && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Erreur installation</title></head><body><pre style="background:#fdd;padding:1em;">';
        echo "ERREUR FATALE\n\n";
        echo htmlspecialchars($err['message'] ?? '') . "\n\n";
        echo "Fichier : " . htmlspecialchars($err['file'] ?? '') . "\n";
        echo "Ligne   : " . ($err['line'] ?? '') . "\n";
        echo '</pre></body></html>';
    }
});

set_exception_handler(function (Throwable $e) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Erreur installation</title></head><body><pre style="background:#fdd;padding:1em;">';
    echo "EXCEPTION : " . htmlspecialchars($e->getMessage()) . "\n\n";
    echo htmlspecialchars($e->getTraceAsString());
    echo '</pre></body></html>';
    exit(1);
});

try {
    require $installRoot . '/install.php';
} catch (Throwable $e) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Erreur installation</title></head><body><pre style="background:#fdd;padding:1em;">';
    echo "ERREUR : " . htmlspecialchars($e->getMessage()) . "\n\n";
    echo htmlspecialchars($e->getTraceAsString());
    echo '</pre></body></html>';
    exit(1);
}
