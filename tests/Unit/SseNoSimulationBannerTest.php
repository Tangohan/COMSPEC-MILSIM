<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Portail SSE : aucun bandeau « SIMULATION » ; au plus une mention discrète de fiction par page
 * (sse_fiction_footer), jamais de ligne de simulation dans le message brut NCIC.
 */
final class SseNoSimulationBannerTest extends TestCase
{
    private static function read(string $rel): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $rel);
    }

    /** Texte rendu approximatif : sources PHP sans commentaires. */
    private static function code(string $rel): string
    {
        $src = self::read($rel);
        $src = (string) preg_replace('~/\*.*?\*/~s', '', $src);

        return (string) preg_replace('~^\s*//.*$~m', '', $src);
    }

    public function testConsultationScreensCarryNoSimulationMention(): void
    {
        foreach (['files/_nav', 'files/index', 'files/subject', 'files/photobook', 'files/mentions', 'files/journal', 'files/seek', 'person_show', 'persons', 'papers/identities', 'papers/identity_show', 'folder_show'] as $v) {
            $code = self::code('views/atak/sse/' . $v . '.php');
            self::assertSame(0, preg_match_all('/simulation/i', $code), $v . ' : mention SIMULATION');
            self::assertStringNotContainsString('AUCUNE BASE', $code, $v);
            self::assertStringNotContainsString('Aucune base réelle', $code, $v);
        }
    }

    public function testPrintedSheetHasOneDiscreetFooterAndNoBanner(): void
    {
        $code = self::code('views/atak/sse/files/subject_print.php');
        self::assertSame(0, preg_match_all('/simulation/i', $code));
        self::assertSame(1, substr_count($code, 'sse_fiction_footer(true)'));
        self::assertStringNotContainsString('class="sim"', $code);
    }

    public function testRawNcicMessageHasNoSimulationLine(): void
    {
        self::assertSame(0, preg_match_all('/simulation|fictional data/i', self::code('app/Services/Sse/SseFileRecordService.php')));
    }

    public function testHomeHasAtMostOneDiscreetMention(): void
    {
        self::assertSame(1, substr_count(self::read('views/atak/sse/home.php'), 'sse_fiction_footer()'));
        $helper = self::read('app/Support/helpers.php');
        self::assertStringContainsString('function sse_fiction_footer', $helper);
        self::assertStringContainsString('Document de fiction — jeu de rôle Arma 3', $helper);
        foreach (['sse_shell', 'sse-files-kit', 'sse-papers'] as $css) {
            self::assertStringNotContainsString('.fx-sim', self::read('public/assets/css/' . $css . '.css'));
        }
    }

    public function testMemberAndMobileBannersAreGone(): void
    {
        foreach (['views/admin/effectifs_workspace/member_paper_form.php', 'views/admin/member_situation/mes_papiers.php', 'views/partials/sse_cover_export.php', 'views/m/controle.php'] as $v) {
            self::assertStringNotContainsString('idm-sim', self::read($v), $v);
            self::assertStringNotContainsString('mm-idc-sim', self::read($v), $v);
        }
    }
}
