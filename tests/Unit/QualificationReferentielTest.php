<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Personnel\QualificationPermissionGrantService;
use App\Services\Personnel\QualificationTemporalStatusService;
use App\Support\QualificationAdminStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class QualificationReferentielTest extends TestCase
{
    public function testAdminStatusLabelsAndTransitions(): void
    {
        self::assertSame('Obtenue', QualificationAdminStatus::label('obtained'));
        self::assertSame('En formation', QualificationAdminStatus::label('in_progress'));
        self::assertTrue(QualificationAdminStatus::canTransition('candidate', 'obtained'));
        self::assertTrue(QualificationAdminStatus::canTransition('obtained', 'revoked'));
        self::assertFalse(QualificationAdminStatus::canTransition('revoked', 'obtained'));
        self::assertFalse(QualificationAdminStatus::canTransition('obtained', 'candidate'));
    }

    public function testTemporalValidPermanent(): void
    {
        $svc = new QualificationTemporalStatusService();
        $r = $svc->resolve([
            'admin_status' => 'obtained',
            'expires_at' => null,
        ], new DateTimeImmutable('2026-09-13'));
        self::assertSame(QualificationTemporalStatusService::VALID, $r['code']);
        self::assertSame('Valide', $r['label']);
    }

    public function testTemporalExpiringSoonAndGrace(): void
    {
        $svc = new QualificationTemporalStatusService();
        $now = new DateTimeImmutable('2026-09-13');

        $soon = $svc->resolve([
            'admin_status' => 'obtained',
            'expires_at' => '2026-09-20',
            'alert_before_expiry_days' => 30,
            'grace_period_days' => 7,
        ], $now);
        self::assertSame(QualificationTemporalStatusService::EXPIRING_SOON, $soon['code']);

        $grace = $svc->resolve([
            'admin_status' => 'obtained',
            'expires_at' => '2026-09-10',
            'alert_before_expiry_days' => 30,
            'grace_period_days' => 7,
        ], $now);
        self::assertSame(QualificationTemporalStatusService::EXPIRED_GRACE, $grace['code']);

        $expired = $svc->resolve([
            'admin_status' => 'obtained',
            'expires_at' => '2026-08-01',
            'alert_before_expiry_days' => 30,
            'grace_period_days' => 7,
        ], $now);
        self::assertSame(QualificationTemporalStatusService::EXPIRED, $expired['code']);
    }

    public function testDefaultExpiresAtRespectsPermanent(): void
    {
        $svc = new QualificationTemporalStatusService();
        self::assertNull($svc->computeDefaultExpiresAt('2026-01-01', 12, true));
        self::assertSame('2026-07-01', $svc->computeDefaultExpiresAt('2026-01-01', 6, false));
    }

    public function testPermissionGrantBlocksAdminCodes(): void
    {
        $repo = $this->createMock(\App\Repositories\QualificationReferentielRepository::class);
        $svc = new QualificationPermissionGrantService($repo);
        self::assertFalse($svc->isPermissionAllowed('admin.roles.manage'));
        self::assertFalse($svc->isPermissionAllowed('admin.settings.manage'));
        self::assertFalse($svc->isPermissionAllowed('platform.anything'));
        self::assertTrue($svc->isPermissionAllowed('trainings.create'));
        self::assertTrue($svc->isPermissionAllowed('personnel.view'));
    }

    public function testCertificateLayoutsExist(): void
    {
        $base = dirname(__DIR__, 2) . '/views/admin/organization/qualifications/certificates/';
        self::assertFileExists($base . 'layout_classique.php');
        self::assertFileExists($base . 'layout_moderne.php');
        self::assertFileExists(dirname(__DIR__, 2) . '/public/assets/img/qualification-badge-default.svg');
        self::assertFileExists(dirname(__DIR__, 2) . '/docs/technique/qualification-certificate-templates/template_classique_vierge.pdf');
        self::assertFileExists(dirname(__DIR__, 2) . '/docs/technique/qualification-certificate-templates/template_moderne_vierge.pdf');
    }

    public function testMigrationRegistersQualificationReferentiel(): void
    {
        $migration = file_get_contents(dirname(__DIR__, 2) . '/bootstrap/qualification_referentiel_migration.php');
        self::assertIsString($migration);
        self::assertStringContainsString('qualification_categories', $migration);
        self::assertStringContainsString('qualification_certificate_templates', $migration);
        self::assertStringContainsString('qualification_grants_permission', $migration);
        self::assertStringContainsString('classique', $migration);
        self::assertStringContainsString('moderne', $migration);
    }
}
