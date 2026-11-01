<?php

declare(strict_types=1);

namespace Tests\Unit\Pdf;

use App\Core\Database;
use App\Repositories\QualificationAwardRepository;
use App\Repositories\QualificationReferentielRepository;
use App\Repositories\TrainingCertificateRepository;
use App\Repositories\TrainingCertificateTemplateRepository;
use App\Repositories\UserRepository;
use App\Services\Personnel\QualificationBadgeStorageService;
use App\Services\Personnel\QualificationCertificatePdfService;
use App\Services\Personnel\QualificationTemporalStatusService;
use App\Services\Training\TrainingCertificateAssetStorageService;
use App\Services\Training\TrainingCertificatePdfService;
use App\Support\Pdf\AthenaCertificateLayout;
use App\Support\TrainingCertificatePdfEngine;
use PHPUnit\Framework\TestCase;

/**
 * Brevets de qualification et attestations de formation : certificat pleine page A4 portrait
 * (gabarit {@see AthenaCertificateLayout}), une seule page, référence et titulaire présents.
 */
final class CertificatePdfTemplateTest extends TestCase
{
    use AthenaPdfAssertions;

    private ?\PDO $previousPdo = null;

    protected function setUp(): void
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            self::markTestSkipped('Dompdf indisponible');
        }
        // Base SQLite minimale : nom de la communauté et grade du titulaire.
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT, logo_url TEXT)');
        $pdo->exec("INSERT INTO tenants (id, name, logo_url) VALUES (7, '75th Ranger Regiment', '')");
        $pdo->exec('CREATE TABLE grades (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec("INSERT INTO grades (id, name) VALUES (3, 'Sergent')");
        $prop = new \ReflectionProperty(Database::class, 'pdo');
        $this->previousPdo = $prop->getValue();
        $prop->setValue(null, $pdo);
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->previousPdo);
    }

    /** @return array<string, mixed> */
    private function award(string $number = 'CERT-CLS-0088'): array
    {
        return [
            'id' => 12,
            'user_id' => 5,
            'tenant_id' => 7,
            'admin_status' => 'obtained',
            'certificate_number' => $number,
            'certificate_number_format' => 'CERT-{code}-{seq}',
            'certificate_template_id' => 2,
            'definition_name' => 'Combat Life Saver (CLS)',
            'definition_code' => 'CLS',
            'level_name' => 'confirmé',
            'category_name' => 'Santé au combat',
            'issuer_name' => 'Centre d’instruction Athena',
            'obtained_at' => '2026-10-12',
            'expires_at' => '2028-10-12',
            'is_permanent' => 0,
        ];
    }

    private function users(): UserRepository
    {
        $users = $this->createStub(UserRepository::class);
        $users->method('findById')->willReturn(['id' => 5, 'display_name' => 'Marchetti', 'callsign' => 'Ghost', 'grade_id' => 3]);
        $users->method('overlayCommunityProfile')->willReturnArgument(0);

        return $users;
    }

    /** @return iterable<string, array{0: string}> */
    public static function layouts(): iterable
    {
        yield 'classique' => ['classique'];
        yield 'moderne' => ['moderne'];
    }

    /** Vraies dépendances (SQLite) : aucune doublure de classe finale, rendu identique à la production. */
    #[\PHPUnit\Framework\Attributes\DataProvider('layouts')]
    public function testQualificationCertificateIsSinglePortraitPage(string $layout): void
    {
        $svc = new QualificationCertificatePdfService(
            new QualificationAwardRepository(),
            new QualificationReferentielRepository(),
            new QualificationBadgeStorageService(),
            new QualificationTemporalStatusService(),
            $this->users(),
        );
        $doc = $svc->renderAward(7, $this->award(), $layout);

        self::assertSame($layout, $doc['layout']);
        self::assertA4Portrait($doc['binary']);
        self::assertSame(1, self::pageCount($doc['binary']), 'une seule page');
        self::assertStringContainsString('CERT-CLS-0088', $doc['html']);
        self::assertStringContainsString('Marchetti', $doc['html']);
        self::assertStringContainsString('Sergent', $doc['html']);
        self::assertStringContainsString('75th Ranger Regiment', $doc['html']);
        self::assertStringContainsString('COMBAT LIFE SAVER (CLS)', $doc['html']);
        self::assertStringNotContainsString('landscape', $doc['html']);
        self::saveSample($doc['binary'], 'brevet-qualification-' . $layout);
    }

    /** Numérotation et choix du gabarit (doublures des dépôts : nécessite BypassFinals). */
    public function testQualificationCertificateAllocatesNumberAndPicksTemplate(): void
    {
        if ((new \ReflectionClass(QualificationAwardRepository::class))->isFinal()) {
            self::markTestSkipped('Doublure de classe finale impossible sans BypassFinals.');
        }
        $awards = $this->createMock(QualificationAwardRepository::class);
        $awards->method('find')->willReturn($this->award(''));
        $awards->method('nextCertificateSequence')->willReturn(88);
        $awards->expects(self::once())->method('update')->with(12, ['certificate_number' => 'CERT-CLS-0088'], null)->willReturn(true);
        $ref = $this->createMock(QualificationReferentielRepository::class);
        $ref->method('findCertificateTemplate')->willReturn(['code' => 'moderne']);

        $svc = new QualificationCertificatePdfService($awards, $ref, new QualificationBadgeStorageService(), new QualificationTemporalStatusService(), $this->users());
        $doc = $svc->renderCertificate(7, 12);

        self::assertSame('moderne', $doc['layout']);
        self::assertSame('CERT-CLS-0088', $doc['certificate_number']);
        self::assertStringContainsString('CERT-CLS-0088', $doc['html']);
        self::assertA4Portrait($doc['binary']);
    }

    private function trainingService(): TrainingCertificatePdfService
    {
        $templates = $this->createMock(TrainingCertificateTemplateRepository::class);
        $templates->method('findByTenantId')->willReturn($this->trainingTemplateRow());
        $assets = $this->createMock(TrainingCertificateAssetStorageService::class);
        $assets->method('absolutePath')->willReturn(null);

        return new TrainingCertificatePdfService(
            $this->createMock(TrainingCertificateRepository::class),
            $templates,
            $this->createMock(UserRepository::class),
            $assets,
        );
    }

    public function testTrainingCertificatePreviewIsSinglePortraitPage(): void
    {
        if (!TrainingCertificatePdfEngine::isAvailable()) {
            self::markTestSkipped('Aucun moteur PDF');
        }
        $svc = $this->trainingService();
        $pdf = $svc->generatePreviewBinary(7);

        self::assertIsString($pdf, (string) $svc->getLastFailureReason());
        self::assertA4Portrait($pdf);
        self::assertSame(1, self::pageCount($pdf), 'une seule page');
        self::saveSample($pdf, 'attestation-formation');

        $html = (new \ReflectionMethod($svc, 'buildDompdfHtml'))->invoke($svc, $this->normalizedTemplate($svc), $this->previewPayload($svc));
        self::assertStringContainsString('DEMO-0001', $html);
        self::assertStringContainsString('Exemple de participant', $html);
        self::assertStringContainsString('ATTESTATION DE FORMATION', $html);
        self::assertStringContainsString('#1d4ed8', $html, 'couleur d’accent du gabarit');
        self::assertStringContainsString('75th Ranger Regiment', $html);
        self::assertStringContainsString('88,5 %', $html);
    }

    public function testTrainingCertificateTcpdfFallbackIsPortrait(): void
    {
        if (!TrainingCertificatePdfEngine::ensureTcpdfLoaded() || !TrainingCertificatePdfEngine::tcpdfCertificateFontsReady()) {
            self::markTestSkipped('TCPDF indisponible');
        }
        $svc = $this->trainingService();
        $pdf = (new \ReflectionMethod($svc, 'renderWithTcpdf'))->invoke($svc, $this->normalizedTemplate($svc), $this->previewPayload($svc));

        self::assertIsString($pdf);
        self::assertA4Portrait($pdf);
        self::assertSame(1, self::pageCount($pdf), 'une seule page');
        self::saveSample($pdf, 'attestation-formation-tcpdf');
    }

    /** Nombre d'objets /Type /Page (la MediaBox peut aussi figurer sur l'arbre /Pages). */
    private static function pageCount(string $pdf): int
    {
        return (int) preg_match_all('#/Type\s*/Page(?![a-zA-Z])#', $pdf);
    }

    /** @return array<string, mixed> */
    private function normalizedTemplate(TrainingCertificatePdfService $svc): array
    {
        $row = $this->trainingTemplateRow();

        return (new \ReflectionMethod($svc, 'normalizeTemplate'))->invoke($svc, $row);
    }

    /** @return array<string, mixed> */
    private function previewPayload(TrainingCertificatePdfService $svc): array
    {
        return ['tenant_id' => 7] + (new \ReflectionMethod($svc, 'previewPayload'))->invoke($svc);
    }

    /** @return array<string, mixed> */
    private function trainingTemplateRow(): array
    {
        return [
            'headline' => 'Attestation de formation',
            'subtitle' => 'Cursus santé au combat',
            'footer_legal' => 'Document interne à la communauté, sans valeur officielle.',
            'primary_hex' => '#0f172a',
            'accent_hex' => '#1d4ed8',
            'logo_relative_path' => null,
            'background_relative_path' => null,
            'layout_json' => json_encode(['show_final_score' => true, 'show_valid_until' => true]),
        ];
    }
}
