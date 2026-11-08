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
        self::assertStringContainsString('[_d displayCtrl 88518, ([] call comspec_atak_native_fnc_layoutGet) get "font", true, 0.65] call comspec_atak_native_fnc_textFit;', $this->read('ui/fn_pageRender.sqf'));
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
