<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Audit\AuditAction;
use App\Support\Audit\AuditSnapshotPresenter;
use PHPUnit\Framework\TestCase;

final class AuditSnapshotPresenterTest extends TestCase
{
    public function testFlattenNestedRestrictionsToFrenchLabels(): void
    {
        $old = json_encode(['restrictions' => ['account' => ['lock' => false]]], JSON_THROW_ON_ERROR);
        $new = json_encode(['restrictions' => ['account' => ['lock' => true]]], JSON_THROW_ON_ERROR);
        $rows = AuditSnapshotPresenter::diffRows($old, $new);
        $this->assertNotEmpty($rows);
        $first = $rows[0];
        $this->assertSame('Verrouillage du compte', $first['label']);
        $this->assertSame('Non', $first['before']);
        $this->assertSame('Oui', $first['after']);
    }

    public function testListSummaryIsHumanReadable(): void
    {
        $old = '{"status":"active"}';
        $new = '{"status":"suspended"}';
        $summary = AuditSnapshotPresenter::listSummary($old, $new);
        $this->assertStringContainsString('État du compte', $summary);
        $this->assertStringContainsString('Compte actif', $summary);
        $this->assertStringContainsString('Compte suspendu', $summary);
        $this->assertStringNotContainsString('{', $summary);
    }

    public function testAuditRollbackConstants(): void
    {
        $this->assertSame('audit.rollback', AuditAction::AUDIT_ROLLBACK);
        $this->assertSame('audit.rollback_alert', AuditAction::AUDIT_ROLLBACK_ALERT);
    }
}
