<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Fenêtre « Réalisme et réseau du serveur » : chaque réglage affiche une explication et un exemple (FR et EN),
 * l'assistant les reprend, et la voix relayée par le maillage se règle depuis cette fenêtre.
 * Lecture du code seulement : rien de tout cela n'a tourné en jeu.
 */
final class AtakReglagesExplicationsTest extends TestCase
{
    private const MOD = __DIR__ . '/../../mod/COMSPEC_ATAK_Native';
    private const MAIN = self::MOD . '/Sources/addons/main';

    private function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents($path));
    }

    /** @return list<string> réglages de la fenêtre, sans le préfixe comspec_atak_native_ */
    private function reglages(): array
    {
        $cat = $this->read(self::MAIN . '/functions/network/fn_realismCatalog.sqf');
        preg_match_all('/_p \+ "([a-z0-9_]+)"/', $cat, $m);
        $out = $m[1];
        $cond = $this->read(self::MAIN . '/functions/network/fn_netCond.sqf');
        preg_match_all('/\["[A-Za-z]+", "comspec_atak_native_([a-z0-9_]+)", "net_/', $cond, $m2);
        $out = array_merge($out, $m2[1]);
        preg_match_all('/"(comspec_overwatch_[a-z_]+|COMSPEC_ATAK_DirtFx)", "(?:ro|pref)"/', $cat, $m3);

        return array_values(array_unique(array_merge($out, $m3[1])));
    }

    /** @return array<string, array{string, string}> */
    private function aides(): array
    {
        $src = $this->read(self::MAIN . '/functions/network/fn_realismHelp.sqf');
        preg_match_all('/^\s*\["([A-Za-z0-9_]+)", \["([^"]+)", "([^"]+)"\]\],?$/m', $src, $m, PREG_SET_ORDER);
        $out = [];
        foreach ($m as $r) {
            $out[$r[1]] = [$r[2], $r[3]];
        }

        return $out;
    }

    /** @return array<string, string> */
    private function dico(string $file): array
    {
        $out = [];
        foreach (explode("\n", $this->read($file)) as $l) {
            if (preg_match('/^\["((?:[^"]|"")*)", (?:"", )?"((?:[^"]|"")*)"\],?$/', $l, $m)) {
                $out[str_replace('""', '"', $m[1])] = $m[2];
            }
        }

        return $out;
    }

    public function testChaqueReglageADesExplicationEtUnExemple(): void
    {
        $aides = $this->aides();
        $reglages = $this->reglages();
        self::assertGreaterThan(70, count($reglages));
        $sans = [];
        foreach ($reglages as $r) {
            $cle = str_starts_with($r, 'comspec_') || $r === 'COMSPEC_ATAK_DirtFx' ? $r : $r;
            if (!isset($aides[$cle]) || strlen($aides[$cle][0]) < 15 || strlen($aides[$cle][1]) < 15) {
                $sans[] = $r;
            }
        }
        self::assertSame([], $sans, 'réglages sans explication ou sans exemple');
        // Un exemple chiffré ou concret : jamais de nom de variable ni de fonction dans un texte joueur.
        foreach ($aides as $k => [$d, $e]) {
            self::assertDoesNotMatchRegularExpression('/comspec_atak|fnc_|CBA_/i', $d . $e, $k);
        }
    }

    public function testChaqueTexteEstTraduitEnAnglais(): void
    {
        $en = $this->dico(self::MAIN . '/i18n/en.txt');
        $xx = $this->dico(self::MOD . '/localisation/template/xx.txt');
        foreach ($this->aides() as $k => $textes) {
            foreach ($textes as $t) {
                self::assertArrayHasKey($t, $en, "EN manquant : {$k}");
                self::assertNotSame('', $en[$t], $k);
                self::assertArrayHasKey($t, $xx, "modèle xx manquant : {$k}");
            }
        }
        foreach (['Ex.', 'Par défaut'] as $t) {
            self::assertArrayHasKey($t, $en);
        }
    }

    public function testFenetreEtAssistantAfficheLesExplications(): void
    {
        $nc = $this->read(self::MAIN . '/functions/network/fn_netConfig.sqf');
        self::assertStringContainsString('"html", _var, _dtx', $nc);
        self::assertStringContainsString('ctrlTextHeight _lb', $nc, 'la ligne grandit avec le texte');
        $wz = $this->read(self::MAIN . '/functions/network/fn_setupWizard.sqf');
        self::assertStringContainsString('realismHelp', $wz);
        self::assertStringContainsString('class realismHelp {}', $this->read(self::MAIN . '/config.cpp'));
        // Aucune valeur par défaut ni garde radio touchés : la valeur d'usine est seulement lue.
        $cat = $this->read(self::MAIN . '/functions/network/fn_realismCatalog.sqf');
        self::assertStringContainsString('[_var, "default"] call CBA_settings_fnc_get', $cat);
    }

    public function testVoixParLeMaillageSeRegleDepuisLaFenetre(): void
    {
        $cat = $this->read(self::MAIN . '/functions/network/fn_realismCatalog.sqf');
        self::assertMatchesRegularExpression('/\[_ms, "Voix relayée par le maillage", _p \+ "mpu_voice"\] call _b/', $cat);
        self::assertStringNotContainsString('[_rd, "Voix relayée par le maillage"', $cat);
        $pre = $this->read(self::MAIN . '/XEH_preInit.sqf');
        $i = strpos($pre, '["comspec_atak_native_mpu_voice"');
        self::assertNotFalse($i);
        self::assertMatchesRegularExpression('/^\s+_mpuCat, false, 1\]/m', substr($pre, $i, strpos($pre, 'addSetting', $i) - $i + 20), 'défaut inchangé : désactivé');
        $voice = $this->read(self::MAIN . '/functions/network/fn_mpuVoice.sqf');
        self::assertStringContainsString('Échap > Réalisme', $voice);
    }

    public function testOngletVoixSeComprendSeul(): void
    {
        $p = $this->read(self::MAIN . '/functions/pages/fn_pageWaveRelay.sqf');
        foreach (['À quoi sert cet onglet', 'Prochain pas', 'Fréquences', 'Personne d\'autre ne publie son réseau voix pour l\'instant'] as $t) {
            self::assertStringContainsString($t, $p);
        }
        $g = $this->read(self::MAIN . '/functions/network/fn_meshGroups.sqf');
        self::assertStringContainsString('[x] ÉCOUTÉ', $g);
        self::assertStringContainsString('[ ] ÉCOUTER', $g);
    }
}
