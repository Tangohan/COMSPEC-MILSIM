<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakActivityPositionIngestAssetTest extends TestCase
{
    public function testPositionHandlerDoesNotJournalEveryHeartbeat(): void
    {
        $root = dirname(__DIR__, 2);
        $api = (string) file_get_contents($root . '/app/Controllers/Api/AtakApiController.php');
        $svc = (string) file_get_contents($root . '/app/Services/Tactical/AtakActivityLogService.php');
        $js = (string) file_get_contents($root . '/public/assets/js/atak-activity.js');

        self::assertStringNotContainsString("'Position reçue — ' . \$callSign", $api);
        self::assertStringContainsString('recordFromPosition(', $api);
        self::assertStringContainsString("if (\$kind === 'position') {", $svc);
        self::assertStringContainsString("'exclude_position_ingest' => true", $svc);
        self::assertStringContainsString('function isRoutinePositionIngest', $js);
        self::assertStringContainsString('dropRoutinePositionIngest', $js);
        self::assertStringNotContainsString('endpoint', $svc);
    }
}
