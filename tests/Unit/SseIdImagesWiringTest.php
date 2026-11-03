<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SseIdPhotoGuide;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseIdentitySqlite;

/**
 * Câblage des images des papiers fictifs (COMSPEC IDENTITY) : route d'envoi (droits, CSRF, journal), étape de
 * migration 3920 rejouable, champs d'envoi et aide « Photo d'identité façon photomaton » dans les pages, guide.
 */
final class SseIdImagesWiringTest extends TestCase
{
    private static function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path));
    }

    public function testUploadRouteChecksRightsCsrfAndUsesTheImageService(): void
    {
        $routes = self::read('routes/web.php');
        self::assertStringContainsString("\$router->post('/atak/sse/papiers/identites/{id}/images', [\\App\\Controllers\\Web\\SseIdWorkshopController::class, 'identityImages'], \$mwSsePortal);", $routes);
        $ctrl = self::read('app/Controllers/Web/SseIdWorkshopController.php');
        $action = substr($ctrl, (int) strpos($ctrl, 'public function identityImages('), 4000);
        self::assertStringContainsString("if (!\$r['manage'] || !Csrf::validate(", $action);
        self::assertStringContainsString('MemberIdentityPapersService::SECRET', $action, 'identité secrète réservée au Confidentiel');
        self::assertStringContainsString("'id_images.update'", $action);
        foreach (["storeUpload(\$_FILES['photo'] ?? null, 'photo'", "storeUpload(\$_FILES['signature'] ?? null, 'signature'", "storeUpload(\$_FILES['background'] ?? null, 'background'"] as $call) {
            self::assertStringContainsString($call, $ctrl);
        }
        self::assertStringNotContainsString('move_uploaded_file', $ctrl, 'plus de copie brute : réencodage sans métadonnées');
        $registry = self::read('app/Controllers/Web/SseIdentityRegistryController.php');
        self::assertStringContainsString("storeUpload(\$_FILES['flag'] ?? null, 'flag'", $registry);
        self::assertStringNotContainsString('move_uploaded_file', $registry);
    }

    public function testMigrationStepIsRegisteredAndReplayable(): void
    {
        self::assertFileExists(dirname(__DIR__, 2) . '/database/steps/3920_atak_sse_identity_images.php');
        self::assertStringContainsString("'file' => '3920_atak_sse_identity_images.php', 'rerun' => true", self::read('database/steps/registry.php'));
        self::assertStringContainsString('bootstrap/atak_sse_identity_images_migration.php', self::read('app/Repositories/SseIdentityRepository.php'));
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $pdo = SseIdentitySqlite::open();
        try {
            $again = SseIdentitySqlite::migrate($pdo);
            self::assertStringNotContainsString('[OK] sse_identities.signature_path', $again);
            self::assertContains('signature_path', array_column($pdo->query('PRAGMA table_info(sse_identities)')->fetchAll(\PDO::FETCH_ASSOC), 'name'));
            self::assertContains('background_path', array_column($pdo->query('PRAGMA table_info(sse_id_documents)')->fetchAll(\PDO::FETCH_ASSOC), 'name'));
        } finally {
            SseIdentitySqlite::close();
        }
    }

    public function testPagesOfferUploadsCropAndExistingPhotos(): void
    {
        $holder = self::read('views/atak/sse/papers/_holder_images.php');
        self::assertStringContainsString('enctype="multipart/form-data"', $holder);
        self::assertStringContainsString('Csrf::field()', $holder);
        self::assertStringContainsString("if (!empty(\$canEditImages))", $holder, 'formulaire réservé aux droits de l’atelier');
        self::assertStringContainsString("require __DIR__ . '/_holder_images.php';", self::read('views/atak/sse/papers/identity_show.php'));
        self::assertStringContainsString("require __DIR__ . '/_holder_images.php';", self::read('views/atak/sse/papers/document_show.php'));
        self::assertStringContainsString("\$data['canEditImages'] ??= \$workshopRights['manage'];", self::read('app/Controllers/Web/SseIdentityRegistryController.php'));
        $input = self::read('views/atak/sse/papers/_photo_input.php');
        foreach (['data-crop', 'data-crop-panel', 'value="35x45"', 'value="1x1"', 'name="photo_existing"', 'name="photo_remove"'] as $needle) {
            self::assertStringContainsString($needle, $input);
        }
        $editor = self::read('views/atak/sse/papers/workshop_editor.php');
        foreach (["require __DIR__ . '/_photo_input.php';", 'name="signature"', 'name="background"', "require __DIR__ . '/_photo_guide.php';", 'assets/js/sse-id-images.js'] as $needle) {
            self::assertStringContainsString($needle, $editor);
        }
        $js = self::read('public/assets/js/sse-id-images.js');
        self::assertStringContainsString('new DataTransfer()', $js);
        self::assertStringContainsString("'35x45': [700, 900]", $js);
        self::assertStringContainsString("'custom'", self::read('public/assets/js/sse-id-workshop.js'));
        self::assertStringContainsString('data-bind-img', self::read('public/assets/js/sse-id-workshop.js'));
    }

    public function testPhotoBoothGuideAndPromptsInPageAndDocs(): void
    {
        $guide = self::read('views/atak/sse/papers/_photo_guide.php');
        $doc = self::read('docs/guide/photo-identite.md');
        self::assertStringContainsString('Photo d’identité façon photomaton', $guide);
        self::assertStringContainsString('<details class="idi-guide">', $guide, 'aide repliable');
        self::assertStringContainsString('Uniquement des personnages du jeu.', $guide);
        self::assertStringContainsString('translate="no" data-prompt', $guide, 'le prompt à copier n’est pas traduit par le site');
        self::assertSame(2, substr_count($guide, 'data-copy-prompt="'));
        self::assertStringContainsString('SseIdPhotoGuide::prompt($lang)', $guide);
        $fr = SseIdPhotoGuide::prompt('fr');
        $en = SseIdPhotoGuide::prompt('en');
        foreach (['personnage fictif de jeu vidéo, pas une vraie personne', 'aucune personne réelle identifiable', 'façon photomaton', 'flash diffus', 'Fond uni gris clair ou blanc cassé', '35 × 45 mm', 'bouche fermée', 'sauf couvre-chef religieux'] as $needle) {
            self::assertStringContainsString($needle, $fr);
        }
        foreach (['fictional video game character, not a real person', 'photo booth', '35 × 45 mm', 'except religious headwear'] as $needle) {
            self::assertStringContainsString($needle, $en);
        }
        self::assertStringContainsString(str_replace('’', "'", $fr), $doc, 'même prompt dans la page et le guide');
        self::assertStringContainsString($en, $doc);
        foreach (['tired' => 'Fatigué', 'beard' => 'Barbe', 'scar' => 'Cicatrice', 'older' => 'Plus âgé'] as $k => $variant) {
            self::assertStringContainsString("'" . $k . "' => '" . $variant . "'", $guide);
            self::assertStringContainsString('| ' . $variant . ' | ' . str_replace('’', "'", SseIdPhotoGuide::VARIANTS[$k]['fr']) . ' | ' . SseIdPhotoGuide::VARIANTS[$k]['en'] . ' |', $doc);
        }
        self::assertStringContainsString('vraies photos Athena', $doc, 'photo pas encore envoyée au jeu : suite prévue');
    }
}
