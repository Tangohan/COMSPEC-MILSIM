<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * ATAK natif, fenêtre « Gérer les écrans » du TOC (fn_tocManagerUi, conception docs/audit/2026-11-gestionnaire-ecrans-ui.md).
 * Lecture du SQF et de la config, rien n'est lancé :
 *  - identifiants 8700 à 8799 : ceux de la conception existent, uniques, créés par la fenêtre, classes déclarées ;
 *  - chaque onglet, chaque champ, chaque bouton a son gestionnaire et son aide avec un exemple ;
 *  - aucun message hors de la fenêtre, aucune texture transparente procédurale, jamais le nom du modèle en titre ;
 *  - libellés traduits à la main (i18n/en.txt, modèle xx.txt, stringtable pour les choix exclusifs).
 */
final class AtakTocManagerUiAssetTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';
    private const TOC = self::MAIN . 'functions/toc/';
    /** Identifiants écrits dans le tableau « Contrôles par zone » de la conception. */
    private const SPEC_IDS = [8700, 8701, 8710, 8711, 8712, 8713, 8714, 8715, 8716, 8717, 8718, 8720, 8721, 8722, 8723, 8724, 8725, 8730, 8731, 8732, 8733, 8734,
        8740, 8741, 8742, 8750, 8751, 8752, 8753, 8754, 8755, 8756, 8760, 8761, 8762, 8763, 8764, 8765, 8770, 8771, 8772, 8773, 8780, 8790, 8791, 8792, 8793, 8794, 8795, 8796, 8798];

    private static function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path));
    }

    /** Table JSON entre « private _nom = [ » et « // ---- FIN ». */
    private static function table(string $src, string $name): array
    {
        self::assertSame(1, preg_match('/private\s+' . preg_quote($name, '/') . '\s*=\s*(\[.*?\]);\s*\n\s*\/\/ ---- FIN/s', $src, $m), 'table ' . $name . ' absente');
        $data = json_decode($m[1], true);
        self::assertIsArray($data, 'table ' . $name . ' : JSON invalide');

        return $data;
    }

    /** Code sans commentaires. */
    private static function code(string $src): string
    {
        return (string) preg_replace('~/\*.*?\*/|(?<=\s)//[^\n]*~s', '', $src);
    }

    /** Clés françaises d'un dictionnaire ["fr", …]. */
    private static function dict(string $path): array
    {
        preg_match_all('/^\["((?:[^"]|"")*)", "/m', self::read($path), $m);

        return array_flip(array_map(static fn (string $s): string => str_replace('""', '"', $s), $m[1]));
    }

    public function testIdentifiantsDeLaConceptionUniquesEtCrees(): void
    {
        $data = self::read(self::TOC . 'fn_tocManagerUiData.sqf');
        $ui = self::read(self::TOC . 'fn_tocManagerUi.sqf');
        $hpp = self::read(self::MAIN . 'ui/toc_manager.hpp');
        $ids = self::table($data, '_ids');
        $idcs = array_column($ids, 1);
        self::assertSame(count($idcs), count(array_unique($idcs)), 'identifiants en double');
        self::assertSame(count($ids), count(array_unique(array_column($ids, 0))), 'clés en double');
        foreach ($ids as [$key, $idc, $class, $zone]) {
            self::assertGreaterThanOrEqual(8700, $idc, $key);
            self::assertLessThanOrEqual(8799, $idc, $key);
            self::assertMatchesRegularExpression('/class ' . preg_quote($class, '/') . '\s*:/', $hpp, $key . ' : classe ' . $class . ' absente de toc_manager.hpp');
            self::assertContains($zone, ['fond', 'haut', 'gauche', 'centre', 'droite', 'bas', 'confirmation'], $key);
            // Créé par la fenêtre : par sa clé, ou par « tab%1 » / « page%1 » pour les six onglets.
            $made = str_contains($ui, '["' . $key . '", ') || str_contains($ui, '"' . $key . '", "')
                || (preg_match('/^(tab|page)[0-5]$/', $key) === 1 && str_contains($ui, 'format ["' . substr($key, 0, -1) . '%1", _forEachIndex]'))
                || str_contains($ui, 'ctrlCreate ["' . $class . '", ' . $idc . ']');
            self::assertTrue($made, $key . ' : jamais créé par fn_tocManagerUi');
        }
        foreach (self::SPEC_IDS as $idc) {
            self::assertContains($idc, $idcs, 'identifiant de la conception absent : ' . $idc);
        }
        // La plage reste libre ailleurs dans le mod : aucun autre fichier de config ne pose un idc 87xx.
        $root = dirname(__DIR__, 2) . '/mod/COMSPEC_ATAK_Native/Sources/addons';
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (!in_array($file->getExtension(), ['hpp', 'cpp'], true)) {
                continue;
            }
            self::assertSame(0, preg_match('/\bidc\s*=\s*87\d\d\b/', (string) file_get_contents($file->getPathname())), $file->getFilename() . ' : idc dans la plage 8700-8799');
        }
        // Le display et son numéro, hors de la plage des contrôles.
        self::assertStringContainsString('#define COMSPEC_ATAK_IDD_TOCMANAGER 88570', $hpp);
        self::assertSame(1, substr_count(self::read(self::MAIN . 'ui/ui_ids.hpp') . $hpp, '88570'));
    }

    public function testClassesDeclareesTaillesEnPixelsDeConception(): void
    {
        $ui = self::read(self::TOC . 'fn_tocManagerUi.sqf');
        $hpp = self::read(self::MAIN . 'ui/toc_manager.hpp');
        $data = self::read(self::TOC . 'fn_tocManagerUiData.sqf');
        preg_match_all('/"(COMSPEC_RscTocMgr\w+)"/', $ui . $data, $m);
        $used = array_unique($m[1]);
        self::assertGreaterThan(15, count($used));
        foreach ($used as $class) {
            self::assertMatchesRegularExpression('/class ' . $class . '\s*:/', $hpp, $class);
        }
        // Contrôles standard du jeu : arbre, tableau, choix exclusif, liste bornée, groupe défilant, curseur, case.
        foreach (['COMSPEC_RscTocMgrTree: RscTree', 'COMSPEC_RscTocMgrTable: COMSPEC_RscListNBox', 'COMSPEC_RscTocMgrTbx: RscToolbox', 'COMSPEC_RscTocMgrGroup: RscControlsGroupNoHScrollbars',
            'COMSPEC_RscTocMgrSlider: RscXSliderH', 'COMSPEC_RscTocMgrCheck: RscCheckBox', 'COMSPEC_RscTocMgrCombo: COMSPEC_RscTocCombo', 'COMSPEC_RscTocMgrPic: RscPicture'] as $decl) {
            self::assertStringContainsString('class ' . $decl, $hpp);
        }
        self::assertStringContainsString('wholeHeight="8 * 28 * safezoneH / 1080"', $hpp, 'liste déroulante bornée à 8 lignes');
        // Tailles : n * safezoneH / 1080, jamais une part de la taille d'interface.
        preg_match_all('/(?:sizeEx|size|rowHeight|wholeHeight)\s*=\s*"([^"]+)"/', $hpp, $sizes);
        self::assertGreaterThan(10, count($sizes[1]));
        foreach ($sizes[1] as $expr) {
            self::assertMatchesRegularExpression('#^(\d+ \* )+safezoneH / 1080$#', $expr);
        }
        self::assertStringContainsString('_uh = safeZoneH / 1080;', $ui);
        self::assertStringContainsString('_uw = _uh * 0.75;', $ui);
        foreach (['(_availW * 0.958) min 2300', '(_availW * 0.96) min 1192', 'is3DEN || {_availW < 1500}', '[1024, 780] select _compact'] as $needle) {
            self::assertStringContainsString($needle, $ui);
        }
        // Texte : jamais sous 11 px de conception dans les boutons ajustés.
        self::assertStringContainsString('{_f > 11 * _uh}', $ui);
        // Display vide, jeu non mis en pause, fermeture signalée ; classes incluses par displays.hpp ; fonctions déclarées.
        self::assertMatchesRegularExpression('/class COMSPEC_RscTocManager \{\s*idd=COMSPEC_ATAK_IDD_TOCMANAGER; movingEnable=0; enableSimulation=1;/', $hpp);
        self::assertStringContainsString("onUnload=\"['unload', _this select 0] call comspec_atak_native_fnc_tocManagerUi\";", $hpp);
        self::assertStringContainsString('#include "toc_manager.hpp"', self::read(self::MAIN . 'ui/displays.hpp'));
        $cfg = self::read(self::MAIN . 'config.cpp');
        foreach (['class tocManagerUi {};', 'class tocManagerUiData {};', 'class tocManagerUiApply {};', 'class tocManagerUiPower { postInit = 1; };'] as $fn) {
            self::assertStringContainsString($fn, $cfg);
        }
        // Choix exclusifs : libellés dans la stringtable, en français et en anglais.
        $st = self::read(self::MAIN . 'stringtable.xml');
        preg_match_all('/\$(STR_COMSPEC_ATAK_CFG_TOCMGR_\w+)/', $hpp, $keys);
        self::assertGreaterThanOrEqual(31, count(array_unique($keys[1])));
        foreach (array_unique($keys[1]) as $key) {
            self::assertSame(1, preg_match('/<Key ID="' . $key . '">\s*<Original>[^<]+<\/Original>\s*<English>[^<]+<\/English>\s*<French>[^<]+<\/French>/', $st), $key);
        }
        // Colonnes d'un choix = nombre de libellés.
        preg_match_all('/class COMSPEC_RscTocMgrTbx\w+: COMSPEC_RscTocMgrTbx \{ columns=(\d); strings\[\]=\{([^}]*)\}/', $hpp, $tb, PREG_SET_ORDER);
        self::assertCount(10, $tb);
        foreach ($tb as [, $cols, $strings]) {
            self::assertSame((int) $cols, count(explode(',', $strings)));
            self::assertLessThanOrEqual(5, (int) $cols, 'au-delà de 5 choix : une liste');
        }
    }

    public function testOngletsChampsEtBoutonsOntLeurGestionnaireEtLeurAide(): void
    {
        $data = self::read(self::TOC . 'fn_tocManagerUiData.sqf');
        $ui = self::read(self::TOC . 'fn_tocManagerUi.sqf');
        $run = self::read(self::TOC . 'fn_tocManagerUiApply.sqf');
        $hpp = self::read(self::MAIN . 'ui/toc_manager.hpp');
        $tabs = self::table($data, '_tabs');
        self::assertSame(['SOURCE', 'VUE', 'DISPOSITION', 'TEXTE', 'ALIMENTATION', 'AVANCÉ'], array_column($tabs, 1));
        self::assertSame([8750, 8751, 8752, 8753, 8754, 8755], array_column($tabs, 2));
        self::assertSame([8760, 8761, 8762, 8763, 8764, 8765], array_column($tabs, 3));
        foreach ($tabs as $tab) {
            self::assertStringContainsString('Exemple :', $tab[4], $tab[1]);
        }
        // Chaque onglet : bouton, page, gestionnaire, raccourcis.
        foreach (['["tab", (_this select 0) getVariable ["COMSPEC_ATAK_Tab", 0]] call comspec_atak_native_fnc_tocManagerUi', 'case "tab": {', 'case (_ctrl && {_dik >= 2} && {_dik <= 7}): { ["tab", _dik - 2] call _fn; true };',
            'case (_ctrl && {_dik isEqualTo 15}):', '(_c get format ["page%1", _i]) ctrlShow'] as $needle) {
            self::assertStringContainsString($needle, $ui);
        }
        $tabKeys = array_column($tabs, 0);
        $acts = self::table($data, '_acts');
        $actKeys = array_column($acts, 0);
        self::assertSame(count($actKeys), count(array_unique($actKeys)));
        $fields = self::table($data, '_fields');
        $perTab = array_fill_keys($tabKeys, 0);
        foreach ($fields as [$key, $tab, $genre, $label, $help]) {
            self::assertContains($tab, $tabKeys, $key);
            ++$perTab[$tab];
            self::assertStringContainsString('Exemple :', $help, $key . ' : aide sans exemple');
            $g = explode(':', $genre);
            self::assertContains($g[0], ['cmb', 'sld', 'tbx', 'chk', 'edt', 'mle', 'txt', 'btn'], $key);
            if ($g[0] === 'tbx') {
                self::assertMatchesRegularExpression('/class ' . $g[1] . ': COMSPEC_RscTocMgrTbx/', $hpp, $key);
            }
            if ($g[0] === 'sld') {
                self::assertCount(4, $g, $key . ' : curseur min, max, pas');
                self::assertLessThan((float) $g[2], (float) $g[1]);
            }
            if ($g[0] === 'btn') {
                foreach (explode(',', $g[1]) as $act) {
                    self::assertContains($act, $actKeys, $key . ' : bouton ' . $act . ' sans ligne dans _acts');
                }
            }
            // Un champ à valeur est lu par « chg » (changement en attente) ou sert d'information.
            if (in_array($g[0], ['cmb', 'sld', 'tbx', 'chk', 'edt'], true) && !in_array($key, ['lName'], true)) {
                self::assertMatchesRegularExpression('/case "' . $key . '"[;:]/', $ui, $key . ' : champ sans gestionnaire dans « chg »');
            }
        }
        foreach (['VIEW', 'LAY', 'TXT', 'PWR', 'ADV'] as $tab) {
            self::assertGreaterThan(0, $perTab[$tab], $tab . ' : onglet sans champ');
        }
        // L'onglet SOURCE est construit à la main : familles, sources, filtre, source actuelle.
        foreach (['["fams", ', '["srcs", ', '["srcFind", ', '["camFilter", ', '["srcNow", ', 'case "famPick": {', 'case "srcPick": {', 'case "srcDbl": {', 'case "srcFill": {'] as $needle) {
            self::assertStringContainsString($needle, $ui);
        }
        // Chaque bouton : aide avec un exemple, et un gestionnaire (dans la fenêtre ou dans l'exécution).
        $local = ['close', 'rooms', 'log', 'apply', 'cancel', 'copy', 'classic'];
        foreach ($acts as [$key, $label, $help, $need]) {
            self::assertNotSame('', $label, $key);
            self::assertStringContainsString('Exemple :', $help, $key);
            self::assertIsBool($need);
            if (in_array($key, $local, true)) {
                self::assertStringContainsString('case (_a isEqualTo "' . $key . '"):', $ui, $key);
            } elseif (str_starts_with($key, 'msg')) {
                self::assertStringContainsString('case "' . $key . '":', $ui, $key);
            } else {
                self::assertMatchesRegularExpression('/case "' . $key . '"[;:]/', $run, $key . ' : bouton sans gestionnaire');
            }
        }
        // Tout mode appelé existe : fenêtre, exécution, données.
        $cases = static function (string $src): array {
            preg_match_all('/^\s{4}case "(\w+)"[;:]/m', $src, $m);

            return $m[1];
        };
        $uiCases = $cases($ui);
        preg_match_all('/\["(\w+)"(?:, (?:[^\[\]{};\n]|\[(?:[^\[\]{};\n]|\[[^\[\]{};\n]*\])*\])*)?\] call (?:comspec_atak_native_fnc_tocManagerUi\b(?!Data|Apply|Power)|_fn\b)/', self::code($ui), $calls);
        self::assertGreaterThan(60, count($calls[1]));
        foreach (array_unique($calls[1]) as $mode) {
            self::assertContains($mode, $uiCases, 'fn_tocManagerUi : mode « ' . $mode . ' » appelé mais absent');
        }
        $runCases = $cases($run);
        preg_match_all('/\["(\w+)"(?:, (?:[^\[\]{};\n]|\[(?:[^\[\]{};\n]|\[[^\[\]{};\n]*\])*\])*)?\] call _run\b/', self::code($ui), $calls);
        foreach (array_unique($calls[1]) as $mode) {
            self::assertContains($mode, $runCases, 'fn_tocManagerUiApply : mode « ' . $mode . ' » appelé mais absent');
        }
        $datCases = $cases($data);
        preg_match_all('/\["(\w+)"(?:, (?:[^\[\]{};\n]|\[(?:[^\[\]{};\n]|\[[^\[\]{};\n]*\])*\])*)?\] call _dat\b/', self::code($ui . $run), $calls);
        foreach (array_unique($calls[1]) as $mode) {
            self::assertContains($mode, $datCases, 'fn_tocManagerUiData : mode « ' . $mode . ' » appelé mais absent');
        }
        // Familles : les neuf de la conception, dans l'ordre, ROUTAGE remis à plus tard et dit en clair.
        self::assertSame(['MAP', 'CAM', 'SAT', 'DATA', 'TIME', 'PLAN', 'BC', 'TEXT', 'MISC'], array_column(self::table($data, '_families'), 1));
        self::assertStringContainsString('Routage : bientôt', $ui);
        self::assertStringContainsString('<French>ROUTAGE · BIENTÔT</French>', self::read(self::MAIN . 'stringtable.xml'));
    }

    public function testRienHorsDeLaFenetreNiPopupNiTextureTransparente(): void
    {
        foreach (['fn_tocManagerUi.sqf', 'fn_tocManagerUiApply.sqf', 'fn_tocManagerUiData.sqf', 'fn_tocManagerUiPower.sqf'] as $file) {
            $code = self::code(self::read(self::TOC . $file));
            foreach (['hint ', 'hintSilent', 'systemChat', 'titleText', 'cutText', 'BIS_fnc_showNotification', 'zen_common_fnc_showMessage', 'fnc_notify', 'fnc_toast', 'BIS_fnc_guiMessage'] as $bad) {
                self::assertStringNotContainsString($bad, $code, $file . ' : ' . $bad);
            }
            self::assertStringNotContainsString('color(0,0,0,0)', $code, $file);
            // Jamais le nom anglais du modèle dans un titre ; aucun rendu ajouté pour les vignettes.
            self::assertStringNotContainsString('"displayName"', $code, $file);
            foreach (['camCreate', 'cameraEffect', 'r2t(', 'onEachFrame', '"EachFrame"', '"Draw3D"'] as $bad) {
                self::assertStringNotContainsString($bad, $code, $file . ' : ' . $bad);
            }
        }
        $ui = self::read(self::TOC . 'fn_tocManagerUi.sqf');
        $run = self::read(self::TOC . 'fn_tocManagerUiApply.sqf');
        // Vignette : la texture déjà posée sur la dalle par fn_tocScreen, relue 2 fois par seconde au plus, fenêtre ouverte.
        self::assertStringContainsString('format ["%1ui(""COMSPEC_RscTocScreenTexture"",""%2"")", _spec, _tex]', $run);
        self::assertStringContainsString('uiSleep 0.5;', $ui);
        self::assertStringContainsString('while { !isNull _d } do {', $ui);
        self::assertStringContainsString('profileNamespace getVariable ["COMSPEC_ATAK_TocMgrThumbs", true]) && {!is3DEN} && {!_compact} && {(count _sib) <= 12}', $ui);
        // Signature une fois par seconde : la fenêtre n'est refaite que si un écran a changé.
        self::assertStringContainsString('if (_sig isNotEqualTo (_st getOrDefault ["sig", ""])) then { ["refresh", "soft"] call _fn; };', $ui);
        // Résultats sur la ligne d'état, questions dans la boîte ; Échap annule avant de fermer ; Entrée applique.
        foreach (['case "say": {', 'case "status": {', 'case "ask": {', 'case "confirm": {', 'case "esc": {', 'case (_dik isEqualTo 1): { ["esc"] call _fn; true };', 'ctrlSetFocus ([_c get "cfNo", _c get "cfEdit"] select (_kind isEqualTo "scene"));',
            '_x ctrlEnable false; _held pushBack _x;', '_d displayAddEventHandler ["KeyDown", { ["key", _this] call comspec_atak_native_fnc_tocManagerUi }];'] as $needle) {
            self::assertStringContainsString($needle, $ui);
        }
        // Mêmes parents que le tableau, plus le PC du poste (fenêtre ouverte par-dessus).
        foreach (['findDisplay 313', 'findDisplay 88560', 'findDisplay 312', 'findDisplay 46'] as $parent) {
            self::assertStringContainsString($parent, $ui);
        }
        self::assertSame(1, substr_count($ui, 'createDisplay "COMSPEC_RscTocManager"'));
        // Le tableau d'avant reste joignable, et « open » mène à la nouvelle fenêtre partout où il s'ouvrait.
        $mgr = self::read(self::TOC . 'fn_tocManager.sqf');
        self::assertStringContainsString('exitWith { ["open", _a] call comspec_atak_native_fnc_tocManagerUi };', $mgr);
        self::assertStringContainsString('case "openTable": {', $mgr);
        self::assertStringContainsString('["openTable"] call comspec_atak_native_fnc_tocManager', $ui);
        // Fonctions des autres lots : jamais appelées sans isNil.
        foreach (['comspec_atak_native_fnc_camList', 'comspec_atak_native_fnc_tocAccess', 'comspec_atak_native_fnc_tocJournal'] as $opt) {
            self::assertStringContainsString('isNil "' . $opt . '"', $run, $opt);
            self::assertStringContainsString('missionNamespace getVariable ["' . $opt . '", {}]', $run, $opt);
        }
        // Dans Eden : attributs seulement, et le résultat le dit.
        self::assertStringContainsString('Enregistré dans la mission pour %1 écran', $run);
    }

    public function testLibellesTraduitsALaMain(): void
    {
        $data = self::read(self::TOC . 'fn_tocManagerUiData.sqf');
        $en = self::dict(self::MAIN . 'i18n/en.txt');
        $xx = self::dict('mod/COMSPEC_ATAK_Native/localisation/template/xx.txt');
        $texts = [];
        foreach (self::table($data, '_tabs') as $r) {
            array_push($texts, $r[1], $r[4]);
        }
        foreach (self::table($data, '_families') as $r) {
            array_push($texts, $r[0], $r[2]);
        }
        foreach (self::table($data, '_fields') as $r) {
            array_push($texts, $r[3], $r[4]);
        }
        foreach (self::table($data, '_acts') as $r) {
            array_push($texts, $r[1], $r[2]);
        }
        // Textes passés à la traduction dans la fenêtre et l'exécution : ("…") call _tr.
        foreach (['fn_tocManagerUi.sqf', 'fn_tocManagerUiApply.sqf'] as $file) {
            preg_match_all('/\("((?:[^"]|"")+)"\) call _tr/', self::code(self::read(self::TOC . $file)), $m);
            self::assertGreaterThan(40, count($m[1]), $file);
            foreach ($m[1] as $t) {
                $texts[] = str_replace('""', '"', $t);
            }
            // Aide écrite en ligne : ["help", ["text", "…"]].
            preg_match_all('/\["help", \["text", "((?:[^"]|"")+)"\]\]/', self::read(self::TOC . $file), $m);
            foreach ($m[1] as $t) {
                $texts[] = str_replace('""', '"', $t);
                self::assertStringContainsString('Exemple :', $t);
            }
        }
        $texts = array_values(array_unique(array_filter($texts, static fn (string $t): bool => $t !== '')));
        self::assertGreaterThan(300, count($texts));
        foreach ($texts as $t) {
            self::assertArrayHasKey($t, $en, 'Sans traduction anglaise (i18n/en.txt) : ' . $t);
            self::assertArrayHasKey($t, $xx, 'Absent du modèle de traduction (localisation/template/xx.txt) : ' . $t);
        }
    }

    public function testLiaisonsEtenduesEtListePourLaFenetre(): void
    {
        $links = self::read(self::TOC . 'fn_tocLinks.sqf');
        self::assertSame(['screen', 'post', 'cam', 'furn', 'none'], array_column(self::table($links, '_colours'), 0));
        foreach (['case "list": {', 'COMSPEC_ATAK_TocRoom', 'missionNamespace getVariable ["COMSPEC_ATAK_Cams", []]', '(count _furn) < 60', 'if (_lbl isNotEqualTo "") then { drawIcon3D'] as $needle) {
            self::assertStringContainsString($needle, $links);
        }
        // Toujours borné : interface Zeus ouverte, 400 m, 80 objets.
        self::assertStringContainsString('if (isNull curatorCamera || {isNull (findDisplay 312)}) exitWith {};', $links);
        self::assertStringContainsString('if (_n >= 80) exitWith {};', $links);
        self::assertStringContainsString('["list"] call comspec_atak_native_fnc_tocLinks', self::read(self::TOC . 'fn_tocManagerUiApply.sqf'));
    }
}
