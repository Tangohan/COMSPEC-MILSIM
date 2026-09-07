<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\PersonnelHrPdfService;
use PHPUnit\Framework\TestCase;

final class PersonnelHrDeskAssetTest extends TestCase
{
    public function testWorkspaceDeclaresHrDeskSurfaces(): void
    {
        $root = dirname(__DIR__, 2);
        $shell = (string) file_get_contents($root . '/views/admin/effectifs_workspace/shell.php');
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $css = (string) file_get_contents($root . '/public/assets/css/back-office-effectifs-workspace.css');
        $docs = (string) file_get_contents($root . '/views/admin/effectifs_workspace/rh_documents.php');
        $settings = (string) file_get_contents($root . '/views/admin/effectifs_workspace/rh_settings.php');
        $catalog = (string) file_get_contents($root . '/app/Services/ConfigurationUpdate/ConfigurationUpdateCatalog.php');
        $cron = (string) file_get_contents($root . '/app/Services/Cron/CronSchedule.php');
        $storage = (string) file_get_contents($root . '/app/Support/PersonnelHrDocumentStorage.php');

        self::assertStringContainsString("'rh_roleplay', 'Roleplay'", $shell);
        self::assertStringContainsString("'rh_integration', 'Intégration'", $shell);
        self::assertStringContainsString("'rh_settings', 'Réglages'", $shell);

        self::assertStringContainsString("effectifs/documents-rh/etablir", $routes);
        self::assertStringContainsString("effectifs/roleplay", $routes);
        self::assertStringContainsString("effectifs/integration", $routes);
        self::assertStringContainsString("effectifs/reglages", $routes);

        self::assertStringContainsString('.bo-eff-workspace .eff-rh-form', $css);
        self::assertStringContainsString('.bo-eff-workspace .eff-rh-tile', $css);
        self::assertStringContainsString('Établir une pièce', $docs);
        self::assertStringContainsString('Avancements de grade', $settings);
        self::assertStringContainsString('PERSONNEL_HR_DESK_V1', $catalog);
        self::assertStringContainsString('personnel_auto_advancement', $cron);
        self::assertStringContainsString('function storeFromBinary', $storage);
        self::assertContains('charte', PersonnelHrPdfService::GENERATABLE_TYPES);
        self::assertContains('affectation', PersonnelHrPdfService::GENERATABLE_TYPES);
    }
}
