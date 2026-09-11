<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakNativeIdentityAssetTest extends TestCase
{
    public function testNativeClientHasIndependentModAndDllIdentity(): void
    {
        $root = dirname(__DIR__, 2);
        $mod = $root . '/mod/COMSPEC_ATAK_Native';
        $config = (string) file_get_contents($mod . '/Sources/addons/main/config.cpp');
        $prefix = trim((string) file_get_contents($mod . '/Sources/addons/main/$PBOPREFIX$'));
        $sqf = (string) file_get_contents($mod . '/Sources/addons/main/functions/network/fn_extensionCall.sqf');
        $project = (string) file_get_contents($mod . '/COMSPECATAKNativeExtension/COMSPECATAKNativeExtension.csproj');
        $extension = (string) file_get_contents($root . '/mod/UptoDate/COMSPECExtension/Extension.cs');
        $controller = (string) file_get_contents($root . '/app/Controllers/Api/AtakApiController.php');
        $transmissions = (string) file_get_contents($root . '/public/assets/js/atak-transmissions.js');
        $atakView = (string) file_get_contents($root . '/views/atak.php');

        self::assertStringContainsString('class comspec_atak_native_main', $config);
        self::assertStringNotContainsString('comspec_overwatch_connect', $config);
        self::assertSame('z\\comspec_atak_native\\addons\\main', $prefix);
        self::assertStringContainsString('"COMSPECATAKNativeExtension" callExtension', $sqf);
        self::assertStringContainsString('<AssemblyName>COMSPECATAKNativeExtension_x64</AssemblyName>', $project);
        self::assertStringContainsString('COMSPEC_ATAK_NATIVE', $project);
        self::assertStringContainsString('ClientProduct = "comspec_atak_native"', $extension);
        self::assertStringContainsString('writer.WriteString("client_product", ClientProduct)', $extension);
        self::assertStringContainsString("'has_atak_native'", $controller);
        self::assertStringContainsString("'atak_native' =>", $controller);
        self::assertStringContainsString("'site', 'atak_native', 'athena'", $transmissions);
        self::assertStringContainsString('id="atak-tx-atak_native"', $atakView);
        self::assertStringContainsString('id="health-tx-atak_native"', $atakView);
    }
}
