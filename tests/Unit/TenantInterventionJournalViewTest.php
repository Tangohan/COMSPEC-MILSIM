<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class TenantInterventionJournalViewTest extends TestCase
{
    public function testJournalUsesReadableDiffTableAndEmptyState(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/admin/system/tenant_intervention.php');

        self::assertStringContainsString('AuditSnapshotPresenter::diffRows', $view);
        self::assertStringContainsString('Aucune donnée n’a été modifiée par cet événement.', $view);
        self::assertStringContainsString('<th class="px-4 py-3">Avant</th>', $view);
        self::assertStringContainsString('<th class="px-4 py-3">Après</th>', $view);
        self::assertStringNotContainsString('json_encode(json_decode', $view);
        self::assertStringNotContainsString('bg-slate-950', $view);
    }
}
