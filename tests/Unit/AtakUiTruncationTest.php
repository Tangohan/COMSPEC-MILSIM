<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Textes coupés relevés par l'autotest du 8/11 (téléphone en portrait) : bandeau de notification dans la barre de titre
 * (« Firin g », « Ante n… »), titre de l'app (« SHORT FI »), décompte de la confirmation d'explosifs, FIRES court.
 * Le contrôle en jeu (AT_fnc_tient, titres des apps et décompte) complète ces vérifications.
 */
final class AtakUiTruncationTest extends TestCase
{
    private const F = __DIR__ . '/../../mod/COMSPEC_ATAK_Native/Sources/addons/main/functions';

    private function read(string $p): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(self::F . '/' . $p));
    }

    public function testBandeauDeNotificationSousLaBarreQuandLaPlaceManque(): void
    {
        $src = $this->read('ui/fn_notificationsRender.sqf');
        self::assertStringContainsString('private _under = (_x1 - _x0) < (_aw * 0.6);', $src);
        self::assertStringContainsString('private _y = [_ay + (_ah - _h) / 2, _ay + _ah] select _under;', $src);
    }

    public function testTitreDeLAppAjuste(): void
    {
        // 4.1.1 : la police ne descend plus à 65 % (sous 14 px) ; abréviation puis icône quand rien ne tient.
        $render = $this->read('ui/fn_pageRender.sqf');
        self::assertStringContainsString('call comspec_atak_native_fnc_titleFit;', $render);
        self::assertStringNotContainsString('[_d displayCtrl 88518, ([] call comspec_atak_native_fnc_layoutGet) get "font", true, 0.65] call comspec_atak_native_fnc_textFit;', $render);
        $fit = $this->read('ui/fn_titleFit.sqf');
        self::assertStringContainsString('COMSPEC_ATAK_FontMin max (pixelH * 14)', $fit);
        self::assertStringContainsString('[_short, _tw, false]', $fit);
        // Place du titre : jusqu'au premier bouton de droite affiché, plus « écran − 4 pas ».
        $layout = $this->read('ui/fn_layoutApply.sqf');
        self::assertStringNotContainsString('_dw - _step * 4', $layout);
        self::assertStringContainsString('[88518, [_tx, _ay, (_tEnd - _tx) max 0, _ah]] call _set;', $layout);
        self::assertStringContainsString('[_font] call comspec_atak_native_fnc_titleFit;', $layout);
    }

    public function testLesTitresLongsOntUneAbreviation(): void
    {
        $cfg = (string) file_get_contents(self::F . '/../config.cpp');
        foreach (['Distance' => 'Dist', 'Chat' => 'Msg', 'InterTeam' => 'I-team', 'Reports' => 'CR', 'Tracking' => 'Tracking'] as $class => $short) {
            self::assertMatchesRegularExpression('/class ' . $class . ' +\{ name="[^"]*"; short="' . preg_quote($short, '/') . '";/', $cfg);
        }
        self::assertStringContainsString('["short", getText (_cfg >> "short")],', $this->read('ui/fn_appList.sqf'));
    }

    public function testTexteReecritEnDirectAPlaceDeDeuxLignes(): void
    {
        self::assertStringContainsString('if (_keep isNotEqualTo "") then { _h = _h max (_fs * 2.5); };', $this->read('ui/fn_formRender.sqf'));
        self::assertStringNotContainsString('["text", "", _surf, "COMSPEC_ATAK_ExploConfirmT"]', $this->read('explo/fn_pageExplo.sqf'));
    }

    public function testFiresCourtMesureSesTextes(): void
    {
        $src = $this->read('tactical/fn_pageQuickMap.sqf');
        self::assertStringContainsString('[_b, _fs, true, 0.65] call comspec_atak_native_fnc_textFit;', $src);
        self::assertStringContainsString('private _hh = (ctrlTextHeight _hd) max (_fs * 1.4);', $src);
        self::assertStringContainsString('private _rh = _rowH max ((ctrlTextHeight _tc) + _pad * 0.6);', $src);
    }

    public function testLeJoueurNEstPasCompteParmiLesAllies(): void
    {
        $src = $this->read('explo/fn_exploAction.sqf');
        self::assertStringContainsString('{_x isNotEqualTo player}', $src);
        self::assertStringNotContainsString('vous compris', $src);
    }
}
