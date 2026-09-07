<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\I18n\Translator;
use PHPUnit\Framework\TestCase;

final class I18nPhraseHelperTest extends TestCase
{
    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/app/Support/helpers.php';
    }

    public function testSlugIsAsciiWithoutIconvArtifacts(): void
    {
        self::assertSame('tableau_de_bord', i18n_slug('Tableau de bord'));
        self::assertSame('reglages_d_immersion', i18n_slug('Réglages d’immersion'));
        self::assertSame('help_aide_signalement', i18n_slug('HELP — aide & signalement'));
    }

    public function testFrenchLocaleReturnsSourcePhrase(): void
    {
        $this->setUiLocale('fr');
        self::assertSame('Tableau de bord', i18n_phrase('nav', 'Tableau de bord'));
        self::assertSame('Rubrique « Ops »', i18n_phrase('nav', 'Rubrique « :name »', ['name' => 'Ops']));
    }

    public function testEnglishLocaleUsesCatalogThenFallback(): void
    {
        $this->setUiLocale('en');
        self::assertSame('Dashboard', i18n_phrase('nav', 'Tableau de bord'));
        self::assertSame('Section “Ops”', t('nav.forum_in_rubric', ['name' => 'Ops'], 'Rubrique « :name »'));
        self::assertSame('Phrase inconnue xyz', i18n_phrase('nav', 'Phrase inconnue xyz'));
    }

    private function setUiLocale(string $locale): void
    {
        $translator = new Translator();
        $translator->setLocale($locale);
        $GLOBALS['__app_translator'] = $translator;
        $GLOBALS['__app_locale'] = $locale;
    }
}
