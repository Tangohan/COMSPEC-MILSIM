<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Masquer le calque des zones sur l'ATAK ne doit jamais modifier les marqueurs Arma (alpha, suppression) :
 * on ne fait que ne plus les dessiner, donc la carte d'Arma reste intacte et la réactivation est fidèle.
 */
final class AtakZoneLayerNonDestructiveAssetTest extends TestCase
{
    private function src(string $rel): string
    {
        $dir = dirname(__DIR__, 2) . '/mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/map/';

        return (string) file_get_contents($dir . $rel);
    }

    public function testLayerToggleNeverMutatesMarkers(): void
    {
        foreach (['fn_mapToolMenu.sqf', 'fn_mapOnDraw.sqf'] as $file) {
            $code = $this->src($file);
            self::assertStringNotContainsString('setMarkerAlphaLocal', $code, $file);
            self::assertStringNotContainsString('COMSPEC_ATAK_ZonesHideAt', $code, $file);
            self::assertStringNotContainsString('COMSPEC_ATAK_ZonesHideRev', $code, $file);
        }
        // Le bloc ZONES de la bascule ne contient aucun appel destructif.
        $menu = $this->src('fn_mapToolMenu.sqf');
        $start = strpos($menu, 'case "ZONES"');
        $end = strpos($menu, 'case "SEARCH"');
        self::assertNotFalse($start);
        self::assertNotFalse($end);
        $block = substr($menu, (int) $start, (int) $end - (int) $start);
        self::assertDoesNotMatchRegularExpression('/deleteMarker|setMarker\w*|allMapMarkers/', $block);
    }

    public function testHiddenLayerIsOnlyADrawFilter(): void
    {
        $gm = $this->src('fn_mapGameMarkers.sqf');
        self::assertStringContainsString('COMSPEC_ATAK_ZonesLayer', $gm);
        self::assertStringContainsString('!_zonesOn && {(_x select [0, 11]) isEqualTo "COMSPEC_TZ_"}', $gm);
        // Le cache est relu à la bascule, sinon la réactivation lirait une liste périmée.
        self::assertStringContainsString('_cache param [5, true]) isNotEqualTo _zonesOn', $gm);
        self::assertStringContainsString('_tac, _zonesOn]', $gm);
        self::assertDoesNotMatchRegularExpression('/setMarker\w*Local\s+0|deleteMarker/', $gm);

        $txt = $this->src('fn_mapMarkerText.sqf');
        self::assertStringContainsString('!_zonesOn && {(_id select [0, 11]) isEqualTo "COMSPEC_TZ_"}', $txt);
    }

    public function testVersionBumpedAsPatch(): void
    {
        $hpp = (string) file_get_contents(dirname(__DIR__, 2) . '/mod/COMSPEC_ATAK_Native/Sources/addons/main/script_component.hpp');
        self::assertStringContainsString('#define VERSION_STR "4.22.0"', $hpp);
        self::assertStringContainsString('#define VERSION_NAME "4.22.0.AG - Washington"', $hpp);
        $log = (string) file_get_contents(dirname(__DIR__, 2) . '/CHANGELOG-ATAK.md');
        self::assertStringContainsString('(ATAK natif 4.21.1.AG - Washington)', $log);
    }
}
