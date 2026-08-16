<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SseIntelCycleCatalog;

/**
 * Persistance cycle de renseignement LOT 4.
 */
final class SseIntelCycleRepository
{
    public function __construct(private ?Database $db = null)
    {
        $this->db ??= Database::getInstance();
    }

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    // ── Requirements ───────────────────────────────────────────────────────

    /**
     * @param array<string,mixed> $filters
     * @return list<array<string,mixed>>
     */
    public function listRequirements(int $tenantId, array $filters = []): array
    {
        $where = ['tenant_id = :t'];
        $params = ['t' => $tenantId];
        if (!empty($filters['case_id'])) {
            $where[] = 'case_id = :c';
            $params['c'] = (int) $filters['case_id'];
        }
        if (!empty($filters['req_type']) && isset(SseIntelCycleCatalog::REQUIREMENT_TYPES[(string) $filters['req_type']])) {
            $where[] = 'req_type = :rt';
            $params['rt'] = (string) $filters['req_type'];
        }
        if (!empty($filters['status']) && isset(SseIntelCycleCatalog::REQUIREMENT_STATUSES[(string) $filters['status']])) {
            $where[] = 'status = :st';
            $params['st'] = (string) $filters['status'];
        }
        $limit = max(1, min(200, (int) ($filters['limit'] ?? 80)));

        try {
            $rows = $this->db->fetchAll(
                'SELECT * FROM sse_intel_requirements WHERE ' . implode(' AND ', $where)
                . ' ORDER BY FIELD(priority, \'critique\',\'prioritaire\',\'normale\',\'basse\'), id DESC'
                . ' LIMIT ' . $limit,
                $params
            );
        } catch (\Throwable) {
            return [];
        }

        return array_map([$this, 'hydrateRequirement'], $rows);
    }

    /** @return array<string,mixed>|null */
    public function findRequirement(int $tenantId, int $id): ?array
    {
        try {
            $row = $this->db->fetchOne(
                'SELECT * FROM sse_intel_requirements WHERE id = :id AND tenant_id = :t',
                ['id' => $id, 't' => $tenantId]
            );
        } catch (\Throwable) {
            return null;
        }

        return $row ? $this->hydrateRequirement($row) : null;
    }

    /**
     * @param array<string,mixed> $data
     * @return array{ok:bool,id?:int,error?:string}
     */
    public function createRequirement(int $tenantId, array $data): array
    {
        $title = trim((string) ($data['title'] ?? ''));
        $question = trim((string) ($data['question'] ?? ''));
        if ($title === '' || $question === '') {
            return ['ok' => false, 'error' => 'Le titre et la question sont obligatoires.'];
        }
        $type = strtoupper(trim((string) ($data['req_type'] ?? 'PIR')));
        if (!isset(SseIntelCycleCatalog::REQUIREMENT_TYPES[$type])) {
            $type = 'PIR';
        }
        $priority = (string) ($data['priority'] ?? 'normale');
        if (!isset(SseIntelCycleCatalog::PRIORITIES[$priority])) {
            $priority = 'normale';
        }
        $status = (string) ($data['status'] ?? 'ouvert');
        if (!isset(SseIntelCycleCatalog::REQUIREMENT_STATUSES[$status])) {
            $status = 'ouvert';
        }

        try {
            $id = (int) $this->db->insert(
                'INSERT INTO sse_intel_requirements (
                    uuid, tenant_id, context_id, case_id, req_type, reference_code, title, question,
                    priority, status, coverage_pct, linked_hypothesis, confirmation_criterion,
                    assignee_label, due_at, author_label, created_by,
                    pos_x, pos_y, visible_on_atak
                ) VALUES (
                    :uuid, :t, :ctx, :c, :rt, :ref, :title, :q,
                    :prio, :st, :cov, :hyp, :crit,
                    :assignee, :due, :author, :uid,
                    :px, :py, :vis
                )',
                [
                    'uuid' => $this->uuid(),
                    't' => $tenantId,
                    'ctx' => (int) ($data['context_id'] ?? 1),
                    'c' => !empty($data['case_id']) ? (int) $data['case_id'] : null,
                    'rt' => $type,
                    'ref' => $this->nullIfEmpty($data['reference_code'] ?? null),
                    'title' => mb_substr($title, 0, 220),
                    'q' => $question,
                    'prio' => $priority,
                    'st' => $status,
                    'cov' => max(0, min(100, (int) ($data['coverage_pct'] ?? 0))),
                    'hyp' => $this->nullIfEmpty($data['linked_hypothesis'] ?? null),
                    'crit' => $this->nullIfEmpty($data['confirmation_criterion'] ?? null),
                    'assignee' => $this->nullIfEmpty($data['assignee_label'] ?? null),
                    'due' => $this->nullableDate($data['due_at'] ?? null),
                    'author' => $this->nullIfEmpty($data['author_label'] ?? null),
                    'uid' => ((int) ($data['created_by'] ?? 0)) ?: null,
                    'px' => $this->nullableFloat($data['pos_x'] ?? null),
                    'py' => $this->nullableFloat($data['pos_y'] ?? null),
                    'vis' => array_key_exists('visible_on_atak', $data)
                        ? (!empty($data['visible_on_atak']) ? 1 : 0)
                        : 1,
                ]
            );

            return ['ok' => true, 'id' => $id];
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'Enregistrement de l’exigence impossible.'];
        }
    }

    /**
     * @param array<string,mixed> $data
     */
    public function updateRequirement(int $tenantId, int $id, array $data): bool
    {
        $sets = [];
        $params = ['id' => $id, 't' => $tenantId];
        foreach (['title', 'question', 'assignee_label', 'confirmation_criterion', 'linked_hypothesis', 'reference_code'] as $col) {
            if (array_key_exists($col, $data)) {
                $sets[] = "{$col} = :{$col}";
                $params[$col] = is_string($data[$col]) ? trim($data[$col]) : $data[$col];
                if ($params[$col] === '') {
                    $params[$col] = null;
                }
            }
        }
        if (isset($data['priority']) && isset(SseIntelCycleCatalog::PRIORITIES[(string) $data['priority']])) {
            $sets[] = 'priority = :priority';
            $params['priority'] = (string) $data['priority'];
        }
        if (isset($data['status']) && isset(SseIntelCycleCatalog::REQUIREMENT_STATUSES[(string) $data['status']])) {
            $sets[] = 'status = :status';
            $params['status'] = (string) $data['status'];
            if (in_array($params['status'], ['satisfait', 'abandonne'], true)) {
                $sets[] = 'satisfied_at = COALESCE(satisfied_at, NOW())';
            }
        }
        if (array_key_exists('coverage_pct', $data)) {
            $sets[] = 'coverage_pct = :coverage_pct';
            $params['coverage_pct'] = max(0, min(100, (int) $data['coverage_pct']));
        }
        if (array_key_exists('due_at', $data)) {
            $sets[] = 'due_at = :due_at';
            $params['due_at'] = $this->nullableDate($data['due_at']);
        }
        if (array_key_exists('pos_x', $data)) {
            $sets[] = 'pos_x = :pos_x';
            $params['pos_x'] = $this->nullableFloat($data['pos_x']);
        }
        if (array_key_exists('pos_y', $data)) {
            $sets[] = 'pos_y = :pos_y';
            $params['pos_y'] = $this->nullableFloat($data['pos_y']);
        }
        if (array_key_exists('visible_on_atak', $data)) {
            $sets[] = 'visible_on_atak = :visible_on_atak';
            $params['visible_on_atak'] = !empty($data['visible_on_atak']) ? 1 : 0;
        }
        if ($sets === []) {
            return false;
        }
        try {
            return $this->db->execute(
                'UPDATE sse_intel_requirements SET ' . implode(', ', $sets)
                . ' WHERE id = :id AND tenant_id = :t',
                $params
            ) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    // ── Taskings ───────────────────────────────────────────────────────────

    /**
     * @param array<string,mixed> $filters
     * @return list<array<string,mixed>>
     */
    public function listTaskings(int $tenantId, array $filters = []): array
    {
        $where = ['tenant_id = :t'];
        $params = ['t' => $tenantId];
        if (!empty($filters['case_id'])) {
            $where[] = 'case_id = :c';
            $params['c'] = (int) $filters['case_id'];
        }
        if (!empty($filters['requirement_id'])) {
            $where[] = 'requirement_id = :r';
            $params['r'] = (int) $filters['requirement_id'];
        }
        if (!empty($filters['status']) && isset(SseIntelCycleCatalog::TASKING_STATUSES[(string) $filters['status']])) {
            $where[] = 'status = :st';
            $params['st'] = (string) $filters['status'];
        }
        $limit = max(1, min(200, (int) ($filters['limit'] ?? 80)));

        try {
            $rows = $this->db->fetchAll(
                'SELECT * FROM sse_intel_taskings WHERE ' . implode(' AND ', $where)
                . ' ORDER BY id DESC LIMIT ' . $limit,
                $params
            );
        } catch (\Throwable) {
            return [];
        }

        return array_map([$this, 'hydrateTasking'], $rows);
    }

    /** @return array<string,mixed>|null */
    public function findTasking(int $tenantId, int $id): ?array
    {
        try {
            $row = $this->db->fetchOne(
                'SELECT * FROM sse_intel_taskings WHERE id = :id AND tenant_id = :t',
                ['id' => $id, 't' => $tenantId]
            );
        } catch (\Throwable) {
            return null;
        }

        return $row ? $this->hydrateTasking($row) : null;
    }

    /**
     * @param array<string,mixed> $data
     * @return array{ok:bool,id?:int,error?:string}
     */
    public function createTasking(int $tenantId, array $data): array
    {
        $reqId = (int) ($data['requirement_id'] ?? 0);
        $title = trim((string) ($data['title'] ?? ''));
        $instruction = trim((string) ($data['instruction'] ?? ''));
        if ($reqId < 1 || $title === '' || $instruction === '') {
            return ['ok' => false, 'error' => 'L’exigence, le titre et la consigne sont obligatoires.'];
        }
        $priority = (string) ($data['priority'] ?? 'normale');
        if (!isset(SseIntelCycleCatalog::PRIORITIES[$priority])) {
            $priority = 'normale';
        }
        $status = (string) ($data['status'] ?? 'brouillon');
        if (!isset(SseIntelCycleCatalog::TASKING_STATUSES[$status])) {
            $status = 'brouillon';
        }

        try {
            $id = (int) $this->db->insert(
                'INSERT INTO sse_intel_taskings (
                    uuid, tenant_id, requirement_id, case_id, title, instruction,
                    tasked_unit, tasked_callsign, priority, status, due_at,
                    author_label, created_by, pos_x, pos_y, visible_on_atak
                ) VALUES (
                    :uuid, :t, :r, :c, :title, :instr,
                    :unit, :cs, :prio, :st, :due,
                    :author, :uid, :px, :py, :vis
                )',
                [
                    'uuid' => $this->uuid(),
                    't' => $tenantId,
                    'r' => $reqId,
                    'c' => !empty($data['case_id']) ? (int) $data['case_id'] : null,
                    'title' => mb_substr($title, 0, 220),
                    'instr' => $instruction,
                    'unit' => $this->nullIfEmpty($data['tasked_unit'] ?? null),
                    'cs' => $this->nullIfEmpty($data['tasked_callsign'] ?? null),
                    'prio' => $priority,
                    'st' => $status,
                    'due' => $this->nullableDateTime($data['due_at'] ?? null),
                    'author' => $this->nullIfEmpty($data['author_label'] ?? null),
                    'uid' => ((int) ($data['created_by'] ?? 0)) ?: null,
                    'px' => $this->nullableFloat($data['pos_x'] ?? null),
                    'py' => $this->nullableFloat($data['pos_y'] ?? null),
                    'vis' => array_key_exists('visible_on_atak', $data)
                        ? (!empty($data['visible_on_atak']) ? 1 : 0)
                        : 1,
                ]
            );

            return ['ok' => true, 'id' => $id];
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'Enregistrement de l’ordre de collecte impossible.'];
        }
    }

    /**
     * @param array<string,mixed> $data
     */
    public function updateTasking(int $tenantId, int $id, array $data): bool
    {
        $sets = [];
        $params = ['id' => $id, 't' => $tenantId];
        foreach (['title', 'instruction', 'tasked_unit', 'tasked_callsign', 'result_summary'] as $col) {
            if (array_key_exists($col, $data)) {
                $sets[] = "{$col} = :{$col}";
                $v = is_string($data[$col]) ? trim($data[$col]) : $data[$col];
                $params[$col] = ($v === '' ? null : $v);
            }
        }
        if (isset($data['priority']) && isset(SseIntelCycleCatalog::PRIORITIES[(string) $data['priority']])) {
            $sets[] = 'priority = :priority';
            $params['priority'] = (string) $data['priority'];
        }
        if (isset($data['status']) && isset(SseIntelCycleCatalog::TASKING_STATUSES[(string) $data['status']])) {
            $sets[] = 'status = :status';
            $params['status'] = (string) $data['status'];
            if (in_array($params['status'], ['remis', 'clos'], true)) {
                $sets[] = 'completed_at = COALESCE(completed_at, NOW())';
            }
        }
        if (array_key_exists('due_at', $data)) {
            $sets[] = 'due_at = :due_at';
            $params['due_at'] = $this->nullableDateTime($data['due_at']);
        }
        if (array_key_exists('pos_x', $data)) {
            $sets[] = 'pos_x = :pos_x';
            $params['pos_x'] = $this->nullableFloat($data['pos_x']);
        }
        if (array_key_exists('pos_y', $data)) {
            $sets[] = 'pos_y = :pos_y';
            $params['pos_y'] = $this->nullableFloat($data['pos_y']);
        }
        if (array_key_exists('visible_on_atak', $data)) {
            $sets[] = 'visible_on_atak = :visible_on_atak';
            $params['visible_on_atak'] = !empty($data['visible_on_atak']) ? 1 : 0;
        }
        if ($sets === []) {
            return false;
        }
        try {
            return $this->db->execute(
                'UPDATE sse_intel_taskings SET ' . implode(', ', $sets)
                . ' WHERE id = :id AND tenant_id = :t',
                $params
            ) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    // ── Products ───────────────────────────────────────────────────────────

    /**
     * @param array<string,mixed> $filters
     * @return list<array<string,mixed>>
     */
    public function listProducts(int $tenantId, array $filters = []): array
    {
        $where = ['tenant_id = :t'];
        $params = ['t' => $tenantId];
        if (!empty($filters['case_id'])) {
            $where[] = 'case_id = :c';
            $params['c'] = (int) $filters['case_id'];
        }
        if (!empty($filters['status']) && isset(SseIntelCycleCatalog::PRODUCT_STATUSES[(string) $filters['status']])) {
            $where[] = 'status = :st';
            $params['st'] = (string) $filters['status'];
        }
        $limit = max(1, min(100, (int) ($filters['limit'] ?? 40)));

        try {
            $rows = $this->db->fetchAll(
                'SELECT * FROM sse_intel_products WHERE ' . implode(' AND ', $where)
                . ' ORDER BY id DESC LIMIT ' . $limit,
                $params
            );
        } catch (\Throwable) {
            return [];
        }

        return array_map([$this, 'hydrateProduct'], $rows);
    }

    /** @return array<string,mixed>|null */
    public function findProduct(int $tenantId, int $id): ?array
    {
        try {
            $row = $this->db->fetchOne(
                'SELECT * FROM sse_intel_products WHERE id = :id AND tenant_id = :t',
                ['id' => $id, 't' => $tenantId]
            );
        } catch (\Throwable) {
            return null;
        }

        return $row ? $this->hydrateProduct($row) : null;
    }

    /**
     * @param array<string,mixed> $data
     * @return array{ok:bool,id?:int,error?:string}
     */
    public function createProduct(int $tenantId, array $data): array
    {
        $caseId = (int) ($data['case_id'] ?? 0);
        $title = trim((string) ($data['title'] ?? ''));
        $body = (string) ($data['body_text'] ?? '');
        if ($caseId < 1 || $title === '' || trim($body) === '') {
            return ['ok' => false, 'error' => 'Le dossier, le titre et le contenu sont obligatoires.'];
        }
        $type = strtoupper(trim((string) ($data['product_type'] ?? 'INITIAL')));
        if (!isset(SseIntelCycleCatalog::PRODUCT_TYPES[$type])) {
            $type = 'INITIAL';
        }
        $release = (string) ($data['release_level'] ?? 'interne');
        if (!isset(SseIntelCycleCatalog::RELEASE_LEVELS[$release])) {
            $release = 'interne';
        }
        $status = (string) ($data['status'] ?? 'brouillon');
        if (!isset(SseIntelCycleCatalog::PRODUCT_STATUSES[$status])) {
            $status = 'brouillon';
        }

        try {
            $id = (int) $this->db->insert(
                'INSERT INTO sse_intel_products (
                    uuid, tenant_id, case_id, requirement_id, product_type, title, body_text,
                    classification, release_level, status, sanitised, author_label, created_by
                ) VALUES (
                    :uuid, :t, :c, :r, :pt, :title, :body,
                    :cls, :rel, :st, :san, :author, :uid
                )',
                [
                    'uuid' => $this->uuid(),
                    't' => $tenantId,
                    'c' => $caseId,
                    'r' => !empty($data['requirement_id']) ? (int) $data['requirement_id'] : null,
                    'pt' => $type,
                    'title' => mb_substr($title, 0, 220),
                    'body' => $body,
                    'cls' => (string) ($data['classification'] ?? 'encadrement'),
                    'rel' => $release,
                    'st' => $status,
                    'san' => !empty($data['sanitised']) ? 1 : 0,
                    'author' => $this->nullIfEmpty($data['author_label'] ?? null),
                    'uid' => ((int) ($data['created_by'] ?? 0)) ?: null,
                ]
            );

            return ['ok' => true, 'id' => $id];
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'Enregistrement du produit impossible.'];
        }
    }

    /**
     * @param array<string,mixed> $data
     */
    public function updateProduct(int $tenantId, int $id, array $data): bool
    {
        $sets = [];
        $params = ['id' => $id, 't' => $tenantId];
        foreach (['title', 'body_text', 'classification', 'validated_by_label'] as $col) {
            if (array_key_exists($col, $data)) {
                $sets[] = "{$col} = :{$col}";
                $params[$col] = $data[$col];
            }
        }
        if (isset($data['release_level']) && isset(SseIntelCycleCatalog::RELEASE_LEVELS[(string) $data['release_level']])) {
            $sets[] = 'release_level = :release_level';
            $params['release_level'] = (string) $data['release_level'];
        }
        if (isset($data['status']) && isset(SseIntelCycleCatalog::PRODUCT_STATUSES[(string) $data['status']])) {
            $sets[] = 'status = :status';
            $params['status'] = (string) $data['status'];
        }
        if (array_key_exists('sanitised', $data)) {
            $sets[] = 'sanitised = :sanitised';
            $params['sanitised'] = !empty($data['sanitised']) ? 1 : 0;
            if (!empty($data['sanitised'])) {
                $sets[] = 'sanitised_at = COALESCE(sanitised_at, NOW())';
            }
        }
        if (!empty($data['mark_validated'])) {
            $sets[] = 'validated_at = NOW()';
        }
        if (!empty($data['mark_diffused'])) {
            $sets[] = 'diffused_at = NOW()';
        }
        if ($sets === []) {
            return false;
        }
        try {
            return $this->db->execute(
                'UPDATE sse_intel_products SET ' . implode(', ', $sets)
                . ' WHERE id = :id AND tenant_id = :t',
                $params
            ) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param list<array{label:string,role?:string}> $recipients
     */
    public function replaceRecipients(int $tenantId, int $productId, array $recipients): void
    {
        try {
            $this->db->execute(
                'DELETE FROM sse_intel_product_recipients WHERE tenant_id = :t AND product_id = :p',
                ['t' => $tenantId, 'p' => $productId]
            );
            foreach ($recipients as $r) {
                $label = trim((string) ($r['label'] ?? $r['recipient_label'] ?? ''));
                if ($label === '') {
                    continue;
                }
                $this->db->insert(
                    'INSERT INTO sse_intel_product_recipients
                        (tenant_id, product_id, recipient_label, recipient_role, ack_status, note)
                     VALUES (:t, :p, :l, :role, :ack, :note)',
                    [
                        't' => $tenantId,
                        'p' => $productId,
                        'l' => mb_substr($label, 0, 160),
                        'role' => $this->nullIfEmpty($r['role'] ?? $r['recipient_role'] ?? null),
                        'ack' => 'envoye',
                        'note' => $this->nullIfEmpty($r['note'] ?? null),
                    ]
                );
            }
        } catch (\Throwable) {
        }
    }

    /** @return list<array<string,mixed>> */
    public function listRecipients(int $tenantId, int $productId): array
    {
        try {
            $rows = $this->db->fetchAll(
                'SELECT * FROM sse_intel_product_recipients
                 WHERE tenant_id = :t AND product_id = :p ORDER BY id ASC',
                ['t' => $tenantId, 'p' => $productId]
            );
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $ack = (string) ($row['ack_status'] ?? 'envoye');
            $out[] = [
                'id' => (int) ($row['id'] ?? 0),
                'recipient_label' => (string) ($row['recipient_label'] ?? ''),
                'recipient_role' => $row['recipient_role'] ?? null,
                'ack_status' => $ack,
                'ack_status_label' => SseIntelCycleCatalog::statusLabel('ack', $ack),
                'ack_at' => $row['ack_at'] ?? null,
                'note' => $row['note'] ?? null,
            ];
        }

        return $out;
    }

    /** @return array{requirements:int,taskings_open:int,products_pending:int} */
    public function countsForTenant(int $tenantId, ?int $caseId = null): array
    {
        $out = ['requirements' => 0, 'taskings_open' => 0, 'products_pending' => 0];
        $caseSql = $caseId !== null ? ' AND case_id = ' . (int) $caseId : '';
        try {
            $out['requirements'] = (int) $this->db->fetchOne(
                "SELECT COUNT(*) AS c FROM sse_intel_requirements
                 WHERE tenant_id = :t AND status IN ('ouvert','en_cours','partiellement_couvert'){$caseSql}",
                ['t' => $tenantId]
            )['c'] ?? 0;
            $out['taskings_open'] = (int) $this->db->fetchOne(
                "SELECT COUNT(*) AS c FROM sse_intel_taskings
                 WHERE tenant_id = :t AND status IN ('brouillon','emis','accepte','en_cours'){$caseSql}",
                ['t' => $tenantId]
            )['c'] ?? 0;
            $out['products_pending'] = (int) $this->db->fetchOne(
                "SELECT COUNT(*) AS c FROM sse_intel_products
                 WHERE tenant_id = :t AND status IN ('brouillon','en_relecture','valide','sanitise'){$caseSql}",
                ['t' => $tenantId]
            )['c'] ?? 0;
        } catch (\Throwable) {
        }

        return $out;
    }

    /** @param array<string,mixed> $row */
    private function hydrateRequirement(array $row): array
    {
        $type = strtoupper((string) ($row['req_type'] ?? 'PIR'));
        $status = (string) ($row['status'] ?? 'ouvert');
        $prio = (string) ($row['priority'] ?? 'normale');

        return [
            'id' => (int) ($row['id'] ?? 0),
            'uuid' => (string) ($row['uuid'] ?? ''),
            'tenant_id' => (int) ($row['tenant_id'] ?? 0),
            'context_id' => (int) ($row['context_id'] ?? 1),
            'case_id' => isset($row['case_id']) ? (int) $row['case_id'] : null,
            'req_type' => $type,
            'req_type_label' => SseIntelCycleCatalog::requirementTypeLabel($type),
            'reference_code' => $row['reference_code'] ?? null,
            'title' => (string) ($row['title'] ?? ''),
            'question' => (string) ($row['question'] ?? ''),
            'priority' => $prio,
            'priority_label' => SseIntelCycleCatalog::PRIORITIES[$prio] ?? $prio,
            'status' => $status,
            'status_label' => SseIntelCycleCatalog::statusLabel('requirement', $status),
            'coverage_pct' => (int) ($row['coverage_pct'] ?? 0),
            'linked_hypothesis' => $row['linked_hypothesis'] ?? null,
            'confirmation_criterion' => $row['confirmation_criterion'] ?? null,
            'assignee_label' => $row['assignee_label'] ?? null,
            'due_at' => $row['due_at'] ?? null,
            'satisfied_at' => $row['satisfied_at'] ?? null,
            'author_label' => $row['author_label'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
            'pos_x' => isset($row['pos_x']) && $row['pos_x'] !== null ? (float) $row['pos_x'] : null,
            'pos_y' => isset($row['pos_y']) && $row['pos_y'] !== null ? (float) $row['pos_y'] : null,
            'visible_on_atak' => !array_key_exists('visible_on_atak', $row) || !empty($row['visible_on_atak']),
        ];
    }

    /** @param array<string,mixed> $row */
    private function hydrateTasking(array $row): array
    {
        $status = (string) ($row['status'] ?? 'brouillon');
        $prio = (string) ($row['priority'] ?? 'normale');

        return [
            'id' => (int) ($row['id'] ?? 0),
            'uuid' => (string) ($row['uuid'] ?? ''),
            'tenant_id' => (int) ($row['tenant_id'] ?? 0),
            'requirement_id' => (int) ($row['requirement_id'] ?? 0),
            'case_id' => isset($row['case_id']) ? (int) $row['case_id'] : null,
            'title' => (string) ($row['title'] ?? ''),
            'instruction' => (string) ($row['instruction'] ?? ''),
            'tasked_unit' => $row['tasked_unit'] ?? null,
            'tasked_callsign' => $row['tasked_callsign'] ?? null,
            'priority' => $prio,
            'priority_label' => SseIntelCycleCatalog::PRIORITIES[$prio] ?? $prio,
            'status' => $status,
            'status_label' => SseIntelCycleCatalog::statusLabel('tasking', $status),
            'due_at' => $row['due_at'] ?? null,
            'completed_at' => $row['completed_at'] ?? null,
            'result_summary' => $row['result_summary'] ?? null,
            'author_label' => $row['author_label'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'pos_x' => isset($row['pos_x']) && $row['pos_x'] !== null ? (float) $row['pos_x'] : null,
            'pos_y' => isset($row['pos_y']) && $row['pos_y'] !== null ? (float) $row['pos_y'] : null,
            'visible_on_atak' => !array_key_exists('visible_on_atak', $row) || !empty($row['visible_on_atak']),
        ];
    }

    /** @param array<string,mixed> $row */
    private function hydrateProduct(array $row): array
    {
        $type = strtoupper((string) ($row['product_type'] ?? 'INITIAL'));
        $status = (string) ($row['status'] ?? 'brouillon');
        $release = (string) ($row['release_level'] ?? 'interne');

        return [
            'id' => (int) ($row['id'] ?? 0),
            'uuid' => (string) ($row['uuid'] ?? ''),
            'tenant_id' => (int) ($row['tenant_id'] ?? 0),
            'case_id' => (int) ($row['case_id'] ?? 0),
            'requirement_id' => isset($row['requirement_id']) ? (int) $row['requirement_id'] : null,
            'product_type' => $type,
            'product_type_label' => SseIntelCycleCatalog::PRODUCT_TYPES[$type] ?? $type,
            'title' => (string) ($row['title'] ?? ''),
            'body_text' => (string) ($row['body_text'] ?? ''),
            'classification' => (string) ($row['classification'] ?? 'encadrement'),
            'release_level' => $release,
            'release_level_label' => SseIntelCycleCatalog::RELEASE_LEVELS[$release] ?? $release,
            'status' => $status,
            'status_label' => SseIntelCycleCatalog::statusLabel('product', $status),
            'sanitised' => !empty($row['sanitised']),
            'sanitised_at' => $row['sanitised_at'] ?? null,
            'validated_at' => $row['validated_at'] ?? null,
            'validated_by_label' => $row['validated_by_label'] ?? null,
            'diffused_at' => $row['diffused_at'] ?? null,
            'author_label' => $row['author_label'] ?? null,
            'created_at' => $row['created_at'] ?? null,
        ];
    }

    private function nullIfEmpty(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }

    private function nullableDate(mixed $v): ?string
    {
        $s = $this->nullIfEmpty($v);
        if ($s === null) {
            return null;
        }

        return preg_match('/^\d{4}-\d{2}-\d{2}/', $s) ? substr($s, 0, 10) : null;
    }

    private function nullableDateTime(mixed $v): ?string
    {
        $s = $this->nullIfEmpty($v);
        if ($s === null) {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s)) {
            return strlen($s) >= 16 ? substr($s, 0, 19) : substr($s, 0, 10) . ' 00:00:00';
        }

        return null;
    }

    private function nullableFloat(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (!is_numeric($v)) {
            return null;
        }

        return (float) $v;
    }
}
