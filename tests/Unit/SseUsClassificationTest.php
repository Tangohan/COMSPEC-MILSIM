<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Gate;
use App\Repositories\SseCaseRepository;
use App\Repositories\SseSheetRepository;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseClearanceService;
use App\Services\Sse\SseRedactionService;
use App\Services\Sse\SseSheetAccess;
use App\Services\Sse\SseSheetFactsService;
use App\Services\Sse\SseSheetService;
use App\Services\Sse\SseWantedAccess;
use App\Support\SseUsClassification;
use App\Support\TrainingCertificatePdfEngine;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseSheetSqlite;

/**
 * Niveaux de classification américains du bureau SSE : correspondance explicite ancien code => niveau, ordre de sensibilité et
 * décisions d'accès inchangés, libellés FR/EN, marquage de bannière dans les PDF (couleur conventionnelle haut et bas), génération
 * rapide sans migration, caviardage dessiné en rectangles (jamais en glyphe), noms accentués et non latins.
 */
final class SseUsClassificationTest extends TestCase
{
    private PDO $pdo;

    /** @var array<string, mixed> */
    private array $saved = [];

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->saved = ['session' => $_SESSION ?? null, 'post' => $_POST ?? [], 'get' => $_GET ?? []];
        \App\Core\Csrf::token();
        $this->pdo = SseSheetSqlite::open();
        $this->pdo->exec("INSERT INTO users (id, tenant_id, steam_id, display_name) VALUES (7, 1, '76561198000000007', 'Vrai Membre')");
        $_SESSION = [
            'user_id' => 7, 'tenant_id' => 1, 'callsign' => 'ANALYSTE', '_csrf_token' => 'jeton',
            'sse_tenant_id' => 1, 'sse_guest' => false, 'sse_clearance_until' => time() + 3600, 'sse_access_code_id' => 0,
            'sse_confidentiality_version' => SseAccessCodeService::CONFIDENTIALITY_VERSION, 'sse_confidentiality_tenant_id' => 1, 'sse_confidentiality_code_id' => 0,
        ];
        $_POST = [];
        $_GET = [];
        Gate::reset();
        Gate::getInstance()->setPermissions(['admin.access', 'atak.sse.case.manage', 'atak.sse.clearance.confidentiel', 'atak.sse.clearance.encadrement']);
    }

    protected function tearDown(): void
    {
        SseSheetSqlite::close();
        $_POST = $this->saved['post'];
        $_GET = $this->saved['get'];
        if ($this->saved['session'] === null) {
            unset($_SESSION);
        } else {
            $_SESSION = $this->saved['session'];
        }
        Gate::reset();
    }

    private static function access(string $level = 'tres_restreint'): SseSheetAccess
    {
        $wanted = new SseWantedAccess(false, true, true, true, 1, 7, 'ANALYSTE');

        return new SseSheetAccess($wanted, static fn (string $s, string $l): bool => true, true, $level);
    }

    private static function requirePdfEngine(): void
    {
        if (!TrainingCertificatePdfEngine::ensureTcpdfLoaded() || !TrainingCertificatePdfEngine::tcpdfCertificateFontsReady()) {
            self::markTestSkipped('TCPDF et ses polices requis');
        }
    }

    private function person(string $last, string $first, array $extra = []): int
    {
        $cols = ['tenant_id' => 1, 'last_name' => $last, 'first_name' => $first, 'alias' => $first . ' ' . $last, 'sex_apparent' => 'F', 'age_estimated' => 38, 'birth_date' => '1987-03-12',
            'birth_place' => 'Kavala', 'nationality' => 'TAK', 'affiliation' => 'Réseau du Nord-Est', 'grid_reference' => '072 118', 'location_description' => 'Pyrgos, marché couvert',
            'created_at' => '2026-11-01 10:00:00', 'updated_at' => '2026-11-12 14:32:00'] + $extra;
        $this->pdo->prepare('INSERT INTO sse_persons (' . implode(', ', array_keys($cols)) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')')->execute(array_values($cols));

        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<string, mixed> */
    private function subject(int $personId, ?SseSheetAccess $access = null): array
    {
        $s = (new SseSheetFactsService())->subject(1, 'person', $personId, $access ?? self::access());
        self::assertNotNull($s);

        return $s;
    }

    /** Contenu décompressé de tous les flux d'un PDF (couleurs de remplissage, rectangles). */
    private static function streams(string $pdf): string
    {
        $out = '';
        if (preg_match_all('~stream\r?\n(.*?)\r?\nendstream~s', $pdf, $m) > 0) {
            foreach ($m[1] as $raw) {
                $plain = @gzuncompress($raw);
                $out .= $plain !== false ? $plain : '';
            }
        }

        return $out;
    }

    // --------------------------------------------------- Correspondance et ordre

    public function testEveryStoredCodeMapsToAUsLevelInTheSameOrder(): void
    {
        self::assertSame(['interne', 'encadrement', 'confidentiel', 'tres_restreint'], SseUsClassification::codes());
        self::assertSame(['interne', 'encadrement', 'confidentiel', 'tres_restreint'], array_keys(SseRedactionService::LEVELS));
        $expected = ['interne' => 'CUI', 'encadrement' => 'C', 'confidentiel' => 'S', 'tres_restreint' => 'TS'];
        $ranks = [];
        foreach ($expected as $code => $abbr) {
            self::assertSame($abbr, SseUsClassification::abbr($code), $code);
            $ranks[] = SseRedactionService::levelRank($code);
        }
        self::assertSame([0, 1, 2, 3], $ranks, 'L’ordre de sensibilité et les rangs ne changent pas');
        // Les constantes de code (clés en base) sont inchangées.
        self::assertSame('interne', SseCaseRepository::CLASS_INTERNAL);
        self::assertSame('encadrement', SseCaseRepository::CLASS_COMMAND);
        self::assertSame('confidentiel', SseCaseRepository::CLASS_CONFIDENTIAL);
        self::assertSame('tres_restreint', SseCaseRepository::CLASS_RESTRICTED);
        self::assertSame('atak.sse.clearance.tres_restreint', 'atak.sse.clearance.' . SseCaseRepository::CLASS_RESTRICTED);
    }

    public function testFrenchAndEnglishLabelsAreTheUsLevelsAndTheOldOnesAreGone(): void
    {
        $fr = SseCaseRepository::CLASSIFICATION_LABELS;
        self::assertSame('Information non classifiée contrôlée (CUI)', $fr['interne']);
        self::assertSame('Confidentiel (C)', $fr['encadrement']);
        self::assertSame('Secret (S)', $fr['confidentiel']);
        self::assertSame('Très secret (TS)', $fr['tres_restreint']);
        self::assertSame('Controlled Unclassified Information (CUI)', SseUsClassification::label('interne', 'en'));
        self::assertSame('Confidential (C)', SseUsClassification::label('encadrement', 'en'));
        self::assertSame('Secret (S)', SseUsClassification::label('confidentiel', 'en'));
        self::assertSame('Top Secret (TS)', SseUsClassification::label('tres_restreint', 'en'));
        self::assertSame($fr, array_map(static fn (array $s): string => $s['fr'], SseUsClassification::SCALE));
        // Le dictionnaire de compatibilité garde les anciens libellés ; plus aucune échelle d'affichage ne les porte.
        foreach (SseUsClassification::LEGACY_LABELS as $code => $old) {
            self::assertNotSame($old, $fr[$code], $code);
            self::assertNotContains($old, SseIntelCycleCatalogLabels::all(), $old);
        }
        self::assertSame(['Information non classifiée contrôlée (CUI)', 'Confidentiel (C)', 'Secret (S)', 'Très secret (TS)'], array_values($fr));
        self::assertSame('Top Secret (TS)', SseSheetService::levelLabels('tres_restreint')[1]);
    }

    public function testBannerMarkingsAndConventionalColours(): void
    {
        self::assertSame('CUI', SseUsClassification::banner('interne'));
        self::assertSame('CONFIDENTIAL', SseUsClassification::banner('encadrement'));
        self::assertSame('SECRET//NOFORN', SseUsClassification::banner('confidentiel'));
        self::assertSame('TOP SECRET//SCI//NOFORN', SseUsClassification::banner('tres_restreint'));
        self::assertSame([80, 40, 120], SseUsClassification::rgb('interne'));
        self::assertSame([0, 51, 160], SseUsClassification::rgb('encadrement'));
        self::assertSame([200, 16, 46], SseUsClassification::rgb('confidentiel'));
        self::assertSame([255, 103, 31], SseUsClassification::rgb('tres_restreint'));
        self::assertSame([0, 122, 51], SseUsClassification::unclassifiedRgb());
        self::assertSame('SECRET//NOFORN · Accès réservé au personnel habilité · Journalisation active', SseUsClassification::portalLine('confidentiel'));
        self::assertSame('SECRET//NOFORN · Access restricted to cleared personnel · Logging active', SseUsClassification::portalLine('confidentiel', 'en'));
        // Un code inconnu retombe sur le niveau intermédiaire, comme avant (« encadrement »).
        self::assertSame('encadrement', SseUsClassification::normalize('n_importe_quoi'));
    }

    public function testAccessDecisionsAreUnchanged(): void
    {
        // Même règle de plafond : un code stocké garde son rang, quel que soit l'affichage.
        foreach (['interne' => 0, 'encadrement' => 1, 'confidentiel' => 2, 'tres_restreint' => 3] as $code => $rank) {
            self::assertSame($rank, SseRedactionService::levelRank($code));
            self::assertSame($code, SseClearanceService::normalizeProfileLevel($code));
        }
        self::assertSame('confidentiel', SseClearanceService::normalizeProfileLevel(' Confidentiel '), 'Les anciennes saisies restent lues');
        self::assertTrue(SseRedactionService::visibleAt('identite', 'confidentiel'));
        self::assertFalse(SseRedactionService::visibleAt('identite', 'encadrement'));
        self::assertFalse(SseRedactionService::visibleAt('source', 'confidentiel'));
        self::assertTrue(SseRedactionService::visibleAt('source', 'tres_restreint'));
    }

    public function testViewsAndSourcesNoLongerCarryTheOldLevelLabels(): void
    {
        $root = dirname(__DIR__, 2);
        $files = array_merge(
            glob($root . '/views/atak/sse/*.php') ?: [],
            glob($root . '/views/atak/sse/*/*.php') ?: [],
            [$root . '/app/Repositories/SseCaseRepository.php', $root . '/app/Support/SseIntelCycleCatalog.php', $root . '/app/Services/Sse/SseSheetService.php', $root . '/app/Services/Sse/SseMemberImageGate.php']
        );
        foreach ($files as $file) {
            $src = (string) file_get_contents($file);
            foreach (['Diffusion très restreinte', 'Diffusion interne', 'CONFIDENTIEL ÉLEVÉ', 'Confidentiel élevé', 'Diffusion restreinte · personnel habilité'] as $old) {
                self::assertStringNotContainsString($old, $src, basename($file) . ' : ' . $old);
            }
        }
        $layout = (string) file_get_contents($root . '/views/atak/sse/_layout.php');
        self::assertStringContainsString('SseUsClassification::banner', $layout);
        self::assertStringContainsString('Accès réservé au personnel habilité · Journalisation active', preg_replace('~</?(strong|span)[^>]*>~', '', str_replace(["\n", '        '], ['', ''], $layout)) ?? '');
        $en = (string) file_get_contents($root . '/lang/en/site_auto.php');
        foreach (['Information non classifiée contrôlée (CUI)', 'Confidentiel (C)', 'Très secret (TS)'] as $key) {
            self::assertStringContainsString("'" . $key . "' =>", $en, $key);
        }
    }

    // -------------------------------------------------------------------- PDF

    public function testEachLevelPrintsItsColouredBannerTopAndBottomOfEveryPage(): void
    {
        self::requirePdfEngine();
        $p = $this->person('Karaman', 'Dimitri');
        $svc = new SseSheetService();
        foreach (['interne', 'encadrement', 'confidentiel', 'tres_restreint'] as $level) {
            foreach (['fr', 'en'] as $lang) {
                $res = $svc->preview(1, $this->subject($p), 'profile', ['lang' => $lang, 'classification' => $level, 'faces' => '2'], self::access());
                self::assertTrue($res['ok'], implode(' | ', $res['errors']));
                $content = self::streams((string) $res['bytes']);
                [$r, $g, $b] = SseUsClassification::rgb($level);
                $fill = sprintf('%.6F %.6F %.6F rg', $r / 255, $g / 255, $b / 255);
                self::assertGreaterThanOrEqual(2 * $res['pages'], substr_count($content, $fill), "$level $lang : un bandeau en haut et en bas de chaque page");
                // Le marquage est écrit en texte (polices Unicode : deux octets par lettre), deux fois par page.
                $encoded = implode('', array_map(static fn (string $c): string => " " . $c, mb_str_split(SseUsClassification::banner($level))));
                self::assertGreaterThanOrEqual(2 * $res['pages'], substr_count($content, '(' . $encoded . ')'), "$level $lang : marquage de bannière");
            }
        }
    }

    public function testRedactionIsDrawnAsBlackRectanglesNeverAsAGlyph(): void
    {
        self::requirePdfEngine();
        $p = $this->person('Øksnevad-Çelik', 'Zoë-Élodie');
        $res = (new SseSheetService())->preview(1, $this->subject($p), 'identification', ['lang' => 'fr', 'classification' => 'interne'], self::access());
        self::assertTrue($res['ok'], implode(' | ', $res['errors']));
        $content = self::streams((string) $res['bytes']);
        self::assertMatchesRegularExpression('~0\.000000 0\.000000 0\.000000 rg\s+[\d.]+ [\d.]+ [\d.]+ -?[\d.]+ re f~', $content, 'rectangle noir plein');
        // Les polices embarquées ne reçoivent jamais le glyphe plein ni un point d'interrogation de repli.
        self::assertStringNotContainsString("\u{2588}", (string) $res['bytes']);
        self::assertStringNotContainsString("( ? ? ?", $content);
        self::assertStringNotContainsString("\u{FFFD}", $content);
    }

    public function testAccentedCyrillicGreekAndArabicNamesRenderInEveryModel(): void
    {
        self::requirePdfEngine();
        $svc = new SseSheetService();
        foreach ([['Øksnevad-Çelik', 'Zoë-Élodie'], ['Каранов', 'Дмитрий'], ['Παπαδόπουλος', 'Γεώργιος'], ['الحسن', 'محمد'], [str_repeat('Montgomery-Øksnevad ', 4), 'Jean-Baptiste-Alexandre']] as [$last, $first]) {
            $p = $this->person($last, $first);
            foreach (['identification', 'vigilance', 'profile', 'control'] as $model) {
                foreach (['fr', 'en'] as $lang) {
                    $res = $svc->preview(1, $this->subject($p), $model, ['lang' => $lang, 'classification' => 'confidentiel'], self::access());
                    self::assertTrue($res['ok'], "$model $lang $last : " . implode(' | ', $res['errors']));
                    self::assertStringStartsWith('%PDF-', (string) $res['bytes']);
                    $content = self::streams((string) $res['bytes']);
                    self::assertStringNotContainsString("\u{FFFD}", $content);
                    self::assertStringNotContainsString("( ? ? ?", $content);
                }
            }
        }
    }

    public function testPdfGeneratorsNeverWriteBlockGlyphs(): void
    {
        $root = dirname(__DIR__, 2) . '/app/';
        foreach (['Services/Sse/SseSheetPdf.php', 'Services/Sse/SseWantedNoticePdf.php', 'Services/Sse/SseWantedNoticePdfService.php', 'Services/Sse/SseTransmissionPdfService.php', 'Services/Sse/SseSheetService.php'] as $file) {
            $src = (string) file_get_contents($root . $file);
            foreach (["\u{2588}", "\u{2592}", "\u{2593}", "\u{25A0}", "\u{25AE}"] as $glyph) {
                self::assertStringNotContainsString($glyph, $src, $file . ' ne doit pas écrire de glyphe de bloc');
            }
        }
        $pdf = (string) file_get_contents($root . 'Services/Sse/SseSheetPdf.php');
        self::assertStringNotContainsString("SetFont('courier'", $pdf, 'Aucune police de base non Unicode (accents en « ? »)');
        self::assertStringNotContainsString("SetFont('helvetica'", $pdf);
    }

    // ------------------------------------------------------ Génération rapide

    public function testQuickGenerationNeedsNoTextAndNoMigration(): void
    {
        self::requirePdfEngine();
        $this->pdo->exec('DROP TABLE sse_doc_issues');
        SseSheetRepository::forgetReady();
        $p = $this->person('Karaman', 'Dimitri');
        $svc = new SseSheetService();
        $access = self::access('confidentiel');
        $input = $svc->quickInput(1, $access, 'en');
        self::assertTrue($input['quick']);
        self::assertSame('en', $input['lang']);
        self::assertSame('encadrement', $input['classification'], 'Classification par défaut de la communauté');
        self::assertFalse(array_key_exists('texts', $input));
        foreach (['identification', 'vigilance', 'profile', 'control'] as $model) {
            $res = $svc->issue(1, $this->subject($p, $access), $model, $input, $access);
            self::assertTrue($res['ok'], $model . ' : ' . implode(' | ', $res['errors']));
            self::assertStringStartsWith('%PDF-', (string) $res['bytes']);
            self::assertSame(0, $res['id'], 'Sans l’étape, rien n’est enregistré');
        }
        // Le plafond d'habilitation s'applique à la classification par défaut.
        $low = self::access('interne');
        self::assertSame('interne', $svc->quickInput(1, $low, 'fr')['classification']);
        // Sans le drapeau « rapide », l'émission depuis l'éditeur reste refusée avec un message clair.
        $normal = $svc->issue(1, $this->subject($p, $access), 'vigilance', [], $access);
        self::assertSame('unavailable', $normal['error']);
    }

    public function testQuickRouteAndMenuAreWired(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        self::assertStringContainsString("/rapide', [\\App\\Controllers\\Web\\SseSheetController::class, 'quick'], \$mwSsePortal", $routes);
        $menu = (string) file_get_contents($root . '/views/atak/sse/_sheet_menu.php');
        self::assertStringContainsString('Csrf::field()', $menu);
        self::assertStringContainsString('Personnaliser les textes', $menu);
        $ctl = (string) file_get_contents($root . '/app/Controllers/Web/SseSheetController.php');
        self::assertStringContainsString('Csrf::verify($request)', substr($ctl, (int) strpos($ctl, 'public function quick')));
    }
}

/** @internal Libellés d'échelle des catalogues d'affichage, pour vérifier l'absence des anciens libellés. */
final class SseIntelCycleCatalogLabels
{
    /** @return list<string> */
    public static function all(): array
    {
        return array_merge(array_values(\App\Support\SseIntelCycleCatalog::RELEASE_LEVELS), array_values(SseCaseRepository::CLASSIFICATION_LABELS));
    }
}
