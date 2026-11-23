<?php

declare(strict_types=1);

namespace Tests\Unit\Pdf;

use App\Services\Sse\SseCasePdfService;
use App\Services\Sse\SseDocumentPdfService;
use App\Services\Sse\SseSheetPdf;
use App\Services\Sse\SseTransmissionPdfService;
use App\Support\Pdf\SsePdfNotice;
use App\Support\SseUsClassification;
use App\Support\TrainingCertificatePdfEngine;
use PHPUnit\Framework\TestCase;
use Tests\Support\PdfText;

/**
 * Page explicative au début des PDF du bureau SSE : activée par défaut, désactivable (paramètre « avec_notice »), une seule page,
 * même bandeau de classification que le reste du document, sommaire et identifiants, jamais de valeur masquée.
 */
final class SsePdfNoticeTest extends TestCase
{
    protected function setUp(): void
    {
        if (!TrainingCertificatePdfEngine::ensureTcpdfLoaded() || !TrainingCertificatePdfEngine::tcpdfCertificateFontsReady()) {
            self::markTestSkipped('TCPDF et ses polices requis');
        }
    }

    private static function pageCount(string $pdf): int
    {
        return (int) preg_match_all('~/Type\s*/Page\b(?!s)~', $pdf);
    }

    /** @return array<string, mixed> */
    private static function sheet(string $level, string $lang, bool $notice): array
    {
        $hidden = static fn (string $cat, string $value): string => \App\Services\Sse\SseRedactionService::visibleAt($cat, $level) ? $value : \App\Support\SseRedactionExplain::bar($cat);

        return [
            'model' => 'profile', 'lang' => $lang, 'faces' => '2', 'ref' => 'FX-2026-0042', 'title' => ['Fiche de renseignement', 'Intelligence sheet'], 'accent' => [55, 65, 81],
            'authority' => ['Service de renseignement', 'Intelligence service'], 'service' => 'Bureau SSE', 'issued_at' => '2026-11-23', 'issued_dt' => '2026-11-23 10:00:00',
            'valid_until' => null, 'expires_at' => null, 'until_lifted' => false,
            'classification' => [\App\Services\Sse\SseSheetService::levelLabels($level)[0], \App\Services\Sse\SseSheetService::levelLabels($level)[1]], 'level' => $level, 'visibility' => null,
            'facts' => [
                'first_name' => $hidden('identite', 'Dimitri'), 'last_name' => $hidden('identite', 'Karaman'), 'sex' => 'M', 'age_estimated' => 38,
                'birth_date' => $hidden('identite', '1987-03-12'), 'birth_place' => $hidden('identite', 'Kavala'), 'nationality' => 'TAK', 'affiliation' => 'Réseau du Nord-Est',
                'aliases' => [], 'language' => 'grec', 'height_cm' => 178, 'weight_kg' => 80, 'build' => 'moyenne', 'eyes' => 'marron', 'hair' => 'noirs', 'marks' => '',
                'bio' => ['prints' => true, 'iris' => false, 'dna' => true, 'quality' => 82, 'tier' => 2, 'subject' => ''], 'checks' => [], 'links' => [], 'weapons' => [], 'equipment' => [],
                'zone' => $hidden('lieu', 'Pyrgos'), 'last_seen_at' => '2026-11-12', 'last_seen_place' => $hidden('lieu', 'Pyrgos'), 'threat' => 'medium', 'armed' => 'unknown',
                'photos' => [], 'vehicles' => [], 'addresses' => [], 'phones' => [],
            ],
            'texts' => [], 'text_labels' => [], 'emblem' => '', 'generated_at' => '23/11/2026 10:00 UTC', 'notice' => $notice,
        ];
    }

    public function testTheCallParameterIsOnByDefaultAndCanBeSwitchedOff(): void
    {
        self::assertTrue(SsePdfNotice::enabled(null));
        self::assertTrue(SsePdfNotice::enabled(''));
        self::assertTrue(SsePdfNotice::enabled('1'));
        self::assertTrue(SsePdfNotice::enabled(true));
        foreach (['0', 'false', 'non', 'off', false, 0] as $off) {
            self::assertFalse(SsePdfNotice::enabled($off), (string) json_encode($off));
        }
        self::assertTrue(SsePdfNotice::enabled('n’importe quoi'), 'une valeur inconnue garde la valeur par défaut');
    }

    public function testTheFingerprintIsShortStableAndFollowsTheContent(): void
    {
        $a = SsePdfNotice::fingerprint(['x' => 1, 'y' => 'é']);
        self::assertMatchesRegularExpression('/^[0-9A-F]{4} [0-9A-F]{4} [0-9A-F]{4}$/', $a);
        self::assertSame($a, SsePdfNotice::fingerprint(['x' => 1, 'y' => 'é']));
        self::assertNotSame($a, SsePdfNotice::fingerprint(['x' => 2, 'y' => 'é']));
    }

    public function testSheetNoticeIsPageOneWithTheBannerOnEveryPageAndCanBeSwitchedOff(): void
    {
        foreach (['interne', 'encadrement', 'confidentiel', 'tres_restreint'] as $level) {
            foreach (['fr', 'en'] as $lang) {
                $with = (new SseSheetPdf())->render(self::sheet($level, $lang, true));
                $without = (new SseSheetPdf())->render(self::sheet($level, $lang, false));
                self::assertNotNull($with);
                self::assertNotNull($without);
                self::assertTrue($with['notice']);
                self::assertFalse($without['notice']);
                self::assertSame($without['pages'] + 1, $with['pages'], "$level $lang : une page de plus");
                self::assertSame($with['pages'], self::pageCount($with['bytes']));
                self::assertSame($without['pages'], self::pageCount($without['bytes']));
                $text = PdfText::of($with['bytes']);
                self::assertStringContainsString($lang === 'en' ? 'About this document' : 'Notice du document', $text);
                self::assertStringContainsString($lang === 'en' ? 'CONTENTS' : 'SOMMAIRE', $text);
                self::assertStringContainsString('FX-2026-0042', $text);
                self::assertGreaterThanOrEqual(2 * $with['pages'], substr_count($text, SseUsClassification::banner($level)), "$level $lang : bandeaux haut et bas de chaque page");
                // Rien de masqué ne passe dans la notice.
                if ($level === 'interne') {
                    foreach (['Karaman', 'Dimitri', 'Kavala', '1987-03-12'] as $secret) {
                        self::assertStringNotContainsString($secret, $text, $secret);
                    }
                }
            }
        }
    }

    public function testNoticeSaysWhatIsWithheldAtTheLevelWithoutRevealingAnyValue(): void
    {
        $fr = PdfText::of((new SseSheetPdf())->render(self::sheet('interne', 'fr', true))['bytes']);
        self::assertStringContainsString('Identité', $fr);
        self::assertStringContainsString('lisible à partir de', $fr);
        $top = PdfText::of((new SseSheetPdf())->render(self::sheet('tres_restreint', 'en', true))['bytes']);
        self::assertStringContainsString('Nothing is withheld at this level', $top);
    }

    public function testCaseDossierGetsTheNoticeAfterTheCoverAndKeepsItsPageNumbering(): void
    {
        $svc = (new \ReflectionClass(SseCasePdfService::class))->newInstanceWithoutConstructor();
        $case = ['id' => 42, 'reference_code' => 'SSE-2026-0042', 'title' => 'Réseau logistique', 'classification' => 'confidentiel', 'classification_label' => 'Secret (S)', 'status' => 'ouvert', 'status_label' => 'En cours',
            'summary' => 'Suivi.', 'created_at' => '2026-08-20 10:00:00', 'updated_at' => '2026-08-28 18:30:00'];
        $people = [['id' => 1, 'display_name' => 'Karim Haddad', 'status_label' => 'Détenu', 'watchlist' => []]];
        $build = static fn (bool $notice, ?string $level): string => TrainingCertificatePdfEngine::suppressTcpdfPhpDeprecationsWhile(
            static fn (): string => $svc->buildPdfBinary($case, $people, [], [], [], [], "FLASH\nx", "CR\ny", $level, 0, $notice)
        );
        $with = $build(true, 'interne');
        $without = $build(false, 'interne');
        self::assertSame(self::pageCount($without) + 1, self::pageCount($with), 'la notice ajoute une page');
        $text = PdfText::of($with);
        self::assertStringContainsString('Notice du document', $text);
        self::assertStringContainsString('SSE-2026-0042', $text);
        self::assertStringNotContainsString('Notice du document', PdfText::of($without));
        // La pagination « n / N » du pied compte la page explicative.
        self::assertStringContainsString('/ ' . self::pageCount($with), $text);
    }

    public function testDocumentAndTransmissionPdfsCarryTheNoticeAndCanDropIt(): void
    {
        $doc = ['title' => 'Note de synthèse', 'reference_code' => 'DOC-1', 'classification' => 'encadrement', 'classification_label' => 'Confidentiel (C)', 'body' => '<p>Texte.</p>', 'status_label' => 'Publié', 'updated_at' => '2026-11-01'];
        $d = new SseDocumentPdfService();
        TrainingCertificatePdfEngine::suppressTcpdfPhpDeprecationsWhile(function () use ($d, $doc): void {
            self::assertSame(self::pageCount($d->build($doc, 0, false)) + 1, self::pageCount($d->build($doc, 0)));
        });
        $t = new SseTransmissionPdfService();
        TrainingCertificatePdfEngine::suppressTcpdfPhpDeprecationsWhile(function () use ($t): void {
            $on = $t->buildPdf('<p>Corps</p>', 'Journal', 'P', 'TR-1');
            $off = $t->buildPdf('<p>Corps</p>', 'Journal', 'P', 'TR-1', [], ['notice' => false]);
            self::assertSame(self::pageCount($off) + 1, self::pageCount($on));
            self::assertStringContainsString('Notice du document', PdfText::of($on));
        });
    }
}
