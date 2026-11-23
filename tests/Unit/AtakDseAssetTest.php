<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Sse\SseFichesGameService;
use PHPUnit\Framework\TestCase;

/**
 * Recherche d'un numéro DSE dans l'app Fiches du téléphone ATAK natif (docs/technical/ICD-SSE-DSE.md) : canal SubmitIdCheck
 * (kind « sse_dse », aucune nouvelle DLL), actions, vue, erreurs claires, dictionnaires et branchement côté site.
 */
final class AtakDseAssetTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';

    private static function read(string $rel): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(self::ROOT . $rel));
    }

    public function testKindMatchesTheSiteConstant(): void
    {
        self::assertSame('sse_dse', SseFichesGameService::KIND_DSE);
        self::assertStringContainsString('"kind":"sse_dse"', self::read(self::MAIN . 'functions/fiches/fn_fichesAction.sqf'));
    }

    public function testSiteRoutesTheKindThroughTheChecksChannel(): void
    {
        self::assertStringContainsString('SseFichesGameService::KIND_DSE', self::read('app/Controllers/Api/SseIdentityApiController.php'));
        $ctl = self::read('app/Controllers/Api/Game/GameSseFichesApiController.php');
        self::assertStringContainsString('KIND_DSE', $ctl);
        self::assertStringContainsString("'action' => 'dse.resolve'", $ctl);
        self::assertStringContainsString('DSE_MAX', $ctl);
    }

    public function testActionsHandleEveryFailureWithAClearMessage(): void
    {
        $src = self::read(self::MAIN . 'functions/fiches/fn_fichesAction.sqf');
        foreach (['case "dseFind"', 'case "dseClear"', 'not_found', 'rate_limited', 'COMSPEC_ATAK_FichesDll', 'athenaMode', 'fnc_bridge'] as $needle) {
            self::assertStringContainsString($needle, $src, $needle);
        }
    }

    public function testPageHasTheDseView(): void
    {
        $src = self::read(self::MAIN . 'functions/fiches/fn_pageFiches.sqf');
        foreach (['_view isEqualTo "dse"', '"fichesDseQ"', '"Nom de code"', '"Numéro d\'identification"', '"IA reliée"', 'dseFind', 'dseClear'] as $needle) {
            self::assertStringContainsString($needle, $src, $needle);
        }
    }

    public function testPlayerTextsAreInBothDictionaries(): void
    {
        $en = self::read(self::MAIN . 'i18n/en.txt');
        $xx = self::read('mod/COMSPEC_ATAK_Native/localisation/template/xx.txt');
        foreach (['Numéro DSE', 'Nom de code', 'IA reliée', 'Fiche trouvée', "Numéro d'identification", 'Trop de recherches de DSE : réessayez dans une minute.',
            'DSE %1 inconnu : vérifiez le numéro (ni 0, ni O, ni 1, ni I), ou il vient d\'une autre communauté.'] as $fr) {
            self::assertStringContainsString("\n[\"" . $fr . '", "', $en, $fr);
            self::assertStringContainsString("\n[\"" . $fr . '", "", "', $xx, $fr);
        }
        // Jamais de virgule après la dernière entrée.
        self::assertMatchesRegularExpression('/[^,]\n\]\s*$/', $en);
        self::assertMatchesRegularExpression('/[^,]\n\]\s*$/', $xx);
    }

    public function testChangelogCarriesTheBilingualPlayerSummary(): void
    {
        $cl = self::read('CHANGELOG-ATAK.md');
        self::assertMatchesRegularExpression('/## [^\n]*\(ATAK natif 4\.32\.0\.AG - Washington\)\n\n### Résumé joueur\nType : nouveauté\nFR : /', $cl);
        self::assertMatchesRegularExpression('/4\.32\.0\.AG[^\n]*\n\n### Résumé joueur[\s\S]*?\nEN : /', $cl);
    }
}
