<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SilentSchemaMigration;
use App\Support\SseAnalysisCatalog;

/**
 * Persistance des constats d’analyse SSE (LOT 6).
 */
final class SseAnalysisFindingRepository
{
    public function __construct(private ?Database $db = null)
    {
        $this->db ??= Database::getInstance();
        $this->ensureSchema();
    }

    private function ensureSchema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        SilentSchemaMigration::run(base_path('bootstrap/atak_sse_analysis_lot6_migration.php'));
        $done = true;
    }

    /**
     * @param array<string,mixed> $filters
     * @return list<array<string,mixed>>
     */
    public function listFindings(int $tenantId, array $filters = []): array
    {
        $where = ['tenant_id = :t'];
        $params = ['t' => $tenantId];
        if (!empty($filters['case_id'])) {
            $where[] = 'case_id = :c';
            $params['c'] = (int) $filters['case_id'];
        }
        if (!empty($filters['entity_uuid'])) {
            $where[] = 'entity_uuid = :eu';
            $params['eu'] = (string) $filters['entity_uuid'];
        }
        if (!empty($filters['finding_type'])) {
            $where[] = 'finding_type = :ft';
            $params['ft'] = (string) $filters['finding_type'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'status = :st';
            $params['st'] = (string) $filters['status'];
        } elseif (empty($filters['all'])) {
            $where[] = "status IN ('ouvert','retenu')";
        }
        $limit = max(1, min(200, (int) ($filters['limit'] ?? 60)));

        try {
            $rows = $this->db->fetchAll(
                'SELECT * FROM sse_analysis_findings WHERE ' . implode(' AND ', $where)
                . ' ORDER BY FIELD(severity, \'critique\',\'haute\',\'normale\',\'basse\'), updated_at DESC, id DESC'
                . ' LIMIT ' . $limit,
                $params
            );
        } catch (\Throwable) {
            return [];
        }

        return array_map([$this, 'hydrate'], $rows);
    }

    public function findById(int $tenantId, int $id): ?array
    {
        try {
            $row = $this->db->fetchOne(
                'SELECT * FROM sse_analysis_findings WHERE tenant_id = :t AND id = :id LIMIT 1',
                ['t' => $tenantId, 'id' => $id]
            );
        } catch (\Throwable) {
            return null;
        }

        return $row ? $this->hydrate($row) : null;
    }

    /**
     * @param array<string,mixed> $data
     * @return array{ok:bool,id?:int,uuid?:string,error?:string}
     */
    public function createFinding(int $tenantId, array $data): array
    {
        $type = (string) ($data['finding_type'] ?? 'anomaly');
        if (!isset(SseAnalysisCatalog::FINDING_TYPES[$type])) {
            $type = 'anomaly';
        }
        $severity = (string) ($data['severity'] ?? 'normale');
        if (!isset(SseAnalysisCatalog::SEVERITIES[$severity])) {
            $severity = 'normale';
        }
        $confidence = (string) ($data['confidence_label'] ?? 'PROBABLE');
        if (!isset(SseAnalysisCatalog::CONFIDENCE[$confidence])) {
            $confidence = 'PROBABLE';
        }
        $title = mb_substr(trim((string) ($data['title'] ?? '')), 0, 220);
        $explanation = trim((string) ($data['explanation'] ?? ''));
        if ($title === '' || $explanation === '') {
            return ['ok' => false, 'error' => 'Le titre et l’explication sont obligatoires.'];
        }

        $uuid = trim((string) ($data['uuid'] ?? ''));
        if ($uuid === '') {
            $uuid = SseEntityIndexRepository::newUuid();
        }

        $evidence = $data['evidence'] ?? $data['evidence_json'] ?? null;
        if (is_array($evidence)) {
            $evidence = json_encode($evidence, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        try {
            $id = (int) $this->db->insert(
                'INSERT INTO sse_analysis_findings (
                    uuid, tenant_id, context_id, case_id, entity_uuid, finding_type, severity, status,
                    confidence_label, title, explanation, evidence_json
                ) VALUES (
                    :uuid, :t, :ctx, :c, :eu, :ft, :sev, \'ouvert\', :conf, :title, :expl, :ev
                )',
                [
                    'uuid' => $uuid,
                    't' => $tenantId,
                    'ctx' => (int) ($data['context_id'] ?? 1),
                    'c' => isset($data['case_id']) && (int) $data['case_id'] > 0 ? (int) $data['case_id'] : null,
                    'eu' => $this->nullIfEmpty($data['entity_uuid'] ?? null),
                    'ft' => $type,
                    'sev' => $severity,
                    'conf' => $confidence,
                    'title' => $title,
                    'expl' => $explanation,
                    'ev' => is_string($evidence) ? $evidence : null,
                ]
            );
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Enregistrement impossible.'];
        }

        return ['ok' => true, 'id' => $id, 'uuid' => $uuid];
    }

    /**
     * Évite les doublons ouverts pour une même empreinte d’évidence.
     */
    public function findOpenByEvidenceFingerprint(int $tenantId, string $fingerprint): ?array
    {
        if ($fingerprint === '') {
            return null;
        }
        try {
            $rows = $this->db->fetchAll(
                "SELECT * FROM sse_analysis_findings
                  WHERE tenant_id = :t AND status = 'ouvert'
                  ORDER BY id DESC LIMIT 40",
                ['t' => $tenantId]
            );
        } catch (\Throwable) {
            return null;
        }
        foreach ($rows as $row) {
            $ev = (string) ($row['evidence_json'] ?? '');
            if ($ev !== '' && str_contains($ev, '"fingerprint":"' . $fingerprint . '"')) {
                return $this->hydrate($row);
            }
        }

        return null;
    }

    public function decide(int $tenantId, int $id, string $status, string $authorLabel = ''): bool
    {
        if (!isset(SseAnalysisCatalog::STATUSES[$status]) || $status === 'ouvert') {
            return false;
        }
        try {
            $this->db->execute(
                'UPDATE sse_analysis_findings
                    SET status = :st, decided_by_label = :lbl, decided_at = UTC_TIMESTAMP()
                  WHERE id = :id AND tenant_id = :t AND status = \'ouvert\'',
                [
                    'st' => $status,
                    'lbl' => mb_substr($authorLabel, 0, 160) ?: null,
                    'id' => $id,
                    't' => $tenantId,
                ]
            );

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array<string,mixed> $profile
     */
    public function savePolSnapshot(int $tenantId, ?int $caseId, ?string $entityUuid, int $windowDays, array $profile): void
    {
        try {
            $this->db->insert(
                'INSERT INTO sse_pol_snapshots (tenant_id, case_id, entity_uuid, window_days, profile_json, computed_at)
                 VALUES (:t, :c, :eu, :w, :p, UTC_TIMESTAMP())',
                [
                    't' => $tenantId,
                    'c' => $caseId !== null && $caseId > 0 ? $caseId : null,
                    'eu' => $this->nullIfEmpty($entityUuid),
                    'w' => max(1, min(90, $windowDays)),
                    'p' => json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
                ]
            );
        } catch (\Throwable) {
        }
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function hydrate(array $row): array
    {
        $type = (string) ($row['finding_type'] ?? 'anomaly');
        $status = (string) ($row['status'] ?? 'ouvert');
        $sev = (string) ($row['severity'] ?? 'normale');
        $conf = (string) ($row['confidence_label'] ?? 'PROBABLE');
        $evidence = null;
        if (!empty($row['evidence_json'])) {
            $decoded = json_decode((string) $row['evidence_json'], true);
            $evidence = is_array($decoded) ? $decoded : null;
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'uuid' => (string) ($row['uuid'] ?? ''),
            'tenant_id' => (int) ($row['tenant_id'] ?? 0),
            'case_id' => isset($row['case_id']) ? (int) $row['case_id'] : null,
            'entity_uuid' => $row['entity_uuid'] ?? null,
            'finding_type' => $type,
            'finding_type_label' => SseAnalysisCatalog::typeLabel($type),
            'severity' => $sev,
            'severity_label' => SseAnalysisCatalog::SEVERITIES[$sev] ?? $sev,
            'status' => $status,
            'status_label' => SseAnalysisCatalog::statusLabel($status),
            'confidence_label' => $conf,
            'confidence_label_fr' => SseAnalysisCatalog::confidenceLabel($conf),
            'title' => (string) ($row['title'] ?? ''),
            'explanation' => (string) ($row['explanation'] ?? ''),
            'evidence' => $evidence,
            'decided_by_label' => $row['decided_by_label'] ?? null,
            'decided_at' => $row['decided_at'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private function nullIfEmpty(mixed $v): ?string
    {
        $s = trim((string) ($v ?? ''));

        return $s === '' ? null : $s;
    }
}
