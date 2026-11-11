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
    private const BASES = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '50', 'Drill'];
    private const FAST = ['2', '3', '4', '5', '6', '7', '8', '9', '50'];
    /** BANG spéciaux : classe => [fichier du modèle, nombre de flashs, nom affiché]. */
    private const THEMES = [
        'Japan' => ['japan', 3, 'JAPAN BANG'],
        'Women' => ['women', 3, 'WOMEN BANG'],
        'Surprise' => ['surprise', 5, 'SUPRISE BANG'],
        'German' => ['german', 9, 'GERMAN BANG'],
    ];

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
        // Variantes R (rafale hyper rapide) : même modèle que la grenade standard, icône propre.
        foreach (self::FAST as $n) {
            $v[] = ["COMSPEC_FB_Flash_{$n}_R", "COMSPEC_FB_Ammo_{$n}_R", $n, -1];
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
            $icon = $d < 0 ? "ui/fb_icon_{$file}_r_ca.paa" : "ui/fb_icon_{$file}_ca.paa";
            foreach (["data/fb_{$file}.p3d", "data/fb_{$file}_spent.p3d", "data/fb_body_{$file}_co.paa", $icon] as $f) {
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
                self::assertStringEndsWith(" {$d}s", $s[$key . '_Name']['fr']);
                self::assertStringContainsString("{$d} s", $s[$key . '_Desc']['fr']);
            }
        }
        self::assertCount(42, $seen, '11 standard (1-9, 50, Drill) + 22 à délai + 9 rafales R.');
    }

    public function testNomsCourtsDistinctsEtValeurQuiDistingueEnPremier(): void
    {
        $s = self::strings();
        $names = [];
        foreach (self::variants() as [$mag]) {
            $names[] = 'STR_' . $mag . '_Name';
        }
        foreach (array_keys(self::THEMES) as $theme) {
            $names[] = "STR_COMSPEC_FB_{$theme}_Name";
        }
        $others = ['STR_COMSPEC_FB_Box', 'STR_COMSPEC_FB_Box_Delay', 'STR_COMSPEC_FB_Box_Special', 'STR_COMSPEC_FB_Device', 'STR_COMSPEC_FB_Muzzle',
            'STR_COMSPEC_FB_Module', 'STR_COMSPEC_FB_Zen_Throw', 'STR_COMSPEC_FB_Zen_Area'];
        foreach (['fr', 'en'] as $lang) {
            $vals = [];
            foreach (array_merge($names, $others) as $k) {
                $v = $s[$k][$lang];
                // Grenades : préfixe « [AT.NA] » (tri groupé dans l'Arsenal), colonne d'inventaire coupée vers 21 caractères.
                $max = in_array($k, $names, true) || $k === 'STR_COMSPEC_FB_Box_Special' ? 21 : 16;
                self::assertLessThanOrEqual($max, mb_strlen($v), "{$k} ({$lang}) trop long : « {$v} »");
                $vals[] = $v;
            }
            self::assertSame(count($vals), count(array_unique($vals)), "Noms en double ({$lang})");
            foreach ($names as $k) {
                self::assertDoesNotMatchRegularExpression('/^Grenade/u', $s[$k][$lang], 'Pas de mot long en tête.');
                self::assertStringStartsWith('[AT.NA] ', $s[$k][$lang], 'Préfixe [AT.NA] sur toutes les grenades.');
            }
            foreach ($others as $k) {
                self::assertStringNotContainsString('[AT.NA]', $s[$k][$lang], 'Préfixe réservé aux grenades.');
            }
            foreach (self::variants() as [$mag]) {
                self::assertStringContainsString('BetterFlash', $s['STR_' . $mag . '_Desc'][$lang], 'Marque dans l\'info-bulle.');
            }
        }
    }

    public function testBangSpeciauxClassesSonsEtModeles(): void
    {
        $s = self::strings();
        $ammo = self::read(self::MAIN . 'CfgAmmo.hpp');
        $mags = self::read(self::MAIN . 'CfgMagazines.hpp');
        $weap = self::read(self::MAIN . 'CfgWeapons.hpp');
        $dev = self::read(self::MAIN . 'CfgVehicles_devices.hpp');
        $veh = self::read(self::MAIN . 'CfgVehicles.hpp');
        $cfg = self::read(self::MAIN . 'config.cpp');
        $sons = self::read(self::MAIN . 'CfgSons.hpp');
        $box = self::read(self::MAIN . 'CfgVehicles_box.hpp');
        $special = self::read(self::MAIN . 'CfgVehicles_boxSpecial.hpp');
        $modelCfg = self::read(self::MAIN . 'data/model.cfg');
        $zen = self::read(self::MAIN . 'functions/fn_zenRegister.sqf');
        foreach (self::THEMES as $theme => [$file, $n, $shown]) {
            // Classes : munition, magasin, muzzle (dans le cycle de Throw), engin, CfgPatches.
            self::assertStringContainsString("FB_AMMO_THEME({$theme},{$file},{$n});", $ammo);
            self::assertStringContainsString("FB_MAG_THEME({$theme},{$file});", $mags);
            self::assertStringContainsString("class COMSPEC_FB_Muzzle_{$theme}: ThrowMuzzle", $weap);
            self::assertStringContainsString("magazines[] = {\"COMSPEC_FB_{$theme}\"};", $weap);
            self::assertSame(2, substr_count($weap, "COMSPEC_FB_Muzzle_{$theme}"), 'Muzzle déclaré et ajouté à Throw.');
            self::assertStringContainsString("class COMSPEC_FB_Device_{$theme}: COMSPEC_FB_Device_Base", $dev);
            self::assertStringContainsString("\"COMSPEC_FB_{$theme}\"", $cfg);
            self::assertStringContainsString("\"COMSPEC_FB_Ammo_{$theme}\"", $cfg);
            // Modèle, texture multicolore, icône.
            foreach (["data/fb_{$file}.p3d", "data/fb_{$file}_spent.p3d", "data/fb_body_{$file}_co.paa", "ui/fb_icon_{$file}_ca.paa"] as $f) {
                self::assertFileExists(self::path(self::MAIN . $f), "{$theme} : {$f}");
            }
            self::assertStringContainsString("class fb_{$file}: fb_1", $modelCfg);
            self::assertStringContainsString("class fb_{$file}_spent: fb_1", $modelCfg);
            // Textes FR + EN : nom préfixé, court, info-bulle marquée.
            foreach (['fr', 'en'] as $lang) {
                self::assertSame('[AT.NA] ' . $shown, $s["STR_COMSPEC_FB_{$theme}_Name"][$lang]);
                self::assertLessThanOrEqual(21, mb_strlen($s["STR_COMSPEC_FB_{$theme}_Name"][$lang]));
                self::assertSame($shown, $s["STR_COMSPEC_FB_{$theme}_Short"][$lang]);
                self::assertStringContainsString('BetterFlash', $s["STR_COMSPEC_FB_{$theme}_Desc"][$lang]);
                self::assertStringContainsString($shown, $s["STR_COMSPEC_FB_{$theme}_Desc"][$lang]);
            }
            // Caisses : 2 dans la caisse standard, 10 dans la caisse dédiée ; listes des modules Eden et ZEN.
            self::assertStringContainsString("magazine = \"COMSPEC_FB_{$theme}\"; count = 2;", $box);
            self::assertStringContainsString("magazine = \"COMSPEC_FB_{$theme}\"; count = 10;", $special);
            self::assertStringContainsString("class {$theme} { name = \"\$STR_COMSPEC_FB_{$theme}_Short\"; value = \"{$theme}\"; };", $veh);
            self::assertStringContainsString("\"{$theme}\"", $zen);
            // Son du thème : fichier 3D et couche 2D présents, durée, flashs calés ou espacés.
            self::assertSame(1, preg_match('/class Theme_' . $theme . ' \{(.*?)\n    \};/s', $sons, $m), "Entrée Theme_{$theme}");
            $entry = $m[1];
            self::assertSame(1, preg_match('/fichiers\[\] = \{"([^"]+)"\};/', $entry, $f));
            self::assertFileExists(self::path(self::MAIN . 'sounds/' . $f[1]));
            self::assertStringEndsWith('_enreg.ogg', $f[1]);
            self::assertSame(1, preg_match('/ui\[\] = \{"([^"]+)"\};/', $entry, $u));
            self::assertSame(1, preg_match('/FB_UI_SOUND\(' . $u[1] . ',([^)]+)\);/', $sons, $uf), "Couche 2D de {$theme}");
            self::assertFileExists(self::path(self::MAIN . 'sounds/' . $uf[1]));
            self::assertSame(1, preg_match('/duree = ([0-9.]+); unique = ([01]); intervalle = ([0-9.]+); instants\[\] = \{([^}]*)\};/', $entry, $d));
            $dur = (float) $d[1];
            $instants = array_values(array_filter(array_map('trim', explode(',', $d[4])), static fn (string $v): bool => $v !== ''));
            if ($d[2] === '1') {
                // Son long joué une fois : un instant par flash, croissants, tous pendant le son.
                self::assertGreaterThan(4.0, $dur);
                self::assertCount($n, $instants, "{$theme} : un instant par flash.");
                self::assertSame('0', $instants[0]);
                for ($i = 1; $i < $n; $i++) {
                    self::assertGreaterThan((float) $instants[$i - 1] + 0.3, (float) $instants[$i]);
                }
                self::assertLessThan($dur, (float) $instants[$n - 1]);
            } else {
                // Son court rejoué à chaque flash : intervalle d'au moins 0,6 x la durée.
                self::assertSame([], $instants);
                self::assertGreaterThanOrEqual(0.6 * $dur, (float) $d[3]);
            }
            self::assertMatchesRegularExpression('/teintes\[\] = \{\{[^;]+\}\};/', $entry, 'Palette du flash.');
            self::assertStringContainsString('volume = 2.5; distance = 900;', $entry, 'Mêmes volume et portée que le bang standard.');
        }
        self::assertStringContainsString('class COMSPEC_FB_Box_Special: COMSPEC_FB_Box', $veh);
        self::assertStringContainsString('"COMSPEC_FB_Box_Special"', $cfg);
        self::assertStringContainsString('comspec_fb_theme = QUOTE(V);', $ammo);
        // Le son du thème remplace le bang ; un son long n'est lu qu'une fois ; l'acouphène reste celui du mod.
        $burst = self::read(self::MAIN . 'functions/fn_burst.sqf');
        self::assertStringContainsString('if (!_themeMute) then { ["Theme_" + _theme, _pos, _delay, _wall, _gain, _range, _dc] call comspec_fb_fnc_playBang };', $burst);
        self::assertStringContainsString('>> "unique") == 1', $burst);
        $server = self::read(self::MAIN . 'functions/fn_serverDetonate.sqf');
        self::assertStringContainsString('getArray (_tcfg >> "instants")', $server);
        self::assertStringContainsString('if (_theme != "") then { _extra = [_theme] };', $server);
        self::assertStringContainsString('selectRandom _palette', self::read(self::MAIN . 'functions/fn_fx.sqf'));
        self::assertStringNotContainsString('Theme_', self::read(self::MAIN . 'functions/fn_tinnitus.sqf'));
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
        self::assertStringContainsString('(call comspec_fb_fnc_clock) + _fuse', $fired, 'Heure de la 1re détonation fixée au lancer (horloge commune).');
    }

    public function testFonctionneSansLeModSurLeServeur(): void
    {
        $post = self::read(self::MAIN . 'XEH_postInit.sqf');
        self::assertStringContainsString('missionNamespace setVariable ["comspec_fb_serverVersion", _version, true]', $post);
        self::assertStringContainsString('[COMSPEC FB] SERVEUR : mod absent (clients seulement)', $post);
        self::assertStringContainsString('comspec_fb_serverVersion', self::read(self::MAIN . 'functions/fn_serverHasMod.sqf'));
        self::assertStringContainsString('class serverHasMod {};', self::read(self::MAIN . 'CfgFunctions.hpp'));
        foreach (['fn_serverDetonate.sqf', 'fn_serverArea.sqf'] as $f) {
            $s = self::read(self::MAIN . 'functions/' . $f);
            self::assertStringNotContainsString('if (!isServer) exitWith {};', $s, "{$f} doit marcher sur un client.");
            self::assertStringContainsString('call comspec_fb_fnc_serverHasMod', $s);
            self::assertStringContainsString('call CBA_fnc_globalEvent', $s, "{$f} : diffusion par le client qui fait autorité.");
        }
        self::assertStringContainsString('createVehicleLocal', self::read(self::MAIN . 'functions/fn_timeline.sqf'));
        $fuse = self::read(self::MAIN . 'functions/fn_fuseEnd.sqf');
        self::assertStringContainsString('_args call comspec_fb_fnc_serverDetonate;', $fuse);
        $zen = self::read(self::MAIN . 'functions/fn_zenRegister.sqf');
        self::assertStringNotContainsString('CBA_fnc_serverEvent', $zen);
        $pre = self::read(self::MAIN . 'XEH_preInit.sqf');
        self::assertStringContainsString('["comspec_fb_debug", "CHECKBOX", "Debug", "Client", true, 0]', $pre, 'Journal actif par défaut.');
        self::assertStringContainsString('"comspec_fb_debugVerbose"', $pre);
    }

    /** Réplique PHP de fn_rand / fn_plan (mêmes formules) : moyenne et bornes vérifiées par tirages. */
    private static function rand(float $amp, float $v): float
    {
        return (mt_rand() / mt_getrandmax() * 2 - 1) * $amp * $v;
    }

    public function testCalibrationDuHasardMoyenneEtVarianceBornees(): void
    {
        $rand = self::read(self::MAIN . 'functions/fn_rand.sqf');
        self::assertStringContainsString('(random 2 - 1) * _amp * (missionNamespace getVariable ["comspec_fb_variation", 0.6])', $rand);
        $burst = self::read(self::MAIN . 'functions/fn_burst.sqf');
        self::assertStringContainsString('private _rv = (1 + ([0.1] call comspec_fb_fnc_rand)) * _g;', $burst);
        self::assertStringContainsString('comspec_fb_sens = 1 + ([0.25] call comspec_fb_fnc_rand)', $burst);
        mt_srand(42);
        $v = 0.6;
        $n = 20000;
        $sum = 0.0;
        $sum2 = 0.0;
        $max = 0.0;
        for ($i = 0; $i < $n; $i++) {
            // Facteur appliqué à la vision d'un joueur au 1er bang (force 1) : tirage x sensibilité de session.
            $f = (1 + self::rand(0.1, $v)) * (1 + self::rand(0.25, $v));
            $sum += $f;
            $sum2 += $f * $f;
            $max = max($max, abs($f - 1));
        }
        $mean = $sum / $n;
        $var = $sum2 / $n - $mean * $mean;
        self::assertEqualsWithDelta(1.0, $mean, 0.05, 'Moyenne conforme à la table (±5 %).');
        self::assertLessThan(0.01, $var, 'Variance bornée.');
        self::assertLessThan(0.23, $max, 'Écart maximal borné (±22 % au pire).');
    }

    public function testCadencesOrdreEtIntervalleMoyen(): void
    {
        $plan = self::read(self::MAIN . 'functions/fn_plan.sqf');
        foreach (['case 1: { [0.15, 0.45] call _around };', 'case 2: { [0.065, 0.45] call _around };',
            '_t = _t + ((_dt max 0.03) min 1.2);'] as $needle) {
            self::assertStringContainsString($needle, $plan);
        }
        mt_srand(7);
        $v = 0.6;
        foreach ([[1, 0.15], [2, 0.065]] as [$mode, $centre]) {
            $total = 0.0;
            $count = 0;
            for ($g = 0; $g < 400; $g++) {
                $t = 0.0;
                $prev = -1.0;
                for ($i = 0; $i < 9; $i++) {
                    self::assertGreaterThan($prev, $t, 'Instants dans l\'ordre, sans doublon.');
                    $prev = $t;
                    $dt = $centre * (1 + self::rand(0.45, $v));
                    $t += min(max($dt, 0.03), 1.2);
                }
                $total += $prev;
                $count += 8;
            }
            self::assertEqualsWithDelta($centre, $total / $count, $centre * 0.1, "Intervalle moyen du mode {$mode} (±10 %).");
        }
        // À 20 images/s, CBA exécute toutes les tâches échues d'une image : aucune détonation perdue.
        self::assertStringContainsString('aucune perte', self::read(self::MAIN . 'functions/fn_timeline.sqf'));
    }

    public function testAcouphene1DemarrageProlongationsEt1Fin(): void
    {
        $tinn = self::read(self::MAIN . 'functions/fn_tinnitus.sqf');
        $tick = self::read(self::MAIN . 'functions/fn_tinnitusTick.sqf');
        self::assertStringContainsString('démarrage niveau=', $tinn);
        self::assertStringContainsString('Train en cours : on prolonge', $tinn);
        self::assertStringContainsString('["attaque", getNumber (_cfg >> "dureeAttaque")] call _play;', $tick);
        self::assertSame(1, substr_count($tick, '["attaque", '), 'Une seule lecture d\'attaque dans le planificateur.');
        // Simulation du planificateur (mêmes règles) : 9 puis 50 flashs rapides.
        foreach ([9, 50] as $flashes) {
            $state = '';
            $starts = $attacks = $ext = $ends = 0;
            $end = 0.0;
            $segEnd = 0.0;
            for ($t = 0.0; $t < 120; $t += 0.02) {
                $hitIdx = (int) round($t / 0.065);
                if ($hitIdx < $flashes && abs($t - $hitIdx * 0.065) < 0.011) {
                    if ($state === '' || $state === 'fini') {
                        $state = 'attaque';
                        $starts++;
                        $end = $t + 45;
                    } else {
                        $ext++;
                        $end = max($end, $t + 45);
                        if ($state === 'fin') {
                            $state = 'boucle';
                        }
                    }
                }
                if ($state === '' || $state === 'fini' || $t < $segEnd - 0.03) {
                    continue;
                }
                if ($state === 'attaque') {
                    $attacks++;
                    $segEnd = $t + 0.5;
                    $state = 'boucle';
                } elseif ($state === 'boucle' && $t < $end) {
                    $segEnd = $t + 3;
                } elseif ($state === 'boucle') {
                    $ends++;
                    $segEnd = $t + 6;
                    $state = 'fin';
                } else {
                    $state = 'fini';
                }
            }
            self::assertSame(1, $starts, "{$flashes} flashs : un seul démarrage.");
            self::assertSame(1, $attacks, "{$flashes} flashs : jamais deux attaques.");
            self::assertGreaterThanOrEqual(1, $ext, "{$flashes} flashs : au moins une prolongation.");
            self::assertSame(1, $ends, "{$flashes} flashs : une seule fin.");
        }
    }
}
