<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Détection et chargement du moteur PDF des attestations (TCPDF embarqué ou Dompdf Composer).
 */
final class TrainingCertificatePdfEngine
{
    private static bool $tcpdfLoadAttempted = false;

    public static function isAvailable(): bool
    {
        return self::ensureTcpdfLoaded() || class_exists(\Dompdf\Dompdf::class);
    }

    public static function prefersTcpdf(): bool
    {
        return self::ensureTcpdfLoaded();
    }

    public static function ensureTcpdfLoaded(): bool
    {
        if (class_exists(\TCPDF::class, false)) {
            return true;
        }
        if (self::$tcpdfLoadAttempted) {
            return false;
        }
        self::$tcpdfLoadAttempted = true;
        $path = base_path('tcpdf/tcpdf.php');
        if (!is_file($path) || !is_readable($path)) {
            return false;
        }
        require_once $path;

        return class_exists(\TCPDF::class, false);
    }

    /**
     * Exécute du code qui utilise TCPDF sans laisser les avertissements E_DEPRECATED
     * polluer la sortie (PHP 8+ : anciennes signatures TCPDF 6.3 si le déploiement n’a pas les fichiers corrigés).
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public static function suppressTcpdfPhpDeprecationsWhile(callable $callback): mixed
    {
        $level = error_reporting();
        error_reporting($level & ~E_DEPRECATED);
        try {
            return $callback();
        } finally {
            error_reporting($level);
        }
    }
}
