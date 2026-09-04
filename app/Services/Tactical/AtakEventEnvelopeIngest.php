<?php

declare(strict_types=1);

namespace App\Services\Tactical;

use App\Repositories\AtakDataRepository;
use App\Repositories\AtakSceneObjectRepository;
use App\Repositories\MapShapeRepository;

/**
 * Accepte soit le relevé de théâtre ({objects:[{x,y}]}), soit une enveloppe
 * athena.event.v1 émise par le téléphone (dessin, marqueur, zone).
 */
final class AtakEventEnvelopeIngest
{
    private const STATE_TYPES = [
        'position.update' => true,
        'bft.snapshot' => true,
        'weather.update' => true,
        'heading.update' => true,
        'speed.update' => true,
        'altitude.update' => true,
        'vehicle.update' => true,
        'health.update' => true,
        'radio.update' => true,
    ];

    public function __construct(
        private AtakSceneObjectRepository $objects,
        private MapShapeRepository $shapes,
        private AtakDataRepository $markers,
    ) {
    }

    /** @param array<string, mixed> $body */
    public function ingest(int $tenantId, array $body): array
    {
        $mapId = max(1, (int) ($body['mapId'] ?? $body['map_id'] ?? 1));
        $schema = (string) ($body['schema'] ?? '');
        if ($schema === 'athena.event.v1') {
            return $this->ingestEnvelope($tenantId, $mapId, $body);
        }
        $items = $body['objects'] ?? [];
        if (!is_array($items) || $items === []) {
            return ['ok' => false, 'error' => 'objects_required', 'upserted' => 0];
        }
        $count = $this->objects->upsertBatch($tenantId, $mapId, array_values($items));

        return ['ok' => true, 'upserted' => $count];
    }

    /** @param array<string, mixed> $body */
    private function ingestEnvelope(int $tenantId, int $mapId, array $body): array
    {
        $type = strtolower((string) ($body['type'] ?? ''));
        $flow = strtolower((string) ($body['flow'] ?? ''));
        if ($flow === 'state' || isset(self::STATE_TYPES[$type])) {
            return ['ok' => true, 'upserted' => 0, 'ignored' => 'state'];
        }
        $payload = is_array($body['payload'] ?? null) ? $body['payload'] : [];
        $object = is_array($payload['object'] ?? null) ? $payload['object'] : null;
        $batch = $payload['objects'] ?? null;
        if (is_array($batch) && $batch !== []) {
            $count = $this->objects->upsertBatch($tenantId, $mapId, array_values($batch));

            return ['ok' => true, 'upserted' => $count];
        }
        if (!is_array($object) || $object === []) {
            return ['ok' => false, 'error' => 'objects_required', 'upserted' => 0];
        }
        $deleted = str_contains($type, 'deleted') || !empty($object['deleted']);
        $kind = strtolower((string) ($object['type'] ?? ''));
        $isMarker = str_starts_with($type, 'marker.') || in_array($kind, ['point', 'rally', 'text'], true);
        if ($isMarker) {
            return $this->upsertMarker($tenantId, $mapId, $body, $object, $deleted);
        }

        return $this->upsertShape($tenantId, $mapId, $body, $object, $deleted);
    }

    /** @param array<string, mixed> $body */
    /** @param array<string, mixed> $object */
    private function upsertMarker(int $tenantId, int $mapId, array $body, array $object, bool $deleted): array
    {
        $uid = substr(trim((string) ($object['id'] ?? '')), 0, 128);
        if ($uid === '') {
            return ['ok' => false, 'error' => 'objects_required', 'upserted' => 0];
        }
        if ($deleted) {
            $this->markers->deleteMarkerByArmaName($tenantId, $mapId, $uid);

            return ['ok' => true, 'upserted' => 0, 'deleted' => 1];
        }
        $markerData = json_encode($object, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        $this->markers->upsertMarkerByArmaName($tenantId, $mapId, 1, $uid, $markerData);

        return ['ok' => true, 'upserted' => 1];
    }

    /** @param array<string, mixed> $body */
    /** @param array<string, mixed> $object */
    private function upsertShape(int $tenantId, int $mapId, array $body, array $object, bool $deleted): array
    {
        $uid = substr(trim((string) ($object['id'] ?? '')), 0, 64);
        if ($uid === '') {
            return ['ok' => false, 'error' => 'objects_required', 'upserted' => 0];
        }
        if ($deleted) {
            $this->shapes->deleteByUid($tenantId, $mapId, $uid);

            return ['ok' => true, 'upserted' => 0, 'deleted' => 1];
        }
        $points = $object['points'] ?? [];
        $coords = [];
        if (is_array($points)) {
            foreach ($points as $pt) {
                if (!is_array($pt) || !isset($pt[0], $pt[1]) || !is_numeric($pt[0]) || !is_numeric($pt[1])) {
                    continue;
                }
                $coords[] = [(float) $pt[0], (float) $pt[1]];
            }
        }
        $style = is_array($object['style'] ?? null) ? $object['style'] : [];
        $source = is_array($body['source'] ?? null) ? $body['source'] : [];
        $kind = strtolower((string) ($object['type'] ?? 'polygon'));
        $closed = in_array($kind, ['polygon', 'ao', 'danger', 'exclusion', 'area', 'circle', 'ellipse', 'rectangle', 'sector'], true);
        $this->shapes->upsertByUid($tenantId, $mapId, [
            'shape_uid' => $uid,
            'type' => strtoupper($kind),
            'label' => (string) ($object['name'] ?? $kind),
            'color' => (string) ($style['color'] ?? '#7ee0c4'),
            'stroke' => (int) ($style['strokeWidth'] ?? 2),
            'fill_opacity' => (float) ($style['fillOpacity'] ?? ($closed ? 0.18 : 0)),
            'created_by' => (string) ($source['callsign'] ?? $object['author'] ?? ''),
            'geometry' => [
                'type' => $closed ? 'Polygon' : (count($coords) > 1 ? 'LineString' : 'Point'),
                'coordinates' => $coords,
            ],
            'meta' => [
                'event_id' => (string) ($body['event_id'] ?? ''),
                'event_type' => (string) ($body['type'] ?? ''),
                'fields' => $object['fields'] ?? [],
            ],
        ]);

        return ['ok' => true, 'upserted' => 1];
    }
}
