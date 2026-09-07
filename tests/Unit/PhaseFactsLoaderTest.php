<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\ArmaPlaytimeRepository;
use App\Repositories\ModerationRepository;
use App\Repositories\PersonnelPhaseRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\PersonnelQualificationRepository;
use App\Repositories\RoleplayGameSessionRepository;
use App\Repositories\TrainingEnrollmentRepository;
use App\Repositories\UserRepository;
use App\Services\Personnel\PersonnelCompletenessService;
use App\Services\Personnel\PhaseRules\PhaseFactsLoader;
use App\Services\Personnel\PhaseRules\PhaseRuleEngine;
use App\Services\Personnel\SeniorityEngine;
use PHPUnit\Framework\TestCase;

final class PhaseFactsLoaderTest extends TestCase
{
    public function testIgnoresQualificationFromAnotherTenantAndExpiredOnes(): void
    {
        $users = $this->createStub(UserRepository::class);
        $users->method('findById')->willReturn(['id' => 10, 'status' => 'active', 'created_at' => '2020-01-01']);
        $profiles = $this->createStub(PersonnelProfileRepository::class);
        $profiles->method('getByUserId')->willReturn(['current_phase_id' => 1, 'rp_tutor_user_id' => 0]);
        $quals = $this->createStub(PersonnelQualificationRepository::class);
        $quals->method('listForUser')->willReturn([
            [
                'tenant_id' => 99,
                'definition_id' => 5,
                'status' => 'valid',
                'qualification_name' => 'Autre communauté',
                'expires_at' => date('Y-m-d', strtotime('+1 year')),
            ],
            [
                'tenant_id' => 1,
                'definition_id' => 5,
                'status' => 'valid',
                'qualification_name' => 'Chef de groupe',
                'expires_at' => '2020-01-01',
            ],
        ]);
        $enrollments = $this->createStub(TrainingEnrollmentRepository::class);
        $enrollments->method('listByUserId')->willReturn([]);
        $playtime = $this->createStub(ArmaPlaytimeRepository::class);
        $playtime->method('schemaReady')->willReturn(true);
        $playtime->method('getSummaryForUser')->willReturn(['total_seconds' => 0]);
        $sessions = $this->createStub(RoleplayGameSessionRepository::class);
        $sessions->method('schemaReady')->willReturn(true);
        $sessions->method('countCheckedIn')->willReturn(0);
        $sessions->method('sumValidatedSeconds')->willReturn(0);
        $sessions->method('countValidatedSessions')->willReturn(0);
        $sessions->method('attendanceRate')->willReturn(['rate' => 0.0]);
        $completeness = $this->createStub(PersonnelCompletenessService::class);
        $completeness->method('getScore')->willReturn(['score' => 40]);
        $seniority = $this->createStub(SeniorityEngine::class);
        $seniority->method('compute')->willReturn(['days' => 10]);
        $moderation = $this->createStub(ModerationRepository::class);
        $moderation->method('listActiveActionsWithRestrictions')->willReturn([]);
        $phases = $this->createStub(PersonnelPhaseRepository::class);

        $loader = new PhaseFactsLoader(
            $users,
            $profiles,
            $quals,
            $enrollments,
            $playtime,
            $sessions,
            $completeness,
            $seniority,
            $moderation,
            $phases
        );
        $ctx = $loader->load(1, 10, [
            'condition_type' => PhaseRuleEngine::TYPE_QUALIFICATION,
            'qualification_id' => 5,
            'require_validity' => 1,
        ]);

        self::assertSame(5, $ctx->qualificationHeldId);
        self::assertFalse($ctx->qualificationValid);
        self::assertSame(0.0, $ctx->rawHours);
        self::assertSame(0, $ctx->checkedInCount);

        $item = (new PhaseRuleEngine())->evaluateOne([
            'condition_type' => PhaseRuleEngine::TYPE_QUALIFICATION,
            'require_validity' => true,
        ], $ctx);
        self::assertFalse($item['passed']);
    }

    public function testSessionQueriesStayOnCheckedInAndTenant(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/RoleplayGameSessionRepository.php');
        self::assertStringContainsString('m.checked_in_at IS NOT NULL', $src);
        self::assertStringContainsString('m.tenant_id = ?', $src);
        self::assertStringNotContainsString('rsvp', strtolower($src));
    }
}
