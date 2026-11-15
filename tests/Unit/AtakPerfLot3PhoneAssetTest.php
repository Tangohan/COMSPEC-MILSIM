<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Optimisation des gels, lot 3 (téléphone : rangement, pages, traduction, drone ; bouton Synchroniser du Lobby) :
 * fige les garde-fous branchés dans le SQF.
 *  - retour de la miniature en deux images, écran en attente retiré s'il est rangé avant la seconde ;
 *  - dock : tuiles gardées tant que leur signature ne change pas, app ouverte recolorée sur place ;
 *  - mise en page seule : calques d'écran gardés quand la place de l'écran n'a pas changé ;
 *  - traduction : dictionnaire lu d'un bloc et préparé une fois par session, caches vidés par moitié, aucune
 *    recopie des listes de modèles ;
 *  - barre d'état : valeur brute comparée avant de composer et de traduire le texte ;
 *  - drone : un seul rendu de la page par image, télémétrie réécrite seulement si elle change ;
 *  - Lobby : requêtes du bouton Synchroniser par la relève de COMSPEC Link, avec repli et borne.
 * Lecture du SQF seulement (rien n'est mesurable sans le jeu) : les durées se lisent dans le RPT, lignes [PERF].
 */
final class AtakPerfLot3PhoneAssetTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';
    private const FN = self::MAIN . 'functions/';
    private const LOBBY = 'mod/COMSPEC_Lobby/Sources/addons/main/functions/fn_lobbyOpen.sqf';

    /** Fichiers modifiés par le lot. */
    private const TOUCHED = [
        self::FN . 'ui/fn_tr.sqf', self::FN . 'ui/fn_langApply.sqf', self::FN . 'ui/fn_dockRender.sqf', self::FN . 'ui/fn_layoutApply.sqf',
        self::FN . 'ui/fn_displayLoad.sqf', self::FN . 'ui/fn_displayUnload.sqf', self::FN . 'ui/fn_pageRender.sqf', self::FN . 'ui/fn_statusUpdate.sqf',
        self::FN . 'ui/fn_close.sqf', self::FN . 'ui/fn_open.sqf', self::FN . 'pages/fn_messagesAll.sqf', self::FN . 'pages/fn_linkAllyInit.sqf',
        self::FN . 'pages/fn_pageNotifs.sqf', self::FN . 'drone/fn_droneAction.sqf', self::LOBBY,
    ];

    private static function read(string $path): string
    {
        $full = dirname(__DIR__, 2) . '/' . $path;
        self::assertFileExists($full);

        return str_replace("\r\n", "\n", (string) file_get_contents($full));
    }

    /** Code sans commentaires ni contenu des chaînes. */
    private static function code(string $src): string
    {
        $src = (string) preg_replace('~/\*.*?\*/~s', '', $src);
        $src = (string) preg_replace('~"(?:[^"\n]|"")*"~', '""', $src);
        $src = (string) preg_replace("~'(?:[^'\n]|'')*'~", "''", $src);

        return (string) preg_replace('~//[^\n]*~', '', $src);
    }

    private static function between(string $src, string $from, string $to): string
    {
        $a = strpos($src, $from);
        self::assertNotFalse($a, $from . ' absent');
        $b = strpos($src, $to, $a + strlen($from));
        self::assertNotFalse($b, $to . ' absent');

        return substr($src, $a, $b - $a);
    }

    public function testTouchedFilesKeepBalancedDelimiters(): void
    {
        foreach (self::TOUCHED as $path) {
            $code = self::code(self::read($path));
            // fn_pageNotifs : code écrit dans des chaînes de plusieurs lignes (compile format), retirées en entier.
            if (str_ends_with($path, 'fn_pageNotifs.sqf')) {
                $noComment = (string) preg_replace('~^\s*//[^\n]*~m', '', (string) preg_replace('~/\*.*?\*/~s', '', self::read($path)));
                $code = (string) preg_replace('~//[^\n]*~', '', (string) preg_replace('~"(?:[^"]|"")*"~', '""', $noComment));
            }
            foreach ([['{', '}'], ['[', ']'], ['(', ')']] as [$open, $close]) {
                self::assertSame(substr_count($code, $open), substr_count($code, $close), "{$path} : « {$open} » et « {$close} » ne s'équilibrent pas");
            }
        }
    }

    public function testNothingIsShownOnScreen(): void
    {
        $banned = '/\b(hint|hintSilent|hintC|systemChat|titleText|cutText|BIS_fnc_showNotification|comspec_atak_native_fnc_toast)\b/';
        // Fichiers sans aucun message à l'écran : ils doivent le rester.
        foreach (['ui/fn_tr.sqf', 'ui/fn_langApply.sqf', 'ui/fn_dockRender.sqf', 'ui/fn_layoutApply.sqf', 'ui/fn_displayLoad.sqf', 'ui/fn_displayUnload.sqf',
            'ui/fn_pageRender.sqf', 'pages/fn_messagesAll.sqf', 'pages/fn_linkAllyInit.sqf'] as $file) {
            self::assertDoesNotMatchRegularExpression($banned, self::code(self::read(self::FN . $file)), "{$file} : message à l'écran");
        }
        self::assertDoesNotMatchRegularExpression('/\b(hint|hintSilent|hintC|systemChat|titleText|cutText|BIS_fnc_showNotification)\b/', self::code(self::read(self::LOBBY)));
        // fn_close : cutText ne sert qu'à retirer la couche du téléphone porté (texte vide), jamais à écrire à l'écran.
        $close = self::read(self::FN . 'ui/fn_close.sqf');
        self::assertSame(substr_count($close, 'cutText'), substr_count($close, '("COMSPEC_ATAK_Hud" call BIS_fnc_rscLayer) cutText ["", "PLAIN"];'));
        // Blocs ajoutés dans les fichiers qui notifient déjà pour d'autres raisons : rien à l'écran dedans.
        $drone = self::read(self::FN . 'drone/fn_droneAction.sqf');
        foreach ([
            self::between($drone, 'private _render = {', 'private _send = {'),
            self::between($drone, 'private _osdT0 = diag_tickTime;', '["drone : OSD", _osdT0]'),
            self::between($drone, 'private _menus = (_s getOrDefault ["droneTab", "PILOT"])', 'call _render; };'),
        ] as $block) {
            self::assertDoesNotMatchRegularExpression($banned, self::code($block));
        }
        $status = self::read(self::FN . 'ui/fn_statusUpdate.sqf');
        self::assertDoesNotMatchRegularExpression($banned, self::code(self::between($status, 'private _putLazy = {', 'private _airplane')));
    }

    public function testStowedPhoneComesBackOverTwoFrames(): void
    {
        $unload = self::read(self::FN . 'ui/fn_displayUnload.sqf');
        $back = self::between($unload, 'private _po = diag_tickTime;', '}] call comspec_atak_native_fnc_nextFrame;');
        self::assertMatchesRegularExpression(
            '/setVariable \["COMSPEC_ATAK_HudStaged", true\];\s*\[false\] call comspec_atak_native_fnc_open;\s*uiNamespace setVariable \["COMSPEC_ATAK_HudStaged", false\];/',
            $back
        );
        self::assertStringContainsString('["téléphone : retour de la miniature", _po] call comspec_atak_native_fnc_perfAcc;', $back);

        $load = self::read(self::FN . 'ui/fn_displayLoad.sqf');
        $stage = self::between($load, 'if (!_interactive && {!_texture} && {uiNamespace getVariable ["COMSPEC_ATAK_HudStaged", false]}) exitWith {', 'private _loadT0 = diag_tickTime;');
        // Première image : l'écran n'est pas encore « le téléphone », ses cartes sont masquées, la suite est déposée.
        self::assertStringContainsString('uiNamespace setVariable ["COMSPEC_ATAK_HudPending", _display];', $stage);
        self::assertStringContainsString('{ (_display displayCtrl _x) ctrlShow false; } forEach [88530, 88532, 88533, 88534];', $stage);
        self::assertStringNotContainsString('"COMSPEC_ATAK_Display"', $stage);
        self::assertStringContainsString('call comspec_atak_native_fnc_nextFrame;', $stage);
        // Seconde image : seulement si l'écran en attente est toujours celui-là, puis chargement complet habituel.
        self::assertStringContainsString('if (isNull _display || {(uiNamespace getVariable ["COMSPEC_ATAK_HudPending", displayNull]) isNotEqualTo _display}) exitWith {};', $stage);
        self::assertStringContainsString('[_display, false] call comspec_atak_native_fnc_displayLoad;', $stage);
        self::assertStringContainsString('["téléphone : retour de la miniature (mise en page et page)", _pl] call comspec_atak_native_fnc_perfAcc;', $stage);
        // Le chargement habituel vient après la mise en attente et ne se fait qu'une fois.
        self::assertGreaterThan(strpos($load, 'COMSPEC_ATAK_HudPending", _display]'), strpos($load, 'uiNamespace setVariable ["COMSPEC_ATAK_Display", _display];'));
        self::assertSame(1, substr_count($load, '[_page] call comspec_atak_native_fnc_navigate;'));

        // Rangé ou rouvert avant la seconde image : l'écran en attente est retiré (fn_open passe toujours par fn_close).
        $close = self::read(self::FN . 'ui/fn_close.sqf');
        $pending = self::between($close, 'private _pend = uiNamespace getVariable ["COMSPEC_ATAK_HudPending", displayNull];', 'if (isNull _d) exitWith { true };');
        self::assertStringContainsString('if (isNull _d && {!isNull _pend}) exitWith {', $pending);
        self::assertStringContainsString('uiNamespace setVariable ["COMSPEC_ATAK_HudPending", displayNull];', $pending);
        self::assertStringContainsString('[] call comspec_atak_native_fnc_close;', self::read(self::FN . 'ui/fn_open.sqf'));
    }

    public function testDockTilesAreKeptUntilTheirSignatureChanges(): void
    {
        $dock = self::read(self::FN . 'ui/fn_dockRender.sqf');
        $code = self::code($dock);
        // Une seule suppression des tuiles, dans l'aide commune ; plus de suppression d'office en tête de fonction.
        self::assertSame(1, substr_count($code, 'ctrlDelete'));
        self::assertStringContainsString('ctrlDelete', self::code(self::between($dock, 'private _dockClear = {', '};')));
        // Signature : taille, apps, pastilles, thème, style d'icône, police, langue.
        $sig = self::between($dock, 'private _sig = str [', '];');
        foreach (['_gRect select 2', '_gRect select 3', '_l get "fontSmall"', '_x get "icon"', '_x get "name"', '_badges', '_th get "name"', '_th get "accent"',
            'comspec_atak_native_fnc_iconTint', '"COMSPEC_ATAK_FontSet"', '"COMSPEC_ATAK_Lang"'] as $part) {
            self::assertStringContainsString($part, $sig, "signature du dock sans {$part}");
        }
        // La place du groupe ne fait pas partie de la signature (glisser du téléphone).
        self::assertStringNotContainsString('_gRect select 0', $sig);
        // Chemin gardé : sortie avant toute création, fond de l'app ouverte recoloré sur place.
        $keep = self::between($dock, 'if ((uiNamespace getVariable ["COMSPEC_ATAK_DockSig", ""]) isEqualTo _sig', 'call _dockClear;');
        self::assertStringContainsString('exitWith {', $keep);
        self::assertStringContainsString('isNull _x || {(ctrlParentControlsGroup _x) isNotEqualTo _grp}', $keep);
        self::assertStringContainsString('_tileBg ctrlSetBackgroundColor ([_th get "chrome", _activeBg] select (_tilePage isEqualTo _active));', $keep);
        self::assertStringNotContainsString('fnc_tileCreate', $keep);
        self::assertStringContainsString('["dock (gardé)", _dockT0] call comspec_atak_native_fnc_perfAcc;', $keep);
        // Rendu complet : signature et tuiles retenues pour le passage suivant.
        self::assertLessThan(strpos($dock, 'fnc_tileCreate;'), strpos($dock, 'call _dockClear;' . "\n" . '_tiles = [];'));
        self::assertStringContainsString('uiNamespace setVariable ["COMSPEC_ATAK_DockSig", _sig];', $dock);
        self::assertStringContainsString('_tiles pushBack [_app get "page", _tile select 0];', $dock);
        self::assertStringContainsString('["dock (dessin)", _dockT0] call comspec_atak_native_fnc_perfAcc;', $dock);
        // Verrouillage, téléphone d'un autre, carte en mode PETIT : tuiles retirées et signature oubliée.
        self::assertSame(3, substr_count($dock, 'exitWith { _grp ctrlShow false; call _dockClear; true };'));
        self::assertStringContainsString('uiNamespace setVariable ["COMSPEC_ATAK_DockSig", ""];', self::between($dock, 'private _dockClear = {', '};'));
    }

    public function testLayoutOnlyPassKeepsScreenOverlaysWhenNothingMoved(): void
    {
        $lay = self::read(self::FN . 'ui/fn_layoutApply.sqf');
        $block = self::between($lay, 'if (_overlays && {!_dragging}) then {', '// Contrôles de l\'app posés hors du groupe de contenu (boussole, bandeau de position, recherche');
        $sig = self::between($block, 'private _laySig = str [', '];');
        foreach (['"device"', '"body"', '"dockRect"', '"font"', '"landscape"', '"model"', '"mode"', '_th get "name"', '_ovStamp'] as $part) {
            self::assertStringContainsString($part, $sig, "signature de mise en page sans {$part}");
        }
        // Gardés seulement si : même signature, calques vivants sur cet écran, aucun contrôle posé depuis.
        $keepIf = self::between($block, 'private _layKeep =', 'if (_layKeep) then {');
        self::assertStringContainsString('(uiNamespace getVariable ["COMSPEC_ATAK_LaySig", ""]) isEqualTo _laySig', $keepIf);
        self::assertStringContainsString('(ctrlParent _dev0) isEqualTo _d', $keepIf);
        self::assertStringContainsString('(_x getVariable ["cs_ov", -1]) isNotEqualTo _ovStamp', $keepIf);
        $kept = self::between($block, 'if (_layKeep) then {', '} else {');
        self::assertStringNotContainsString('ctrlDelete', $kept);
        self::assertStringContainsString('[] call comspec_atak_native_fnc_deviceOverlay;', $kept);
        // Recréation : tampon et signature posés pour que le rendu de page qui suit ne les recrée pas une seconde fois.
        $redo = substr($block, (int) strpos($block, '} else {'));
        self::assertStringContainsString('ctrlDelete', $redo);
        self::assertStringContainsString('_x setVariable ["cs_ov", _ovStamp];', $redo);
        self::assertStringContainsString('uiNamespace setVariable ["COMSPEC_ATAK_OvSig", str [_l get "device", _l get "landscape", _l get "model", _l get "mode", _l get "texture"]];', $redo);
        self::assertStringContainsString('uiNamespace setVariable ["COMSPEC_ATAK_LaySig", _laySig];', $redo);
        // Même formule de signature que fn_pageRender, sinon il recréerait les calques à tort.
        self::assertStringContainsString('str [_ovL get "device", _ovL get "landscape", _ovL get "model", _ovL get "mode", _ovL get "texture"]', self::read(self::FN . 'ui/fn_pageRender.sqf'));
        self::assertStringContainsString('["téléphone : disposition", _layT0] call comspec_atak_native_fnc_perfAcc;', $lay);
    }

    public function testDictionaryIsParsedInOneBlockAndPreparedOncePerSession(): void
    {
        $apply = self::read(self::FN . 'ui/fn_langApply.sqf');
        // Table de hachage construite une fois par session de jeu (uiNamespace), reprise sans relecture à chaque mission.
        self::assertStringContainsString('uiNamespace setVariable ["COMSPEC_ATAK_I18nUi_" + _lang, [_exact, _tpls]];', $apply);
        self::assertStringContainsString('if (_loaded isNotEqualTo _lang && {!isNil {uiNamespace getVariable ("COMSPEC_ATAK_I18nUi_" + _lang)}}) then {', $apply);
        self::assertSame(1, substr_count($apply, 'loadFile'));
        self::assertLessThan(strpos($apply, 'loadFile'), strpos($apply, 'if (_loaded isNotEqualTo _lang) then {'));
        // Lecture d'un bloc, gardée seulement si le compte de paires y est ; sinon lecture ligne à ligne, comme avant.
        $parse = self::between($apply, 'private _parseLang = {', 'private _all = parseSimpleArray _raw;');
        self::assertStringContainsString('private _bulk = parseSimpleArray _raw;', $parse);
        self::assertStringContainsString('{(count _bulk) >= ((count _lines) - 2)}) exitWith { _bulk };', $parse);
        self::assertStringContainsString('private _row = parseSimpleArray _l;', $parse);
        self::assertLessThan(strpos($parse, 'private _row = parseSimpleArray _l;'), strpos($parse, 'exitWith { _bulk };'));
        // Modèles découpés d'un « % » au suivant, plus caractère par caractère ; tri sans appel de fonction par clé.
        $code = self::code($apply);
        self::assertStringNotContainsString('_buf = _buf + _c;', $code);
        self::assertStringContainsString('_k = _rest find "%";', $apply);
        self::assertStringNotContainsString('BIS_fnc_sortBy', $code);
        self::assertStringContainsString('[-(count ((_x select 0) joinString "")), _rank, _x]', $apply);
        // Files d'arrivée des caches remises à zéro avec eux.
        self::assertStringContainsString('missionNamespace setVariable ["COMSPEC_ATAK_I18nCacheQ", []];', $apply);
        self::assertStringContainsString('missionNamespace setVariable ["COMSPEC_ATAK_I18nCacheNumQ", []];', $apply);
        // Préparé au lancement du jeu, pas à la première page.
        self::assertStringContainsString('[] call comspec_atak_native_fnc_langApply;', self::read(self::MAIN . 'XEH_preStart.sqf'));
    }

    public function testDictionaryFilesAllowTheOneBlockRead(): void
    {
        // La lecture d'un bloc n'est gardée que si toutes les lignes, sauf « [ » et « ] », sont des paires.
        foreach (['en.txt', 'fr.txt', 'zh_cn.txt'] as $file) {
            $lines = array_values(array_filter(array_map('trim', explode("\n", self::read(self::MAIN . 'i18n/' . $file))), static fn (string $l): bool => $l !== ''));
            self::assertGreaterThan(100, count($lines), $file);
            self::assertSame('[', $lines[0], $file);
            self::assertSame(']', $lines[count($lines) - 1], $file);
            $pairs = array_slice($lines, 1, -1);
            $bad = array_filter($pairs, static fn (string $l): bool => !str_starts_with($l, '["') || !(str_ends_with($l, '"],') || str_ends_with($l, '"]')));
            self::assertSame([], array_values($bad), "{$file} : ligne qui n'est pas une paire");
            self::assertStringEndsWith('"]', $pairs[count($pairs) - 1], "{$file} : virgule après la dernière paire");
        }
    }

    public function testTranslationLookupNeitherCopiesListsNorFlushesWholeCaches(): void
    {
        $tr = self::read(self::FN . 'ui/fn_tr.sqf');
        $code = self::code($tr);
        // Recherche par table de hachage ; les deux listes de modèles sont parcourues sans être recopiées.
        self::assertStringContainsString('private _hit = _cache getOrDefault [_v, nil];', $tr);
        self::assertStringContainsString('private _r = _exact getOrDefault [_core, ""];', $tr);
        self::assertStringNotContainsString('getOrDefault [_core select [0, 3], []]) +', $tr);
        self::assertStringContainsString('} forEach [_tpls getOrDefault [_core select [0, 3], []], _tpls getOrDefault [_gen + (_core select [(_n - 3) max 0]), _tpls getOrDefault [_gen, []]]];', $tr);
        // Aucune lecture ni découpe de fichier ici.
        self::assertStringNotContainsString('loadFile', $code);
        self::assertStringNotContainsString('parseSimpleArray', $code);
        // Texte sans lettre : rendu tel quel, avant le chronomètre des textes nouveaux et sans entrée de cache.
        $early = 'if ((toLower _v) isEqualTo (toUpper _v) && {!((trim _v) in _exact)}) exitWith { _v };';
        self::assertStringContainsString($early, $tr);
        self::assertLessThan(strpos($tr, 'private _trT0 = diag_tickTime;'), strpos($tr, $early));
        // Chiffres cherchés hors balises ; cache plein : la moitié la plus ancienne sort.
        self::assertStringContainsString('if (((_plain splitString "0123456789") joinString "") isNotEqualTo _plain) exitWith {', $tr);
        self::assertStringNotContainsString('((_v splitString "0123456789") joinString "")', $tr);
        $keep = self::between($tr, 'private _keep = {', '// Texte avec chiffres hors balises');
        self::assertStringContainsString('{ _map deleteAt _x; } forEach (_q select [0, _half]);', $keep);
        self::assertStringContainsString('_q deleteRange [0, _half];', $keep);
        self::assertStringContainsString('[_cacheNum, "COMSPEC_ATAK_I18nCacheNum", "COMSPEC_ATAK_I18nCacheNumQ", 1500] call _keep;', $tr);
        self::assertStringContainsString('[_cache, "COMSPEC_ATAK_I18nCache", "COMSPEC_ATAK_I18nCacheQ", 4000] call _keep;', $tr);
        // Textes nouveaux comptés pour la mesure par rendu (fn_pageRender), au RPT.
        self::assertStringContainsString('"COMSPEC_ATAK_TrMiss"', $tr);
        $render = self::read(self::FN . 'ui/fn_pageRender.sqf');
        self::assertStringContainsString('["traduction : textes nouveaux (rendu)"', $render);
        self::assertStringContainsString('[format ["page %1 (contenu)", _page], _perfC0] call comspec_atak_native_fnc_perfAcc;', $render);
        self::assertStringContainsString('[format ["page %1 (dessin)", _page], _perfT] call comspec_atak_native_fnc_perfAcc;', $render);
    }

    public function testStatusBarComparesRawValuesBeforeBuildingTexts(): void
    {
        $status = self::read(self::FN . 'ui/fn_statusUpdate.sqf');
        $lazy = self::between($status, 'private _putLazy = {', '// Mode avion');
        self::assertStringContainsString('if (_k isEqualTo "cs_tt" && {!_tips}) exitWith {};', $lazy);
        self::assertStringContainsString('if ((_c getVariable [_k + "_r", 0]) isEqualTo _raw) exitWith {};', $lazy);
        self::assertStringContainsString('[_c, _k, call _make] call _put;', $lazy);
        self::assertStringContainsString('private _tips = _state getOrDefault ["interactive", false];', $status);
        // Textes composés (format) : tous derrière la comparaison de la valeur brute, langue comprise.
        self::assertGreaterThanOrEqual(7, substr_count($status, '}] call _putLazy;'));
        foreach (['kbit/s', 'Mémoire vive : %1 / %2 Mo', '%1°C   %2 %3', 'Vent du %1, %2 m/s', '"HH:MM"] call BIS_fnc_timeToString', 'Centre de notifications : %1 non lue(s)'] as $text) {
            $at = strpos($status, $text);
            self::assertNotFalse($at, $text);
            $lineStart = (int) strrpos(substr($status, 0, $at), "\n");
            $line = substr($status, $lineStart, strpos($status, "\n", $at) - $lineStart);
            self::assertStringNotContainsString('] call _put;', $line, "« {$text} » encore composé à chaque passage");
        }
        self::assertStringContainsString('[_net, _lq get "label", _lq get "kbps", _lq get "latency", _lq get "loss", _airplane, [0, _held] select _heldOn, _lang]', $status);
        // Fil des messages : relecture complète chronométrée.
        self::assertStringContainsString('["messages (relecture)", _msgT0] call comspec_atak_native_fnc_perfAcc;', self::read(self::FN . 'pages/fn_messagesAll.sqf'));
    }

    public function testDronePageRendersOncePerFrameAndRewritesTelemetryOnlyOnChange(): void
    {
        $drone = self::read(self::FN . 'drone/fn_droneAction.sqf');
        $render = self::between($drone, 'private _render = {', 'private _send = {');
        self::assertStringContainsString('if ((missionNamespace getVariable ["COMSPEC_ATAK_DroneRenderFrame", -1]) isEqualTo diag_frameNo) exitWith {};', $render);
        self::assertStringContainsString('missionNamespace setVariable ["COMSPEC_ATAK_DroneRenderFrame", diag_frameNo];', $render);
        self::assertSame(1, substr_count($render, '["DRONE"] call comspec_atak_native_fnc_pageRender;'));
        // Onglets CIBLES et JOURNAL : au plus un rendu complet toutes les 2 s, signature retenue une fois redessiné.
        $tabs = self::between($drone, 'private _menus = (_s getOrDefault ["droneTab", "PILOT"])', 'call _render; };');
        self::assertStringContainsString('if (_menus && {diag_tickTime - (missionNamespace getVariable ["COMSPEC_ATAK_DroneSigAt", -10]) < 2}) exitWith {};', $tabs);
        self::assertLessThan(strpos($tabs, 'missionNamespace setVariable ["COMSPEC_ATAK_DroneSig", _sig];'), strpos($tabs, ') < 2}) exitWith {};'));
        // Télémétrie et incrustations : texte brut gardé sur le contrôle, réécrit seulement s'il change.
        $osd = self::between($drone, 'private _osdT0 = diag_tickTime;', '["drone : OSD", _osdT0]');
        self::assertStringContainsString('if (isNull _c || {(_c getVariable ["cs_osd", 0]) isEqualTo _raw}) exitWith {};', $osd);
        self::assertSame(1, substr_count($osd, 'ctrlSetStructuredText'));
        self::assertStringContainsString('{ [_x, _t select _forEachIndex] call _osdPut; } forEach _feed;', $osd);
        self::assertStringContainsString('["drone : repérage", _scanT0] call comspec_atak_native_fnc_perfAcc;', $drone);
        // Boucles de la liaison d'un allié : départ décalé et sonde permanente du lot 1.
        $ally = self::read(self::FN . 'pages/fn_linkAllyInit.sqf');
        self::assertSame(2, substr_count($ally, '] call COMSPEC_ATAK_PfhSpread;'));
        self::assertStringNotContainsString('CBA_fnc_addPerFrameHandler', self::code($ally));
    }

    public function testNotificationRowsCompileTheirCodeOnce(): void
    {
        $notifs = self::read(self::FN . 'pages/fn_pageNotifs.sqf');
        $memo = self::between($notifs, 'private _codeOf = {', "
};");
        self::assertStringContainsString('private _c = _codeMemo getOrDefault [_src, 0];', $memo);
        self::assertStringContainsString('if (_c isEqualType 0) then { _c = compile _src; _codeMemo set [_src, _c]; };', $memo);
        // Mémoire bornée, gardée d'un rendu à l'autre.
        self::assertStringContainsString('if (isNil "_codeMemo" || {(count _codeMemo) > 400}) then {', $notifs);
        // Les deux codes de chaque ligne (ouvrir, fermer) passent par elle : plus de compilation par ligne et par rendu.
        $rows = self::between($notifs, 'private _open = if (_page isEqualTo "")', '_rows pushBack ["notif"');
        self::assertSame(2, substr_count($rows, ') call _codeOf'));
        self::assertStringNotContainsString('compile format', $rows);
    }

    public function testShellAndWallpaperTexturesStayLoadedOnceSeen(): void
    {
        $lay = self::read(self::FN . 'ui/fn_layoutApply.sqf');
        $keep = self::between($lay, 'private _d46 = findDisplay 46;', '_wc ctrlShow (');
        // Fichiers des PBO seulement, une fois chacun, nombre borné ; jamais d'image visible (hors écran, taille infime),
        // et sur l'écran de mission : aucun contrôle de plus sur le téléphone.
        self::assertStringContainsString('{!(_x in _kept)} && {(count _kept) < 40}', $keep);
        self::assertStringContainsString('private _kc = _d46 ctrlCreate ["RscPicture", -1];', $keep);
        self::assertStringContainsString('_kc ctrlSetPosition [safeZoneX - 1, safeZoneY - 1, 0.001, 0.001];', $keep);
        self::assertStringContainsString('_d46 setVariable ["COMSPEC_ATAK_TexKeepMore", _kept];', $keep);
        self::assertStringContainsString('forEach [_l get "phoneTexture", _wallTex, ', $keep);
        self::assertStringNotContainsString('_d ctrlCreate', $keep);
        foreach (['ui_icon_squircle', 'ui_hand', 'ui_pin'] as $icon) {
            self::assertStringContainsString('\\data\\' . $icon . '.paa"', $keep);
            self::assertFileExists(dirname(__DIR__, 2) . '/' . self::MAIN . 'data/' . $icon . '.paa');
        }
        // Le lot ne touche pas au dossier de la carte (lot 2).
        self::assertStringNotContainsString('TexKeepMore', self::read(self::FN . 'map/fn_textureKeep.sqf'));
    }

    public function testMeasuresGoToTheRptUnderNamedKeys(): void
    {
        $keys = [
            'ui/fn_open.sqf' => ['"téléphone : création de l\'écran (en main, scripts compris)"', '"téléphone : création de l\'écran (porté, scripts compris)"'],
            'ui/fn_displayLoad.sqf' => ['"téléphone : verrou et barre d\'état (ouverture)"', '"téléphone : ouverture (scripts de l\'écran)"'],
            'ui/fn_layoutApply.sqf' => ['"téléphone : disposition"', '"téléphone : calques de mise en page gardés"'],
            'ui/fn_dockRender.sqf' => ['"dock (dessin)"', '"dock (gardé)"'],
            'drone/fn_droneAction.sqf' => ['"drone : repérage"', '"drone : OSD"', '"drone suivi"'],
        ];
        foreach ($keys as $file => $list) {
            $src = self::read(self::FN . $file);
            foreach ($list as $key) {
                self::assertMatchesRegularExpression('/\[' . preg_quote($key, '/') . ', _\w+\] call comspec_atak_native_fnc_perfAcc/', $src, "{$file} : clé {$key}");
            }
        }
        // L'instrumentation du lot 1 est toujours là (rien n'est défait).
        self::assertFileExists(dirname(__DIR__, 2) . '/' . self::FN . 'core/fn_perfAcc.sqf');
        self::assertStringContainsString('COMSPEC_ATAK_PfhSpread = {', self::read(self::MAIN . 'XEH_preInit.sqf'));
    }

    public function testLobbySyncUsesTheBackgroundRelayWithFallbackAndBound(): void
    {
        $lobby = self::read(self::LOBBY);
        $bg = self::between($lobby, 'private _extBg = {', 'private _raw = ');
        // Dépôt puis relève, attente hors du fil du jeu, bornée à 20 s.
        self::assertStringContainsString('"COMSPECExtension" callExtension ["AsyncKeep", [_ticket, _cmd] + _args]', $bg);
        self::assertStringContainsString('"COMSPECExtension" callExtension ["AsyncPoll", [_ticket]]', $bg);
        self::assertStringContainsString('uiSleep 0.1;', $bg);
        self::assertStringContainsString('while { _wait && {diag_tickTime - _t < 20} } do {', $bg);
        self::assertStringContainsString('if ((_r select [0, 8]) isNotEqualTo "PENDING|") then { _out = _r; _wait = false; };', $bg);
        // COMSPEC Link plus ancien ou relève refusée (pas de « QUEUED| ») : appel direct, comme avant.
        self::assertStringContainsString('if ((_r select [0, 7]) isNotEqualTo "QUEUED|") exitWith { [_cmd, _args] call _ext };', $bg);
        self::assertLessThan(strpos($bg, 'uiSleep 0.1;'), strpos($bg, 'exitWith { [_cmd, _args] call _ext };'));
        // Sans réponse dans la borne : une erreur, jamais une réponse vide prise pour « COMSPEC Link absent ».
        self::assertStringContainsString('private _out = "ERR|async_timeout";', $bg);
        // Les deux appels qui attendent le site passent par la relève ; l'état gardé en mémoire reste direct.
        self::assertStringContainsString('private _raw = ["GetPlayerAvatarInfo", [_uid, _tenant]] call _extBg;', $lobby);
        self::assertStringContainsString('(["DownloadBriefingSlideImage", [_u, format ["avatar_%1_%2", _uid, hashValue _u]]] call _extBg) splitString "|";', $lobby);
        self::assertStringContainsString('private _auth = (["GetAuthState", []] call _ext) splitString _tab;', $lobby);
        self::assertSame(2, substr_count($lobby, '] call _extBg'));
        // Un seul Synchroniser à la fois, dans du code ordonnancé (uiSleep permis), durées au RPT.
        self::assertStringContainsString('if (uiNamespace getVariable ["COMSPEC_LOBBY_SyncBusy", false]) exitWith {};', $lobby);
        $sync = self::between($lobby, 'uiNamespace setVariable ["COMSPEC_LOBBY_sync", {', 'uiNamespace setVariable ["COMSPEC_LOBBY_syncRun", {');
        self::assertStringContainsString('[] spawn {', $sync);
        self::assertStringContainsString('uiNamespace setVariable ["COMSPEC_LOBBY_SyncBusy", false];', $sync);
        self::assertGreaterThanOrEqual(4, substr_count($lobby, '[COMSPEC LOBBY][PERF]'));
        self::assertStringContainsString('[COMSPEC LOBBY][PERF] Synchroniser : %1 ms', $lobby);
        self::assertStringContainsString('[COMSPEC LOBBY][PERF] DLL %1 : %2 ms (direct)', $lobby);
        // La DLL répond bien à ces deux commandes (relève fusionnée avec le lot 6).
        $dll = self::read('mod/UptoDate/COMSPECExtension/Extension.cs');
        self::assertStringContainsString('if (function == "AsyncPoll")', $dll);
        self::assertStringContainsString('function is "Async" or "AsyncKeep"', $dll);
    }
}
