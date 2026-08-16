<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SilentSchemaMigration;

/**
 * Exploitation de site sensible — dossiers site, checklist de pièces, saisies.
 *
 * Les tables existent depuis la 1.4.0 mais n'étaient exploitées par aucun code.
 * Même modèle que SsePersonRepository : libellés métier calculés au chargement,
 * jamais de valeur brute exposée à l'écran.
 */
final class SseSiteRepository
{
    public const STATUS_OPEN = 'ouvert';
    public const STATUS_IN_PROGRESS = 'en_cours';
    public const STATUS_CLOSED = 'cloture';

    /** @var array<string, string> */
    public const STATUS_LABELS = [
        self::STATUS_OPEN => 'Ouvert',
        self::STATUS_IN_PROGRESS => 'Exploitation en cours',
        self::STATUS_CLOSED => 'Clôturé',
    ];

    /** @var array<string, string> */
    public const TYPE_LABELS = [
        'habitation' => 'Habitation',
        'depot' => 'Dépôt',
        'poste_ennemi' => 'Poste de commandement ennemi',
        'cache' => 'Cache',
        'vehicule' => 'Véhicule fouillé',
        'autre' => 'Autre',
    ];

    /** @var array<string, string> */
    public const SEIZURE_LABELS = [
        'arme' => 'Armement',
        'munition' => 'Munitions',
        'document' => 'Documents',
        'radio' => 'Matériel de transmission',
        'medical' => 'Matériel médical',
        'numerique' => 'Support numérique',
        'valeur' => 'Valeurs',
        'autre' => 'Autre',
    ];

    /** Pièces proposées par défaut selon le type de site. */
    private const DEFAULT_ROOMS = [
        'habitation' => ['Entrée', 'Séjour', 'Chambres', 'Cuisine', 'Sanitaires', 'Combles', 'Cave', 'Extérieur'],
        'depot' => ['Accès', 'Quai', 'Zone de stockage', 'Bureau', 'Sanitaires', 'Extérieur'],
        'poste_ennemi' => ['Accès', 'Salle de transmission', 'Salle de commandement', 'Repos', 'Réserve', 'Extérieur'],
        'cache' => ['Abord', 'Ouverture', 'Volume principal', 'Fond'],
        'vehicule' => ['Habitacle avant', 'Habitacle arrière', 'Coffre', 'Compartiment moteur', 'Dessous de caisse'],
        'autre' => ['Accès', 'Volume principal', 'Extérieur'],
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

        return isset(self::STATUS_LABELS[$s]) ? $s : self::STATUS_OPEN;
    }

    public static function normalizeType(string $raw): string
    {
        $s = strtolower(trim($raw));

        return isset(self::TYPE_LABELS[$s]) ? $s : 'habitation';
    }

    public static function normalizeSeizureCategory(string $raw): string
    {
        $s = strtolower(trim($raw));

        return isset(self::SEIZURE_LABELS[$s]) ? $s : 'autre';
    }

    /**
     * Référence lisible d'un site, du même format que les dossiers.
     */
    public function nextReference(int $tenantId): string
    {
        $year = date('Y');
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS c FROM sse_sites WHERE tenant_id = :t AND reference_code LIKE :p',
            ['t' => $tenantId, 'p' => 'SITE-' . $year . '-%']
        );
        $n = (int) ($row['c'] ?? 0) + 1;

        return sprintf('SITE-%s-%04d', $year, $n);
    }

    /**
     * Ouvre un dossier site et prégarnit la checklist des pièces.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $tenantId = (int) ($data['tenant_id'] ?? 0);
        $type = self::normalizeType((string) ($data['site_type'] ?? 'habitation'));
        $ref = strtoupper(trim((string) ($data['reference_code'] ?? '')));
        if ($ref === '') {
            $ref = $this->nextReference($tenantId);
        }

        $id = (int) $this->db->insert(
            'INSERT INTO sse_sites (
                tenant_id, context_id, case_id, reference_code, name, site_type, status, team_label,
                pos_x, pos_y, pos_z, grid_reference, summary,
                submitter_callsign, submitter_steam_id
            ) VALUES (
                :tenant_id, :context_id, :case_id, :reference_code, :name, :site_type, :status, :team_label,
                :pos_x, :pos_y, :pos_z, :grid_reference, :summary,
                :submitter_callsign, :submitter_steam_id
            )',
            [
                'tenant_id' => $tenantId,
                'context_id' => (int) ($data['context_id'] ?? $data['mapId'] ?? 1),
                'case_id' => !empty($data['case_id']) ? (int) $data['case_id'] : null,
                'reference_code' => $ref,
                'name' => trim((string) ($data['name'] ?? '')) ?: 'Site sans nom',
                'site_type' => $type,
                'status' => self::normalizeStatus((string) ($data['status'] ?? self::STATUS_OPEN)),
                'team_label' => $this->nullIfEmpty($data['team_label'] ?? null),
                'pos_x' => $this->floatOrNull($data['pos_x'] ?? null),
                'pos_y' => $this->floatOrNull($data['pos_y'] ?? null),
                'pos_z' => $this->floatOrNull($data['pos_z'] ?? null),
                'grid_reference' => $this->nullIfEmpty($data['grid_reference'] ?? null),
                'summary' => $this->nullIfEmpty($data['summary'] ?? null),
                'submitter_callsign' => $this->nullIfEmpty($data['submitter_callsign'] ?? null),
                'submitter_steam_id' => $this->nullIfEmpty($data['submitter_steam_id'] ?? null),
            ]
        );

        // Checklist : celle fournie, sinon le gabarit du type de site.
        $rooms = $data['rooms'] ?? null;
        if (!is_array($rooms) || $rooms === []) {
            $rooms = self::DEFAULT_ROOMS[$type] ?? self::DEFAULT_ROOMS['autre'];
        }
        $order = 0;
        foreach ($rooms as $room) {
            $label = is_array($room) ? (string) ($room['label'] ?? '') : (string) $room;
            $label = trim($label);
            if ($label === '') {
                continue;
            }
            $this->addRoom($id, $tenantId, ['label' => $label, 'sort_order' => $order]);
            $order++;
        }

        $this->addCustodyEvent($tenantId, $id, 'site_ouvert', sprintf('Site ouvert : %s', $ref), (string) ($data['submitter_callsign'] ?? ''));

        return $id;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function addRoom(int $siteId, int $tenantId, array $data): int
    {
        $zone = strtoupper(trim((string) ($data['zone_type'] ?? 'ROOM')));
        if ($zone === '') {
            $zone = 'ROOM';
        }
        $pct = isset($data['exploitation_pct'])
            ? max(0, min(100, (int) $data['exploitation_pct']))
            : (!empty($data['checked']) ? 100 : 0);

        try {
            return (int) $this->db->insert(
                'INSERT INTO sse_site_rooms (site_id, tenant_id, label, zone_type, checked, exploitation_pct, notes, sort_order)
                 VALUES (:s, :t, :l, :z, :c, :p, :n, :o)',
                [
                    's' => $siteId,
                    't' => $tenantId,
                    'l' => trim((string) ($data['label'] ?? '')),
                    'z' => $zone,
                    'c' => !empty($data['checked']) ? 1 : 0,
                    'p' => $pct,
                    'n' => $this->nullIfEmpty($data['notes'] ?? null),
                    'o' => (int) ($data['sort_order'] ?? 0),
                ]
            );
        } catch (\Throwable) {
            return (int) $this->db->insert(
                'INSERT INTO sse_site_rooms (site_id, tenant_id, label, checked, notes, sort_order)
                 VALUES (:s, :t, :l, :c, :n, :o)',
                [
                    's' => $siteId,
                    't' => $tenantId,
                    'l' => trim((string) ($data['label'] ?? '')),
                    'c' => !empty($data['checked']) ? 1 : 0,
                    'n' => $this->nullIfEmpty($data['notes'] ?? null),
                    'o' => (int) ($data['sort_order'] ?? 0),
                ]
            );
        }
    }

    /**
     * Marque une pièce comme fouillée ou non.
     */
    public function setRoomChecked(int $roomId, int $tenantId, bool $checked, ?string $notes = null): bool
    {
        try {
            $affected = $this->db->execute(
                'UPDATE sse_site_rooms
                 SET checked = :c,
                     exploitation_pct = CASE WHEN :c2 = 1 THEN 100 ELSE 0 END,
                     notes = COALESCE(:n, notes)
                 WHERE id = :id AND tenant_id = :t',
                [
                    'c' => $checked ? 1 : 0,
                    'c2' => $checked ? 1 : 0,
                    'n' => $this->nullIfEmpty($notes),
                    'id' => $roomId,
                    't' => $tenantId,
                ]
            );
        } catch (\Throwable) {
            $affected = $this->db->execute(
                'UPDATE sse_site_rooms SET checked = :c, notes = COALESCE(:n, notes)
                 WHERE id = :id AND tenant_id = :t',
                ['c' => $checked ? 1 : 0, 'n' => $this->nullIfEmpty($notes), 'id' => $roomId, 't' => $tenantId]
            );
        }

        return $affected > 0;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateRoomZone(int $roomId, int $tenantId, array $data): bool
    {
        $sets = [];
        $params = ['id' => $roomId, 't' => $tenantId];
        if (array_key_exists('zone_type', $data)) {
            $sets[] = 'zone_type = :zone_type';
            $params['zone_type'] = strtoupper(trim((string) $data['zone_type'])) ?: 'ROOM';
        }
        if (array_key_exists('exploitation_pct', $data)) {
            $sets[] = 'exploitation_pct = :exploitation_pct';
            $params['exploitation_pct'] = max(0, min(100, (int) $data['exploitation_pct']));
            $sets[] = 'checked = :checked';
            $params['checked'] = ((int) $params['exploitation_pct']) >= 100 ? 1 : 0;
        }
        if ($sets === []) {
            return false;
        }
        try {
            return $this->db->execute(
                'UPDATE sse_site_rooms SET ' . implode(', ', $sets) . ' WHERE id = :id AND tenant_id = :t',
                $params
            ) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    public function setExploitationPct(int $siteId, int $tenantId, int $pct): bool
    {
        try {
            return $this->db->execute(
                'UPDATE sse_sites SET exploitation_pct = :p WHERE id = :id AND tenant_id = :t',
                ['p' => max(0, min(100, $pct)), 'id' => $siteId, 't' => $tenantId]
            ) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public function addSeizure(int $tenantId, array $data): int
    {
        $siteId = isset($data['site_id']) ? (int) $data['site_id'] : 0;
        $custody = strtoupper(trim((string) ($data['custody_state'] ?? 'OBSERVED')));
        if ($custody === '') {
            $custody = 'OBSERVED';
        }
        try {
            $id = (int) $this->db->insert(
                'INSERT INTO sse_seizures (
                    tenant_id, site_id, person_id, room_id, category, label, quantity, notes,
                    custody_state, packaging, seal_code, actor_callsign
                ) VALUES (
                    :t, :s, :p, :r, :c, :l, :q, :n,
                    :cs, :pack, :seal, :actor
                )',
                [
                    't' => $tenantId,
                    's' => $siteId > 0 ? $siteId : null,
                    'p' => !empty($data['person_id']) ? (int) $data['person_id'] : null,
                    'r' => !empty($data['room_id']) ? (int) $data['room_id'] : null,
                    'c' => self::normalizeSeizureCategory((string) ($data['category'] ?? 'autre')),
                    'l' => trim((string) ($data['label'] ?? '')) ?: 'Objet non désigné',
                    'q' => max(1, (int) ($data['quantity'] ?? 1)),
                    'n' => $this->nullIfEmpty($data['notes'] ?? null),
                    'cs' => $custody,
                    'pack' => $this->nullIfEmpty($data['packaging'] ?? null),
                    'seal' => $this->nullIfEmpty($data['seal_code'] ?? null),
                    'actor' => $this->nullIfEmpty($data['actor_callsign'] ?? null),
                ]
            );
        } catch (\Throwable) {
            $id = (int) $this->db->insert(
                'INSERT INTO sse_seizures (tenant_id, site_id, person_id, room_id, category, label, quantity, notes)
                 VALUES (:t, :s, :p, :r, :c, :l, :q, :n)',
                [
                    't' => $tenantId,
                    's' => $siteId > 0 ? $siteId : null,
                    'p' => !empty($data['person_id']) ? (int) $data['person_id'] : null,
                    'r' => !empty($data['room_id']) ? (int) $data['room_id'] : null,
                    'c' => self::normalizeSeizureCategory((string) ($data['category'] ?? 'autre')),
                    'l' => trim((string) ($data['label'] ?? '')) ?: 'Objet non désigné',
                    'q' => max(1, (int) ($data['quantity'] ?? 1)),
                    'n' => $this->nullIfEmpty($data['notes'] ?? null),
                ]
            );
        }

        if ($siteId > 0) {
            $this->addCustodyEvent(
                $tenantId,
                $siteId,
                'saisie',
                sprintf('Saisie : %s', trim((string) ($data['label'] ?? 'objet'))),
                (string) ($data['actor_callsign'] ?? ''),
                $id
            );
        }

        return $id;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateSeizureCustody(int $seizureId, int $tenantId, array $data): bool
    {
        try {
            return $this->db->execute(
                'UPDATE sse_seizures SET
                    custody_state = :cs,
                    packaging = COALESCE(:pack, packaging),
                    seal_code = COALESCE(:seal, seal_code),
                    sealed_at = COALESCE(:sealed_at, sealed_at),
                    actor_callsign = COALESCE(:actor, actor_callsign),
                    exploited_at = COALESCE(:exploited_at, exploited_at)
                 WHERE id = :id AND tenant_id = :t',
                [
                    'cs' => strtoupper(trim((string) ($data['custody_state'] ?? 'OBSERVED'))),
                    'pack' => $this->nullIfEmpty($data['packaging'] ?? null),
                    'seal' => $this->nullIfEmpty($data['seal_code'] ?? null),
                    'sealed_at' => $data['sealed_at'] ?? null,
                    'actor' => $this->nullIfEmpty($data['actor_callsign'] ?? null),
                    'exploited_at' => $data['exploited_at'] ?? null,
                    'id' => $seizureId,
                    't' => $tenantId,
                ]
            ) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    public function addCustodyEventPublic(
        int $tenantId,
        int $siteId,
        string $type,
        string $label,
        string $actor,
        ?int $seizureId = null
    ): void {
        $this->addCustodyEvent($tenantId, $siteId, $type, $label, $actor, $seizureId);
    }

    /**
     * Saisie unique, catégorie déjà traduite — nécessaire aux automatismes qui
     * jugent sur la nature normalisée, pas sur ce que le terrain a tapé.
     *
     * @return array<string, mixed>|null
     */
    public function findSeizure(int $id, int $tenantId): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM sse_seizures WHERE id = :id AND tenant_id = :t',
            ['id' => $id, 't' => $tenantId]
        );
        if (!$row) {
            return null;
        }
        $cat = self::normalizeSeizureCategory((string) ($row['category'] ?? 'autre'));
        $row['category'] = $cat;
        $row['category_label'] = self::SEIZURE_LABELS[$cat] ?? 'Autre';
        $custody = strtoupper((string) ($row['custody_state'] ?? 'OBSERVED'));
        $row['custody_state'] = $custody !== '' ? $custody : 'OBSERVED';

        return $row;
    }

    /**
     * Clôture : le compte rendu est figé et le site n'accepte plus de saisie côté portail.
     */
    public function close(int $siteId, int $tenantId, ?string $summary, ?string $actor = null): bool
    {
        $affected = $this->db->execute(
            'UPDATE sse_sites
             SET status = :st, summary = COALESCE(:s, summary), closed_at = NOW()
             WHERE id = :id AND tenant_id = :t',
            ['st' => self::STATUS_CLOSED, 's' => $this->nullIfEmpty($summary), 'id' => $siteId, 't' => $tenantId]
        );
        if ($affected < 1) {
            return false;
        }
        $this->addCustodyEvent($tenantId, $siteId, 'site_cloture', 'Site clôturé', (string) ($actor ?? ''));

        return true;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id, int $tenantId): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM sse_sites WHERE id = :id AND tenant_id = :t LIMIT 1',
            ['id' => $id, 't' => $tenantId]
        );
        if ($row === null) {
            return null;
        }
        $site = $this->hydrate($row);
        $site['rooms'] = $this->listRooms($id, $tenantId);
        $site['seizures'] = $this->listSeizures($id, $tenantId);

        return $site;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByReferenceCode(int $tenantId, string $reference): ?array
    {
        $reference = strtoupper(trim($reference));
        if ($reference === '') {
            return null;
        }
        $row = $this->db->fetchOne(
            'SELECT * FROM sse_sites WHERE tenant_id = :t AND UPPER(reference_code) = :r LIMIT 1',
            ['t' => $tenantId, 'r' => $reference]
        );

        return $row ? $this->hydrate($row) : null;
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function listForContext(int $tenantId, int $contextId = 1, array $filters = []): array
    {
        $where = ['tenant_id = :t', 'context_id = :c'];
        $params = ['t' => $tenantId, 'c' => $contextId];

        if (!empty($filters['status'])) {
            $where[] = 'status = :st';
            $params['st'] = self::normalizeStatus((string) $filters['status']);
        }
        if (!empty($filters['site_type'])) {
            $where[] = 'site_type = :ty';
            $params['ty'] = self::normalizeType((string) $filters['site_type']);
        }

        $limit = max(1, min(200, (int) ($filters['limit'] ?? 100)));
        $rows = $this->db->fetchAll(
            'SELECT * FROM sse_sites WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT ' . $limit,
            $params
        );

        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->hydrate($row);
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
                'SELECT * FROM sse_sites
                 WHERE tenant_id = :t
                   AND (name LIKE :q1 OR reference_code LIKE :q2 OR CAST(id AS CHAR) LIKE :q3)
                 ORDER BY id DESC
                 LIMIT ' . $limit,
                ['t' => $tenantId, 'q1' => $like, 'q2' => $like, 'q3' => $like]
            );
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->hydrate($row);
        }

        return $out;
    }

    /**
     * Sites rattachés à un dossier.
     *
     * @return list<array<string, mixed>>
     */
    public function listForCase(int $caseId, int $tenantId): array
    {
        if ($caseId < 1) {
            return [];
        }
        try {
            $rows = $this->db->fetchAll(
                'SELECT * FROM sse_sites WHERE tenant_id = :t AND case_id = :c ORDER BY id ASC',
                ['t' => $tenantId, 'c' => $caseId]
            );
        } catch (\Throwable) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->hydrate($row);
        }

        return $out;
    }

    /**
     * Rattache un site à un dossier après coup.
     */
    public function attachToCase(int $siteId, int $tenantId, ?int $caseId): bool
    {
        return $this->db->execute(
            'UPDATE sse_sites SET case_id = :c WHERE id = :id AND tenant_id = :t',
            ['c' => $caseId, 'id' => $siteId, 't' => $tenantId]
        ) > 0;
    }

    /**
     * Volumétrie par site : pièces fouillées / total, nombre de saisies.
     *
     * @param list<int> $siteIds
     * @return array<int, array{rooms: int, rooms_checked: int, seizures: int}>
     */
    public function countsForSites(array $siteIds, int $tenantId): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $siteIds),
            static fn (int $i): bool => $i > 0
        )));
        if ($ids === []) {
            return [];
        }
        $in = implode(',', $ids);

        $out = [];
        foreach ($ids as $id) {
            $out[$id] = ['rooms' => 0, 'rooms_checked' => 0, 'seizures' => 0];
        }

        try {
            $rows = $this->db->fetchAll(
                "SELECT site_id, COUNT(*) AS total, SUM(checked) AS done
                 FROM sse_site_rooms WHERE tenant_id = :t AND site_id IN ({$in}) GROUP BY site_id",
                ['t' => $tenantId]
            );
            foreach ($rows as $row) {
                $sid = (int) ($row['site_id'] ?? 0);
                if (isset($out[$sid])) {
                    $out[$sid]['rooms'] = (int) ($row['total'] ?? 0);
                    $out[$sid]['rooms_checked'] = (int) ($row['done'] ?? 0);
                }
            }
        } catch (\Throwable) {
            // table absente : volumétrie à zéro
        }

        try {
            $rows = $this->db->fetchAll(
                "SELECT site_id, COUNT(*) AS c
                 FROM sse_seizures WHERE tenant_id = :t AND site_id IN ({$in}) GROUP BY site_id",
                ['t' => $tenantId]
            );
            foreach ($rows as $row) {
                $sid = (int) ($row['site_id'] ?? 0);
                if (isset($out[$sid])) {
                    $out[$sid]['seizures'] = (int) ($row['c'] ?? 0);
                }
            }
        } catch (\Throwable) {
            // idem
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listRooms(int $siteId, int $tenantId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM sse_site_rooms WHERE site_id = :s AND tenant_id = :t
             ORDER BY sort_order ASC, id ASC',
            ['s' => $siteId, 't' => $tenantId]
        );

        $out = [];
        foreach ($rows as $row) {
            $zone = strtoupper((string) ($row['zone_type'] ?? 'ROOM'));
            $out[] = [
                'id' => (int) ($row['id'] ?? 0),
                'label' => (string) ($row['label'] ?? ''),
                'zone_type' => $zone !== '' ? $zone : 'ROOM',
                'checked' => !empty($row['checked']),
                'exploitation_pct' => isset($row['exploitation_pct'])
                    ? (int) $row['exploitation_pct']
                    : (!empty($row['checked']) ? 100 : 0),
                'notes' => $row['notes'] ?? null,
                'sort_order' => (int) ($row['sort_order'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listSeizures(int $siteId, int $tenantId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM sse_seizures WHERE site_id = :s AND tenant_id = :t ORDER BY id ASC',
            ['s' => $siteId, 't' => $tenantId]
        );

        $out = [];
        foreach ($rows as $row) {
            $cat = (string) ($row['category'] ?? 'autre');
            $custody = strtoupper((string) ($row['custody_state'] ?? 'OBSERVED'));
            $out[] = [
                'id' => (int) ($row['id'] ?? 0),
                'category' => $cat,
                'category_label' => self::SEIZURE_LABELS[$cat] ?? 'Autre',
                'label' => (string) ($row['label'] ?? ''),
                'quantity' => (int) ($row['quantity'] ?? 1),
                'notes' => $row['notes'] ?? null,
                'custody_state' => $custody !== '' ? $custody : 'OBSERVED',
                'packaging' => $row['packaging'] ?? null,
                'seal_code' => $row['seal_code'] ?? null,
                'sealed_at' => $row['sealed_at'] ?? null,
                'actor_callsign' => $row['actor_callsign'] ?? null,
                'exploited_at' => $row['exploited_at'] ?? null,
                'person_id' => isset($row['person_id']) ? (int) $row['person_id'] : null,
                'room_id' => isset($row['room_id']) ? (int) $row['room_id'] : null,
                'created_at' => $row['created_at'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * Compte rendu en cinq lignes, généré à la clôture puis modifiable.
     *
     * @param array<string, mixed> $site
     */
    public function buildFiveLineReport(array $site): string
    {
        $rooms = is_array($site['rooms'] ?? null) ? $site['rooms'] : [];
        $seizures = is_array($site['seizures'] ?? null) ? $site['seizures'] : [];
        $checked = count(array_filter($rooms, static fn (array $r): bool => !empty($r['checked'])));

        $byCategory = [];
        foreach ($seizures as $s) {
            $lbl = (string) ($s['category_label'] ?? 'Autre');
            $byCategory[$lbl] = ($byCategory[$lbl] ?? 0) + (int) ($s['quantity'] ?? 1);
        }
        $seizureTxt = [];
        foreach ($byCategory as $lbl => $qty) {
            $seizureTxt[] = sprintf('%s ×%d', $lbl, $qty);
        }

        $lines = [
            sprintf('1. Site — %s (%s)', (string) ($site['name'] ?? ''), (string) ($site['site_type_label'] ?? '')),
            sprintf('2. Position — grille %s', (string) ($site['grid_reference'] ?? 'non relevée')),
            sprintf('3. Équipe — %s', (string) ($site['team_label'] ?? 'non précisée')),
            sprintf('4. Fouille — %d pièce(s) sur %d', $checked, count($rooms)),
            sprintf('5. Saisies — %s', $seizureTxt === [] ? 'aucune' : implode(', ', $seizureTxt)),
        ];

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $status = self::normalizeStatus((string) ($row['status'] ?? self::STATUS_OPEN));
        $type = self::normalizeType((string) ($row['site_type'] ?? 'habitation'));

        return [
            'id' => (int) ($row['id'] ?? 0),
            'tenant_id' => (int) ($row['tenant_id'] ?? 0),
            'context_id' => (int) ($row['context_id'] ?? 1),
            'reference_code' => (string) ($row['reference_code'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'site_type' => $type,
            'site_type_label' => self::TYPE_LABELS[$type] ?? 'Autre',
            'status' => $status,
            'status_label' => self::STATUS_LABELS[$status] ?? 'Ouvert',
            'exploitation_pct' => isset($row['exploitation_pct']) ? (int) $row['exploitation_pct'] : 0,
            'team_label' => $row['team_label'] ?? null,
            'pos_x' => isset($row['pos_x']) ? (float) $row['pos_x'] : null,
            'pos_y' => isset($row['pos_y']) ? (float) $row['pos_y'] : null,
            'pos_z' => isset($row['pos_z']) ? (float) $row['pos_z'] : null,
            'grid_reference' => $row['grid_reference'] ?? null,
            'summary' => $row['summary'] ?? null,
            'case_id' => isset($row['case_id']) ? (int) $row['case_id'] : null,
            'submitter_callsign' => $row['submitter_callsign'] ?? null,
            'closed_at' => $row['closed_at'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private function addCustodyEvent(
        int $tenantId,
        int $siteId,
        string $type,
        string $label,
        string $actor,
        ?int $seizureId = null
    ): void {
        if ($tenantId < 1) {
            return;
        }
        try {
            $this->db->execute(
                'INSERT INTO sse_custody_events (tenant_id, site_id, seizure_id, event_type, label, actor_callsign)
                 VALUES (:t, :s, :sz, :e, :l, :a)',
                [
                    't' => $tenantId,
                    's' => $siteId,
                    'sz' => $seizureId,
                    'e' => $type,
                    'l' => mb_substr($label, 0, 255),
                    'a' => $this->nullIfEmpty($actor),
                ]
            );
        } catch (\Throwable) {
            try {
                $this->db->execute(
                    'INSERT INTO sse_custody_events (tenant_id, site_id, event_type, label, actor_callsign)
                     VALUES (:t, :s, :e, :l, :a)',
                    [
                        't' => $tenantId,
                        's' => $siteId,
                        'e' => $type,
                        'l' => mb_substr($label, 0, 255),
                        'a' => $this->nullIfEmpty($actor),
                    ]
                );
            } catch (\Throwable) {
                // la traçabilité ne doit jamais faire échouer l'opération métier
            }
        }
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

        return is_numeric($v) ? (float) $v : null;
    }
}
