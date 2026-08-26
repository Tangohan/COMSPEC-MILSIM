<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakZenEdenAssetTest extends TestCase
{
    private function connectRoot(): string
    {
        return dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect';
    }

    public function testEdenModulesAreListedAndWired(): void
    {
        $cfg = (string) file_get_contents($this->connectRoot() . '/config.cpp');
        foreach ([
            'COMSPEC_Module_NoCoverage',
            'COMSPEC_Module_Interference',
            'COMSPEC_Module_Degraded',
            'COMSPEC_Module_Jammer',
            'COMSPEC_Module_SSE_Case',
            'COMSPEC_Module_SSE_Profile',
            'COMSPEC_Module_SSE_Equip',
            'COMSPEC_Module_TheaterSurvey',
            'COMSPEC_Module_AtakShowEnemyAi',
        ] as $cls) {
            self::assertStringContainsString('"' . $cls . '"', $cfg, $cls);
            self::assertStringContainsString($cls, $cfg);
        }

        self::assertStringContainsString('class COMSPEC_Roleplay', $cfg);
        self::assertStringContainsString('class COMSPEC_SSE', $cfg);
        self::assertStringContainsString('class COMSPEC_Outils', $cfg);
        self::assertStringContainsString('module_roleplay_zone.hpp', $cfg);
        self::assertStringContainsString('module_sse.hpp', $cfg);
        self::assertStringContainsString('module_theater_survey.hpp', $cfg);
        self::assertStringContainsString('module_atak_show_enemy_ai.hpp', $cfg);
        self::assertStringContainsString('display_theater_survey.hpp', $cfg);
        self::assertStringContainsString('class registerZenRoleplayModules {};', $cfg);
        self::assertStringContainsString('class registerZenAtakPlayerActions {};', $cfg);
        self::assertStringContainsString('class registerZenSseModules {};', $cfg);
        self::assertStringContainsString('class registerZenTrackActions {};', $cfg);
        self::assertStringContainsString('class registerZenTheaterSurvey {};', $cfg);
        self::assertStringContainsString('class moduleAtakShowEnemyAi {};', $cfg);
        self::assertStringContainsString('1.4.82', $cfg);
    }

    public function testZeusRegistrationWaitsForEnhancedAndKeepsRetrySafe(): void
    {
        $post = (string) file_get_contents($this->connectRoot() . '/XEH_postInit.sqf');
        self::assertStringContainsString('registerZenRoleplayModules', $post);
        self::assertStringContainsString('registerZenAtakPlayerActions', $post);
        self::assertStringContainsString('registerZenSseModules', $post);
        self::assertStringContainsString('registerZenTrackActions', $post);
        self::assertStringContainsString('registerZenTheaterSurvey', $post);
        self::assertStringContainsString('COMSPEC_ZenRegisterDeadline', $post);
        self::assertStringContainsString('CBA_fnc_waitUntilAndExecute', $post);
        self::assertStringContainsString('zen_custom_modules_fnc_register', $post);

        $track = (string) file_get_contents($this->connectRoot() . '/functions/fn_registerZenTrackActions.sqf');
        self::assertStringContainsString('COMSPEC_ZenTrackActionsRegistered', $track);
        self::assertStringContainsString('COMSPEC_AceTrackActionsRegistered', $track);
        self::assertStringContainsString('Afficher les IA ennemies sur la carte', $track);
        self::assertStringContainsString('Balise GPS véhicule', $track);
        self::assertStringContainsString('Géolocalisation téléphone', $track);
        self::assertStringContainsString('IA alliée sur l’ATAK', $track);

        $atak = (string) file_get_contents($this->connectRoot() . '/functions/fn_registerZenAtakPlayerActions.sqf');
        self::assertStringContainsString('COMSPEC_ZenAtakPlayerModulesRegistered', $atak);
        self::assertStringContainsString('COMSPEC_AceAtakPlayerModulesRegistered', $atak);
        self::assertStringContainsString('ATAK — Éditer joueur', $atak);
        self::assertStringNotContainsString(
            "if (missionNamespace getVariable [\"COMSPEC_ZenAtakPlayerModulesRegistered\", false]) exitWith {};",
            $atak
        );

        $role = (string) file_get_contents($this->connectRoot() . '/functions/fn_registerZenRoleplayModules.sqf');
        self::assertStringContainsString('isNil "zen_custom_modules_fnc_register"', $role);
        self::assertStringContainsString('Brouilleur ATAK actif', $role);

        $sse = (string) file_get_contents($this->connectRoot() . '/functions/fn_registerZenSseModules.sqf');
        self::assertStringContainsString('Dossier SSE actif', $sse);
        self::assertStringContainsString('Profil d\'identité SSE', $sse);
    }

    public function testTheaterSurveyKeepsZeusCursorAndAcceptsEdenCombo(): void
    {
        $show = (string) file_get_contents($this->connectRoot() . '/functions/fn_theaterSurveyShow.sqf');
        self::assertStringNotContainsString('findDisplay 312', $show);
        self::assertStringContainsString('findDisplay 46', $show);

        $dlg = (string) file_get_contents($this->connectRoot() . '/display_theater_survey.hpp');
        self::assertStringContainsString('enableSimulation = 1', $dlg);
        self::assertStringContainsString('idd = 9994', $dlg);
        self::assertStringContainsString('Relevé de la carte', $dlg);

        $mod = (string) file_get_contents($this->connectRoot() . '/functions/fn_moduleTheaterSurvey.sqf');
        self::assertStringContainsString('_mode isEqualType 0', $mod);
        self::assertStringContainsString('_runAtStart', $mod);

        $enemy = (string) file_get_contents($this->connectRoot() . '/functions/fn_moduleAtakShowEnemyAi.sqf');
        self::assertStringContainsString('_mode isEqualType 0', $enemy);
        self::assertStringContainsString('_show', $enemy);

        $zen = (string) file_get_contents($this->connectRoot() . '/functions/fn_registerZenTheaterSurvey.sqf');
        self::assertStringContainsString('ace_zeus_fnc_addModule', $zen);
        self::assertStringContainsString('zen_context_menu_fnc_addAction', $zen);
    }

    public function testEdenObjectAttributesCoverGpsPhoneAndAlly(): void
    {
        $eden = (string) file_get_contents($this->connectRoot() . '/modules/eden_sse_attributes.hpp');
        self::assertStringContainsString('Téléphone visible sur l’ATAK', $eden);
        self::assertStringContainsString('IA alliée visible sur l’ATAK', $eden);
        self::assertStringContainsString('Balise GPS (suivi ATAK)', $eden);
        self::assertStringContainsString('objectVehicle', $eden);
        self::assertStringContainsString('objectBrain', $eden);
        self::assertStringNotContainsString('sqf', strtolower($eden));
        self::assertStringNotContainsString('json', strtolower($eden));
        self::assertStringNotContainsString('endpoint', strtolower($eden));
    }

    public function testCatalogUpdateMentionsZeusMenus(): void
    {
        $php = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Support/DevDispatchCatalog.php');
        self::assertStringContainsString('$pr(220', $php);
        self::assertStringContainsString('Menus Zeus et éditeur au rendez-vous', $php);
        self::assertStringContainsString('COMSPEC Outils', $php);
        self::assertStringContainsString('COMSPEC Roleplay', $php);
    }
}
