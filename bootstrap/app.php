<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap/autoload.php';

// Merge config for global config() helper
$configPath = dirname(__DIR__) . '/app/Config';
$config = [];
foreach (['app', 'database', 'auth', 'maintenance', 'units', 'forum'] as $name) {
    $file = $configPath . '/' . $name . '.php';
    if (is_file($file)) {
        $config[$name] = require $file;
    }
}

// Store in global for helpers (used by config())
$GLOBALS['__app_config'] = $config;

App\Core\ExceptionHandler::register();

return $config;
