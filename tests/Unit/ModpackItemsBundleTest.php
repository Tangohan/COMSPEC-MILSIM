<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Modpack\ModpackBundle;
use App\Services\Modpack\ModpackItems;
use PHPUnit\Framework\TestCase;

/**
 * Téléchargement à l'unité et groupé des mods : liste blanche du manifeste, refus des identifiants inconnus,
 * archive ZIP valide, manifeste en phase avec les fichiers réels du dépôt, page au thème boutique.
 */
final class ModpackItemsBundleTest extends TestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/mpk-test-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/mod/alpha/addons', 0777, true);
        mkdir($this->dir . '/mod/beta', 0777, true);
        file_put_contents($this->dir . '/mod/alpha/addons/a.pbo', 'AAAA');
        file_put_contents($this->dir . '/mod/alpha/mod.cpp', 'name="alpha";');
        file_put_contents($this->dir . '/mod/beta/mod.cpp', 'name="beta";');
        file_put_contents($this->dir . '/secret.txt', 'hors dossier source');
        $file = static fn (string $path, string $content): array => ['path' => $path, 'size' => strlen($content), 'sha256' => hash('sha256', $content)];
        $items = [
            ['id' => 'alpha', 'name' => 'Alpha', 'ico' => 'ALP', 'version' => '1.2.0', 'folder' => '@Alpha', 'source' => 'mod/alpha', 'files' => [$file('addons/a.pbo', 'AAAA'), $file('mod.cpp', 'name="alpha";')]],
            ['id' => 'beta', 'name' => 'Beta', 'ico' => 'BET', 'version' => null, 'folder' => '@Beta', 'source' => 'mod/beta', 'files' => [$file('mod.cpp', 'name="beta";')]],
            ['id' => 'evil', 'name' => 'Evil', 'ico' => 'EVL', 'version' => null, 'folder' => '@Evil', 'source' => 'mod/alpha', 'files' => [$file('../../../secret.txt', 'hors dossier source')]],
            ['id' => 'gone', 'name' => 'Gone', 'ico' => 'GON', 'version' => null, 'folder' => '@Gone', 'source' => 'mod/alpha', 'files' => [$file('absent.pbo', 'x')]],
            ['id' => 'soon', 'name' => 'Soon', 'ico' => 'SON', 'version' => null, 'folder' => null, 'source' => null, 'files' => []],
        ];
        file_put_contents($this->dir . '/items.json', json_encode(['version' => 1, 'items' => $items]));
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $node) {
            $node->isDir() ? rmdir($node->getPathname()) : unlink($node->getPathname());
        }
        rmdir($this->dir);
    }

    private function items(): ModpackItems
    {
        return new ModpackItems($this->dir . '/items.json', $this->dir);
    }

    private function refused(callable $call): string
    {
        try {
            $call();
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return $e->getMessage();
        }
        return '';
    }

    public function testOnlyManifestIdsAreFound(): void
    {
        $items = $this->items();
        self::assertNotNull($items->find('alpha'));
        self::assertNull($items->find('gamma'));
        self::assertNull($items->find('../alpha'));
        self::assertNull($items->find('ALPHA'));
        self::assertNull($items->find(''));
    }

    public function testDownloadableNeedsEveryFileWithTheExpectedSize(): void
    {
        $items = $this->items();
        self::assertTrue($items->isDownloadable($items->find('alpha')));
        self::assertFalse($items->isDownloadable($items->find('gone')), 'fichier absent');
        self::assertFalse($items->isDownloadable($items->find('soon')), 'aucun fichier au manifeste');
        self::assertFalse($items->isDownloadable($items->find('evil')), 'le chemin ../ est refusé par le manifeste');
        file_put_contents($this->dir . '/mod/beta/mod.cpp', 'remplacé');
        self::assertFalse($items->isDownloadable($items->find('beta')), 'taille différente du manifeste');
    }

    public function testTraversalAndUnknownIdsAreRefused(): void
    {
        $bundle = new ModpackBundle($this->items());
        self::assertSame('unknown_item', $this->refused(fn () => $bundle->build(['alpha', 'inconnu'])));
        self::assertSame('unknown_item', $this->refused(fn () => $bundle->build(['../../etc/passwd'])));
        self::assertSame('unknown_item', $this->refused(fn () => $bundle->build([['alpha']])));
        self::assertSame('unavailable_item', $this->refused(fn () => $bundle->build(['evil'])));
        self::assertSame('unavailable_item', $this->refused(fn () => $bundle->build(['soon'])));
        self::assertSame('empty_selection', $this->refused(fn () => $bundle->build([])));
    }

    public function testSizeCeilingStopsOversizedSelections(): void
    {
        $bundle = new ModpackBundle($this->items(), 10);
        self::assertSame('too_large', $this->refused(fn () => $bundle->build(['alpha'])));
    }

    public function testBundleIsAValidZipWithOneFolderPerModAndNoDuplicates(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            return;
        }
        $built = (new ModpackBundle($this->items()))->build(['beta', 'alpha', 'alpha'], $this->dir);
        try {
            self::assertSame('COMSPEC-Selection.zip', $built['name']);
            self::assertSame(filesize($built['path']), $built['bytes']);
            $zip = new \ZipArchive();
            self::assertTrue($zip->open($built['path']) === true);
            $names = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $names[] = $zip->getNameIndex($i);
            }
            self::assertSame(['@Alpha/addons/a.pbo', '@Alpha/mod.cpp', '@Beta/mod.cpp'], $names);
            self::assertSame('AAAA', $zip->getFromName('@Alpha/addons/a.pbo'));
            $zip->close();
        } finally {
            unlink($built['path']);
        }
    }

    public function testSingleDownloadIsNamedAfterTheModAndItsVersion(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            return;
        }
        $built = (new ModpackBundle($this->items()))->build(['alpha'], $this->dir);
        unlink($built['path']);
        self::assertSame('COMSPEC-alpha-1.2.0.zip', $built['name']);
    }

    public function testManifestMatchesTheRealFilesOfTheRepository(): void
    {
        $root = dirname(__DIR__, 2);
        $items = new ModpackItems($root . '/config/modpack_items.json', $root);
        $all = $items->all();
        foreach (['atak', 'sse', 'lobby', 'flashbang', 'sof_base'] as $id) {
            self::assertArrayHasKey($id, $all);
        }
        self::assertTrue($items->isDownloadable($all['sse']));
        self::assertTrue($items->isDownloadable($all['sof_base']));
        self::assertTrue($all['atak']['in_pack']);
        self::assertSame('https://steamcommunity.com/sharedfiles/filedetails/?id=3837805212', $all['flashbang']['workshop_url']);
        foreach ($all as $item) {
            foreach ($item['files'] as $file) {
                self::assertSame($file['sha256'], hash_file('sha256', $items->filePath($item, $file)), $item['id'] . '/' . $file['path']);
            }
        }
    }

    public function testPageUsesTheShopThemeAndOffersBothDownloadModes(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/site/public_modpack.php');
        $css = (string) file_get_contents($root . '/public/assets/css/public-modpack.css');
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $controller = (string) file_get_contents($root . '/app/Controllers/Web/PublicModpackController.php');

        self::assertSame(1, substr_count($view, '<h1'));
        self::assertDoesNotMatchRegularExpression('/class="[^"]*(kicker|eyebrow|overline|surtitre)/i', $view);
        self::assertDoesNotMatchRegularExpression('/\.[\w-]*(kicker|eyebrow|overline|surtitre)/i', $css);
        self::assertStringContainsString('name="mods[]"', $view);
        self::assertStringContainsString("url('modpacks/telecharger-selection')", $view);
        self::assertStringContainsString("url('modpacks/telecharger-mod')", $view);
        self::assertStringContainsString('SHA-256', $view);
        self::assertStringContainsString("url('soutenir-atak')", $view);
        self::assertStringContainsString("'/modpacks/telecharger-mod'", $routes);
        self::assertStringContainsString("'/modpacks/telecharger-selection'", $routes);
        self::assertStringContainsString("'X-Content-Type-Options', 'nosniff'", $controller);
        self::assertStringContainsString("'Content-Type', 'application/zip'", $controller);
        self::assertStringContainsString('@unlink($path)', $controller);
        self::assertFileExists($root . '/public/assets/js/public-modpack-select.js');

        $fr = require $root . '/lang/fr/modpack.php';
        $en = require $root . '/lang/en/modpack.php';
        self::assertSame([], array_keys(array_diff_key($fr, $en)));
        self::assertSame([], array_keys(array_diff_key($en, $fr)));
        foreach (['atak', 'sse', 'lobby', 'flashbang', 'sof_base'] as $id) {
            foreach (['short', 'long'] as $part) {
                self::assertNotSame($fr["item_{$id}_{$part}"], $en["item_{$id}_{$part}"]);
            }
        }
    }
}
