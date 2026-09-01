<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakAccessGapAssetTest extends TestCase
{
    public function testMapOffersAccessRequestModalInFrench(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/atak.php');
        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-access-gap.js');
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/atak.css');
        $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');
        $ctrl = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/AtakController.php');

        self::assertStringContainsString('id="atak-access-gap-modal"', $view);
        self::assertStringContainsString('Certaines vues ne vous sont pas ouvertes', $view);
        self::assertStringContainsString('Demander les autorisations d’accès', $view);
        self::assertStringContainsString('window.ATAK_ACCESS_GAP', $view);
        self::assertStringContainsString('atak-access-gap.js', $view);
        self::assertStringContainsString('grade, votre rôle ou votre fonction', $view);
        self::assertStringNotContainsString('personnel.profile.view', $view);
        self::assertStringNotContainsString('endpoint', $view);

        self::assertStringContainsString('isLiveInGame', $js);
        self::assertStringContainsString('linked', $js);
        self::assertStringContainsString('/atak/demande-acces', $js);
        self::assertStringContainsString('ATAK_ACCESS_GAP', $js);
        self::assertStringContainsString('phoneSession', $js);
        self::assertStringContainsString('ATAK_POPOUT', $js);

        self::assertStringContainsString('.atak-access-gap-modal', $css);

        self::assertStringContainsString("'requestMapAccess'", $routes);
        self::assertStringContainsString('/atak/demande-acces', $routes);
        self::assertStringContainsString('function requestMapAccess', $ctrl);
        self::assertStringContainsString('requestElevation', $ctrl);
        self::assertStringContainsString("'droits'", $ctrl);
    }

    public function testAccessGapServiceIsWiredOnTheMapPage(): void
    {
        $ctrl = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/AtakController.php');
        self::assertStringContainsString('AtakMapAccessGapService', $ctrl);
        self::assertStringContainsString('atakAccessGap', $ctrl);
        self::assertStringContainsString('EffectifsStaffAlertService', $ctrl);
    }
}
