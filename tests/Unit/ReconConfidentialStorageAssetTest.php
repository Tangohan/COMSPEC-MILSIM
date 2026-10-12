<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ReconImageStorage;
use PHPUnit\Framework\TestCase;

/**
 * Clichés confidentiels : rangés hors racine web (storage/private/recon), nom aléatoire,
 * servis seulement par GET /api/recon/images/{id}/file (habilitation « confidentiel », 404 sinon).
 */
final class ReconConfidentialStorageAssetTest extends TestCase
{
    private function read(string $rel): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $rel);
    }

    public function testPrivatePathsAndNames(): void
    {
        self::assertSame('storage/private/recon', ReconImageStorage::PRIVATE_DIR);
        self::assertStringStartsNotWith('public/', ReconImageStorage::PRIVATE_DIR);
        // public/index.php ne sert en repli que storage/uploads/… : storage/private n'en fait pas partie.
        self::assertStringStartsNotWith('storage/uploads', ReconImageStorage::PRIVATE_DIR);
        $name = ReconImageStorage::privateFileName('JPG');
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}\.jpg$/', $name);
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}\.jpg$/', ReconImageStorage::privateFileName('php'));
        self::assertSame('recon-private/' . $name, ReconImageStorage::privateRelativeImagePath($name));
        self::assertTrue(ReconImageStorage::isPrivatePath('recon-private/' . $name));
        self::assertFalse(ReconImageStorage::isPrivatePath('recon/recon_x.jpg'));
    }

    public function testUrlForRowNeverExposesUploadsForConfidential(): void
    {
        $url = (string) ReconImageStorage::urlForRow(['id' => 42, 'image_path' => 'recon-private/' . str_repeat('a', 32) . '.jpg', 'is_confidential' => 1]);
        self::assertStringContainsString('/api/recon/images/42/file', $url);
        self::assertStringNotContainsString('/uploads/', $url);
        // Drapeau posé avant la migration des fichiers : déjà la route authentifiée.
        $legacy = (string) ReconImageStorage::urlForRow(['id' => 7, 'image_path' => 'recon/recon_x.jpg', 'is_confidential' => 1]);
        self::assertStringContainsString('/api/recon/images/7/file', $legacy);
        $open = (string) ReconImageStorage::urlForRow(['id' => 8, 'image_path' => 'recon/recon_x.jpg', 'is_confidential' => 0]);
        self::assertStringContainsString('/uploads/recon/recon_x.jpg', $open);
    }

    public function testStorageWritesDenyRuleAndRelocates(): void
    {
        $src = $this->read('app/Support/ReconImageStorage.php');
        self::assertStringContainsString('function ensurePrivateDir', $src);
        self::assertStringContainsString('Require all denied', $src);
        self::assertStringContainsString('function relocate(string $imagePath, bool $confidential)', $src);
        self::assertStringContainsString('bool $confidential = false', $src);
        self::assertStringContainsString('Require all denied', $this->read('storage/private/.htaccess'));
        self::assertStringContainsString('!/storage/private/.htaccess', $this->read('.gitignore'));
    }

    public function testControllerStoresConfidentialPrivatelyAndServesThroughRoute(): void
    {
        $ctrl = $this->read('app/Controllers/Api/AtakApiController.php');
        self::assertStringContainsString('ReconImageStorage::privateFileName($ext)', $ctrl);
        self::assertStringContainsString('storeFromTemp((string) $file[\'tmp_name\'], $filename, $storeConfidential)', $ctrl);
        self::assertStringContainsString('ReconImageStorage::delete($storedImagePath)', $ctrl);
        self::assertStringContainsString('public function reconImagesFile(', $ctrl);
        self::assertStringContainsString("'no-store, private, max-age=0'", $ctrl);
        self::assertStringContainsString('canViewConfidentialRecon()', $ctrl);
        self::assertStringNotContainsString('ReconImageStorage::publicUrl(', $ctrl);
        // Relais Discord : cliché confidentiel relayé sans image (message, lien de lecture habilité, note).
        self::assertStringContainsString("'read_url' => (string) (\$row['url'] ?? '')", $ctrl);
        self::assertStringNotContainsString("if (\$isConfidential) {\n                    return;", $ctrl);

        $file = substr($ctrl, (int) strpos($ctrl, 'public function reconImagesFile('), 2400);
        self::assertStringContainsString('return $notFound();', $file);
        self::assertStringNotContainsString('403', $file);

        self::assertStringContainsString(
            "\$router->get('/api/recon/images/{id}/file', [AtakApiController::class, 'reconImagesFile']);",
            $this->read('routes/web.php')
        );

        $intel = $this->read('app/Controllers/Api/AtakOverwatchIntelController.php');
        self::assertStringContainsString('COALESCE(is_confidential, 0) = 0', $intel);
        self::assertStringContainsString('ReconImageStorage::urlForRow($r)', $intel);
    }

    public function testMigrationMovesExistingConfidentialFiles(): void
    {
        $mig = $this->read('bootstrap/atak_recon_images_confidential_storage_migration.php');
        self::assertStringContainsString('/storage/private/recon', $mig);
        self::assertStringContainsString("NOT LIKE 'recon-private/%'", $mig);
        self::assertStringContainsString('bin2hex(random_bytes(16))', $mig);
        self::assertStringContainsString("'atak_recon_images_confidential_storage_migration.php'", $this->read('run-migrations.php'));
    }

    public function testFeedsDoNotRebuildUploadsUrlForPrivatePaths(): void
    {
        foreach (['public/assets/js/atak-overwatch-beta.js', 'public/assets/js/tacmap-recon.js'] as $js) {
            self::assertStringContainsString("indexOf('recon-private/') !== 0", $this->read($js), $js);
        }
    }
}
