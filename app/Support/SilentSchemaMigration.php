<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Database;
use PDO;
use Throwable;

/**
 * Exécute une migration bootstrap sans polluer la sortie HTTP (echo des logs).
 */
final class SilentSchemaMigration
{
    /** @param list<string> $absolutePaths */
    public static function runMany(array $absolutePaths, ?PDO $pdo = null): void
    {
        foreach ($absolutePaths as $path) {
            self::run((string) $path, $pdo);
        }
    }

    public static function run(string $absolutePath, ?PDO $pdo = null): void
    {
        if ($absolutePath === '' || !is_file($absolutePath)) {
            return;
        }

        try {
            $migrate = require_once $absolutePath;
            $named = 'run_' . basename($absolutePath, '.php');
            if (!is_callable($migrate) && function_exists($named)) {
                $migrate = $named;
            }
            if (!is_callable($migrate)) {
                return;
            }
            $pdo ??= Database::getPdo();
            if (!$pdo instanceof PDO) {
                return;
            }
            $silent = static function (string $_message): void {
                // Intentionally silent on web requests.
            };
            ob_start();
            try {
                // PHP 8 : trop d’arguments → ArgumentCountError sur les closures à 1 param.
                $paramCount = (new \ReflectionFunction($migrate))->getNumberOfParameters();
                if ($paramCount >= 2) {
                    $migrate($pdo, $silent);
                } else {
                    $migrate($pdo);
                }
            } finally {
                if (ob_get_level() > 0) {
                    ob_end_clean();
                }
            }
        } catch (Throwable) {
            // L’appelant gère les erreurs SQL métier.
        }
    }
}
