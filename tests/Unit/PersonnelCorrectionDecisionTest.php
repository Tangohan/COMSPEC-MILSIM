<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\GradeRepository;
use App\Repositories\PersonnelAssignmentRepository;
use App\Repositories\PersonnelCorrectionRequestRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserNotificationPreferencesRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\UserRepository;
use App\Services\EmailService;
use App\Services\Personnel\PersonnelCorrectionRequestService;
use App\Services\Personnel\PersonnelServiceHistoryWriter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Corrections de fiches : un refus s'explique (motif obligatoire), le lot passe par le même contrôle.
 * Données fictives uniquement.
 */
final class PersonnelCorrectionDecisionTest extends TestCase
{
    private function service(PersonnelCorrectionRequestRepository $requests): PersonnelCorrectionRequestService
    {
        return new PersonnelCorrectionRequestService(
            $requests,
            $this->createMock(PersonnelProfileRepository::class),
            $this->createMock(UserRepository::class),
            $this->createMock(TenantRepository::class),
            $this->createMock(EmailService::class),
            $this->createMock(UserNotificationPreferencesRepository::class),
            $this->createMock(UserProfileRepository::class),
            $this->createMock(PersonnelAssignmentRepository::class),
            $this->createMock(PersonnelJobRoleRepository::class),
            $this->createMock(UnitRepository::class),
            $this->createMock(GradeRepository::class),
            $this->createMock(PersonnelServiceHistoryWriter::class),
        );
    }

    /** @return PersonnelCorrectionRequestRepository&MockObject */
    private function repoWithPendingRequest(): PersonnelCorrectionRequestRepository
    {
        $repo = $this->createMock(PersonnelCorrectionRequestRepository::class);
        $repo->method('findById')->willReturn([
            'id' => 5, 'tenant_id' => 1, 'target_user_id' => 9, 'requested_by' => 9, 'status' => 'pending',
            'proposed' => ['callsign' => 'NOUVEAU'], 'before' => ['callsign' => 'ANCIEN'],
        ]);

        return $repo;
    }

    public function testRefusalWithoutReasonIsRejectedAndNothingIsResolved(): void
    {
        $repo = $this->repoWithPendingRequest();
        $repo->expects(self::never())->method('resolve');

        foreach (['', '   '] as $note) {
            $res = $this->service($repo)->decide(1, 5, 2, 'rejected', $note);
            self::assertFalse($res['ok']);
            self::assertStringContainsString('motif', $res['message']);
        }
    }

    public function testRefusalWithAReasonIsResolved(): void
    {
        $repo = $this->repoWithPendingRequest();
        $repo->expects(self::once())->method('resolve')->with(5, 1, 'rejected', 2, 'Information déjà à jour.')->willReturn(true);

        $res = $this->service($repo)->decide(1, 5, 2, 'rejected', 'Information déjà à jour.');
        self::assertTrue($res['ok']);
    }

    public function testBatchRefusalNeedsAReasonBeforeTouchingAnything(): void
    {
        $repo = $this->createMock(PersonnelCorrectionRequestRepository::class);
        $repo->expects(self::never())->method('findById');

        $res = $this->service($repo)->decideBatch(1, [1, 2, 3], 2, 'rejected', '');
        self::assertFalse($res['ok']);
        self::assertSame(0, $res['done']);
        self::assertStringContainsString('motif', $res['message']);
    }

    public function testBatchNeedsASelectionAndAKnownDecision(): void
    {
        $repo = $this->createMock(PersonnelCorrectionRequestRepository::class);
        $repo->expects(self::never())->method('findById');
        $service = $this->service($repo);

        self::assertFalse($service->decideBatch(1, [], 2, 'approved')['ok']);
        self::assertFalse($service->decideBatch(1, [0, '', -3], 2, 'approved')['ok']);
        self::assertFalse($service->decideBatch(1, [4], 2, 'peut-etre')['ok']);
    }

    public function testBatchIsCappedAndEachRequestGoesThroughTheSameCheck(): void
    {
        $repo = $this->createMock(PersonnelCorrectionRequestRepository::class);
        // Requêtes introuvables (autre communauté ou supprimées) : comptées en « ignorées », jamais appliquées.
        $repo->expects(self::exactly(PersonnelCorrectionRequestService::BATCH_MAX))->method('findById')->willReturn(null);
        $repo->expects(self::never())->method('resolve');

        $res = $this->service($repo)->decideBatch(1, range(1, 80), 2, 'approved');
        self::assertFalse($res['ok']);
        self::assertSame(0, $res['done']);
        self::assertSame(PersonnelCorrectionRequestService::BATCH_MAX, $res['failed']);
    }

    public function testBatchDuplicatesAreHandledOnce(): void
    {
        $repo = $this->createMock(PersonnelCorrectionRequestRepository::class);
        $repo->expects(self::exactly(2))->method('findById')->willReturn(null);

        $this->service($repo)->decideBatch(1, [3, '3', 4, 4], 2, 'approved');
    }

    public function testBatchIsReservedToBroaderRightsThanSingleDecisions(): void
    {
        foreach (PersonnelCorrectionRequestService::BATCH_PERMISSIONS as $slug) {
            self::assertContains($slug, PersonnelCorrectionRequestService::STAFF_PERMISSIONS);
        }
        self::assertContains('personnel.grades.manage', PersonnelCorrectionRequestService::STAFF_PERMISSIONS);
        self::assertNotContains('personnel.grades.manage', PersonnelCorrectionRequestService::BATCH_PERMISSIONS);
    }

    public function testControllerAndRouteExposeTheBatchWithCsrfAndRights(): void
    {
        $root = dirname(__DIR__, 2);
        $ctrl = (string) file_get_contents($root . '/app/Controllers/Web/PersonnelCorrectionController.php');
        $routes = (string) file_get_contents($root . '/routes/web.php');
        self::assertStringContainsString("'/back-office/personnel/corrections/lot'", $routes);
        self::assertStringContainsString("'decideBatch'", $routes);
        $batch = substr($ctrl, (int) strpos($ctrl, 'public function decideBatch'));
        $batch = substr($batch, 0, (int) strpos($batch, '/** POST /back-office/personnel/corrections/appliquer'));
        self::assertStringContainsString('canBatch()', $batch);
        self::assertStringContainsString('Csrf::validate', $batch);
        self::assertStringContainsString('$tenantId', $batch, 'filtré sur la communauté de la session');
    }

    public function testHistoryQueryStaysInTheCommunityAndKeepsOnlyHandledRequests(): void
    {
        $repo = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/PersonnelCorrectionRequestRepository.php');
        $start = (int) strpos($repo, 'function listResolvedForTenant');
        $body = substr($repo, $start, 1400);
        self::assertStringContainsString('r.tenant_id = ?', $body);
        self::assertStringContainsString("r.status <> 'pending'", $body);
        self::assertStringNotContainsString('email', strtolower($body));
        // Aucune adresse e-mail non plus pour la file en attente.
        $open = substr($repo, (int) strpos($repo, 'function listOpenForTenant'), 900);
        self::assertStringNotContainsString('email', strtolower($open));
    }
}
