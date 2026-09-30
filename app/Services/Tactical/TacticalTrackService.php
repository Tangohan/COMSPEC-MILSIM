<?php

declare(strict_types=1);

namespace App\Services\Tactical;

use App\Repositories\AtakDataRepository;
use App\Repositories\TacticalTrackRepository;

/**
 * Phase D — création / fusion de tracks à partir d’observations (jamais de vérité moteur ennemie).
 */
final class TacticalTrackService
{
    public function __construct(
        private TacticalTrackRepository $tracks,
        private ?AtakDataRepository $atak = null,
        private ?AtakActivityLogService $activityLog = null,
    ) {
        $this->activityLog ??= new AtakActivityLogService();
    }

    /**
     * @param array<string, mixed> $ev
     * @return array{ok:bool, track?:array<string,mixed>, observation?:array<string,mixed>, error?:string}
     */
    public function ingestObservation(int $tenantId, int $mapId, array $ev, ?string $actorCallSign = null): array
    {
        $kind = strtolower(trim((string) ($ev['t'] ?? $ev['type'] ?? $ev['kind'] ?? 'obs')));
        if (in_array($kind, ['obs', 'observation', 'recon'], true)) {
            $kind = isset($ev['tag']) || isset($ev['text']) ? 'recon' : 'obs';
        }

        $layer = 'observation';
        $affiliation = strtoupper(trim((string) ($ev['affiliation'] ?? 'UNKNOWN')));
        if (!in_array($affiliation, ['FRIEND', 'FRIENDLY', 'HOSTILE', 'NEUTRAL', 'UNKNOWN', 'ASSUMED_FRIEND', 'SUSPECTED_HOSTILE'], true)) {
            $affiliation = 'UNKNOWN';
        }
        if ($affiliation === 'FRIENDLY') {
            $affiliation = 'FRIEND';
        }

        $confidence = $this->resolveConfidence($ev, $kind);
        $x = $this->num($ev, ['x', 'pos_x']);
        $y = $this->num($ev, ['y', 'pos_y']);
        $bearing = $this->num($ev, ['bearing']);
        $actor = trim((string) ($ev['call_sign'] ?? $ev['cs'] ?? $ev['actor'] ?? $actorCallSign ?? ''));
        $label = trim((string) ($ev['label'] ?? $ev['text'] ?? $ev['tag'] ?? ''));
        $trackType = strtoupper(trim((string) ($ev['track_type'] ?? $ev['target_type'] ?? 'UNKNOWN')));
        if ($trackType === '') {
            $trackType = 'UNKNOWN';
        }

        // BDA : toujours candidat — jamais DESTROYED automatique depuis le moteur.
        $status = 'candidate';
        $source = 'recon';
        if ($kind === 'salute') {
            $source = 'salute';
            $affiliation = $affiliation === 'UNKNOWN' ? 'SUSPECTED_HOSTILE' : $affiliation;
            if ($label === '') {
                $label = 'Contact SALUTE';
            }
        } elseif ($kind === 'bda') {
            $source = 'bda';
            $status = 'candidate';
            $label = $label !== '' ? $label : 'BDA — candidat';
            // Interdit : forcer DESTROYED depuis un flag moteur.
            unset($ev['engine_destroyed'], $ev['getDammage'], $ev['damage_auto']);
            $assessment = strtoupper(trim((string) ($ev['assessment'] ?? $ev['result'] ?? 'UNKNOWN')));
            if (!in_array($assessment, ['DESTROYED', 'DAMAGED', 'UNKNOWN', 'NO_DAMAGE'], true)) {
                $assessment = 'UNKNOWN';
            }
            $ev['assessment'] = $assessment;
            $ev['requires_confirmation'] = true;
            $confidence = min($confidence, 0.55);
        } elseif ($kind === 'bda_confirm') {
            $source = 'bda';
            $status = 'confirmed';
            $layer = 'assessment';
            $confidence = max($confidence, 0.8);
            $label = $label !== '' ? $label : 'BDA — confirmé';
        } elseif ($kind === 'sigint') {
            $source = 'sigint';
            $affiliation = $affiliation === 'UNKNOWN' ? 'SUSPECTED_HOSTILE' : $affiliation;
            $label = $label !== '' ? $label : 'Émetteur radio';
            $trackType = 'RADIO';
        }

        $trackUid = trim((string) ($ev['track_id'] ?? $ev['track_uid'] ?? ''));
        if ($trackUid === '' && $kind === 'bda_confirm') {
            $trackUid = trim((string) ($ev['ref_track'] ?? ''));
        }
        if ($trackUid === '') {
            $seed = $kind . '|' . $actor . '|' . ($x !== null ? round($x / 50) : '') . '|' . ($y !== null ? round($y / 50) : '') . '|' . substr(md5((string) json_encode($ev)), 0, 6);
            $trackUid = 'TRK-' . strtoupper(substr(sha1($seed), 0, 8));
        }

        $track = $this->tracks->upsertTrack($tenantId, $mapId, [
            'track_uid' => $trackUid,
            'label' => $label !== '' ? $label : $trackUid,
            'type' => $trackType,
            'affiliation' => $affiliation,
            'layer' => $layer,
            'status' => $status,
            'confidence' => $confidence,
            'source' => $source,
            'pos_x' => $x,
            'pos_y' => $y,
            'call_sign_ref' => $actor,
            'meta' => [
                'kind' => $kind,
                'assessment' => $ev['assessment'] ?? null,
                'requires_confirmation' => !empty($ev['requires_confirmation']),
                'tag' => $ev['tag'] ?? null,
            ],
        ]);

        $obs = $this->tracks->addObservation($tenantId, $mapId, [
            'kind' => $kind,
            'layer' => $layer,
            'actor' => $actor,
            'track_id' => (int) ($track['id'] ?? 0),
            'pos_x' => $x,
            'pos_y' => $y,
            'bearing' => $bearing,
            'confidence' => $confidence,
            'payload' => $ev,
        ]);

        // SIGINT : si zones d’intersection existent, créer/mettre à jour un track OBSERVATION au point estimé.
        if ($kind === 'sigint' && $this->atak !== null) {
            try {
                $zones = $this->atak->getSigintZones($tenantId, $mapId, 30);
                foreach ($zones as $zone) {
                    if (($zone['kind'] ?? '') !== 'ellipse') {
                        continue;
                    }
                    $zx = (float) ($zone['pos_x'] ?? 0);
                    $zy = (float) ($zone['pos_y'] ?? 0);
                    $zUid = 'TRK-SIGINT-' . strtoupper(substr(sha1($tenantId . '|' . $mapId . '|' . round($zx) . '|' . round($zy)), 0, 8));
                    $this->tracks->upsertTrack($tenantId, $mapId, [
                        'track_uid' => $zUid,
                        'label' => 'Émetteur probable',
                        'type' => 'RADIO',
                        'affiliation' => 'SUSPECTED_HOSTILE',
                        'layer' => 'observation',
                        'status' => 'active',
                        'confidence' => min(0.7, 0.4 + 0.05 * (int) ($zone['reports'] ?? 2)),
                        'source' => 'sigint',
                        'pos_x' => $zx,
                        'pos_y' => $zy,
                        'meta' => [
                            'kind' => 'sigint_fix',
                            'radius' => $zone['radius'] ?? null,
                            'reports' => $zone['reports'] ?? null,
                        ],
                    ]);
                }
            } catch (\Throwable) {
            }
        }

        try {
            $this->activityLog->record(
                $tenantId,
                $mapId,
                AtakActivityLogService::TYPE_TACTICAL_REPORT,
                ($actor !== '' ? $actor . ' — ' : '') . ($label !== '' ? $label : strtoupper($kind)),
                $actor !== '' ? $actor : null,
                [
                    'source' => 'telemetry',
                    'track_uid' => $track['track_uid'] ?? $trackUid,
                    'layer' => $layer,
                    'kind' => $kind,
                ]
            );
        } catch (\Throwable) {
        }

        return ['ok' => true, 'track' => $track, 'observation' => $obs];
    }

    /**
     * @param array<string, mixed> $ev
     */
    private function resolveConfidence(array $ev, string $kind): float
    {
        if (isset($ev['confidence']) && is_numeric($ev['confidence'])) {
            return max(0.05, min(1.0, (float) $ev['confidence']));
        }
        $conf = strtolower(trim((string) ($ev['conf'] ?? $ev['confidence_label'] ?? '')));

        return match (true) {
            $conf === 'vu_direct', $conf === 'direct' => 0.75,
            $conf === 'rapporte', $conf === 'reported' => 0.45,
            $kind === 'salute' => 0.6,
            $kind === 'bda' => 0.45,
            $kind === 'bda_confirm' => 0.85,
            $kind === 'sigint' => 0.5,
            default => 0.55,
        };
    }

    /**
     * @param array<string, mixed> $ev
     * @param list<string> $keys
     */
    private function num(array $ev, array $keys): ?float
    {
        foreach ($keys as $k) {
            if (isset($ev[$k]) && is_numeric($ev[$k])) {
                return (float) $ev[$k];
            }
        }
        if (isset($ev['pos']) && is_array($ev['pos'])) {
            if ($keys[0] === 'x' || $keys[0] === 'pos_x') {
                return isset($ev['pos'][0]) && is_numeric($ev['pos'][0]) ? (float) $ev['pos'][0] : null;
            }
            if ($keys[0] === 'y' || $keys[0] === 'pos_y') {
                return isset($ev['pos'][1]) && is_numeric($ev['pos'][1]) ? (float) $ev['pos'][1] : null;
            }
        }

        return null;
    }
}
