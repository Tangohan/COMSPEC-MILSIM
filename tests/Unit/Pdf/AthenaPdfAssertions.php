<?php

declare(strict_types=1);

namespace Tests\Unit\Pdf;

use App\Support\Pdf\AthenaPdfTheme;
use App\Support\Pdf\AthenaTcpdf;

/**
 * Vérifications communes des PDF au gabarit Athena : PDF valide, toutes les pages en A4 portrait,
 * en-tête et pied de page dessinés sur chaque page. Si la variable d'environnement
 * ATHENA_PDF_SAMPLES_DIR est définie, le PDF y est aussi écrit (exemples à relire).
 */
trait AthenaPdfAssertions
{
    /** Pages A4 portrait (MediaBox) ; renvoie le nombre de pages. */
    private static function assertA4Portrait(string $pdf): int
    {
        self::assertStringStartsWith('%PDF-', $pdf, 'binaire PDF');
        preg_match_all('#/MediaBox\s*\[\s*([\d.]+)\s+([\d.]+)\s+([\d.]+)\s+([\d.]+)\s*\]#', $pdf, $m, PREG_SET_ORDER);
        self::assertNotEmpty($m, 'au moins une page');
        foreach ($m as $box) {
            $w = (float) $box[3] - (float) $box[1];
            $h = (float) $box[4] - (float) $box[2];
            self::assertEqualsWithDelta(595.28, $w, 1.0, 'largeur A4 portrait');
            self::assertEqualsWithDelta(841.89, $h, 1.0, 'hauteur A4 portrait');
        }

        // La MediaBox peut aussi figurer sur l'arbre /Pages : on compte les objets /Type /Page.
        return max(1, preg_match_all('#/Type\s*/Page(?![a-zA-Z])#', $pdf));
    }

    /** PDF TCPDF : portrait + en-tête et pied sur chaque page. */
    private static function assertAthenaTcpdf(string $pdf, string $sampleName): void
    {
        $pages = self::assertA4Portrait($pdf);
        self::assertGreaterThanOrEqual($pages, AthenaTcpdf::$lastStamp['headers'], 'en-tête Athena sur chaque page');
        self::assertGreaterThanOrEqual($pages, AthenaTcpdf::$lastStamp['footers'], 'pied de page Athena sur chaque page');
        self::saveSample($pdf, $sampleName);
    }

    /** PDF Dompdf : portrait + en-tête/pied fixes du gabarit. */
    private static function assertAthenaDompdf(string $pdf, string $sampleName): void
    {
        self::assertA4Portrait($pdf);
        self::assertTrue(AthenaPdfTheme::$lastDompdfStamp['header'], 'en-tête Athena');
        self::assertTrue(AthenaPdfTheme::$lastDompdfStamp['footer'], 'pied de page Athena');
        self::saveSample($pdf, $sampleName);
    }

    private static function saveSample(string $pdf, string $name): void
    {
        $dir = getenv('ATHENA_PDF_SAMPLES_DIR');
        if (is_string($dir) && $dir !== '' && is_dir($dir)) {
            file_put_contents(rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $name . '.pdf', $pdf);
        }
    }
}
