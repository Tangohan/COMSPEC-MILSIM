<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** App Explosifs et déclencheur réseau : le serveur décide, pas de tir sans droit, types lus chez ACE, page mise à jour sur place. */
final class AtakExplosivesAppTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';

    private static function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path));
    }

    public function testObjetDeclareSansModelePropreNiEntreeAce(): void
    {
        $cfg = self::read(self::MAIN . 'config.cpp');
        self::assertStringContainsString('class COMSPEC_ATAK_NetDetonator: ItemCore', $cfg);
        self::assertStringContainsString('class Item_COMSPEC_ATAK_NetDetonator: Item_Base_F', $cfg);
        self::assertStringContainsString('"Item_COMSPEC_ATAK_NetDetonator"', $cfg);
        self::assertStringContainsString('"COMSPEC_ATAK_NetDetonator"', $cfg);
        self::assertStringContainsString('model="\A3\Structures_F\Items\Tools\MultiMeter_F.p3d"', $cfg);
        self::assertStringNotContainsString('ace_explosives_detonator=', $cfg);
        self::assertStringNotContainsString('class ACE_Triggers', $cfg);
        self::assertStringContainsString('class exploNet {};', $cfg);
        self::assertStringContainsString('class exploRow {};', $cfg);
        self::assertFileExists(dirname(__DIR__, 2) . '/' . self::MAIN . 'data/item_netdetonator.paa');
        self::assertFileExists(dirname(__DIR__, 2) . '/mod/COMSPEC_ATAK_Native/tools/gen_net_detonator_icon.py');
    }

    public function testNomsAceVerifiesEtPoseParApiPublique(): void
    {
        $net = self::read(self::MAIN . 'functions/explo/fn_exploNet.sqf');
        self::assertStringContainsString('call ace_explosives_fnc_placeExplosive', $net);
        self::assertStringContainsString('["ACE_Explosives_Place", 0, ["ACE_MainActions", "ACE_SetTrigger"], _pose, true] call ace_interact_menu_fnc_addActionToClass', $net);
        self::assertStringContainsString('["CAManBase", 1, ["ACE_SelfActions", "ACE_Explosives"], _link, true]', $net);
        self::assertStringContainsString('"ace_explosives_defuse"', $net);
        self::assertStringContainsString('"ace_explosives_explodeOnDefuse"', $net);
        self::assertStringContainsString('"ace_explosives_place"', $net);
        self::assertStringContainsString('"ace_explosives_clackers"', $net);
        // Ni cellulaire ni homme mort : ces déclencheurs ont leur propre logique dans ACE.
        self::assertStringContainsString('["", "Command", "MK16_Transmitter"]', $net);
    }

    public function testServeurDecideDroitArmementLiaisonEtDetonation(): void
    {
        $net = self::read(self::MAIN . 'functions/explo/fn_exploNet.sqf');
        $srv = substr($net, (int) strpos($net, 'case "srvCmd":'));
        // Appelant vérifié : un client ne parle pas au nom d'un autre.
        self::assertStringContainsString('(owner _unit) isNotEqualTo remoteExecutedOwner', $srv);
        self::assertStringContainsString('(_rec select 0) isNotEqualTo _uid', $srv);
        self::assertStringContainsString('_verb isEqualTo "fire" && {!(_rec select 4)}', $srv);
        self::assertStringContainsString('["path", [_e, _unit]] call NET_FN', $srv);
        self::assertStringContainsString('[_unit, -1, [_e, _fuse], NET_ITEM] call ace_explosives_fnc_detonateExplosive', $srv);
        self::assertStringContainsString('comspec_atak_native_explo_net_delay', $srv);
        // Le client ne détone jamais une charge réseau lui-même.
        $act = self::read(self::MAIN . 'functions/explo/fn_exploAction.sqf');
        self::assertStringContainsString('case "net": { ["cmd", ["fire", [_e]]] call comspec_atak_native_fnc_exploNet; };', $act);
        // État public, reçu par ceux qui rejoignent ; liaison perdue au respawn comme les clackers d'ACE.
        self::assertStringContainsString('_e setVariable [NET_VAR, [_uid, name _unit, _mag, _trig, false], true];', $net);
        self::assertStringContainsString('missionNamespace setVariable [NET_LIST,', $net);
        self::assertStringContainsString('["cmd", ["release", []]] call comspec_atak_native_fnc_exploNet;', self::read(self::MAIN . 'XEH_postInitClient.sqf'));
        // Motifs de refus : brouillage, hors réseau, relais exigés, téléphone.
        foreach (['"jam"', '"range"', '"relay"', 'Téléphone sans signal', 'Téléphone brouillé', 'Mode avion'] as $motif) {
            self::assertStringContainsString($motif, $net);
        }
        self::assertStringContainsString('fnc_radioLink', $net);
    }

    public function testRegleDuClackerRespectee(): void
    {
        $act = self::read(self::MAIN . 'functions/explo/fn_exploAction.sqf');
        self::assertStringContainsString('call ace_explosives_fnc_getDetonators', $act);
        self::assertStringContainsString('[player, _c get "range", [_e, _c get "clackFuse"], _c get "det"] call ace_explosives_fnc_detonateExplosive', $act);
        // L'ancien tir sans portée ni clacker, et setDamage envoyé depuis un client, ont disparu.
        self::assertStringNotContainsString('"#scripted"', $act);
        self::assertStringNotContainsString('remoteExecCall ["setDamage"', $act);
        // La mine passive n'a pas de bouton FEU.
        $row = self::read(self::MAIN . 'functions/explo/fn_exploRow.sqf');
        self::assertStringContainsString('if (_kind in ["net", "atak", "command", "vanilla"])', $row);
    }

    public function testConfirmationEtMiseAJourSurPlace(): void
    {
        $act = self::read(self::MAIN . 'functions/explo/fn_exploAction.sqf');
        $page = self::read(self::MAIN . 'functions/explo/fn_pageExplo.sqf');
        self::assertStringContainsString('["ask"] call _call', self::read(self::MAIN . 'functions/explo/fn_exploRow.sqf'));
        self::assertStringContainsString('case "askYes":', $act);
        self::assertStringContainsString('["exploFireRow", false] call comspec_atak_native_fnc_formSet', $page);
        self::assertStringContainsString('}, [_n], 0.7] call comspec_atak_native_fnc_waitAndExec', $act);
        // Une seconde, page ouverte seulement ; la page entière n'est redessinée que si la structure change.
        self::assertStringContainsString('["live"] call comspec_atak_native_fnc_exploAction; }, 1] call CBA_fnc_addPerFrameHandler', $act);
        self::assertStringContainsString('if (([_list] call _sigOf) isNotEqualTo (_s getOrDefault ["sig", []])) exitWith { call _render; };', $act);
        self::assertStringNotContainsString('nextDraw', $act);
        $form = self::read(self::MAIN . 'functions/ui/fn_formSet.sqf');
        self::assertStringContainsString('private _each = _v isEqualType [];', $form);
    }

    public function testCouleursDuThemeEtTraductions(): void
    {
        foreach (['fn_pageExplo.sqf', 'fn_exploRow.sqf', 'fn_exploAction.sqf'] as $f) {
            self::assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{6}/', self::read(self::MAIN . 'functions/explo/' . $f), $f);
        }
        $en = self::read(self::MAIN . 'i18n/en.txt');
        foreach (['Déclencheur réseau', 'Charge brouillée', 'CONFIRMER LA MISE À FEU', 'Téléphone sans signal : %1', 'Portée du déclencheur réseau (m)'] as $fr) {
            self::assertStringContainsString('["' . $fr . '", "', $en);
        }
        $st = self::read(self::MAIN . 'stringtable.xml');
        self::assertStringContainsString('<French>Déclencheur réseau</French>', $st);
        self::assertStringContainsString('<English>Network detonator</English>', $st);
    }

    public function testReglagesCbaEtInitialisations(): void
    {
        $pre = self::read(self::MAIN . 'XEH_preInit.sqf');
        foreach (['explo_net_range', 'explo_net_delay', 'explo_net_consume'] as $k) {
            self::assertStringContainsString('"comspec_atak_native_' . $k . '"', $pre);
        }
        self::assertStringContainsString('["init"] call comspec_atak_native_fnc_exploNet;', self::read(self::MAIN . 'XEH_postInitServer.sqf'));
        self::assertStringContainsString('["clientInit"] call comspec_atak_native_fnc_exploNet;', self::read(self::MAIN . 'XEH_postInitClient.sqf'));
        self::assertStringContainsString('["aceInit"] call comspec_atak_native_fnc_exploNet;', self::read(self::MAIN . 'functions/shell/fn_aceMenu.sqf'));
    }
}
