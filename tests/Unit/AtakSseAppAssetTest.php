<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Application SSE unique du téléphone ATAK : une seule tuile (SseHub, page SSEHUB), des sections qui rouvrent les écrans d'origine,
 * une coque (barre SOMMAIRE + fonctions) autour de ces écrans, les anciennes pages et raccourcis qui ouvrent toujours la bonne
 * fonction, et les conditions d'affichage de chaque app d'origine conservées. Lit les sources : indépendant des fins de ligne.
 */
final class AtakSseAppAssetTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';
    private const MAIN = self::ROOT . 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';
    private const PACK = self::ROOT . 'mod/@COMSPEC_SSE/addons/';

    /** Classe => page de chaque app SSE regroupée (aucune page n'est renommée). */
    private const PAGES = [
        'SseField' => 'SSEFIELD', 'IdCheck' => 'IDCHECK', 'Fichiers' => 'FICHIERS', 'IdCover' => 'IDCOVER',
        'Wanted' => 'WANTED', 'Fiches' => 'FICHES', 'SseAlert' => 'SSEALERT',
        'DataLink' => 'DATALINK', 'Sse' => 'SSE',
    ];

    /** Section => classes, dans l'ordre. */
    private const SECTIONS = [
        'control' => ['IdCheck', 'Fichiers', 'IdCover'],
        'field' => ['SseField', 'Sse', 'DataLink'],
        'notices' => ['SseAlert', 'Wanted', 'Fiches'],
    ];

    private function src(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents($path));
    }

    private function main(string $path): string
    {
        return $this->src(self::MAIN . $path);
    }

    /** @return list<string> lignes « class X { ... }; » du bloc COMSPEC_ATAK_Apps d'un config.cpp */
    private function appBlocks(string $cfg): array
    {
        $start = strpos($cfg, 'class COMSPEC_ATAK_Apps {');
        if ($start === false) {
            return [];
        }
        $block = substr($cfg, $start, (int) strpos($cfg, "\n};", $start) - $start);
        preg_match_all('~^\s*class (\w+)\s+\{ (name=.*)$~m', $block, $m, PREG_SET_ORDER);

        return array_map(static fn (array $r): string => $r[1] . ' ' . $r[2], $m);
    }

    /** @return array<string, string> toutes les apps du téléphone : classe => déclaration */
    private function allApps(): array
    {
        $files = [self::MAIN . 'config.cpp'];
        foreach (glob(self::PACK . '*/config.cpp') ?: [] as $f) {
            $files[] = $f;
        }
        $out = [];
        foreach ($files as $f) {
            foreach ($this->appBlocks($this->src($f)) as $line) {
                [$id, $rest] = explode(' ', $line, 2);
                $out[$id] = $rest;
            }
        }

        return $out;
    }

    private function attr(string $line, string $key): string
    {
        return preg_match('~(?:^|[ ;])' . $key . '="([^"]*)"~', $line, $m) === 1 ? $m[1] : '';
    }

    /** @return list<string> classes d'un tableau SQF « [ "A", "B" ] » */
    private function strings(string $list): array
    {
        preg_match_all('~"([^"]+)"~', $list, $m);

        return $m[1];
    }

    // (a) Une seule tuile SSE dans le registre.

    public function testOnlyOneSseTileInTheRegistry(): void
    {
        $all = $this->allApps();
        $tiles = array_keys(array_filter($all, fn (string $l): bool => $this->attr($l, 'page') === 'SSEHUB'));
        self::assertSame(['SseHub'], $tiles);
        // Aucune autre app ne s'appelle « SSE » : la recherche du lanceur n'en trouve qu'une.
        $named = array_keys(array_filter($all, fn (string $l): bool => strtolower($this->attr($l, 'name')) === 'sse'));
        self::assertSame(['SseHub'], $named);
        self::assertSame('comspec_atak_native_fnc_pageSseHub', $this->attr($all['SseHub'], 'function'));
        self::assertStringNotContainsString('system=1', $all['SseHub']);
        self::assertStringNotContainsString('hidden=1', $all['SseHub']);

        // Les apps regroupées n'apparaissent plus à la racine des sections ni dans la vue regroupée, quand l'app est présente.
        $hub = $this->main('functions/ui/fn_sseHub.sqf');
        self::assertStringContainsString('case "rootHidden": {', $hub);
        self::assertStringContainsString('isEqualTo "SseHub"', $hub);
        $launcher = $this->main('functions/ui/fn_launcherRender.sqf');
        self::assertStringContainsString('private _rootApps = _apps select { !((_x get "id") in _hubIds) };', $launcher);
        self::assertStringContainsString('private _hubIds = ["rootHidden", _apps] call comspec_atak_native_fnc_sseHub;', $this->main('functions/ui/fn_appSpaces.sqf'));
        // Aucune dock ni tuile en double : l'app n'est pas dans le dock et reste désinstallable.
        self::assertStringContainsString('dock=0;', $all['SseHub']);
    }

    // (b) Chaque section est mappée sur ses anciens écrans.

    public function testEverySectionMapsToItsOldScreens(): void
    {
        $hub = $this->main('functions/ui/fn_sseHub.sqf');
        self::assertSame(1, preg_match('~private _sectionsDef = \[(.*?)\n\];~s', $hub, $m));
        preg_match_all('~\["(\w+)", "([^"]+)", \[([^\]]*)\]\]~', $m[1], $rows, PREG_SET_ORDER);
        $found = [];
        foreach ($rows as $r) {
            $found[$r[1]] = $this->strings($r[3]);
            self::assertGreaterThanOrEqual(10, mb_strlen($r[2]), $r[1]);
            self::assertLessThanOrEqual(26, mb_strlen($r[2]), $r[1]);
        }
        self::assertSame(self::SECTIONS, $found);

        // Chaque app regroupée est dans exactement une section, et sa classe existe avec la page d'origine.
        $flat = array_merge(...array_values($found));
        self::assertSame(count($flat), count(array_unique($flat)));
        $sorted = $flat;
        sort($sorted);
        $want = array_keys(self::PAGES);
        sort($want);
        self::assertSame($want, $sorted);
        $all = $this->allApps();
        foreach (self::PAGES as $id => $page) {
            self::assertArrayHasKey($id, $all, $id);
            self::assertSame($page, $this->attr($all[$id], 'page'), $id);
        }
        // La table de l'app (nom, phrase) couvre les mêmes classes ; l'accueil ouvre la page d'origine de chaque fonction.
        self::assertSame(1, preg_match('~private _table = \[(.*?)\n\];~s', $hub, $t));
        preg_match_all('~\["(\w+)", "[^"]+", "[^"]+"\]~', $t[1], $ids);
        $tableIds = $ids[1];
        sort($tableIds);
        self::assertSame($want, $tableIds);
        $page = $this->main('functions/pages/fn_pageSseHub.sqf');
        self::assertStringContainsString('str _target]', $page);
        self::assertStringContainsString('_target = _app get "page"', $page);
        self::assertStringContainsString('["fold", _secTitle,', $page);
        // Les écrans d'origine restent routés comme avant.
        $render = $this->main('functions/ui/fn_pageRender.sqf');
        foreach (['SSE' => 'pageSse', 'WANTED' => 'pageWanted', 'FICHES' => 'pageFiches'] as $p => $fn) {
            self::assertStringContainsString('case "' . $p . '": { [] call comspec_atak_native_fnc_' . $fn . '; };', $render);
        }
        foreach (['DataLink' => 'comspec_atak_native_fnc_pageDataLink', 'SseAlert' => 'comspec_atak_native_fnc_pageAlertes'] as $id => $fn) {
            self::assertSame($fn, $this->attr($all[$id], 'function'), $id);
        }
        foreach (['SseField' => 'compat_atak', 'IdCheck' => 'identity', 'Fichiers' => 'identity', 'IdCover' => 'identity'] as $id => $addon) {
            self::assertStringContainsString('comspec_sse_fnc_', $this->attr($all[$id], 'function'), $id);
            self::assertArrayHasKey($id, array_flip(array_map(static fn (string $l): string => explode(' ', $l)[0], $this->appBlocks($this->src(self::PACK . $addon . '/config.cpp')))), "$id déclaré par $addon");
        }
    }

    public function testShellStripAndPageListsAreTheSameEverywhere(): void
    {
        $hub = $this->main('functions/ui/fn_sseHub.sqf');
        self::assertSame(1, preg_match('~private _pages = \[([^\]]*)\];~', $hub, $m));
        $pages = $this->strings($m[1]);
        $want = array_values(self::PAGES);
        sort($want);
        $sorted = $pages;
        sort($sorted);
        self::assertSame($want, $sorted);

        // fn_layoutGet : même liste, deux fois (clé de mémoire et place de la barre).
        $layout = $this->main('functions/ui/fn_layoutGet.sqf');
        self::assertSame(2, preg_match_all('~\(_s0? getOrDefault \["activePage", ""\]\) in \[([^\]]*)\]~', $layout, $mm));
        foreach ($mm[1] as $list) {
            self::assertSame($pages, $this->strings($list));
        }
        self::assertStringContainsString('["body", [_sx + _railW, _bodyY + _sseH, _sw - _railW, _bodyH - _sseH]],', $layout);
        self::assertStringContainsString('["sseStrip", [_sx + _railW, _bodyY, _sw - _railW, _sseH]],', $layout);
        // La barre est dessinée avant la page, le titre dit « SSE · fonction ».
        $render = $this->main('functions/ui/fn_pageRender.sqf');
        self::assertStringContainsString('if (["hosted", _page] call comspec_atak_native_fnc_sseHub) then { ["drawStrip", _page] call comspec_atak_native_fnc_sseHub; };', $render);
        self::assertLessThan((int) strpos($render, 'switch (_page) do {'), (int) strpos($render, '["drawStrip", _page]'));
        self::assertStringContainsString('format ["SSE · %1", toUpper ((_fn select 0) select 1)]', $render);
        // Barre : SOMMAIRE puis les fonctions visibles de la section, la page courante choisie, la pastille de chaque app.
        self::assertStringContainsString('["SOMMAIRE", "[\'home\'] call comspec_atak_native_fnc_sseHub;", false, 0]', $hub);
        self::assertStringContainsString('[_p] call comspec_atak_native_fnc_appBadge', $hub);
        self::assertStringContainsString('["tabs", _tabs]', $hub);
        self::assertStringContainsString('[["strip", _arg] call comspec_atak_native_fnc_sseHub, _r, false] call comspec_atak_native_fnc_formRender;', $hub);
    }

    // (c) Compatibilité : anciennes pages, raccourcis, notifications, épingles.

    public function testOldPagesShortcutsAndPinsStillWork(): void
    {
        // Toute page déclarée s'ouvre par fn_navigate : les anciens raccourcis, notifications et liens profonds passent.
        $nav = $this->main('functions/ui/fn_navigate.sqf');
        self::assertStringContainsString('+(([] call comspec_atak_native_fnc_appList) apply { _x get "page" });', $nav);
        // Les apps d'origine gardent leur classe : rien à renommer côté appelants.
        $all = $this->allApps();
        foreach (self::PAGES as $id => $page) {
            self::assertArrayHasKey($id, $all);
            self::assertSame($page, $this->attr($all[$id], 'page'));
        }
        // Lien profond, touche SEEK, raccourcis ACE du pack : ils appellent toujours les pages d'origine.
        self::assertStringContainsString('["SSE", "SSEFIELD"] select', $this->src(self::PACK . 'biometrics/functions/fn_openSeekKeybind.sqf'));
        self::assertStringContainsString('["WANTED"] call comspec_sse_fnc_openApp', $this->src(self::PACK . 'identity/functions/fn_fileAction.sqf'));
        self::assertStringContainsString('"SSEFIELD"', $this->src(self::PACK . 'compat_atak/functions/fn_evidencePhoto.sqf'));
        // Les rafraîchissements d'une fonction se font sur sa page : elle reste la page active (la coque n'en change pas).
        self::assertStringContainsString('isEqualTo "SSEALERT"', $this->main('functions/alertes/fn_alertesSend.sqf'));
        $hub = $this->main('functions/ui/fn_sseHub.sqf');
        self::assertStringNotContainsString('activePage", "SSEHUB"', $hub);

        // Épingles et récentes d'une ancienne tuile : remplacées une fois par l'app SSE, sans erreur sur un profil vide ou abîmé.
        self::assertStringContainsString('case "migrate": {', $hub);
        self::assertStringContainsString('COMSPEC_ATAK_SseMigrated', $hub);
        self::assertStringContainsString('["COMSPEC_ATAK_PinnedApps", "COMSPEC_ATAK_AppRecent"]', $hub);
        self::assertStringContainsString('if (_cur isEqualType [] &&', $hub);
        self::assertStringContainsString('_new pushBackUnique ([_x, "SSEHUB"] select (_x isEqualType "" && {_x in _pages}));', $hub);
        // Pas de migration si l'app n'a aucune fonction visible (les anciennes épingles restent alors telles quelles).
        self::assertStringContainsString('if ((["count"] call comspec_atak_native_fnc_sseHub) < 1) exitWith { false };', $hub);
        self::assertStringContainsString('["migrate"] call comspec_atak_native_fnc_sseHub;', $this->main('functions/ui/fn_launcherRender.sqf'));
        // Une fonction ouverte compte comme l'app dans les récentes.
        self::assertStringContainsString('case "recent": {', $hub);
        self::assertStringContainsString('private _recPage = ["recent", _page] call comspec_atak_native_fnc_sseHub;', $nav);
        // Sommaire : retire de l'historique ce qui est après l'accueil de l'app ; changement de fonction : remplace sans empiler.
        self::assertStringContainsString('_h resize (_i + 1);', $hub);
        self::assertStringContainsString('_h set [(count _h) - 1, _page];', $hub);
        self::assertStringContainsString('[_page, false, true] call comspec_atak_native_fnc_navigate', $hub);
        // La pastille de l'app : somme des pastilles des fonctions.
        self::assertStringContainsString('case "SSEHUB": { ["badge"] call comspec_atak_native_fnc_sseHub };', $this->main('functions/ui/fn_appBadge.sqf'));
    }

    // (d) Droits et conditions d'affichage conservés.

    public function testVisibilityRulesOfTheOriginalAppsAreKept(): void
    {
        $hub = $this->main('functions/ui/fn_sseHub.sqf');
        // Une section ou une fonction n'existe que si l'app d'origine serait visible : mêmes règles (fn_appVisible), jamais copiées.
        self::assertStringContainsString('[_x] call comspec_atak_native_fnc_appVisible', $hub);
        self::assertStringContainsString('!(_x getOrDefault ["wip", false])', $hub);
        self::assertStringContainsString('if ((count _rows) > 0) then { _out pushBack [_sid, _title, _rows]; };', $hub);
        self::assertStringNotContainsString('hasDevice', $hub);

        $vis = $this->main('functions/ui/fn_appVisible.sqf');
        // Règles d'origine inchangées : mode Essentiel, interdiction, désinstallation, Athena refusé pour les apps du portail.
        self::assertStringContainsString('["hides", _id] call comspec_atak_native_fnc_essentialMode', $vis);
        self::assertStringContainsString('if (_inst isEqualTo "forbidden") exitWith { false };', $vis);
        self::assertStringContainsString('_id in ["Athena", "Resynch", "Discord", "SseAlert", "Wanted", "Fiches"] && {([] call comspec_atak_native_fnc_athenaMode) isEqualTo "off"}', $vis);
        self::assertStringContainsString('_inst isNotEqualTo "removed"', $vis);
        // L'app SSE elle-même : visible dès qu'une fonction l'est, absente sinon ; filtre du mode Essentiel avant.
        self::assertStringContainsString('if (_id isEqualTo "SseHub" && {(["count"] call comspec_atak_native_fnc_sseHub) < 1}) exitWith { false };', $vis);
        self::assertLessThan((int) strpos($vis, '"SseHub"'), (int) strpos($vis, '["hides", _id]'));
        self::assertSame(1, preg_match('~private _essential = \[(.*?)\n\];~s', $this->main('functions/ui/fn_essentialMode.sqf'), $m));
        self::assertStringNotContainsString('SseHub', $m[1]);

        // Ouverture : interdiction et désinstallation restent contrôlées par fn_navigate pour chaque page d'origine.
        $nav = $this->main('functions/ui/fn_navigate.sqf');
        self::assertStringContainsString('if (_inst isEqualTo "forbidden") exitWith {', $nav);
        self::assertStringContainsString('if (_inst isEqualTo "removed") then {', $nav);
        // Compteurs : lus dans ce que l'app Avis et vigilance garde déjà (aucune requête, aucune boucle).
        self::assertStringContainsString('uiNamespace getVariable ["COMSPEC_ATAK_Alertes", createHashMap]', $hub);
        self::assertStringContainsString('missionNamespace getVariable ["COMSPEC_ATAK_AlertesOut", []]', $hub);
        self::assertStringContainsString('isEqualTo "ÉCHEC"', $hub);
        $page = $this->main('functions/pages/fn_pageSseHub.sqf');
        self::assertStringContainsString('"SseAlert"', $page);
        self::assertStringContainsString('Avis en vigueur : %1', $page);
        self::assertStringContainsString('Envois en échec : %1', $page);
    }

    public function testSearchBadgesAndSilentSources(): void
    {
        $page = $this->main('functions/pages/fn_pageSseHub.sqf');
        self::assertStringContainsString('["field", "sseQ", "Chercher une fonction"', $page);
        self::assertStringContainsString('["search", ["sseQ"] call comspec_atak_native_fnc_formValue] call comspec_atak_native_fnc_sseHub;', $page);
        self::assertStringContainsString('toLower', $page);
        self::assertStringContainsString('"Aucune fonction trouvée"', $page);

        $files = [
            'functions/ui/fn_sseHub.sqf', 'functions/pages/fn_pageSseHub.sqf', 'functions/ui/fn_layoutGet.sqf',
            'functions/ui/fn_pageRender.sqf', 'functions/ui/fn_navigate.sqf', 'functions/ui/fn_appVisible.sqf', 'functions/ui/fn_launcherRender.sqf',
        ];
        foreach ($files as $path) {
            $code = $this->main($path);
            $code = preg_replace('~/\*.*?\*/~s', '', $code) ?? '';
            $code = preg_replace('~//[^\n]*~', '', $code) ?? '';
            $code = preg_replace('~"(?:[^"]|"")*"~s', '""', $code) ?? '';
            $code = preg_replace("~'(?:[^']|'')*'~s", "''", $code) ?? '';
            foreach (['{' => '}', '[' => ']', '(' => ')'] as $open => $close) {
                self::assertSame(substr_count($code, $open), substr_count($code, $close), "équilibre $open$close dans $path");
            }
        }
        // Rien à l'écran d'Arma hors téléphone, et aucun toast ajouté par l'app : seuls les fichiers de l'app sont concernés.
        foreach (['functions/ui/fn_sseHub.sqf', 'functions/pages/fn_pageSseHub.sqf'] as $path) {
            $src = $this->main($path);
            foreach (['systemChat', 'hint', 'hintSilent', 'cutText', 'titleText', 'fnc_toast', 'fnc_notify', 'BIS_fnc_showNotification', 'cutRsc', 'titleRsc'] as $bad) {
                self::assertSame(0, preg_match('~\b' . $bad . '\b~', $src), "$bad dans $path");
            }
            // Rien ne tourne en continu : ni boucle par image, ni attente, ni scrutateur.
            foreach (['onEachFrame', 'addMissionEventHandler', 'CBA_fnc_addPerFrameHandler', 'waitUntil', 'sleep', 'spawn'] as $loop) {
                self::assertSame(0, preg_match('~\b' . $loop . '\b~', $src), "$loop dans $path");
            }
        }
        // Mots interdits dans les textes du joueur.
        foreach (['functions/ui/fn_sseHub.sqf', 'functions/pages/fn_pageSseHub.sqf'] as $path) {
            self::assertSame(0, preg_match('~fiction|simulation|spécimen|specimen|RP\b~i', $this->main($path)), $path);
        }
    }

    public function testNewTextsHaveAnEnglishTranslation(): void
    {
        $en = $this->main('i18n/en.txt');
        $need = [
            'Contrôle et identités', 'Terrain et analyse', 'Avis et recherches', 'SOMMAIRE', 'Chercher une fonction', 'Chercher',
            'Avis en vigueur : %1', 'Envois en échec : %1', '%1 à voir', '%1 fonctions', 'Aucune fonction trouvée',
            'Essayez un autre mot ou affichez tout.', 'Tout afficher',
            'TERRAIN', 'CONTRÔLE ID', 'FICHIERS', 'COUVERTURE', 'RECHERCHÉS', 'FICHES', 'AVIS', 'EXTRACTION', 'ANALYSE',
            'SUR LE TERRAIN', "CONSOLE D'ANALYSE", 'MA COUVERTURE',
        ];
        foreach ($need as $fr) {
            self::assertStringContainsString("\n[\"" . $fr . '", "', $en, $fr);
        }
        // Libellés d'onglets et noms de la table : chacun a sa forme capitale pour le titre et la barre.
        $hub = $this->main('functions/ui/fn_sseHub.sqf');
        self::assertSame(1, preg_match('~private _tabName = createHashMapFromArray \[(.*?)\];\n~s', $hub, $m));
        preg_match_all('~\["(\w+)", "([^"]+)"\]~', $m[1], $tabs, PREG_SET_ORDER);
        self::assertCount(count(self::PAGES), $tabs);
        foreach ($tabs as [, , $label]) {
            self::assertStringContainsString("\n[\"" . mb_strtoupper($label) . '", "', $en, $label);
        }
    }
}
