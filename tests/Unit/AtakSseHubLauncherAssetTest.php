<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Lanceur de l'ATAK natif : une seule tuile « SSE » (dossier, page SSEHUB) à la place des huit tuiles des apps liées au
 * portail SSE, des icônes toutes différentes, et l'app OSINT (pas encore faite) retirée de l'accueil. Aucune classe ni
 * page d'app n'est renommée : raccourcis, épingles et liens ouvrent les mêmes pages qu'avant.
 */
final class AtakSseHubLauncherAssetTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';
    private const MAIN = self::ROOT . 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';
    private const PACK = self::ROOT . 'mod/@COMSPEC_SSE/addons/';

    /** Apps regroupées : classe => page (inchangées). Les quatre dernières sont déclarées par le pack SSE. */
    private const CHILDREN = [
        'Sse' => 'SSE',
        'DataLink' => 'DATALINK',
        'Wanted' => 'WANTED',
        'Fiches' => 'FICHES',
        'SseField' => 'SSEFIELD',
        'IdCheck' => 'IDCHECK',
        'Fichiers' => 'FICHIERS',
        'IdCover' => 'IDCOVER',
    ];
    private const FROM_PACK = ['SseField' => 'compat_atak/config.cpp', 'IdCheck' => 'identity/config.cpp', 'Fichiers' => 'identity/config.cpp', 'IdCover' => 'identity/config.cpp'];

    /** Fichiers SQF créés ou modifiés pour le dossier. */
    private const TOUCHED = [
        'functions/ui/fn_sseHub.sqf',
        'functions/pages/fn_pageSseHub.sqf',
        'functions/ui/fn_appVisible.sqf',
        'functions/ui/fn_appList.sqf',
        'functions/ui/fn_appBadge.sqf',
        'functions/ui/fn_appSpaces.sqf',
        'functions/ui/fn_launcherRender.sqf',
        'functions/pages/fn_pageStore.sqf',
    ];

    private function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents($path));
    }

    private function main(string $path): string
    {
        return $this->read(self::MAIN . $path);
    }

    /** @return array<string, string> classe => ligne de déclaration, pour un bloc COMSPEC_ATAK_Apps */
    private function appLines(string $cfg): array
    {
        $out = [];
        $start = strpos($cfg, 'class COMSPEC_ATAK_Apps {');
        if ($start === false) {
            return $out;
        }
        $block = substr($cfg, $start, (int) strpos($cfg, "\n};", $start) - $start);
        preg_match_all('~^\s*class (\w+)\s+\{ (name=.*)$~m', $block, $m, PREG_SET_ORDER);
        foreach ($m as $row) {
            $out[$row[1]] = $row[2];
        }

        return $out;
    }

    private function attr(string $line, string $key): string
    {
        return preg_match('~(?:^|[ ;])' . $key . '="([^"]*)"~', $line, $m) === 1 ? $m[1] : '';
    }

    /** @return array<string, string> toutes les apps du téléphone, ATAK puis pack SSE */
    private function allApps(): array
    {
        $apps = $this->appLines($this->main('config.cpp'));
        foreach (array_unique(array_values(self::FROM_PACK)) as $file) {
            $apps += $this->appLines($this->read(self::PACK . $file));
        }

        return $apps;
    }

    /** @return list<array{0: string, 1: string, 2: string}> table du dossier (fn_sseHub) : classe, nom, description */
    private function hubTable(): array
    {
        $fn = $this->main('functions/ui/fn_sseHub.sqf');
        self::assertSame(1, preg_match('~private _table = \[(.*?)\n\];~s', $fn, $m), 'table du dossier SSE');
        preg_match_all('~\["(\w+)", "([^"]+)", "([^"]+)"\]~', $m[1], $rows, PREG_SET_ORDER);

        return array_map(static fn (array $r): array => [$r[1], $r[2], $r[3]], $rows);
    }

    /** Icône réellement affichée : celle de la config, sauf remplacement de fn_sseHub (apps du pack SSE). */
    private function iconOf(string $id, string $line): string
    {
        $fn = $this->main('functions/ui/fn_sseHub.sqf');
        if (preg_match('~case "' . preg_quote($id, '~') . '": \{ _dir \+ "([\w.]+)" \};~', $fn, $m) === 1) {
            return '\\z\\comspec_atak_native\\addons\\main\\data\\' . $m[1];
        }

        return $this->attr($line, 'icon');
    }

    private function iconFile(string $icon): string
    {
        $main = '\\z\\comspec_atak_native\\addons\\main\\';
        $pack = '\\z\\comspec_sse\\addons\\';
        if (str_starts_with($icon, $main)) {
            return self::MAIN . str_replace('\\', '/', substr($icon, strlen($main)));
        }
        if (str_starts_with($icon, $pack)) {
            return self::PACK . str_replace('\\', '/', substr($icon, strlen($pack)));
        }

        return '';
    }

    public function testHubIsARegisteredAppAndNoPageIsRenamed(): void
    {
        $cfg = $this->main('config.cpp');
        $apps = $this->appLines($cfg);
        self::assertArrayHasKey('SseHub', $apps);
        self::assertSame('SSE', $this->attr($apps['SseHub'], 'name'));
        self::assertSame('SSEHUB', $this->attr($apps['SseHub'], 'page'));
        self::assertSame('comspec_atak_native_fnc_pageSseHub', $this->attr($apps['SseHub'], 'function'));
        self::assertSame('Renseignement', $this->attr($apps['SseHub'], 'section'));
        self::assertStringNotContainsString('system=1', $apps['SseHub']);
        self::assertStringContainsString('class sseHub {};', $cfg);
        self::assertStringContainsString('class pageSseHub {};', $cfg);
        self::assertTrue(is_file(self::MAIN . 'functions/ui/fn_sseHub.sqf'));
        self::assertTrue(is_file(self::MAIN . 'functions/pages/fn_pageSseHub.sqf'));

        // Chaque app regroupée garde sa classe, sa page et sa façon d'être dessinée.
        $all = $this->allApps();
        $render = $this->main('functions/ui/fn_pageRender.sqf');
        foreach (self::CHILDREN as $id => $page) {
            self::assertArrayHasKey($id, $all, $id);
            self::assertSame($page, $this->attr($all[$id], 'page'), $id);
            $routed = $this->attr($all[$id], 'function') !== '' || str_contains($render, 'case "' . $page . '":');
            self::assertTrue($routed, "page $page routée");
            if (isset(self::FROM_PACK[$id])) {
                self::assertArrayHasKey($id, $this->appLines($this->read(self::PACK . self::FROM_PACK[$id])), "$id vient du pack SSE");
            } else {
                self::assertArrayHasKey($id, $apps, "$id vient de l'ATAK");
            }
        }
        // fn_navigate accepte toujours toute page déclarée ; rien de propre au dossier n'y a été ajouté.
        $nav = $this->main('functions/ui/fn_navigate.sqf');
        self::assertStringContainsString('+(([] call comspec_atak_native_fnc_appList) apply { _x get "page" });', $nav);
        self::assertStringNotContainsString('sseHub', $nav);
        self::assertStringNotContainsString('SSEHUB', $nav);
        // Le pack SSE ouvre toujours ses pages par leur identifiant.
        self::assertStringContainsString('["SSE", "SSEFIELD"] select', $this->read(self::PACK . 'biometrics/functions/fn_openSeekKeybind.sqf'));
    }

    public function testLauncherRootNoLongerListsTheGroupedApps(): void
    {
        $home = $this->main('functions/ui/fn_launcherRender.sqf');
        self::assertStringContainsString('private _hubIds = ["rootHidden", _apps] call comspec_atak_native_fnc_sseHub;', $home);
        self::assertStringContainsString('private _rootApps = _apps select { !((_x get "id") in _hubIds) };', $home);
        // Sections (toutes, ou celle de la puce choisie) : construites sans les apps du dossier.
        self::assertSame(2, substr_count($home, '_rootApps select { (_x get "section") isEqualTo'));
        self::assertSame(0, substr_count($home, ' _apps select { (_x get "section") isEqualTo'));
        self::assertStringContainsString('{ _sections pushBackUnique (_x get "section"); } forEach _rootApps;', $home);
        // Épinglées, récentes et groupes du joueur : toujours toutes les apps (une app SSE épinglée reste épinglée).
        self::assertStringContainsString('{ _byPage set [_x get "page", _x]; } forEach _apps;', $home);
        self::assertStringContainsString('private _pinApps = (_pins apply { _byPage getOrDefault [_x, createHashMap] })', $home);
        self::assertStringContainsString('profileNamespace getVariable ["COMSPEC_ATAK_PinnedApps", []]', $home);
        // Recherche : les apps du dossier restent trouvables par leur nom (groupe montré seulement en recherche).
        self::assertStringContainsString('_groups pushBack ["", "SSE", _this, false, true];', $home);

        $hub = $this->main('functions/ui/fn_sseHub.sqf');
        self::assertStringContainsString('case "rootHidden": {', $hub);
        self::assertStringContainsString('isEqualTo "SseHub"', $hub);
        $ids = array_column($this->hubTable(), 0);
        sort($ids);
        $want = array_keys(self::CHILDREN);
        sort($want);
        self::assertSame($want, $ids);

        // Vue regroupée (fn_appSpaces) : le dossier à la place de ses apps.
        $spaces = $this->main('functions/ui/fn_appSpaces.sqf');
        self::assertStringContainsString('["Exploitation", ["Intel", "SseHub", ', $spaces);
        self::assertStringContainsString('private _hubIds = ["rootHidden", _apps] call comspec_atak_native_fnc_sseHub;', $spaces);
    }

    public function testHubShowsOnlyWhatExistsAndNeedsTwoApps(): void
    {
        $vis = $this->main('functions/ui/fn_appVisible.sqf');
        self::assertStringContainsString('if (_id isEqualTo "SseHub" && {(["count"] call comspec_atak_native_fnc_sseHub) < 2}) exitWith { false };', $vis);
        // Mode Essentiel inchangé : le filtre passe avant, et le dossier n'est pas une app du mode.
        self::assertLessThan((int) strpos($vis, '"SseHub"'), (int) strpos($vis, '["hides", _id]'));
        self::assertSame(1, preg_match('~private _essential = \[(.*?)\n\];~s', $this->main('functions/ui/fn_essentialMode.sqf'), $m));
        self::assertStringNotContainsString('SseHub', $m[1]);

        $hub = $this->main('functions/ui/fn_sseHub.sqf');
        // Seulement les apps déclarées (pack SSE chargé ou non) et visibles.
        self::assertStringContainsString('[_x] call comspec_atak_native_fnc_appVisible', $hub);
        self::assertStringContainsString('forEach ([] call comspec_atak_native_fnc_appList);', $hub);
        // Pastille du dossier : somme des compteurs existants des tuiles.
        self::assertStringContainsString('case "SSEHUB": { ["badge"] call comspec_atak_native_fnc_sseHub };', $this->main('functions/ui/fn_appBadge.sqf'));
        self::assertStringContainsString('call comspec_atak_native_fnc_appBadge', $hub);
    }

    public function testHubPageListsEveryAppWithNameDescriptionAndWayBack(): void
    {
        $table = $this->hubTable();
        self::assertCount(count(self::CHILDREN), $table);
        $names = [];
        foreach ($table as [$id, $name, $desc]) {
            self::assertArrayHasKey($id, self::CHILDREN);
            self::assertGreaterThanOrEqual(5, mb_strlen($name), $id);
            self::assertLessThanOrEqual(20, mb_strlen($name), $id);
            self::assertGreaterThanOrEqual(20, mb_strlen($desc), $id);
            self::assertLessThanOrEqual(70, mb_strlen($desc), $id);
            // Une ligne, cherchée telle quelle dans le dictionnaire (fn_tr découpe aux « : » et aux « · »).
            foreach ([' : ', ' · ', '<', "\n"] as $cut) {
                self::assertStringNotContainsString($cut, $name . $desc, $id);
            }
            $names[] = $name;
        }
        self::assertSame(count($names), count(array_unique($names)));
        self::assertNotContains('SSE', $names);

        $page = $this->main('functions/pages/fn_pageSseHub.sqf');
        self::assertStringContainsString('private _apps = ["apps"] call comspec_atak_native_fnc_sseHub;', $page);
        self::assertStringContainsString('_rows pushBack ["person", _app get "icon",', $page);
        self::assertStringContainsString('_name call comspec_atak_native_fnc_tr', $page);
        self::assertStringContainsString('_desc call comspec_atak_native_fnc_tr', $page);
        // Ouverture par la page habituelle de l'app, pastille par app, retour à l'accueil.
        self::assertStringContainsString('["Ouvrir", compile format ["[%1] call comspec_atak_native_fnc_navigate;", str _target], true]', $page);
        self::assertStringContainsString('[_target] call comspec_atak_native_fnc_appBadge', $page);
        self::assertStringContainsString('["Accueil", { ["LAUNCHER"] call comspec_atak_native_fnc_navigate; }, false]', $page);
        self::assertStringContainsString('call comspec_atak_native_fnc_formRender;', $page);
        // Pas de petit libellé au-dessus d'un titre, pas de bloc d'en-tête : la barre d'app dit déjà « SSE ».
        self::assertStringNotContainsString('"hero"', $page);
        self::assertStringNotContainsString('"section"', $page);
    }

    public function testEveryTileHasItsOwnExistingIcon(): void
    {
        $all = $this->allApps();
        self::assertGreaterThan(60, count($all));
        $icons = [];
        foreach ($all as $id => $line) {
            $icon = $this->iconOf($id, $line);
            self::assertNotSame('', $icon, $id);
            $icons[$id] = strtolower($icon);
            $file = $this->iconFile($icon);
            if ($file !== '') {
                self::assertTrue(is_file($file), "$id : $icon");
                self::assertGreaterThan(200, (int) filesize($file), $icon);
            }
        }
        // Aucune icône portée par deux apps : ni à la racine du lanceur, ni dans le dossier, ni entre les deux.
        $seen = [];
        foreach ($icons as $id => $icon) {
            self::assertFalse(isset($seen[$icon]), "$id a la même icône que " . ($seen[$icon] ?? ''));
            $seen[$icon] = $id;
        }
        // La tuile du dossier garde l'icône SSE connue des joueurs.
        self::assertStringContainsString('app_sse.paa', $icons['SseHub']);
        // Le remplacement d'icône ne touche que les deux apps du pack SSE qui reprenaient une icône de l'ATAK.
        self::assertSame(2, preg_match_all('~case "\w+": \{ _dir \+ "[\w.]+" \};~', $this->main('functions/ui/fn_sseHub.sqf')));
        self::assertStringContainsString('["icon", ["icon", [_x select 1, getText (_cfg >> "icon")]] call comspec_atak_native_fnc_sseHub],', $this->main('functions/ui/fn_appList.sqf'));
    }

    public function testOsintIsHiddenButStillDeclared(): void
    {
        $apps = $this->appLines($this->main('config.cpp'));
        self::assertArrayHasKey('Osint', $apps);
        self::assertSame('OSINT', $this->attr($apps['Osint'], 'page'));
        self::assertStringContainsString('hidden=1;', $apps['Osint']);
        self::assertTrue(is_file(self::MAIN . 'functions/pages/fn_pageOsint.sqf'));
        // Seule OSINT est cachée ainsi.
        self::assertSame(1, substr_count(implode("\n", $apps), 'hidden=1;'));
        self::assertStringContainsString('["hidden", (getNumber (_cfg >> "hidden")) > 0],', $this->main('functions/ui/fn_appList.sqf'));
        $vis = $this->main('functions/ui/fn_appVisible.sqf');
        self::assertStringContainsString('if (_app getOrDefault ["hidden", false]) exitWith { false };', $vis);
        // Avant la règle d'installation : même rendue obligatoire, une app cachée ne revient pas à l'accueil.
        self::assertLessThan((int) strpos($vis, 'private _inst ='), (int) strpos($vis, '["hidden", false]'));
        self::assertStringContainsString('{!(_x getOrDefault ["hidden", false])}', $this->main('functions/pages/fn_pageStore.sqf'));
    }

    public function testTouchedSourcesAreBalancedAndSilent(): void
    {
        foreach (self::TOUCHED as $path) {
            $code = preg_replace('~/\*.*?\*/~s', '', $this->main($path)) ?? '';
            $code = preg_replace('~//[^\r\n]*~', '', $code) ?? '';
            $code = preg_replace('~"(?:[^"]|"")*"~s', '""', $code) ?? '';
            $code = preg_replace("~'(?:[^']|'')*'~s", "''", $code) ?? '';
            foreach (['{' => '}', '[' => ']', '(' => ')'] as $open => $close) {
                self::assertSame(substr_count($code, $open), substr_count($code, $close), "équilibre $open$close dans $path");
            }
        }
        // Fichiers du dossier : rien à l'écran d'Arma, pas même le toast du téléphone.
        foreach (['functions/ui/fn_sseHub.sqf', 'functions/pages/fn_pageSseHub.sqf', 'functions/ui/fn_appVisible.sqf', 'functions/ui/fn_appList.sqf', 'functions/ui/fn_appBadge.sqf'] as $path) {
            $src = $this->main($path);
            foreach (['systemChat', 'hint', 'cutText', 'titleText', 'fnc_toast', 'fnc_notify', 'BIS_fnc_showNotification'] as $bad) {
                self::assertSame(0, preg_match('~\b' . $bad . '\b~', $src), "$bad dans $path");
            }
        }
        // Lanceur : les lignes ajoutées pour le dossier ne préviennent de rien.
        foreach (explode("\n", $this->main('functions/ui/fn_launcherRender.sqf')) as $line) {
            if (str_contains($line, '_hub') || str_contains($line, '_rootApps')) {
                self::assertSame(0, preg_match('~systemChat|hint|cutText|titleText|fnc_toast~', $line), $line);
            }
        }
    }
}
