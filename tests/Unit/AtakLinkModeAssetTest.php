<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Mode de liaison du téléphone (fn_linkMode) : aucun relais posé dans la mission = liaison satellite, le téléphone
 * marche partout ; au moins un relais (même détruit) = liaison par relais, sans repli sur le satellite.
 * Une seule source de vérité, lue par fn_relayNet puis fn_linkQuality ; réglage « Liaison du téléphone » en tête de
 * la liste COMSPEC ; les photos partent en liaison satellite.
 */
final class AtakLinkModeAssetTest extends TestCase
{
    private const MAIN = __DIR__ . '/../../mod/COMSPEC_ATAK_Native/Sources/addons/main';

    /** Fichiers SQF touchés par ce sujet. */
    private const TOUCHED = [
        'functions/network/fn_linkMode.sqf',
        'functions/network/fn_relayNet.sqf',
        'functions/core/fn_linkQuality.sqf',
        'functions/core/fn_setupCheck.sqf',
        'functions/ui/fn_statusUpdate.sqf',
        'functions/pages/fn_pagePhotos.sqf',
        'functions/pages/fn_pageGallery.sqf',
        'functions/pages/fn_photoMode.sqf',
        'functions/pages/fn_photoCam.sqf',
        'functions/pages/fn_pageNetwork.sqf',
        'XEH_preInit.sqf',
        'XEH_postInitServer.sqf',
        'XEH_postInitClient.sqf',
    ];

    private static function read(string $rel): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(self::MAIN . '/' . $rel));
    }

    /** Corps d'un `case "x": { ... }` d'un switch SQF (jusqu'au case suivant de même retrait). */
    private static function caseBody(string $src, string $name): string
    {
        $at = strpos($src, '    case "' . $name . '": {');
        self::assertNotFalse($at, $name);
        $next = strpos($src, "\n    case \"", (int) $at + 10);

        return substr($src, (int) $at, ($next === false ? strlen($src) : (int) $next) - (int) $at);
    }

    public function testFunctionIsDeclaredAndReturnsSatOrRelay(): void
    {
        self::assertStringContainsString('class relayNet {}; class linkMode {};', self::read('config.cpp'));
        $get = self::caseBody(self::read('functions/network/fn_linkMode.sqf'), 'get');
        // Le réglage d'abord, puis la détection publiée par le serveur ; « sat » tant que rien n'est publié.
        self::assertStringContainsString('missionNamespace getVariable ["comspec_atak_native_link_mode", "auto"]', $get);
        self::assertStringContainsString('case (_set isEqualTo "sat"): { "sat" };', $get);
        self::assertStringContainsString('case (_set isEqualTo "relay"): { "relay" };', $get);
        self::assertStringContainsString('["sat", "relay"] select ((missionNamespace getVariable ["COMSPEC_ATAK_LinkModeAuto", "sat"]) isEqualTo "relay")', $get);
        // Aucun calcul local des relais dans la lecture : seule la variable publiée compte.
        self::assertStringNotContainsString('COMSPEC_AtakRelays', $get);
        // Changement de mode : la liaison est recalculée tout de suite.
        self::assertStringContainsString('uiNamespace setVariable ["COMSPEC_ATAK_LinkQ", []];', $get);
    }

    public function testDetectionRuleIsReadable(): void
    {
        $fn = self::read('functions/network/fn_linkMode.sqf');
        // 0 relais : satellite ; au moins un : relais ; plus aucun : satellite.
        $decide = self::caseBody($fn, 'decide');
        self::assertStringContainsString('["sat", "relay"] select (_n > 0)', $decide);

        // Relais comptés : ceux de la mission, vivants ou non, tous camps ; pas ce qu'un joueur déploie lui-même.
        $placed = self::caseBody($fn, 'placed');
        self::assertStringContainsString('forEach (missionNamespace getVariable ["COMSPEC_AtakRelays", []]);', $placed);
        self::assertStringContainsString('isNull _x', $placed);
        self::assertStringContainsString('if (alive _x && {!(_x getVariable ["COMSPEC_AtakRelay", false])}) then { continue };', $placed);
        self::assertStringContainsString('COMSPEC_AtakRelayKit', $placed);
        self::assertStringNotContainsString('COMSPEC_AtakRelaySide', $placed);
        self::assertStringNotContainsString('COMSPEC_AtakRelayOn', $placed);
        self::assertStringNotContainsString('damage', $placed);

        $tick = self::caseBody($fn, 'serverTick');
        self::assertStringContainsString('if (!isServer) exitWith {};', $tick);
        // Détruit : il compte toujours (même épave effacée). Supprimé intact ou par un Zeus : il ne compte plus.
        self::assertStringContainsString('_seen set [_id, [_x, !alive _x,', $tick);
        self::assertStringContainsString('if (!_dead || {_zeus}) then { _seen deleteAt _x; };', $tick);
        self::assertStringContainsString('if (alive _o) then { _seen deleteAt _x; } else { _seen set [_x, [_o, true, _zeus]]; };', $tick);
        self::assertStringContainsString('["decide", [count _seen]] call comspec_atak_native_fnc_linkMode', $tick);
        // Anti-rafale, puis publication à tous (JIP compris) seulement quand le mode change.
        self::assertStringContainsString('if (_want isEqualTo _pub) exitWith {', $tick);
        self::assertStringContainsString('if (diag_tickTime - _pt < 3) exitWith {};', $tick);
        self::assertStringContainsString('missionNamespace setVariable ["COMSPEC_ATAK_LinkModeAuto", _want, true];', $tick);
        self::assertSame(1, substr_count($fn, '["COMSPEC_ATAK_LinkModeAuto", _want, true]'));

        // Le serveur seul décide, toutes les 2 s ; un relais supprimé par un Zeus lui est signalé.
        $srv = self::read('XEH_postInitServer.sqf');
        self::assertStringContainsString('["serverInit"] call comspec_atak_native_fnc_linkMode;', $srv);
        self::assertStringContainsString('[{ ["serverTick"] call comspec_atak_native_fnc_linkMode; }, 2] call COMSPEC_ATAK_PfhSpread;', $srv);
        self::assertStringContainsString('"CuratorObjectDeleted"', self::caseBody($fn, 'curatorHook'));
        self::assertStringContainsString('["curatorHook"] call comspec_atak_native_fnc_linkMode;', self::read('XEH_postInitClient.sqf'));
    }

    public function testSettingIsFirstOfTheComspecList(): void
    {
        $pre = self::read('XEH_preInit.sqf');
        $at = strpos($pre, '["comspec_atak_native_link_mode", "LIST",');
        self::assertNotFalse($at);
        // Premier réglage déclaré du fichier, avant la langue : CBA garde l'ordre de déclaration, et les réglages sans
        // sous-catégorie passent au-dessus des sections « ATAK · … ».
        $firstCba = strpos($pre, 'call CBA_fnc_addSetting');
        $firstOwn = strpos($pre, 'call comspec_atak_native_fnc_addSetting');
        self::assertNotFalse($firstCba);
        self::assertNotFalse($firstOwn);
        $end = strpos($pre, 'call CBA_fnc_addSetting', (int) $at);
        self::assertSame($firstCba, $end);
        self::assertLessThan((int) $firstOwn, (int) $end);
        self::assertLessThan((int) strpos($pre, '["comspec_atak_native_language", "LIST",'), (int) $at);
        self::assertSame(1, substr_count($pre, '"comspec_atak_native_link_mode"'));

        $decl = substr($pre, (int) $at, (int) $end - (int) $at);
        self::assertStringContainsString('["Liaison du téléphone / Phone link", "', $decl);
        self::assertStringContainsString('["COMSPEC", ""]', $decl);
        // Trois valeurs, « auto » par défaut (index 0), réglage global imposé par le serveur (1).
        self::assertStringContainsString('[["auto", "sat", "relay"], ["Automatique (détection des relais) / Automatic (relay detection)", "Satellite : toujours relié / Satellite: always linked", "Relais obligatoires / Relays required"], 0], 1,', $decl);
        foreach (['Automatique : ', 'Satellite : ', 'Relais obligatoires : ', 'Automatic: ', 'Satellite: ', 'Relays required: '] as $t) {
            self::assertStringContainsString($t, $decl, $t);
        }
    }

    public function testEveryGuardGoesThroughTheSingleFunction(): void
    {
        $net = self::read('functions/network/fn_relayNet.sqf');
        $only = self::caseBody($net, 'only');
        self::assertStringContainsString('(["get"] call comspec_atak_native_fnc_linkMode) isEqualTo "relay"', $only);
        // Plus aucune décision parallèle dans « only » ni « required ».
        foreach (['relayOnly', 'COMSPEC_ATAK_RelayOnly', 'COMSPEC_LinkViaRelays', 'gateOn'] as $old) {
            self::assertStringNotContainsString($old, $only, $old);
        }
        $required = self::caseBody($net, 'required');
        self::assertStringContainsString('["only"] call comspec_atak_native_fnc_relayNet', $required);
        self::assertStringNotContainsString('COMSPEC_ATAK_RelaysRequired', $required);
        // Règle « pas de relais, pas de réseau MPU / radio » : sans objet en liaison satellite.
        $gate = self::caseBody($net, 'gateOn');
        self::assertStringContainsString('((["get"] call comspec_atak_native_fnc_linkMode) isEqualTo "relay")', $gate);
        self::assertStringContainsString('comspec_atak_native_relay_gate', $gate);

        // La liaison du téléphone ne lit plus les drapeaux elle-même.
        $lq = self::read('functions/core/fn_linkQuality.sqf');
        self::assertStringContainsString('private _only = ["only"] call comspec_atak_native_fnc_relayNet;', $lq);
        self::assertStringContainsString('private _req = ["required"] call comspec_atak_native_fnc_relayNet;', $lq);
        foreach (['COMSPEC_ATAK_RelaysRequired', 'COMSPEC_ATAK_RelayOnly', 'COMSPEC_LinkViaRelays', 'comspec_atak_native_relay_only', 'comspec_atak_native_relay_gate'] as $old) {
            self::assertStringNotContainsString($old, $lq, $old);
        }

        // Les drapeaux « réseau uniquement par les relais » ne sont lus que par fn_linkMode (et écrits par leurs menus).
        $mode = self::read('functions/network/fn_linkMode.sqf');
        self::assertStringContainsString('["relayOnly"] call comspec_atak_native_fnc_netCond', $mode);
        self::assertStringContainsString('COMSPEC_LinkViaRelays', $mode);
        self::assertStringNotContainsString('COMSPEC_LinkViaRelays', $net);

        // Les apps demandent toujours l'état du réseau aux fonctions de décision, jamais aux drapeaux.
        foreach (glob(self::MAIN . '/functions/*/*.sqf') ?: [] as $file) {
            $base = basename($file);
            if (in_array($base, ['fn_linkMode.sqf', 'fn_moduleRelay.sqf', 'fn_relayZeus.sqf', 'fn_netCond.sqf', 'fn_realismCatalog.sqf', 'fn_realismHelp.sqf'], true)) {
                continue;
            }
            $src = (string) file_get_contents($file);
            foreach (['COMSPEC_ATAK_RelaysRequired', 'COMSPEC_ATAK_RelayOnly', 'COMSPEC_LinkViaRelays', 'COMSPEC_ATAK_LinkModeAuto', '"comspec_atak_native_link_mode"'] as $flag) {
                self::assertStringNotContainsString($flag, $src, $base . ' : ' . $flag);
            }
        }
    }

    public function testSatelliteLinkNeverBlocksAndSaysTheRealReason(): void
    {
        $lq = self::read('functions/core/fn_linkQuality.sqf');
        // Liaison satellite sans relais : jamais coupée par le terrain, assez de débit pour une photo (pas de retenue
        // « liaison faible » : plus d'une barre et plus de 96 kbit/s).
        self::assertStringContainsString('private _sat = (["get"] call comspec_atak_native_fnc_linkMode) isEqualTo "sat";', $lq);
        self::assertStringContainsString('if (_sat && {!_mesh} && {_best isEqualTo []} && {!_cut}) then {', $lq);
        self::assertStringContainsString('private _floor = 0.1 * _jamM * (1 - _zj);', $lq);
        self::assertGreaterThan(250, 4000 * 0.1);
        // Barre d'état : SAT en liaison satellite, HORS RÉSEAU en liaison par relais sans relais reçu.
        self::assertStringContainsString('else { _netT = ["HORS RÉSEAU", "SAT"] select _sat; };', $lq);
        self::assertStringContainsString('["mode", ["relay", "sat"] select _sat]', $lq);
        // Liaison par relais : la vraie raison, plus le libellé générique.
        self::assertStringContainsString('_noRelay = ["reason"] call comspec_atak_native_fnc_linkMode;', $lq);
        self::assertStringNotContainsString('Aucun relais reçu (réseau exclusivement par relais)', $lq);

        $mode = self::read('functions/network/fn_linkMode.sqf');
        $reason = self::caseBody($mode, 'reason');
        foreach (['"Aucun relais à portée"', '"Relais hors service"', '"Relais détruit"'] as $t) {
            self::assertStringContainsString($t, $reason, $t);
        }
        // « why » : "" dès que le téléphone a du réseau ; jamais « hors réseau » comme raison.
        $why = self::caseBody($mode, 'why');
        self::assertStringContainsString('(_q getOrDefault ["bars", 0]) > 0}) exitWith { "" };', $why);
        self::assertStringNotContainsString('ors réseau', $why . $reason);

        $status = self::read('functions/ui/fn_statusUpdate.sqf');
        self::assertStringContainsString('if (_netT isEqualTo "SAT" && {_bars > 0}) then {', $status);
    }

    public function testPhotosAreSentOverSatelliteWithOneMessage(): void
    {
        $guards = [
            'functions/pages/fn_pagePhotos.sqf' => 'private _net = _netWhy isEqualTo "";',
            'functions/pages/fn_pageGallery.sqf' => 'private _net = _netWhy isEqualTo "";',
            'functions/pages/fn_photoMode.sqf' => 'private _noNet = _netWhy isNotEqualTo "";',
            'functions/pages/fn_photoCam.sqf' => 'private _noNet = _netWhy isNotEqualTo "";',
        ];
        foreach ($guards as $file => $guard) {
            $src = self::read($file);
            // La décision vient de la fonction unique ; plus de test direct des barres dans ces pages.
            self::assertStringContainsString('private _netWhy = ["why"] call comspec_atak_native_fnc_linkMode;', $src, $file);
            self::assertStringContainsString($guard, $src, $file);
            self::assertStringNotContainsString('comspec_atak_native_fnc_linkQuality', $src, $file);
            // Le message dit la vraie raison : plus de « hors réseau » ni de « pas de réseau » écrits en dur.
            foreach (['Hors réseau', 'HORS RÉSEAU', 'Pas de réseau'] as $old) {
                self::assertStringNotContainsString($old, $src, $file . ' : ' . $old);
            }
            // Aucun réglage ne retient les photos en liaison satellite.
            self::assertStringNotContainsString('"sat"', $src, $file);
        }

        // App Photo : une seule ligne d'état, quatre cas exclusifs dans le même switch.
        $photos = self::read('functions/pages/fn_pagePhotos.sqf');
        $at = strpos($photos, '// Ligne d\'état : où part la photo.');
        self::assertNotFalse($at);
        $line = substr($photos, (int) $at, (int) strpos($photos, '// Zones : viseur', (int) $at) - (int) $at);
        self::assertSame(1, substr_count($line, 'ctrlSetStructuredText'));
        self::assertSame(1, substr_count($line, 'switch (true) do {'));
        self::assertSame(1, substr_count($line, 'case (!_net):'));
        self::assertSame(1, substr_count($line, 'default {'));
        self::assertStringContainsString("%1 : la photo reste sur le téléphone (app Galerie pour l'envoyer)", $line);
        self::assertStringContainsString('(_netWhy) call comspec_atak_native_fnc_tr', $line);
        self::assertSame(1, substr_count($photos, 'la photo reste sur le téléphone'));

        // Les envois de la Galerie restent en file par fn_netSend (débit, file visible), jamais bloqués à part.
        $lib = self::read('functions/pages/fn_photoLibrary.sqf');
        self::assertSame(2, substr_count($lib, 'call comspec_atak_native_fnc_netSend;'));
        self::assertStringNotContainsString('linkMode', $lib);
    }

    public function testNoArmaScreenMessageAndBalancedDelimiters(): void
    {
        $own = self::read('functions/network/fn_linkMode.sqf');
        foreach (['systemChat', 'hint ', 'hintSilent', 'cutText', 'titleText', 'fnc_toast', 'fnc_notify'] as $bad) {
            self::assertStringNotContainsString($bad, $own, $bad);
        }
        foreach (self::TOUCHED as $rel) {
            // Déjà là avant ce sujet : le mode photo vide son propre calque à la sortie (texte vide, rien à l'écran).
            $src = str_replace('("COMSPEC_ATAK_Camera" call BIS_fnc_rscLayer) cutText ["", "PLAIN"];', '', self::read($rel));
            foreach (['systemChat', 'hintSilent', 'cutText', 'titleText'] as $bad) {
                self::assertSame(0, preg_match('/(?<![A-Za-z_])' . $bad . '\s*[\[("]/', self::stripCommentsAndStrings($src)), $rel . ' : ' . $bad);
            }
            $code = self::stripCommentsAndStrings($src);
            foreach ([['{', '}'], ['[', ']'], ['(', ')']] as [$open, $close]) {
                self::assertSame(substr_count($code, $open), substr_count($code, $close), $rel . ' : ' . $open . $close);
            }
        }
    }

    /** Code SQF sans commentaires ni contenu de chaînes (les guillemets doublés restent dans la chaîne). */
    private static function stripCommentsAndStrings(string $src): string
    {
        $out = '';
        $n = strlen($src);
        $i = 0;
        while ($i < $n) {
            $c = $src[$i];
            $two = substr($src, $i, 2);
            if ($two === '//') {
                $e = strpos($src, "\n", $i);
                $i = $e === false ? $n : $e;
                continue;
            }
            if ($two === '/*') {
                $e = strpos($src, '*/', $i + 2);
                $i = $e === false ? $n : $e + 2;
                continue;
            }
            if ($c === '"' || $c === "'") {
                $i++;
                while ($i < $n) {
                    if ($src[$i] === $c) {
                        if ($i + 1 < $n && $src[$i + 1] === $c) {
                            $i += 2;
                            continue;
                        }
                        break;
                    }
                    $i++;
                }
                $i++;
                $out .= '""';
                continue;
            }
            $out .= $c;
            $i++;
        }

        return $out;
    }
}
