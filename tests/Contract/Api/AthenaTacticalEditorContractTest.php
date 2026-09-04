<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AthenaTacticalEditorContractTest extends TestCase
{
    public function testMigrationDefinesRevisionedMarkersAndEvents(): void
    {
        $sql = file_get_contents(dirname(__DIR__, 3) . '/migrations/20260904190000_athena_tactical_editor.sql');
        self::assertIsString($sql);
        self::assertStringContainsString('athena_tactical_markers', $sql);
        self::assertStringContainsString('revision BIGINT UNSIGNED NOT NULL', $sql);
        foreach (['MARKER_CREATE', 'MARKER_UPDATE', 'MARKER_DELETE'] as $event) {
            self::assertStringContainsString($event, $sql);
        }
    }

    public function testRoutesExposeEditorCrudAndCursorSync(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 3) . '/routes/web.php');
        self::assertIsString($routes);
        self::assertStringContainsString("'/api/athena/tactical/sync'", $routes);
        self::assertStringContainsString("'/api/athena/tactical/markers/{uuid}'", $routes);
        self::assertStringContainsString("'/back-office/operations/carte-tactique'", $routes);
    }

    public function testSqfSuppliesTheCurrentWorldToLegacyMarkerChannel(): void
    {
        $sqf = file_get_contents(dirname(__DIR__, 3) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_pollAthenaMarkers.sqf');
        self::assertIsString($sqf);
        self::assertStringContainsString('"world:" + worldName', $sqf);
    }
}
