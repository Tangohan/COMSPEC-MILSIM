<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakSuperPingAssetTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testCommandPostWiresAnimatedSuperPing(): void
    {
        $js = (string) file_get_contents($this->root() . '/public/assets/js/atak-super-ping.js');
        $pings = (string) file_get_contents($this->root() . '/public/assets/js/atak-pings.js');
        $ctx = (string) file_get_contents($this->root() . '/public/assets/js/atak-context-menu.js');
        $map = (string) file_get_contents($this->root() . '/public/assets/js/atak-map.js');
        $css = (string) file_get_contents($this->root() . '/public/assets/css/atak.css');
        $view = (string) file_get_contents($this->root() . '/views/atak.php');

        self::assertStringContainsString('window.ATAKSuperPing', $js);
        self::assertStringContainsString('atak-super-ping__ring', $js);
        self::assertStringContainsString("tag.indexOf('super')", $js);

        self::assertStringContainsString("value: 'super', label: 'Super ping'", $ctx);
        self::assertStringContainsString('data-action="superping"', $ctx);
        self::assertStringContainsString("createPingAt(ll.lng, ll.lat, '', 'super')", $ctx);

        self::assertStringContainsString("super: 'Super ping'", $pings);
        self::assertStringContainsString('data-super', $pings);

        self::assertStringContainsString("kind: 'super', label: 'Super ping'", $map);
        self::assertStringContainsString('ATAKSuperPing.ingest', $map);

        self::assertStringContainsString('@keyframes atak-super-ping-expand', $css);
        self::assertStringContainsString('atak-super-ping.js', $view);
    }

    public function testInGameAtakDrawsSuperPingEllipses(): void
    {
        $root = $this->root();
        $dll = (string) file_get_contents($root . '/mod/UptoDate/COMSPECExtension/Extension.cs');
        $draw = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_superPingDraw.sqf');
        $send = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_superPingSend.sqf');
        $install = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_installReachMap.sqf');
        $cfgC = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp');
        $cfgA = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp');
        $post = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/XEH_postInitClient.sqf');

        self::assertStringContainsString('SimplifyPingsJson', $dll);
        self::assertStringContainsString('function == "GetPings"', $dll);
        self::assertMatchesRegularExpression('/ExtensionVersion = "2\\.0\\.\\d+"/', $dll);
        self::assertStringContainsString('drawEllipse', $draw);
        self::assertStringContainsString('SUPER PING', $draw);
        self::assertStringContainsString('[Super ping]', $send);

        self::assertStringContainsString('superPingSend', $install);
        self::assertStringContainsString('_shift', $install);
        self::assertStringContainsString('class superPingDraw', $cfgC);
        self::assertMatchesRegularExpression('/versionStr = "1\\.5\\.\\d+"/', $cfgC);
        self::assertMatchesRegularExpression('/versionStr = "1\\.0\\.\\d+"/', $cfgA);
        self::assertStringContainsString('superPingInstall', $post);
    }
}
