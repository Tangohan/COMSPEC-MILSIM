<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakMarkerIconPathTest extends TestCase
{
    public function testVanillaMilitaryTextureMapsToPngTree(): void
    {
        self::assertSame(
            'a3/ui_f/data/map/markers/military/warning_ca.png',
            atak_marker_icon_relpath('\\A3\\ui_f\\data\\map\\markers\\military\\warning_CA.paa')
        );
    }

    public function testCtabTextureKeepsCtabFolder(): void
    {
        self::assertSame(
            'ctab/img/o_inf_rifle.png',
            atak_marker_icon_relpath('\\cTab\\img\\o_inf_rifle.paa')
        );
    }

    public function testIcemanAndNlnPrefixesRewriteToCtab(): void
    {
        self::assertSame(
            'ctab/img/o_inf_rifle.png',
            atak_marker_icon_relpath('\\iceman\\img\\o_inf_rifle.paa')
        );
        self::assertSame(
            'ctab/img/o_inf_at.png',
            atak_marker_icon_relpath('\\nln_ctab\\img\\o_inf_at.paa')
        );
    }

    public function testTypeFallbackForVanillaAndMarkersPlus(): void
    {
        self::assertSame(
            'a3/ui_f/data/map/markers/military/warning_ca.png',
            atak_marker_icon_relpath_from_type('mil_warning')
        );
        self::assertSame(
            'a3/ui_f/data/map/markers/nato/b_inf.png',
            atak_marker_icon_relpath_from_type('b_inf')
        );
        self::assertSame(
            'markersplus/data/img/ambush.png',
            atak_marker_icon_relpath_from_type('mplus_ambush')
        );
    }

    public function testProceduralTextureIsIgnored(): void
    {
        self::assertNull(atak_marker_icon_relpath('#(argb,8,8,3)color(1,0,0,1)'));
    }
}
