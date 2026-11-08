<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Tactical\AirBriefService;
use App\Services\Tactical\AtakExperienceService;
use PHPUnit\Framework\TestCase;

/**
 * Mode pilote, FIRES court, apps regroupées et désactivation par communauté : câblage du mod et du site.
 */
final class AtakPilotSpacesTest extends TestCase
{
    private const MAIN = '/mod/COMSPEC_ATAK_Native/Sources/addons/main/';

    private function src(string $rel): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . $rel);
    }

    public function testEveryOptionalPhoneAppCanBeRuledFromAthena(): void
    {
        $cfg = $this->src(self::MAIN . 'config.cpp');
        $block = substr($cfg, (int) strpos($cfg, 'class COMSPEC_ATAK_Apps {'));
        $block = substr($block, 0, (int) strpos($block, 'class CfgWeapons'));
        preg_match_all('/^\s*class (\w+)\s+\{ name=/m', $block, $m);
        $ids = array_column((new AtakExperienceService())->phoneApps(), 'id');
        // Hors de la règle, par conception : ce qui fait partie du téléphone et ce qui dépend des droits ou du poste.
        $missing = array_values(array_diff($m[1], $ids, ['Settings', 'Store', 'Alerts', 'Admin', 'Toc']));
        self::assertSame([], $missing);
    }

    public function testSettingsAreOffByDefaultAndProposedInTheWizard(): void
    {
        $pre = $this->src(self::MAIN . 'XEH_preInit.sqf');
        foreach (['launcher_spaces', 'pilot_mode'] as $key) {
            self::assertMatchesRegularExpression('/"comspec_atak_native_' . $key . '", "LIST",.*?\[\[0, 1, 2\], \[[^\]]*\], 0\], 1\]/s', $pre);
            self::assertStringContainsString('_p + "' . $key . '"', $this->src(self::MAIN . 'functions/network/fn_setupWizard.sqf'));
            self::assertStringContainsString('_p + "' . $key . '"', $this->src(self::MAIN . 'functions/network/fn_realismCatalog.sqf'));
        }
        // Le joueur n'a rien choisi : aucun changement.
        self::assertStringContainsString('getVariable ["COMSPEC_ATAK_SpacesOn", false]', $this->src(self::MAIN . 'functions/ui/fn_appSpaces.sqf'));
        self::assertStringContainsString('getVariable ["COMSPEC_ATAK_RoleProfile", "all"]', $this->src(self::MAIN . 'functions/ui/fn_appSpaces.sqf'));
        self::assertStringContainsString('getVariable ["COMSPEC_ATAK_PilotOn", false]', $this->src(self::MAIN . 'functions/tactical/fn_pilotMode.sqf'));
    }

    public function testGroupedAppsOnlyShowVisibleApps(): void
    {
        $src = $this->src(self::MAIN . 'functions/ui/fn_appSpaces.sqf');
        self::assertStringContainsString('fn_appVisible', $src);
        self::assertStringContainsString('"*ALL"', $this->src(self::MAIN . 'functions/pages/fn_pageSpace.sqf'));
        $cfg = $this->src(self::MAIN . 'config.cpp');
        foreach (['appSpaces', 'pageSpace', 'firesQuick', 'pageQuickMap', 'pilotMode'] as $fn) {
            self::assertStringContainsString('class ' . $fn . ' {};', $cfg);
        }
    }

    public function testShortFiresReusesTheAirBriefHandshake(): void
    {
        $src = $this->src(self::MAIN . 'functions/tactical/fn_firesQuick.sqf');
        self::assertStringContainsString('fn_mapPing', $src);
        self::assertStringContainsString('fn_markerWeb', $src);
        self::assertStringContainsString('["send", ["5", _lines, "", _dest, _ref, true]] call comspec_atak_native_fnc_airBrief', $src);
        self::assertStringContainsString('["DONE", 4]', $this->src(self::MAIN . 'functions/tactical/fn_airBrief.sqf'));
        self::assertSame(['done', 'HOG 21'], AirBriefService::parseAck('DONE:HOG 21'));
        self::assertContains('done', AirBriefService::STATUSES);
        // Le 9-LINE garde son écran : FIRES court s'ajoute comme une entrée de plus.
        self::assertStringContainsString('ENVOYER LE 9-LINE', $this->src(self::MAIN . 'functions/pages/fn_pageFires.sqf'));
    }

    public function testAppsOffCacheFollowsTheSetting(): void
    {
        $src = $this->src(self::MAIN . 'functions/ui/fn_appList.sqf');
        self::assertStringContainsString('(_cached select 0) isEqualTo _offRaw', $src);
        self::assertStringContainsString('- ["settings", "store"]', $src);
        self::assertStringContainsString('"comspec_atak_native_apps_off"', $this->src(self::MAIN . 'XEH_postInitClient.sqf'));
    }

    public function testNewPhoneStringsHaveAnEnglishTranslation(): void
    {
        $en = $this->src(self::MAIN . 'i18n/en.txt');
        foreach (['Lanceur : apps regroupées', 'Mode pilote', 'FIRES court', 'Touchez la cible sur la carte', 'Profil de rôle'] as $fr) {
            self::assertStringContainsString('["' . $fr . '", "', $en);
        }
    }
}
