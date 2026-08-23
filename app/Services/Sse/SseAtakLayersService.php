<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseAtakLayersRepository;
use App\Repositories\SseCaseMapRepository;
use App\Repositories\SseDigitalLabRepository;
use App\Support\SseAtakLayersCatalog;
use App\Support\SseIntelCycleCatalog;

/**
 * LOT 5 — Agrège calques ATAK : dossiers, PIR, taskings, photos, tracks, historique.
 */
final class SseAtakLayersService
{
    public function __construct(
        private ?SseCaseMapRepository $maps = null,
        private ?SseAtakLayersRepository $layers = null,
    ) {
        $this->maps ??= new SseCaseMapRepository();
        $this->layers ??= new SseAtakLayersRepository();
    }

    /**
     * Payload overlay unifié (rétro-compatible `points` + `layers`).
     *
     * @return array<string,mixed>
     */
    public function buildOverlay(int $tenantId, int $mapId = 0): array
    {
        $caseFeatures = $this->maps->listAtakOverlay($tenantId, $mapId);
        $sites = $this->maps->listAtakSites($tenantId, $mapId);
        $pirs = $this->layers->listPirPins($tenantId, $mapId);
        $taskings = $this->layers->listTaskingPins($tenantId, $mapId);
        $photos = $this->layers->listFieldPhotos($tenantId, $mapId);
        $tracks = $this->layers->listTracks($tenantId, $mapId);
        $history = $this->layers->listHistoryPoints($tenantId, $mapId);

        $layersOut = [
            'cases' => ['id' => 'cases', 'label' => SseAtakLayersCatalog::layerLabel('cases'), 'points' => [], 'polylines' => []],
            'pir' => ['id' => 'pir', 'label' => SseAtakLayersCatalog::layerLabel('pir'), 'points' => [], 'polylines' => []],
            'taskings' => ['id' => 'taskings', 'label' => SseAtakLayersCatalog::layerLabel('taskings'), 'points' => [], 'polylines' => []],
            'photos' => ['id' => 'photos', 'label' => SseAtakLayersCatalog::layerLabel('photos'), 'points' => [], 'polylines' => []],
            'tracks' => ['id' => 'tracks', 'label' => SseAtakLayersCatalog::layerLabel('tracks'), 'points' => [], 'polylines' => []],
            'ghost_tracks' => ['id' => 'ghost_tracks', 'label' => SseAtakLayersCatalog::layerLabel('ghost_tracks'), 'points' => [], 'polylines' => []],
            'history' => ['id' => 'history', 'label' => SseAtakLayersCatalog::layerLabel('history'), 'points' => [], 'polylines' => []],
            'intel' => ['id' => 'intel', 'label' => SseAtakLayersCatalog::layerLabel('intel'), 'points' => [], 'polylines' => []],
        ];

        $flat = [];

        foreach ($caseFeatures as $f) {
            $pt = $this->point(
                'feat-' . (int) ($f['id'] ?? 0),
                'cases',
                'feature',
                (string) ($f['kind'] ?? 'ping'),
                (string) ($f['label'] ?? ''),
                (string) ($f['note'] ?? ''),
                (string) ($f['color'] ?? SseAtakLayersCatalog::colorFor('cases')),
                (float) ($f['arma_x'] ?? 0),
                (float) ($f['arma_y'] ?? 0),
                (int) ($f['case_id'] ?? 0),
                (string) ($f['case_ref'] ?? ''),
                (string) ($f['case_title'] ?? '')
            );
            $layersOut['cases']['points'][] = $pt;
            $flat[] = $pt;
        }

        foreach ($sites as $s) {
            $pt = $this->point(
                'site-' . (int) ($s['site_id'] ?? 0),
                'cases',
                'site',
                'site',
                (string) ($s['designation'] ?? 'Site'),
                (string) ($s['grid_reference'] ?? ''),
                SseAtakLayersCatalog::colorFor('site'),
                (float) ($s['pos_x'] ?? 0),
                (float) ($s['pos_y'] ?? 0),
                (int) ($s['case_id'] ?? 0),
                (string) ($s['case_ref'] ?? ''),
                (string) ($s['case_title'] ?? '')
            );
            $layersOut['cases']['points'][] = $pt;
            $flat[] = $pt;
        }

        foreach ($pirs as $r) {
            $type = strtoupper((string) ($r['req_type'] ?? 'PIR'));
            $pt = $this->point(
                'pir-' . (int) ($r['id'] ?? 0),
                'pir',
                'pir',
                $type,
                (string) ($r['title'] ?? ''),
                SseIntelCycleCatalog::requirementTypeLabel($type)
                    . ' · ' . SseIntelCycleCatalog::statusLabel('requirement', (string) ($r['status'] ?? '')),
                SseAtakLayersCatalog::colorFor('pir'),
                (float) ($r['pos_x'] ?? 0),
                (float) ($r['pos_y'] ?? 0),
                (int) ($r['case_id'] ?? 0),
                (string) ($r['case_ref'] ?? ''),
                (string) ($r['case_title'] ?? ''),
                ['reference' => (string) ($r['reference_code'] ?? ''), 'priority' => (string) ($r['priority'] ?? '')]
            );
            $layersOut['pir']['points'][] = $pt;
            $flat[] = $pt;
        }

        foreach ($taskings as $t) {
            $pt = $this->point(
                'task-' . (int) ($t['id'] ?? 0),
                'taskings',
                'tasking',
                'tasking',
                (string) ($t['title'] ?? ''),
                trim(
                    ((string) ($t['tasked_unit'] ?? '')) !== ''
                        ? (string) $t['tasked_unit']
                        : ((string) ($t['tasked_callsign'] ?? ''))
                ),
                SseAtakLayersCatalog::colorFor('taskings'),
                (float) ($t['pos_x'] ?? 0),
                (float) ($t['pos_y'] ?? 0),
                (int) ($t['case_id'] ?? 0),
                (string) ($t['case_ref'] ?? ''),
                (string) ($t['case_title'] ?? ''),
                ['status' => SseIntelCycleCatalog::statusLabel('tasking', (string) ($t['status'] ?? ''))]
            );
            $layersOut['taskings']['points'][] = $pt;
            $flat[] = $pt;
        }

        foreach ($photos as $p) {
            $path = (string) ($p['image_path'] ?? '');
            $pt = $this->point(
                'photo-' . (int) ($p['id'] ?? 0),
                'photos',
                'photo',
                (string) ($p['photo_type'] ?? 'terrain'),
                'Photo terrain',
                (string) ($p['quality'] ?? ''),
                SseAtakLayersCatalog::colorFor('photos'),
                (float) ($p['pos_x'] ?? 0),
                (float) ($p['pos_y'] ?? 0),
                (int) ($p['case_id'] ?? 0),
                (string) ($p['case_ref'] ?? ''),
                (string) ($p['case_title'] ?? ''),
                [
                    'photo_url' => $path !== '' ? user_media_public_url($path) : null,
                    'heading' => $p['heading'] ?? null,
                    'created_at' => $p['created_at'] ?? null,
                ]
            );
            $layersOut['photos']['points'][] = $pt;
            $flat[] = $pt;
        }

        foreach ($tracks as $tr) {
            $kind = (string) ($tr['track_kind'] ?? 'live');
            $layerKey = $kind === 'ghost' ? 'ghost_tracks' : ($kind === 'history' ? 'history' : 'tracks');
            $line = [
                'id' => 'track-' . (int) ($tr['id'] ?? 0),
                'layer' => $layerKey,
                'track_kind' => $kind,
                'label' => (string) ($tr['label'] ?? ''),
                'callsign' => $tr['callsign'] ?? null,
                'color' => (string) ($tr['color'] ?? SseAtakLayersCatalog::colorFor($layerKey)),
                'dashed' => $kind === 'ghost',
                'case_id' => $tr['case_id'] ?? null,
                'points' => array_values(array_filter(array_map(static function ($p): ?array {
                    if (!is_array($p)) {
                        return null;
                    }
                    $x = $p['x'] ?? $p['pos_x'] ?? null;
                    $y = $p['y'] ?? $p['pos_y'] ?? null;
                    if (!is_numeric($x) || !is_numeric($y)) {
                        return null;
                    }

                    return ['pos_x' => (float) $x, 'pos_y' => (float) $y, 't' => $p['t'] ?? null];
                }, is_array($tr['points'] ?? null) ? $tr['points'] : []))),
            ];
            if (count($line['points']) < 2) {
                continue;
            }
            $layersOut[$layerKey]['polylines'][] = $line;
        }

        foreach ($history as $h) {
            $x = $h['pos_x'] ?? null;
            $y = $h['pos_y'] ?? null;
            // Fallback lat/lng uniquement si pas de coords terrain (carte monde).
            if (!is_numeric($x) || !is_numeric($y)) {
                continue;
            }
            $pt = $this->point(
                'hist-' . (int) ($h['id'] ?? 0),
                'history',
                'history',
                (string) ($h['event_type'] ?? 'event'),
                (string) ($h['summary'] ?? 'Événement'),
                (string) ($h['event_time'] ?? ''),
                SseAtakLayersCatalog::colorFor('history'),
                (float) $x,
                (float) $y,
                (int) ($h['case_id'] ?? 0),
                (string) ($h['case_ref'] ?? ''),
                (string) ($h['case_title'] ?? ''),
                ['author' => (string) ($h['author_label'] ?? '')]
            );
            $layersOut['history']['points'][] = $pt;
            $flat[] = $pt;
        }

        try {
            $pins = (new SseDigitalLabRepository())->listMapPins($tenantId);
        } catch (\Throwable) {
            $pins = [];
        }
        foreach ($pins as $pin) {
            $note = trim((string) ($pin['origin_label'] ?? ''));
            $grid = trim((string) ($pin['grid_reference'] ?? ''));
            if ($grid !== '') {
                $note = $note !== '' ? $note . ' · ' . $grid : $grid;
            }
            $pt = $this->point(
                'intel-' . (int) ($pin['id'] ?? 0),
                'intel',
                'intel',
                (string) ($pin['packet_type'] ?? 'coordinate'),
                (string) ($pin['title'] ?? 'Renseignement'),
                $note !== '' ? $note : (string) ($pin['body_text'] ?? ''),
                SseAtakLayersCatalog::colorFor('intel'),
                (float) $pin['pos_x'],
                (float) $pin['pos_y'],
                (int) ($pin['case_id'] ?? 0),
                (string) ($pin['case_ref'] ?? ''),
                (string) ($pin['case_title'] ?? ''),
                ['support' => (string) ($pin['support_label'] ?? '')]
            );
            $layersOut['intel']['points'][] = $pt;
            $flat[] = $pt;
        }

        $counts = [];
        foreach ($layersOut as $key => $layer) {
            $counts[$key] = count($layer['points']) + count($layer['polylines']);
        }

        return [
            'mapId' => $mapId,
            'count' => count($flat),
            'points' => $flat,
            'layers' => array_values($layersOut),
            'counts' => $counts,
            'catalog' => SseAtakLayersCatalog::LAYER_KINDS,
        ];
    }

    /**
     * @param array<string,mixed> $data
     * @return array{ok:bool,id?:int,message?:string,error?:string}
     */
    public function saveTrack(int $tenantId, array $data, string $author = '', ?int $userId = null): array
    {
        $data['author_label'] = $author !== '' ? $author : ($data['author_label'] ?? null);
        $data['created_by'] = $userId;
        $result = $this->layers->upsertTrack($tenantId, $data);
        if (!($result['ok'] ?? false)) {
            return $result;
        }

        return ['ok' => true, 'id' => $result['id'], 'message' => 'Tracé enregistré.'];
    }

    /**
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    private function point(
        string $id,
        string $layer,
        string $source,
        string $kind,
        string $label,
        string $note,
        string $color,
        float $posX,
        float $posY,
        int $caseId,
        string $caseRef,
        string $caseTitle,
        array $extra = []
    ): array {
        return array_merge([
            'id' => $id,
            'layer' => $layer,
            'source' => $source,
            'case_id' => $caseId,
            'case_ref' => $caseRef,
            'case_title' => $caseTitle,
            'kind' => $kind,
            'label' => $label,
            'note' => $note,
            'color' => $color,
            'pos_x' => $posX,
            'pos_y' => $posY,
        ], $extra);
    }
}
