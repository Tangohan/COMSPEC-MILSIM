<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\TacticalTrackRepository;
use App\Services\Tactical\TacticalTrackService;
use PHPUnit\Framework\TestCase;

final class TacticalTrackPhaseDTest extends TestCase
{
    public function testBdaStaysCandidateAndIgnoresEngineDestroyed(): void
    {
        $repo = $this->createMock(TacticalTrackRepository::class);
        $repo->expects(self::once())
            ->method('upsertTrack')
            ->with(
                1,
                2,
                self::callback(static function (array $data): bool {
                    return ($data['status'] ?? '') === 'candidate'
                        && ($data['layer'] ?? '') === 'observation'
                        && ($data['source'] ?? '') === 'bda'
                        && empty($data['meta']['engine_destroyed']);
                })
            )
            ->willReturn([
                'id' => 10,
                'track_uid' => 'TRK-BDA1',
                'status' => 'candidate',
                'layer' => 'observation',
            ]);
        $repo->expects(self::once())
            ->method('addObservation')
            ->willReturn(['id' => 1, 'obs_uid' => 'OBS-1']);

        $svc = new TacticalTrackService($repo, null, null);
        $result = $svc->ingestObservation(1, 2, [
            't' => 'bda',
            'x' => 100,
            'y' => 200,
            'engine_destroyed' => true,
            'getDammage' => 1,
            'assessment' => 'DESTROYED',
            'call_sign' => 'N-10',
        ], 'N-10');

        self::assertTrue($result['ok']);
        self::assertSame('candidate', $result['track']['status'] ?? null);
    }

    public function testBdaConfirmMovesToAssessment(): void
    {
        $repo = $this->createMock(TacticalTrackRepository::class);
        $repo->expects(self::once())
            ->method('upsertTrack')
            ->with(
                1,
                2,
                self::callback(static function (array $data): bool {
                    return ($data['status'] ?? '') === 'confirmed'
                        && ($data['layer'] ?? '') === 'assessment'
                        && ($data['track_uid'] ?? '') === 'TRK-REF';
                })
            )
            ->willReturn([
                'id' => 11,
                'track_uid' => 'TRK-REF',
                'status' => 'confirmed',
                'layer' => 'assessment',
            ]);
        $repo->method('addObservation')->willReturn(['id' => 2, 'obs_uid' => 'OBS-2']);

        $svc = new TacticalTrackService($repo, null, null);
        $result = $svc->ingestObservation(1, 2, [
            't' => 'bda_confirm',
            'ref_track' => 'TRK-REF',
            'x' => 100,
            'y' => 200,
        ], 'TOC');

        self::assertTrue($result['ok']);
        self::assertSame('assessment', $result['track']['layer'] ?? null);
    }

    public function testExtensionVersionIsPhaseD(): void
    {
        $root = dirname(__DIR__, 2);
        $extension = (string) file_get_contents($root . '/mod/UptoDate/COMSPECExtension/Extension.cs');
        self::assertStringContainsString('ExtensionVersion = "2.0.56"', $extension);
        self::assertFileExists($root . '/docs/technique/telemetry-bus-phase-d.md');
    }
}
