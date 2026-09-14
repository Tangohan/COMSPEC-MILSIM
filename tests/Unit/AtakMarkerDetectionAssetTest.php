<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakMarkerDetectionAssetTest extends TestCase
{
    public function testAdminGameAndOverwatchWireMarkerDetection(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $nav = (string) file_get_contents($root . '/views/partials/ath_sidebar_nav.php');
        $view = (string) file_get_contents($root . '/views/admin/organization/atak_marker_detection.php');
        $controller = (string) file_get_contents($root . '/app/Controllers/Api/AtakApiController.php');
        $admin = (string) file_get_contents($root . '/app/Controllers/Admin/Organization/AtakMarkerDetectionAdminController.php');
        $overwatch = (string) file_get_contents($root . '/public/assets/js/atak-overwatch-beta.js');
        $sqf = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_pollPoMarkers.sqf'
        );
        $poll = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_pollMarkerDetectionRules.sqf'
        );
        $loops = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_startSyncLoops.sqf'
        );
        $cfg = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp'
        );
        $ext = (string) file_get_contents($root . '/mod/UptoDate/COMSPECExtension/Extension.cs');
        $catalog = (string) file_get_contents($root . '/app/Services/ConfigurationUpdate/ConfigurationUpdateCatalog.php');
        $seed = (string) file_get_contents($root . '/bootstrap/configuration_updates_migration.php');
        $migrate = (string) file_get_contents($root . '/run-migrations.php');

        self::assertStringContainsString("back-office/atak/detection-marqueurs", $routes);
        self::assertStringContainsString('AtakMarkerDetectionAdminController', $routes);
        self::assertStringContainsString('/api/atak/marker-detection-rules', $routes);
        self::assertStringContainsString('Détection des marqueurs', $nav);
        self::assertStringContainsString('Nom de la règle', $view);
        self::assertStringContainsString('Confirmer le point lorsqu’un téléphone ATAK entre dans le rayon', $view);
        self::assertStringNotContainsString('endpoint', strtolower($view));
        self::assertStringNotContainsString('JSON', $view);
        self::assertStringContainsString('markerDetectionRulesIndex', $controller);
        self::assertStringContainsString('markerDetectionService()->applyToJson', $controller);
        self::assertStringContainsString('ATAK_MARKER_DETECTION_V1', $admin);
        self::assertStringContainsString('data.detection', $overwatch);
        self::assertStringContainsString('detection_radius_m', $overwatch);
        self::assertStringContainsString('Point suivi atteint', $overwatch);
        self::assertStringContainsString('Point suivi atteint', $sqf);
        self::assertStringContainsString('_COMSPEC_DET_RING_', $sqf);
        self::assertStringContainsString('GetMarkerDetectionRules', $poll);
        self::assertStringContainsString('pollMarkerDetectionRules', $loops);
        self::assertStringContainsString('class pollMarkerDetectionRules {}', $cfg);
        self::assertStringContainsString('GetMarkerDetectionRules', $ext);
        self::assertStringContainsString('SimplifyMarkerDetectionRulesJson', $ext);
        self::assertStringContainsString('ATAK_MARKER_DETECTION_V1', $catalog);
        self::assertStringContainsString('ATAK_MARKER_DETECTION_V1', $seed);
        self::assertStringContainsString('run_atak_marker_detection_rules_migration', $migrate);
    }
}
