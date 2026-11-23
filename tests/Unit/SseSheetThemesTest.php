<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Sse\SseDocumentPdfService;
use App\Support\SseDocumentTemplates;
use App\Support\SseRichText;
use App\Support\TrainingCertificatePdfEngine;
use Tests\Support\PdfText;
use App\Support\SseSheetThemes;
use PHPUnit\Framework\TestCase;

/**
 * Thèmes de feuille : catalogue original (aucun nom d'agence réelle, aucun logo), valeurs normalisées, thème par défaut des modèles,
 * un seul jeu de couleurs pour la feuille et le PDF.
 */
final class SseSheetThemesTest extends TestCase
{
    public function testAtLeastFourOriginalThemesAndTheClassicDefault(): void
    {
        self::assertGreaterThanOrEqual(4, count(SseSheetThemes::all()));
        self::assertLessThanOrEqual(6, count(SseSheetThemes::all()));
        self::assertArrayHasKey(SseSheetThemes::DEFAULT, SseSheetThemes::all());
    }

    public function testNoRealAgencyNameNorEmblemAnywhere(): void
    {
        $files = [
            __DIR__ . '/../../app/Support/SseSheetThemes.php',
            __DIR__ . '/../../app/Support/Pdf/SseThemedTcpdf.php',
            __DIR__ . '/../../public/assets/css/sse-doc-themes.css',
        ];
        foreach ($files as $file) {
            $text = (string) file_get_contents($file);
            // Seul le code du fichier est contrôlé : le commentaire d'en-tête dit justement qu'aucun nom réel n'est utilisé.
            $text = (string) preg_replace('~/\*.*?\*/~s', '', $text);
            self::assertDoesNotMatchRegularExpression('/\b(CIA|NSA|FBI|DoD|Langley|Fort Meade|Pentagon|KGB|MI6|DGSE)\b/i', $text, basename($file));
            self::assertDoesNotMatchRegularExpression('/<svg|data:image/i', $text, basename($file) . ' : aucun emblème intégré (seul le logo déjà enregistré par la communauté peut figurer)');
        }
    }

    public function testNormalizeFallsBackToTheDefault(): void
    {
        self::assertSame('archives', SseSheetThemes::normalize(' Archives '));
        self::assertSame(SseSheetThemes::DEFAULT, SseSheetThemes::normalize('<script>'));
        self::assertSame(SseSheetThemes::DEFAULT, SseSheetThemes::normalize(null));
        self::assertSame('sse-theme--classique', SseSheetThemes::cssClass('nimporte-quoi'));
    }

    public function testEveryTemplateHasAKnownDefaultTheme(): void
    {
        $used = [];
        foreach (array_keys(SseDocumentTemplates::CATALOGUE) as $code) {
            $theme = SseSheetThemes::defaultForTemplate($code);
            self::assertArrayHasKey($theme, SseSheetThemes::all(), $code);
            $used[$theme] = true;
        }
        self::assertGreaterThanOrEqual(3, count($used), 'les modèles n’ont pas tous le même thème');
    }

    public function testFontsAreLimitedToThoseAlreadyAvailable(): void
    {
        foreach (SseSheetThemes::all() as $id => $t) {
            self::assertDoesNotMatchRegularExpression('/https?:|url\(/', $t['body'], $id);
            self::assertContains($t['pdf_body'], ['sans', 'serif', 'mono'], $id);
        }
    }

    public function testRgbConversion(): void
    {
        self::assertSame([31, 77, 58], SseSheetThemes::rgb('#1f4d3a'));
    }

    public function testEveryThemeRendersInThePdfWithPageNumbersAndReference(): void
    {
        if (!TrainingCertificatePdfEngine::ensureTcpdfLoaded() || !TrainingCertificatePdfEngine::tcpdfCertificateFontsReady()) {
            self::markTestSkipped('TCPDF et ses polices requis');
        }
        foreach (array_keys(SseSheetThemes::all()) as $id) {
            $pdf = (new SseDocumentPdfService())->build([
                'reference_code' => 'SSE-NR-26-0042', 'title' => 'Note de test', 'document_type_label' => 'Note d’analyse',
                'classification_label' => 'Confidentiel', 'status_label' => 'Brouillon', 'case_reference' => 'DOS-26-0007',
                'author_label' => 'Rédacteur', 'sheet_theme' => $id,
                'body' => SseRichText::MARK . SseDocumentTemplates::body('note_renseignement', ['reference' => 'SSE-NR-26-0042']),
            ], 0, false);
            self::assertStringStartsWith('%PDF', $pdf, $id);
            $text = PdfText::of($pdf);
            self::assertStringContainsString('SSE-NR-26-0042', $text, $id);
            self::assertMatchesRegularExpression('~page\s+1\s+sur\s+\d~', $text, $id . ' : page n sur N');
            self::assertStringContainsString('ATHENA', $text, $id);
        }
    }
}
