<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** ATAK 4.16.0 : une app (Messages, SMS, Ordres) peut être épinglée sur la carte, dans un bandeau de la taille de celui des coordonnées. */
final class AtakPinnedAppMapAssetTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';
    private const MAIN = self::ROOT . 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';

    private function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents($path));
    }

    public function testPinnedAppIsAnOffByDefaultPlayerSetting(): void
    {
        $pre = $this->read(self::MAIN . 'XEH_preInit.sqf');
        // 4.17.0 : le réglage de la 4.16.0 reste le premier emplacement, la liste s'allonge (mini-apps), « Aucune » reste le défaut.
        self::assertStringContainsString('["comspec_atak_native_map_pinned_app", "App épinglée sur la carte", ', $pre);
        self::assertStringContainsString('[_pinVar, "LIST", [_pinTitle, _pinHelp],', $pre);
        self::assertStringContainsString('["COMSPEC", "ATAK · Affichage"], [["none", "CHAT", "SMS", "TASK", ', $pre);
        self::assertStringContainsString('"Alertes", "Heure H"], 0], 0,', $pre);
        self::assertStringContainsString('{ ["refresh"] call comspec_atak_native_fnc_mapPinnedApp; }] call comspec_atak_native_fnc_addSetting;', $pre);
    }

    public function testFunctionIsRegisteredAndBalanced(): void
    {
        self::assertStringContainsString('class mapPinnedApp {};', $this->read(self::MAIN . 'config.cpp'));

        $fn = $this->read(self::MAIN . 'functions/map/fn_mapPinnedApp.sqf');
        // Équilibre des accolades, crochets et parenthèses hors chaînes et commentaires.
        $code = preg_replace('~/\*.*?\*/~s', '', $fn) ?? '';
        $code = preg_replace('~//[^\r\n]*~', '', $code) ?? '';
        $code = preg_replace('~"(?:[^"]|"")*"~s', '""', $code) ?? '';
        foreach (['{' => '}', '[' => ']', '(' => ')'] as $open => $close) {
            self::assertSame(substr_count($code, $open), substr_count($code, $close), "équilibre $open$close");
        }
    }

    public function testFunctionSummarisesTheThreeAppsWithoutAnyPopup(): void
    {
        $fn = $this->read(self::MAIN . 'functions/map/fn_mapPinnedApp.sqf');
        foreach (['"CHAT"', '"SMS"', '"TASK"'] as $page) {
            self::assertStringContainsString($page, $fn);
        }
        // Données : messages, SMS directs, ordres ; ouverture de l'app au toucher.
        self::assertStringContainsString('comspec_atak_native_fnc_messagesAll', $fn);
        self::assertStringContainsString('comspec_atak_native_fnc_tasksAll', $fn);
        self::assertStringContainsString('comspec_atak_native_fnc_appBadge', $fn);
        self::assertStringContainsString('comspec_atak_native_fnc_navigate', $fn);
        // Mise à jour sans redessin : texte réécrit seulement s'il a changé, mesuré par fn_perfAcc.
        self::assertStringContainsString('(_c getVariable ["txt", ""]) isEqualTo _txt', $fn);
        self::assertStringContainsString('comspec_atak_native_fnc_perfAcc', $fn);
        // Règle : aucune notification, aucun toast, aucun message d'Arma.
        foreach (['fnc_notify', 'fnc_toast', 'systemChat', 'hint ', 'hintSilent', 'titleText', 'cutText'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $fn, $forbidden);
        }
    }

    public function testMapPageBuildsTheBannerAndReservesItsPlace(): void
    {
        $page = $this->read(self::MAIN . 'functions/pages/fn_pageMap.sqf');
        self::assertStringContainsString('["apps"] call comspec_atak_native_fnc_mapPinnedApp', $page);
        self::assertStringContainsString('["build", [_ov, _mk, [_pinX, _pinY, _pinW, _pinH]', $page);
        // Même hauteur que le bandeau des coordonnées, placée à sa droite ; masquée en petit format.
        self::assertStringContainsString('["height", [_panFs, _panIn]] call comspec_atak_native_fnc_mapPinnedApp', $page);
        self::assertStringContainsString('if ((count _pinApps) > 0 && {!_mini}) then {', $page);
        // Réservée pour les fiches, RECHERCHER, le menu OUTILS et les étiquettes de la carte.
        self::assertStringContainsString('if (!_pinTop) then { _panH = _panH max (_pinOff + _pinH); };', $page);
        self::assertStringContainsString('_hud pushBack [_pinX - _bx, _pinY - _by, _pinX + _pinW - _bx, _pinY + _pinH - _by];', $page);
        // Choix en un toucher depuis le panneau SITUATION.
        self::assertStringContainsString('(["rows"] call comspec_atak_native_fnc_mapPinnedApp)', $page);

        $upd = $this->read(self::MAIN . 'functions/map/fn_mapOverlayUpdate.sqf');
        self::assertStringContainsString('["tick"] call comspec_atak_native_fnc_mapPinnedApp;', $upd);
        // Après la sortie des appels par image (boussole seule, curseur seul) : pas de coût à chaque frame.
        self::assertGreaterThan(
            (int) strpos($upd, 'if (_cursorOnly && {!_tapeOnly}) exitWith { true };'),
            (int) strpos($upd, '["tick"] call comspec_atak_native_fnc_mapPinnedApp;')
        );
    }

    public function testSettingIsAlsoInTheMapSettingsPage(): void
    {
        $set = $this->read(self::MAIN . 'functions/pages/fn_pageSettings.sqf');
        // 4.17.0 : la même ligne de boutons à bascule que le panneau SITUATION.
        self::assertStringContainsString('(["rows"] call comspec_atak_native_fnc_mapPinnedApp) select 0,', $set);
    }

    public function testTextsAreTranslatedToEnglish(): void
    {
        $en = $this->read(self::MAIN . 'i18n/en.txt');
        foreach ([
            '["App épinglée sur la carte", "App pinned on the map"]',
            '["%1 non lu(s)", "%1 unread"]',
            '["%1 à traiter", "%1 to handle"]',
            '["Aucun non lu", "None unread"]',
            '["Aucun SMS", "No SMS"]',
            '["Ouvrir l\'app épinglée", "Open the pinned app"]',
            '["Apps épinglées sur la carte", "Apps pinned on the map"]',
        ] as $pair) {
            self::assertStringContainsString($pair, $en);
        }
        // Pas de doublon d'entrée française dans le dictionnaire pour les textes ajoutés.
        self::assertSame(1, substr_count($en, "\n[\"App épinglée sur la carte\","));
    }

    public function testVersionAndChangelogs(): void
    {
        $hpp = $this->read(self::MAIN . 'script_component.hpp');
        self::assertStringContainsString('#define VERSION 4.16.0', $hpp);
        self::assertStringContainsString('#define VERSION_STR "4.16.0"', $hpp);
        self::assertStringContainsString('#define VERSION_NAME "4.16.0.AG - Washington"', $hpp);

        $cl = $this->read(self::ROOT . 'CHANGELOG-ATAK.md');
        self::assertStringContainsString('(ATAK natif 4.16.0.AG - Washington)', $cl);
        $entry = substr($cl, (int) strpos($cl, '(ATAK natif 4.16.0.AG - Washington)'));
        $entry = substr($entry, 0, (int) strpos($entry, "\n## ", 10));
        self::assertStringContainsString("Type : nouveauté\nFR : Épinglez une app sur la carte", $entry);
        self::assertStringContainsString("\nEN : Pin an app on the map", $entry);
        // Résumé joueur : ni nom de fonction, ni clé de réglage.
        $summary = substr($entry, 0, (int) strpos($entry, '### Détail technique'));
        self::assertStringNotContainsString('fn_', $summary);
        self::assertStringNotContainsString('comspec_atak_native', $summary);

        $steam = $this->read(self::ROOT . 'mod/COMSPEC_ATAK_Native/publisher/STEAM_CHANGELOG.txt');
        self::assertStringStartsWith('[h1]COMSPEC ATAK 4.16.0 Washington', $steam);
    }
}
