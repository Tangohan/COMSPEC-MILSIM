<?php

declare(strict_types=1);

namespace Tests\Unit\Pdf;

use App\Repositories\Courrier\DocumentPresetRepository;
use App\Services\Courrier\DocumentBuilderService;
use App\Services\Courrier\DocumentExportService;
use PHPUnit\Framework\TestCase;

/**
 * Courrier officiel : garde le papier à en-tête de la communauté, mais toujours en A4 portrait,
 * police embarquée (accents) et pagination du gabarit — même pour un modèle « A4 paysage ».
 * (Sans doublure de classe finale : l'exemple peut ainsi être généré hors BypassFinals, dont le flux
 * de fichiers altère des glyphes Dompdf.)
 */
final class CourrierPdfTemplateTest extends TestCase
{
    use AthenaPdfAssertions;

    public function testCourrierPdfIsPortraitEvenForLandscapePreset(): void
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            self::markTestSkipped('Dompdf indisponible');
        }
        $builder = $this->createStub(DocumentBuilderService::class);
        $builder->method('buildPreviewHtml')->willReturn(
            '<div class="courrier-preview a4-landscape" data-orientation="landscape"><div class="courrier-preview-inner">'
            . '<p><b>RÉFÉRENCE</b> : N° 042/SOAR/BCO</p><p>Objet : désignation d’un chef d’équipe — « Alpha Assault ».</p>'
            . str_repeat('<p>Le commandant d’unité certifie que l’intéressé a satisfait aux épreuves. Œuvre, élève, à bientôt.</p>', 60)
            . '</div></div>'
        );
        $service = new DocumentExportService(
            $builder,
            $this->createStub(DocumentPresetRepository::class)
        );

        $pdf = (string) $service->renderPdfBinary(['reference_number' => 'N° 042/SOAR/BCO', 'body_rendered' => '']);

        self::assertGreaterThan(1, self::assertA4Portrait($pdf), 'plusieurs pages, toutes portrait');
        self::saveSample($pdf, 'courrier-officiel');
    }
}
