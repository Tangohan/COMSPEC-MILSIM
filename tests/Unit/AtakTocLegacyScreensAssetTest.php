<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Premiers écrans TOC refaits (retour de TTRD, 11/11 : « des vieux écrans TOC horriblement laids ») : téléviseur, écrans
 * sur trépied, moniteurs, toiles de projection, vidéoprojecteur et PC portable sont des modèles du mod
 * (tools/model_toc_legacy.py) au lieu d'objets du jeu réhabillés. Les classes gardent leur nom (missions, sauvegardes).
 * Lecture de la config, du générateur et des fichiers ; le rendu en jeu se vérifie à part.
 */
final class AtakTocLegacyScreensAssetTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';

    /** Classe => [modèle, sélections cachées attendues (dans l'ordre)]. */
    private const CLASSES = [
        'COMSPEC_ATAK_Screen_Tripod' => ['toc_tripod_65', ['screen']],
        'COMSPEC_ATAK_Screen_Dual' => ['toc_tripod_dual_43', ['screen', 'screen2']],
        'COMSPEC_ATAK_Screen_Desk' => ['toc_desk_27', ['screen']],
        'COMSPEC_ATAK_Screen_Desk2' => ['toc_desk_27x2', ['panel1', 'panel2']],
        'COMSPEC_ATAK_Screen_Desk3' => ['toc_desk_27x3', ['panel1', 'panel2', 'panel3']],
        'COMSPEC_ATAK_Screen_Wall' => ['toc_tv_55', ['screen']],
        'COMSPEC_ATAK_Screen_Wall75' => ['toc_tv_75', ['screen']],
        'COMSPEC_ATAK_ProjScreen' => ['toc_proj_screen_tripod', ['canvas']],
        'COMSPEC_ATAK_ProjScreen_Wall' => ['toc_proj_screen_wall', ['canvas']],
        'COMSPEC_ATAK_Projector' => ['toc_projector', []],
        'COMSPEC_ATAK_Projector_Ceiling' => ['toc_projector_ceiling', []],
    ];

    private static function path(string $rel): string
    {
        return dirname(__DIR__, 2) . '/' . $rel;
    }

    private static function read(string $rel): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(self::path($rel)));
    }

    /** Corps d'une classe (accolades appariées) et nom de sa classe parente. */
    private static function classBody(string $src, string $name): array
    {
        self::assertSame(1, preg_match('/class ' . preg_quote($name, '/') . ':\s*(\w+)\s*\{/', $src, $m, PREG_OFFSET_CAPTURE), $name . ' : classe définie');
        $i = $m[0][1] + strlen($m[0][0]);
        $depth = 1;
        $start = $i;
        for ($len = strlen($src); $i < $len && $depth > 0; $i++) {
            $depth += ($src[$i] === '{') <=> ($src[$i] === '}');
        }

        return [substr($src, $start, $i - $start - 1), $m[1][0]];
    }

    /** Valeur d'une propriété, cherchée dans la classe puis dans ses parentes du même fichier. */
    private static function prop(string $src, string $name, string $prop): ?string
    {
        for ($n = 0; $n < 6; $n++) {
            if (preg_match('/class ' . preg_quote($name, '/') . ':\s*\w+\s*\{/', $src) !== 1) {
                return null;
            }
            [$body, $parent] = self::classBody($src, $name);
            if (preg_match('/(?:^|[;\s])' . preg_quote($prop, '/') . '\s*=\s*([^;]*);/', $body, $m) === 1) {
                return trim($m[1]);
            }
            $name = $parent;
        }

        return null;
    }

    public function testClassesKeepTheirNamesAndUseModModels(): void
    {
        $hpp = self::read(self::MAIN . 'toc_screens_legacy.hpp') . "\n" . self::read(self::MAIN . 'toc_screens3d.hpp');
        $cfg = self::read(self::MAIN . 'config.cpp');
        self::assertStringContainsString('#include "toc_screens_legacy.hpp"', $cfg);
        self::assertGreaterThan(strpos($cfg, '#include "toc_screens3d.hpp"'), strpos($cfg, '#include "toc_screens_legacy.hpp"'), 'après Base3D');
        self::assertSame(1, preg_match('/units\[\]=\{([^}]*)\}/', $cfg, $u));
        // Sections déclarées dans model.cfg : sans elles, la binarisation perd les sélections retexturables.
        $mcfg = self::read(self::MAIN . 'data/toc/model.cfg');
        foreach (self::CLASSES as $class => [$model, $sels]) {
            // Plus aucune de ces classes n'hérite d'un objet du jeu dans config.cpp (une seule définition, dans le hpp).
            self::assertSame(0, preg_match('/class ' . $class . ':\s*Land_/', $cfg), $class . ' : plus de modèle du jeu');
            self::assertStringContainsString('"' . $class . '"', $u[1], $class . ' : dans units[]');
            self::assertSame('"\\z\\comspec_atak_native\\addons\\main\\data\\toc\\' . $model . '.p3d"', self::prop($hpp, $class, 'model'), $class . ' : modèle');
            self::assertSame('"\\z\\comspec_atak_native\\addons\\main\\data\\toc\\' . $model . '_preview.jpg"', self::prop($hpp, $class, 'editorPreview'), $class . ' : aperçu');
            self::assertSame('2', self::prop($hpp, $class, 'scope'), $class . ' : scope');
            self::assertSame('2', self::prop($hpp, $class, 'scopeCurator'), $class . ' : scopeCurator');
            self::assertSame('3', self::prop($hpp, $class, 'side'), $class . ' : side=3 (listes Eden et Zeus)');
            self::assertSame('"COMSPEC_ATAK_EdSubcat_Screens"', self::prop($hpp, $class, 'editorSubcategory'), $class . ' : catégorie');
            foreach (['.p3d', '_preview.jpg'] as $ext) {
                self::assertFileExists(self::path(self::MAIN . 'data/toc/' . $model . $ext));
            }
            if ($sels !== []) {
                self::assertSame('{"' . implode('","', $sels) . '"}', self::prop($hpp, $class, 'hiddenSelections[]'), $class . ' : dalles');
                $sections = self::prop($mcfg, $model, 'sections[]');
                self::assertSame('{"' . implode('","', $sels) . '"}', $sections, $model . ' : sections de model.cfg');
                $p3d = (string) file_get_contents(self::path(self::MAIN . 'data/toc/' . $model . '.p3d'));
                foreach ($sels as $s) {
                    self::assertStringContainsString("\x01" . $s . "\x00", $p3d, $model . ' : sélection ' . $s . ' dans le modèle');
                }
            }
        }
    }

    public function testScreensProjectionAndLaptopBehaviourIsKept(): void
    {
        $hpp = self::read(self::MAIN . 'toc_screens_legacy.hpp') . "\n" . self::read(self::MAIN . 'toc_screens3d.hpp');
        // Écrans : dalle 0, matières jour / nuit du mod ; double écran : deuxième dalle recopiée comme avant.
        foreach (['COMSPEC_ATAK_Screen_Tripod', 'COMSPEC_ATAK_Screen_Dual', 'COMSPEC_ATAK_Screen_Desk', 'COMSPEC_ATAK_Screen_Wall'] as $c) {
            self::assertSame('1', self::prop($hpp, $c, 'comspec_screen'), $c);
            self::assertStringContainsString('comspec_screen_on.rvmat', (string) self::prop($hpp, $c, 'comspec_screenMat[]'), $c);
        }
        self::assertSame('{1}', self::prop($hpp, 'COMSPEC_ATAK_Screen_Dual', 'comspec_screenMirror[]'));
        self::assertSame('2', self::prop($hpp, 'COMSPEC_ATAK_Screen_Desk2', 'comspec_screenPanels'));
        self::assertSame('3', self::prop($hpp, 'COMSPEC_ATAK_Screen_Desk3', 'comspec_screenPanels'));
        // Toiles : jamais un écran lumineux, image seulement sous un projecteur allumé (fn_projector).
        foreach (['COMSPEC_ATAK_ProjScreen', 'COMSPEC_ATAK_ProjScreen_Wall'] as $c) {
            self::assertSame('1', self::prop($hpp, $c, 'comspec_projBoard'), $c);
            self::assertSame('0', self::prop($hpp, $c, 'comspec_screen'), $c);
            self::assertSame('{}', self::prop($hpp, $c, 'comspec_screenMat[]'), $c);
        }
        self::assertSame('1', self::prop($hpp, 'COMSPEC_ATAK_Projector', 'comspec_projector'));
        self::assertSame('1', self::prop($hpp, 'COMSPEC_ATAK_Projector_Ceiling', 'comspec_projector'));
        $proj = self::read(self::MAIN . 'functions/toc/fn_projector.sqf');
        self::assertStringContainsString('["COMSPEC_ATAK_ProjScreen", "COMSPEC_ATAK_ProjScreen_Wall"]', $proj);
        // PC portable du poste : même classe, modèle du mod, écran en sélection.
        $cfg = self::read(self::MAIN . 'config.cpp');
        [$lap] = self::classBody($cfg, 'COMSPEC_ATAK_TocLaptop');
        self::assertStringContainsString('data\\toc\\toc_laptop.p3d"', $lap);
        self::assertStringNotContainsString('Laptop_unfolded_F.p3d', $lap);
        self::assertStringContainsString('hiddenSelections[]={"screen"}', $lap);
        self::assertStringContainsString('side=3', $lap);
        self::assertFileExists(self::path(self::MAIN . 'data/toc/toc_laptop.p3d'));
        self::assertFileExists(self::path(self::MAIN . 'data/toc/toc_laptop_preview.jpg'));
        self::assertFileExists(self::path(self::MAIN . 'data/toc/toc_canvas.rvmat'));
    }

    public function testNamesAreFrenchAndEnglish(): void
    {
        $hpp = self::read(self::MAIN . 'toc_screens_legacy.hpp');
        $st = self::read(self::MAIN . 'stringtable.xml');
        foreach (array_keys(self::CLASSES) as $class) {
            $name = (string) self::prop($hpp, $class, 'displayName');
            self::assertSame(1, preg_match('/^"\$(STR_COMSPEC_ATAK_CFG_\w+)"$/', $name, $m), $class . ' : nom par clé');
            self::assertSame(1, preg_match('/<Key ID="' . $m[1] . '">\s*<Original>([^<]+)<\/Original>\s*<English>([^<]+)<\/English>\s*<French>([^<]+)<\/French>/', $st, $k), $class . ' : nom FR et EN');
            self::assertNotSame($k[2], $k[3], $class . ' : le français n\'est pas l\'anglais');
            self::assertLessThanOrEqual(48, mb_strlen($k[3]), $class . ' : nom court');
        }
    }

    public function testGeneratorKeepsEngineCenteredOriginExceptCeilingProjector(): void
    {
        $gen = self::read('mod/COMSPEC_ATAK_Native/tools/model_toc_legacy.py');
        self::assertStringContainsString('import model_toc_screens as T', $gen);
        foreach (self::CLASSES as [$model]) {
            self::assertSame(1, preg_match('/"' . $model . '": \(.*, (True|False)\),/', $gen, $m), $model . ' : dans le générateur');
            self::assertSame($model === 'toc_projector_ceiling' ? 'False' : 'True', $m[1], $model . ' : origine');
        }
        // Six LOD par modèle : 3 visuelles, ombre, géométrie, tir, vue (7 avec la géométrie de vue).
        $p3d = (string) file_get_contents(self::path(self::MAIN . 'data/toc/toc_tripod_65.p3d'));
        self::assertSame('MLOD', substr($p3d, 0, 4));
        self::assertSame(7, unpack('V', substr($p3d, 8, 4))[1]);
    }
}
