<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\AtakToc\CasCheckinSheet;
use App\Services\AtakToc\CasCheckinSheetPdf;
use App\Services\AtakToc\TocReportCatalog;
use App\Services\AtakToc\TocReportPdf;
use App\Services\AtakToc\TocReportService;
use App\Support\TrainingCertificatePdfEngine;
use PHPUnit\Framework\TestCase;
use Tests\Support\PdfText;
use Tests\Support\TocReportsSqlite;

/**
 * Fiche de check-in CAS (« 9 LINE CLOSE AIR SUPPORT BRIEF ») versée au compte rendu par l'app JTAC du téléphone (onglet
 * CKIN) : lecture des données structurées (form = cas_ckin, ckin_<champ>), fiche masquée des « autres informations » du
 * rapport, export PDF du poste TOC sur la grille de la fiche blanche (remplie ou vierge), sans mention de jeu ni bandeau
 * de classification inventé. Rendu réel (TCPDF) et dépôt réel sur sqlite.
 */
final class AtakCasCheckinSheetPdfTest extends TestCase
{
    /** @return array<string, string> */
    private static function sd(): array
    {
        return [
            'form' => 'cas_ckin', 'kind' => 'other', 'title' => 'CAS CHECK-IN · TIGER 6', 'text' => 'lu en phonie',
            'ckin_ac' => 'TIGER 6', 'ckin_fac' => 'SABRE 1', 'ckin_pilot' => 'TIGER6 // type 1 BOT 9988', 'ckin_acft' => '1 × A-10C',
            'ckin_pos' => "2500m\n  /////", 'ckin_ord' => 'GBU 12 - GAU8 - Hydra70', 'ckin_tos' => '45 mike', 'ckin_abort' => 'Iron man',
            'ckin_l1' => 'IP BRAVO', 'ckin_l2' => '245° M', 'ckin_l2o' => 'GAUCHE', 'ckin_l3' => '4.2 NM', 'ckin_l4' => '450 ft MSL',
            'ckin_l5' => '2 BTR, à découvert', 'ckin_l6' => '04512 07788', 'ckin_l7' => 'LASER', 'ckin_l7c' => '1688', 'ckin_l8' => 'SUD 600 m',
            'ckin_l9' => 'NORD, retour IP BRAVO', 'ckin_rem' => 'Rester au-dessus de 3000 ft', 'ckin_fah' => '200 ± 20', 'ckin_ltl' => '170° M',
            'ckin_thr' => 'MANPADS 2 km NORD', 'ckin_gtl' => '090', 'ckin_push' => '1', 'ckin_unknown' => 'ignoré',
        ];
    }

    private static function ready(): void
    {
        if (!TrainingCertificatePdfEngine::ensureTcpdfLoaded() || !TrainingCertificatePdfEngine::tcpdfCertificateFontsReady()) {
            self::markTestSkipped('moteur PDF indisponible');
        }
    }

    private static function pages(string $pdf): int
    {
        return preg_match_all('#/Type\s*/Page[^s]#', $pdf) ?: 0;
    }

    public function testStructuredDataIsReadOnlyWhenTheFormIsACasCheckIn(): void
    {
        self::assertNull(CasCheckinSheet::fromStructured(['kind' => 'other', 'ckin_ac' => 'TIGER 6']));
        $v = CasCheckinSheet::fromStructured(self::sd());
        self::assertIsArray($v);
        self::assertSame('TIGER 6', $v['ac']);
        self::assertSame('2500m /////', $v['pos'], 'une valeur tient sur une ligne');
        self::assertArrayNotHasKey('unknown', $v);
        self::assertSame('LEFT', CasCheckinSheet::offset($v['l2o']));
        self::assertSame('RIGHT', CasCheckinSheet::offset('droite'));
        self::assertSame('', CasCheckinSheet::offset(''));
        self::assertTrue(CasCheckinSheet::isSheetKey('ckin_l6'));
        self::assertTrue(CasCheckinSheet::isSheetKey('form'));
        self::assertFalse(CasCheckinSheet::isSheetKey('grid'));
    }

    public function testTheSheetHasTheFieldsOfTheBlankBrief(): void
    {
        self::assertSame(['pilot', 'acft', 'pos', 'ord', 'tos', 'abort', 'add'], CasCheckinSheet::CHECK_IN, 'sept champs de check-in');
        self::assertSame(['l1', 'l2', 'l3', 'l4', 'l5', 'l6', 'l7', 'l8', 'l9'], CasCheckinSheet::NINE, 'neuf lignes');
        foreach (['ac', 'fac', 'sit', 'l2o', 'l7c', 'rem', 'fah', 'ltl', 'thr', 'gtl', 'tot', 'push', 'amp'] as $k) {
            self::assertArrayHasKey($k, CasCheckinSheet::FIELDS, $k);
        }
    }

    public function testFilledSheetPrintsTheBriefGridAndTheValuesWithoutAnyGameMention(): void
    {
        self::ready();
        foreach (['fr', 'en'] as $lang) {
            $bytes = (new CasCheckinSheetPdf())->render((array) CasCheckinSheet::fromStructured(self::sd()), ['lang' => $lang, 'ref' => 'TR-12', 'dtg' => '161200ZNOV26']);
            self::assertNotNull($bytes);
            self::assertStringStartsWith('%PDF-', $bytes);
            self::assertSame(1, self::pages($bytes), 'la fiche tient sur une page');
            $text = PdfText::of($bytes);
            foreach (['9 LINE CLOSE AIR SUPPORT BRIEF', 'standing by for aircraft check-in', 'Pilot Callsign / Mission #', 'Number and type of aircraft',
                'Position and altitude', 'Ordnance', 'Time on station', 'Abort Code', 'Additional Remarks', 'Provide friendly, enemy situation and game plan to pilot',
                'Stand by for 9 line, I am not a JTAC', 'IP / BP', 'Heading', 'Offset', 'Distance', 'Target Elevation', 'Target Description', 'Target Location',
                'Type Mark', 'Laser Code', 'Friendlies', 'Egress', 'Say when ready to copy remarks', 'Remarks', 'Final Attack Heading', 'Laser Target Line',
                'Threats', 'Active Gun Target Line', 'TOT / TTT', 'PUSH ASAP', 'Say when ready for amplifying information',
                'TIGER 6', 'SABRE 1', '04512 07788', '1688', 'MANPADS 2 km NORD', 'TR-12'] as $needle) {
                self::assertStringContainsString($needle, $text, $lang . ' : ' . $needle);
            }
            self::assertNull(PdfText::fictionMention($bytes), $lang);
            self::assertStringNotContainsString('UNCLASSIFIED', $text, 'aucune classification sans bandeau du rapport');
        }
    }

    public function testBlankSheetIsPrintableAndHasNoClassification(): void
    {
        self::ready();
        $bytes = (new CasCheckinSheetPdf())->render([], ['lang' => 'fr']);
        self::assertNotNull($bytes);
        self::assertSame(1, self::pages($bytes));
        $text = PdfText::of($bytes);
        self::assertStringContainsString('9 LINE CLOSE AIR SUPPORT BRIEF', $text);
        self::assertStringContainsString('Final Attack Heading', $text);
        self::assertStringNotContainsString('TIGER', $text);
        self::assertStringNotContainsString('CLASSIFIED', $text);
        self::assertNull(PdfText::fictionMention($bytes));
    }

    public function testTocReportCarriesTheSheetAndItsPdfIsTheBrief(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        self::ready();
        $pdo = TocReportsSqlite::open();
        $id = TocReportsSqlite::tactical($pdo, 1, 5, 'OTHER', 'SABRE 1', 'JTAC', 'CAS CHECK-IN · TIGER 6', self::sd());
        $plain = TocReportsSqlite::tactical($pdo, 1, 6, 'OTHER', 'SABRE 1', 'JTAC', 'Message', ['kind' => 'other', 'title' => 'Message', 'text' => 'RAS']);
        $svc = new TocReportService($pdo, static fn (int $t, array $rows): array => $rows);
        $d = $svc->find(1, 7, 'tres_restreint', 'tr-' . $id);
        self::assertIsArray($d);
        self::assertIsArray($d['cas_ckin']);
        self::assertSame('SABRE 1', $d['cas_ckin']['fac']);
        foreach ($d['extra'] as $row) {
            self::assertStringNotContainsStringIgnoringCase('ckin', (string) $row['label'], 'la fiche ne se répète pas dans les autres informations');
        }
        $bytes = (new TocReportPdf())->render($d, ['lang' => 'fr']);
        self::assertNotNull($bytes);
        self::assertStringContainsString('9 LINE CLOSE AIR SUPPORT BRIEF', PdfText::of($bytes));
        $other = $svc->find(1, 7, 'tres_restreint', 'tr-' . $plain);
        self::assertIsArray($other);
        self::assertNull($other['cas_ckin'], 'un compte rendu sans fiche garde le rapport habituel');
        self::assertStringNotContainsString('9 LINE CLOSE AIR SUPPORT BRIEF', PdfText::of((string) (new TocReportPdf())->render($other, ['lang' => 'fr'])));
        $doc = TocReportCatalog::render('OTHER', self::sd());
        self::assertSame([], array_filter($doc['extra'], static fn (array $e): bool => str_contains(strtolower($e['label']), 'ckin')));
    }

    public function testBlankRouteAndTocLinkAreWired(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = str_replace("\r\n", "\n", (string) file_get_contents($root . '/routes/web.php'));
        self::assertStringContainsString("\$router->get('/atak/toc/cas-checkin.pdf', [\$tocReports, 'casCheckinBlank'], \$mwAtakMemberOnly);", $routes);
        self::assertStringContainsString('public function casCheckinBlank(Request $request', (string) file_get_contents($root . '/app/Controllers/Api/AtakTocReportsApiController.php'));
        $js = (string) file_get_contents($root . '/public/assets/js/toc/toc-reports.js');
        self::assertStringContainsString("'/atak/toc/cas-checkin.pdf'", $js);
        self::assertStringContainsString("r.cas_ckin ? 'Fiche CAS 9 lignes (PDF)'", $js);
        $en = require $root . '/lang/en/site_auto.php';
        self::assertSame('Blank CAS form', $en['Fiche CAS vierge'] ?? null);
        self::assertSame('CAS 9-line form (PDF)', $en['Fiche CAS 9 lignes (PDF)'] ?? null);
    }
}
