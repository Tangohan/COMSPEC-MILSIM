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
        self::assertStringContainsString('if (!_themeMute) then { _voices = ["Theme_" + _theme, _pos, _delay, _wall, _gain, _range, _dc, _maxCopies] call comspec_fb_fnc_playBang }', $burst);
        self::assertStringContainsString('>> "unique") == 1', $burst);
        $server = self::read(self::MAIN . 'functions/fn_serverDetonate.sqf');
        self::assertStringContainsString('getArray (_tcfg >> "instants")', $server);
        self::assertStringContainsString('if (_theme != "") then { _extra = [_theme] };', $server);
        self::assertStringContainsString('selectRandom _palette', self::read(self::MAIN . 'functions/fn_tint.sqf'));
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

    /** Réplique PHP de fn_schedule (mêmes règles) : [dues, abandonnées, suivante, attente]. @return array{list<int>, list<int>, int, float} */
    private static function schedule(array $offsets, float $t0, float $now, int $next, float $lateMax = 1.5): array
    {
        $due = [];
        $dropped = [];
        $n = count($offsets);
        while ($next < $n && ($t0 + $offsets[$next]) <= $now + 0.01) {
            if ($now - ($t0 + $offsets[$next]) > $lateMax) {
                $dropped[] = $next;
            } else {
                $due[] = $next;
            }
            $next++;
        }

        return [$due, $dropped, $next, $next < $n ? max(0.0, $t0 + $offsets[$next] - $now) : -1.0];
    }

    /** Réplique PHP de fn_sndBudget : lectures 3D superposées accordées (0 = bang fusionné). */
    private static function sndBudget(int $busy, int $wanted, int $maxCopies = 4): int
    {
        if ($busy >= 28) {
            return 0;
        }
        $c = min(max($wanted, 1), $maxCopies, 4);
        if ($busy >= 12) {
            $c = min($c, 2);
        }
        if ($busy >= 20) {
            $c = 1;
        }

        return $c;
    }

    /**
     * Rafale simulée image par image (fn_timelineRun + fn_burst + fn_playBang) : plan hyper rapide, planificateur,
     * budget sonore. @return array{done: list<int>, dropped: int, sounds: int, fused: int, lightsNew: int, lightsRelit: int, maxBusy: int}
     */
    private static function simulateBurst(int $n, int $fps, float $late = 0.0, float $centre = 0.065): array
    {
        $v = 0.6;
        $offsets = [];
        $t = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $offsets[] = $t;
            $t += min(max($centre * (1 + self::rand(0.45, $v)), 0.03), 1.2);
        }
        $t0 = 100.0;
        $now = $t0 - 0.2 + $late + (mt_rand() / mt_getrandmax()) / $fps;
        $next = 0;
        $out = ['done' => [], 'dropped' => 0, 'sounds' => 0, 'fused' => 0, 'lightsNew' => 0, 'lightsRelit' => 0, 'maxBusy' => 0];
        $ends = [];
        $lastLight = -1.0;
        $guard = 0;
        while ($next < $n && $guard++ < 100000) {
            [$due, $dropped, $next] = self::schedule($offsets, $t0, $now, $next);
            $out['dropped'] += count($dropped);
            foreach ($due as $idx) {
                $out['done'][] = $idx;
                // Lumière (fn_fx) : relancée si la précédente a moins de 0,12 s, sinon créée.
                if ($now - $lastLight < 0.12) {
                    $out['lightsRelit']++;
                } else {
                    $out['lightsNew']++;
                }
                $lastLight = $now;
                // Son (fn_burst) : bang court si un autre suit à moins de 0,25 s, entier pour le 1er et le dernier ;
                // 2 lectures au plus en rafale + couche proche 2D ; couche grave au 1er.
                $gapNext = $idx < $n - 1 ? $offsets[$idx + 1] - $offsets[$idx] : 1e6;
                $short = $idx > 0 && $gapNext < 0.25;
                $entries = [[$short ? 0.35 : 1.2]];
                if ($idx === 0) {
                    $entries[] = [1.0];
                }
                foreach ($entries as $k => [$dur]) {
                    $ends = array_values(array_filter($ends, static fn (float $e): bool => $e > $now));
                    $busy = count($ends);
                    $out['maxBusy'] = max($out['maxBusy'], $busy);
                    $copies = self::sndBudget($busy, 4, 2);
                    if ($k === 0) {
                        $copies === 0 ? $out['fused']++ : $out['sounds']++;
                    }
                    for ($c = 0; $c < ($copies === 0 ? 0 : $copies + 1); $c++) {
                        $ends[] = $now + $dur;
                    }
                }
            }
            $now += 1.0 / $fps;
        }

        return $out;
    }

    public function testRafaleNDetonationsNSonsA60Et20ImagesParSeconde(): void
    {
        $sched = self::read(self::MAIN . 'functions/fn_schedule.sqf');
        foreach (['(_t0 + (_offsets select _next)) <= _now + 0.01', '> _lateMax) then { _dropped pushBack _next } else { _due pushBack _next }',
            '["_lateMax", 1.5, [0]]'] as $needle) {
            self::assertStringContainsString($needle, $sched);
        }
        $run = self::read(self::MAIN . 'functions/fn_timelineRun.sqf');
        self::assertStringContainsString('{ [_id, _x] call comspec_fb_fnc_burst } forEach _due;', $run, 'Toutes les détonations dues, dans l\'ordre.');
        self::assertStringContainsString('["rafale", format ["%1 : prévues %2, exécutées %3, abandonnées %4 ; sons lancés %5', $run, 'Ligne de synthèse.');
        $timeline = self::read(self::MAIN . 'functions/fn_timeline.sqf');
        self::assertStringContainsString('[comspec_fb_fnc_timelineRun, [_id, 0], (_t0 - _now) max 0] call CBA_fnc_waitAndExecute;', $timeline);
        self::assertStringNotContainsString('_wait < -0.3', $timeline, 'Plus de saut des détonations « en retard » de 0,3 s.');
        $budget = self::read(self::MAIN . 'functions/fn_sndBudget.sqf');
        foreach (['if (_busy >= 28) exitWith { 0 };', 'if (_busy >= 12) then { _c = _c min 2 };', 'if (_busy >= 20) then { _c = 1 };'] as $needle) {
            self::assertStringContainsString($needle, $budget);
        }
        $play = self::read(self::MAIN . 'functions/fn_playBang.sqf');
        self::assertStringNotContainsString('_busy >= 16', $play, 'Ancienne cause : bang supprimé dès 16 lectures (4e bang d\'une rafale).');
        self::assertStringContainsString('[_busy, ceil (_vol / 5), _maxCopies] call comspec_fb_fnc_sndBudget', $play);
        $burst = self::read(self::MAIN . 'functions/fn_burst.sqf');
        self::assertStringContainsString('private _short = _index > 0 && {_gapNext < 0.25};', $burst);
        self::assertStringContainsString('[["BangUnique", "BangCourt"] select _short, _pos, _delay, false, _gain, _range, _dc, _maxCopies] call comspec_fb_fnc_playBang', $burst);
        self::assertStringContainsString('min ([8, 3] select _close)', $burst, 'Limiteur : -3 dB au plus en rafale.');
        $sons = self::read(self::MAIN . 'CfgSons.hpp');
        self::assertSame(1, preg_match('/class BangCourt \{\s*fichiers\[\] = \{([^}]*)\};/', $sons, $f));
        foreach (explode(',', $f[1]) as $file) {
            self::assertFileExists(self::path(self::MAIN . 'sounds/' . trim($file, " \"")));
        }
        foreach (['A', 'B', 'C'] as $x) {
            self::assertStringContainsString('FB_UI_SOUND(COMSPEC_FB_UI_Court' . $x . ',bang_court_' . strtolower($x) . '_enreg.ogg);', $sons);
        }
        self::assertStringContainsString('class selfCheck {};', self::read(self::MAIN . 'CfgFunctions.hpp'));

        mt_srand(11);
        foreach ([6, 9, 50] as $n) {
            foreach ([60, 20] as $fps) {
                for ($g = 0; $g < 150; $g++) {
                    $r = self::simulateBurst($n, $fps);
                    self::assertSame(range(0, $n - 1), $r['done'], "N={$n} à {$fps} images/s : toutes exécutées, dans l'ordre.");
                    self::assertSame(0, $r['dropped'], "N={$n} à {$fps} images/s : aucune abandonnée pour retard.");
                    self::assertSame($n, $r['sounds'] + $r['fused']);
                    self::assertSame($n, $r['lightsNew'] + $r['lightsRelit'], 'Un éclat par détonation (lumière créée ou relancée).');
                    self::assertLessThan(28, $r['maxBusy'], "N={$n} : budget jamais saturé par une seule grenade.");
                    self::assertSame($n, $r['sounds'], "N={$n} à {$fps} images/s : un son par détonation, aucun fusionné.");
                }
            }
        }
        // Cadence « Rapide » (~0,15 s) choisie par le réglage ou un module : même garantie.
        for ($g = 0; $g < 100; $g++) {
            $r = self::simulateBurst(9, 20, 0.0, 0.15);
            self::assertSame(range(0, 8), $r['done']);
            self::assertSame(9, $r['sounds']);
        }
        // Message arrivé 0,6 s en retard (toute la rafale de 6 est déjà passée) : rattrapage groupé, rien d'abandonné.
        $r = self::simulateBurst(6, 20, 0.6);
        self::assertSame(range(0, 5), $r['done']);
        self::assertSame(0, $r['dropped']);
        // Retard extrême (2,2 s) : seules les détonations vieilles de plus de 1,5 s sont abandonnées, les autres jouent.
        [$due, $dropped, $next] = self::schedule([0.0, 0.1, 0.2, 0.9, 1.4], 100.0, 101.75, 0);
        self::assertSame([0, 1, 2], $dropped);
        self::assertSame([3, 4], $due);
        self::assertSame(5, $next);
        // Budget : jamais 0 sous 28 lectures ; deux GANG BANG simultanés finissent par fusionner (documenté).
        self::assertSame(2, self::sndBudget(0, 4, 2));
        self::assertSame(4, self::sndBudget(0, 4));
        self::assertSame(2, self::sndBudget(12, 4));
        self::assertSame(1, self::sndBudget(27, 4));
        self::assertSame(0, self::sndBudget(28, 4));
    }

    /** Réplique PHP de fn_visionTable : [facteur selon l'angle, facteur dans le champ]. */
    private static function vision(float $dn, float $ang, bool $outdoor): array
    {
        $tabs = $outdoor
            ? [[[0, 1], [2, 1], [5, 0.6], [10, 0.25], [15, 0.08], [19, 0]],
                [[0, 0.9], [2, 0.9], [5, 0.35], [10, 0.12], [15, 0.03], [19, 0]],
                [[0, 0.3], [2, 0.3], [5, 0.12], [10, 0.04], [15, 0], [19, 0]]]
            : [[[0, 1], [3.5, 1], [5, 0.85], [10, 0.5], [15, 0.3], [25, 0.04], [30, 0]],
                [[0, 0.92], [3.5, 0.92], [5, 0.5], [10, 0.25], [15, 0.1], [25, 0]],
                [[0, 0.35], [3.5, 0.35], [5, 0.2], [10, 0.08], [15, 0.03], [25, 0]]];
        $lerp = static function (array $tab, float $x): float {
            $out = (float) $tab[count($tab) - 1][1];
            for ($i = 0; $i < count($tab) - 1; $i++) {
                [$x0, $y0] = $tab[$i];
                [$x1, $y1] = $tab[$i + 1];
                if ($x <= $x1) {
                    return $y0 + ($y1 - $y0) * (max($x, $x0) - $x0) / ($x1 - $x0);
                }
            }

            return $out;
        };
        [$in, $side, $back] = [$lerp($tabs[0], $dn), $lerp($tabs[1], $dn), $lerp($tabs[2], $dn)];
        $fd = $ang <= 65 ? $in : ($ang <= 90 ? $in + ($side - $in) * ($ang - 65) / 25 : $side + ($back - $side) * ($ang - 90) / 90);

        return [$fd, $in];
    }

    /** Réplique PHP de fn_pupil. */
    private static function pupil(float $lum, float $d, float $night = 1.0): float
    {
        $lum = min(max($lum, 0.0), 1.0);
        $p = $lum <= 0.5 ? $night + (0.7 - $night) * $lum / 0.5 : 0.7 + (0.45 - 0.7) * ($lum - 0.5) / 0.5;
        if ($p < 1) {
            $p = 1 + ($p - 1) * min(max(($d - 2) / 4, 0.0), 1.0);
        }

        return $p;
    }

    public function testCalibrationInterieurExterieurJourNuit(): void
    {
        $tab = self::read(self::MAIN . 'functions/fn_visionTable.sqf');
        foreach (['[[[0, 1], [2, 1], [5, 0.6], [10, 0.25], [15, 0.08], [19, 0]],', '[[[0, 1], [3.5, 1], [5, 0.85], [10, 0.5], [15, 0.3], [25, 0.04], [30, 0]],',
            '[[0, 0.9], [2, 0.9], [5, 0.35], [10, 0.12], [15, 0.03], [19, 0]],', '[[0, 0.3], [2, 0.3], [5, 0.12], [10, 0.04], [15, 0], [19, 0]]]'] as $needle) {
            self::assertStringContainsString($needle, $tab);
        }
        $pupil = self::read(self::MAIN . 'functions/fn_pupil.sqf');
        self::assertStringContainsString('linearConversion [0, 0.5, _lum, _night, 0.7, true] } else { linearConversion [0.5, 1, _lum, 0.7, 0.45, true] }', $pupil);
        self::assertStringContainsString('if (_p < 1) then { _p = linearConversion [2, 6, _d, 1, _p, true] };', $pupil);
        $exp = self::read(self::MAIN . 'functions/fn_exposure.sqf');
        self::assertStringContainsString('private _outdoor = !_enclosed;', $exp);
        self::assertStringContainsString('private _lum = sunOrMoon * (1 - 0.3 * overcast);', $exp, 'Luminosité réelle : soleil x ciel.');
        self::assertStringContainsString('if (_indoorUnit) then { _lum = _lum min 0.25 };', $exp);
        self::assertStringContainsString('comspec_fb_outdoorRange', $exp);
        self::assertStringContainsString('comspec_fb_outdoorIntensity', $exp);
        $pre = self::read(self::MAIN . 'XEH_preInit.sqf');
        self::assertStringContainsString('["comspec_fb_nightMult", "SLIDER", "NightMult", "Vision", [1, 2, 1, 2]]', $pre, 'Nuit : x1,0 par défaut.');
        self::assertStringContainsString('["comspec_fb_outdoorRange", "SLIDER", "OutdoorRange", "Vision", [0.5, 1.5, 1, 0, true]]', $pre);
        self::assertStringContainsString('["comspec_fb_outdoorIntensity", "SLIDER", "OutdoorIntensity", "Vision", [0.2, 1.5, 1, 0, true]]', $pre);

        // Table extérieure (pupille ouverte) : plein effet <= 2 m, 0,6 / 0,25 / 0,08 à 5 / 10 / 15 m, rien au-delà de 19 m.
        foreach ([[1.0, 1.0], [2.0, 1.0], [5.0, 0.6], [10.0, 0.25], [15.0, 0.08], [19.0, 0.0], [25.0, 0.0]] as [$d, $want]) {
            self::assertEqualsWithDelta($want, self::vision($d, 0, true)[0], 1e-9, "Extérieur, de face, {$d} m.");
        }
        // Local fermé : table d'origine inchangée, toujours au moins aussi forte que dehors.
        foreach ([[3.5, 1.0], [5.0, 0.85], [10.0, 0.5], [15.0, 0.3], [25.0, 0.04]] as [$d, $want]) {
            self::assertEqualsWithDelta($want, self::vision($d, 0, false)[0], 1e-9, "Local fermé, de face, {$d} m.");
        }
        for ($d = 0.5; $d <= 30; $d += 0.5) {
            foreach ([0, 65, 90, 140, 180] as $ang) {
                $in = self::vision($d, $ang, false)[0];
                $out = self::vision($d, $ang, true)[0];
                self::assertGreaterThanOrEqual($out - 1e-9, $in, "Intérieur >= extérieur à {$d} m, {$ang}°.");
                self::assertGreaterThanOrEqual(self::vision($d + 0.5, $ang, true)[0] - 1e-9, $out, 'Décroissance avec la distance.');
            }
            self::assertGreaterThanOrEqual(self::vision($d, 90, true)[0], self::vision($d, 0, true)[0]);
            self::assertGreaterThanOrEqual(self::vision($d, 180, true)[0], self::vision($d, 90, true)[0]);
        }
        self::assertEqualsWithDelta(0.35, self::vision(5, 90, true)[0], 1e-9);
        self::assertEqualsWithDelta(0.12, self::vision(5, 180, true)[0], 1e-9);
        // Adaptation : plein soleil 0,45, aube / crépuscule 0,7, nuit 1,0 ; pas de réduction sous 2 m.
        self::assertEqualsWithDelta(0.45, self::pupil(1.0, 10), 1e-9);
        self::assertEqualsWithDelta(0.70, self::pupil(0.5, 10), 1e-9);
        self::assertEqualsWithDelta(1.00, self::pupil(0.0, 10), 1e-9);
        self::assertEqualsWithDelta(1.00, self::pupil(1.0, 2.0), 1e-9);
        self::assertEqualsWithDelta(0.725, self::pupil(1.0, 4.0), 1e-9);
        self::assertEqualsWithDelta(1.30, self::pupil(0.0, 10, 1.3), 1e-9, 'Multiplicateur nuit réglé à 1,3.');
        // Sous un toit (luminosité bornée à 0,25) : entre la nuit et le crépuscule.
        self::assertEqualsWithDelta(0.85, self::pupil(0.25, 10), 1e-9);
        // Résultat dehors en plein soleil, de face (README) : nettement plus faible et plus court qu'avant (0,85 / 0,50 / 0,30).
        $day = static fn (float $d): float => self::vision($d, 0, true)[0] * self::pupil(1.0, $d);
        self::assertEqualsWithDelta(1.0, $day(2.0), 1e-9);
        self::assertEqualsWithDelta(0.3525, $day(5.0), 1e-9);
        self::assertEqualsWithDelta(0.1125, $day(10.0), 1e-9);
        self::assertEqualsWithDelta(0.036, $day(15.0), 1e-9);
        self::assertSame(0.0, $day(20.0));
        // Jour < crépuscule < nuit à toute distance utile ; extérieur de nuit < local fermé de nuit.
        foreach ([5.0, 10.0, 15.0] as $d) {
            $night = self::vision($d, 0, true)[0] * self::pupil(0.0, $d);
            $dusk = self::vision($d, 0, true)[0] * self::pupil(0.5, $d);
            self::assertLessThan($dusk, $day($d));
            self::assertLessThan($night, $dusk);
            self::assertLessThan(self::vision($d, 0, false)[0] * self::pupil(0.0, $d), $night);
        }
        // « Portée de l'éblouissement en extérieur » à 50 % : la table est parcourue deux fois plus vite.
        self::assertEqualsWithDelta(self::vision(10, 0, true)[0], self::vision(5 / 0.5, 0, true)[0], 1e-9);
        // Lumière du monde et halo réduits de jour ; couche sonore 2D jamais au-delà de 25 m.
        $fx = self::read(self::MAIN . 'functions/fn_fx.sqf');
        self::assertStringContainsString('private _dayK = linearConversion [0, 1, _sun, 1, 0.35, true];', $fx);
        self::assertStringContainsString('setLightFlareMaxDistance (linearConversion [0, 1, _sun, 2500, 350, true])', $fx);
        self::assertStringContainsString('linearConversion [30, 200, _dist, 1, 0.4, true]', $fx);
        self::assertStringContainsString('{_dc < 25}', self::read(self::MAIN . 'functions/fn_playBang.sqf'));
        self::assertStringContainsString('linearConversion [8, 25, _dc, 1, 0, true]', self::read(self::MAIN . 'functions/fn_playBang.sqf'));
        $readme = self::read('mod/COMSPEC_Flashbang/README.md');
        self::assertStringContainsString('| Regard ≤ 65° du flash | 1,00 | 0,60 | 0,25 | 0,08 | 0 |', $readme);
        self::assertStringContainsString('| Facteur | 1,0 (multiplicateur nuit) | 0,70 | 0,45 |', $readme);
    }

    /** Réplique PHP de fn_sideRule : [niveau, kVision, kDurée, kAudition]. */
    private static function sideRule(string $unitSide, string $throwSide, bool $isThrower, array $set): array
    {
        $level = $set['side'][$unitSide] ?? 0;
        $same = $throwSide !== '' && $unitSide === $throwSide;
        $map = [0, 1, 3];
        if ($isThrower) {
            $level = max($level, $map[$set['own'] ?? 0]);
        }
        if ($same && !$isThrower) {
            $level = max($level, $map[$set['sideGrenade'] ?? 0]);
        }
        if ($same && !($set['friendly'] ?? true)) {
            $level = 3;
        }
        $k = $set['coef'] ?? 0.35;

        return array_merge([$level], [[1, 1, 1], [$k, $k, 1], [0, 1, 0.5], [0, 0, 0]][$level]);
    }

    public function testEffetParCampLePlusProtecteurGagne(): void
    {
        $rule = self::read(self::MAIN . 'functions/fn_sideRule.sqf');
        foreach (['["comspec_fb_sideWest", "comspec_fb_sideEast", "comspec_fb_sideIndep", "comspec_fb_sideCiv"] select _i',
            '[west, east, independent, civilian] find _unitSide', '[[1, 1, 1], [_k, _k, 1], [0, 1, 0.5], [0, 0, 0]] select _level',
            '_level max ([0, 1, 3] select', '["normal", "réduit", "aucun effet visuel", "immunisé"] select _level',
            'missionNamespace getVariable ["comspec_fb_reducedCoef", 0.35]'] as $needle) {
            self::assertStringContainsString($needle, $rule);
        }
        $pre = self::read(self::MAIN . 'XEH_preInit.sqf');
        self::assertStringContainsString('[format ["comspec_fb_side%1", _x], "LIST", format ["Side%1", _x], "Ai", [[0, 1, 2, 3], _sideLabels, 0]] call _add;', $pre, 'Défaut : Normal.');
        self::assertStringContainsString('} forEach ["West", "East", "Indep", "Civ"];', $pre);
        self::assertStringContainsString('["comspec_fb_reducedCoef", "SLIDER", "ReducedCoef", "Ai", [0.05, 0.9, 0.35, 0, true]] call _add;', $pre);
        self::assertStringContainsString('["comspec_fb_ownGrenade", "LIST", "OwnGrenade", "Ai", [[0, 1, 2], _ynLabels, 0]] call _add;', $pre);
        self::assertStringContainsString('["comspec_fb_sideGrenade", "LIST", "SideGrenade", "Ai", [[0, 1, 2], _ynLabels, 0]] call _add;', $pre);
        self::assertStringNotContainsString('"comspec_fb_friendly", "CHECKBOX"', $pre, 'Remplacé par « Les grenades de mon camp m\'affectent ».');
        $burst = self::read(self::MAIN . 'functions/fn_burst.sqf');
        self::assertStringContainsString('[side group _u, _side, !isNull _thrower && {_u == _thrower}] call comspec_fb_fnc_sideRule', $burst, 'Camp courant, à chaque détonation, joueurs et IA.');
        self::assertStringContainsString('camp=%12 règle=%13', $burst);
        self::assertStringContainsString('camp=%3 règle=immunisé', $burst);
        self::assertStringContainsString('{!_meHit} && {!_meShielded}', $burst, 'Pas d\'éclat de spectateur pour un joueur protégé.');
        self::assertStringContainsString('_vis = _vis * _rv * _kVis;', $burst);
        self::assertStringContainsString('_aud = _aud * _ra * _kAud;', $burst);
        $s = self::strings();
        foreach (['SideWest', 'SideEast', 'SideIndep', 'SideCiv', 'ReducedCoef', 'OwnGrenade', 'SideGrenade', 'OutdoorRange', 'OutdoorIntensity'] as $k) {
            foreach (['fr' => 'Exemple', 'en' => 'Example'] as $lang => $ex) {
                self::assertNotSame('', $s["STR_COMSPEC_FB_Set_{$k}"][$lang]);
                self::assertStringContainsString($ex, $s["STR_COMSPEC_FB_Set_{$k}_Tip"][$lang], "Exemple manquant : {$k} ({$lang})");
            }
        }
        self::assertStringContainsString('BLUFOR sur « Aucun effet visuel » : votre équipe d\'assaut n\'est pas éblouie par ses propres grenades', $s['STR_COMSPEC_FB_Set_SideWest_Tip']['fr']);
        foreach (['Side_Normal', 'Side_Reduced', 'Side_NoVisual', 'Side_Immune', 'Affect_Yes', 'Affect_Reduced', 'Affect_No'] as $k) {
            self::assertNotSame('', $s["STR_COMSPEC_FB_{$k}"]['fr']);
            self::assertNotSame('', $s["STR_COMSPEC_FB_{$k}"]['en']);
        }

        // Défaut : tout le monde « normal ».
        self::assertSame([0, 1, 1, 1], self::sideRule('WEST', 'WEST', true, []));
        // Coefficients par camp.
        self::assertSame([1, 0.35, 0.35, 1], self::sideRule('WEST', 'EAST', false, ['side' => ['WEST' => 1]]));
        self::assertSame([1, 0.2, 0.2, 1], self::sideRule('WEST', 'EAST', false, ['side' => ['WEST' => 1], 'coef' => 0.2]));
        self::assertSame([2, 0, 1, 0.5], self::sideRule('WEST', 'WEST', false, ['side' => ['WEST' => 2]]), 'Aucun effet visuel : son et acouphène à moitié.');
        self::assertSame([3, 0, 0, 0], self::sideRule('CIV', 'WEST', false, ['side' => ['CIV' => 3]]));
        self::assertSame([0, 1, 1, 1], self::sideRule('EAST', 'WEST', false, ['side' => ['WEST' => 2]]), 'Le réglage de BLUFOR ne protège pas OPFOR.');
        // Lanceur et camp du lanceur.
        self::assertSame(1, self::sideRule('WEST', 'WEST', true, ['own' => 1])[0]);
        self::assertSame(3, self::sideRule('WEST', 'WEST', true, ['own' => 2])[0]);
        self::assertSame(0, self::sideRule('WEST', 'WEST', false, ['own' => 2])[0], '« Mes propres grenades » ne concerne que le lanceur.');
        self::assertSame(3, self::sideRule('WEST', 'WEST', false, ['sideGrenade' => 2])[0]);
        self::assertSame(0, self::sideRule('WEST', 'WEST', true, ['sideGrenade' => 2])[0], 'Le lanceur suit « Mes propres grenades ».');
        self::assertSame(0, self::sideRule('EAST', 'WEST', false, ['sideGrenade' => 2])[0]);
        // Le plus protecteur gagne.
        self::assertSame(2, self::sideRule('WEST', 'WEST', true, ['side' => ['WEST' => 2], 'own' => 1])[0]);
        self::assertSame(3, self::sideRule('WEST', 'WEST', false, ['side' => ['WEST' => 1], 'sideGrenade' => 2])[0]);
        self::assertSame(1, self::sideRule('WEST', 'WEST', false, ['side' => ['WEST' => 0], 'sideGrenade' => 1])[0]);
        // Ancien réglage de mission comspec_fb_friendly = false : camp du lanceur immunisé, lanceur compris.
        self::assertSame(3, self::sideRule('WEST', 'WEST', true, ['friendly' => false])[0]);
        self::assertSame(0, self::sideRule('EAST', 'WEST', false, ['friendly' => false])[0]);
        // Niveaux ordonnés : chaque niveau protège au moins autant que le précédent, sur chaque canal.
        $levels = [[1, 1, 1], [0.35, 0.35, 1], [0, 1, 0.5], [0, 0, 0]];
        for ($i = 1; $i < 4; $i++) {
            self::assertLessThanOrEqual($levels[$i - 1][0], $levels[$i][0]);
            self::assertLessThanOrEqual($levels[$i - 1][2], $levels[$i][2]);
        }
    }

    public function testHalosVariesTeintesEtDescriptionSteam(): void
    {
        for ($i = 1; $i <= 8; $i++) {
            self::assertFileExists(self::path(self::MAIN . "ui/fb_halo_{$i}_ca.paa"));
            self::assertFileExists(self::path("mod/COMSPEC_Flashbang/tools/render/fb_halo_{$i}_ca.png"));
        }
        self::assertFileExists(self::path(self::MAIN . 'ui/fb_halo_core_ca.paa'));
        $rsc = self::read(self::MAIN . 'RscTitles.hpp');
        self::assertStringContainsString('class COMSPEC_FB_Halo {', $rsc);
        foreach ([201, 202, 203, 204, 211, 212, 213, 214] as $idc) {
            self::assertStringContainsString("idc = {$idc};", $rsc);
        }
        $halo = self::read(self::MAIN . 'functions/fn_halo.sqf');
        self::assertStringContainsString('private _model = 1 + floor random 8;', $halo);
        self::assertStringContainsString('if (_model == _last) then { _model = 1 + ((_model + floor random 7) mod 8) };', $halo, 'Jamais deux fois de suite le même modèle.');
        self::assertStringContainsString('ctrlSetAngle [random 360, 0.5, 0.5, true]', $halo);
        self::assertStringContainsString('fb_halo_%1_ca.paa', $halo);
        // Tirage sans répétition : la formule ne retombe jamais sur le modèle précédent et reste dans 1..8.
        for ($last = 1; $last <= 8; $last++) {
            for ($r = 0; $r < 7; $r++) {
                $m = 1 + (($last + $r) % 8);
                self::assertNotSame($last, $m);
                self::assertGreaterThanOrEqual(1, $m);
                self::assertLessThanOrEqual(8, $m);
            }
        }
        $tint = self::read(self::MAIN . 'functions/fn_tint.sqf');
        foreach (['"blanc pur"', '"blanc bleuté"', '"blanc chaud"', '"doré, bord bleuté"', '"pointe verte"', '"pointe magenta"', 'selectRandom _palette',
            'if (_v <= 0) exitWith { ["blanc pur"'] as $needle) {
            self::assertStringContainsString($needle, $tint);
        }
        // Poids du tirage : somme 1 ; teintes réalistes : aucun canal sous 0,38 (pas d'arc-en-ciel hors thème).
        self::assertSame(6, preg_match_all('/\]\], (0\.\d+)/', $tint, $w), 'Six teintes pondérées.');
        self::assertEqualsWithDelta(1.0, array_sum(array_map('floatval', $w[1])), 1e-9);
        preg_match_all('/\[(1|0\.\d+), (1|0\.\d+), (1|0\.\d+)\]/', substr($tint, (int) strpos($tint, 'selectRandomWeighted')), $c);
        self::assertGreaterThanOrEqual(18, count($c[0]));
        foreach (array_merge($c[1], $c[2], $c[3]) as $ch) {
            self::assertGreaterThanOrEqual(0.38, (float) $ch);
        }
        $fx = self::read(self::MAIN . 'functions/fn_fx.sqf');
        self::assertStringContainsString('[_palette] call comspec_fb_fnc_tint', $fx);
        self::assertStringContainsString('[_ctx, _pos, _k, _tintInfo, _fast] call comspec_fb_fnc_halo', $fx);
        self::assertStringContainsString('["fx", format ["halo=%1 teinte=%2', $fx);
        self::assertStringContainsString('(_lightArgs select 0) setLightColor _tint;', $fx, 'Rafale : la lumière partagée change de teinte à chaque coup.');
        self::assertStringContainsString('private _tau = [0.07, 0.022] select _fast;', $fx, 'Rafale : la lumière retombe entre deux coups (stroboscope).');
        // Stroboscope : à 65 ms d'écart, la lumière redescend sous 15 % avant le coup suivant (pic 18 ms, constante 22 ms).
        self::assertLessThan(0.15, exp(-(0.065 - 0.018) / 0.022));
        self::assertGreaterThan(0.5, exp(-(0.065 - 0.035) / 0.07), 'Ancienne décroissance : lumière encore à 65 %, éclats confondus.');
        self::assertStringContainsString('linearConversion [0, 0.06, _now - (_st getOrDefault ["lastHit", -10]), 1, 0.15, true]', self::read(self::MAIN . 'functions/fn_playerLoop.sqf'));
        $desc = (string) file_get_contents(self::path('mod/COMSPEC_Flashbang/workshop/STEAM_DESCRIPTION.txt'));
        self::assertLessThanOrEqual(7900, strlen($desc), 'Description Steam : 7 900 octets au plus.');
        self::assertSame(45, substr_count(self::read(self::MAIN . 'XEH_preInit.sqf'), 'call _add;') + 3, '45 réglages (4 camps par une seule ligne).');
        self::assertStringContainsString('45 réglages CBA', $desc);
        self::assertStringContainsString('45 CBA settings', $desc);
    }
}
