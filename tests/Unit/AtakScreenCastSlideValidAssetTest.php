<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * ATAK natif : diapositive diffusée sur un écran COMSPEC (fn_screenCast). Une image n'est posée sur l'objet qu'après
 * validation (_objTex : JPEG ou PAA, hors du cache téléchargé, taille lue par Arma) ; sinon le navigateur intégré, et
 * si rien ne marche une carte « Diapositive indisponible » avec la raison, jamais une dalle noire (poste PC incurvé,
 * 4.7.1 : « image posée : comspec_cache\briefing\… » puis dalle noire).
 */
final class AtakScreenCastSlideValidAssetTest extends TestCase
{
    private const MAIN = __DIR__ . '/../../mod/COMSPEC_ATAK_Native/Sources/addons/main';

    private function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(self::MAIN . '/' . $path));
    }

    private function cast(): string
    {
        return $this->read('functions/pages/fn_screenCast.sqf');
    }

    /** Corps de la validation _objTex. */
    private function objTex(): string
    {
        self::assertSame(1, preg_match('/private _objTex = \{(.*?)\n\};/s', $this->cast(), $m), '_objTex introuvable');

        return $m[1];
    }

    public function testFileIsValidatedBeforeBeingAppliedToTheObject(): void
    {
        $body = $this->objTex();
        self::assertStringContainsString('[".jpg", "jpeg", ".paa"]', $body);
        self::assertStringContainsString('comspec_atak_native_fnc_texUsable', $body);
        self::assertStringContainsString('getTextureInfo', $body);
        // Largeur et hauteur lues par Arma, toutes deux positives.
        self::assertMatchesRegularExpression('/_info param \[0, -1\]\) max 0\) > 0/', $body);
        self::assertMatchesRegularExpression('/_info param \[1, -1\]\) max 0\) > 0/', $body);
    }

    public function testDownloadedCacheFileIsNeverAppliedToAnObject(): void
    {
        $body = $this->objTex();
        self::assertMatchesRegularExpression('/find "comspec_cache"\) >= 0\) exitWith \{ "" \}/', $body);
        // Le refus du cache vient avant la lecture de la texture.
        self::assertLessThan(strpos($body, 'getTextureInfo'), strpos($body, 'comspec_cache'));
    }

    public function testDownloadResultGoesThroughTheValidation(): void
    {
        $sqf = $this->cast();
        self::assertStringContainsString('[_key, _id, _cache, _objTex] spawn', $sqf);
        self::assertStringContainsString('private _use = [_p] call _objTex;', $sqf);
        // Plus de pose directe sur la seule foi de fn_texUsable (lisible en interface, noir sur un objet).
        self::assertSame(1, substr_count($sqf, 'comspec_atak_native_fnc_texUsable'));
        // Repli : navigateur intégré (image de l'app Briefing ou relue par COMSPEC Link).
        self::assertStringContainsString('_use = "WEB:" + _key;', $sqf);
        self::assertStringContainsString('comspec_atak_native_fnc_slideWebData', $sqf);
    }

    public function testCachedPathIsCheckedAgainBeforeThePaint(): void
    {
        $sqf = $this->cast();
        $check = strpos($sqf, '{([_p] call _objTex) isEqualTo ""}) then {');
        $paint = strpos($sqf, '["paint", [_o, _sel, [_want,');
        self::assertNotFalse($check);
        self::assertNotFalse($paint);
        self::assertLessThan($paint, $check);
        self::assertStringContainsString('non posable sur un objet, diapositive reprise par le navigateur intégré', $sqf);
    }

    public function testUnavailableSlideShowsACardWithTheReasonNeverABlackPanel(): void
    {
        $sqf = $this->cast();
        self::assertStringContainsString('_status = format ["Diapositive indisponible : %1.', $sqf);
        self::assertStringContainsString('getOrDefault [_key, "image refusée par le jeu"]', $sqf);
        // La raison est gardée pour la carte et écrite au journal.
        self::assertStringContainsString('_whyMap set [_key, _why];', $sqf);
        self::assertStringContainsString('carte « Diapositive indisponible » sur l\'écran : %1', $sqf);
        // Carte dessinée dans la texture UI de l'écran dès qu'un message existe.
        self::assertStringContainsString('{_status isNotEqualTo ""}) then {', $sqf);
        self::assertStringContainsString('_msgC ctrlSetStructuredText parseText', $sqf);
        self::assertStringNotContainsString('écran noir : Arma refuse', $sqf);
    }

    public function testStopRestoresThePreviousViewOfThePanel(): void
    {
        $sqf = $this->cast();
        self::assertMatchesRegularExpression('/case "stop": \{.*?_a setVariable \["COMSPEC_ATAK_ScreenCast", \[\], true\];/s', $sqf);
        self::assertStringContainsString('[_want, _o getVariable ["COMSPEC_ATAK_ScreenOrig", ""]] select (_want isEqualTo "")', $sqf);
        self::assertStringContainsString('["surface", [_o, _sel, _want isNotEqualTo ""]]', $sqf);
        // Écran TOC : sa texture est redemandée par fn_tocScreen au passage suivant.
        self::assertStringContainsString('then { _o setVariable ["COMSPEC_ATAK_TocTex", ""]; };', $sqf);
    }

    public function testNoGamePopupAndBothLanguages(): void
    {
        $sqf = $this->cast();
        foreach (['hint ', 'systemChat', 'titleText', 'cutText', 'BIS_fnc_showNotification'] as $cmd) {
            self::assertStringNotContainsString($cmd, $sqf, $cmd);
        }
        $en = $this->read('i18n/en.txt');
        $xx = str_replace("\r\n", "\n", (string) file_get_contents(self::MAIN . '/../../../localisation/template/xx.txt'));
        foreach ([
            'Diapositive indisponible : %1. Nouvel essai dans une minute. Elle reste visible dans l\'app Briefing du téléphone.',
            'affichée par le navigateur intégré (fichier hors PBO, non posable sur un objet)',
            'carte « Diapositive indisponible » sur l\'écran : %1',
            'Écran %1 : image %2 non posable sur un objet, diapositive reprise par le navigateur intégré',
        ] as $fr) {
            self::assertStringContainsString('["' . $fr . '", "', $en, $fr);
            self::assertStringContainsString('["' . $fr . '", "", "', $xx, $fr);
        }
    }
}
