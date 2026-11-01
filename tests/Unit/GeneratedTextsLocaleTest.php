<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Community\TenantCommunityProfileService;
use App\Services\Platform\AthenaHeritageNarration;
use App\Support\CommunityLanguages;
use PHPUnit\Framework\TestCase;

/**
 * Textes composés côté PHP (annuaire, frise « Notre histoire ») : rédigés dans la langue du lecteur, pas en français
 * collé à des valeurs que le dictionnaire d'affichage ne reconnaît pas.
 */
final class GeneratedTextsLocaleTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['__app_locale'], $GLOBALS['__app_translator'], $GLOBALS['__app_site_lang']);
    }

    private function useLocale(string $locale): void
    {
        $GLOBALS['__app_locale'] = $locale;
        unset($GLOBALS['__app_translator'], $GLOBALS['__app_site_lang']);
    }

    public function testUnitAffiliationInReaderLanguageWithoutTranslatingUnitNames(): void
    {
        $real = ['unit_affiliation' => ['is_real' => true, 'country' => 'US', 'country_label' => 'États-Unis',
            'unit_labels' => ['1st Marine Division', '2nd Marine Regiment']]];
        $fict = ['unit_affiliation' => ['is_real' => false, 'fictional_label' => 'Légion du Nord']];

        $this->useLocale('fr');
        self::assertSame('États-Unis — 1st Marine Division, 2nd Marine Regiment', TenantCommunityProfileService::unitAffiliationSummary($real));
        self::assertSame('Unité fictive : Légion du Nord', TenantCommunityProfileService::unitAffiliationSummary($fict));
        self::assertSame('États-Unis', TenantCommunityProfileService::affiliationCountryLabel($real['unit_affiliation']));

        $this->useLocale('en');
        self::assertSame('United States — 1st Marine Division, 2nd Marine Regiment', TenantCommunityProfileService::unitAffiliationSummary($real));
        self::assertSame('Fictional unit: Légion du Nord', TenantCommunityProfileService::unitAffiliationSummary($fict));
        self::assertSame('United States', TenantCommunityProfileService::affiliationCountryLabel($real['unit_affiliation']));
    }

    public function testMonthAndDayNamesFollowReaderLanguage(): void
    {
        $this->useLocale('fr');
        self::assertSame('mars', \App\Support\LocalizedCalendar::month(3));
        self::assertSame('lundi', \App\Support\LocalizedCalendar::weekday(1));
        self::assertSame('chaque semaine (lundi, dimanche)', \App\Support\EventRecurrence::describe('weekly', [1, 7]));
        $this->useLocale('en');
        self::assertSame('Mar', \App\Support\LocalizedCalendar::month(3, true));
        self::assertSame('Sunday', \App\Support\LocalizedCalendar::weekday(7));
        self::assertSame('12 March 2026 at 14:05', \App\Support\LocalizedCalendar::dateTime(12, 3, '2026', '14:05'));
        self::assertSame('every week (Monday, Sunday)', \App\Support\EventRecurrence::describe('weekly', [1, 7]));
    }

    public function testCountryLabelInEnglishWithoutIntl(): void
    {
        self::assertSame('Germany', CommunityLanguages::countryLabelIn('de', 'en'));
        self::assertSame('Allemagne', CommunityLanguages::countryLabelIn('DE', 'fr'));
        self::assertSame('United Kingdom', CommunityLanguages::countryLabelIn('GB', 'en-GB'));
    }

    public function testStoredFrenchMilestonesAreRewrittenForEnglishReaders(): void
    {
        $stored = [
            ['milestone_key' => 'players_10', 'title' => '10 opérateurs enregistrés', 'narrative' => 'Avec 10 opérateurs…', 'value_label' => '10 opérateurs'],
            ['milestone_key' => 'first_sse_mission', 'title' => 'Première mission SSE', 'narrative' => 'Première mission SSE ouverte.', 'value_label' => null],
            ['milestone_key' => 'first_authenticated_terminal', 'title' => 'Premier terminal authentifié', 'narrative' => '', 'value_label' => null],
            ['milestone_key' => 'first_ops_50_players', 'title' => 'x', 'narrative' => 'y', 'value_label' => '62 joueurs', 'context_json' => '{"players":62}'],
            ['milestone_key' => 'first_international_org', 'title' => 'x', 'narrative' => 'y', 'value_label' => 'BE', 'context_json' => ['country_code' => 'be']],
            ['milestone_key' => 'version_1_0_0', 'title' => 'ATHENA 1.0.0', 'narrative' => 'Fondation multi-communautés', 'value_label' => '1.0.0'],
            ['milestone_key' => 'custom_note', 'title' => 'Jalon saisi', 'narrative' => 'Texte libre', 'value_label' => null],
        ];

        $this->useLocale('en');
        $en = array_map([AthenaHeritageNarration::class, 'localizeMilestone'], $stored);
        self::assertSame('10 registered operators', $en[0]['title']);
        self::assertSame('With 10 registered operators, ATHENA reaches a new stage of its international deployment.', $en[0]['narrative']);
        self::assertSame('10 operators', $en[0]['value_label']);
        self::assertSame('First SSE mission', $en[1]['title']);
        self::assertSame('First authenticated terminal', $en[2]['title']);
        self::assertSame('First operation bringing together 62 players.', $en[3]['narrative']);
        self::assertSame('62 players', $en[3]['value_label']);
        self::assertSame('First international organisation registered (BE).', $en[4]['narrative']);
        self::assertStringStartsWith('Multi-community foundation', $en[5]['narrative']);
        self::assertSame('Jalon saisi', $en[6]['title'], 'un jalon inconnu reste tel quel');

        $this->useLocale('fr');
        $fr = AthenaHeritageNarration::localizeMilestone($stored[0]);
        self::assertSame('10 opérateurs enregistrés', $fr['title']);
        self::assertSame("1\u{202F}000 opérateurs", AthenaHeritageNarration::localizeMilestone(['milestone_key' => 'players_1000'])['value_label']);
    }
}
