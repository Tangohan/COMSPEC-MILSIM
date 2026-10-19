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
        // On cible le handler GetWardrobe lui-même (un commentaire de cache cite « évite ERR|too_large »).
        $start = strpos($ext, 'if (function == "GetWardrobe" && ');
        $end = strpos($ext, 'if (function == "GetWardrobeChunk" && ');
        self::assertNotFalse($start);
        self::assertNotFalse($end);
        self::assertGreaterThan($start, $end);
        $getWardrobe = substr($ext, $start, $end - $start);
        self::assertStringNotContainsString('ERR|too_large', $getWardrobe);
        self::assertStringContainsString('OK|CHUNKED', $getWardrobe);

        self::assertStringContainsString('CHUNKED', $cloud);
        self::assertStringContainsString('GetWardrobeChunk', $cloud);
        self::assertStringContainsString('COMSPEC_ArsenalCloudLoadoutError', $cloud);

        self::assertStringContainsString('ListWardrobes', $list);
        self::assertStringContainsString('NEXT', $list);
        self::assertStringContainsString('class arsenalListWardrobes', $cfg);

        self::assertStringContainsString('JVN', $icons);
        self::assertStringContainsString('Contenu gilet', $icons);
        // Les groupes repliables (_fnc_initCollapsed) ont laissé place au cache de liste (90 s) avant redessin.
        self::assertStringContainsString('COMSPEC_ArsenalWardrobeLines', $refresh);
        self::assertStringContainsString('comspec_overwatch_connect_fnc_arsenalOverlayFill', $refresh);
        self::assertStringContainsString('arsenalListWardrobes', $refresh);
        \Tests\Support\ModVersion::assertAtLeast('1.5.0', \Tests\Support\ModVersion::fromConfigCpp($cfg));
    }
}
