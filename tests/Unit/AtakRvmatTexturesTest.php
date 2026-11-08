<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Textures des rvmat du mod : chemin sans « \ » de tête (comme ACE et les rvmat de BI) et fichier présent dans l'addon.
 * « texture="\z\..._nohq.paa" » : fichier présent dans le PBO, mais « Cannot load texture » et une boîte d'erreur à l'écran
 * du joueur (autotest du 8/11, captures 153 et 154). Les chemins des modèles p3d, sans « \ », se chargeaient.
 */
final class AtakRvmatTexturesTest extends TestCase
{
    private const ADDONS = __DIR__ . '/../../mod/COMSPEC_ATAK_Native/Sources/addons';

    public function testCheminsDesTexturesDesRvmat(): void
    {
        $files = array_merge(
            glob(self::ADDONS . '/*/data/*.rvmat') ?: [],
            glob(self::ADDONS . '/*/data/*/*.rvmat') ?: []
        );
        self::assertNotEmpty($files);
        $n = 0;
        foreach ($files as $f) {
            $src = (string) file_get_contents($f);
            preg_match_all('/texture\s*=\s*"([^"#][^"]*)"/', $src, $m);
            foreach ($m[1] as $path) {
                $n++;
                self::assertStringStartsNotWith('\\', $path, basename($f) . ' : ' . $path);
                if (preg_match('/^z\\\\comspec_atak_native\\\\addons\\\\(\w+)\\\\(.+)$/i', $path, $p) === 1) {
                    $local = self::ADDONS . '/' . $p[1] . '/' . str_replace('\\', '/', $p[2]);
                    self::assertFileExists($local, basename($f) . ' : ' . $path);
                }
            }
        }
        self::assertGreaterThan(0, $n);
    }
}
