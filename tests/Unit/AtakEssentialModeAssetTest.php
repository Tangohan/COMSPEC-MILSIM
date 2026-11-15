<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** ATAK 4.18.0 : le mode Essentiel, un seul bouton qui réduit le téléphone aux apps de base (filtre à la source, jamais de désinstallation). */
final class AtakEssentialModeAssetTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';
    private const MAIN = self::ROOT . 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';

    private function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents($path));
    }

    private function main(string $path): string
    {
        return $this->read(self::MAIN . $path);
    }

    /** Équilibre des accolades, crochets et parenthèses hors chaînes et commentaires. */
    private function assertBalanced(string $path): void
    {
        $code = preg_replace('~/\*.*?\*/~s', '', $this->main($path)) ?? '';
        $code = preg_replace('~//[^\r\n]*~', '', $code) ?? '';
        $code = preg_replace('~"(?:[^"]|"")*"~s', '""', $code) ?? '';
        $code = preg_replace("~'(?:[^']|'')*'~s", "''", $code) ?? '';
        foreach (['{' => '}', '[' => ']', '(' => ')'] as $open => $close) {
            self::assertSame(substr_count($code, $open), substr_count($code, $close), "équilibre $open$close dans $path");
        }
    }

    /** @return list<string> apps de la table du mode (fn_essentialMode) */
    private function essentialIds(): array
    {
        $fn = $this->main('functions/ui/fn_essentialMode.sqf');
        self::assertSame(1, preg_match('~private _essential = \[(.*?)\n\];~s', $fn, $m), 'table des apps essentielles');
        preg_match_all('~"([A-Za-z]+)"~', $m[1], $ids);

        return $ids[1];
    }

    /** @return list<string> classes de COMSPEC_ATAK_Apps (config.cpp) */
    private function appClasses(): array
    {
        $cfg = $this->main('config.cpp');
        $start = (int) strpos($cfg, 'class COMSPEC_ATAK_Apps {');
        $block = substr($cfg, $start, (int) strpos($cfg, "\n};", $start) - $start);
        preg_match_all('~^\s*class (\w+)\s+\{ name=~m', $block, $m);

        return $m[1];
    }

    public function testEssentialTableIsShortAndUsesRealApps(): void
    {
        $ids = $this->essentialIds();
        // Courte (4 à 8 apps, plus Réglages qui sert à sortir du mode) ; chaque clé est une vraie app du mod, sans doublon.
        self::assertGreaterThanOrEqual(5, count($ids));
        self::assertLessThanOrEqual(9, count($ids));
        self::assertSame(count($ids), count(array_unique($ids)));
        $apps = $this->appClasses();
        self::assertGreaterThan(50, count($apps));
        foreach ($ids as $id) {
            self::assertContains($id, $apps, "app inconnue : $id");
        }
        // Carte, GPS / navigation, mission, vidéo du drone ; et la sortie du mode.
        foreach (['Map', 'Gps', 'Waypoints', 'Tasks', 'Briefing', 'Drone', 'Livecam', 'Settings'] as $id) {
            self::assertContains($id, $ids, $id);
        }
        // Messages, SMS et Radio ne sont pas indispensables à l'opération : hors du mode.
        foreach (['Chat', 'Sms', 'Store', 'Dating', 'Food', 'Music'] as $id) {
            self::assertNotContains($id, $ids, $id);
        }
        // La table est en tête du fichier, avant tout code.
        $fn = $this->main('functions/ui/fn_essentialMode.sqf');
        self::assertLessThan((int) strpos($fn, 'switch (_act) do'), (int) strpos($fn, 'private _essential = ['));
    }

    public function testFilterIsAppliedOnceAtTheSource(): void
    {
        self::assertStringContainsString('class essentialMode {};', $this->main('config.cpp'));

        $vis = $this->main('functions/ui/fn_appVisible.sqf');
        self::assertStringContainsString('params [["_app", createHashMap], ["_ignoreEssential", false]];', $vis);
        self::assertStringContainsString('if (!_ignoreEssential && {["hides", _id] call comspec_atak_native_fnc_essentialMode}) exitWith { false };', $vis);
        // Réglages reste toujours affiché, avant le filtre : jamais piégé dans le mode.
        self::assertLessThan(
            (int) strpos($vis, '["hides", _id]'),
            (int) strpos($vis, 'if (_id isEqualTo "Settings") exitWith { true };')
        );

        // Tous les endroits qui énumèrent les apps passent par fn_appVisible : accueil, apps regroupées (et recherche), dock, rail, dossiers.
        foreach ([
            'functions/ui/fn_launcherRender.sqf' => 'comspec_atak_native_fnc_appVisible',
            'functions/ui/fn_appSpaces.sqf' => 'comspec_atak_native_fnc_appVisible',
            'functions/ui/fn_dockRender.sqf' => 'comspec_atak_native_fnc_appVisible',
            'functions/pages/fn_pageFolder.sqf' => 'comspec_atak_native_fnc_appVisible',
            'functions/pages/fn_pageSettings.sqf' => 'comspec_atak_native_fnc_appVisible',
        ] as $file => $needle) {
            self::assertStringContainsString($needle, $this->main($file), $file);
        }
        // Le filtre n'est copié nulle part : le lanceur n'appelle fn_essentialMode que pour poser le bouton.
        self::assertSame(1, substr_count($this->main('functions/ui/fn_launcherRender.sqf'), 'comspec_atak_native_fnc_essentialMode'));

        // L'inventaire envoyé au site décrit les apps installées, pas l'affichage.
        self::assertStringContainsString('[_x, true] call comspec_atak_native_fnc_appVisible', $this->main('functions/core/fn_phoneSync.sqf'));
    }

    public function testNothingIsRemovedAndNavigationIsUntouched(): void
    {
        $fn = $this->main('functions/ui/fn_essentialMode.sqf');
        // Ne touche ni aux apps désinstallées, ni aux données, ni aux badges.
        foreach (['COMSPEC_ATAK_HiddenApps', 'profileNamespace', 'COMSPEC_ATAK_Data', 'deleteAt', 'appBadge'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $fn, $forbidden);
        }
        // Une app obligatoire (encadrement) et Admin / Toc (leur propre règle) restent affichées.
        self::assertStringContainsString('(["state", _id] call comspec_atak_native_fnc_appInstall) isNotEqualTo "required"', $fn);
        self::assertStringContainsString('private _own = ["Admin", "Toc"];', $fn);
        // Un raccourci, une notification ou un outil de la carte ouvre toujours l'app : aucun piège.
        self::assertStringNotContainsString('essentialMode', $this->main('functions/ui/fn_navigate.sqf'));
        // Aucune notification, aucun toast, aucune fenêtre d'Arma.
        foreach (['fnc_notify', 'fnc_toast', 'systemChat', 'hint ', 'hintSilent', 'titleText', 'cutText'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $fn, $forbidden);
        }
        // Rien par image : la bascule redessine une seule fois (dédoublonnée par image), par le chemin des apps imposées.
        self::assertStringContainsString('(uiNamespace getVariable ["COMSPEC_ATAK_EssentialAt", -1]) isEqualTo diag_frameNo', $fn);
        self::assertStringContainsString('["applied"] call comspec_atak_native_fnc_appInstall;', $fn);
        self::assertStringContainsString('isNull ([] call comspec_atak_native_fnc_display)', $fn);
    }

    public function testButtonIsOnHomeSettingsAndMap(): void
    {
        $fn = $this->main('functions/ui/fn_essentialMode.sqf');
        // Un bouton à deux états visibles, grisé avec une phrase s'il est imposé.
        self::assertStringContainsString('["ESSENTIEL", "ESSENTIEL · ACTIF"] select _isOn', $fn);
        self::assertStringContainsString('"ESSENTIEL · IMPOSÉ"', $fn);
        self::assertStringContainsString('_b ctrlEnable false;', $fn);
        self::assertStringContainsString('Imposé par l\'encadrement pour toute la mission', $fn);
        self::assertStringContainsString('ButtonClick", { ["toggle"] call comspec_atak_native_fnc_essentialMode; }', $fn);

        // Écran d'accueil : lanceur classique et apps regroupées.
        self::assertStringContainsString('_y = _y + (["bar", [_pad, _y, _gw, _font, _mini]] call comspec_atak_native_fnc_essentialMode);', $this->main('functions/ui/fn_launcherRender.sqf'));
        self::assertStringContainsString('_y = _y + (["bar", [_pad, _y, _gw, _font, _l get "mini"]] call comspec_atak_native_fnc_essentialMode);', $this->main('functions/pages/fn_pageSpace.sqf'));
        // Avant la barre de profil de rôle : pas de décalage de la recherche, posé juste dessous.
        $home = $this->main('functions/ui/fn_launcherRender.sqf');
        self::assertLessThan((int) strpos($home, 'private _viewBar'), (int) strpos($home, '["bar", [_pad, _y, _gw, _font, _mini]]'));
        self::assertLessThan((int) strpos($home, '["bar", [_pad, _y, _gw, _font, _mini]]'), (int) strpos($home, '_srch = [_ed, _hint, _clr];'));

        // Réglages > Applications et panneau SITUATION de la carte : la ligne à interrupteur.
        self::assertStringContainsString('_appsRows append (["rows"] call comspec_atak_native_fnc_essentialMode);', $this->main('functions/pages/fn_pageSettings.sqf'));
        $map = $this->main('functions/pages/fn_pageMap.sqf');
        self::assertStringContainsString('_rows append (["rows"] call comspec_atak_native_fnc_essentialMode);', $map);
        // Posée avant les apps épinglées, sans toucher au bandeau des coordonnées ni à RECHERCHER.
        self::assertLessThan(
            (int) strpos($map, '_rows append (["rows"] call comspec_atak_native_fnc_mapPinnedApp);'),
            (int) strpos($map, '_rows append (["rows"] call comspec_atak_native_fnc_essentialMode);')
        );
        self::assertStringContainsString('["switch", "Mode Essentiel", call _on,', $fn);
        self::assertStringContainsString(', _isLocked, "essentialMode"]', $fn);
        // App Store : une phrase quand le mode est actif.
        self::assertStringContainsString('["on"] call comspec_atak_native_fnc_essentialMode', $this->main('functions/pages/fn_pageStore.sqf'));
    }

    public function testCbaSettingsAreDeclaredAndStaySynchronised(): void
    {
        $pre = $this->main('XEH_preInit.sqf');
        // Par joueur : catégorie « ATAK · Affichage », décoché par défaut, redessin au changement (menu CBA compris).
        self::assertStringContainsString('["comspec_atak_native_essential_mode", "CHECKBOX",', $pre);
        self::assertMatchesRegularExpression(
            '~\["comspec_atak_native_essential_mode", "CHECKBOX",\n    \["Mode Essentiel", "[^"]+"\],\n    \["COMSPEC", "ATAK · Affichage"\], false, 0,\n    \{ \["refresh"\] call comspec_atak_native_fnc_essentialMode; \}\] call comspec_atak_native_fnc_addSetting;~',
            $pre
        );
        // Imposé à tous : réglage serveur, décoché par défaut, rangé avec les autres réglages du lanceur.
        self::assertMatchesRegularExpression(
            '~\["comspec_atak_native_essential_force", "CHECKBOX",\n    \["Mode Essentiel imposé à tous", "[^"]+"\],\n    _spc, false, 1,\n    \{ \["refresh"\] call comspec_atak_native_fnc_essentialMode; \}\] call comspec_atak_native_fnc_addSetting;~',
            $pre
        );
        self::assertStringContainsString('private _spc = ["COMSPEC", "ATAK · Lanceur et mode pilote"];', $pre);
        self::assertLessThan(
            (int) strpos($pre, '["comspec_atak_native_essential_force"'),
            (int) strpos($pre, 'private _spc = ')
        );

        $fn = $this->main('functions/ui/fn_essentialMode.sqf');
        // Le bouton et le réglage sont la même variable, écrite par fn_setSetting (une écriture de profil regroupée, pas à chaque appel).
        self::assertStringContainsString('private _var = "comspec_atak_native_essential_mode";', $fn);
        self::assertStringContainsString('private _forceVar = "comspec_atak_native_essential_force";', $fn);
        self::assertStringContainsString('[_var, (missionNamespace getVariable [_var, false]) isNotEqualTo true] call comspec_atak_native_fnc_setSetting;', $fn);
        self::assertStringNotContainsString('CBA_settings_fnc_set', $fn);
        self::assertStringNotContainsString('saveProfileNamespace', $fn);
        // Imposé : la bascule ne fait rien, le mode est actif quoi que dise le choix du joueur.
        self::assertStringContainsString('if (call _locked) exitWith { false };', $fn);
        self::assertStringContainsString('private _on = { call _locked || {(missionNamespace getVariable [_var, false]) isEqualTo true} };', $fn);
    }

    public function testPinnedAppsOfTheMapFollowTheMode(): void
    {
        $pin = $this->main('functions/map/fn_mapPinnedApp.sqf');
        // Une app masquée par le mode n'a plus de bandeau (comme une app interdite), le choix du joueur est gardé.
        self::assertStringContainsString('{!(["hides", _app get "id"] call comspec_atak_native_fnc_essentialMode)}', $pin);
        self::assertStringContainsString('"Masquée par le mode Essentiel : pas de bandeau"', $pin);
        // Les deux apps essentielles épinglables restent (Ordres, Itinéraire), les autres sont masquées par le mode.
        $ids = $this->essentialIds();
        self::assertContains('Tasks', $ids);
        self::assertContains('Waypoints', $ids);
        self::assertNotContains('Chat', $ids);
    }

    public function testFilesAreBalanced(): void
    {
        foreach ([
            'functions/ui/fn_essentialMode.sqf',
            'functions/ui/fn_appVisible.sqf',
            'functions/ui/fn_launcherRender.sqf',
            'functions/pages/fn_pageSpace.sqf',
            'functions/pages/fn_pageStore.sqf',
            'functions/map/fn_mapPinnedApp.sqf',
        ] as $path) {
            $this->assertBalanced($path);
        }
    }

    public function testTextsAreTranslatedToEnglishWithoutDuplicates(): void
    {
        $en = $this->main('i18n/en.txt');
        foreach ([
            '["Mode Essentiel", "Essential mode"]',
            '["ESSENTIEL", "ESSENTIAL"]',
            '["ESSENTIEL · ACTIF", "ESSENTIAL · ON"]',
            '["ESSENTIEL · IMPOSÉ", "ESSENTIAL · IMPOSED"]',
            '["Mode Essentiel imposé à tous", "Essential mode imposed on everyone"]',
            '["Imposé par l\'encadrement pour toute la mission : tous les téléphones montrent les mêmes apps.", "Imposed by the leadership for the whole mission: every phone shows the same apps."]',
            '["Masquée par le mode Essentiel : pas de bandeau", "Hidden by Essential mode: no banner"]',
        ] as $pair) {
            self::assertStringContainsString($pair, $en);
        }
        // Chaque texte français ajouté a son anglais, sans doublon d'entrée.
        $fn = $this->main('functions/ui/fn_essentialMode.sqf');
        foreach ([
            'Réduit le téléphone aux apps de base : carte, GPS, ordres, drone',
            'Mode Essentiel actif : touchez pour afficher toutes les apps',
            'Réduit le téléphone aux apps de base : carte, GPS, itinéraire, ordres, briefing, alertes, drone et vidéo en direct. Rien n\'est désinstallé ni perdu.',
        ] as $fr) {
            self::assertStringContainsString($fr, $fn);
            self::assertSame(1, substr_count($en, "\n[\"$fr\","), $fr);
        }
        self::assertStringContainsString('Mode Essentiel actif : l\'accueil ne montre que les apps de base.', $this->main('functions/pages/fn_pageStore.sqf'));
        self::assertSame(1, substr_count($en, "\n[\"Mode Essentiel actif : l'accueil ne montre que les apps de base."));
        foreach (['Mode Essentiel', 'ESSENTIEL', 'ESSENTIEL · ACTIF', 'ESSENTIEL · IMPOSÉ', 'Mode Essentiel imposé à tous', 'Masquée par le mode Essentiel : pas de bandeau'] as $fr) {
            self::assertSame(1, substr_count($en, "\n[\"$fr\","), $fr);
        }
        // Réglages CBA : titres et aides traduits (fn_addSetting), EN présent pour l'aide des deux réglages.
        self::assertStringContainsString('"Off by default. Cuts the phone down to the basic apps', $en);
        self::assertStringContainsString('"Off by default (each player chooses). On: every phone is cut down to the basic apps', $en);
        // Dernier élément sans virgule, les autres avec (format strict lu par parseSimpleArray).
        $lines = preg_split('/\n/', rtrim($en)) ?: [];
        self::assertSame(']', end($lines));
        self::assertNotSame(',', substr($lines[count($lines) - 2], -1));
    }

    public function testVersionAndChangelogs(): void
    {
        $hpp = $this->main('script_component.hpp');
        // Sortie en 4.18.0 ; les versions suivantes (4.19.0, signature du SEEK II) la contiennent.
        self::assertMatchesRegularExpression('/#define VERSION_STR "4\.(1[89]\.\d+|[2-9]\d\.\d+)"/', $hpp);
        // La DLL COMSPEC Link ne change pas dans cette version.
        self::assertStringContainsString('#define LINK_VERSION_STR "2.0.128"', $hpp);

        $cl = $this->read(self::ROOT . 'CHANGELOG-ATAK.md');
        $title = '(ATAK natif 4.18.0.AG - Washington)';
        self::assertSame(1, substr_count($cl, $title));
        // Entrée en tête du journal, au-dessus de la 4.17.0.
        self::assertLessThan(strpos($cl, '(ATAK natif 4.17.0.AG - Washington)'), strpos($cl, $title));
        $entry = substr($cl, (int) strpos($cl, $title));
        $entry = substr($entry, 0, (int) strpos($entry, "\n## ", 10));
        self::assertStringContainsString("### Résumé joueur\nType : nouveauté\nFR : Mode Essentiel, un téléphone réduit à l'essentiel en un bouton\n- ", $entry);
        self::assertStringContainsString("\nEN : Essential mode, a phone cut down to the basics with one button\n- ", $entry);
        // Résumé joueur : mêmes puces dans les deux langues, cinq au plus (dont le correctif de l'appareil photo), aucun nom interne.
        $summary = substr($entry, 0, (int) strpos($entry, '### Détail technique'));
        $fr = substr($summary, 0, (int) strpos($summary, "\nEN : "));
        $en = substr($summary, (int) strpos($summary, "\nEN : "));
        self::assertSame(substr_count($fr, "\n- "), substr_count($en, "\n- "));
        self::assertGreaterThanOrEqual(3, substr_count($fr, "\n- "));
        self::assertLessThanOrEqual(5, substr_count($fr, "\n- "));
        foreach (['fn_', 'comspec_atak_native', 'COMSPEC_ATAK_', 'CBA', '.sqf', 'AssetTest', '`'] as $internal) {
            self::assertStringNotContainsString($internal, $summary, $internal);
        }
        foreach (['ESSENTIEL', 'la carte', 'le GPS', 'les ordres', 'le drone', 'la vidéo en direct', 'Réglages > Applications', 'SITUATION', 'Rien n\'est désinstallé'] as $word) {
            self::assertStringContainsString($word, $fr, $word);
        }
        self::assertStringContainsString('### Détail technique', $entry);
        self::assertStringContainsString('`fn_essentialMode`', $entry);
        self::assertStringContainsString('`fn_setSetting`', $entry);

        $steam = $this->read(self::ROOT . 'mod/COMSPEC_ATAK_Native/publisher/STEAM_CHANGELOG.txt');
        self::assertStringContainsString('[h1]COMSPEC ATAK 4.18.0 Washington', $steam);
        $from = (int) strpos($steam, '[h1]COMSPEC ATAK 4.18.0 Washington');
        $block = substr($steam, $from, (int) strpos($steam, '[h1]COMSPEC ATAK 4.17.0 Washington') - $from);
        self::assertStringContainsString('[h2]Français[/h2]', $block);
        self::assertStringContainsString('[h2]English[/h2]', $block);
        self::assertSame(2, substr_count($block, '[list]'));
        self::assertSame(2, substr_count($block, '[/list]'));
        self::assertSame(2, substr_count($block, '[h2]'));
    }
}
