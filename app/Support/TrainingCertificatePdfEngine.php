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
}
