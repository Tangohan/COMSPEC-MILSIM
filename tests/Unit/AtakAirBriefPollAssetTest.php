<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Tactical\AirBriefService;
use PHPUnit\Framework\TestCase;

/**
 * Sources SQF du téléphone : boîte BRIEFS AIR relevée sans dépendre de la place à bord (tick toutes les 4 s, relevé
 * borné, relancé au réveil, à l'ouverture et au retour de la liaison), noms interrogés, clé d'indicatif identique à celle
 * d'Athena, liaison de bord (rien de retenu à un poste d'équipage), une alerte par brief nouveau par fn_notify seulement,
 * pastille de l'app, diagnostic de la page et textes anglais. Lecture des fichiers, indépendante des fins de ligne.
 */
final class AtakAirBriefPollAssetTest extends TestCase
{
    private const MAIN = '/mod/COMSPEC_ATAK_Native/Sources/addons/main/';

    private static function read(string $rel): string
    {
        $src = file_get_contents(dirname(__DIR__, 2) . self::MAIN . $rel);
        self::assertIsString($src, $rel);

        return str_replace("\r\n", "\n", $src);
    }

    /** Bloc d'une action du switch de fn_airBrief (du « case » au « case » suivant de même retrait). */
    private static function action(string $src, string $name): string
    {
        $start = strpos($src, "\n    case \"" . $name . '": {');
        self::assertNotFalse($start, $name);
        $next = strpos($src, "\n    case \"", $start + 10);

        return substr($src, $start, ($next === false ? strlen($src) : $next) - $start);
    }

    public function testPollTickRunsEveryFourSecondsAndIsNoLongerTiedToTheSeat(): void
    {
        $xeh = self::read('XEH_postInitClient.sqf');
        self::assertStringContainsString('[{ ["tick"] call comspec_atak_native_fnc_airBrief; }, 4] call COMSPEC_ATAK_PfhSpread;', $xeh);
        self::assertStringNotContainsString('[{ ["poll"] call comspec_atak_native_fnc_airBrief; }, 12]', $xeh);

        $src = self::read('functions/tactical/fn_airBrief.sqf');
        $poll = self::action($src, 'poll');
        self::assertStringNotContainsString('!(_v isKindOf "Air")}) exitWith', $poll, 'plus de relevé réservé au siège d’un aéronef');
        self::assertStringContainsString('call _whyNot', $poll);
        self::assertStringContainsString('"GetCASForCallsign"', $poll);
        self::assertStringContainsString('comspec_atak_native_fnc_extAwait', $poll, 'appel asynchrone (spawn + extAwait), jamais par image');
        self::assertStringContainsString('] spawn {', $poll);
        self::assertStringContainsString('comspec_atak_native_fnc_waitAndExec', $poll, 'relevé forcé relancé (la DLL rend le relevé précédent)');

        $tick = self::action($src, 'tick');
        self::assertStringContainsString('[45, 15] select _inAir', $tick, 'intervalle borné : 15 s à bord, 45 s à pied');
        self::assertStringContainsString('_gap >= 5', $tick);
        self::assertStringContainsString('!_wasOpen', $tick, 'réveil du téléphone');
        self::assertStringContainsString('!_wasApp', $tick, 'ouverture de l’app JTAC ou pilote');
        self::assertStringContainsString('!_wasOk', $tick, 'retour de la liaison');
        self::assertStringContainsString('["poll", true]', $tick);
        self::assertStringContainsString('< 1800', $tick, 'pilote sorti de l’appareil depuis moins de 30 min');
        self::assertStringContainsString('"kindOf", player', $tick);
        self::assertStringNotContainsString('allPlayers', $tick, 'rien de lourd toutes les 4 s');
        self::assertStringNotContainsString('nearestObjects', $tick);
    }

    public function testPolledNamesCoverEveryCallsignOfThePlayer(): void
    {
        $names = self::action(self::read('functions/tactical/fn_airBrief.sqf'), 'names');
        foreach (['[_v] call _airCs', 'COMSPEC_ATAK_AirCsLast', 'comspec_overwatch_connect_fnc_getCallsign', 'comspec_atak_native_fnc_unitCallsign', 'groupId group player', 'name player', '_l select [0, 8]', '!(_k in _ks)', '!(_k in _generic)'] as $needle) {
            self::assertStringContainsString($needle, $names, $needle);
        }
    }

    public function testSqfCallsignKeyFollowsTheServerRule(): void
    {
        $src = self::read('functions/tactical/fn_airBrief.sqf');
        self::assertStringContainsString('toArray (toLower _t)', $src);
        self::assertStringContainsString('(_x >= 48 && {_x <= 57}) || {_x >= 97 && {_x <= 122}}', $src, 'chiffres et lettres a-z seulement');
        self::assertSame(1, preg_match('/_acc = createHashMapFromArray \[(.*?)\];/s', $src, $m));
        self::assertSame(22, preg_match_all('/\[(\d+), (\d+)\]/', $m[1], $pairs, PREG_SET_ORDER));
        foreach ($pairs as $p) {
            self::assertSame(chr((int) $p[2]), AirBriefService::callsignKey(mb_chr((int) $p[1], 'UTF-8')), 'accent ' . $p[1] . ' ramené comme sur Athena');
        }
        self::assertSame(1, preg_match('/private _generic = \[(.*?)\];/', $src, $g));
        preg_match_all('/"([a-z]+)"/', $g[1], $gm);
        $generic = $gm[1];
        self::assertSame([], AirBriefService::inboxKeys($generic), 'mêmes noms génériques ignorés des deux côtés');
    }

    public function testAircrewSeatIsNeverHeldOffline(): void
    {
        $xeh = self::read('XEH_postInitClient.sqf');
        foreach (['comspec_atak_native_airBrief', 'comspec_atak_native_cas'] as $ev) {
            self::assertStringContainsString('["' . $ev . '", { if (!(["airLink"] call comspec_atak_native_fnc_airBrief) && {["hold", ["' . $ev . '", _this]] call comspec_atak_native_fnc_offline}) exitWith {};', $xeh, $ev);
        }
        $src = self::read('functions/tactical/fn_airBrief.sqf');
        self::assertStringContainsString('private _airLink = { ((vehicle player) isKindOf "Air") && {([player] call comspec_atak_native_fnc_vehicleTerminal) isEqualTo "air"} };', $src);
        self::assertStringContainsString('if (!(call _airLink) && {!(["online"] call comspec_atak_native_fnc_offline)}) exitWith { "hors réseau" };', $src);
    }

    public function testOneClearPhoneNotificationPerNewBriefAndNoGameAlert(): void
    {
        $src = self::read('functions/tactical/fn_airBrief.sqf');
        $store = self::action($src, 'store');
        self::assertStringContainsString('"9 LIGNES reçues de %1"', $store);
        self::assertStringContainsString('"5 LIGNES reçues de %1"', $store);
        self::assertStringContainsString('_head + " — %2"', $store);
        self::assertStringContainsString('if (_i >= 0) exitWith {', $store, 'brief déjà rangé : pas de nouvelle alerte');
        self::assertStringContainsString('call comspec_atak_native_fnc_notify', $store);
        foreach (['fn_airBrief.sqf', 'fn_jtacAction.sqf'] as $f) {
            $code = self::read('functions/tactical/' . $f);
            foreach (['hint ', 'hintSilent', 'systemChat', 'cutText', 'titleText', 'BIS_fnc_showNotification', 'showNotification', 'BIS_fnc_guiMessage'] as $banned) {
                self::assertStringNotContainsString($banned, $code, $f . ' : ' . $banned);
            }
        }
        $badge = self::read('functions/ui/fn_appBadge.sqf');
        self::assertStringContainsString("case \"JTAC\";\n    case \"PILOT\": { [\"unread\"] call comspec_atak_native_fnc_airBrief };", $badge);
    }

    public function testDiagnosticLineIsShownInBriefsAirAndTranslated(): void
    {
        $src = self::read('functions/tactical/fn_airBrief.sqf');
        $diag = self::action($src, 'diag');
        foreach (['"Boîte interrogée pour : %1"', '"dernier relevé il y a %1 s"', '"%1 brief(s) en attente"', '"liaison OK"', '"liaison KO (%1)"', 'call _whyNot'] as $needle) {
            self::assertStringContainsString($needle, $diag, $needle);
        }
        self::assertStringContainsString('["diag"] call comspec_atak_native_fnc_airBrief', self::read('functions/tactical/fn_pageJtac.sqf'));

        $en = self::read('i18n/en.txt');
        foreach (['9 LIGNES reçues de %1', '9 LIGNES reçues de %1 — %2', '5 LIGNES reçues de %1 — %2', 'MEDEVAC reçu de %1 — %2', 'Boîte interrogée pour : %1',
            'dernier relevé il y a %1 s', 'dernier relevé : jamais', '%1 brief(s) en attente', 'liaison KO (%1)', 'liaison OK', 'pas de téléphone', 'Athena non relié', 'hors réseau'] as $fr) {
            self::assertStringContainsString('["' . $fr . '", "', $en, $fr);
        }
    }

    public function testJtacAppMatchesTypedCallsignsByKeyAndBuildsSafeMissionIds(): void
    {
        $src = self::read('functions/tactical/fn_jtacAction.sqf');
        self::assertStringContainsString('private _tk = ["key", _t] call comspec_atak_native_fnc_airBrief;', $src);
        self::assertStringContainsString('(["key", [_x] call comspec_atak_native_fnc_unitCallsign] call comspec_atak_native_fnc_airBrief) isEqualTo _tk', $src);
        self::assertStringContainsString('format ["%1-%2", getPlayerUID player, (round (diag_tickTime * 1000)) toFixed 0]', $src, 'pas de « 6.35867e+06 » : deux missions proches ne partagent plus la même ref');
    }
}
