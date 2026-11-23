<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Sse\SseDocumentPdfService;
use App\Support\SseRichText;
use App\Support\TrainingCertificatePdfEngine;
use PHPUnit\Framework\TestCase;
use Tests\Support\PdfText;

/** Export PDF d'un document SSE rédigé avec l'éditeur : même chaîne PDF que le reste du SSE, texte lisible, sauts de page. */
final class SseDocumentPdfTest extends TestCase
{
    public function testRichDocumentIsExportedWithBannerTitleAndAllPages(): void
    {
        if (!TrainingCertificatePdfEngine::ensureTcpdfLoaded() || !TrainingCertificatePdfEngine::tcpdfCertificateFontsReady()) {
            self::markTestSkipped('TCPDF et ses polices requis');
        }
        $body = SseRichText::wrap(
            '<h2>Situation</h2><p>Premier <strong>paragraphe</strong> <script>alert(1)</script>avec <em>emphase</em>.</p>'
            . '<ul><li>puce alpha</li></ul><table><tbody><tr><td>cellule A</td><td>cellule B</td></tr></tbody></table>'
            . '<div class="sse-pagebreak"></div><p>Texte de la page deux, dossier <span class="sse-field" data-field="dossier">x</span>.</p>'
        );
        $pdf = (new SseDocumentPdfService())->build([
            'reference_code' => 'DOC-2026-0001', 'title' => 'Note de test', 'document_type_label' => 'Note d’analyse',
            'classification_label' => 'Confidentiel', 'status_label' => 'Brouillon', 'case_reference' => 'SSE-2026-0042',
            'author_label' => 'Rédacteur', 'body' => $body,
        ], 0);
        self::assertStringStartsWith('%PDF', $pdf);
        $text = PdfText::of($pdf);
        foreach (['DOC-2026-0001', 'Note de test', 'Premier', 'puce alpha', 'cellule B', 'Texte de la page deux', 'SSE-2026-0042'] as $needle) {
            self::assertStringContainsString($needle, $text, $needle);
        }
        self::assertStringNotContainsString('alert(1)', $text);
        self::assertMatchesRegularExpression('~/Count 2~', $pdf, 'le saut de page produit une seconde page');
    }
}
