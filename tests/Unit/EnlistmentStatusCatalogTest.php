<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\EnlistmentStatusCatalog;
use PHPUnit\Framework\TestCase;

final class EnlistmentStatusCatalogTest extends TestCase
{
    private const NOW = 1791979200; // 2026-10-14 12:00:00 UTC

    private static function at(int $daysAgo, int $hoursAgo = 0): string
    {
        return date('Y-m-d H:i:s', self::NOW - $daysAgo * 86400 - $hoursAgo * 3600);
    }

    public function testStageFollowsPipelineAndAccountLink(): void
    {
        self::assertSame('reception', EnlistmentStatusCatalog::stage(['status' => 'submitted'])['key']);
        self::assertSame('instruction', EnlistmentStatusCatalog::stage(['status' => 'submitted', 'reviewed_by' => 4])['key']);
        self::assertSame('interview', EnlistmentStatusCatalog::stage(['status' => 'submitted', 'pipeline_stage' => 'interview_scheduled'])['key']);
        self::assertSame('on_hold', EnlistmentStatusCatalog::stage(['status' => 'submitted', 'pipeline_stage' => 'on_hold'])['key']);
        self::assertSame('onboarding', EnlistmentStatusCatalog::stage(['status' => 'reviewed', 'pipeline_stage' => 'acceptance_onboarding', 'submitter_user_id' => 3])['key']);
        self::assertSame('link_account', EnlistmentStatusCatalog::stage(['status' => 'reviewed'])['key']);
        $done = EnlistmentStatusCatalog::stage(['status' => 'reviewed', 'pipeline_stage' => 'accepted', 'submitter_user_id' => 3]);
        self::assertSame('integrated', $done['key']);
        self::assertSame(4, $done['index']);
        self::assertSame('closed', EnlistmentStatusCatalog::stage(['status' => 'cancelled'])['key']);
    }

    public function testCancelledHasItsOwnLabel(): void
    {
        self::assertSame('Annulée', EnlistmentStatusCatalog::status('cancelled')['label']);
        self::assertSame('Refusée (auto)', EnlistmentStatusCatalog::status('rejected', true)['label']);
        self::assertTrue(EnlistmentStatusCatalog::isClosed('cancelled'));
        self::assertFalse(EnlistmentStatusCatalog::isClosed('reviewed'));
    }

    public function testRetroIsDueThirtyDaysAfterAcceptanceNotAfterSubmission(): void
    {
        // Déposé il y a 60 j, accepté il y a 10 j : bilan pas encore dû.
        $row = ['status' => 'reviewed', 'created_at' => self::at(60), 'reviewed_at' => self::at(10)];
        $retro = EnlistmentStatusCatalog::retro($row, null, true, self::NOW);
        self::assertSame('waiting', $retro['key']);
        self::assertSame('Dans 20 j', $retro['meta']);

        $row['reviewed_at'] = self::at(45);
        $retro = EnlistmentStatusCatalog::retro($row, null, true, self::NOW);
        self::assertSame('due', $retro['key']);
        self::assertTrue($retro['actionable']);
        self::assertSame('Attendu depuis 15 j', $retro['meta']);
    }

    public function testRetroNeverDueForPendingOrClosedDossiers(): void
    {
        $pending = ['status' => 'submitted', 'created_at' => self::at(90)];
        self::assertSame('pending_decision', EnlistmentStatusCatalog::retro($pending, null, true, self::NOW)['key']);
        foreach (['rejected', 'blocked', 'cancelled'] as $st) {
            self::assertSame('not_applicable', EnlistmentStatusCatalog::retro(['status' => $st, 'created_at' => self::at(90)], null, true, self::NOW)['key']);
        }
        $done = EnlistmentStatusCatalog::retro(['status' => 'reviewed', 'reviewed_at' => self::at(40)], self::at(2), true, self::NOW);
        self::assertSame('done', $done['key']);
        self::assertFalse($done['actionable']);
    }

    public function testDelayForPendingAndDecidedDossiers(): void
    {
        $late = EnlistmentStatusCatalog::delay(['status' => 'submitted', 'submitted_age_hours' => 100], 72, self::NOW);
        self::assertSame('late', $late['key']);
        self::assertSame('ok', EnlistmentStatusCatalog::delay(['status' => 'submitted', 'submitted_age_hours' => 10], 72, self::NOW)['key']);
        self::assertSame('watch', EnlistmentStatusCatalog::delay(['status' => 'submitted', 'submitted_age_hours' => 60], 72, self::NOW)['key']);

        $decided = EnlistmentStatusCatalog::delay(['status' => 'reviewed', 'created_at' => self::at(3), 'reviewed_at' => self::at(1)], 72, self::NOW);
        self::assertSame('done', $decided['key']);
        self::assertSame('Traité en 2 j', $decided['label']);
        self::assertSame('emerald', $decided['tone']);
    }

    public function testRetroAnchorFallsBackToSubmissionDateForLegacyRows(): void
    {
        self::assertSame('2026-01-01 10:00:00', EnlistmentStatusCatalog::retroAnchor(['status' => 'reviewed', 'created_at' => '2026-01-01 10:00:00']));
        self::assertNull(EnlistmentStatusCatalog::retroAnchor(['status' => 'submitted', 'created_at' => '2026-01-01 10:00:00']));
    }
}
