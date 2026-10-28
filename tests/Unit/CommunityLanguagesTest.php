<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Organization\OrbatChartPdfService;
use App\Support\CommunityLanguages;
use App\Support\OrbatSheetLabels;
use PHPUnit\Framework\TestCase;

final class CommunityLanguagesTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testCatalogOffersFrenchEnglishRussianSpanish(): void
    {
        self::assertSame(['fr', 'en', 'ru', 'es'], CommunityLanguages::codes());
        self::assertSame('ru', CommunityLanguages::normalize('RU'));
        self::assertSame('es', CommunityLanguages::normalize('es_ES'));
        self::assertSame('fr', CommunityLanguages::normalize('fr-FR'));
        self::assertNull(CommunityLanguages::normalize('de'));
        self::assertSame('Russe · Русский', CommunityLanguages::fullLabel('ru'));
        self::assertSame(['en', 'ru'], CommunityLanguages::normalizeList(['ru', 'xx', 'en', 'RU']));
    }

    public function testFlagHtmlUsesFlagcdnImagesNotRegionalEmojis(): void
    {
        self::assertSame('FR', CommunityLanguages::countryCode('fr'));
        self::assertSame('GB', CommunityLanguages::countryCode('en'));
        self::assertSame('RU', CommunityLanguages::countryCode('ru'));

        $fr = CommunityLanguages::flagHtml('fr');
        self::assertStringContainsString('flagcdn.com/16x12/fr.png', $fr);
        self::assertStringNotContainsString('🇫🇷', $fr);

        $flags = CommunityLanguages::flagsHtml(['fr', 'en', 'fr']);
        self::assertStringContainsString('flagcdn.com/16x12/fr.png', $flags);
        self::assertStringContainsString('flagcdn.com/16x12/gb.png', $flags);
        self::assertSame(1, substr_count($flags, 'flagcdn.com/16x12/fr.png'));
    }

    public function testCommunityDefaultsAndOrbatLanguage(): void
    {
        $community = ['default_locale' => 'es', 'community_languages' => ['en']];
        self::assertSame('es', CommunityLanguages::communityDefault($community));
        self::assertSame(['es', 'en'], CommunityLanguages::communityLanguages($community));
        self::assertSame('', CommunityLanguages::orbatLanguageSetting($community));
        self::assertSame('es', CommunityLanguages::orbatLanguage($community));
        self::assertSame('ru', CommunityLanguages::orbatLanguage($community + ['orbat_language' => 'ru']));
        self::assertSame('fr', CommunityLanguages::communityDefault([]));
    }

    public function testUnitCountryFallsBackToRealAffiliationAndDrivesGrades(): void
    {
        $aff = ['unit_affiliation' => ['is_real' => true, 'country' => 'US']];
        self::assertSame('US', CommunityLanguages::unitCountry($aff));
        self::assertSame('RU', CommunityLanguages::unitCountry($aff + ['unit_country' => 'ru']));
        self::assertSame('', CommunityLanguages::unitCountry(['unit_affiliation' => ['is_real' => false]]));

        self::assertSame('US', CommunityLanguages::preferredGradeCountry(['community' => $aff]));
        // Pays sans catalogue de grades : on garde le système de grades de la communauté.
        self::assertSame('US', CommunityLanguages::preferredGradeCountry([
            'community' => ['unit_country' => 'RU'],
            'grade_system_code' => 'US_ARMY',
        ]));
        self::assertSame('FR', CommunityLanguages::preferredGradeCountry(['community' => ['unit_country' => 'ES']]));
    }

    public function testMemberLanguageResolution(): void
    {
        $community = ['default_locale' => 'ru'];
        $set = CommunityLanguages::resolveMember(['primary_lang' => 'es', 'other_langs' => 'es,en'], 'fr', $community);
        self::assertSame(['primary' => 'es', 'others' => ['en'], 'source' => 'member'], $set);

        $ui = CommunityLanguages::resolveMember(null, 'en', $community);
        self::assertSame('en', $ui['primary']);
        self::assertSame('interface', $ui['source']);

        $fallback = CommunityLanguages::resolveMember(null, '', $community);
        self::assertSame('ru', $fallback['primary']);
        self::assertSame('community', $fallback['source']);
    }

    public function testEveryOrbatLabelExistsInFourLanguages(): void
    {
        foreach (OrbatSheetLabels::keys() as $key) {
            foreach (CommunityLanguages::codes() as $lang) {
                self::assertNotSame('', OrbatSheetLabels::get($key, $lang), $key . '/' . $lang);
            }
        }
        foreach (OrbatSheetLabels::typeKeys() as $type) {
            self::assertNotSame('', OrbatSheetLabels::typeLabel($type, 'ru'));
        }
        self::assertSame('ОБЩИЙ СОСТАВ', OrbatSheetLabels::get('composition', 'ru'));
        self::assertSame('COMPOSICIÓN GENERAL', OrbatSheetLabels::get('composition', 'es'));
        self::assertSame('COMPOSITION GÉNÉRALE', OrbatSheetLabels::get('composition', 'xx'));
    }

    public function testPdfHtmlFollowsSheetLanguage(): void
    {
        $service = new OrbatChartPdfService();
        $doc = $service->buildDocument([
            'label' => 'Command',
            'type' => 'command',
            'unitId' => 0,
            'members' => [],
            'children' => [[
                'label' => 'ALPHA',
                'type' => 'alpha',
                'unitId' => 2,
                'members' => [],
                'children' => [],
            ]],
        ], ['unit_label' => 'TF Phoenix', 'lang' => 'ru', 'country' => 'RU', 'country_label' => 'Russie']);

        self::assertSame('ru', $doc['meta']['lang']);
        $html = $service->tcpdfFriendlyHtml($doc);
        self::assertStringContainsString('ОБЩИЙ СОСТАВ', $html);
        self::assertStringContainsString('СТРАНА', $html);
        self::assertStringNotContainsString('COMPOSITION GÉNÉRALE', $html);
    }

    public function testSettingsAndEffectifsAreWired(): void
    {
        $root = $this->root();
        $settings = (string) file_get_contents($root . '/views/admin/organization/settings.php');
        $controller = (string) file_get_contents($root . '/app/Controllers/Admin/Organization/OrganizationSettingsController.php');
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $member = (string) file_get_contents($root . '/views/admin/effectifs_workspace/member.php');
        $roster = (string) file_get_contents($root . '/views/admin/effectifs_workspace/roster.php');

        self::assertStringContainsString('id="pays-langues"', $settings);
        self::assertStringContainsString('name="community_languages[]"', $settings);
        self::assertStringContainsString('name="orbat_language"', $settings);
        self::assertStringContainsString('name="unit_country"', $settings);
        self::assertStringContainsString("'languages_form'", $controller);
        self::assertStringContainsString("/membres/{id}/langues'", $routes);
        self::assertStringContainsString('id="eff-dlg-langues"', $member);
        self::assertStringContainsString('name="langue"', $roster);
        self::assertStringContainsString('flagsHtml(', $roster);
        self::assertStringContainsString('eff-sheets__lang-flag', $roster);
    }
}
