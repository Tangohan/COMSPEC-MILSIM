<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SilentSchemaMigration;
use App\Support\SqlText;
use PDO;

/**
 * Fiches SSE (Sensitive Site Exploitation) — personnes identifiées sur le terrain.
 */
final class SsePersonRepository
{
    public const STATUS_CIVIL = 'civil';
    public const STATUS_COMBATTANT = 'combattant';
    public const STATUS_DETENU = 'detenu';
    public const STATUS_PRIORITAIRE = 'prioritaire';

    /** @var array<string, string> */
    public const STATUS_LABELS = [
        self::STATUS_CIVIL => 'Civil',
        self::STATUS_COMBATTANT => 'Combattant',
        self::STATUS_DETENU => 'Détenu',
        self::STATUS_PRIORITAIRE => 'Personne prioritaire',
    ];

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->ensureSchema();
    }

    private function ensureSchema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        SilentSchemaMigration::run(base_path('bootstrap/atak_sse_persons_migration.php'));
        $done = true;
    }

    public static function normalizeStatus(string $raw): string
    {
        $s = strtolower(trim($raw));
        $map = [
            'civilian' => self::STATUS_CIVIL,
            'civil' => self::STATUS_CIVIL,
            'combatant' => self::STATUS_COMBATTANT,
            'combattant' => self::STATUS_COMBATTANT,
            'detainee' => self::STATUS_DETENU,
            'detenu' => self::STATUS_DETENU,
            'détenu' => self::STATUS_DETENU,
            'hvt' => self::STATUS_PRIORITAIRE,
            'prioritaire' => self::STATUS_PRIORITAIRE,
            'personne_prioritaire' => self::STATUS_PRIORITAIRE,
        ];

        return $map[$s] ?? (isset(self::STATUS_LABELS[$s]) ? $s : self::STATUS_CIVIL);
    }

    public static function statusLabel(string $status): string
    {
        $n = self::normalizeStatus($status);

        return self::STATUS_LABELS[$n] ?? 'Civil';
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $weapons = $data['weapons'] ?? $data['weapons_json'] ?? [];
        $equipment = $data['equipment'] ?? $data['equipment_json'] ?? [];
        if (!is_array($weapons)) {
            $weapons = [];
        }
        if (!is_array($equipment)) {
            $equipment = [];
        }

        // Procès-verbal ATAK : signature transmise par le terminal SEEK.
        $signature = $data['signature'] ?? null;
        if (!is_array($signature)) {
            $signature = [];
        }

        $sql = 'INSERT INTO sse_persons (
            tenant_id, context_id, status, last_name, first_name, alias,
            sex_apparent, age_estimated, birth_date, birth_place, nationality, language_spoken,
            id_document_present, id_document_type, id_document_number,
            distinguishing_marks, affiliation, circumstances, statements, confidence_level,
            weapons_json, equipment_json, biometrics_simulated, consent_recorded,
            capture_pos_x, capture_pos_y, capture_pos_z, grid_reference, location_description,
            submitter_user_id, submitter_callsign, submitter_steam_id, target_unit_netid,
            medical_context_json, signed_by_callsign, signed_terminal_uid, signed_atak_id, signed_at,
            identity_query_json
        ) VALUES (
            :tenant_id, :context_id, :status, :last_name, :first_name, :alias,
            :sex_apparent, :age_estimated, :birth_date, :birth_place, :nationality, :language_spoken,
            :id_document_present, :id_document_type, :id_document_number,
            :distinguishing_marks, :affiliation, :circumstances, :statements, :confidence_level,
            :weapons_json, :equipment_json, :biometrics_simulated, :consent_recorded,
            :capture_pos_x, :capture_pos_y, :capture_pos_z, :grid_reference, :location_description,
            :submitter_user_id, :submitter_callsign, :submitter_steam_id, :target_unit_netid,
            :medical_context_json, :signed_by_callsign, :signed_terminal_uid, :signed_atak_id, :signed_at,
            :identity_query_json
        )';

        $id = (int) $this->db->insert($sql, [
            'tenant_id' => (int) ($data['tenant_id'] ?? 0),
            'context_id' => (int) ($data['context_id'] ?? $data['mapId'] ?? $data['map_id'] ?? 1),
            'status' => self::normalizeStatus((string) ($data['status'] ?? self::STATUS_CIVIL)),
            'last_name' => trim((string) ($data['last_name'] ?? '')),
            'first_name' => trim((string) ($data['first_name'] ?? '')),
            'alias' => $this->nullIfEmpty($data['alias'] ?? null),
            'sex_apparent' => $this->nullIfEmpty($data['sex_apparent'] ?? null),
            'age_estimated' => isset($data['age_estimated']) && $data['age_estimated'] !== '' && $data['age_estimated'] !== null
                ? (int) $data['age_estimated'] : null,
            'birth_date' => $this->nullIfEmpty($data['birth_date'] ?? null),
            'birth_place' => $this->nullIfEmpty($data['birth_place'] ?? null),
            'nationality' => $this->nullIfEmpty($data['nationality'] ?? null),
            'language_spoken' => $this->nullIfEmpty($data['language_spoken'] ?? null),
            'id_document_present' => !empty($data['id_document_present']) ? 1 : 0,
            'id_document_type' => $this->nullIfEmpty($data['id_document_type'] ?? null),
            'id_document_number' => $this->nullIfEmpty($data['id_document_number'] ?? null),
            'distinguishing_marks' => $this->nullIfEmpty($data['distinguishing_marks'] ?? null),
            'affiliation' => $this->nullIfEmpty($data['affiliation'] ?? null),
            'circumstances' => $this->nullIfEmpty($data['circumstances'] ?? null),
            'statements' => $this->nullIfEmpty($data['statements'] ?? null),
            'confidence_level' => $this->nullIfEmpty($data['confidence_level'] ?? null),
            'weapons_json' => json_encode($weapons, JSON_UNESCAPED_UNICODE),
            'equipment_json' => json_encode($equipment, JSON_UNESCAPED_UNICODE),
            'biometrics_simulated' => !empty($data['biometrics_simulated']) ? 1 : 0,
            'consent_recorded' => !empty($data['consent_recorded']) ? 1 : 0,
            'capture_pos_x' => $this->floatOrNull($data['pos_x'] ?? $data['capture_pos_x'] ?? null),
            'capture_pos_y' => $this->floatOrNull($data['pos_y'] ?? $data['capture_pos_y'] ?? null),
            'capture_pos_z' => $this->floatOrNull($data['pos_z'] ?? $data['capture_pos_z'] ?? null),
            'grid_reference' => $this->nullIfEmpty($data['grid_reference'] ?? null),
            'location_description' => $this->nullIfEmpty($data['location_description'] ?? null),
            'submitter_user_id' => isset($data['submitter_user_id']) ? (int) $data['submitter_user_id'] : null,
            'submitter_callsign' => $this->nullIfEmpty($data['submitter_callsign'] ?? null),
            'submitter_steam_id' => $this->nullIfEmpty($data['submitter_steam_id'] ?? null),
            'target_unit_netid' => $this->nullIfEmpty($data['target_unit_netid'] ?? null),
            'medical_context_json' => is_array($data['medical_context'] ?? null) && $data['medical_context'] !== []
                ? json_encode($data['medical_context'], JSON_UNESCAPED_UNICODE)
                : null,
            'signed_by_callsign' => $this->nullIfEmpty($signature['callsign'] ?? null),
            'signed_terminal_uid' => $this->nullIfEmpty($signature['terminal_uid'] ?? null),
            'signed_atak_id' => $this->nullIfEmpty($signature['atak_id'] ?? null),
            'signed_at' => ($signature !== [] ? date('Y-m-d H:i:s') : null),
            'identity_query_json' => is_array($data['identity_query'] ?? null) && $data['identity_query'] !== []
                ? json_encode($data['identity_query'], JSON_UNESCAPED_UNICODE)
                : null,
        ]);

        $this->addCustodyEvent((int) ($data['tenant_id'] ?? 0), $id, null, 'capture', 'Personne enregistrée', (string) ($data['submitter_callsign'] ?? ''));

        return $id;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id, ?int $tenantId = null): ?array
    {
        $sql = 'SELECT * FROM sse_persons WHERE id = :id';
        $params = ['id' => $id];
        if ($tenantId !== null) {
            $sql .= ' AND tenant_id = :tenant_id';
            $params['tenant_id'] = $tenantId;
        }
        $row = $this->db->fetchOne($sql, $params);
        if (!$row) {
            return null;
        }

        return $this->hydrate($row, true);
    }

    /**
     * @param list<int> $ids
     * @return list<array<string, mixed>>
     */
    public function listByIds(int $tenantId, array $ids): array
    {
        $clean = [];
        foreach ($ids as $raw) {
            $id = (int) $raw;
            if ($id > 0) {
                $clean[$id] = $id;
            }
        }
        $clean = array_values($clean);
        if ($tenantId < 1 || $clean === []) {
            return [];
        }
        $in = implode(',', $clean);
        try {
            $rows = $this->db->fetchAll(
                "SELECT * FROM sse_persons WHERE tenant_id = :t AND id IN ({$in}) ORDER BY last_name ASC, first_name ASC, id ASC",
                ['t' => $tenantId]
            );
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->hydrate($row, false);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function listForContext(int $tenantId, int $contextId, array $filters = []): array
    {
        $where = ['p.tenant_id = :tenant_id', 'p.context_id = :context_id'];
        $params = ['tenant_id' => $tenantId, 'context_id' => $contextId];

        if (!empty($filters['status'])) {
            $where[] = 'p.status = :status';
            $params['status'] = self::normalizeStatus((string) $filters['status']);
        }
        if (!empty($filters['since_id'])) {
            $where[] = 'p.id > :since_id';
            $params['since_id'] = (int) $filters['since_id'];
        }

        $limit = max(1, min(200, (int) ($filters['limit'] ?? 100)));
        $offset = max(0, (int) ($filters['offset'] ?? 0));

        $sql = 'SELECT p.* FROM sse_persons p WHERE ' . implode(' AND ', $where)
            . ' ORDER BY p.id DESC LIMIT ' . $limit . ' OFFSET ' . $offset;

        $rows = $this->db->fetchAll($sql, $params);
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->hydrate($row, false);
        }

        return $out;
    }

    /**
     * Recherche textuelle sur le tenant (tous contextes).
     *
     * @return list<array<string, mixed>>
     */
    public function searchForTenant(int $tenantId, string $q, int $limit = 20): array
    {
        $q = trim($q);
        if ($q === '' || mb_strlen($q) < 2) {
            return [];
        }
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
        $limit = max(1, min(50, $limit));
        try {
            $rows = $this->db->fetchAll(
                'SELECT p.* FROM sse_persons p
                 WHERE p.tenant_id = :t
                   AND (
                     p.last_name LIKE :q1 OR p.first_name LIKE :q2 OR p.alias LIKE :q3
                     OR CAST(p.id AS CHAR) LIKE :q4
                   )
                 ORDER BY p.id DESC
                 LIMIT ' . $limit,
                [
                    't' => $tenantId,
                    'q1' => $like,
                    'q2' => $like,
                    'q3' => $like,
                    'q4' => $like,
                ]
            );
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->hydrate($row, false);
        }

        return $out;
    }

    public function tenantHasAnyPerson(int $tenantId): bool
    {
        try {
            $st = Database::getPdo()->prepare('SELECT 1 FROM sse_persons WHERE tenant_id = ? LIMIT 1');
            $st->execute([$tenantId]);

            return (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    /**
     * @param array<string, mixed> $fields
     */
    public function updateTerrainFields(int $personId, int $tenantId, array $fields): bool
    {
        $sets = [];
        $params = ['id' => $personId, 't' => $tenantId];
        foreach (['subject_id', 'seek_stage', 'identity_tier'] as $col) {
            if (array_key_exists($col, $fields)) {
                $sets[] = "{$col} = :{$col}";
                $params[$col] = $fields[$col] !== null && $fields[$col] !== ''
                    ? (string) $fields[$col] : null;
            }
        }
        if (array_key_exists('acquisition_quality_avg', $fields)) {
            $sets[] = 'acquisition_quality_avg = :acquisition_quality_avg';
            $params['acquisition_quality_avg'] = $fields['acquisition_quality_avg'] !== null
                ? max(0, min(100, (int) $fields['acquisition_quality_avg'])) : null;
        }
        if ($sets === []) {
            return false;
        }
        try {
            return $this->db->execute(
                'UPDATE sse_persons SET ' . implode(', ', $sets)
                . ' WHERE id = :id AND tenant_id = :t',
                $params
            ) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    public function addCustodyEventPublic(
        int $tenantId,
        ?int $personId,
        ?int $photoId,
        string $type,
        string $label,
        string $actor
    ): void {
        $this->addCustodyEvent($tenantId, $personId, $photoId, $type, $label, $actor);
    }

    public function addPhoto(int $personId, int $tenantId, array $data): int
    {
        $meta = $data['metadata'] ?? $data['metadata_json'] ?? null;
        $metaJson = null;
        if (is_array($meta)) {
            $metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE);
        } elseif (is_string($meta) && $meta !== '') {
            $metaJson = $meta;
        }
        $quality = isset($data['quality']) ? max(0, min(100, (int) $data['quality'])) : null;
        $photoType = strtoupper(trim((string) ($data['photo_type'] ?? 'FACE')));
        if ($photoType === '') {
            $photoType = 'FACE';
        }

        try {
            $photoId = (int) $this->db->insert(
                'INSERT INTO sse_person_photos (
                    person_id, tenant_id, image_path, angle, photo_type, quality, heading,
                    case_id, target_ref, metadata_json, caption, author_callsign, pos_x, pos_y, pos_z
                ) VALUES (
                    :person_id, :tenant_id, :image_path, :angle, :photo_type, :quality, :heading,
                    :case_id, :target_ref, :metadata_json, :caption, :author_callsign, :pos_x, :pos_y, :pos_z
                )',
                [
                    'person_id' => $personId,
                    'tenant_id' => $tenantId,
                    'image_path' => (string) ($data['image_path'] ?? ''),
                    'angle' => $this->normalizeAngle((string) ($data['angle'] ?? 'face')),
                    'photo_type' => $photoType,
                    'quality' => $quality,
                    'heading' => isset($data['heading']) ? (int) $data['heading'] : null,
                    'case_id' => !empty($data['case_id']) ? (int) $data['case_id'] : null,
                    'target_ref' => $this->nullIfEmpty($data['target_ref'] ?? null),
                    'metadata_json' => $metaJson,
                    'caption' => $this->nullIfEmpty($data['caption'] ?? null),
                    'author_callsign' => $this->nullIfEmpty($data['author_callsign'] ?? $data['author'] ?? null),
                    'pos_x' => $this->floatOrNull($data['pos_x'] ?? null),
                    'pos_y' => $this->floatOrNull($data['pos_y'] ?? null),
                    'pos_z' => $this->floatOrNull($data['pos_z'] ?? null),
                ]
            );
        } catch (\Throwable) {
            $photoId = (int) $this->db->insert(
                'INSERT INTO sse_person_photos (
                    person_id, tenant_id, image_path, angle, caption, author_callsign, pos_x, pos_y, pos_z
                ) VALUES (
                    :person_id, :tenant_id, :image_path, :angle, :caption, :author_callsign, :pos_x, :pos_y, :pos_z
                )',
                [
                    'person_id' => $personId,
                    'tenant_id' => $tenantId,
                    'image_path' => (string) ($data['image_path'] ?? ''),
                    'angle' => $this->normalizeAngle((string) ($data['angle'] ?? 'face')),
                    'caption' => $this->nullIfEmpty($data['caption'] ?? null),
                    'author_callsign' => $this->nullIfEmpty($data['author_callsign'] ?? $data['author'] ?? null),
                    'pos_x' => $this->floatOrNull($data['pos_x'] ?? null),
                    'pos_y' => $this->floatOrNull($data['pos_y'] ?? null),
                    'pos_z' => $this->floatOrNull($data['pos_z'] ?? null),
                ]
            );
        }

        $person = $this->db->fetchOne('SELECT primary_photo_id FROM sse_persons WHERE id = :id AND tenant_id = :t', [
            'id' => $personId,
            't' => $tenantId,
        ]);
        if ($person && empty($person['primary_photo_id'])) {
            $this->db->execute(
                'UPDATE sse_persons SET primary_photo_id = :pid WHERE id = :id AND tenant_id = :t',
                ['pid' => $photoId, 'id' => $personId, 't' => $tenantId]
            );
        }

        $this->addCustodyEvent(
            $tenantId,
            $personId,
            $photoId,
            'photo',
            'Photo du visage jointe',
            (string) ($data['author_callsign'] ?? $data['author'] ?? '')
        );

        return $photoId;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPhotos(int $personId, int $tenantId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM sse_person_photos WHERE person_id = :pid AND tenant_id = :t ORDER BY id ASC',
            ['pid' => $personId, 't' => $tenantId]
        );
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->hydratePhoto($row);
        }

        return $out;
    }

    public function markBiometricsSimulated(int $personId, int $tenantId, string $kind, string $actorCallsign): bool
    {
        if ($personId < 1) {
            return false;
        }
        $person = $this->findById($personId, $tenantId);
        if ($person === null) {
            return false;
        }
        $already = !empty($person['biometrics_simulated']);
        // PDO/MySQL : rowCount vaut 0 si la colonne est déjà à 1 — ce n’est pas « introuvable ».
        $this->db->execute(
            'UPDATE sse_persons SET biometrics_simulated = 1 WHERE id = :id AND tenant_id = :t',
            ['id' => $personId, 't' => $tenantId]
        );
        if ($already) {
            return true;
        }
        $label = match ($kind) {
            'iris' => 'Simulation biométrique (iris) effectuée',
            'adn' => 'Simulation biométrique (ADN) effectuée',
            default => 'Simulation biométrique (empreintes) effectuée',
        };
        $this->addCustodyEvent($tenantId, $personId, null, 'biometrie_sim', $label, $actorCallsign);

        return true;
    }

    /**
     * Chaîne de possession pour plusieurs fiches, en une requête.
     * Les événements sont écrits depuis la 1.4.0 (capture, photo, biométrie) mais
     * n'étaient affichés nulle part.
     *
     * @param list<int> $personIds
     * @return array<int, list<array{type: string, type_label: string, label: string, actor: ?string, created_at: ?string}>>
     */
    public function custodyEventsForPersons(array $personIds, int $tenantId): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $personIds),
            static fn (int $i): bool => $i > 0
        )));
        if ($ids === []) {
            return [];
        }
        $in = implode(',', $ids);

        try {
            $rows = $this->db->fetchAll(
                "SELECT person_id, event_type, label, actor_callsign, created_at
                 FROM sse_custody_events
                 WHERE tenant_id = :t AND person_id IN ({$in})
                 ORDER BY id ASC",
                ['t' => $tenantId]
            );
        } catch (\Throwable) {
            return [];
        }

        $labels = [
            'capture' => 'Prise en compte',
            'biometrie_sim' => 'Relevé biométrique',
            'photo' => 'Photographie',
            'transfert' => 'Transfert de garde',
            'liberation' => 'Remise en liberté',
        ];

        $out = [];
        foreach ($rows as $row) {
            $pid = (int) ($row['person_id'] ?? 0);
            $type = (string) ($row['event_type'] ?? 'capture');
            $out[$pid][] = [
                'type' => $type,
                'type_label' => $labels[$type] ?? 'Événement',
                'label' => (string) ($row['label'] ?? ''),
                'actor' => $row['actor_callsign'] ?? null,
                'created_at' => $row['created_at'] ?? null,
            ];
        }

        return $out;
    }

    private function addCustodyEvent(
        int $tenantId,
        ?int $personId,
        ?int $photoId,
        string $type,
        string $label,
        string $actor
    ): void {
        if ($tenantId < 1) {
            return;
        }
        try {
            $this->db->insert(
                'INSERT INTO sse_custody_events (
                    tenant_id, person_id, photo_id, event_type, label, actor_callsign
                ) VALUES (
                    :tenant_id, :person_id, :photo_id, :event_type, :label, :actor_callsign
                )',
                [
                    'tenant_id' => $tenantId,
                    'person_id' => $personId,
                    'photo_id' => $photoId,
                    'event_type' => $type,
                    'label' => $label,
                    'actor_callsign' => $this->nullIfEmpty($actor),
                ]
            );
        } catch (\Throwable) {
            // table peut manquer sur vieux déploiements partiels
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row, bool $withPhotos): array
    {
        $weapons = $this->decodeJsonList($row['weapons_json'] ?? null);
        $equipment = $this->decodeJsonList($row['equipment_json'] ?? null);
        $status = self::normalizeStatus((string) ($row['status'] ?? self::STATUS_CIVIL));
        $out = [
            'id' => (int) ($row['id'] ?? 0),
            'tenant_id' => (int) ($row['tenant_id'] ?? 0),
            'context_id' => (int) ($row['context_id'] ?? 1),
            'mapId' => (int) ($row['context_id'] ?? 1),
            'status' => $status,
            'status_label' => self::statusLabel($status),
            'last_name' => (string) ($row['last_name'] ?? ''),
            'first_name' => (string) ($row['first_name'] ?? ''),
            'alias' => $row['alias'] ?? null,
            'display_name' => $this->displayName($row),
            'sex_apparent' => $row['sex_apparent'] ?? null,
            'age_estimated' => isset($row['age_estimated']) ? (int) $row['age_estimated'] : null,
            'birth_date' => $row['birth_date'] ?? null,
            'birth_place' => $row['birth_place'] ?? null,
            'nationality' => $row['nationality'] ?? null,
            'language_spoken' => $row['language_spoken'] ?? null,
            'id_document_present' => !empty($row['id_document_present']),
            'id_document_type' => $row['id_document_type'] ?? null,
            'id_document_number' => $row['id_document_number'] ?? null,
            'distinguishing_marks' => $row['distinguishing_marks'] ?? null,
            'affiliation' => $row['affiliation'] ?? null,
            'circumstances' => $row['circumstances'] ?? null,
            'circumstances_label' => $this->circumstancesLabel((string) ($row['circumstances'] ?? '')),
            'statements' => $row['statements'] ?? null,
            'confidence_level' => $row['confidence_level'] ?? null,
            'weapons' => $weapons,
            'equipment' => $equipment,
            'biometrics_simulated' => !empty($row['biometrics_simulated']),
            'consent_recorded' => !empty($row['consent_recorded']),
            'pos_x' => isset($row['capture_pos_x']) ? (float) $row['capture_pos_x'] : null,
            'pos_y' => isset($row['capture_pos_y']) ? (float) $row['capture_pos_y'] : null,
            'pos_z' => isset($row['capture_pos_z']) ? (float) $row['capture_pos_z'] : null,
            'grid_reference' => $row['grid_reference'] ?? null,
            'location_description' => $row['location_description'] ?? null,
            'submitter_callsign' => $row['submitter_callsign'] ?? null,
            'submitter_steam_id' => $row['submitter_steam_id'] ?? null,
            'primary_photo_id' => isset($row['primary_photo_id']) ? (int) $row['primary_photo_id'] : null,
            'target_unit_netid' => $row['target_unit_netid'] ?? null,
            'subject_id' => $row['subject_id'] ?? null,
            'seek_stage' => (string) ($row['seek_stage'] ?? 'capture'),
            'identity_tier' => strtoupper((string) ($row['identity_tier'] ?? 'DECLARED')),
            'acquisition_quality_avg' => isset($row['acquisition_quality_avg'])
                ? (int) $row['acquisition_quality_avg'] : null,
            'medical_context' => $this->decodeJsonMap($row['medical_context_json'] ?? null),
            'identity_query' => $this->decodeJsonMap($row['identity_query_json'] ?? null),
            'signature' => $this->hydrateSignature($row),
            'from_arma' => $this->isArmaSourced($row),
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];

        if ($withPhotos) {
            $out['photos'] = $this->listPhotos((int) $out['id'], (int) $out['tenant_id']);
            $primaryId = isset($row['primary_photo_id']) ? (int) $row['primary_photo_id'] : 0;
            $primary = null;
            foreach ($out['photos'] as $ph) {
                if ($primaryId > 0 && (int) ($ph['id'] ?? 0) === $primaryId) {
                    $primary = $ph;
                    break;
                }
            }
            if ($primary === null && $out['photos'] !== []) {
                $primary = $out['photos'][0];
            }
            $out['primary_photo'] = $primary;
        } elseif (!empty($row['primary_photo_id'])) {
            $photo = $this->db->fetchOne(
                'SELECT * FROM sse_person_photos WHERE id = :id LIMIT 1',
                ['id' => (int) $row['primary_photo_id']]
            );
            $out['primary_photo'] = $photo ? $this->hydratePhoto($photo) : null;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    /**
     * Décode une carte JSON (constat de terrain) en tolérant les valeurs absentes.
     *
     * @return array<string, mixed>
     */
    private function decodeJsonMap(mixed $raw): array
    {
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function isArmaSourced(array $row): bool
    {
        foreach (['signed_terminal_uid', 'signed_by_callsign', 'submitter_steam_id', 'target_unit_netid'] as $k) {
            if (trim((string) ($row[$k] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Fiches remontées du terrain (Arma / terminal) non encore liées à un dossier donné.
     *
     * @param list<int> $excludePersonIds
     * @return list<array<string, mixed>>
     */
    public function listArmaInbox(int $tenantId, int $mapId = 1, array $excludePersonIds = [], int $limit = 40): array
    {
        $limit = max(1, min(100, $limit));
        $sql = 'SELECT * FROM sse_persons
            WHERE tenant_id = :t
              AND (
                (signed_terminal_uid IS NOT NULL AND signed_terminal_uid <> \'\')
                OR (signed_by_callsign IS NOT NULL AND signed_by_callsign <> \'\')
                OR (submitter_steam_id IS NOT NULL AND submitter_steam_id <> \'\')
                OR (target_unit_netid IS NOT NULL AND target_unit_netid <> \'\')
              )';
        $params = ['t' => $tenantId];
        if ($mapId > 0) {
            $sql .= ' AND context_id = :m';
            $params['m'] = $mapId;
        }
        $exclude = array_values(array_filter(array_map('intval', $excludePersonIds), static fn (int $id): bool => $id > 0));
        if ($exclude !== []) {
            $placeholders = [];
            foreach ($exclude as $i => $id) {
                $key = 'ex' . $i;
                $placeholders[] = ':' . $key;
                $params[$key] = $id;
            }
            $sql .= ' AND id NOT IN (' . implode(',', $placeholders) . ')';
        }
        $sql .= ' ORDER BY id DESC LIMIT ' . $limit;
        $rows = $this->db->fetchAll($sql, $params);
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->hydrate($row, false);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    private function hydrateSignature(array $row): ?array
    {
        $callsign = trim((string) ($row['signed_by_callsign'] ?? ''));
        $terminal = trim((string) ($row['signed_terminal_uid'] ?? ''));
        if ($callsign === '' && $terminal === '') {
            return null;
        }

        return [
            'callsign' => $callsign !== '' ? $callsign : null,
            'terminal_uid' => $terminal !== '' ? $terminal : null,
            'atak_id' => $this->nullIfEmpty($row['signed_atak_id'] ?? null),
            'signed_at' => $row['signed_at'] ?? null,
        ];
    }

    /**
     * Fiche déjà ouverte pour la même identité (unité Arma, sinon nom+prénom+alias).
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function findLikelyDuplicate(int $tenantId, int $contextId, array $data): ?array
    {
        $netId = trim((string) ($data['target_unit_netid'] ?? ''));
        if ($netId !== '') {
            $this->ensureSchema();
            $byNet = $this->db->fetchOne(
                'SELECT * FROM sse_persons
                 WHERE tenant_id = :t AND context_id = :c AND target_unit_netid = :n
                 ORDER BY id ASC LIMIT 1',
                ['t' => $tenantId, 'c' => $contextId, 'n' => $netId]
            );
            if ($byNet) {
                return $this->hydrate($byNet, true);
            }
        }

        $key = \App\Support\SsePersonDedupe::identityKey(
            (string) ($data['last_name'] ?? ''),
            (string) ($data['first_name'] ?? ''),
            (string) ($data['alias'] ?? '')
        );
        if ($key === '') {
            return null;
        }

        $this->ensureSchema();
        $pdo = Database::getPdo();
        $lastEq = SqlText::normalizedEquals($pdo, 'last_name');
        $firstEq = SqlText::normalizedEquals($pdo, 'first_name');
        $aliasEq = SqlText::normalizedCoalesceEmptyEquals($pdo, 'alias');
        $row = $this->db->fetchOne(
            'SELECT * FROM sse_persons
             WHERE tenant_id = ? AND context_id = ?
               AND ' . $lastEq . '
               AND ' . $firstEq . '
               AND ' . $aliasEq . '
             ORDER BY id ASC
             LIMIT 1',
            [
                $tenantId,
                $contextId,
                mb_strtolower(trim((string) ($data['last_name'] ?? ''))),
                mb_strtolower(trim((string) ($data['first_name'] ?? ''))),
                mb_strtolower(trim((string) ($data['alias'] ?? ''))),
            ]
        );

        return $row ? $this->hydrate($row, true) : null;
    }

    /**
     * @param list<int> $personIds
     * @return array<int, list<array{kind: string, kind_label: string}>>
     */
    public function biometricSummariesForPersons(int $tenantId, array $personIds): array
    {
        $personIds = array_values(array_unique(array_filter(array_map('intval', $personIds))));
        if ($personIds === []) {
            return [];
        }
        $placeholders = [];
        $params = ['t' => $tenantId];
        foreach ($personIds as $i => $id) {
            $placeholders[] = ':p' . $i;
            $params['p' . $i] = $id;
        }
        try {
            $rows = $this->db->fetchAll(
                'SELECT person_id, kind FROM sse_biometric_samples
                 WHERE tenant_id = :t AND person_id IN (' . implode(',', $placeholders) . ')',
                $params
            );
        } catch (\Throwable) {
            return [];
        }
        $labels = ['empreintes' => 'Empreintes', 'iris' => 'Iris', 'adn' => 'ADN'];
        $out = [];
        foreach ($rows as $row) {
            $pid = (int) ($row['person_id'] ?? 0);
            $kind = \App\Support\SsePersonDedupe::normalizeKind((string) ($row['kind'] ?? 'empreintes'));
            if ($pid < 1) {
                continue;
            }
            $out[$pid] ??= [];
            $seen = array_column($out[$pid], 'kind');
            if (in_array($kind, $seen, true)) {
                continue;
            }
            $out[$pid][] = [
                'kind' => $kind,
                'kind_label' => $labels[$kind] ?? 'Empreintes',
            ];
        }

        return $out;
    }

    /**
     * Fiche déjà ouverte pour une unité Arma donnée (panneau « fiche existante »).
     *
     * @return array<string, mixed>|null
     */
    public function findByTargetUnit(int $tenantId, int $contextId, string $netId): ?array
    {
        $netId = trim($netId);
        if ($netId === '') {
            return null;
        }
        $this->ensureSchema();
        $row = $this->db->fetchOne(
            'SELECT * FROM sse_persons
             WHERE tenant_id = :t AND context_id = :c AND target_unit_netid = :n
             ORDER BY id DESC LIMIT 1',
            ['t' => $tenantId, 'c' => $contextId, 'n' => $netId]
        );

        return $row ? $this->hydrate($row, true) : null;
    }

    /**
     * Échantillon biométrique simulé. Un seul par modalité et par personne :
     * un second relevé remplace le précédent plutôt que d'empiler des doublons.
     */
    public function addBiometricSample(int $personId, int $tenantId, array $data): void
    {
        $kind = \App\Support\SsePersonDedupe::normalizeKind((string) ($data['kind'] ?? 'empreintes'));
        $quality = isset($data['quality']) ? max(0, min(100, (int) $data['quality'])) : null;
        $qualityLabel = $data['quality_label'] ?? null;
        if ($qualityLabel === null || $qualityLabel === '') {
            $qualityLabel = $quality === null ? null : (
                $quality < 30 ? 'Insuffisante' : (
                    $quality < 55 ? 'Partielle' : ($quality < 80 ? 'Bonne' : 'Excellente')
                )
            );
        }
        $laterality = $this->nullIfEmpty($data['laterality'] ?? null);
        $conditions = $data['conditions'] ?? $data['conditions_json'] ?? null;
        $conditionsJson = null;
        if (is_array($conditions)) {
            $conditionsJson = json_encode($conditions, JSON_UNESCAPED_UNICODE);
        } elseif (is_string($conditions) && $conditions !== '') {
            $conditionsJson = $conditions;
        }

        try {
            $this->db->execute(
                'INSERT INTO sse_biometric_samples
                    (person_id, tenant_id, kind, laterality, quality, quality_label, conditions_json, lab_reference, operator_callsign)
                 VALUES (:p, :t, :k, :lat, :q, :ql, :cj, :r, :o)
                 ON DUPLICATE KEY UPDATE
                    laterality = VALUES(laterality),
                    quality = VALUES(quality),
                    quality_label = VALUES(quality_label),
                    conditions_json = VALUES(conditions_json),
                    lab_reference = VALUES(lab_reference),
                    operator_callsign = VALUES(operator_callsign)',
                [
                    'p' => $personId,
                    't' => $tenantId,
                    'k' => $kind,
                    'lat' => $laterality,
                    'q' => $quality,
                    'ql' => $this->nullIfEmpty($qualityLabel),
                    'cj' => $conditionsJson,
                    'r' => $this->nullIfEmpty($data['lab_reference'] ?? null),
                    'o' => $this->nullIfEmpty($data['operator_callsign'] ?? null),
                ]
            );
        } catch (\Throwable) {
            try {
                $this->db->execute(
                    'INSERT INTO sse_biometric_samples
                        (person_id, tenant_id, kind, quality, lab_reference, operator_callsign)
                     VALUES (:p, :t, :k, :q, :r, :o)
                     ON DUPLICATE KEY UPDATE
                        quality = VALUES(quality),
                        lab_reference = VALUES(lab_reference),
                        operator_callsign = VALUES(operator_callsign)',
                    [
                        'p' => $personId,
                        't' => $tenantId,
                        'k' => $kind,
                        'q' => $quality,
                        'r' => $this->nullIfEmpty($data['lab_reference'] ?? null),
                        'o' => $this->nullIfEmpty($data['operator_callsign'] ?? null),
                    ]
                );
            } catch (\Throwable) {
                // Table absente sur une base non migrée : la fiche reste valide sans échantillon.
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listBiometricSamples(int $personId, int $tenantId): array
    {
        try {
            $rows = $this->db->fetchAll(
                'SELECT * FROM sse_biometric_samples
                 WHERE person_id = :p AND tenant_id = :t ORDER BY id ASC',
                ['p' => $personId, 't' => $tenantId]
            );
        } catch (\Throwable) {
            return [];
        }

        $labels = ['empreintes' => 'Empreintes', 'iris' => 'Iris', 'adn' => 'ADN'];
        $out = [];
        foreach ($rows as $row) {
            $kind = (string) ($row['kind'] ?? 'empreintes');
            $out[] = [
                'id' => (int) ($row['id'] ?? 0),
                'kind' => $kind,
                'kind_label' => $labels[$kind] ?? 'Empreintes',
                'laterality' => $row['laterality'] ?? null,
                'quality' => isset($row['quality']) ? (int) $row['quality'] : null,
                'quality_label' => $row['quality_label'] ?? null,
                'conditions' => $this->decodeJsonMap($row['conditions_json'] ?? null),
                'lab_reference' => $row['lab_reference'] ?? null,
                'operator_callsign' => $row['operator_callsign'] ?? null,
                'created_at' => $row['created_at'] ?? null,
            ];
        }

        return $out;
    }

    private function hydratePhoto(array $row): array
    {
        $path = (string) ($row['image_path'] ?? '');

        return [
            'id' => (int) ($row['id'] ?? 0),
            'person_id' => (int) ($row['person_id'] ?? 0),
            'image_path' => $path,
            'url' => $path !== '' ? user_media_public_url($path) : null,
            'angle' => (string) ($row['angle'] ?? 'face'),
            'angle_label' => $this->angleLabel((string) ($row['angle'] ?? 'face')),
            'photo_type' => (string) ($row['photo_type'] ?? 'FACE'),
            'quality' => isset($row['quality']) ? (int) $row['quality'] : null,
            'heading' => isset($row['heading']) ? (int) $row['heading'] : null,
            'case_id' => isset($row['case_id']) ? (int) $row['case_id'] : null,
            'target_ref' => $row['target_ref'] ?? null,
            'metadata' => $this->decodeJsonMap($row['metadata_json'] ?? null),
            'caption' => $row['caption'] ?? null,
            'author_callsign' => $row['author_callsign'] ?? null,
            'pos_x' => isset($row['pos_x']) ? (float) $row['pos_x'] : null,
            'pos_y' => isset($row['pos_y']) ? (float) $row['pos_y'] : null,
            'pos_z' => isset($row['pos_z']) ? (float) $row['pos_z'] : null,
            'created_at' => $row['created_at'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function displayName(array $row): string
    {
        $first = trim((string) ($row['first_name'] ?? ''));
        $last = trim((string) ($row['last_name'] ?? ''));
        $alias = trim((string) ($row['alias'] ?? ''));
        $name = trim($first . ' ' . $last);
        if ($name === '' && $alias !== '') {
            return $alias;
        }
        if ($alias !== '' && $name !== '') {
            return $name . ' (« ' . $alias . ' »)';
        }

        return $name !== '' ? $name : 'Personne sans nom';
    }

    private function circumstancesLabel(string $code): string
    {
        return match (strtolower(trim($code))) {
            'controle' => 'Contrôle',
            'perquisition' => 'Perquisition',
            'reddition' => 'Reddition',
            'autre' => 'Autre',
            default => $code !== '' ? $code : '—',
        };
    }

    private function angleLabel(string $angle): string
    {
        return match ($this->normalizeAngle($angle)) {
            'profil' => 'Profil',
            'trois_quarts' => 'Trois-quarts',
            default => 'Face',
        };
    }

    private function normalizeAngle(string $angle): string
    {
        $a = strtolower(trim($angle));

        return in_array($a, ['face', 'profil', 'trois_quarts'], true) ? $a : 'face';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function decodeJsonList(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function nullIfEmpty(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }

    private function floatOrNull(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }

        return (float) $v;
    }
}
