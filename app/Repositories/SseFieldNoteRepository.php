<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SilentSchemaMigration;
use App\Support\SseFieldNoteCatalog;

/**
 * Fiches de renseignement simplifiées et leurs pièces jointes.
 */
final class SseFieldNoteRepository
{
    public function __construct(private ?Database $db = null)
    {
        $this->db ??= Database::getInstance();
        SilentSchemaMigration::run(base_path('bootstrap/atak_sse_field_notes_migration.php'));
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function listForTenant(int $tenantId, array $filters = []): array
    {
        $where = ['n.tenant_id = :tenant'];
        $params = ['tenant' => $tenantId];

        $status = strtolower(trim((string) ($filters['status'] ?? '')));
        if ($status !== '' && isset(SseFieldNoteCatalog::STATUSES[$status])) {
            $where[] = 'n.status = :status';
            $params['status'] = $status;
        }

        $kind = strtoupper(trim((string) ($filters['note_kind'] ?? '')));
        if ($kind !== '' && isset(SseFieldNoteCatalog::KINDS[$kind])) {
            $where[] = 'n.note_kind = :kind';
            $params['kind'] = $kind;
        }

        $urgency = strtolower(trim((string) ($filters['urgency'] ?? '')));
        if ($urgency !== '' && isset(SseFieldNoteCatalog::URGENCIES[$urgency])) {
            $where[] = 'n.urgency = :urgency';
            $params['urgency'] = $urgency;
        }

        $theme = strtolower(trim((string) ($filters['theme'] ?? '')));
        if ($theme !== '' && isset(SseFieldNoteCatalog::THEMES[$theme])) {
            $where[] = 'n.themes LIKE :theme';
            $params['theme'] = '%"' . $theme . '"%';
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $where[] = '(n.reference_code LIKE :q_ref OR n.body LIKE :q_body OR n.place_label LIKE :q_place OR n.author_label LIKE :q_author)';
            $params['q_ref'] = $like;
            $params['q_body'] = $like;
            $params['q_place'] = $like;
            $params['q_author'] = $like;
        }

        $since = trim((string) ($filters['since'] ?? ''));
        if ($since !== '') {
            $where[] = 'n.observed_at >= :since';
            $params['since'] = $since;
        }

        $author = trim((string) ($filters['author_steam_id'] ?? ''));
        if ($author !== '') {
            $where[] = 'n.author_steam_id = :author_steam';
            $params['author_steam'] = $author;
        }

        $contextId = (int) ($filters['context_id'] ?? 0);
        if ($contextId > 0) {
            $where[] = 'n.context_id = :context';
            $params['context'] = $contextId;
        }

        $limit = (int) ($filters['limit'] ?? 100);
        $limit = max(1, min(300, $limit));
        $offset = max(0, (int) ($filters['offset'] ?? 0));

        $rows = $this->db->fetchAll(
            'SELECT n.*, (
                 SELECT COUNT(*) FROM sse_field_note_attachments a
                 WHERE a.note_id = n.id AND a.tenant_id = n.tenant_id
             ) AS attachment_count
             FROM sse_field_notes n
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY n.observed_at DESC, n.id DESC
             LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params
        );

        return array_map(fn (array $row): array => $this->hydrate($row), $rows);
    }

    /** @return array<string, mixed>|null */
    public function findForTenant(int $tenantId, int $id): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM sse_field_notes WHERE id = :id AND tenant_id = :tenant LIMIT 1',
            ['id' => $id, 'tenant' => $tenantId]
        );

        return $row !== null ? $this->hydrate($row) : null;
    }

    /** @return array<string, mixed>|null */
    public function findByIdempotencyKey(int $tenantId, string $key): ?array
    {
        $key = trim($key);
        if ($key === '') {
            return null;
        }
        $row = $this->db->fetchOne(
            'SELECT * FROM sse_field_notes WHERE tenant_id = :tenant AND idempotency_key = :key LIMIT 1',
            ['tenant' => $tenantId, 'key' => $key]
        );

        return $row !== null ? $this->hydrate($row) : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(int $tenantId, array $data): int
    {
        $fields = [
            'tenant_id', 'context_id', 'reference_code', 'note_kind', 'themes', 'body', 'observed_at',
            'place_label', 'grid_reference', 'pos_x', 'pos_y', 'pos_z', 'lat', 'lng', 'urgency',
            'classification', 'source_reliability', 'info_credibility', 'status', 'origin',
            'author_label', 'author_user_id', 'author_steam_id', 'author_unit', 'case_id',
            'interest_case_id', 'idempotency_key',
        ];

        $values = [
            'tenant_id' => $tenantId,
            'reference_code' => $this->nextReferenceCode($tenantId),
        ];
        foreach ($fields as $field) {
            if (!array_key_exists($field, $values)) {
                $values[$field] = $data[$field] ?? null;
            }
        }
        $values['context_id'] = max(1, (int) ($values['context_id'] ?? 1));
        $values['note_kind'] = SseFieldNoteCatalog::normalizeKind($values['note_kind']);
        $values['themes'] = json_encode(
            SseFieldNoteCatalog::normalizeThemes($values['themes']),
            JSON_UNESCAPED_UNICODE
        ) ?: '[]';
        $values['body'] = SseFieldNoteCatalog::normalizeBody($values['body']);
        $values['observed_at'] = $this->normalizeDateTime($values['observed_at']) ?? date('Y-m-d H:i:s');
        $values['urgency'] = SseFieldNoteCatalog::normalizeUrgency($values['urgency']);
        $values['status'] = SseFieldNoteCatalog::normalizeStatus($values['status']);
        $values['classification'] = trim((string) ($values['classification'] ?? '')) ?: 'interne';
        $values['source_reliability'] = strtoupper(substr(trim((string) ($values['source_reliability'] ?? 'C')), 0, 1)) ?: 'C';
        $values['info_credibility'] = max(1, min(6, (int) ($values['info_credibility'] ?? 3)));
        $values['origin'] = isset(SseFieldNoteCatalog::ORIGINS[(string) ($values['origin'] ?? '')])
            ? (string) $values['origin']
            : 'web';
        foreach (['author_user_id', 'case_id', 'interest_case_id'] as $intField) {
            $values[$intField] = ((int) ($values[$intField] ?? 0)) > 0 ? (int) $values[$intField] : null;
        }
        foreach (['pos_x', 'pos_y', 'pos_z', 'lat', 'lng'] as $numField) {
            $values[$numField] = is_numeric($values[$numField] ?? null) ? (float) $values[$numField] : null;
        }
        foreach (['place_label', 'grid_reference', 'author_label', 'author_steam_id', 'author_unit', 'idempotency_key'] as $textField) {
            $text = trim((string) ($values[$textField] ?? ''));
            $values[$textField] = $text !== '' ? $text : null;
        }

        return (int) $this->db->insert(
            'INSERT INTO sse_field_notes (' . implode(',', $fields) . ')
             VALUES (:' . implode(',:', $fields) . ')',
            $values
        );
    }

    /**
     * Réécrit une fiche encore modifiable (brouillon ou transmise par son auteur).
     *
     * @param array<string, mixed> $data
     */
    public function update(int $tenantId, int $id, array $data): bool
    {
        $sets = [];
        $params = ['id' => $id, 'tenant' => $tenantId];

        if (array_key_exists('body', $data)) {
            $sets[] = 'body = :body';
            $params['body'] = SseFieldNoteCatalog::normalizeBody($data['body']);
        }
        if (array_key_exists('note_kind', $data)) {
            $sets[] = 'note_kind = :kind';
            $params['kind'] = SseFieldNoteCatalog::normalizeKind($data['note_kind']);
        }
        if (array_key_exists('themes', $data)) {
            $sets[] = 'themes = :themes';
            $params['themes'] = json_encode(
                SseFieldNoteCatalog::normalizeThemes($data['themes']),
                JSON_UNESCAPED_UNICODE
            ) ?: '[]';
        }
        if (array_key_exists('observed_at', $data)) {
            $sets[] = 'observed_at = :observed_at';
            $params['observed_at'] = $this->normalizeDateTime($data['observed_at']) ?? date('Y-m-d H:i:s');
        }
        if (array_key_exists('place_label', $data)) {
            $sets[] = 'place_label = :place';
            $params['place'] = trim((string) $data['place_label']) ?: null;
        }
        if (array_key_exists('urgency', $data)) {
            $sets[] = 'urgency = :urgency';
            $params['urgency'] = SseFieldNoteCatalog::normalizeUrgency($data['urgency']);
        }
        if (array_key_exists('status', $data)) {
            $sets[] = 'status = :status';
            $params['status'] = SseFieldNoteCatalog::normalizeStatus($data['status']);
        }

        if ($sets === []) {
            return false;
        }

        return $this->db->execute(
            'UPDATE sse_field_notes SET ' . implode(', ', $sets) . ' WHERE id = :id AND tenant_id = :tenant',
            $params
        ) >= 0;
    }

    public function updateTriage(
        int $tenantId,
        int $id,
        string $status,
        ?string $triageNote,
        ?int $triagedBy
    ): bool {
        return $this->db->execute(
            'UPDATE sse_field_notes
             SET status = :status, triage_note = :note, triaged_by = :by, triaged_at = UTC_TIMESTAMP()
             WHERE id = :id AND tenant_id = :tenant',
            [
                'status' => SseFieldNoteCatalog::normalizeStatus($status),
                'note' => $triageNote !== null && trim($triageNote) !== '' ? mb_substr(trim($triageNote), 0, 400) : null,
                'by' => $triagedBy !== null && $triagedBy > 0 ? $triagedBy : null,
                'id' => $id,
                'tenant' => $tenantId,
            ]
        ) >= 0;
    }

    public function attachToCase(int $tenantId, int $id, ?int $caseId, ?int $interestCaseId): bool
    {
        return $this->db->execute(
            'UPDATE sse_field_notes SET case_id = :case, interest_case_id = :interest
             WHERE id = :id AND tenant_id = :tenant',
            [
                'case' => $caseId !== null && $caseId > 0 ? $caseId : null,
                'interest' => $interestCaseId !== null && $interestCaseId > 0 ? $interestCaseId : null,
                'id' => $id,
                'tenant' => $tenantId,
            ]
        ) >= 0;
    }

    public function delete(int $tenantId, int $id): bool
    {
        return $this->db->execute(
            'DELETE FROM sse_field_notes WHERE id = :id AND tenant_id = :tenant',
            ['id' => $id, 'tenant' => $tenantId]
        ) > 0;
    }

    // ================= Pièces jointes =================

    /**
     * @param array<string, mixed> $data
     */
    public function addAttachment(int $tenantId, int $noteId, array $data): int
    {
        $fields = [
            'tenant_id', 'note_id', 'file_path', 'original_name', 'mime_type', 'byte_size',
            'kind', 'caption', 'grid_reference', 'pos_x', 'pos_y', 'pos_z', 'author_label',
        ];
        $values = ['tenant_id' => $tenantId, 'note_id' => $noteId];
        foreach ($fields as $field) {
            if (!array_key_exists($field, $values)) {
                $values[$field] = $data[$field] ?? null;
            }
        }
        $kind = strtolower(trim((string) ($values['kind'] ?? 'photo')));
        $values['kind'] = isset(SseFieldNoteCatalog::ATTACHMENT_KINDS[$kind]) ? $kind : 'photo';
        $values['byte_size'] = ((int) ($values['byte_size'] ?? 0)) > 0 ? (int) $values['byte_size'] : null;
        foreach (['pos_x', 'pos_y', 'pos_z'] as $numField) {
            $values[$numField] = is_numeric($values[$numField] ?? null) ? (float) $values[$numField] : null;
        }
        foreach (['original_name', 'mime_type', 'caption', 'grid_reference', 'author_label'] as $textField) {
            $text = trim((string) ($values[$textField] ?? ''));
            $values[$textField] = $text !== '' ? mb_substr($text, 0, 255) : null;
        }

        return (int) $this->db->insert(
            'INSERT INTO sse_field_note_attachments (' . implode(',', $fields) . ')
             VALUES (:' . implode(',:', $fields) . ')',
            $values
        );
    }

    /** @return list<array<string, mixed>> */
    public function listAttachments(int $tenantId, int $noteId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM sse_field_note_attachments
             WHERE tenant_id = :tenant AND note_id = :note
             ORDER BY id ASC',
            ['tenant' => $tenantId, 'note' => $noteId]
        );

        return array_map(static function (array $row): array {
            $path = (string) ($row['file_path'] ?? '');
            $row['id'] = (int) $row['id'];
            $row['note_id'] = (int) $row['note_id'];
            $row['byte_size'] = $row['byte_size'] !== null ? (int) $row['byte_size'] : null;
            $row['kind_label'] = SseFieldNoteCatalog::attachmentKindLabel((string) ($row['kind'] ?? 'photo'));
            $row['url'] = $path !== '' ? user_media_public_url($path) : null;
            $row['is_image'] = str_starts_with((string) ($row['mime_type'] ?? ''), 'image/');

            return $row;
        }, $rows);
    }

    /** @return array<string, mixed>|null */
    public function findAttachment(int $tenantId, int $noteId, int $attachmentId): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM sse_field_note_attachments
             WHERE id = :id AND note_id = :note AND tenant_id = :tenant LIMIT 1',
            ['id' => $attachmentId, 'note' => $noteId, 'tenant' => $tenantId]
        );
    }

    public function countAttachments(int $tenantId, int $noteId): int
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS total FROM sse_field_note_attachments
             WHERE tenant_id = :tenant AND note_id = :note',
            ['tenant' => $tenantId, 'note' => $noteId]
        );

        return (int) ($row['total'] ?? 0);
    }

    public function deleteAttachment(int $tenantId, int $noteId, int $attachmentId): bool
    {
        return $this->db->execute(
            'DELETE FROM sse_field_note_attachments
             WHERE id = :id AND note_id = :note AND tenant_id = :tenant',
            ['id' => $attachmentId, 'note' => $noteId, 'tenant' => $tenantId]
        ) > 0;
    }

    // ================= Agrégats =================

    /**
     * Compteurs du bandeau : file du jour, urgences, pièces jointes.
     *
     * @return array{total:int,today:int,immediate:int,untriaged:int}
     */
    public function counters(int $tenantId): array
    {
        $row = $this->db->fetchOne(
            'SELECT
                 COUNT(*) AS total,
                 SUM(CASE WHEN DATE(observed_at) = UTC_DATE() THEN 1 ELSE 0 END) AS today,
                 SUM(CASE WHEN urgency = \'immediate\' AND status IN (\'transmise\', \'prise_en_compte\') THEN 1 ELSE 0 END) AS immediate,
                 SUM(CASE WHEN status = \'transmise\' THEN 1 ELSE 0 END) AS untriaged
             FROM sse_field_notes WHERE tenant_id = :tenant',
            ['tenant' => $tenantId]
        );

        return [
            'total' => (int) ($row['total'] ?? 0),
            'today' => (int) ($row['today'] ?? 0),
            'immediate' => (int) ($row['immediate'] ?? 0),
            'untriaged' => (int) ($row['untriaged'] ?? 0),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function listForCase(int $tenantId, int $caseId, int $limit = 30): array
    {
        $limit = max(1, min(100, $limit));
        $rows = $this->db->fetchAll(
            'SELECT n.*, (
                 SELECT COUNT(*) FROM sse_field_note_attachments a
                 WHERE a.note_id = n.id AND a.tenant_id = n.tenant_id
             ) AS attachment_count
             FROM sse_field_notes n
             WHERE n.tenant_id = :tenant AND n.case_id = :case
             ORDER BY n.observed_at DESC, n.id DESC
             LIMIT ' . $limit,
            ['tenant' => $tenantId, 'case' => $caseId]
        );

        return array_map(fn (array $row): array => $this->hydrate($row), $rows);
    }

    private function nextReferenceCode(int $tenantId): string
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS total FROM sse_field_notes WHERE tenant_id = :tenant',
            ['tenant' => $tenantId]
        );
        $sequence = ((int) ($row['total'] ?? 0)) + 1;

        for ($attempt = 0; $attempt < 50; $attempt++) {
            $candidate = sprintf('FR-%s-%06d', date('Y'), $sequence + $attempt);
            $clash = $this->db->fetchOne(
                'SELECT 1 AS hit FROM sse_field_notes WHERE tenant_id = :tenant AND reference_code = :code LIMIT 1',
                ['tenant' => $tenantId, 'code' => $candidate]
            );
            if ($clash === null) {
                return $candidate;
            }
        }

        return sprintf('FR-%s-%s', date('Y'), strtoupper(substr(bin2hex(random_bytes(4)), 0, 6)));
    }

    private function normalizeDateTime(mixed $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }
        $raw = str_replace('T', ' ', $raw);
        $ts = strtotime($raw);
        if ($ts === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $ts);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $row['id'] = (int) ($row['id'] ?? 0);
        $row['tenant_id'] = (int) ($row['tenant_id'] ?? 0);
        $row['context_id'] = (int) ($row['context_id'] ?? 1);
        $row['case_id'] = ((int) ($row['case_id'] ?? 0)) > 0 ? (int) $row['case_id'] : null;
        $row['interest_case_id'] = ((int) ($row['interest_case_id'] ?? 0)) > 0 ? (int) $row['interest_case_id'] : null;
        $row['author_user_id'] = ((int) ($row['author_user_id'] ?? 0)) > 0 ? (int) $row['author_user_id'] : null;
        $row['info_credibility'] = (int) ($row['info_credibility'] ?? 3);
        $row['attachment_count'] = (int) ($row['attachment_count'] ?? 0);

        $row['themes'] = SseFieldNoteCatalog::normalizeThemes($row['themes'] ?? '[]');
        $row['theme_labels'] = array_map(
            static fn (string $code): string => SseFieldNoteCatalog::themeLabel($code),
            $row['themes']
        );
        $row['note_kind'] = SseFieldNoteCatalog::normalizeKind($row['note_kind'] ?? null);
        $row['note_kind_label'] = SseFieldNoteCatalog::kindLabel($row['note_kind']);
        $row['urgency'] = SseFieldNoteCatalog::normalizeUrgency($row['urgency'] ?? null);
        $row['urgency_label'] = SseFieldNoteCatalog::urgencyLabel($row['urgency']);
        $row['status'] = SseFieldNoteCatalog::normalizeStatus($row['status'] ?? null);
        $row['status_label'] = SseFieldNoteCatalog::statusLabel($row['status']);
        $row['origin_label'] = SseFieldNoteCatalog::originLabel((string) ($row['origin'] ?? 'web'));

        $body = (string) ($row['body'] ?? '');
        $row['body_length'] = mb_strlen($body);
        $row['excerpt'] = mb_strlen($body) > 180 ? mb_substr($body, 0, 180) . '…' : $body;

        $observed = (string) ($row['observed_at'] ?? '');
        $ts = $observed !== '' ? strtotime($observed) : false;
        $row['observed_date_label'] = $ts !== false ? date('d/m/Y', $ts) : '';
        $row['observed_time_label'] = $ts !== false ? date('H:i', $ts) : '';
        $row['observed_input_value'] = $ts !== false ? date('Y-m-d\TH:i', $ts) : '';

        foreach (['pos_x', 'pos_y', 'pos_z', 'lat', 'lng'] as $numField) {
            $row[$numField] = is_numeric($row[$numField] ?? null) ? (float) $row[$numField] : null;
        }
        $row['has_position'] = $row['grid_reference'] !== null
            || $row['pos_x'] !== null
            || $row['lat'] !== null;

        return $row;
    }
}
