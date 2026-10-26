<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * DEF-008, DEF-009 — CfgPatches de l'ATAK natif : weapons[] et units[] listent chaque classe publique (scope 2,
 * directe ou héritée dans le fichier) de CfgWeapons et CfgVehicles. Manquaient le MPU5 (COMSPEC_ATAK_MPU5), le
 * téléphone (COMSPEC_ATAK_Phone) et le téléphone posé au sol (Item_COMSPEC_ATAK_Phone, arbre de création de Zeus).
 *
 * Test statique (lecture de config.cpp et de modules.hpp inclus dans CfgVehicles, commentaires retirés). Il échoue
 * si une classe publique manque à sa liste, si une liste nomme une classe non définie ici (faute de frappe), ou si les
 * trois classes du constat en sortent.
 */
final class AtakCfgPatchesPublicClassesAssetTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';

    private function config(): string
    {
        $dir = dirname(__DIR__, 2) . '/' . self::MAIN;
        $cfg = file_get_contents($dir . 'config.cpp');
        $mod = file_get_contents($dir . 'modules.hpp');
        self::assertNotFalse($cfg);
        self::assertNotFalse($mod);
        self::assertSame(1, substr_count((string) $cfg, '#include "modules.hpp"'));
        $t = str_replace('#include "modules.hpp"', (string) $mod, (string) $cfg);
        $t = (string) preg_replace('~/\*.*?\*/~s', '', (string) preg_replace('~//[^\n]*~', '', $t));
        self::assertSame(substr_count($t, '{'), substr_count($t, '}'), 'Accolades déséquilibrées après retrait des commentaires');

        return $t;
    }

    /** Corps (entre accolades) de la classe de premier niveau $name. */
    private function topClassBody(string $t, string $name): string
    {
        self::assertSame(1, preg_match('~^class\s+' . $name . '\s*\{~m', $t, $m, PREG_OFFSET_CAPTURE), $name . ' introuvable');
        $start = $m[0][1] + strlen($m[0][0]);
        $depth = 1;
        for ($i = $start, $n = strlen($t); $i < $n; $i++) {
            if ($t[$i] === '{') {
                $depth++;
            } elseif ($t[$i] === '}' && --$depth === 0) {
                return substr($t, $start, $i - $start);
            }
        }
        self::fail($name . ' non refermée');
    }

    /** @return array<string, int|null> classe de premier niveau du corps => scope effectif (héritage dans le corps) */
    private function scopes(string $body): array
    {
        $direct = [];
        $parent = [];
        $depth = 0;
        preg_match_all('~class\s+(\w+)\s*(?::\s*(\w+))?\s*(\{|;)|\{|\}|\bscope\s*=\s*(\d+)~', $body, $toks, PREG_SET_ORDER);
        $current = null;
        foreach ($toks as $tk) {
            if (str_starts_with($tk[0], 'class')) {
                if (($tk[3] ?? '') === '{') {
                    if ($depth === 0) {
                        $current = $tk[1];
                        $parent[$current] = ($tk[2] ?? '') !== '' ? $tk[2] : null;
                        $direct[$current] = null;
                    }
                    $depth++;
                }
            } elseif ($tk[0] === '{') {
                $depth++;
            } elseif ($tk[0] === '}') {
                $depth--;
            } elseif ($depth === 1 && $current !== null) {
                $direct[$current] = (int) $tk[4];
            }
        }
        $out = [];
        foreach (array_keys($direct) as $c) {
            $s = $direct[$c];
            for ($p = $parent[$c], $guard = 0; $s === null && $p !== null && array_key_exists($p, $direct) && $guard < 20; $p = $parent[$p], $guard++) {
                $s = $direct[$p];
            }
            $out[$c] = $s;
        }

        return $out;
    }

    /** @return list<string> */
    private function patchList(string $t, string $list): array
    {
        $patches = $this->topClassBody($t, 'CfgPatches');
        self::assertSame(1, preg_match('~\b' . preg_quote($list, '~') . '\[\]\s*=\s*\{([^}]*)\}~', $patches, $m), $list . '[] introuvable');
        preg_match_all('~"([^"]+)"~', $m[1], $names);

        return $names[1];
    }

    public function testEveryPublicClassIsListedInCfgPatches(): void
    {
        $t = $this->config();
        foreach (['CfgWeapons' => 'weapons', 'CfgVehicles' => 'units'] as $section => $list) {
            $scopes = $this->scopes($this->topClassBody($t, $section));
            self::assertGreaterThan(15, count($scopes), $section . ' : classes introuvables');
            $listed = $this->patchList($t, $list);
            $public = array_keys(array_filter($scopes, static fn (?int $s): bool => $s === 2));
            self::assertSame([], array_values(array_diff($public, $listed)), $section . ' : classes publiques absentes de ' . $list . '[]');
            self::assertSame([], array_values(array_diff($listed, array_keys($scopes))), $list . '[] nomme une classe non définie dans ' . $section);
            self::assertSame(count($listed), count(array_unique($listed)), $list . '[] contient un doublon');
        }
    }

    public function testFindingClassesAreListed(): void
    {
        $t = $this->config();
        $weapons = $this->patchList($t, 'weapons');
        self::assertContains('COMSPEC_ATAK_MPU5', $weapons);
        self::assertContains('COMSPEC_ATAK_Phone', $weapons);
        self::assertContains('Item_COMSPEC_ATAK_Phone', $this->patchList($t, 'units'));
    }
}
