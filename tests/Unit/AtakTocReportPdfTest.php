<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\AtakToc\TocReportPdf;
use App\Services\AtakToc\TocReportService;
use App\Support\TrainingCertificatePdfEngine;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\TocReportsSqlite;

/**
 * Export PDF d'un rapport du poste TOC web : un PDF par type de rapport, bandeau de classification américain fictif,
 * aucune mention de jeu, caviardage en rectangles noirs (jamais le bloc plein U+2588, absent de la police : il sortirait en « ? »).
 */
final class AtakTocReportPdfTest extends TestCase
{
    private PDO $pdo;

    private TocReportService $svc;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        if (!TrainingCertificatePdfEngine::ensureTcpdfLoaded() || !TrainingCertificatePdfEngine::tcpdfCertificateFontsReady()) {
            self::markTestSkipped('moteur PDF indisponible');
        }
        $this->pdo = TocReportsSqlite::open();
        TocReportsSqlite::seedRealistic($this->pdo, time());
        $this->svc = new TocReportService($this->pdo, static fn (int $t, array $rows): array => $rows);
    }

    private static function pages(string $pdf): int
    {
        return preg_match_all('#/Type\s*/Page[^s]#', $pdf) ?: 0;
    }

    public function testEveryReportOfTheDataSetExportsToAValidPdf(): void
    {
        $pdf = new TocReportPdf();
        foreach ($this->svc->list(1, 7, 'tres_restreint')['reports'] as $r) {
            $d = $this->svc->find(1, 7, 'tres_restreint', $r['key']);
            $bytes = $pdf->render($d, ['lang' => 'fr', 'operator' => 'TA1']);
            self::assertNotNull($bytes, $r['key']);
            self::assertStringStartsWith('%PDF-', $bytes, $r['key']);
            self::assertGreaterThanOrEqual(1, self::pages($bytes), $r['key']);
            self::assertLessThanOrEqual(3, self::pages($bytes), $r['key'] . ' : un rapport tient en trois pages au plus');
        }
    }

    public function testEnglishExportAndLongTextsDoNotBreakThePage(): void
    {
        $id = TocReportsSqlite::tactical($this->pdo, 1, 1, 'PATROLREP', 'Capitaine Jean-Baptiste de La Rochefoucauld-Montmirail', 'COMPAGNIE DE FUSILIERS DE MONTAGNE · 2e SECTION', 'Fin de patrouille', [
            'kind' => 'patrolrep', 'start' => '0830', 'end' => '1105', 'route' => 'Point d’appui › moulin › crête 214 › Pyrgos › col de Sofia', 'result' => 'REMPLIE',
            'observations' => str_repeat('Zone dégagée, aucun mouvement ennemi observé, population absente. Œuvre d’art détruite à l’est (été 1944) ; garçon blessé. ', 70),
        ]);
        $d = $this->svc->find(1, 7, 'tres_restreint', 'tr-' . $id);
        $en = (new TocReportPdf())->render($d, ['lang' => 'en']);
        $fr = (new TocReportPdf())->render($d, ['lang' => 'fr']);
        self::assertStringStartsWith('%PDF-', (string) $en);
        self::assertGreaterThan(1, self::pages((string) $fr), 'un texte très long passe sur la page suivante');
        self::assertNotSame($en, $fr);
    }

    public function testBannerFollowsTheClassificationWithAmericanLevels(): void
    {
        [$b0, $c0] = TocReportPdf::banner(['classification' => 'UNCLASSIFIED', 'required_level' => 'interne']);
        self::assertSame('UNCLASSIFIED', $b0);
        self::assertSame([0, 122, 51], $c0);
        [$b1, $c1] = TocReportPdf::banner(['classification' => 'CONFIDENTIAL', 'required_level' => 'encadrement']);
        self::assertSame('CONFIDENTIAL', $b1);
        self::assertSame([0, 51, 160], $c1);
        [$b2, $c2] = TocReportPdf::banner(['classification' => 'SECRET//NOFORN', 'required_level' => 'confidentiel']);
        self::assertSame('SECRET//NOFORN', $b2);
        self::assertSame([200, 16, 46], $c2);
    }

    public function testRedactionIsDrawnAsBlackRectanglesAndNeverAsTheMissingGlyph(): void
    {
        $block = "\u{2588}";
        $html = TocReportPdf::html('Drone au-dessus de ' . str_repeat($block, 6) . ' <b>cap sud</b> & « été »');
        self::assertStringNotContainsString($block, $html);
        self::assertStringContainsString('background-color:#000000;color:#000000', $html);
        self::assertStringContainsString('&lt;b&gt;cap sud&lt;/b&gt; &amp; « été »', $html, 'le texte du jeu est échappé, accents conservés');
        self::assertSame('Position [CAVIARDÉ] ici', TocReportPdf::line('Position ' . str_repeat($block, 12) . ' ici'));
        self::assertSame('Position [REDACTED] here', TocReportPdf::line('Position ' . str_repeat($block, 3) . ' here', true));
        // Le rectangle a toujours la même largeur : la longueur masquée ne se devine pas.
        self::assertSame(TocReportPdf::html(str_repeat($block, 3)), TocReportPdf::html(str_repeat($block, 40)));
        // Un compte rendu caviardé s'exporte sans lever d'erreur.
        $id = TocReportsSqlite::tactical($this->pdo, 1, 1, 'SPOTREP', 'YA1', 'YA1', 'Observation (SPOTREP) · Drone', ['kind' => 'spotrep', 'what' => 'Drone']);
        $svc = new TocReportService($this->pdo, static function (int $t, array $rows) use ($id): array {
            foreach ($rows as $i => $r) {
                if ((int) $r['id'] === $id) {
                    $rows[$i]['summary'] = 'Observation (SPOTREP) · ' . str_repeat("\u{2588}", 9);
                    $rows[$i]['details'] = 'Drone ' . str_repeat("\u{2588}", 9) . ' fin';
                }
            }

            return $rows;
        });
        $d = $svc->find(1, 7, 'interne', 'tr-' . $id);
        self::assertNotNull($d);
        self::assertTrue($d['redacted']);
        $bytes = (new TocReportPdf())->render($d, ['lang' => 'fr']);
        self::assertStringStartsWith('%PDF-', (string) $bytes);
    }

    public function testNoFictionMentionAndNoRealOrganisationInTheTemplate(): void
    {
        // Chaque rapport du jeu de données, en français et en anglais, du rapport sans restriction au plus classifié :
        // aucune mention de jeu sur les pages (en-tête, pied, diffusion) ni dans les propriétés du fichier.
        $pdf = new TocReportPdf();
        $seen = 0;
        foreach ($this->svc->list(1, 7, 'tres_restreint')['reports'] as $r) {
            $d = $this->svc->find(1, 7, 'tres_restreint', $r['key']);
            foreach (['fr', 'en'] as $lang) {
                $bytes = (string) $pdf->render($d, ['lang' => $lang, 'operator' => 'TA1']);
                self::assertStringContainsString(strtoupper((string) $r['key']), \Tests\Support\PdfText::of($bytes), $r['key'] . ' : le texte des pages est bien lu');
                self::assertNull(\Tests\Support\PdfText::fictionMention($bytes), $r['key'] . ' ' . $lang . ' : mention de jeu sur le rapport');
                ++$seen;
            }
        }
        self::assertGreaterThan(0, $seen);
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/AtakToc/TocReportPdf.php');
        self::assertStringNotContainsString('Document de fiction', $src);
        self::assertStringNotContainsString('Fictional document', $src);
        self::assertStringNotContainsString('ROLE-PLAY USE ONLY', $src);
        self::assertStringNotContainsString('USAGE JEU DE RÔLE', $src);
        foreach (['U.S. ARMY', 'US ARMY', 'Department of', 'Pentagon', 'NATO', 'OTAN', 'CIA', 'FBI'] as $real) {
            self::assertStringNotContainsString($real, $src, 'aucun nom d’organisation réelle : ' . $real);
        }
    }
}
