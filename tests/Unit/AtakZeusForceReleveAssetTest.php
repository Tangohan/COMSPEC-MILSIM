<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Centre Zeus : forcer le relevé d'un joueur ou des joueurs affichés (fn_zeusCentre « force », fn_zeusState « force » /
 * « forceScan » / « poll » forcé, menu ZEN). Même canal que le relevé (événements CBA via le serveur, droits vérifiés sur le
 * serveur), anti-abus des trois côtés, états demandé / reçu / sans réponse, aucun message hors du Centre.
 */
final class AtakZeusForceReleveAssetTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';

    private function read(string $rel): string
    {
        $src = str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . self::MAIN . $rel));

        return (string) preg_replace(['~/\*.*?\*/~s', '~^\s*//.*$~m'], '', $src);
    }

    public function testCentreHasTheButtonsAndTheZenEntry(): void
    {
        $c = $this->read('functions/zeus/fn_zeusCentre.sqf');
        self::assertStringContainsString('["FORCER LE RELEVÉ", "FORCER", "', $c);
        self::assertStringContainsString('"[\'force\'] call comspec_atak_native_fnc_zeusCentre;", "tgt"]', $c);
        self::assertStringContainsString('"FORCER TOUS LES RELEVÉS", "TOUT RELEVER"', $c);
        self::assertStringContainsString("['force', ['list']] call comspec_atak_native_fnc_zeusCentre;", $c);
        self::assertStringContainsString('[_row, _row, [2, 1], [[_search, 0, 1], [_forceAll, 1, 1]]]', $c);
        self::assertStringContainsString('case "force": {', $c);
        $menu = $this->read('functions/zeus/fn_zeusMenu.sqf');
        self::assertStringContainsString('["comspec_atak_zeus_force", "Forcer le relevé"', $menu);
        self::assertStringContainsString('[_force, ["comspec_atak_zeus"], 101] call zen_context_menu_fnc_addAction;', $menu);
        self::assertStringContainsString('["force", [_u]] call comspec_atak_native_fnc_zeusCentre;', $menu);
    }

    public function testRequestGoesThroughTheServerWhichChecksRights(): void
    {
        $s = $this->read('functions/zeus/fn_zeusState.sqf');
        // Zeus -> serveur ; le serveur vérifie les droits (comme le relevé) avant tout envoi.
        self::assertStringContainsString('["comspec_atak_native_zeusStateForce", [player, _uids]] call CBA_fnc_serverEvent;', $s);
        self::assertMatchesRegularExpression(
            '/\["comspec_atak_native_zeusStateForce", \{.*?if !\(\[_caller\] call comspec_atak_native_fnc_adminAllowed\) exitWith \{\s*diag_log.*?\["forceScan", \[_caller, _uids\]\]/s',
            $s
        );
        // Serveur -> joueurs visés seulement, par l'événement de relevé existant ; journal du serveur.
        self::assertStringContainsString('["comspec_atak_native_zeusStatePoll", [true], _to] call CBA_fnc_targetEvent;', $s);
        self::assertStringContainsString('[COMSPEC ATAK NATIVE][INFO][ZEUS] Relevé forcé par %1', $s);
        // Un poste plus ancien reçoit le même événement et y répond comme à un relevé ordinaire : rien de nouveau à déclarer.
        $pre = $this->read('XEH_preInit.sqf');
        self::assertStringContainsString('["comspec_atak_native_zeusStatePoll", { ["poll", _this] call comspec_atak_native_fnc_zeusState; }]', $pre);
        self::assertDoesNotMatchRegularExpression('/\bremoteExec(Call)?\b/', $s);
    }

    public function testOneRequestPerPlayerEveryFiveSecondsOnEachSide(): void
    {
        $s = $this->read('functions/zeus/fn_zeusState.sqf');
        // Zeus.
        self::assertStringContainsString('if (_now - ((_f getOrDefault [_uid, [-100]]) select 0) < 5) then { _skip = _skip + 1; continue; };', $s);
        // Serveur.
        self::assertStringContainsString('{_now - (_at getOrDefault [_u, -100]) >= 5}', $s);
        self::assertStringContainsString('select [0, 256]', $s);
        // Joueur : forcé seulement depuis le serveur, une fois toutes les 5 s ; sinon relevé ordinaire (dédoublonné).
        self::assertStringContainsString('{!isRemoteExecuted || {remoteExecutedOwner isEqualTo 2}}', $s);
        self::assertStringContainsString('diag_tickTime - (uiNamespace getVariable ["COMSPEC_ATAK_ZeusForceRx", -100]) >= 5', $s);
        self::assertStringContainsString('if (!_force && {_sig isEqualTo _old} && {diag_tickTime - _at < 30}) exitWith {};', $s);
    }

    public function testCentreShowsRequestedReceivedOrNoReply(): void
    {
        $c = $this->read('functions/zeus/fn_zeusCentre.sqf');
        // Reçu : fiche arrivée après la demande ; demandé : moins de 8 s ; sinon sans réponse.
        self::assertStringContainsString('if (_rec isNotEqualTo [] && {(_rec select 1) >= (_fq select 0)}) then { _fq set [1, true]; }', $c);
        self::assertStringContainsString('_ask = ["none", "wait"] select (_now - (_fq select 0) < 8);', $c);
        self::assertStringContainsString('"Relevé demandé… (%1 s)"', $c);
        self::assertStringContainsString('"Pas de réponse (%1)"', $c);
        self::assertStringContainsString('"mod %1 trop ancien pour répondre : mettre à jour"', $c);
        self::assertStringContainsString('"téléphone éteint, mod trop ancien ou liaison coupée"', $c);
        foreach (['"SANS RELEVÉ (jamais)"', '"RELEVÉ ANCIEN (%1 min)"', '"RELEVÉ DEMANDÉ…"', '"SANS RÉPONSE"'] as $tok) {
            self::assertStringContainsString($tok, $c);
        }
        // Plusieurs joueurs : confirmation par un second clic, dans la ligne d'état du Centre.
        self::assertStringContainsString('"Forcer le relevé de %1 joueur(s) ? Cliquez à nouveau dans les 5 s pour confirmer."', $c);
        // Réponse reçue : le Centre se met à jour sans attendre sa boucle.
        $s = $this->read('functions/zeus/fn_zeusState.sqf');
        self::assertStringContainsString('["refresh"] call comspec_atak_native_fnc_zeusCentre;', $s);
    }

    public function testNothingIsShownOutsideTheCentre(): void
    {
        $code = $this->read('functions/zeus/fn_zeusState.sqf')
            . $this->read('functions/zeus/fn_zeusCentre.sqf')
            . $this->read('functions/zeus/fn_zeusMenu.sqf');
        self::assertDoesNotMatchRegularExpression('/\b(hint|hintSilent|systemChat|titleText|cutText|BIS_fnc_showNotification|createDialog)\b/', $code);
        // Côté joueur, le relevé forcé n'appelle aucune notification du téléphone.
        preg_match('/case "poll": \{(.*?)\n    \};/s', $this->read('functions/zeus/fn_zeusState.sqf'), $poll);
        self::assertNotEmpty($poll);
        self::assertStringNotContainsString('fnc_notify', $poll[1]);
    }

    public function testEveryNewTextHasEnglish(): void
    {
        $en = str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . self::MAIN . 'i18n/en.txt'));
        foreach ([
            'FORCER LE RELEVÉ', 'FORCER', 'FORCER TOUS LES RELEVÉS', 'TOUT RELEVER', 'SANS RELEVÉ (jamais)', 'RELEVÉ ANCIEN (%1 min)',
            'RELEVÉ DEMANDÉ…', 'SANS RÉPONSE', 'Relevé demandé… (%1 s)', 'Pas de réponse (%1)', 'mod %1 trop ancien pour répondre : mettre à jour',
            'joueur sans le mod, ou mod trop ancien pour répondre', 'joueur mort ou en réapparition',
            'téléphone éteint, mod trop ancien ou liaison coupée', 'Relevé demandé à %1 joueur(s).', '%1 déjà demandé(s) il y a moins de 5 s.',
            'Forcer le relevé de %1 joueur(s) ? Cliquez à nouveau dans les 5 s pour confirmer.', 'Forcer le relevé',
        ] as $fr) {
            self::assertSame(1, substr_count($en, "\n[\"" . $fr . '", "'), "« {$fr} » : une traduction anglaise attendue");
        }
    }
}
