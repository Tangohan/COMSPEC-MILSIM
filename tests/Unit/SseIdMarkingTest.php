<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\SseFileRecordRepository;
use App\Support\SseIdMarking;
use PHPUnit\Framework\TestCase;

/**
 * Atelier de papiers : peu de « simulation » à l'écran, marquage discret de l'image exportée (réglage de communauté),
 * mention dans les métadonnées, pays réel stylisé non désactivable.
 */
final class SseIdMarkingTest extends TestCase
{
    private static function read(string $rel): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $rel);
    }

    public function testModesDefaultToDiscreetAndRejectUnknownValues(): void
    {
        self::assertSame('discreet', SseIdMarking::normalize(null));
        self::assertSame('discreet', SseIdMarking::normalize('zzz'));
        self::assertSame('standard', SseIdMarking::normalize('standard'));
        self::assertSame('none', SseIdMarking::normalize('none'));
        self::assertSame('discreet', SseFileRecordRepository::normalizeSettings([])['papers_marking']);
        self::assertSame('standard', SseFileRecordRepository::normalizeSettings(['papers_marking' => 'standard'])['papers_marking']);
        self::assertSame('discreet', SseFileRecordRepository::normalizeSettings(['papers_marking' => 'bad'])['papers_marking']);
    }

    public function testRealCountryCanNeverBeWithoutVisibleMarking(): void
    {
        self::assertSame('none', SseIdMarking::effective('none', false), 'pays imaginaire : possible');
        self::assertSame('discreet', SseIdMarking::effective('none', true), 'pays réel : discret imposé');
        self::assertSame('standard', SseIdMarking::effective('standard', true));
        self::assertSame('discreet', SseIdMarking::effective('discreet', true));
        self::assertTrue(SseIdMarking::canDisableVisible(false));
        self::assertFalse(SseIdMarking::canDisableVisible(true));
    }

    public function testPngMetadataCarriesTheGameMention(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);
        self::assertIsString($png);
        self::assertFalse(SseIdMarking::hasMention($png));
        $marked = SseIdMarking::pngWithMention($png);
        self::assertTrue(SseIdMarking::hasMention($marked));
        self::assertStringStartsWith("\x89PNG\r\n\x1a\n", $marked);
        self::assertStringEndsWith(substr($png, 33), $marked, 'données de l’image intactes');
        self::assertSame('not a png', SseIdMarking::pngWithMention('not a png'));
    }

    public function testWorkshopScreensKeepAtMostOneSimulationMention(): void
    {
        $count = 0;
        foreach (['workshop_editor', '_person_block', '_photo_guide', '_holder_images', 'countries', 'kit_templates', 'briefs', 'document_show'] as $v) {
            $count += substr_count(self::read('views/atak/sse/papers/' . $v . '.php'), 'SIMULATION');
            self::assertStringNotContainsString('simulation', mb_strtolower(self::read('views/atak/sse/papers/' . $v . '.php')), $v);
        }
        self::assertSame(0, $count);
        // Le bandeau commun porte l'unique mention sobre de chaque écran.
        self::assertSame(1, substr_count(self::read('views/atak/sse/papers/_subnav.php'), 'sans valeur réelle'));
        // L'aperçu ne porte le filigrane et le badge SIMULATION que dans le mode « standard ».
        $preview = self::read('views/atak/sse/papers/_doc_preview.php');
        foreach (explode("
", $preview) as $line) {
            if (str_contains($line, 'SIMULATION') && !str_starts_with(ltrim($line), '*') && !str_contains($line, '$pvStandard') && !str_contains($line, 'idw-watermark')) {
                self::fail('SIMULATION hors mode standard : ' . $line);
            }
        }
        self::assertStringContainsString('$pvStandard', $preview);
    }

    public function testExportStampsDiscreetMarkAndMetadataInTheImage(): void
    {
        $js = self::read('public/assets/js/sse-id-workshop.js');
        self::assertStringContainsString('function stampDiscreet', $js);
        self::assertStringContainsString("var MICRO = 'Document de jeu – sans valeur · Game document – no value';", $js);
        self::assertStringContainsString("if (mode === 'standard') stampStandard", $js);
        self::assertStringContainsString("else if (mode === 'discreet') stampDiscreet", $js);
        self::assertStringContainsString("['Description', META]", $js, 'métadonnées PNG tEXt');
        self::assertLessThan(strpos($js, 'pngWithMention(canvas.toDataURL'), strpos($js, 'stampGameMarks(canvas);'));
        // Aucun réglage utilisateur de la page ne coupe le marquage : seule la valeur serveur (data-marking) compte.
        self::assertStringNotContainsString('localStorage.getItem(\'idw-marking', $js);
        self::assertStringContainsString("if (m === 'none' && real) return 'discreet';", $js);
        $css = self::read('public/assets/css/sse-id-workshop.css');
        self::assertStringContainsString('#idw-preview[data-marking="discreet"] .idw-face::after', $css, 'PDF : mention de jeu à l’impression');
    }

    public function testCommunitySettingIsOnTheFilesSettingsScreen(): void
    {
        $view = self::read('views/admin/sse_files/index.php');
        self::assertStringContainsString('name="papers_marking"', $view);
        foreach (['discreet', 'standard', 'none'] as $mode) {
            self::assertStringContainsString('<option value="' . $mode . '"', $view);
        }
        $ctrl = self::read('app/Controllers/Admin/AdminSseFilesController.php');
        self::assertStringContainsString("'papers_marking' => \\App\\Support\\SseIdMarking::normalize(", $ctrl);
        // Lecture par communauté (tenant) dans l'atelier.
        self::assertStringContainsString('->settings($tenantId)', self::read('app/Controllers/Web/SseIdWorkshopController.php'));
    }

    public function testCommunityCoversAndWantedNoticeGuardrailsAreUntouched(): void
    {
        // #1672 : le pied de la notice et ses propriétés PDF restent en place.
        $settings = self::read('views/atak/sse/papers/wanted_settings.php');
        self::assertStringContainsString('Document de fiction — jeu de rôle Arma 3', $settings);
        self::assertStringContainsString('Le fichier PDF garde toujours cette mention dans ses propriétés.', $settings);
    }
}
