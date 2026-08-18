<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SilentSchemaMigration;

/**
 * File de rapprochements / signaux produits par le moteur SSE.
 * Rien n’y est « vrai » tant qu’un analyste n’a pas validé.
 */
final class SseSuggestionQueueRepository
{
    public const CONFIDENCE = [
        'possible' => 'Possible',
        'probable' => 'Probable',
        'confirme_candidat' => 'Candidat à confirmation',
    ];

    public const STATUSES = [
        'pending' => 'À traiter',
        'accepted' => 'Validé',
        'rejected' => 'Rejeté',
        'deferred' => 'Reporté',
    ];

    public const KINDS = [
        'name_near' => 'Nom / alias proche',
        'watchlist' => 'Liste de surveillance',
        'co_presence' => 'Co-présence',
        'duplicate_bio' => 'Doublon biométrique',
        'same_site' => 'Même site',
        'cross_case_person' => 'Personne multi-dossiers',
        'case_similarity' => 'Similarité de dossiers',
        'intel_pre_sse' => 'Intel terrain pré-SSE',
        'single_source' => 'Source unique',
        'stale_intel' => 'Renseignement vieilli',
        'convergence' => 'Convergence',
        'contradiction' => 'Contradiction',
        'merge_suggest' => 'Fusion suggérée',
        'reopen_suggest' => 'Réouverture suggérée',
    ];

    public const SIGNAL_TYPES = [
        'anomaly' => 'Anomalie',
        'completeness' => 'Complétude',
        'gap_auto' => 'Lacune générée',
        'aging' => 'Vieillissement',
        'single_source' => 'Source unique',
        'circular' => 'Circular reporting',
        'behavior' => 'Changement de comportement',
        'cluster' => 'Cluster',
        'digest' => 'Digest',
        'classification' => 'Classification',
    ];

    public function __construct(private ?Database $db = null)
    {
        $this->db ??= Database::getInstance();
        SilentSchemaMigration::run(base_path('bootstrap/atak_sse_engine_migration.php'));
    }

    /**
     * Upsert idempotent d’une suggestion (ne touche pas un statut déjà décidé).
     *
     * @param array<string,mixed> $data
     */
    public function upsertSuggestion(int $tenantId, array $data): ?int
    {
        $kind = (string) ($data['kind'] ?? '');
        $leftType = (string) ($data['left_type'] ?? '');
        $rightType = (string) ($data['right_type'] ?? '');
        $leftId = (int) ($data['left_id'] ?? 0);
        $rightId = (int) ($data['right_id'] ?? 0);
        if ($kind === '' || $leftType === '' || $rightType === '' || $leftId < 1 || $rightId < 1) {
            return null;
        }
        if ($leftType === $rightType && $leftId === $rightId) {
            return null;
        }

        $confidence = (string) ($data['confidence'] ?? 'possible');
        if (!isset(self::CONFIDENCE[$confidence])) {
            $confidence = 'possible';
        }
        $score = max(0, min(100, (int) ($data['score'] ?? 0)));
        $evidence = $data['evidence'] ?? null;
        $evidenceJson = is_array($evidence) ? json_encode($evidence, JSON_UNESCAPED_UNICODE) : (is_string($evidence) ? $evidence : null);

        try {
            $existing = $this->db->fetchOne(
                'SELECT id, status FROM sse_suggestion_queue
                  WHERE tenant_id = :t AND kind = :k AND left_type = :lt AND left_id = :li
                    AND right_type = :rt AND right_id = :ri LIMIT 1',
                [
                    't' => $tenantId,
                    'k' => $kind,
                    'lt' => $leftType,
                    'li' => $leftId,
                    'rt' => $rightType,
                    'ri' => $rightId,
                ]
            );
            if ($existing && in_array((string) ($existing['status'] ?? ''), ['accepted', 'rejected'], true)) {
                return (int) $existing['id'];
            }

            $this->db->execute(
                'INSERT INTO sse_suggestion_queue
                    (tenant_id, case_id, related_case_id, left_type, left_id, right_type, right_id,
                     kind, score, confidence, status, title, reason, evidence_json, rule_key, run_id)
                 VALUES
                    (:t, :c, :rc, :lt, :li, :rt, :ri, :k, :sc, :conf, \'pending\', :title, :reason, :ev, :rule, :run)
                 ON DUPLICATE KEY UPDATE
                    score = GREATEST(sse_suggestion_queue.score, VALUES(score)),
                    confidence = IF(sse_suggestion_queue.status = \'pending\', VALUES(confidence), sse_suggestion_queue.confidence),
                    title = IF(sse_suggestion_queue.status = \'pending\', VALUES(title), sse_suggestion_queue.title),
                    reason = IF(sse_suggestion_queue.status = \'pending\', VALUES(reason), sse_suggestion_queue.reason),
                    evidence_json = IF(sse_suggestion_queue.status = \'pending\', VALUES(evidence_json), sse_suggestion_queue.evidence_json),
                    case_id = COALESCE(VALUES(case_id), sse_suggestion_queue.case_id),
                    related_case_id = COALESCE(VALUES(related_case_id), sse_suggestion_queue.related_case_id),
                    run_id = VALUES(run_id),
                    updated_at = NOW()',
                [
                    't' => $tenantId,
                    'c' => ((int) ($data['case_id'] ?? 0)) ?: null,
                    'rc' => ((int) ($data['related_case_id'] ?? 0)) ?: null,
                    'lt' => $leftType,
                    'li' => $leftId,
                    'rt' => $rightType,
                    'ri' => $rightId,
                    'k' => $kind,
                    'sc' => $score,
                    'conf' => $confidence,
                    'title' => mb_substr(trim((string) ($data['title'] ?? self::KINDS[$kind] ?? $kind)), 0, 220),
                    'reason' => mb_substr(trim((string) ($data['reason'] ?? '')), 0, 500),
                    'ev' => $evidenceJson,
                    'rule' => mb_substr((string) ($data['rule_key'] ?? $kind), 0, 64),
                    'run' => ($data['run_id'] ?? null) ?: null,
                ]
            );

            if ($existing) {
                return (int) $existing['id'];
            }

            return (int) Database::getPdo()->lastInsertId();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<string,mixed> $filters
     * @return list<array<string,mixed>>
     */
    public function listSuggestions(int $tenantId, array $filters = []): array
    {
        $where = ['tenant_id = :t'];
        $params = ['t' => $tenantId];
        if (!empty($filters['case_id'])) {
            $where[] = '(case_id = :c OR related_case_id = :c2)';
            $params['c'] = (int) $filters['case_id'];
            $params['c2'] = (int) $filters['case_id'];
        }
        if (!empty($filters['statuses']) && is_array($filters['statuses'])) {
            $statuses = array_values(array_filter(
                array_map('strval', $filters['statuses']),
                static fn (string $s): bool => isset(self::STATUSES[$s])
            ));
            if ($statuses !== []) {
                $placeholders = [];
                foreach ($statuses as $i => $st) {
                    $key = 'st' . $i;
                    $placeholders[] = ':' . $key;
                    $params[$key] = $st;
                }
                $where[] = 'status IN (' . implode(',', $placeholders) . ')';
            }
        } elseif (!empty($filters['status'])) {
            $where[] = 'status = :st';
            $params['st'] = (string) $filters['status'];
        } elseif (empty($filters['all'])) {
            $where[] = 'status = \'pending\'';
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(title LIKE :q1 OR reason LIKE :q2 OR kind LIKE :q3 OR COALESCE(evidence_json, \'\') LIKE :q4 OR COALESCE(rule_key, \'\') LIKE :q5)';
            $like = '%' . $q . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
            $params['q4'] = $like;
            $params['q5'] = $like;
        }
        $limit = max(1, min(200, (int) ($filters['limit'] ?? 80)));
        $history = !empty($filters['history']);
        $order = $history
            ? 'ORDER BY COALESCE(decided_at, updated_at) DESC, id DESC'
            : 'ORDER BY FIELD(confidence, \'confirme_candidat\',\'probable\',\'possible\'), score DESC, id DESC';

        try {
            $rows = $this->db->fetchAll(
                'SELECT * FROM sse_suggestion_queue WHERE ' . implode(' AND ', $where)
                . ' ' . $order
                . ' LIMIT ' . $limit,
                $params
            );
        } catch (\Throwable) {
            return [];
        }

        return array_map([$this, 'hydrateSuggestion'], $rows);
    }

    public function countDecided(int $tenantId, ?int $caseId = null): int
    {
        try {
            if ($caseId !== null && $caseId > 0) {
                $row = $this->db->fetchOne(
                    'SELECT COUNT(*) AS n FROM sse_suggestion_queue
                      WHERE tenant_id = :t
                        AND status IN (\'accepted\', \'rejected\', \'deferred\')
                        AND (case_id = :c OR related_case_id = :c2)',
                    ['t' => $tenantId, 'c' => $caseId, 'c2' => $caseId]
                );
            } else {
                $row = $this->db->fetchOne(
                    'SELECT COUNT(*) AS n FROM sse_suggestion_queue
                      WHERE tenant_id = :t AND status IN (\'accepted\', \'rejected\', \'deferred\')',
                    ['t' => $tenantId]
                );
            }

            return (int) ($row['n'] ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    public function findSuggestion(int $tenantId, int $id): ?array
    {
        try {
            $row = $this->db->fetchOne(
                'SELECT * FROM sse_suggestion_queue WHERE id = :id AND tenant_id = :t LIMIT 1',
                ['id' => $id, 't' => $tenantId]
            );
        } catch (\Throwable) {
            return null;
        }

        return $row ? $this->hydrateSuggestion($row) : null;
    }

    public function decide(int $tenantId, int $id, string $status, ?string $authorLabel = null, ?int $userId = null): bool
    {
        if (!isset(self::STATUSES[$status]) || $status === 'pending') {
            return false;
        }
        try {
            $this->db->execute(
                'UPDATE sse_suggestion_queue
                    SET status = :st, decided_at = NOW(), author_label = :a, decided_by = :u
                  WHERE id = :id AND tenant_id = :t AND status = \'pending\'',
                [
                    'st' => $status,
                    'a' => $authorLabel,
                    'u' => $userId ?: null,
                    'id' => $id,
                    't' => $tenantId,
                ]
            );

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function countPending(int $tenantId, ?int $caseId = null): int
    {
        try {
            if ($caseId !== null && $caseId > 0) {
                $row = $this->db->fetchOne(
                    'SELECT COUNT(*) AS n FROM sse_suggestion_queue
                      WHERE tenant_id = :t AND status = \'pending\' AND (case_id = :c OR related_case_id = :c2)',
                    ['t' => $tenantId, 'c' => $caseId, 'c2' => $caseId]
                );
            } else {
                $row = $this->db->fetchOne(
                    'SELECT COUNT(*) AS n FROM sse_suggestion_queue WHERE tenant_id = :t AND status = \'pending\'',
                    ['t' => $tenantId]
                );
            }

            return (int) ($row['n'] ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    public function countOpenSignals(int $tenantId, ?int $caseId = null): int
    {
        try {
            if ($caseId !== null && $caseId > 0) {
                $row = $this->db->fetchOne(
                    'SELECT COUNT(*) AS n FROM sse_engine_signals
                      WHERE tenant_id = :t AND status = \'open\' AND case_id = :c',
                    ['t' => $tenantId, 'c' => $caseId]
                );
            } else {
                $row = $this->db->fetchOne(
                    'SELECT COUNT(*) AS n FROM sse_engine_signals WHERE tenant_id = :t AND status = \'open\'',
                    ['t' => $tenantId]
                );
            }

            return (int) ($row['n'] ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @param array<string,mixed> $data
     */
    public function addSignal(int $tenantId, array $data): ?int
    {
        $type = (string) ($data['signal_type'] ?? 'anomaly');
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            return null;
        }
        $payload = $data['payload'] ?? null;
        try {
            $this->db->execute(
                'INSERT INTO sse_engine_signals
                    (tenant_id, case_id, signal_type, severity, title, detail, payload_json, status, rule_key, run_id)
                 VALUES (:t, :c, :type, :sev, :title, :detail, :payload, \'open\', :rule, :run)',
                [
                    't' => $tenantId,
                    'c' => ((int) ($data['case_id'] ?? 0)) ?: null,
                    'type' => $type,
                    'sev' => (string) ($data['severity'] ?? 'info'),
                    'title' => mb_substr($title, 0, 220),
                    'detail' => ($data['detail'] ?? null) ?: null,
                    'payload' => is_array($payload) ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
                    'rule' => mb_substr((string) ($data['rule_key'] ?? $type), 0, 64),
                    'run' => ($data['run_id'] ?? null) ?: null,
                ]
            );

            return (int) Database::getPdo()->lastInsertId();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array{case_id?:int|null,q?:string}|int|null $caseIdOrFilters
     * @return list<array<string,mixed>>
     */
    public function listSignals(int $tenantId, array|int|null $caseIdOrFilters = null, int $limit = 40): array
    {
        $caseId = null;
        $q = '';
        if (is_array($caseIdOrFilters)) {
            $caseId = isset($caseIdOrFilters['case_id']) ? (int) $caseIdOrFilters['case_id'] : null;
            $q = trim((string) ($caseIdOrFilters['q'] ?? ''));
            if (isset($caseIdOrFilters['limit'])) {
                $limit = (int) $caseIdOrFilters['limit'];
            }
        } elseif ($caseIdOrFilters !== null) {
            $caseId = (int) $caseIdOrFilters;
        }

        $where = ['tenant_id = :t', 'status = \'open\''];
        $params = ['t' => $tenantId];
        if ($caseId !== null && $caseId > 0) {
            $where[] = '(case_id = :c OR case_id IS NULL)';
            $params['c'] = $caseId;
        }
        if ($q !== '') {
            $where[] = '(title LIKE :q1 OR COALESCE(detail, \'\') LIKE :q2 OR signal_type LIKE :q3)';
            $like = '%' . $q . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
        }

        try {
            $rows = $this->db->fetchAll(
                'SELECT * FROM sse_engine_signals WHERE ' . implode(' AND ', $where)
                . ' ORDER BY FIELD(severity, \'critical\',\'high\',\'medium\',\'info\'), id DESC'
                . ' LIMIT ' . max(1, min(100, $limit)),
                $params
            );
        } catch (\Throwable) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $row['signal_type_label'] = self::SIGNAL_TYPES[(string) ($row['signal_type'] ?? '')] ?? (string) ($row['signal_type'] ?? '');
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $breakdown
     * @param array<string,mixed>|null $digest
     */
    public function saveCompleteness(int $tenantId, int $caseId, int $score, array $breakdown, ?array $digest = null): void
    {
        try {
            $this->db->execute(
                'INSERT INTO sse_case_completeness (case_id, tenant_id, score, breakdown_json, digest_json, computed_at)
                 VALUES (:c, :t, :s, :b, :d, NOW())
                 ON DUPLICATE KEY UPDATE score = VALUES(score), breakdown_json = VALUES(breakdown_json),
                    digest_json = VALUES(digest_json), computed_at = NOW()',
                [
                    'c' => $caseId,
                    't' => $tenantId,
                    's' => max(0, min(100, $score)),
                    'b' => json_encode($breakdown, JSON_UNESCAPED_UNICODE),
                    'd' => $digest !== null ? json_encode($digest, JSON_UNESCAPED_UNICODE) : null,
                ]
            );
        } catch (\Throwable) {
        }
    }

    public function getCompleteness(int $tenantId, int $caseId): ?array
    {
        try {
            $row = $this->db->fetchOne(
                'SELECT * FROM sse_case_completeness WHERE case_id = :c AND tenant_id = :t LIMIT 1',
                ['c' => $caseId, 't' => $tenantId]
            );
        } catch (\Throwable) {
            return null;
        }
        if (!$row) {
            return null;
        }
        $row['breakdown'] = json_decode((string) ($row['breakdown_json'] ?? ''), true) ?: [];
        $row['digest'] = json_decode((string) ($row['digest_json'] ?? ''), true) ?: [];

        return $row;
    }

    /** @param array<string,mixed> $row */
    private function hydrateSuggestion(array $row): array
    {
        $row['kind_label'] = self::KINDS[(string) ($row['kind'] ?? '')] ?? (string) ($row['kind'] ?? '');
        $row['confidence_label'] = self::CONFIDENCE[(string) ($row['confidence'] ?? '')] ?? '';
        $row['status_label'] = self::STATUSES[(string) ($row['status'] ?? '')] ?? '';
        $row['evidence'] = json_decode((string) ($row['evidence_json'] ?? ''), true) ?: [];

        return $row;
    }
}
