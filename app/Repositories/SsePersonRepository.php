<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
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
        $path = base_path('bootstrap/atak_sse_persons_migration.php');
        if (is_file($path)) {
            $migrate = require $path;
            if (is_callable($migrate)) {
                try {
                    $migrate(Database::getPdo());
                } catch (\Throwable) {
                    // ignore — run-migrations.php est la source principale
                }
            }
        }
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

        $sql = 'INSERT INTO sse_persons (
            tenant_id, context_id, status, last_name, first_name, alias,
            sex_apparent, age_estimated, birth_date, birth_place, nationality, language_spoken,
            id_document_present, id_document_type, id_document_number,
            distinguishing_marks, affiliation, circumstances, statements, confidence_level,
            weapons_json, equipment_json, biometrics_simulated, consent_recorded,
            capture_pos_x, capture_pos_y, capture_pos_z, grid_reference, location_description,
            submitter_user_id, submitter_callsign, submitter_steam_id, target_unit_netid
        ) VALUES (
            :tenant_id, :context_id, :status, :last_name, :first_name, :alias,
            :sex_apparent, :age_estimated, :birth_date, :birth_place, :nationality, :language_spoken,
            :id_document_present, :id_document_type, :id_document_number,
            :distinguishing_marks, :affiliation, :circumstances, :statements, :confidence_level,
            :weapons_json, :equipment_json, :biometrics_simulated, :consent_recorded,
            :capture_pos_x, :capture_pos_y, :capture_pos_z, :grid_reference, :location_description,
            :submitter_user_id, :submitter_callsign, :submitter_steam_id, :target_unit_netid
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
    public function addPhoto(int $personId, int $tenantId, array $data): int
    {
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
        $n = $this->db->execute(
            'UPDATE sse_persons SET biometrics_simulated = 1 WHERE id = :id AND tenant_id = :t',
            ['id' => $personId, 't' => $tenantId]
        );
        if ($n < 1) {
            return false;
        }
        $label = $kind === 'iris'
            ? 'Simulation biométrique (iris) effectuée'
            : 'Simulation biométrique (empreintes) effectuée';
        $this->addCustodyEvent($tenantId, $personId, null, 'biometrie_sim', $label, $actorCallsign);

        return true;
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
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];

        if ($withPhotos) {
            $out['photos'] = $this->listPhotos((int) $out['id'], (int) $out['tenant_id']);
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
    private function hydratePhoto(array $row): array
    {
        $path = (string) ($row['image_path'] ?? '');

        return [
            'id' => (int) ($row['id'] ?? 0),
            'person_id' => (int) ($row['person_id'] ?? 0),
            'image_path' => $path,
            'url' => $path !== '' ? '/' . ltrim($path, '/') : null,
            'angle' => (string) ($row['angle'] ?? 'face'),
            'angle_label' => $this->angleLabel((string) ($row['angle'] ?? 'face')),
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
