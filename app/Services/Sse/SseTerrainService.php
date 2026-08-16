<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Database;
use App\Repositories\SsePersonRepository;
use App\Repositories\SseSiteRepository;

/**
 * LOT 3 — Terrain SSE : SEEK, qualité, zones, custody, photos, pont digital.
 */
final class SseTerrainService
{
    public const SEEK_STAGES = [
        'capture' => 'Capture',
        'query' => 'Interrogation identité',
        'match' => 'Appariement',
        'sign' => 'Signature / transmission',
        'done' => 'Terminé',
    ];

    public const IDENTITY_TIERS = [
        'UNKNOWN' => 'Non établi',
        'DECLARED' => 'Déclaré',
        'DOCUMENTARY' => 'Documentaire',
        'CONFIRMED' => 'Confirmé',
    ];

    public const ZONE_TYPES = [
        'ROOM' => 'Pièce',
        'CACHE' => 'Cache',
        'COLLECTION_POINT' => 'Point de collecte',
        'ENTRY' => 'Accès',
        'EXTERIOR' => 'Extérieur',
        'VEHICLE' => 'Véhicule',
    ];

    public const CUSTODY_STATES = [
        'OBSERVED' => 'Observé',
        'MARKED' => 'Marqué',
        'COLLECTED' => 'Collecté',
        'PACKAGED' => 'Conditionné',
        'SEALED' => 'Scellé',
        'TRANSFERRED' => 'Transmis',
        'EXPLOITED' => 'Exploité',
    ];

    private const CUSTODY_ORDER = [
        'OBSERVED', 'MARKED', 'COLLECTED', 'PACKAGED', 'SEALED', 'TRANSFERRED', 'EXPLOITED',
    ];

    public const PHOTO_TYPES = [
        'FACE' => 'Visage',
        'OVERVIEW' => 'Vue d’ensemble',
        'EVIDENCE' => 'Preuve',
        'CONTEXT' => 'Contexte',
        'DOCUMENT' => 'Document',
        'DIGITAL_SCREEN' => 'Écran numérique',
    ];

    public function __construct(
        private ?SsePersonRepository $persons = null,
        private ?SseSiteRepository $sites = null,
        private ?SseIntelFoundationService $intel = null,
        private ?Database $db = null,
    ) {
        $this->persons ??= new SsePersonRepository();
        $this->sites ??= new SseSiteRepository();
        $this->intel ??= new SseIntelFoundationService();
        $this->db ??= Database::getInstance();
    }

    public static function qualityLabel(?int $quality): string
    {
        if ($quality === null) {
            return 'Non mesurée';
        }
        if ($quality < 30) {
            return 'Insuffisante';
        }
        if ($quality < 55) {
            return 'Partielle';
        }
        if ($quality < 80) {
            return 'Bonne';
        }

        return 'Excellente';
    }

    public static function normalizeSeekStage(string $raw): string
    {
        $s = strtolower(trim($raw));
        if ($s === '') {
            return 'capture';
        }

        return array_key_exists($s, self::SEEK_STAGES) ? $s : 'capture';
    }

    public static function normalizeIdentityTier(string $raw): string
    {
        $t = strtoupper(trim($raw));
        if ($t === '') {
            return 'DECLARED';
        }

        return array_key_exists($t, self::IDENTITY_TIERS) ? $t : 'DECLARED';
    }

    public static function normalizeZoneType(string $raw): string
    {
        $z = strtoupper(trim($raw));
        if ($z === '') {
            return 'ROOM';
        }

        return array_key_exists($z, self::ZONE_TYPES) ? $z : 'ROOM';
    }

    public static function normalizeCustodyState(string $raw): string
    {
        $s = strtoupper(trim($raw));
        if ($s === '') {
            return 'OBSERVED';
        }

        return array_key_exists($s, self::CUSTODY_STATES) ? $s : 'OBSERVED';
    }

    public static function normalizePhotoType(string $raw): string
    {
        $p = strtoupper(trim($raw));
        if ($p === '') {
            return 'EVIDENCE';
        }

        return array_key_exists($p, self::PHOTO_TYPES) ? $p : 'EVIDENCE';
    }

    /**
     * Déduit le palier d’identité sans jamais forcer CONFIRMED depuis un scan seul.
     *
     * @param array<string, mixed> $person
     * @param list<array<string, mixed>> $samples
     */
    public function deriveIdentityTier(array $person, array $samples = []): string
    {
        $explicit = strtoupper(trim((string) ($person['identity_tier'] ?? '')));
        if ($explicit === 'CONFIRMED') {
            // Conservé s’il a déjà été posé par un analyste / workflow métier.
            return 'CONFIRMED';
        }

        $hasDoc = !empty($person['id_document_present'])
            || trim((string) ($person['id_document_number'] ?? '')) !== '';
        $hasBio = $samples !== [] || !empty($person['biometrics_simulated']);

        if ($hasDoc && $hasBio) {
            return 'DOCUMENTARY';
        }
        if ($hasDoc || $hasBio) {
            return 'DOCUMENTARY';
        }
        if (trim((string) ($person['last_name'] ?? '')) !== ''
            || trim((string) ($person['first_name'] ?? '')) !== '') {
            return 'DECLARED';
        }

        return 'UNKNOWN';
    }

    /**
     * @param list<array<string, mixed>> $samples
     */
    public function averageAcquisitionQuality(array $samples, ?int $photoQuality = null): ?int
    {
        $scores = [];
        foreach ($samples as $s) {
            if (isset($s['quality']) && $s['quality'] !== null && $s['quality'] !== '') {
                $scores[] = max(0, min(100, (int) $s['quality']));
            }
        }
        if ($photoQuality !== null) {
            $scores[] = max(0, min(100, $photoQuality));
        }
        if ($scores === []) {
            return null;
        }

        return (int) round(array_sum($scores) / count($scores));
    }

    /**
     * Enrichit une fiche personne pour l’UI / API terrain.
     *
     * @return array<string, mixed>
     */
    public function personTerrainDossier(int $tenantId, array $person): array
    {
        $id = (int) ($person['id'] ?? 0);
        $samples = $id > 0 ? $this->persons->listBiometricSamples($id, $tenantId) : [];
        $photos = $id > 0 ? $this->persons->listPhotos($id, $tenantId) : [];

        $primaryQuality = null;
        foreach ($photos as $ph) {
            if (($ph['angle'] ?? '') === 'face' || ($ph['photo_type'] ?? '') === 'FACE') {
                $primaryQuality = isset($ph['quality']) ? (int) $ph['quality'] : null;
                break;
            }
        }

        $tier = $this->deriveIdentityTier($person, $samples);
        $avg = $this->averageAcquisitionQuality($samples, $primaryQuality);
        $stage = self::normalizeSeekStage((string) ($person['seek_stage'] ?? 'capture'));

        $sampleCards = [];
        foreach ($samples as $s) {
            $q = isset($s['quality']) ? (int) $s['quality'] : null;
            $sampleCards[] = array_merge($s, [
                'quality_label' => (string) ($s['quality_label'] ?? self::qualityLabel($q)),
                'laterality_label' => $this->lateralityLabel($s['laterality'] ?? null),
            ]);
        }

        return [
            'subject_id' => (string) ($person['subject_id'] ?? ''),
            'seek_stage' => $stage,
            'seek_stage_label' => self::SEEK_STAGES[$stage] ?? $stage,
            'seek_stages' => self::SEEK_STAGES,
            'identity_tier' => $tier,
            'identity_tier_label' => self::IDENTITY_TIERS[$tier] ?? $tier,
            'acquisition_quality_avg' => $avg,
            'acquisition_quality_label' => self::qualityLabel($avg),
            'biometric_samples' => $sampleCards,
            'photos' => $photos,
        ];
    }

    public function applyPersonIngest(int $tenantId, int $personId, array $body): void
    {
        $person = $this->persons->findById($personId, $tenantId);
        if ($person === null) {
            return;
        }

        $samples = $this->persons->listBiometricSamples($personId, $tenantId);
        $tier = !empty($body['identity_tier'])
            ? self::normalizeIdentityTier((string) $body['identity_tier'])
            : $this->deriveIdentityTier(array_merge($person, $body), $samples);

        // Un ingest terrain ne pose jamais CONFIRMED tout seul.
        if ($tier === 'CONFIRMED' && self::normalizeIdentityTier((string) ($person['identity_tier'] ?? '')) !== 'CONFIRMED') {
            $tier = 'DOCUMENTARY';
        }

        $subjectId = trim((string) ($body['subject_id'] ?? $person['subject_id'] ?? ''));
        if ($subjectId === '') {
            $subjectId = $this->allocateSubjectId($tenantId, $personId);
        }

        $stage = self::normalizeSeekStage((string) (
            $body['seek_stage']
            ?? $this->inferSeekStage($body, $person, $samples)
        ));

        $avg = $this->averageAcquisitionQuality($samples);

        $this->persons->updateTerrainFields($personId, $tenantId, [
            'subject_id' => $subjectId,
            'seek_stage' => $stage,
            'identity_tier' => $tier,
            'acquisition_quality_avg' => $avg,
        ]);

        $fresh = $this->persons->findById($personId, $tenantId);
        if (is_array($fresh)) {
            $fresh['identity_tier'] = $tier;
            $this->intel->syncPerson($tenantId, $fresh);
        }
    }

    /**
     * @param list<array<string, mixed>> $samples
     */
    private function inferSeekStage(array $body, array $person, array $samples): string
    {
        if (!empty($body['signature']) || !empty($person['signed_at'])) {
            return 'done';
        }
        if ($samples !== [] || !empty($person['biometrics_simulated'])) {
            return 'match';
        }
        if (!empty($body['identity_query']) || !empty($person['identity_query_json'])) {
            return 'query';
        }

        return 'capture';
    }

    private function allocateSubjectId(int $tenantId, int $personId): string
    {
        return sprintf('SUB-%05d-%04d', $tenantId % 100000, $personId % 10000);
    }

    public function advanceSeekStage(int $tenantId, int $personId, string $stage, string $actor = ''): bool
    {
        $person = $this->persons->findById($personId, $tenantId);
        if ($person === null) {
            return false;
        }
        $next = self::normalizeSeekStage($stage);
        $ok = $this->persons->updateTerrainFields($personId, $tenantId, [
            'seek_stage' => $next,
        ]);
        if ($ok) {
            $this->persons->addCustodyEventPublic(
                $tenantId,
                $personId,
                null,
                'seek_stage',
                'Étape SEEK : ' . (self::SEEK_STAGES[$next] ?? $next),
                $actor
            );
        }

        return $ok;
    }

    /**
     * Recalcule le % d’exploitation d’un site (pièces + zones pondérées).
     */
    public function refreshSiteExploitation(int $tenantId, int $siteId): int
    {
        $site = $this->sites->findById($siteId, $tenantId);
        if ($site === null) {
            return 0;
        }
        $rooms = is_array($site['rooms'] ?? null) ? $site['rooms'] : [];
        if ($rooms === []) {
            $this->sites->setExploitationPct($siteId, $tenantId, 0);

            return 0;
        }

        $weightSum = 0.0;
        $doneSum = 0.0;
        foreach ($rooms as $room) {
            $zone = self::normalizeZoneType((string) ($room['zone_type'] ?? 'ROOM'));
            $w = match ($zone) {
                'CACHE' => 1.4,
                'COLLECTION_POINT' => 1.2,
                'VEHICLE' => 1.3,
                'ENTRY' => 0.8,
                'EXTERIOR' => 0.7,
                default => 1.0,
            };
            $weightSum += $w;
            $roomPct = isset($room['exploitation_pct'])
                ? max(0, min(100, (int) $room['exploitation_pct']))
                : (!empty($room['checked']) ? 100 : 0);
            $doneSum += $w * ($roomPct / 100);
        }

        $pct = $weightSum > 0 ? (int) round(($doneSum / $weightSum) * 100) : 0;
        $this->sites->setExploitationPct($siteId, $tenantId, $pct);

        return $pct;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function advanceSeizureCustody(int $tenantId, int $seizureId, array $data): ?array
    {
        $row = $this->sites->findSeizure($seizureId, $tenantId);
        if ($row === null) {
            return null;
        }

        $current = self::normalizeCustodyState((string) ($row['custody_state'] ?? 'OBSERVED'));
        $requested = self::normalizeCustodyState((string) ($data['custody_state'] ?? $current));

        $curIdx = array_search($current, self::CUSTODY_ORDER, true);
        $reqIdx = array_search($requested, self::CUSTODY_ORDER, true);
        if ($curIdx === false) {
            $curIdx = 0;
        }
        if ($reqIdx === false || $reqIdx < $curIdx) {
            $requested = $current;
        }

        $packaging = trim((string) ($data['packaging'] ?? $row['packaging'] ?? ''));
        $seal = trim((string) ($data['seal_code'] ?? $row['seal_code'] ?? ''));
        $actor = trim((string) ($data['actor_callsign'] ?? ''));

        $this->sites->updateSeizureCustody($seizureId, $tenantId, [
            'custody_state' => $requested,
            'packaging' => $packaging !== '' ? $packaging : null,
            'seal_code' => $seal !== '' ? $seal : null,
            'sealed_at' => $requested === 'SEALED' || $requested === 'TRANSFERRED' || $requested === 'EXPLOITED'
                ? ($row['sealed_at'] ?? date('Y-m-d H:i:s'))
                : ($row['sealed_at'] ?? null),
            'actor_callsign' => $actor !== '' ? $actor : ($row['actor_callsign'] ?? null),
            'exploited_at' => $requested === 'EXPLOITED'
                ? date('Y-m-d H:i:s')
                : ($row['exploited_at'] ?? null),
        ]);

        $siteId = (int) ($row['site_id'] ?? 0);
        if ($siteId > 0) {
            $this->sites->addCustodyEventPublic(
                $tenantId,
                $siteId,
                'custody_' . strtolower($requested),
                sprintf(
                    'Matériel « %s » → %s',
                    (string) ($row['label'] ?? 'objet'),
                    self::CUSTODY_STATES[$requested] ?? $requested
                ),
                $actor,
                $seizureId
            );
        }

        $this->intel->recordEvent([
            'tenant_id' => $tenantId,
            'context_id' => 1,
            'event_type' => 'CUSTODY_UPDATE',
            'source_system' => 'MANUAL',
            'summary' => sprintf(
                'Chaîne de possession : %s — %s',
                (string) ($row['label'] ?? 'matériel'),
                self::CUSTODY_STATES[$requested] ?? $requested
            ),
            'identity_tier' => 'DECLARED',
            'source_reliability' => 'C',
            'info_credibility' => 3,
            'author_label' => $actor !== '' ? $actor : 'Cellule SSE',
            'payload' => [
                'seizure_id' => $seizureId,
                'custody_state' => $requested,
                'site_id' => $siteId > 0 ? $siteId : null,
            ],
            'idempotency_key' => sprintf('custody:%d:%s:%s', $seizureId, $requested, date('YmdHi')),
        ]);

        return $this->sites->findSeizure($seizureId, $tenantId);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function recordFieldPhoto(int $tenantId, array $data): int
    {
        $quality = isset($data['quality']) ? max(0, min(100, (int) $data['quality'])) : null;
        $photoType = self::normalizePhotoType((string) ($data['photo_type'] ?? 'EVIDENCE'));
        $meta = $data['metadata'] ?? $data['metadata_json'] ?? null;
        if (is_array($meta)) {
            $metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE);
        } elseif (is_string($meta) && $meta !== '') {
            $metaJson = $meta;
        } else {
            $metaJson = null;
        }

        $id = (int) $this->db->insert(
            'INSERT INTO sse_field_photos (
                tenant_id, context_id, case_id, person_id, site_id, seizure_id,
                photo_type, image_path, quality, quality_label, heading,
                pos_x, pos_y, pos_z, grid_reference, target_ref, caption,
                author_callsign, metadata_json
            ) VALUES (
                :tenant_id, :context_id, :case_id, :person_id, :site_id, :seizure_id,
                :photo_type, :image_path, :quality, :quality_label, :heading,
                :pos_x, :pos_y, :pos_z, :grid_reference, :target_ref, :caption,
                :author_callsign, :metadata_json
            )',
            [
                'tenant_id' => $tenantId,
                'context_id' => (int) ($data['context_id'] ?? 1),
                'case_id' => !empty($data['case_id']) ? (int) $data['case_id'] : null,
                'person_id' => !empty($data['person_id']) ? (int) $data['person_id'] : null,
                'site_id' => !empty($data['site_id']) ? (int) $data['site_id'] : null,
                'seizure_id' => !empty($data['seizure_id']) ? (int) $data['seizure_id'] : null,
                'photo_type' => $photoType,
                'image_path' => ($data['image_path'] ?? null) !== null && (string) $data['image_path'] !== ''
                    ? (string) $data['image_path'] : null,
                'quality' => $quality,
                'quality_label' => self::qualityLabel($quality),
                'heading' => isset($data['heading']) ? (int) $data['heading'] : null,
                'pos_x' => $this->floatOrNull($data['pos_x'] ?? null),
                'pos_y' => $this->floatOrNull($data['pos_y'] ?? null),
                'pos_z' => $this->floatOrNull($data['pos_z'] ?? null),
                'grid_reference' => $this->nullIfEmpty($data['grid_reference'] ?? null),
                'target_ref' => $this->nullIfEmpty($data['target_ref'] ?? null),
                'caption' => $this->nullIfEmpty($data['caption'] ?? null),
                'author_callsign' => $this->nullIfEmpty($data['author_callsign'] ?? $data['author'] ?? null),
                'metadata_json' => $metaJson,
            ]
        );

        $this->intel->recordEvent([
            'tenant_id' => $tenantId,
            'context_id' => (int) ($data['context_id'] ?? 1),
            'event_type' => 'PHOTOGRAPHED',
            'source_system' => (string) ($data['source_system'] ?? 'ARMA_SSE'),
            'summary' => sprintf(
                'Photo terrain (%s)%s',
                self::PHOTO_TYPES[$photoType] ?? $photoType,
                $quality !== null ? sprintf(' — qualité %d %%', $quality) : ''
            ),
            'identity_tier' => 'DECLARED',
            'source_reliability' => 'C',
            'info_credibility' => 3,
            'author_label' => (string) ($data['author_callsign'] ?? $data['author'] ?? 'Terrain'),
            'payload' => [
                'photo_id' => $id,
                'photo_type' => $photoType,
                'quality' => $quality,
                'target_ref' => $data['target_ref'] ?? null,
                'person_id' => $data['person_id'] ?? null,
                'site_id' => $data['site_id'] ?? null,
            ],
            'idempotency_key' => sprintf(
                'photo:%d:%s:%s',
                $tenantId,
                (string) ($data['target_ref'] ?? $id),
                (string) ($data['idempotency'] ?? $id)
            ),
        ]);

        return $id;
    }

    /**
     * Pont lab numérique → timeline intel (findings à revoir).
     *
     * @param array<string, mixed> $finding
     * @param array<string, mixed> $device
     */
    public function onDigitalFindingCreated(int $tenantId, array $finding, array $device = []): void
    {
        $title = trim((string) ($finding['title'] ?? 'Constat numérique'));
        $this->intel->recordEvent([
            'tenant_id' => $tenantId,
            'context_id' => 1,
            'case_id' => !empty($finding['case_id']) ? (int) $finding['case_id'] : (!empty($device['case_id']) ? (int) $device['case_id'] : null),
            'event_type' => 'DIGITAL_FINDING',
            'source_system' => 'DIGITAL_LAB',
            'summary' => $title,
            'identity_tier' => 'DOCUMENTARY',
            'source_reliability' => 'B',
            'info_credibility' => 2,
            'author_label' => 'Laboratoire numérique',
            'payload' => [
                'finding_id' => $finding['id'] ?? null,
                'finding_type' => $finding['finding_type'] ?? null,
                'device_id' => $finding['device_id'] ?? $device['id'] ?? null,
                'device_ref' => $device['reference_code'] ?? null,
                'score_pct' => $finding['score_pct'] ?? null,
                'status' => $finding['status'] ?? null,
            ],
            'idempotency_key' => sprintf(
                'digital-finding:%d:%s',
                $tenantId,
                (string) ($finding['id'] ?? md5($title . ($finding['detail'] ?? '')))
            ),
        ]);
    }

    private function lateralityLabel(mixed $raw): string
    {
        $v = strtoupper(trim((string) $raw));

        return match ($v) {
            'L', 'LEFT', 'GAUCHE' => 'Gauche',
            'R', 'RIGHT', 'DROITE' => 'Droite',
            'BOTH', 'BINOCULAR' => 'Les deux',
            default => $v !== '' ? $v : '—',
        };
    }

    private function floatOrNull(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }

        return (float) $v;
    }

    private function nullIfEmpty(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }
}
