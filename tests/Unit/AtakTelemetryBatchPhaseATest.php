<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Tactical\AtakTelemetryBatchIngest;
use App\Services\Tactical\AtakTelemetryJournalService;
use PHPUnit\Framework\TestCase;

final class AtakTelemetryBatchPhaseATest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . '/comspec-telemetry-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->tmpDir);
        parent::tearDown();
    }

    public function testJournalAfterIdIsMonotonic(): void
    {
        $journal = new AtakTelemetryJournalService($this->tmpDir);
        $a = $journal->append(9, 1, 'pos', ['call_sign' => 'N-10']);
        $b = $journal->append(9, 1, 'pos', ['call_sign' => 'N-10']);
        self::assertSame(1, $a);
        self::assertSame(2, $b);
        self::assertSame(2, $journal->latestId(9, 1));

        $after = $journal->listAfter(9, 1, 1, 10);
        self::assertCount(1, $after);
        self::assertSame(2, (int) $after[0]['id']);
    }

    public function testBatchIngestDelegatesPositionAndJournals(): void
    {
        $journal = new AtakTelemetryJournalService($this->tmpDir);
        $atak = $this->createMock(\App\Repositories\AtakDataRepository::class);
        $ingest = new AtakTelemetryBatchIngest($atak, $journal);

        $calls = 0;
        $result = $ingest->ingest(3, 1, [
            'seq' => 42,
            'ts' => 1_700_000_000,
            'events' => [
                ['t' => 'pos', 'x' => 1831, 'y' => 5420, 'h' => 271, 'call_sign' => 'N-10'],
                ['t' => 'unknown_type', 'x' => 1],
            ],
        ], static function (array $body) use (&$calls): array {
            $calls++;
            self::assertSame('N-10', $body['call_sign']);
            self::assertSame(1831.0, (float) $body['pos_x']);
            self::assertSame(5420.0, (float) $body['pos_y']);

            return ['ok' => true, 'call_sign' => 'N-10'];
        });

        self::assertSame(1, $calls);
        self::assertSame(1, $result['accepted']);
        self::assertSame(1, $result['rejected']);
        self::assertSame(42, $result['seq']);
        self::assertGreaterThan(0, $result['last_id']);
        self::assertFalse($result['ok']);
    }

    public function testExtensionExposesTelemetryBatchCapability(): void
    {
        $root = dirname(__DIR__, 2);
        $extension = (string) file_get_contents($root . '/mod/UptoDate/COMSPECExtension/Extension.cs');
        $telemetry = (string) file_get_contents($root . '/mod/UptoDate/COMSPECExtension/Extension_Telemetry.cs');
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $api = (string) file_get_contents($root . '/app/Controllers/Api/AtakApiController.php');
        $sqfEmit = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_emitTelemetryEvent.sqf');
        $sqfMed = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_reportMedicalAlert.sqf');

        self::assertStringContainsString('TryOfferBatchablePost', $extension);
        self::assertStringContainsString('TryDrainTelemetryBatch', $extension);
        self::assertStringContainsString('TelemetryBatch', $extension);
        self::assertStringContainsString('GetTelemetryMetrics', $extension);
        self::assertStringContainsString('EmitTelemetry', $extension);
        self::assertStringContainsString('ApplyEmitTelemetry', $telemetry);
        self::assertStringContainsString('class TelemetryItem', $telemetry);
        self::assertStringContainsString('/api/atak/telemetry/batch', $telemetry);
        self::assertStringContainsString("post('/api/atak/telemetry/batch'", $routes);
        self::assertStringContainsString("get('/api/atak/telemetry/events'", $routes);
        self::assertStringContainsString('function telemetryBatch', $api);
        self::assertStringContainsString('function ingestPositionPayload', $api);
        self::assertStringContainsString('telmed_', $api);
        self::assertStringContainsString('EmitTelemetry', $sqfEmit);
        self::assertStringContainsString('emitTelemetryEvent', $sqfMed);
        self::assertStringContainsString('EnqueueOrSend(_baseUrl + "/api/atak/position", payload)', $extension);
        self::assertStringContainsString('initLogisticsLoop', (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_startSyncLoops.sqf'));
        self::assertStringContainsString('logstat', $telemetry);
        self::assertStringContainsString('tx_start', (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_updatePosition.sqf'));
        self::assertStringContainsString("get('/api/atak/telemetry/comms'", $routes);
    }

    public function testMedicalStoreAndBatchTypes(): void
    {
        $journal = new AtakTelemetryJournalService($this->tmpDir);
        $medDir = $this->tmpDir . '/med';
        mkdir($medDir);
        $store = new \App\Services\Tactical\AtakTelemetryMedicalStore($medDir);
        $atak = $this->createMock(\App\Repositories\AtakDataRepository::class);
        $ingest = new AtakTelemetryBatchIngest($atak, $journal, null, null, null, $store);

        $result = $ingest->ingest(2, 1, [
            'events' => [
                ['t' => 'med', 'kind' => 'unconscious', 'call_sign' => 'N-12', 'hr' => 40, 'blood' => 55, 'x' => 1, 'y' => 2],
                ['t' => 'unit', 'action' => 'enter', 'call_sign' => 'N-12', 'vehicle' => 'MRAP'],
                ['t' => 'combat', 'kind' => 'hit', 'n' => 2, 'call_sign' => 'N-12'],
            ],
        ], static fn (): array => ['ok' => true]);

        self::assertSame(3, $result['accepted']);
        $active = $store->listActive(2, 1);
        self::assertNotEmpty($active);
        self::assertSame('N-12', $active[0]['call_sign']);
        self::assertStringStartsWith('telmed_', (string) $active[0]['id']);
    }

    public function testCommsJournalAndPhaseCTypes(): void
    {
        $journal = new AtakTelemetryJournalService($this->tmpDir . '/j');
        $commsDir = $this->tmpDir . '/comms';
        mkdir($commsDir);
        $comms = new \App\Services\Tactical\AtakTelemetryCommsJournal($commsDir);
        $atak = $this->createMock(\App\Repositories\AtakDataRepository::class);
        $atak->method('upsertAirAsset')->willReturn(['id' => 1, 'callsign' => 'HAWK-1']);
        $ingest = new AtakTelemetryBatchIngest($atak, $journal, null, null, null, null, $comms, null, null);

        $result = $ingest->ingest(4, 1, [
            'events' => [
                ['t' => 'comms', 'action' => 'tx_start', 'call_sign' => 'N-10', 'freq' => '51.200', 'channel' => '3'],
                ['t' => 'comms', 'action' => 'tx_end', 'call_sign' => 'N-10', 'duration_s' => 4.8],
                ['t' => 'state', 'call_sign' => 'N-10', 'fuel' => '78', 'crew_count' => 4],
                ['t' => 'flight', 'callsign' => 'HAWK-1', 'fuel_pct' => 60, 'speed' => 80, 'damage' => 0.1],
            ],
        ], static fn (): array => ['ok' => true]);

        self::assertSame(4, $result['accepted']);
        $listed = $comms->listAfter(4, 1, 0, 10);
        self::assertCount(2, $listed);
        self::assertSame('tx_start', $listed[0]['action']);
    }
}
