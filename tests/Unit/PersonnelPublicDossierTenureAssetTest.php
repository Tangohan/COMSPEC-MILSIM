<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PersonnelPublicDossierTenureAssetTest extends TestCase
{
    public function testPublicFileLoadsRichestDossierAndCoalescesHistory(): void
    {
        $root = dirname(__DIR__, 2);
        $controller = (string) file_get_contents($root . '/app/Controllers/Web/PersonnelController.php');
        $file = (string) file_get_contents($root . '/views/personnel/file.php');
        $tableau = (string) file_get_contents($root . '/views/partials/personnel/file_tableau_admin_tab.php');
        $script = (string) file_get_contents($root . '/scripts/coalesce-personnel-assignment-history.php');

        self::assertStringContainsString('getByUserId($uid, (int) $tenantId)', $controller);
        self::assertStringContainsString('PersonnelAssignmentHistoryCoalescer::coalesceForDisplay', $controller);
        self::assertStringContainsString('SeniorityDossierInferenceSyncService', $controller);

        self::assertStringContainsString('membershipCandidates', $file);
        self::assertStringContainsString('allers-retours du même jour', $file);
        self::assertStringNotContainsString('$pushRow($sheetRows, $panelName, \'(vide)\'', $tableau);
        self::assertStringContainsString('if (!is_array($data) || $data === []) {', $tableau);
        self::assertStringContainsString('continue;', $tableau);

        self::assertStringContainsString('--apply', $script);
        self::assertStringContainsString('persistCoalescedHistoryForUser', $script);
    }
}