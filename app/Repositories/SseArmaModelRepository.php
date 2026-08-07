<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class SseArmaModelRepository
{
    /** @var array<string, string> */
    public const PROFILE_LABELS = [
        'CIVILIAN' => 'Civil',
        'INSURGENT' => 'Insurgé',
        'MILITARY' => 'Militaire',
        'COMMANDER' => 'Commandant / HVT',
        'COURIER' => 'Courrier',
        'FINANCIER' => 'Financier',
        'TECHNICIAN' => 'Technicien',
        'INTELLIGENCE' => 'Renseignement',
        'LOGISTICS' => 'Logistique',
        'RANDOM' => 'Aléatoire',
    ];

    /** @var array<string, string> */
    public const COMPLEXITY_LABELS = [
        'LIGHT' => 'Léger',
        'STANDARD' => 'Standard',
        'DETAILED' => 'Détaillé',
        'HIGH_VALUE' => 'Haute valeur',
    ];

    /** @var array<string, string> */
    public const REGION_LABELS = [
        'IRAQ' => 'Irak',
        'SYRIA' => 'Syrie',
        'LEVANT' => 'Levant',
        'AFRICA_SAHEL' => 'Sahel',
        'GENERIC' => 'Générique',
    ];

    /** @var array<string, string> */
    public const THEME_LABELS = [
        'fuel_delivery' => 'Livraison de carburant',
        'weapons_cache' => 'Cache d’armes',
        'meeting_alpha' => 'Réunion point ALPHA',
        'courier_run' => 'Course de courrier',
        'finance_drop' => 'Drop financier',
        'ied_cell' => 'Cellule IED',
        'safehouse' => 'Planque',
        'recruitment' => 'Recrutement',
        'smuggling' => 'Contrebande',
        'drone_ops' => 'Opérations drone',
        'propaganda' => 'Propagande / média',
        'medical_logistics' => 'Logistique médicale',
        'RANDOM' => 'Aléatoire',
    ];

    /** @var array<string, string> */
    public const STATUS_LABELS = [
        'draft' => 'Brouillon',
        'published' => 'Publié',
        'archived' => 'Archivé',
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
        $path = base_path('bootstrap/atak_sse_arma_models_migration.php');
        if (is_file($path)) {
            $migrate = require $path;
            if (is_callable($migrate)) {
                try {
                    $migrate(Database::getPdo());
                } catch (\Throwable) {
                }
            }
        }
        $done = true;
    }

    /**
     * @param array{status?:string,q?:string,profile?:string,limit?:int} $filters
     * @return list<array<string,mixed>>
     */
    public function listForTenant(int $tenantId, array $filters = []): array
    {
        $sql = 'SELECT * FROM sse_arma_models WHERE tenant_id = ? AND deleted_at IS NULL';
        $args = [$tenantId];

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $sql .= ' AND status = ?';
            $args[] = $status;
        }
        $profile = trim((string) ($filters['profile'] ?? ''));
        if ($profile !== '') {
            $sql .= ' AND profile_code = ?';
            $args[] = $profile;
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $sql .= ' AND (name LIKE ? OR public_id LIKE ? OR notes LIKE ? OR author_label LIKE ?)';
            $like = '%' . $q . '%';
            array_push($args, $like, $like, $like, $like);
        }

        $sql .= ' ORDER BY updated_at DESC';
        $limit = (int) ($filters['limit'] ?? 200);
        if ($limit > 0) {
            $sql .= ' LIMIT ' . min(500, max(1, $limit));
        }

        $st = $this->db->query($sql, $args);

        return array_map([$this, 'hydrate'], $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function findForTenant(int $id, int $tenantId): ?array
    {
        $st = $this->db->query(
            'SELECT * FROM sse_arma_models WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL LIMIT 1',
            [$id, $tenantId]
        );
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function findByPublicId(string $publicId, int $tenantId): ?array
    {
        $st = $this->db->query(
            'SELECT * FROM sse_arma_models WHERE public_id = ? AND tenant_id = ? AND deleted_at IS NULL LIMIT 1',
            [$publicId, $tenantId]
        );
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    /**
     * @param array<string,mixed> $data
     */
    public function create(int $tenantId, array $data): int
    {
        $this->db->query(
            'INSERT INTO sse_arma_models (
                tenant_id, public_id, name, author_label, source, status,
                profile_code, complexity_code, region_code, theme_code,
                include_biometrics, include_phone, include_documents, include_computer,
                network_size, noise_probability, false_lead_probability, notes,
                payload_json, tags_json, version, created_by, updated_by
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $tenantId,
                (string) $data['public_id'],
                (string) $data['name'],
                $data['author_label'] ?? null,
                (string) ($data['source'] ?? 'WEB'),
                (string) ($data['status'] ?? 'draft'),
                (string) $data['profile_code'],
                (string) $data['complexity_code'],
                (string) $data['region_code'],
                (string) $data['theme_code'],
                !empty($data['include_biometrics']) ? 1 : 0,
                !empty($data['include_phone']) ? 1 : 0,
                !empty($data['include_documents']) ? 1 : 0,
                !empty($data['include_computer']) ? 1 : 0,
                (int) ($data['network_size'] ?? 8),
                $data['noise_probability'] ?? null,
                $data['false_lead_probability'] ?? null,
                $data['notes'] ?? null,
                (string) $data['payload_json'],
                isset($data['tags_json']) ? (string) $data['tags_json'] : null,
                (int) ($data['version'] ?? 1),
                $data['created_by'] ?? null,
                $data['updated_by'] ?? null,
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    /**
     * @param array<string,mixed> $data
     */
    public function update(int $id, int $tenantId, array $data): bool
    {
        $this->db->query(
            'UPDATE sse_arma_models SET
                name = ?, author_label = ?, status = ?,
                profile_code = ?, complexity_code = ?, region_code = ?, theme_code = ?,
                include_biometrics = ?, include_phone = ?, include_documents = ?, include_computer = ?,
                network_size = ?, noise_probability = ?, false_lead_probability = ?, notes = ?,
                payload_json = ?, tags_json = ?, version = version + 1, updated_by = ?
             WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL',
            [
                (string) $data['name'],
                $data['author_label'] ?? null,
                (string) ($data['status'] ?? 'draft'),
                (string) $data['profile_code'],
                (string) $data['complexity_code'],
                (string) $data['region_code'],
                (string) $data['theme_code'],
                !empty($data['include_biometrics']) ? 1 : 0,
                !empty($data['include_phone']) ? 1 : 0,
                !empty($data['include_documents']) ? 1 : 0,
                !empty($data['include_computer']) ? 1 : 0,
                (int) ($data['network_size'] ?? 8),
                $data['noise_probability'] ?? null,
                $data['false_lead_probability'] ?? null,
                $data['notes'] ?? null,
                (string) $data['payload_json'],
                isset($data['tags_json']) ? (string) $data['tags_json'] : null,
                $data['updated_by'] ?? null,
                $id,
                $tenantId,
            ]
        );

        return true;
    }

    public function softDelete(int $id, int $tenantId): bool
    {
        $this->db->query(
            'UPDATE sse_arma_models SET deleted_at = NOW() WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL',
            [$id, $tenantId]
        );

        return true;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function hydrate(array $row): array
    {
        $payload = [];
        $raw = (string) ($row['payload_json'] ?? '');
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }
        $tags = [];
        $tagsRaw = (string) ($row['tags_json'] ?? '');
        if ($tagsRaw !== '') {
            $decodedTags = json_decode($tagsRaw, true);
            if (is_array($decodedTags)) {
                $tags = array_values(array_filter(array_map('strval', $decodedTags)));
            }
        }

        $row['payload'] = $payload;
        $row['tags'] = $tags;
        $row['profile_label'] = self::PROFILE_LABELS[(string) ($row['profile_code'] ?? '')] ?? (string) ($row['profile_code'] ?? '');
        $row['complexity_label'] = self::COMPLEXITY_LABELS[(string) ($row['complexity_code'] ?? '')] ?? (string) ($row['complexity_code'] ?? '');
        $row['region_label'] = self::REGION_LABELS[(string) ($row['region_code'] ?? '')] ?? (string) ($row['region_code'] ?? '');
        $row['theme_label'] = self::THEME_LABELS[(string) ($row['theme_code'] ?? '')] ?? (string) ($row['theme_code'] ?? '');
        $row['status_label'] = self::STATUS_LABELS[(string) ($row['status'] ?? '')] ?? (string) ($row['status'] ?? '');

        return $row;
    }
}
