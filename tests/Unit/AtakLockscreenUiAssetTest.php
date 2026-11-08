<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Écran de verrouillage, code, fonds d'écran, onglets lisibles, Bloc-notes, Bluetooth et calques de dégâts du téléphone :
 * garde-fous sur les sources et contrastes calculés depuis les jetons de fn_theme.
 */
final class AtakLockscreenUiAssetTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main';
    private const ITEMS = 'mod/COMSPEC_ATAK_Native/Sources/addons/items/data';

    private function read(string $path): string
    {
        $content = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
        self::assertIsString($content, $path);

        return str_replace("\r\n", "\n", $content);
    }

    /** @return array<string, array<int, float>> */
    private function tokens(string $block): array
    {
        preg_match_all('/\["(\w+)", \[([0-9., ]+)\]\]/', $block, $m, PREG_SET_ORDER);
        $out = [];
        foreach ($m as $row) {
            $out[$row[1]] = array_map('floatval', explode(',', $row[2]));
        }

        return $out;
    }

    /** @param array<int, float> $c */
    private function luminance(array $c): float
    {
        $lin = static fn (float $v): float => $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;

        return 0.2126 * $lin($c[0]) + 0.7152 * $lin($c[1]) + 0.0722 * $lin($c[2]);
    }

    /**
     * @param array<int, float> $a
     * @param array<int, float> $b
     */
    private function contrast(array $a, array $b): float
    {
        $l1 = $this->luminance($a);
        $l2 = $this->luminance($b);

        return (max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05);
    }

    public function testThemeTokensStayReadableInAllThemes(): void
    {
        $src = $this->read(self::MAIN . '/functions/ui/fn_theme.sqf');
        $day = (int) strpos($src, 'case "DAY": {');
        $nvg = (int) strpos($src, 'case "NVG": {');
        $dark = (int) strpos($src, 'default {' . "\n" . '            createHashMapFromArray');
        $end = (int) strpos($src, '_th set ["name", _theme];');
        $blocks = [
            'DAY' => substr($src, $day, $nvg - $day),
            'NVG' => substr($src, $nvg, $dark - $nvg),
            'DARK' => substr($src, $dark, $end - $dark),
        ];
        foreach ($blocks as $name => $block) {
            $t = $this->tokens($block);
            self::assertArrayHasKey('text', $t, $name);
            self::assertGreaterThanOrEqual(7.0, $this->contrast($t['text'], $t['surface']), "$name text/surface");
            self::assertGreaterThanOrEqual(7.0, $this->contrast($t['text'], $t['surface2']), "$name text/surface2");
            self::assertGreaterThanOrEqual(4.5, $this->contrast($t['textDim'], $t['surface']), "$name textDim/surface");
            self::assertGreaterThanOrEqual(3.0, $this->contrast($t['textMuted'], $t['bg1']), "$name textMuted/bg1");
        }
    }

    public function testTextOnAccentIsReadableForEveryAccent(): void
    {
        $src = $this->read(self::MAIN . '/functions/ui/fn_accent.sqf');
        preg_match_all('/\[\[([0-9., ]+)\], "#/', $src, $m);
        self::assertNotEmpty($m[1]);
        $onAccent = [0.03, 0.05, 0.04];
        foreach ($m[1] as $accent) {
            $rgb = array_map('floatval', explode(',', $accent));
            self::assertGreaterThanOrEqual(4.5, $this->contrast($onAccent, $rgb), $accent);
        }
    }

    public function testSelectedButtonsDrawTheirBackgroundOnAFace(): void
    {
        self::assertStringContainsString('class COMSPEC_RscButtonFlat: COMSPEC_RscButtonOverlay', $this->read(self::MAIN . '/ui/controls.hpp'));
        $form = $this->read(self::MAIN . '/functions/ui/fn_formRender.sqf');
        self::assertStringContainsString('[_b, _acc] call _face; _b ctrlSetTextColor (_th get "onAccent");', $form);
        self::assertStringNotContainsString('_b ctrlSetBackgroundColor _acc;', $form);
        self::assertStringContainsString('_f ctrlSetBackgroundColor _col;', $this->read(self::MAIN . '/functions/ui/fn_formSet.sqf'));
    }

    public function testLockScreenHasNoFloatingTooltipAndMeasuresItsCards(): void
    {
        $lock = $this->read(self::MAIN . '/functions/ui/fn_lockScreen.sqf');
        self::assertStringNotContainsString('ctrlSetTooltip', $lock);
        self::assertStringNotContainsString('●', $lock, 'Glyphe absent de la police : il décalait le titre.');
        self::assertStringContainsString('ctrlTextHeight _probe', $lock);
        self::assertStringContainsString('COMSPEC_ATAK_LockAfter', $lock);
        self::assertStringContainsString('currentVisionMode player', $lock);
    }

    public function testPinFlowHasNoDefaultCodeAndCountsDown(): void
    {
        $own = $this->read(self::MAIN . '/functions/core/fn_phoneOwn.sqf');
        self::assertStringContainsString('case "pinSetup"', $own);
        self::assertStringContainsString('case "hasPin"', $own);
        self::assertStringContainsString('["count", [_until, "Bloqué pendant %1 s."]]', $own);
        self::assertStringContainsString('profileNamespace getVariable ["COMSPEC_ATAK_LockCode", ""]', $own);
        $pad = $this->read(self::MAIN . '/functions/shell/fn_phonePad.sqf');
        self::assertStringContainsString('"COMSPEC_RscButtonInvisible", [0, 0, _dw, _dh]', $pad);
        self::assertStringContainsString('case "count"', $pad);
        self::assertStringContainsString('["key", _pd]', $this->read(self::MAIN . '/functions/ui/fn_keyBlock.sqf'));
    }

    public function testSiteWallpapersLiveInTheItemsAddon(): void
    {
        $root = dirname(__DIR__, 2) . '/';
        $path = $this->read(self::MAIN . '/functions/ui/fn_wallPath.sqf');
        foreach (['eagle', 'logo', 'atak', 'sse', 'sof'] as $key) {
            self::assertStringContainsString('"' . $key . '"', $path);
            foreach (['land', 'port', 'blur_land', 'blur_port'] as $suffix) {
                self::assertFileExists($root . self::ITEMS . "/walls/wall_{$key}_{$suffix}.paa");
            }
        }
        self::assertStringContainsString('addons\items\data\walls', $path);
    }

    public function testBootImagesAndDedicatedIconsExist(): void
    {
        $root = dirname(__DIR__, 2) . '/' . self::MAIN . '/data/';
        foreach (['boot_land', 'boot_port', 'app_tenant', 'item_keyloader', 'item_goniometer'] as $name) {
            self::assertFileExists($root . $name . '.paa');
        }
        self::assertStringContainsString('"boot" call _tex', $this->read(self::MAIN . '/functions/ui/fn_deviceOverlay.sqf'));
        $config = $this->read(self::MAIN . '/config.cpp');
        self::assertStringContainsString('class Tenant', $config);
        self::assertMatchesRegularExpression('/class Tenant[^\n]*app_tenant\.paa/', $config);
        self::assertMatchesRegularExpression('/class COMSPEC_ATAK_KeyLoader: ItemCore \{.*?item_keyloader\.paa/s', $config);
        self::assertMatchesRegularExpression('/class COMSPEC_ATAK_Goniometer: ItemCore \{.*?item_goniometer\.paa/s', $config);
    }

    public function testNotesListOffersFourVisibleActionsWithoutTouchingStorage(): void
    {
        $page = $this->read(self::MAIN . '/functions/pages/fn_pageNotes.sqf');
        foreach (['"OUVRIR"', '"ÉDITER"', '"PARTAGER"', '"SUPPRIMER"'] as $label) {
            self::assertStringContainsString($label, $page);
        }
        $act = $this->read(self::MAIN . '/functions/pages/fn_notesAction.sqf');
        self::assertStringContainsString('case "ask"', $act);
        self::assertStringContainsString('case "share"', $act);
        self::assertStringContainsString('_list pushBack [_nid, _title, _body, call _stamp, "", false];', $act);
    }

    public function testBluetoothUpdatesInPlaceAndExplainsAnEmptyList(): void
    {
        $bt = $this->read(self::MAIN . '/functions/bluetooth/fn_btAction.sqf');
        self::assertStringContainsString('case "scan"', $bt);
        self::assertStringContainsString('case "near"', $bt);
        self::assertStringContainsString('"btx_" + _id', $bt);
        $page = $this->read(self::MAIN . '/functions/bluetooth/fn_pageBluetooth.sqf');
        self::assertStringContainsString('Personne à portée', $page);
        self::assertStringContainsString('"btn_" + _uid', $page);
    }

    public function testDamageLayersArePickedStoredAndRepaired(): void
    {
        $root = dirname(__DIR__, 2) . '/' . self::ITEMS . '/dmg/';
        $n = 0;
        foreach (['g_bullet' => 3, 'g_web' => 2, 'g_corner' => 2, 'g_chips' => 2, 'g_scratch' => 3, 'l_vlines' => 2, 'l_hlines' => 2, 'l_band' => 2, 'l_pixels' => 2, 'l_ink' => 3] as $name => $variants) {
            for ($v = 0; $v < $variants; $v++) {
                self::assertFileExists($root . "{$name}_{$v}_port.paa");
                self::assertFileExists($root . "{$name}_{$v}_land.paa");
                $n++;
            }
        }
        self::assertGreaterThanOrEqual(15, $n);
        self::assertStringContainsString('_n set ["fx", _fxList];', $this->read(self::MAIN . '/functions/core/fn_deviceDamage.sqf'));
        self::assertStringContainsString('_n deleteAt "fx";', $this->read(self::MAIN . '/functions/core/fn_deviceRepair.sqf'));
        $fx = $this->read(self::MAIN . '/functions/ui/fn_screenFx.sqf');
        self::assertStringContainsString('COMSPEC_ATAK_CrackFx', $fx);
        self::assertStringContainsString('COMSPEC_ATAK_DmgPreview', $fx);
    }

    public function testNewStringsAreTranslated(): void
    {
        $en = $this->read(self::MAIN . '/i18n/en.txt');
        foreach (['DÉVERROUILLER', 'Nouveau code', 'Téléphones détectés', 'Calques de dégâts', 'Lire la note'] as $text) {
            self::assertStringContainsString('["' . $text . '",', $en);
        }
    }
}
