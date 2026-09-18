<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class IcemanBceClampAssetTest extends TestCase
{
    public function testIceManSpringRefusesTinyWidth(): void
    {
        $root = dirname(__DIR__, 2);
        $base = $root . '/mod/UptoDate/Sources/iceman-bce-clamp/functions';
        $spring = (string) file_get_contents($base . '/fnc_handler_spring.sqf');
        $ext = (string) file_get_contents($base . '/fnc_handler_springextension.sqf');
        $type = (string) file_get_contents($base . '/fn_anim_type.sqf');
        $offset = (string) file_get_contents($base . '/fn_anim_customoffset.sqf');
        $readme = (string) file_get_contents($root . '/mod/UptoDate/Sources/iceman-bce-clamp/README.md');
        $bug = (string) file_get_contents($root . '/docs/bugs/2026-09-17-atak-crash-iceman-ressort.md');

        self::assertStringContainsString('_vecPos set [2, 0.001]', $spring);
        self::assertStringContainsString('_vecPos set [3, 0.001]', $spring);
        self::assertStringContainsString('_vecPos set [2, 0.001]', $ext);
        self::assertStringContainsString('ctrlSetPositionW', $type);
        self::assertStringContainsString('_p set [2, 0.001]', $type);
        self::assertStringContainsString('_result set [2, 0.001]', $offset);
        self::assertStringContainsString('pack FN', $readme);
        self::assertStringContainsString('IceMan', $bug);
        self::assertStringContainsString('tout seul', strtolower($bug));
        self::assertStringNotContainsString('endpoint', $bug);
        self::assertStringNotContainsString('endpoint', $readme);
    }
}
