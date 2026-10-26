<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * ATAK natif, interface plus réaliste (lot 1) : icônes d'apps sur un carré arrondi coloré (texture PAA teintée),
 * icône qui s'enfonce à l'appui, transitions sans calque opaque (plus de flash noir), activées par défaut.
 */
final class AtakRealisticUiTest extends TestCase
{
    private const MAIN = __DIR__ . '/../../mod/COMSPEC_ATAK_Native/Sources/addons/main';

    private function read(string $path): string
    {
        return (string) file_get_contents(self::MAIN . '/' . $path);
    }

    public function testSquircleTextureIsAPaaInsideTheAddon(): void
    {
        $paa = self::MAIN . '/data/ui_icon_squircle.paa';
        self::assertFileExists($paa);
        // En-tête DXT5 (0xFF05) : texture lue dans le PBO, jamais hors PBO (Arma la refuserait).
        self::assertSame("\x05\xFF", substr((string) file_get_contents($paa), 0, 2));
        self::assertFileExists(__DIR__ . '/../../mod/COMSPEC_ATAK_Native/tools/gen_ui_realism.py');
    }

    public function testTilesUseTintedSquircleAndPressFeedback(): void
    {
        self::assertStringContainsString('class tileCreate {}; class iconTint {};', $this->read('config.cpp'));
        $tint = $this->read('functions/ui/fn_iconTint.sqf');
        self::assertStringContainsString('"COMSPEC_ATAK_IconStyle", "real"', $tint);
        self::assertStringContainsString('"Communication"', $tint);

        $tile = $this->read('functions/ui/fn_tileCreate.sqf');
        self::assertStringContainsString('ui_icon_squircle.paa', $tile);
        self::assertStringContainsString('["color", _page] call comspec_atak_native_fnc_iconTint', $tile);
        self::assertStringContainsString('"MouseButtonDown"', $tile);
        self::assertStringContainsString('forEach ["MouseButtonUp", "MouseExit"]', $tile);
        // Libellé lisible sur le fond d'écran : ombre faite d'une copie noire (shadow du moteur coupé, PR #1119).
        self::assertStringContainsString('_ts ctrlSetTextColor [0, 0, 0,', $tile);

        $settings = $this->read('functions/pages/fn_pageSettings.sqf');
        self::assertStringContainsString('["COMSPEC_ATAK_IconStyle", "real", "Style des icônes"', $settings);
        self::assertStringContainsString('"COMSPEC_ATAK_IconStyle", "COMSPEC_ATAK_PageFx", "COMSPEC_ATAK_OpenSplash", "COMSPEC_ATAK_TitleSlide", "COMSPEC_ATAK_PressFx", "COMSPEC_ATAK_DockApps"', $settings);
    }

    public function testTransitionsNoLongerDrawAnOpaqueVeil(): void
    {
        $fx = $this->read('functions/ui/fn_fx.sqf');
        self::assertStringContainsString('profileNamespace getVariable ["COMSPEC_ATAK_PageFx", true]', $fx);
        self::assertStringNotContainsString('ctrlSetBackgroundColor [0.045, 0.055, 0.048, 1]', $fx);
        self::assertStringContainsString('forEach [_d displayCtrl 88531, [_d] call comspec_atak_native_fnc_mapCtrl]', $fx);
        // La zone de contenu n'est jamais déplacée (son défilement repartirait en haut).
        self::assertStringNotContainsString('(_d displayCtrl 88531) ctrlSetPosition', $fx);
        $settings = $this->read('functions/pages/fn_pageSettings.sqf');
        self::assertStringContainsString('["COMSPEC_ATAK_PageFx", true, "", "Transitions entre les pages"', $settings);
        // Chaque effet a son propre interrupteur (TTRD, 26/10).
        foreach (['OpenSplash', 'TitleSlide', 'PressFx'] as $k) {
            self::assertStringContainsString('["COMSPEC_ATAK_' . $k . '", true, ""', $settings);
            self::assertStringContainsString('"COMSPEC_ATAK_' . $k . '", true]', $fx . $this->read('functions/ui/fn_tileCreate.sqf'));
        }
    }
}
