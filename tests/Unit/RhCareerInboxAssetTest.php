<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class RhCareerInboxAssetTest extends TestCase
{
    public function testCareerFileMergesServiceHistoryAndInboxExists(): void
    {
        $root = dirname(__DIR__, 2);
        $career = (string) file_get_contents($root . '/app/Services/Personnel/CareerFileService.php');
        $writer = (string) file_get_contents($root . '/app/Services/Personnel/PersonnelServiceHistoryWriter.php');
        $inbox = (string) file_get_contents($root . '/app/Services/Effectifs/RhActionInboxService.php');
        $view = (string) file_get_contents($root . '/views/admin/effectifs_workspace/rh_inbox.php');
        $shell = (string) file_get_contents($root . '/views/admin/effectifs_workspace/shell.php');
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $controller = (string) file_get_contents($root . '/app/Controllers/Admin/RhDossierWorkspaceController.php');
        $carriere = (string) file_get_contents($root . '/views/admin/member_situation/carriere.php');
        $elevation = (string) file_get_contents($root . '/app/Services/Effectifs/ElevationApprovalService.php');
        $correction = (string) file_get_contents($root . '/app/Services/Personnel/PersonnelCorrectionRequestService.php');

        self::assertStringContainsString('PersonnelServiceHistoryRepository', $career);
        self::assertStringContainsString('service_history', $career);
        self::assertStringContainsString('function recordElevation', $writer);
        self::assertStringContainsString('function recordAward', $writer);
        self::assertStringContainsString('function recordCorrectionApplied', $writer);

        self::assertStringContainsString('class RhActionInboxService', $inbox);
        self::assertStringContainsString('Corrections à traiter', $inbox);
        self::assertStringContainsString('Élévations à examiner', $inbox);
        self::assertStringContainsString('Dossiers incomplets', $inbox);

        self::assertStringContainsString('À traiter', $view);
        self::assertStringContainsString('File d’actions', $view);
        self::assertStringContainsString('Le système propose ; vous décidez', $view);

        self::assertStringContainsString("['rh_inbox', 'À traiter', 'a-traiter'", $shell);
        self::assertStringContainsString("effectifs_workspace_url('a-traiter')", $shell);
        self::assertStringContainsString("'/back-office/ressources/effectifs/a-traiter'", $routes);
        self::assertStringContainsString('function inbox', $controller);

        self::assertStringContainsString("'promotion' => 'Promotion'", $carriere);
        self::assertStringContainsString("'assignment' => 'Affectation'", $carriere);

        self::assertStringContainsString('recordElevation', $elevation);
        self::assertStringContainsString('recordCorrectionApplied', $correction);
    }
}
