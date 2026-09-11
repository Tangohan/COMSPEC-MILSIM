<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ArsenalWardrobeChunkAssetTest extends TestCase
{
    public function testWardrobeDetailSupportsChunkingAndListPagination(): void
    {
        $root = dirname(__DIR__, 2);
        $ext = (string) file_get_contents($root . '/mod/UptoDate/COMSPECExtension/Extension.cs');
        $cloud = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_arsenalCloudLoadout.sqf'
        );
        $list = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_arsenalListWardrobes.sqf'
        );
        $icons = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_arsenalLoadoutIcons.sqf'
        );
        $refresh = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_arsenalOverlayRefresh.sqf'
        );
        $cfg = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp'
        );

        self::assertStringContainsString('GetWardrobeChunk', $ext);
        self::assertStringContainsString('FormatWardrobesListPage', $ext);
        self::assertStringContainsString('OK|NEXT', $ext);
        self::assertStringContainsString('CHUNKED', $ext);
        // Plus de hard-fail sur le détail (chunking à la place).
        self::assertDoesNotMatchRegularExpression('/GetWardrobe[\s\S]{0,800}ERR\|too_large/', $ext);

        self::assertStringContainsString('CHUNKED', $cloud);
        self::assertStringContainsString('GetWardrobeChunk', $cloud);
        self::assertStringContainsString('COMSPEC_ArsenalCloudLoadoutError', $cloud);

        self::assertStringContainsString('ListWardrobes', $list);
        self::assertStringContainsString('NEXT', $list);
        self::assertStringContainsString('class arsenalListWardrobes', $cfg);

        self::assertStringContainsString('JVN', $icons);
        self::assertStringContainsString('Contenu gilet', $icons);
        self::assertStringContainsString('_fnc_initCollapsed', $refresh);
        self::assertStringContainsString('arsenalListWardrobes', $refresh);
        self::assertStringContainsString('1.5.37', $cfg);
    }
}
