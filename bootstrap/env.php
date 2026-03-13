<?php

declare(strict_types=1);

/**
 * Charge le fichier .env dans $_ENV (sans Composer/Dotenv).
 */
function load_env(string $root): void
{
    $path = $root . DIRECTORY_SEPARATOR . '.env';
    if (!is_file($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value, " \t\"'");
        $_ENV[$name] = $value;
        putenv("$name=$value");
    }
}
