<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Les objets d'appui du mod (supports invisibles des relais et des dalles TOC, nœuds simulés du maillage) ne doivent pas
 * apparaître dans la liste d'objets éditables du Zeus (retour de TTRD : « héliports invisibles », « sphères »).
 */
final class AtakZeusHideAssetTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';

    private function read(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path);
    }

    public function testHelperRemovesFromEveryCuratorAndSweeps(): void
    {
        $fn = $this->read(self::MAIN . 'functions/zeus/fn_zeusHide.sqf');

        self::assertStringContainsString('removeCuratorEditableObjects', $fn);
        self::assertStringContainsString('forEach allCurators', $fn);
        self::assertStringContainsString('curatorEditableObjects', $fn);
        // Sur un client : demande au serveur, jamais d'écriture locale.
        self::assertStringContainsString('CBA_fnc_serverEvent', $fn);
        self::assertStringContainsString('COMSPEC_ATAK_ZeusHidden', $fn);
        self::assertStringContainsString('> 400', $fn, 'la liste est bornée');
    }

    public function testEveryCreationSiteHidesItsObject(): void
    {
        $sites = [
            'functions/network/fn_relayConvert.sqf' => '["add", [_pr]]',
            'functions/toc/fn_tocScreen.sqf' => '["add", [_rv]]',
            'functions/devmesh/fn_devMeshSim.sqf' => '["add", [_o]]',
        ];
        foreach ($sites as $file => $call) {
            $src = $this->read(self::MAIN . $file);
            self::assertStringContainsString($call . ' call comspec_atak_native_fnc_zeusHide;', $src, $file);
        }
    }

    public function testFunctionIsDeclaredAndServerWiresEventAndSweep(): void
    {
        self::assertStringContainsString('class zeusHide {};', $this->read(self::MAIN . 'config.cpp'));
        $server = $this->read(self::MAIN . 'XEH_postInitServer.sqf');
        self::assertStringContainsString('["comspec_atak_native_zeusHide"', $server);
        self::assertStringContainsString('["sweep"] call comspec_atak_native_fnc_zeusHide', $server);
        self::assertFileExists(dirname(__DIR__, 2) . '/' . self::MAIN . 'functions/zeus/fn_zeusHide.sqf');
    }
}
