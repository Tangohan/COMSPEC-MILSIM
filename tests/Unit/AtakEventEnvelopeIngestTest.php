<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\AtakDataRepository;
use App\Repositories\AtakSceneObjectRepository;
use App\Repositories\MapShapeRepository;
use App\Services\Tactical\AtakEventEnvelopeIngest;
use PDO;
use PHPUnit\Framework\TestCase;

final class AtakEventEnvelopeIngestTest extends TestCase
{
    private function pdo(): PDO
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required');
        }
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec(
            'CREATE TABLE atak_map_shapes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tenant_id INTEGER NOT NULL,
                map_id INTEGER NOT NULL DEFAULT 1,
                mission_id TEXT,
                shape_uid TEXT NOT NULL,
                type TEXT NOT NULL,
                label TEXT,
                color TEXT,
                stroke INTEGER,
                fill_opacity REAL,
                created_by TEXT,
                visible_to TEXT,
                geometry TEXT NOT NULL,
                meta TEXT,
                created_at TEXT,
                updated_at TEXT
            )'
        );
        $pdo->exec(
            'CREATE TABLE atak_markers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tenant_id INTEGER NOT NULL,
                map_id INTEGER NOT NULL,
                layer_id INTEGER NOT NULL,
                marker_data TEXT NOT NULL,
                arma_name TEXT,
                updated_at TEXT
            )'
        );
        $pdo->exec(
            'CREATE TABLE atak_scene_objects (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tenant_id INTEGER NOT NULL,
                map_id INTEGER NOT NULL,
                source_id TEXT NOT NULL,
                kind TEXT NOT NULL,
                model_class TEXT,
                world_x REAL,
                world_y REAL,
                world_z REAL,
                bearing REAL,
                width_m REAL,
                depth_m REAL,
                height_m REAL,
                density REAL,
                updated_at TEXT
            )'
        );

        return $pdo;
    }

    private function service(PDO $pdo): AtakEventEnvelopeIngest
    {
        return new AtakEventEnvelopeIngest(
            new AtakSceneObjectRepository($pdo),
            new MapShapeRepository($pdo),
            new AtakDataRepository($pdo),
        );
    }

    public function testPositionUpdateIsNotHistorized(): void
    {
        $pdo = $this->pdo();
        $svc = $this->service($pdo);
        $out = $svc->ingest(4, [
            'schema' => 'athena.event.v1',
            'type' => 'position.update',
            'flow' => 'state',
            'payload' => ['x' => 1, 'y' => 2],
        ]);
        self::assertTrue($out['ok']);
        self::assertSame(0, $out['upserted']);
        self::assertSame('state', $out['ignored'] ?? null);
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM atak_map_shapes')->fetchColumn());
    }

    public function testDrawingEnvelopeUpsertsShapePoints(): void
    {
        $pdo = $this->pdo();
        $svc = $this->service($pdo);
        $envelope = [
            'schema' => 'athena.event.v1',
            'event_id' => '01JTEST',
            'type' => 'drawing.created',
            'flow' => 'event',
            'source' => ['callsign' => 'N-10'],
            'payload' => [
                'object' => [
                    'id' => 'draw_12',
                    'type' => 'polygon',
                    'name' => 'ZONE ROUGE',
                    'points' => [[14223.5, 18241.8], [14542.1, 18021.4], [14724.8, 18412.0]],
                    'style' => ['strokeWidth' => 2, 'fillOpacity' => 0.18, 'color' => '#e24a46'],
                ],
            ],
        ];
        $out = $svc->ingest(4, $envelope);
        self::assertTrue($out['ok']);
        self::assertSame(1, $out['upserted']);
        $row = $pdo->query('SELECT shape_uid, type, label, created_by, geometry FROM atak_map_shapes')->fetch(PDO::FETCH_ASSOC);
        self::assertSame('draw_12', $row['shape_uid']);
        self::assertSame('POLYGON', $row['type']);
        self::assertSame('ZONE ROUGE', $row['label']);
        self::assertSame('N-10', $row['created_by']);
        $geom = json_decode((string) $row['geometry'], true);
        self::assertSame('Polygon', $geom['type']);
        self::assertCount(3, $geom['coordinates']);

        $envelope['payload']['object']['name'] = 'ZONE ROUGE MAJ';
        $again = $svc->ingest(4, $envelope);
        self::assertSame(1, $again['upserted']);
        self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM atak_map_shapes')->fetchColumn());
        self::assertSame('ZONE ROUGE MAJ', $pdo->query('SELECT label FROM atak_map_shapes')->fetchColumn());
    }

    public function testDeletedDrawingRemovesShape(): void
    {
        $pdo = $this->pdo();
        $svc = $this->service($pdo);
        $svc->ingest(4, [
            'schema' => 'athena.event.v1',
            'type' => 'drawing.created',
            'payload' => ['object' => ['id' => 'draw_9', 'type' => 'polygon', 'name' => 'A', 'points' => [[1, 2], [3, 4], [5, 6]]]],
        ]);
        $out = $svc->ingest(4, [
            'schema' => 'athena.event.v1',
            'type' => 'map.object.deleted',
            'payload' => ['object' => ['id' => 'draw_9', 'type' => 'polygon', 'deleted' => true]],
        ]);
        self::assertTrue($out['ok']);
        self::assertSame(1, $out['deleted'] ?? 0);
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM atak_map_shapes')->fetchColumn());
    }
}
