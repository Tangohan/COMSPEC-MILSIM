<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Retrait des fichiers morts des mods (images en double d'Overwatch connect, polices hors du choix, tablette du JPC) :
 * aucun script ni config ne cite un fichier absent, les fonds d'écran du téléphone sont tous là.
 */
final class ModDeadFilesRemovedAssetTest extends TestCase
{
    private const CONNECT = '/mod/UptoDate/Sources/comspec-overwatch-addons/connect';
    private const ATAK_MAIN = '/mod/COMSPEC_ATAK_Native/Sources/addons/main';
    private const ATAK_ITEMS = '/mod/COMSPEC_ATAK_Native/Sources/addons/items';

    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    /** @return list<string> chemins relatifs au dossier, en minuscules, séparés par « / » */
    private static function filesLower(string $dir): array
    {
        $out = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->isFile()) {
                $out[] = strtolower(str_replace('\\', '/', substr($file->getPathname(), strlen($dir) + 1)));
            }
        }
        sort($out);

        return $out;
    }

    /** @return array<string, string> fichier source => contenu (scripts et configs d'Arma) */
    private static function sources(string $dir): array
    {
        $out = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->isFile() && in_array(strtolower($file->getExtension()), ['sqf', 'hpp', 'cpp', 'ext', 'h'], true)) {
                $out[$file->getPathname()] = (string) file_get_contents($file->getPathname());
            }
        }

        return $out;
    }

    /**
     * Chemins de fichiers cités sous un préfixe de PBO, relatifs à ce préfixe, en minuscules. Un chemin construit à
     * l'exécution (« %1 ») n'est pas un nom de fichier : il est ignoré ici.
     *
     * @return array<string, string> chemin relatif => premier fichier source qui le cite
     */
    private static function citedPaths(string $sourcesDir, string $prefix, string $extensions): array
    {
        $pattern = '~' . str_replace('/', '[\\\\/]+', preg_quote($prefix, '~'))
            . '[\\\\/]+([A-Za-z0-9_\\-\\\\/ .]+?\.(?:' . $extensions . '))(?![A-Za-z0-9])~i';
        $out = [];
        foreach (self::sources($sourcesDir) as $path => $src) {
            if (preg_match_all($pattern, $src, $m) < 1) {
                continue;
            }
            foreach ($m[1] as $rel) {
                $out[strtolower(str_replace('\\', '/', $rel))] ??= basename($path);
            }
        }
        ksort($out);

        return $out;
    }

    public function testEveryConnectImageCitedByAScriptOrConfigExists(): void
    {
        $root = self::root();
        $present = self::filesLower($root . self::CONNECT . '/img');
        $cited = [];
        foreach (['/mod/UptoDate/Sources/comspec-overwatch-addons', '/mod/COMSPEC_ATAK_Native/Sources/addons', '/mod/@COMSPEC_SSE/addons'] as $dir) {
            $cited += self::citedPaths($root . $dir, 'comspec_overwatch/addons/connect/img', 'paa|png|jpe?g|pac|tga');
        }
        self::assertGreaterThan(20, count($cited), 'les chemins d\'images de connect/img sont bien extraits');
        self::assertArrayHasKey('atak-fx/broken-screen.paa', $cited);
        foreach ($cited as $rel => $by) {
            self::assertContains($rel, $present, "image citée par $by mais absente de connect/img : $rel");
        }
    }

    public function testNoConnectImageIsCitedAsPngAndConvertedTexturesArePresent(): void
    {
        $root = self::root();
        $cited = self::citedPaths($root . '/mod/UptoDate/Sources/comspec-overwatch-addons', 'comspec_overwatch/addons/connect/img', 'paa|png|jpe?g|pac|tga');
        foreach ($cited as $rel => $by) {
            // Arma n'affiche pas un PNG en texture : seuls .paa (et .jpg) sont lus.
            self::assertFalse(str_ends_with($rel, '.png'), "texture PNG citée par $by : $rel");
        }
        $converted = [
            'overlays/comspec_icon_jammer_ca.paa', 'overlays/comspec_icon_no_coverage_ca.paa',
            'overlays/comspec_icon_device_destroyed_ca.paa', 'overlays/comspec_icon_reboot_ca.paa',
            'overlays/comspec_icon_repair_ca.paa', 'ecoti/ecoti_glow_ca.paa', 'ecoti/ecoti_icon_bracket_ca.paa',
            'ecoti/ecoti_vignette_ca.paa',
        ];
        foreach ($converted as $rel) {
            self::assertArrayHasKey($rel, $cited, "texture convertie encore citée : $rel");
            $file = $root . self::CONNECT . '/img/' . $rel;
            self::assertTrue(is_file($file), "texture convertie absente : $rel");
            self::assertGreaterThan(4096, (int) filesize($file), "texture convertie trop petite : $rel");
        }
    }

    public function testNoDuplicatePngOrJpgIsLeftInConnectImg(): void
    {
        $left = array_values(array_filter(
            self::filesLower(self::root() . self::CONNECT . '/img'),
            static fn (string $f): bool => preg_match('/\.(png|jpe?g)$/', $f) === 1
        ));
        self::assertSame([], $left, 'connect/img ne contient plus que des textures .paa');
    }

    public function testEveryDeclaredFontPageExistsAndNoUnusedFamilyIsShipped(): void
    {
        $root = self::root();
        $hpp = (string) file_get_contents($root . self::ATAK_MAIN . '/ui/fonts.hpp');
        $present = self::filesLower($root . self::ATAK_MAIN . '/fonts');
        self::assertSame(
            1,
            preg_match_all('~comspec_atak_native[\\\\/]+addons[\\\\/]+main[\\\\/]+fonts[\\\\/]+([A-Za-z0-9_\\-\\\\/]+)"~i', $hpp, $m) > 0 ? 1 : 0,
            'fonts.hpp cite des polices embarquées'
        );
        $families = [];
        foreach (array_unique($m[1]) as $rel) {
            $rel = strtolower(str_replace('\\', '/', $rel));
            $families[dirname($rel)] = true;
            self::assertContains($rel . '.fxy', $present, "police déclarée dans fonts.hpp mais absente : $rel.fxy");
        }
        // Chaque dossier de police livré est déclaré : pas de famille embarquée sans classe.
        $shipped = [];
        foreach ($present as $file) {
            $shipped[explode('/', $file)[0]] = true;
        }
        self::assertSame(array_keys($families), array_keys($shipped));
        foreach (['COMSPEC_B612', 'COMSPEC_Rajdhani', 'COMSPEC_ShareTech'] as $class) {
            self::assertStringNotContainsString($class, $hpp);
        }
        // Les familles déclarées restent celles que le choix de police propose (fn_fontSet).
        $set = (string) file_get_contents($root . self::ATAK_MAIN . '/functions/ui/fn_fontSet.sqf');
        self::assertSame(1, preg_match_all('/"(COMSPEC_[A-Za-z0-9]+)"/', $set, $used) > 0 ? 1 : 0);
        foreach (array_unique($used[1]) as $class) {
            self::assertStringContainsString('class ' . $class . ' {', $hpp, "famille proposée mais non déclarée : $class");
        }
    }

    public function testPhoneWallpapersAreAllKept(): void
    {
        $root = self::root();
        $isWall = static fn (string $f): bool => preg_match('~^(walls/)?wall_[a-z0-9]+(_blur)?_(land|port)\.paa$~', $f) === 1;
        $main = array_values(array_filter(self::filesLower($root . self::ATAK_MAIN . '/data'), $isWall));
        $items = array_values(array_filter(self::filesLower($root . self::ATAK_ITEMS . '/data'), $isWall));
        self::assertCount(32, $main, 'fonds d\'écran de main/data (8 fonds, 4 fichiers chacun)');
        self::assertCount(20, $items, 'fonds d\'écran de items/data/walls (5 fonds, 4 fichiers chacun)');
        foreach ([[$main, self::ATAK_MAIN], [$items, self::ATAK_ITEMS]] as [$list, $dir]) {
            foreach ($list as $rel) {
                self::assertGreaterThan(10000, (int) filesize($root . $dir . '/data/' . $rel), "fond d'écran vide : $rel");
            }
        }
    }

    public function testEveryModelAndTextureOfTheMainConfigExists(): void
    {
        $root = self::root();
        $present = self::filesLower($root . self::ATAK_MAIN);
        $config = (string) file_get_contents($root . self::ATAK_MAIN . '/config.cpp');
        self::assertSame(
            1,
            preg_match_all('~"\\\\?z[\\\\/]+comspec_atak_native[\\\\/]+addons[\\\\/]+main[\\\\/]+([A-Za-z0-9_\\-\\\\/.]+\.(?:p3d|paa|rvmat))"~i', $config, $m) > 0 ? 1 : 0
        );
        self::assertGreaterThan(50, count(array_unique($m[1])));
        foreach (array_unique($m[1]) as $rel) {
            $rel = strtolower(str_replace('\\', '/', $rel));
            self::assertContains($rel, $present, "fichier cité par config.cpp mais absent : $rel");
        }
        // Tablette du JPC retirée : la classe reste (parente des téléphones du selfie), cachée, sur un modèle présent.
        self::assertSame(1, preg_match('/class COMSPEC_ATAK_JpcTablet: Static \{\s*scope=1;[^}]*?model="([^"]+)"/', $config, $jpc));
        self::assertStringNotContainsString('jpc_tablet', strtolower($jpc[1]));
        self::assertStringNotContainsString('jpc_tablet_co', $config);
        foreach (['data/jpc_tablet.p3d', 'data/jpc_tablet_co.paa', 'data/logo_soar.paa', 'data/dirt_drop.paa', 'data/dirt_smear_0.paa'] as $gone) {
            self::assertNotContains($gone, $present);
        }
    }
}
