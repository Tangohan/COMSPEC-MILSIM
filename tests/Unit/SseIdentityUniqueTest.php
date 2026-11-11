<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Identité unique d'une IA (mod SSE) : le nom donné par le Zeus / Eden / l'atelier IA / un profil est celui de ses
 * papiers, de ses bulles, de SEEK II et des fichiers. Une seule fonction le décide (identity/fn_idIdentityOf.sqf) ;
 * plus de second tirage de nom pour les papiers. Règle « Inconnu » commune à l'en-tête et aux bulles ; portrait vérifié
 * avant d'être montré (sinon silhouette), au lieu d'une photo bleue unie.
 */
final class SseIdentityUniqueTest extends TestCase
{
    private const A = 'mod/@COMSPEC_SSE/addons/';

    private static function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path));
    }

    /** @return array<string, string> chemin relatif => contenu, tous les .sqf des addons SSE */
    private static function allSqf(): array
    {
        $root = dirname(__DIR__, 2) . '/' . self::A;
        $out = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile() && str_ends_with($f->getFilename(), '.sqf')) {
                $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($root)));
                $out[$rel] = str_replace("\r\n", "\n", (string) file_get_contents($f->getPathname()));
            }
        }
        return $out;
    }

    public function testResolverIsDeclaredAndItsPrecedenceTableIsTheOneImplemented(): void
    {
        self::assertStringContainsString("class idGenerate {};\n            class idIdentityOf {};", self::read(self::A . 'identity/config.cpp'));
        $r = self::read(self::A . 'identity/functions/fn_idIdentityOf.sqf');
        // Tableau documenté : explicite, puis nom Arma choisi, puis tirage gardé.
        $p1 = strpos($r, '1. explicit');
        $p2 = strpos($r, '2. arma');
        $p3 = strpos($r, '3. generated');
        self::assertNotFalse($p1);
        self::assertTrue($p1 < $p2 && $p2 < $p3, 'ordre de priorité documenté');
        // Code : mêmes étapes, même ordre.
        $get = substr($r, (int) strpos($r, 'case "get": {'));
        $c1 = strpos($get, 'private _ex = [_u] call _explicitOf;');
        $c2 = strpos($get, '!([name _u] call _isGeneric)');
        $c3 = strpos($get, '_src = "generated";');
        self::assertTrue($c1 !== false && $c2 !== false && $c3 !== false && $c1 < $c2 && $c2 < $c3, 'explicite > nom Arma choisi > tirage');
        // Nom Arma tiré par le jeu : listes GenericNames lues dans la config.
        self::assertStringContainsString('configFile >> "CfgWorlds" >> "GenericNames"', $r);
        self::assertStringContainsString('"FirstNames"', $r);
        self::assertStringContainsString('"LastNames"', $r);
        // Sources explicites : variables du profil d'identité SSE (un seul jeu pour SEEK, dossier, papiers).
        foreach (['COMSPEC_SSE_FirstName', 'COMSPEC_SSE_LastName', 'COMSPEC_SSE_Sex', 'COMSPEC_SSE_BirthDate', 'COMSPEC_SSE_BirthPlace', 'COMSPEC_ID_Country'] as $v) {
            self::assertStringContainsString('"' . $v . '"', $r, $v);
        }
        // Sexe d'après le prénom quand il n'est pas donné.
        self::assertStringContainsString('_id set ["sex", _ex getOrDefault ["sex", [_ex get "first", _u] call _sexOf]];', $r);
    }

    public function testGeneratedIdentityIsDrawnOnceStoredAndBroadcast(): void
    {
        $r = self::read(self::A . 'identity/functions/fn_idIdentityOf.sqf');
        // Gardée sur l'unité, diffusée (paires, jamais une HashMap diffusée).
        self::assertStringContainsString('_u setVariable ["comspec_sse_identity", (keys _id) apply { [_x, _id get _x] }, true];', $r);
        // Une identité déjà tirée reste la même (même si le pays de la mission change).
        self::assertStringContainsString('private _base = [_draw, _cur] select ((_cur getOrDefault ["src", ""]) isEqualTo "generated");', $r);
        // Le tirage brut ne passe jamais par le résolveur (pas de boucle) ; un appel « son identité » y passe.
        $g = self::read(self::A . 'identity/functions/fn_idGenerate.sqf');
        self::assertStringContainsString('["_raw", false, [false]]', $g);
        self::assertStringContainsString('["get", [_unit, _key, _country]] call comspec_sse_fnc_idIdentityOf', $g);
        self::assertSame(2, substr_count($r, '"", _country, true] call comspec_sse_fnc_idGenerate') + substr_count($r, '"", _id getOrDefault ["country", ""], true] call comspec_sse_fnc_idGenerate'));
    }

    public function testRenameRegeneratesPapersUnlessAlreadyHandedToAPlayer(): void
    {
        $r = self::read(self::A . 'identity/functions/fn_idIdentityOf.sqf');
        self::assertStringContainsString('["sync", [_u]] call comspec_sse_fnc_idAiConfig;', $r);
        $sync = self::read(self::A . 'identity/functions/fn_idAiConfig.sqf');
        $case = substr($sync, (int) strpos($sync, 'case "sync": {'), 4000);
        self::assertStringContainsString('["get", [_u, _key]] call comspec_sse_fnc_idIdentityOf', $case);
        self::assertStringContainsString('comspec_sse_id_papersFollowName', $case);
        self::assertStringContainsString('["heldByPlayer", [_d]] call comspec_sse_fnc_idIdentityOf', $case);
        self::assertStringContainsString('_d set ["trueLast", _own get "last"];', $case);
        self::assertStringNotContainsString('comspec_sse_fnc_idGenerate', $case);
        // Réglage déclaré, coché par défaut, FR + EN.
        $settings = self::read(self::A . 'identity/functions/fn_idInitSettings.sqf');
        self::assertMatchesRegularExpression('/\["comspec_sse_id_papersFollowName", "CHECKBOX", \["Mettre à jour ses papiers quand son identité change", "[^"]+"\], true\] call _add;/u', $settings);
        $table = self::read(self::A . 'main/stringtable.xml');
        $key = 'STR_COMSPEC_SSE_CFG_' . strtoupper(substr(sha1('Mettre à jour ses papiers quand son identité change'), 0, 10));
        self::assertSame(1, preg_match('#<Key ID="' . $key . '">(.*?)</Key>#s', $table, $m));
        self::assertStringContainsString('<English>Update its papers when its identity changes</English>', $m[1]);
        // Fenêtre du Zeus : papiers déjà remis signalés (ligne d'état de l'atelier IA, jamais une alerte Arma).
        $server = self::read(self::A . 'ia_rp/functions/fn_iarpServer.sqf');
        self::assertStringContainsString('"STR_COMSPEC_IARP_W_IdHeld"', $server);
        self::assertStringContainsString('["wsNote", [_notes]] call _reply;', $server);
        self::assertStringContainsString('case "note":', self::read(self::A . 'ia_rp/functions/fn_iarpWorkshop.sqf'));
    }

    public function testForgedPapersPrintAnotherNameButKeepTheResolverIdentityAsTruth(): void
    {
        $issue = self::read(self::A . 'identity/functions/fn_idIssue.sqf');
        self::assertStringContainsString('private _ident = if (isNull _u) then { createHashMap } else { ["get", [_u, _key, ', $issue);
        self::assertStringContainsString('private _printed = +_own;', $issue);
        self::assertStringContainsString('private _trueLast = _own get "last";', $issue);
        self::assertStringContainsString('_printed = [_u, _key, "alt", _country] call comspec_sse_fnc_idGenerate;', $issue);
        // Pays de l'identité par défaut (numéro et MRZ de son pays).
        self::assertStringContainsString('_country = _ident getOrDefault ["country", ""];', $issue);
        // Un faux tiré par les règles automatiques ne remplace pas un nom donné.
        $reg = self::read(self::A . 'identity/functions/fn_idRegistry.sqf');
        self::assertStringContainsString('{_u getVariable ["COMSPEC_SSE_NameAuthored", false]}', $reg);
        self::assertStringContainsString('["autoKind", _autoKind]', $reg);
        // Auto-test en jeu : les quatre lignes du tableau.
        $r = self::read(self::A . 'identity/functions/fn_idIdentityOf.sqf');
        foreach (['nom Arma tiré ignoré -> identité générée', 'identité générée gardée (stable)', 'nom donné -> identité explicite', 'renommée -> papiers refaits', 'faux -> imprimé ≠ vrai, vrai = identité'] as $case) {
            self::assertStringContainsString($case, $r);
        }
    }

    public function testNoSecondNameGeneratorFeedsPapersOrChat(): void
    {
        foreach (self::allSqf() as $path => $code) {
            if (in_array($path, ['identity/functions/fn_idIdentityOf.sqf', 'identity/functions/fn_idGenerate.sqf'], true)) {
                continue;
            }
            preg_match_all('/\[([^\[\]]*)\]\s*call comspec_sse_fnc_idGenerate/', $code, $m);
            foreach ($m[1] as $args) {
                $parts = array_map('trim', explode(',', $args));
                // Seul un tirage d'une AUTRE identité (sel « alt », « orig », « blank »…) est permis hors du résolveur.
                self::assertTrue(isset($parts[2]) && preg_match('/^"[a-z]+"$/', $parts[2]) === 1, $path . ' : [' . $args . '] call fn_idGenerate (son identité : fn_idIdentityOf)');
            }
        }
        $profile = self::read(self::A . 'ia_rp/functions/fn_iarpProfile.sqf');
        self::assertStringNotContainsString('selectRandom _firsts', $profile);
        self::assertStringNotContainsString('selectRandom _lasts', $profile);
        self::assertStringContainsString('["set", [_u, _in, "workshop"]] call comspec_sse_fnc_idIdentityOf;', $profile);
        foreach (['identity/functions/fn_idIssue.sqf', 'identity/functions/fn_idRegistry.sqf', 'identity/functions/fn_idHvt.sqf', 'identity/functions/fn_idLegend.sqf',
            'identity/functions/fn_idAiConfig.sqf', 'identity/functions/fn_idServer.sqf', 'editor/functions/fn_aiWho.sqf', 'editor/functions/fn_editorAiServer.sqf',
            'ia_rp/functions/fn_iarpName.sqf', 'ia_rp/functions/fn_iarpServer.sqf'] as $consumer) {
            self::assertStringContainsString('comspec_sse_fnc_idIdentityOf', self::read(self::A . $consumer), $consumer);
        }
        // Analyse du Zeus : plus le nom d'unité Arma.
        // Fusion 4.4.0 : la liste en texte « Vrais papiers de … » est remplacée par la fenêtre Papiers de l'IA du Zeus
        // (fix/sse-zeus-papiers) ; son en-tête prend le nom donné de fn_aiWho (identité unique), l'unité Arma en second.
        $server = self::read(self::A . 'identity/functions/fn_idServer.sqf');
        self::assertStringContainsString('["zeusPapers", [[_u, _pub] call comspec_sse_fnc_idZeusPapersData]] call _reply;', $server);
        self::assertStringContainsString('_out set ["given", _w getOrDefault ["given", ""]];', self::read(self::A . 'identity/functions/fn_idZeusPapersData.sqf'));
        self::assertStringContainsString('format ["UnitAs" call _L, _given, _un]', self::read(self::A . 'identity/functions/fn_idZeusPapers.sqf'));
        // Dossier SSE généré après coup : il reprend l'identité unique.
        self::assertStringContainsString('_entity getVariable ["comspec_sse_identity", []]', self::read(self::A . 'generator/functions/fn_applyAuthoredIdentity.sqf'));
    }

    public function testHeaderAndBubblesShowTheNameOnlyOnceLearned(): void
    {
        $name = self::read(self::A . 'ia_rp/functions/fn_iarpName.sqf');
        foreach (['case "known":', 'case "learn":', 'comspec_sse_nameKnownBy', 'getAssignedCuratorLogic _v'] as $needle) {
            self::assertStringContainsString($needle, $name);
        }
        self::assertStringContainsString('if (hasInterface && {!isNull player} && {_n isNotEqualTo ""} && {!([_a, player] call _knows)}) then { _n = ""; };', $name);
        self::assertStringContainsString('if (!([_u, _to] call _knows)) exitWith { "" };', $name);
        $ui = self::read(self::A . 'ia_rp/functions/fn_iarpUi.sqf');
        self::assertStringContainsString('["known", [_t, player]] call comspec_sse_fnc_iarpName', $ui);
        self::assertStringNotContainsString('_name = _who select 0;', $ui);
        self::assertStringContainsString('if (_aiNow isNotEqualTo "") then { _who = _aiNow; };', $ui);
        // Appris : elle l'a dit, ou il l'a lu sur ses papiers.
        self::assertStringContainsString('if (_intent isEqualTo "name") then { ["learn", [_t, [_me] + ([_t] call _hearers)]] call comspec_sse_fnc_iarpName; };', self::read(self::A . 'ia_rp/functions/fn_iarpServer.sqf'));
        self::assertStringContainsString('["learn", [_bearer, [_me]]] call comspec_sse_fnc_iarpName;', self::read(self::A . 'identity/functions/fn_idServer.sqf'));
        $st = self::read(self::A . 'ia_rp/stringtable.xml');
        self::assertSame(1, preg_match('#<Key ID="STR_COMSPEC_IARP_Anonymous">(.*?)</Key>#s', $st, $m));
        self::assertStringContainsString('<French>Inconnu</French>', $m[1]);
        self::assertStringContainsString('<English>Unknown</English>', $m[1]);
    }

    public function testPortraitIsShownOnlyOnceVerifiedOtherwiseTheNeutralSilhouette(): void
    {
        $face = self::read(self::A . 'identity/functions/fn_idFace.sqf');
        self::assertStringContainsString('if (_op isEqualTo "ready") exitWith { _state param [2, false] };', $face);
        self::assertStringContainsString('"Land_Cargo20_grey_F"', $face);
        self::assertStringContainsString('(boundingBoxReal _box) params ["_b1", "_b2"];', $face);
        self::assertStringContainsString('_box setPosWorld', $face);
        self::assertStringContainsString('if (_try >= 4) exitWith {', $face);
        self::assertStringContainsString('[_camPos] call _inside', $face);
        $viewer = self::read(self::A . 'identity/functions/fn_idViewerOnLoad.sqf');
        $sil = strpos($viewer, '_dir + "silhouette_co.paa", _angle] call _pic;');
        $live = strpos($viewer, '_fc ctrlShow (["ready"] call comspec_sse_fnc_idFace);');
        self::assertTrue($sil !== false && $live !== false && $sil < $live, 'silhouette dessous, portrait caché tant qu\'il n\'est pas vérifié');
        self::assertStringContainsString('uiNamespace setVariable ["comspec_sse_idFaceCtrl", _fc];', $viewer);
        self::assertFileExists(dirname(__DIR__, 2) . '/' . self::A . 'identity/data/silhouette_co.paa');
    }

    public function testOldRecordsAndMissionsStillLoad(): void
    {
        $cfg = self::read(self::A . 'identity/functions/fn_idAiConfig.sqf');
        self::assertStringContainsString('private _keptId = _spec getOrDefault ["identity", ""];', $cfg);
        self::assertStringContainsString('if (_v isEqualType [] && {(count _v) >= 2}) then {', $cfg);
        $r = self::read(self::A . 'identity/functions/fn_idIdentityOf.sqf');
        self::assertStringContainsString('if (_v isEqualType [] && {_v isNotEqualTo []}) then { createHashMapFromArray (_v select { _x isEqualType [] && {(count _x) isEqualTo 2} }) } else { createHashMap }', $r);
        // Champ « identity » ajouté à l'élément gardé seulement pour une IA nommée.
        self::assertStringContainsString('_cfg pushBack ["identity", str (', self::read(self::A . 'editor/functions/fn_editorAiServer.sqf'));
    }

    public function testGuideStringsAndNoArmaAlertPopups(): void
    {
        self::assertStringContainsString('description = "$STR_COMSPEC_SSE_GUIDE_IDNAME_DESC";', self::read(self::A . 'guide/config.cpp'));
        $table = self::read(self::A . 'main/stringtable.xml');
        self::assertSame(1, preg_match('#<Key ID="STR_COMSPEC_SSE_GUIDE_IDNAME_DESC">(.*?)</Key>#s', $table, $m));
        self::assertStringContainsString("Le nom que vous donnez à l'IA est celui de ses papiers", $m[1]);
        self::assertStringContainsString('The name you give the AI is the name on its papers', $m[1]);
        self::assertStringContainsString('Faux papiers', $m[1]);
        self::assertStringContainsString('Forged papers', $m[1]);
        $ia = self::read(self::A . 'ia_rp/stringtable.xml');
        foreach (['W_IdDone', 'W_IdHeld', 'W_IdStale', 'W_IdSuggest'] as $k) {
            self::assertSame(1, preg_match('#<Key ID="STR_COMSPEC_IARP_' . $k . '">(.*?)</Key>#s', $ia, $mm), $k);
            self::assertMatchesRegularExpression('#<English>[^<]+</English>#', $mm[1], $k);
            self::assertMatchesRegularExpression('#<French>[^<]+</French>#', $mm[1], $k);
        }
        foreach (['main/stringtable.xml', 'ia_rp/stringtable.xml'] as $xml) {
            $doc = new \DOMDocument();
            self::assertTrue(@$doc->loadXML(self::read(self::A . $xml)), $xml . ' : XML valide');
        }
        foreach (['identity/functions/fn_idIdentityOf.sqf', 'ia_rp/functions/fn_iarpName.sqf', 'identity/functions/fn_idFace.sqf'] as $f) {
            self::assertDoesNotMatchRegularExpression('/\b(hint|hintSilent|systemChat|titleText|cutText|BIS_fnc_showNotification)\b/', self::read(self::A . $f), $f);
        }
    }
}
