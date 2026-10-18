<?php

declare(strict_types=1);

namespace App\Support;

use PDO;

/**
 * Applique bootstrap/training_url_delivery_migration.php à la première écriture qui en a besoin,
 * pour ne pas dépendre d’un run-migrations.php oublié après déploiement.
 */
final class TrainingUrlDeliverySchema
{
    private static bool $ensured = false;

    public static function ensure(PDO $pdo): void
    {
        if (self::$ensured) {
            return;
        }
        self::$ensured = true;
        $path = base_path('bootstrap/training_url_delivery_migration.php');
        if (!is_file($path)) {
            return;
        }
        $migrate = require $path;
        if (!is_callable($migrate)) {
            return;
        }
        ob_start();
        try {
            $migrate($pdo);
        } catch (\Throwable) {
            // L’écriture qui suit remontera sa propre erreur si le schéma manque encore.
        } finally {
            ob_end_clean();
        }
    }
}
