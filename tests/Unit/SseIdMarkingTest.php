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
        // Pays imaginaire : aucune marque visible, quel que soit le réglage enregistré.
        foreach (['none', 'discreet', 'standard', null, 'zzz'] as $setting) {
            self::assertSame('none', SseIdMarking::effective($setting, false), 'pays imaginaire : jamais de marque visible');
        }
        self::assertFalse(SseIdMarking::isRealCountry(['real_sim' => false], 'ALT'));
        self::assertFalse(SseIdMarking::isRealCountry(null, 'ALT'));
        self::assertTrue(SseIdMarking::isRealCountry(['real_sim' => true], 'XXX'));
        self::assertTrue(SseIdMarking::isRealCountry(null, 'gbr'), 'code du catalogue des pays réels sans ligne de pays : le marquage reste');
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
        // Le réglage ne concerne plus que les pays réels stylisés : « aucune marque » n'y est pas proposé.
        foreach (['discreet', 'standard'] as $mode) {
            self::assertStringContainsString('<option value="' . $mode . '"', $view);
        }
        self::assertStringNotContainsString('<option value="none"', $view);
        self::assertStringContainsString('pays réels stylisés', $view);
        $ctrl = self::read('app/Controllers/Admin/AdminSseFilesController.php');
        self::assertStringContainsString("'papers_marking' => \\App\\Support\\SseIdMarking::normalize(", $ctrl);
        // Lecture par communauté (tenant) dans l'atelier.
        self::assertStringContainsString('->settings($tenantId)', self::read('app/Controllers/Web/SseIdWorkshopController.php'));
    }

    public function testWantedNoticeSettingsNoLongerOfferAFictionMention(): void
    {
        // La notice ne porte plus de mention de jeu : ses réglages n'en proposent plus (ni filigrane, ni pied de page).
        $settings = self::read('views/atak/sse/papers/wanted_settings.php');
        self::assertStringNotContainsString('Document de fiction', $settings);
        self::assertStringNotContainsString('name="watermark"', $settings);
        self::assertStringNotContainsString('name="footer"', $settings);
        self::assertStringNotContainsString('Aspect de la notice', $settings);
    }

    public function testMemberPaperSpecimenIsUnmarkedForAnImaginaryCountryAndMarkedForARealOne(): void
    {
        $opxDocSvg = null;
        require dirname(__DIR__, 2) . '/views/operateur/_document_svg.php';
        self::assertNotNull($opxDocSvg);
        $doc = ['id' => 4, 'doc_type' => 'id_card', 'doc_type_label' => 'Carte d’identité', 'country_code' => 'ALT', 'country_name' => 'Altis', 'last_name' => 'Marek',
            'first_name' => 'Ana', 'sex' => 'F', 'birth_date' => '1991-02-03', 'birth_place' => 'Kavala', 'nationality' => 'ALT', 'doc_number' => 'AL1234567',
            'issued_on' => '2026-01-01', 'expires_on' => '2036-01-01', 'authority' => 'Préfecture de Kavala', 'ref' => 'DOC-4', 'holder_name' => 'Ana Marek', 'mrz' => ['IDALT', 'X', 'Y']];
        foreach (['recto', 'verso'] as $side) {
            $svg = $opxDocSvg($doc, $side, null);
            self::assertStringContainsString('<svg', $svg);
            self::assertNull(\App\Support\SseFictionMentions::find($svg), 'pays imaginaire, ' . $side . ' : aucune mention de jeu');
            self::assertStringNotContainsString('SIMULATION', $svg);
            $real = $opxDocSvg(['country_real' => true, 'country_code' => 'GBR', 'country_name' => 'Royaume-Uni'] + $doc, $side, null);
            self::assertStringContainsString('SPÉCIMEN · SIMULATION', $real, 'pays réel stylisé, ' . $side . ' : la mention reste');
        }
        // La page du spécimen n'imprime pas son en-tête pour un pays imaginaire.
        $page = self::read('views/operateur/specimen.php');
        self::assertStringContainsString("opx-specimen__head--screen", $page);
        self::assertMatchesRegularExpression('/@media print \{[^@]*\.opx-specimen__head--screen \{ display: none; \}/s', self::read('public/assets/css/operateur-hub.css'));
        foreach (['views/admin/effectifs_workspace/partials/member_papers.php', 'views/admin/member_situation/mes_papiers.php'] as $v) {
            self::assertStringContainsString("<?php if (!empty(\$d['country_real'])): ?><span>SIMULATION</span><?php endif; ?>", self::read($v), $v);
        }
    }
}
