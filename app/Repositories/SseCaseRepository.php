<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class SseCaseRepository
{
    public const CLASS_INTERNAL = 'interne';
    public const CLASS_COMMAND = 'encadrement';
    public const CLASS_CONFIDENTIAL = 'confidentiel';
    public const CLASS_RESTRICTED = 'tres_restreint';

    /** @var array<string, string> */
    public const CLASSIFICATION_LABELS = [
        self::CLASS_INTERNAL => 'Diffusion interne',
        self::CLASS_COMMAND => 'Encadrement',
        self::CLASS_CONFIDENTIAL => 'Confidentiel',
        self::CLASS_RESTRICTED => 'Diffusion très restreinte',
    ];

    /** @var array<string, string> */
    public const STATUS_LABELS = [
        'ouvert' => 'Ouvert',
        'en_cours' => 'En cours d’exploitation',
        'clos' => 'Clos',
        'archive' => 'Archivé',
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
        $path = base_path('bootstrap/atak_sse_portal_migration.php');
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

    public static function classificationLabel(string $code): string
    {
        return self::CLASSIFICATION_LABELS[$code] ?? 'Encadrement';
    }

    public static function statusLabel(string $code): string
    {
        return self::STATUS_LABELS[$code] ?? $code;
    }

    public static function normalizeClassification(string $raw): string
    {
        $s = strtolower(trim($raw));

        return isset(self::CLASSIFICATION_LABELS[$s]) ? $s : self::CLASS_COMMAND;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $tenantId = (int) ($data['tenant_id'] ?? 0);
        $ref = trim((string) ($data['reference_code'] ?? ''));
        if ($ref === '') {
            $ref = $this->nextReference($tenantId);
        }

        return (int) $this->db->insert(
            'INSERT INTO sse_cases (
                tenant_id, context_id, reference_code, title, summary, classification, status,
                unlock_code_hash, created_by
            ) VALUES (
                :tenant_id, :context_id, :reference_code, :title, :summary, :classification, :status,
                :unlock_code_hash, :created_by
            )',
            [
                'tenant_id' => $tenantId,
                'context_id' => (int) ($data['context_id'] ?? 1),
                'reference_code' => $ref,
                'title' => trim((string) ($data['title'] ?? 'Dossier sans titre')),
                'summary' => $this->nullIfEmpty($data['summary'] ?? null),
                'classification' => self::normalizeClassification((string) ($data['classification'] ?? self::CLASS_COMMAND)),
                'status' => $this->normalizeStatus((string) ($data['status'] ?? 'ouvert')),
                'unlock_code_hash' => $data['unlock_code_hash'] ?? null,
                'created_by' => isset($data['created_by']) ? (int) $data['created_by'] : null,
            ]
        );
    }

    /**
     * Volumétrie par dossier : personnes rattachées, notes, pièces.
     * Trois agrégats plutôt qu'une requête par dossier et par table.
     *
     * @param list<int> $caseIds
     * @return array<int, array{persons: int, notes: int, evidence: int}>
     */
    public function countsForCases(array $caseIds, int $tenantId): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $caseIds),
            static fn (int $i): bool => $i > 0
        )));
        if ($ids === []) {
            return [];
        }
        $in = implode(',', $ids);

        $out = [];
        foreach ($ids as $id) {
            $out[$id] = ['persons' => 0, 'notes' => 0, 'evidence' => 0];
        }

        $sources = [
            'persons' => 'sse_case_persons',
            'notes' => 'sse_case_notes',
            'evidence' => 'sse_case_evidence',
        ];
        foreach ($sources as $key => $table) {
            try {
                $rows = $this->db->fetchAll(
                    "SELECT case_id, COUNT(*) AS c FROM {$table}
                     WHERE tenant_id = :t AND case_id IN ({$in}) GROUP BY case_id",
                    ['t' => $tenantId]
                );
            } catch (\Throwable) {
                continue;
            }
            foreach ($rows as $row) {
                $cid = (int) ($row['case_id'] ?? 0);
                if (isset($out[$cid])) {
                    $out[$cid][$key] = (int) ($row['c'] ?? 0);
                }
            }
        }

        return $out;
    }

    /**
     * Dossier par référence saisie sur le terrain (terminal SEEK).
     * La casse et les espaces sont tolérés : l'opérateur tape sous contrainte.
     *
     * @return array<string, mixed>|null
     */
    public function findByReferenceCode(int $tenantId, string $reference): ?array
    {
        $reference = strtoupper(trim($reference));
        if ($reference === '') {
            return null;
        }

        return $this->db->fetchOne(
            'SELECT * FROM sse_cases WHERE tenant_id = :t AND UPPER(reference_code) = :r LIMIT 1',
            ['t' => $tenantId, 'r' => $reference]
        );
    }

    public function nextReference(int $tenantId): string
    {
        $year = date('Y');
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS c FROM sse_cases WHERE tenant_id = :t AND reference_code LIKE :p',
            ['t' => $tenantId, 'p' => 'SSE-' . $year . '-%']
        );
        $n = (int) ($row['c'] ?? 0) + 1;

        return sprintf('SSE-%s-%04d', $year, $n);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id, int $tenantId): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM sse_cases WHERE id = :id AND tenant_id = :t LIMIT 1',
            ['id' => $id, 't' => $tenantId]
        );

        return $row ? $this->hydrate($row) : null;
    }

    /**
     * @param list<int>|null $scopeIds null = tous
     * @return list<array<string, mixed>>
     */
    public function listForTenant(int $tenantId, ?array $scopeIds = null, array $filters = []): array
    {
        $where = ['tenant_id = :t'];
        $params = ['t' => $tenantId];
        if ($scopeIds !== null) {
            if ($scopeIds === []) {
                return [];
            }
            $ids = array_values(array_filter(array_map('intval', $scopeIds), static fn (int $i): bool => $i > 0));
            if ($ids === []) {
                return [];
            }
            $where[] = 'id IN (' . implode(',', $ids) . ')';
        }
        if (!empty($filters['status'])) {
            $where[] = 'status = :status';
            $params['status'] = $this->normalizeStatus((string) $filters['status']);
        }
        if (!empty($filters['classification'])) {
            $where[] = 'classification = :class';
            $params['class'] = self::normalizeClassification((string) $filters['classification']);
        }
        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $where[] = '(reference_code LIKE :search OR title LIKE :search OR summary LIKE :search)';
            $params['search'] = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
        }
        $rows = $this->db->fetchAll(
            'SELECT * FROM sse_cases WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT 200',
            $params
        );
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->hydrate($row);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, int $tenantId, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id, 't' => $tenantId];
        foreach (['title', 'summary', 'classification', 'status'] as $k) {
            if (!array_key_exists($k, $data)) {
                continue;
            }
            if ($k === 'classification') {
                $fields[] = 'classification = :classification';
                $params['classification'] = self::normalizeClassification((string) $data['classification']);
            } elseif ($k === 'status') {
                $fields[] = 'status = :status';
                $params['status'] = $this->normalizeStatus((string) $data['status']);
                if ($params['status'] === 'clos' || $params['status'] === 'archive') {
                    $fields[] = 'closed_at = COALESCE(closed_at, NOW())';
                }
            } elseif ($k === 'summary') {
                $fields[] = 'summary = :summary';
                $params['summary'] = $this->nullIfEmpty($data['summary']);
            } else {
                $fields[] = 'title = :title';
                $params['title'] = trim((string) $data['title']);
            }
        }
        if (array_key_exists('unlock_code_hash', $data)) {
            $fields[] = 'unlock_code_hash = :unlock_code_hash';
            $params['unlock_code_hash'] = $data['unlock_code_hash'];
        }
        if ($fields === []) {
            return false;
        }

        return $this->db->execute(
            'UPDATE sse_cases SET ' . implode(', ', $fields) . ' WHERE id = :id AND tenant_id = :t',
            $params
        ) > 0;
    }

    public function linkPerson(int $caseId, int $personId, int $tenantId, ?int $userId, ?string $note = null): bool
    {
        try {
            $this->db->insert(
                'INSERT INTO sse_case_persons (case_id, person_id, tenant_id, linked_by, note)
                 VALUES (:c, :p, :t, :u, :n)',
                [
                    'c' => $caseId,
                    'p' => $personId,
                    't' => $tenantId,
                    'u' => $userId,
                    'n' => $this->nullIfEmpty($note),
                ]
            );

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listLinkedPersonIds(int $caseId, int $tenantId): array
    {
        return $this->db->fetchAll(
            'SELECT person_id, note, created_at FROM sse_case_persons WHERE case_id = :c AND tenant_id = :t ORDER BY id ASC',
            ['c' => $caseId, 't' => $tenantId]
        );
    }

    public function addNote(int $caseId, int $tenantId, string $body, string $classification, ?int $userId, ?string $label): int
    {
        return (int) $this->db->insert(
            'INSERT INTO sse_case_notes (case_id, tenant_id, body, classification, author_user_id, author_label)
             VALUES (:c, :t, :b, :cl, :u, :l)',
            [
                'c' => $caseId,
                't' => $tenantId,
                'b' => trim($body),
                'cl' => self::normalizeClassification($classification),
                'u' => $userId,
                'l' => $this->nullIfEmpty($label),
            ]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listNotes(int $caseId, int $tenantId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM sse_case_notes WHERE case_id = :c AND tenant_id = :t ORDER BY id DESC',
            ['c' => $caseId, 't' => $tenantId]
        );
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => (int) $row['id'],
                'body' => (string) $row['body'],
                'classification' => (string) $row['classification'],
                'classification_label' => self::classificationLabel((string) $row['classification']),
                'author_label' => $row['author_label'] ?? null,
                'created_at' => $row['created_at'] ?? null,
            ];
        }

        return $out;
    }

    public function addEvidence(int $caseId, int $tenantId, array $data): int
    {
        return (int) $this->db->insert(
            'INSERT INTO sse_case_evidence (case_id, tenant_id, label, caption, image_path, person_id, author_label)
             VALUES (:c, :t, :label, :caption, :image_path, :person_id, :author_label)',
            [
                'c' => $caseId,
                't' => $tenantId,
                'label' => trim((string) ($data['label'] ?? 'Preuve')),
                'caption' => $this->nullIfEmpty($data['caption'] ?? null),
                'image_path' => $this->nullIfEmpty($data['image_path'] ?? null),
                'person_id' => isset($data['person_id']) ? (int) $data['person_id'] : null,
                'author_label' => $this->nullIfEmpty($data['author_label'] ?? null),
            ]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listEvidence(int $caseId, int $tenantId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM sse_case_evidence WHERE case_id = :c AND tenant_id = :t ORDER BY id DESC',
            ['c' => $caseId, 't' => $tenantId]
        );
        $out = [];
        foreach ($rows as $row) {
            $path = (string) ($row['image_path'] ?? '');
            $out[] = [
                'id' => (int) $row['id'],
                'label' => (string) ($row['label'] ?? ''),
                'caption' => $row['caption'] ?? null,
                'image_path' => $path !== '' ? $path : null,
                'url' => $path !== '' ? '/' . ltrim($path, '/') : null,
                'author_label' => $row['author_label'] ?? null,
                'created_at' => $row['created_at'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $class = self::normalizeClassification((string) ($row['classification'] ?? self::CLASS_COMMAND));
        $status = $this->normalizeStatus((string) ($row['status'] ?? 'ouvert'));

        return [
            'id' => (int) ($row['id'] ?? 0),
            'tenant_id' => (int) ($row['tenant_id'] ?? 0),
            'reference_code' => (string) ($row['reference_code'] ?? ''),
            'title' => (string) ($row['title'] ?? ''),
            'summary' => $row['summary'] ?? null,
            'classification' => $class,
            'classification_label' => self::classificationLabel($class),
            'status' => $status,
            'status_label' => self::statusLabel($status),
            'has_unlock_code' => !empty($row['unlock_code_hash']),
            'created_by' => isset($row['created_by']) ? (int) $row['created_by'] : null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
            'closed_at' => $row['closed_at'] ?? null,
        ];
    }

    private function normalizeStatus(string $raw): string
    {
        $s = strtolower(trim($raw));

        return isset(self::STATUS_LABELS[$s]) ? $s : 'ouvert';
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
