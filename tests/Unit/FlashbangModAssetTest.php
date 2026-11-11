<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Mod COMSPEC Flashbang : cohérence des variantes (standard, délai 3 s / 8 s, DRILL), des noms courts, des sons
 * au choix de la DRILL et des messages réseau (événements CBA, pas de remoteExec).
 */
final class FlashbangModAssetTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_Flashbang/Sources/addons/main/';
    private const BASES = ['1', '2', '3', '4', '5', '6', '7', '8', '9', 'Drill'];

    private static function path(string $p): string
    {
        return dirname(__DIR__, 2) . '/' . $p;
    }

    private static function read(string $p): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(self::path($p)));
    }

    /** @return array<string, array{en: string, fr: string}> */
    private static function strings(): array
    {
        $xml = simplexml_load_string(self::read(self::MAIN . 'stringtable.xml'));
        self::assertNotFalse($xml, 'stringtable.xml doit être un XML valide.');
        $out = [];
        foreach ($xml->xpath('//Key') as $k) {
            $id = (string) $k['ID'];
            self::assertArrayNotHasKey($id, $out, "Clé en double : {$id}");
            $out[$id] = ['en' => (string) $k->English, 'fr' => (string) $k->French];
        }

        return $out;
    }

    /** Toutes les variantes : [classe du magasin, munition, fichier, délai]. @return list<array{string, string, string, int}> */
    private static function variants(): array
    {
        $v = [];
        foreach (self::BASES as $b) {
            $file = strtolower($b);
            $mag = $b === 'Drill' ? 'COMSPEC_FB_Drill' : "COMSPEC_FB_Flash_{$b}";
            $v[] = [$mag, "COMSPEC_FB_Ammo_{$b}", $file, 0];
            foreach ([3, 8] as $d) {
                $v[] = ["{$mag}_D{$d}", "COMSPEC_FB_Ammo_{$b}_D{$d}", "{$file}_d{$d}", $d];
            }
        }

        return $v;
    }

    public function testVariantesGenereesParMacrosPourChaqueBase(): void
    {
        $ammo = self::read(self::MAIN . 'CfgAmmo.hpp');
        $mags = self::read(self::MAIN . 'CfgMagazines.hpp');
        $weap = self::read(self::MAIN . 'CfgWeapons.hpp');
        $dev = self::read(self::MAIN . 'CfgVehicles_devices.hpp');
        $cfg = self::read(self::MAIN . 'config.cpp');
        foreach (self::BASES as $b) {
            $f = strtolower($b);
            $mag = $b === 'Drill' ? 'Drill' : "Flash_{$b}";
            self::assertStringContainsString("FB_AMMO_DELAYS({$b},{$f});", $ammo);
            self::assertStringContainsString("FB_MAG_DELAYS({$mag},{$b},{$f});", $mags);
            self::assertStringContainsString("FB_MUZZLE_DELAYS({$b},{$mag});", $weap);
            self::assertStringContainsString("FB_MUZZLE_NAMES({$b})", $weap);
            self::assertStringContainsString("FB_DEVICE_DELAYS({$b},{$f});", $dev);
            self::assertStringContainsString('FB_D2(COMSPEC_FB_Ammo_,' . $b . ')', $cfg);
            self::assertStringContainsString($b === 'Drill' ? 'FB_D2(COMSPEC_FB_,Drill)' : "FB_D2(COMSPEC_FB_Flash_,{$b})", $cfg);
        }
        // Mèche fixe des variantes : 3 et 8 s, durée de vie du projectile au-delà (mèche + 7 s).
        self::assertStringContainsString('#define FB_AMMO_DELAYS(V,F) FB_AMMO_DELAY(V,F,3,10); FB_AMMO_DELAY(V,F,8,15)', $ammo);
        self::assertStringContainsString('comspec_fb_fuse = D;', $ammo);
        self::assertStringContainsString('comspec_fb_fuse = -1;', $ammo, 'Variantes standard : réglage « Mèche ».');
        self::assertMatchesRegularExpression('/timeToLive = 6;/', $ammo, 'Mèche réglable 4 s max < 6 s de vie.');
        self::assertStringContainsString('class COMSPEC_FB_Ammo_Base: SmokeShell', $ammo, 'shotSmokeX : jamais de détonation d\'impact.');
        self::assertStringContainsString('explosionTime = 30;', $ammo);
        self::assertStringContainsString('muzzles[] += {', $weap, 'Ajout cumulatif aux muzzles de Throw.');
    }

    public function testFichiersEtTextesDeChaqueVariante(): void
    {
        $s = self::strings();
        $box = self::read(self::MAIN . 'CfgVehicles_box.hpp') . self::read(self::MAIN . 'CfgVehicles_boxDelay.hpp');
        $modelCfg = self::read(self::MAIN . 'data/model.cfg');
        $seen = [];
        foreach (self::variants() as [$mag, $ammo, $file, $d]) {
            self::assertArrayNotHasKey($mag, $seen, "Classe en double : {$mag}");
            $seen[$mag] = true;
            foreach (["data/fb_{$file}.p3d", "data/fb_{$file}_spent.p3d", "data/fb_body_{$file}_co.paa", "ui/fb_icon_{$file}_ca.paa"] as $f) {
                self::assertFileExists(self::path(self::MAIN . $f), "{$mag} : {$f}");
            }
            self::assertStringContainsString("class fb_{$file}:", $modelCfg);
            $key = 'STR_' . $mag;
            foreach (['_Name', '_Short', '_Desc'] as $suffix) {
                self::assertArrayHasKey($key . $suffix, $s, "Texte manquant : {$key}{$suffix}");
                self::assertNotSame('', $s[$key . $suffix]['fr']);
                self::assertNotSame('', $s[$key . $suffix]['en']);
            }
            self::assertStringContainsString("magazine = \"{$mag}\";", $box, "{$mag} absent des caisses");
            if ($d > 0) {
                self::assertStringContainsString("{$d} s", $s[$key . '_Name']['fr']);
                self::assertStringContainsString("{$d} s", $s[$key . '_Desc']['fr']);
            }
        }
        self::assertCount(30, $seen, '10 standard + 20 à délai.');
    }

    public function testNomsCourtsDistinctsEtValeurQuiDistingueEnPremier(): void
    {
        $s = self::strings();
        $names = [];
        foreach (self::variants() as [$mag]) {
            $names[] = 'STR_' . $mag . '_Name';
        }
        $others = ['STR_COMSPEC_FB_Box', 'STR_COMSPEC_FB_Box_Delay', 'STR_COMSPEC_FB_Device', 'STR_COMSPEC_FB_Muzzle',
            'STR_COMSPEC_FB_Module', 'STR_COMSPEC_FB_Zen_Throw', 'STR_COMSPEC_FB_Zen_Area'];
        foreach (['fr', 'en'] as $lang) {
            $vals = [];
            foreach (array_merge($names, $others) as $k) {
                $v = $s[$k][$lang];
                self::assertLessThanOrEqual(16, mb_strlen($v), "{$k} ({$lang}) trop long : « {$v} »");
                $vals[] = $v;
            }
            self::assertSame(count($vals), count(array_unique($vals)), "Noms en double ({$lang})");
            foreach ($names as $k) {
                self::assertDoesNotMatchRegularExpression('/^Grenade/u', $s[$k][$lang], 'Pas de mot long en tête.');
            }
        }
    }

    public function testSonsDeLaDrillPointentVersDesFichiers(): void
    {
        $sons = self::read(self::MAIN . 'CfgSons.hpp');
        $s = self::strings();
        self::assertSame(1, preg_match('/drillChoix\[\] = \{([^}]*)\};/', $sons, $m));
        $keys = array_map(static fn (string $k): string => trim($k, " \""), explode(',', $m[1]));
        self::assertSame(['Pop', 'PopSourd', 'BangLeger', 'BangReel', 'Rafale', 'Sifflet', 'Cloche', 'Aucun'], $keys);
        $labels = [];
        foreach ($keys as $k) {
            self::assertArrayHasKey("STR_COMSPEC_FB_DrillSound_{$k}", $s);
            $labels[] = $s["STR_COMSPEC_FB_DrillSound_{$k}"]['fr'];
            if ($k === 'Aucun') {
                self::assertStringNotContainsString('class Drill_Aucun', $sons);
                continue;
            }
            self::assertSame(1, preg_match('/class Drill_' . $k . ' \{\s*fichiers\[\] = \{([^}]*)\};/', $sons, $f), "Entrée Drill_{$k}");
            foreach (explode(',', $f[1]) as $file) {
                self::assertFileExists(self::path(self::MAIN . 'sounds/' . trim($file, " \"")));
            }
        }
        self::assertSame(count($labels), count(array_unique($labels)));
        foreach (['Set_DrillSound', 'Set_DrillVolume', 'Module_DrillSound', 'Module_FuseMode', 'Module_FuseCustom', 'Set_Debug'] as $k) {
            foreach (['fr' => 'Ex', 'en' => 'Ex'] as $lang => $ex) {
                self::assertStringContainsString($ex, $s["STR_COMSPEC_FB_{$k}_Tip"][$lang], "Exemple manquant : {$k} ({$lang})");
            }
        }
    }

    public function testMessagesReseauParEvenementsCbaEtJournal(): void
    {
        $all = '';
        foreach (glob(self::path(self::MAIN . 'functions/*.sqf')) ?: [] as $f) {
            $all .= (string) file_get_contents($f);
        }
        self::assertDoesNotMatchRegularExpression('/remoteExec(Call)?\s*\[/', $all, 'Événements CBA : indépendants des listes blanches CfgRemoteExec.');
        self::assertFileDoesNotExist(self::path(self::MAIN . 'CfgRemoteExec.hpp'));
        $pre = self::read(self::MAIN . 'XEH_preInit.sqf');
        foreach (['comspec_fb_serverDetonate', 'comspec_fb_serverArea', 'comspec_fb_timeline', 'comspec_fb_cancel', 'comspec_fb_aiRestore'] as $ev) {
            self::assertStringContainsString("[\"{$ev}\",", $pre);
        }
        self::assertStringContainsString('[COMSPEC FB] chargé', self::read(self::MAIN . 'XEH_postInit.sqf'));
        self::assertStringContainsString('[COMSPEC FB]', self::read(self::MAIN . 'functions/fn_log.sqf'));
        $fired = self::read(self::MAIN . 'functions/fn_fired.sqf');
        self::assertStringContainsString('getNumber (_cfg >> "comspec_fb_fuse")', $fired);
        self::assertStringContainsString('CBA_missionTime + _fuse', $fired, 'Heure de la 1re détonation fixée au lancer.');
    }
}
