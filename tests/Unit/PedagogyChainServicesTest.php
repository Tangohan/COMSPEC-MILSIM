<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\PedagogyRepository;
use App\Services\Training\PedagogyCapabilityResolver;
use App\Services\Training\PedagogyPathwayService;
use App\Services\Training\TenantPedagogyChainGuard;
use App\Services\Training\TrainingCoursePublicationGuard;
use PHPUnit\Framework\TestCase;

final class PedagogyChainServicesTest extends TestCase
{
    public function testPathwayStageCatalogHasSixStages(): void
    {
        $stages = PedagogyPathwayService::stageCatalog();
        self::assertCount(6, $stages);
        self::assertSame('instructor_stagiaire', $stages[0]['slug'] ?? null);
    }

    public function testTenantPedagogyChainGuardAssessReturnsGapsArray(): void
    {
        $repo = $this->createMock(PedagogyRepository::class);
        $repo->method('countUsersWithDesignTrainerRoleSet')->willReturn(0);
        $repo->method('countUsersWithActiveRoleDefinitions')->willReturnCallback(function (int $tenantId, array $slugs): int {
            if ($slugs === ['trainer', 'instructor_trainer', 'trainer_of_trainers']) {
                return 0;
            }
            if ($slugs === ['instructor', 'senior_instructor', 'trainer', 'instructor_trainer', 'trainer_of_trainers']) {
                return 0;
            }
            if ($slugs === ['instructor_trainer', 'trainer_of_trainers']) {
                return 0;
            }
            if ($slugs === ['trainer_of_trainers']) {
                return 0;
            }

            return 0;
        });
        $repo->method('tenantHasAnyInstructorEligibility')->willReturn(false);
        $repo->method('countUsersWithPedagogyKindRoles')->willReturn(0);

        $guard = new TenantPedagogyChainGuard($repo);
        $assess = $guard->assessTenantChain(1);
        self::assertArrayHasKey('ok', $assess);
        self::assertArrayHasKey('gaps', $assess);
        self::assertFalse($assess['ok']);
        self::assertNotSame([], $assess['gaps']);
    }

    public function testChainOkWhenInstructorCertifierRoleSetHasMembers(): void
    {
        $repo = $this->createMock(PedagogyRepository::class);
        $repo->method('countUsersWithDesignTrainerRoleSet')->willReturn(1);
        $repo->method('countUsersWithActiveRoleDefinitions')->willReturnCallback(function (int $tenantId, array $slugs): int {
            if ($slugs === ['trainer', 'instructor_trainer', 'trainer_of_trainers']) {
                return 1;
            }
            if ($slugs === ['instructor', 'senior_instructor', 'trainer', 'instructor_trainer', 'trainer_of_trainers']) {
                return 1;
            }
            if ($slugs === ['instructor_trainer', 'trainer_of_trainers']) {
                return 0;
            }
            if ($slugs === ['trainer_of_trainers']) {
                return 0;
            }

            return 0;
        });
        $repo->method('tenantHasAnyInstructorEligibility')->willReturn(true);
        $repo->method('countUsersWithPedagogyKindRoles')->willReturnCallback(function (int $tenantId, string $kind): int {
            if ($kind === 'instructor_certifier') {
                return 1;
            }
            if ($kind === 'trainer_certifier') {
                return 1;
            }

            return 0;
        });

        $guard = new TenantPedagogyChainGuard($repo);
        $assess = $guard->assessTenantChain(1);
        self::assertTrue($assess['ok']);
        self::assertSame([], $assess['gaps']);
    }

    public function testTrainingCoursePublicationGuardBlocksWithoutOwnerWhenColumnsExist(): void
    {
        $repo = $this->createMock(PedagogyRepository::class);
        $repo->method('trainingCoursesHavePedagogyColumns')->willReturn(true);
        $chain = $this->createMock(TenantPedagogyChainGuard::class);
        $chain->method('hasActiveDesignerCapacity')->willReturn(true);
        $guard = new TrainingCoursePublicationGuard($repo, $chain);
        $ok = $guard->canPublish(1, ['visibility' => 'published', 'pedagogical_owner_user_id' => null], 1);
        self::assertFalse($ok);
        self::assertNotNull($guard->lastUserMessage());
    }

    public function testPedagogyCapabilityResolverDeniedWithoutGatePermission(): void
    {
        $repo = $this->createMock(PedagogyRepository::class);
        $chain = $this->createMock(TenantPedagogyChainGuard::class);
        $resolver = new PedagogyCapabilityResolver($repo, $chain);
        $r = $resolver->can(1, 1, null, 'publish', 'training_course', []);
        self::assertFalse($r['allowed']);
        self::assertSame('permission_denied', $r['reason_code']);
    }
}
