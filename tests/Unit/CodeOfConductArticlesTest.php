<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Code de conduite Athena : articles FR et EN présents et équivalents, numérotation continue,
 * renvois [[art:N]] valides, un seul h1, adresse de contact, articles 1 à 7 stables (cités par les avis de suspension).
 */
final class CodeOfConductArticlesTest extends TestCase
{
    private const COUNT = 13;

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    /** @return array<string, string> */
    private function catalogue(string $locale): array
    {
        $data = require $this->root() . '/lang/' . $locale . '/conduct.php';
        self::assertIsArray($data);

        return $data;
    }

    public function testEveryArticleExistsInBothLanguages(): void
    {
        foreach (['fr', 'en'] as $locale) {
            $c = $this->catalogue($locale);
            for ($n = 1; $n <= self::COUNT; $n++) {
                foreach (['title', 'summary', 'body', 'example'] as $part) {
                    self::assertTrue(isset($c['article_' . $n . '_' . $part]), "$locale article $n $part");
                }
                self::assertTrue(trim($c['article_' . $n . '_title']) !== '', "$locale article $n titre vide");
                self::assertTrue(trim($c['article_' . $n . '_summary']) !== '', "$locale article $n résumé vide");
                self::assertTrue(trim($c['article_' . $n . '_body']) !== '', "$locale article $n corps vide");
            }
            self::assertFalse(isset($c['article_' . (self::COUNT + 1) . '_title']), "$locale : article en trop");
        }
    }

    public function testFrenchAndEnglishAreEquivalent(): void
    {
        $fr = $this->catalogue('fr');
        $en = $this->catalogue('en');
        self::assertSame(array_keys($fr), array_keys($en));
        for ($n = 1; $n <= self::COUNT; $n++) {
            foreach (['summary', 'body', 'example'] as $part) {
                $key = 'article_' . $n . '_' . $part;
                self::assertSame($fr[$key] === '', $en[$key] === '', $key . ' : exemple présent dans une seule langue');
                self::assertSame($this->tokens($fr[$key]), $this->tokens($en[$key]), $key . ' : renvois différents');
                self::assertSame(substr_count($fr[$key], "\n- "), substr_count($en[$key], "\n- "), $key . ' : puces différentes');
            }
        }
    }

    /** @return list<string> */
    private function tokens(string $text): array
    {
        preg_match_all('/\[\[[a-z:0-9]+\]\]/', $text, $m);
        sort($m[0]);

        return $m[0];
    }

    public function testCrossReferencesPointToExistingArticles(): void
    {
        foreach (['fr', 'en'] as $locale) {
            foreach ($this->catalogue($locale) as $key => $text) {
                if (preg_match_all('/\[\[art:(\d+)\]\]/', $text, $m) > 0) {
                    foreach ($m[1] as $target) {
                        self::assertTrue((int) $target >= 1 && (int) $target <= self::COUNT, "$locale $key renvoie à l'article $target");
                    }
                }
            }
        }
    }

    public function testArticlesCitedByBanNoticesKeepTheirTopic(): void
    {
        $fr = $this->catalogue('fr');
        self::assertStringContainsString('Respect', $fr['article_1_title']);
        self::assertStringContainsString('Contenus', $fr['article_2_title']);
        self::assertStringContainsString('Comptes', $fr['article_3_title']);
        self::assertStringContainsString('Inactivité', $fr['article_7_title']);
        $notice = (string) file_get_contents($this->root() . '/app/Services/Account/PlatformBanNotice.php');
        self::assertStringContainsString('art\. ([1-7])', $notice);
    }

    public function testViewHasSingleH1AndAnchors(): void
    {
        $view = (string) file_get_contents($this->root() . '/views/legal/conduct.php');
        self::assertSame(1, substr_count($view, '<h1'));
        self::assertStringContainsString('id="article-<?= $number ?>"', $view);
        self::assertStringContainsString('range(1, ' . self::COUNT . ')', $view);
        self::assertStringContainsString('legal_public_contact_email', $view);
        self::assertStringContainsString('window.print()', $view);
        self::assertFalse((bool) preg_match('/kicker|eyebrow|overline|surtitre/i', $view));
    }

    public function testContactAddressIsInTheRepository(): void
    {
        $config = (string) file_get_contents($this->root() . '/config/contact_mail.php');
        self::assertStringContainsString('contact@athena-comspec.eu', $config);
    }

    public function testNoUnresolvedPlaceholderInPublicText(): void
    {
        foreach (['fr', 'en'] as $locale) {
            foreach ($this->catalogue($locale) as $key => $text) {
                self::assertStringNotContainsString('À COMPLÉTER', $text, "$locale $key");
                self::assertStringNotContainsString('TODO', $text, "$locale $key");
            }
        }
    }
}
