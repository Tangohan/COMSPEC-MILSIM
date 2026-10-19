<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\Support\ModVersion;

final class AtakRelayAtAssetTest extends TestCase
{
    public function testRelaisAtReplacesWaveRelayAndExposesFiche(): void
    {
        $root = dirname(__DIR__, 2);
        $cfgA = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp');
        $cfgC = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp');
        $page = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/ui/relay_page.hpp');
        $update = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updateRelay.sqf');
        $nearest = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_getNearestAtakRelay.sqf');
        $eden = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/modules/module_atak_relay.hpp')
            . (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/stringtable.xml');
        $js = (string) file_get_contents($root . '/public/assets/js/atak-overwatch-ops.js');
        $repo = (string) file_get_contents($root . '/app/Repositories/AtakRelayRepository.php');
        $view = (string) file_get_contents($root . '/views/admin/atak/server_control.php');
        $ext = (string) file_get_contents($root . '/mod/UptoDate/COMSPECExtension/Extension.cs');

        ModVersion::assertAtLeast('1.0.165', ModVersion::fromConfigCpp($cfgA));
        self::assertStringContainsString('canSetArea = 0;', $eden);
        ModVersion::assertAtLeast('1.6.9', ModVersion::fromConfigCpp($cfgC));
        self::assertStringContainsString('filterDrawerApps', $cfgA);
        self::assertStringContainsString('class AtakRelay: message', $cfgA);
        self::assertStringContainsString('ORDER = 0.08;', $cfgA);
        self::assertStringContainsString('text = "<t size=\'1\'>Relais AT</t>"', $cfgA);
        self::assertStringContainsString('PAGE_CTRL = "COMSPEC_ATAK_Relay"', $cfgA);
        self::assertStringContainsString('class COMSPEC_ATAK_Relay', $page);
        self::assertStringContainsString('Adresse réseau', $update);
        self::assertStringContainsString('getNearestAtakRelay', $update);
        self::assertStringContainsString('throughput_mbps', $nearest);
        self::assertStringContainsString('Tutoriel Eden', $eden);
        self::assertStringContainsString('RelayCertificate', $eden);
        self::assertStringContainsString('relayPopupHtml', $js);
        self::assertStringContainsString('Adresse réseau', $js);
        self::assertStringContainsString('display_name', $repo);
        self::assertStringContainsString('ip_addr', $repo);
        self::assertStringContainsString('adresse réseau', $view);
        self::assertStringNotContainsString('endpoint', strtolower($view));
        self::assertStringContainsString('args.Length > 6', $ext);
        ModVersion::assertAtLeast('2.0.52', ModVersion::match('/ExtensionVersion\s*=\s*"([0-9.]+)"/', $ext));
    }
}
