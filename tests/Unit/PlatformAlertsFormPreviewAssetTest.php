<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PlatformAlertsFormPreviewAssetTest extends TestCase
{
    public function testCreateFormHasPreviewsAndMultiPlacement(): void
    {
        $form = (string) file_get_contents(dirname(__DIR__, 2) . '/views/admin/system/platform_alerts_form.php');
        $ctrl = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Admin/System/SystemPlatformAlertsController.php');
        $svc = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Alerts/AlertPresentationService.php');

        self::assertStringContainsString('name="display_styles[]"', $form);
        self::assertStringContainsString('type="checkbox"', $form);
        self::assertStringContainsString('pa-mock', $form);
        self::assertStringContainsString('Aperçu avec votre texte', $form);
        self::assertStringContainsString('pa-live', $form);
        self::assertStringContainsString('plusieurs emplacements', $form);
        self::assertStringContainsString('display_styles', $ctrl);
        self::assertStringContainsString('parsePlatformList', $ctrl);
        self::assertStringContainsString('foreach (\\App\\Support\\AlertDisplayStyle::parsePlatformList', $svc);
    }
}
