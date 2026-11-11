<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Téléphone qui rame (TTRD, 11/11) : chargement étalé des tuiles du fond photo et réglage « Mesure des performances
 * (journal) ». Lecture du code seulement : rien de tout cela n'a tourné en jeu.
 */
final class AtakPerfLogAssetTest extends TestCase
{
    private const MAIN = __DIR__ . '/../../mod/COMSPEC_ATAK_Native/Sources/addons/main';

    private function read(string $rel): string
    {
        $text = file_get_contents(self::MAIN . '/' . $rel);
        self::assertIsString($text, $rel);

        return str_replace("\r\n", "\n", $text);
    }

    public function testPhotoTilesLoadAFewPerFrame(): void
    {
        $src = $this->read('functions/map/fn_photoMap.sqf');
        // Tuiles déjà chargées retenues, deux neuves au plus par image (une si l'image précédente a ramé).
        self::assertStringContainsString('COMSPEC_ATAK_PhotoSeen', $src);
        self::assertStringContainsString('_pre = _queue select [0, [2, 1] select (diag_deltaTime > 0.1)];', $src);
        // Tuiles en chargement invisibles ; un seul niveau affiché.
        self::assertStringContainsString('{ (_x + [0.01]) call _draw; } forEach _pre;', $src);
        self::assertStringContainsString('{ _x call _draw; } forEach _show;', $src);
        // Plus de dessin direct de toutes les tuiles du niveau visé sans test de chargement.
        self::assertStringNotContainsString('{ _x call _draw; } forEach _fine;', $src);
        // Le fond photo a sa propre mesure dans le RPT.
        self::assertStringContainsString('"carte fond photo (image)"', $this->read('functions/map/fn_mapOnDraw.sqf'));
    }

    public function testPerfLogSettingIsOffAndOnlyWritesTheRpt(): void
    {
        $pre = $this->read('XEH_preInit.sqf');
        self::assertMatchesRegularExpression('/\["comspec_atak_native_perf_log", "CHECKBOX",\s*\["Mesure des performances \(journal\)", "[^"]*Exemple : active-le, ouvre le téléphone puis la carte, puis envoie ton journal avec Envoyer_mes_logs\.bat[^"]*"\],\s*\["COMSPEC", "ATAK · Debug"\], false, 0,/', $pre);
        self::assertStringContainsString('class perfLog {};', $this->read('config.cpp'));

        $log = $this->read('functions/core/fn_perfLog.sqf');
        self::assertStringContainsString('[COMSPEC perf]', $log);
        foreach (['hint', 'systemChat', 'titleText', 'cutText', 'BIS_fnc_showNotification', 'fnc_toast', 'fnc_notify', 'ctrlCreate'] as $ui) {
            self::assertDoesNotMatchRegularExpression('/\b' . preg_quote($ui, '/') . '\b/', $log, $ui);
        }

        // Décoché : une lecture de variable aux points de mesure, rien d'autre.
        $hooks = [
            'functions/core/fn_perfAcc.sqf', 'functions/ui/fn_open.sqf', 'functions/ui/fn_displayLoad.sqf', 'functions/ui/fn_navigate.sqf',
            'functions/ui/fn_hudToggle.sqf', 'functions/ui/fn_interactToggle.sqf', 'functions/network/fn_extensionCall.sqf', 'functions/network/fn_extAwait.sqf',
        ];
        foreach ($hooks as $f) {
            $src = $this->read($f);
            preg_match_all('/^(.*)call comspec_atak_native_fnc_perfLog/m', $src, $m);
            self::assertNotEmpty($m[1], $f);
            foreach ($m[1] as $line) {
                self::assertMatchesRegularExpression('/if \((_perfLog|missionNamespace getVariable \["COMSPEC_ATAK_PerfLog", false\])\) then \{/', $line, $f);
            }
        }

        // Réglage traduit (dictionnaire anglais et modèle des traductions).
        $en = (string) file_get_contents(self::MAIN . '/i18n/en.txt');
        $xx = (string) file_get_contents(self::MAIN . '/../../../localisation/template/xx.txt');
        self::assertStringContainsString('["Mesure des performances (journal)", "Performance measurement (log)"]', $en);
        self::assertStringContainsString('["Mesure des performances (journal)", "", "Performance measurement (log)"]', $xx);
    }

    public function testZeusCentreTranslatorIsNotShadowed(): void
    {
        $src = $this->read('functions/zeus/fn_zeusCentre.sqf');
        // SQF ne distingue pas _t de _T : la traduction porte un nom qu'aucune variable locale ne reprend.
        // Fusion 4.4.0 : la refonte du Centre Zeus (fix/atak-zeus-centre-ui) nomme la traduction _tr (plus _trZ) ;
        // les commentaires qui racontent l'ancien bogue citent _T, ils sont retirés avant la vérification.
        $code = preg_replace('~^\s*//.*$~m', '', $src);
        self::assertDoesNotMatchRegularExpression('/\b_T\b/', $code);
        self::assertStringContainsString('private _tr = { _this call comspec_atak_native_fnc_tr };', $src);
        self::assertSame(1, preg_match_all('/(private\s+_tr\s*=|"_tr")/i', $code));
    }
}
