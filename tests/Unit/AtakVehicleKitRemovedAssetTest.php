<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Kit de terminal de véhicule (COMSPEC_ATAK_VehicleKit) retiré de l'offre en 4.28.0 : il ne servait que si le serveur
 * choisissait « Avec le kit installé » ; par défaut tous les véhicules ont déjà la tablette de bord.
 * La classe reste (tenues, arsenaux et missions enregistrés), cachée et sans effet.
 */
final class AtakVehicleKitRemovedAssetTest extends TestCase
{
    private const MAIN = __DIR__ . '/../../mod/COMSPEC_ATAK_Native/Sources/addons/main';

    private static function read(string $rel): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(self::MAIN . '/' . $rel));
    }

    private static function classBody(string $cfg, string $head): string
    {
        $at = strpos($cfg, $head);
        self::assertNotFalse($at, $head);
        $end = strpos($cfg, "\n    };", (int) $at);
        self::assertNotFalse($end, $head);

        return substr($cfg, (int) $at, (int) $end - (int) $at);
    }

    public function testClassesAreKeptButHidden(): void
    {
        $cfg = self::read('config.cpp');
        $item = self::classBody($cfg, 'class COMSPEC_ATAK_VehicleKit: ItemCore {');
        self::assertStringContainsString('scope=1;', $item);
        self::assertStringContainsString('scopeCurator=0;', $item);
        self::assertStringContainsString('scopeArsenal=0;', $item);
        self::assertStringNotContainsString('scope=2', $item);
        // Le modèle et l'image restent : un kit déjà dans un sac ou une caisse s'affiche encore.
        self::assertStringContainsString('item_vehiclekit.paa', $item);

        $ground = self::classBody($cfg, 'class Item_COMSPEC_ATAK_VehicleKit: Item_Base_F {');
        self::assertStringContainsString('scope=1;', $ground);
        self::assertStringContainsString('scopeCurator=0;', $ground);
        self::assertStringNotContainsString('scope=2', $ground);
        self::assertStringContainsString('name="COMSPEC_ATAK_VehicleKit"', $ground);
    }

    public function testKitIsOutOfEveryList(): void
    {
        $cfg = self::read('config.cpp');
        // Listes d'objets de l'addon (Zeus, Eden) : units[] et weapons[] de CfgPatches.
        self::assertSame(1, preg_match('/units\[\]=\{([^}]*)\}/', $cfg, $m));
        self::assertStringNotContainsString('VehicleKit', $m[1]);
        self::assertStringContainsString('"Item_COMSPEC_ATAK_NvgFilter"', $m[1]);
        self::assertSame(1, preg_match('/weapons\[\]=\{([^}]*)\}/', $cfg, $w));
        self::assertStringNotContainsString('VehicleKit', $w[1]);
        // Plus aucune fonction ne le lit, ne le donne ni ne le consomme.
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::MAIN, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (!in_array($file->getExtension(), ['sqf', 'hpp'], true)) {
                continue;
            }
            $src = (string) file_get_contents($file->getPathname());
            self::assertStringNotContainsString('"COMSPEC_ATAK_VehicleKit"', $src, $file->getFilename());
            self::assertStringNotContainsString('COMSPEC_ATAK_VehKit', $src, $file->getFilename());
            self::assertStringNotContainsString('COMSPEC_ATAK_VehTerm', $src, $file->getFilename());
        }
    }

    public function testNoActionNorSettingOffersIt(): void
    {
        $ace = self::read('functions/shell/fn_aceMenu.sqf');
        self::assertStringNotContainsString('Installer le terminal ATAK', $ace);
        self::assertStringNotContainsString('item_vehiclekit', $ace);
        // La passerelle de véhicule, elle, garde son menu.
        self::assertStringContainsString('"COMSPEC_ATAK_GwExt", "Passerelle ATAK"', $ace);

        $pre = self::read('XEH_preInit.sqf');
        self::assertStringContainsString('[["all", "off"], ["Tous les véhicules", "Aucun"], 0]', $pre);
        self::assertStringNotContainsString('Avec le kit installé', $pre);
        self::assertStringNotContainsString('kit tablette', $pre);
    }

    public function testVehicleTabletStillWorksWithoutTheKit(): void
    {
        $fn = self::read('functions/vehicle/fn_vehicleTerminal.sqf');
        // Ancienne valeur « kit » d'un réglage serveur enregistré : vaut « tous les véhicules », seul « off » coupe.
        self::assertStringContainsString('["", "vehicle"] select (_mode isNotEqualTo "off" && {[_v, _u] call _crew})', $fn);
        self::assertStringContainsString('missionNamespace getVariable ["comspec_atak_native_vehicle_terminal", "all"]', $fn);
        self::assertStringNotContainsString('case "kit"', $fn);
        foreach (['functions/vehicle/fn_vehicleTerminal.sqf', 'functions/shell/fn_aceMenu.sqf'] as $rel) {
            $code = (string) preg_replace('~/\*.*?\*/~s', '', self::read($rel));
            $code = (string) preg_replace('~"(?:[^"]|"")*"~s', '""', $code);
            $code = (string) preg_replace('~//[^\n]*~', '', $code);
            foreach (['{' => '}', '[' => ']', '(' => ')'] as $o => $c) {
                self::assertSame(substr_count($code, $o), substr_count($code, $c), "$rel : $o $c");
            }
            self::assertSame(0, preg_match('/(?<![\w])(systemChat|hint|hintSilent|hintC|cutText|titleText)(?![\w])/', $code), $rel);
        }
    }
}
