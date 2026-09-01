<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Tactical\AtakActivityLogService;
use PHPUnit\Framework\TestCase;

final class AtakActivityWebLogTest extends TestCase
{
    private AtakActivityLogService $svc;
    private int $tenantId;
    private int $mapId = 1;

    protected function setUp(): void
    {
        $this->svc = new AtakActivityLogService();
        $this->tenantId = 910000 + random_int(1, 99999);
        $this->svc->purgeAllForTenant($this->tenantId);
    }

    protected function tearDown(): void
    {
        $this->svc->purgeAllForTenant($this->tenantId);
    }

    public function testRecordErrorAppearsUnderIncidentsFilter(): void
    {
        $this->svc->recordError(
            $this->tenantId,
            $this->mapId,
            'Le poste est momentanément injoignable.',
            'Carte web',
            ['token' => 'secret-value', 'source' => 'web']
        );

        $list = $this->svc->listFiltered($this->tenantId, $this->mapId, [
            'type' => 'incidents',
            'limit' => 20,
        ]);

        self::assertNotEmpty($list['events']);
        $ev = $list['events'][0];
        self::assertSame(AtakActivityLogService::TYPE_ERROR, $ev['type']);
        self::assertSame('Le poste est momentanément injoignable.', $ev['label']);
        self::assertSame('Carte web', $ev['actor']);
        self::assertArrayNotHasKey('token', $ev['meta'] ?? []);
        self::assertSame('web', $ev['meta']['source'] ?? null);
    }

    public function testRecordErrorIsThrottledByLabel(): void
    {
        $this->svc->recordError($this->tenantId, $this->mapId, 'Accès refusé pour cette action.', 'Carte web');
        $this->svc->recordError($this->tenantId, $this->mapId, 'Accès refusé pour cette action.', 'Carte web');

        $list = $this->svc->listFiltered($this->tenantId, $this->mapId, [
            'type' => 'incidents',
            'limit' => 20,
        ]);

        self::assertCount(1, $list['events']);
    }

    public function testRecordIngestIsThrottledByKindAndActor(): void
    {
        $this->svc->recordIngest($this->tenantId, $this->mapId, 'web', 'Données transmises au poste', 'Carte web');
        $this->svc->recordIngest($this->tenantId, $this->mapId, 'web', 'Données transmises au poste', 'Carte web');
        $this->svc->recordIngest($this->tenantId, $this->mapId, 'web', 'Effectifs reçus', 'TOC');

        $list = $this->svc->listFiltered($this->tenantId, $this->mapId, [
            'type' => 'donnees',
            'limit' => 20,
        ]);

        self::assertCount(2, $list['events']);
        $actors = array_map(static fn (array $e): string => (string) ($e['actor'] ?? ''), $list['events']);
        sort($actors);
        self::assertSame(['Carte web', 'TOC'], $actors);
    }

    public function testPositionIngestHeartbeatsAreNotJournalised(): void
    {
        $this->svc->recordIngest($this->tenantId, $this->mapId, 'position', 'Position reçue — HAWK-1', 'HAWK-1');
        $this->svc->record($this->tenantId, $this->mapId, AtakActivityLogService::TYPE_CLIENT_INIT, 'Connexion établie — HAWK-1', 'HAWK-1');

        $donnees = $this->svc->listFiltered($this->tenantId, $this->mapId, [
            'type' => 'donnees',
            'limit' => 20,
        ]);
        self::assertSame([], $donnees['events']);

        $recent = $this->svc->listRecent($this->tenantId, $this->mapId, 20);
        self::assertCount(1, $recent);
        self::assertSame(AtakActivityLogService::TYPE_CLIENT_INIT, $recent[0]['type']);
    }

    public function testListRecentHidesLegacyPositionIngestCards(): void
    {
        $dir = dirname(__DIR__, 2) . '/storage/cache/atak-activity';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $path = $dir . '/t' . $this->tenantId . '_m' . $this->mapId . '.json';
        $now = date('c');
        file_put_contents($path, json_encode([
            'next_id' => 4,
            'events' => [
                [
                    'id' => 1,
                    'type' => AtakActivityLogService::TYPE_INGEST,
                    'label' => 'Position reçue — YA1 / Bravo',
                    'actor' => 'YA1 / Bravo',
                    'at' => $now,
                    'meta' => ['kind' => 'position', 'source' => 'terrain'],
                ],
                [
                    'id' => 2,
                    'type' => AtakActivityLogService::TYPE_INGEST,
                    'label' => 'Position reçue — YA1 / Bravo',
                    'actor' => 'YA1 / Bravo',
                    'at' => $now,
                    'meta' => ['kind' => 'position'],
                ],
                [
                    'id' => 3,
                    'type' => AtakActivityLogService::TYPE_CLIENT_INIT,
                    'label' => 'Connexion établie — YA1 / Bravo',
                    'actor' => 'YA1 / Bravo',
                    'at' => $now,
                ],
            ],
        ], JSON_UNESCAPED_UNICODE));

        $recent = $this->svc->listRecent($this->tenantId, $this->mapId, 20);
        self::assertCount(1, $recent);
        self::assertSame('Connexion établie — YA1 / Bravo', $recent[0]['label']);

        $donnees = $this->svc->listFiltered($this->tenantId, $this->mapId, [
            'type' => 'donnees',
            'limit' => 20,
        ]);
        self::assertCount(2, $donnees['events']);
    }

    public function testUrlsAreStrippedFromLabels(): void
    {
        $this->svc->recordError(
            $this->tenantId,
            $this->mapId,
            'Échec https://exemple.invalid/api/atak/units pendant la lecture',
            'Carte web'
        );

        $list = $this->svc->listFiltered($this->tenantId, $this->mapId, [
            'type' => 'incidents',
            'limit' => 5,
        ]);

        self::assertNotEmpty($list['events']);
        self::assertStringNotContainsString('https://', (string) $list['events'][0]['label']);
        self::assertStringNotContainsString('/api/', (string) $list['events'][0]['label']);
    }
}
