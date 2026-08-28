<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Database;
use App\Repositories\SseAuditLogRepository;
use App\Repositories\SseCaseRepository;
use App\Repositories\SseEntityIndexRepository;
use App\Repositories\SseIntelEventRepository;
use App\Repositories\SseInterestCaseRepository;
use App\Repositories\SsePersonRepository;
use App\Repositories\SseSiteRepository;
use App\Services\Sse\SseTransmissionDiscordService;

/**
 * Couche fondation LOT 1 : synchronisation index, événements normalisés, audit.
 */
final class SseIntelFoundationService
{
    public function __construct(
        private ?SseEntityIndexRepository $entities = null,
        private ?SseIntelEventRepository $events = null,
        private ?SseAuditLogRepository $audit = null,
        private ?SsePersonRepository $persons = null,
        private ?SseSiteRepository $sites = null,
        private ?SseCaseRepository $cases = null,
        private ?SseInterestCaseRepository $interest = null,
        private ?Database $db = null,
    ) {
        $this->entities ??= new SseEntityIndexRepository();
        $this->events ??= new SseIntelEventRepository();
        $this->audit ??= new SseAuditLogRepository();
        $this->persons ??= new SsePersonRepository();
        $this->sites ??= new SseSiteRepository();
        $this->cases ??= new SseCaseRepository();
        $this->interest ??= new SseInterestCaseRepository();
        $this->db ??= Database::getInstance();
    }

    /**
     * @param array<string, mixed> $person
     * @return array{id:int,uuid:string,created:bool}
     */
    public function syncPerson(int $tenantId, array $person): array
    {
        $id = (int) ($person['id'] ?? 0);
        $label = trim((string) ($person['display_name'] ?? ''));
        if ($label === '') {
            $label = trim(
                (string) ($person['first_name'] ?? '') . ' ' . (string) ($person['last_name'] ?? '')
            ) ?: (string) ($person['alias'] ?? ('Personne #' . $id));
        }
        $blob = implode(' ', array_filter([
            $label,
            (string) ($person['alias'] ?? ''),
            (string) ($person['affiliation'] ?? ''),
            (string) ($person['id_document_number'] ?? ''),
            (string) ($person['sse_uid'] ?? ''),
        ]));

        $tier = strtoupper(trim((string) ($person['identity_tier'] ?? '')));
        if ($tier === '' && !empty($person['biometrics_simulated'])) {
            $tier = 'DOCUMENTARY';
        }
        if ($tier === '') {
            $tier = 'DECLARED';
        }

        return $this->entities->upsert([
            'tenant_id' => $tenantId,
            'context_id' => (int) ($person['context_id'] ?? 1),
            'entity_type' => 'person',
            'source_table' => 'sse_persons',
            'source_id' => $id,
            'display_label' => $label,
            'reference_code' => $person['sse_uid'] ?? null,
            'status' => (string) ($person['status'] ?? ''),
            'identity_tier' => $tier,
            'source_reliability' => $person['source_reliability'] ?? 'C',
            'info_credibility' => $person['info_credibility'] ?? 3,
            'classification' => (string) ($person['classification'] ?? 'encadrement'),
            'search_blob' => $blob,
            'last_event_at' => $person['updated_at'] ?? $person['created_at'] ?? null,
        ]);
    }

    /**
     * @param array<string, mixed> $site
     * @return array{id:int,uuid:string,created:bool}
     */
    public function syncSite(int $tenantId, array $site): array
    {
        $id = (int) ($site['id'] ?? 0);
        $label = trim((string) ($site['name'] ?? $site['title'] ?? $site['display_name'] ?? ''))
            ?: ('Site #' . $id);
        $blob = implode(' ', array_filter([
            $label,
            (string) ($site['code'] ?? ''),
            (string) ($site['reference_code'] ?? ''),
            (string) ($site['location_text'] ?? ''),
        ]));

        return $this->entities->upsert([
            'tenant_id' => $tenantId,
            'context_id' => (int) ($site['context_id'] ?? 1),
            'entity_type' => 'site',
            'source_table' => 'sse_sites',
            'source_id' => $id,
            'display_label' => $label,
            'reference_code' => $site['code'] ?? $site['reference_code'] ?? null,
            'status' => (string) ($site['status'] ?? ''),
            'source_reliability' => $site['source_reliability'] ?? 'C',
            'info_credibility' => $site['info_credibility'] ?? 3,
            'classification' => (string) ($site['classification'] ?? 'encadrement'),
            'search_blob' => $blob,
            'last_event_at' => $site['updated_at'] ?? $site['created_at'] ?? null,
        ]);
    }

    /**
     * @param array<string, mixed> $case
     * @return array{id:int,uuid:string,created:bool}
     */
    public function syncCase(int $tenantId, array $case): array
    {
        $id = (int) ($case['id'] ?? 0);
        $ref = (string) ($case['reference_code'] ?? '');
        $title = trim((string) ($case['title'] ?? ''));
        $label = $ref !== '' ? ($ref . ($title !== '' ? ' — ' . $title : '')) : ($title ?: ('Dossier #' . $id));

        return $this->entities->upsert([
            'tenant_id' => $tenantId,
            'context_id' => (int) ($case['context_id'] ?? 1),
            'entity_type' => 'case',
            'source_table' => 'sse_cases',
            'source_id' => $id,
            'display_label' => $label,
            'reference_code' => $ref !== '' ? $ref : null,
            'status' => (string) ($case['lifecycle_status'] ?? $case['status'] ?? ''),
            'classification' => (string) ($case['classification'] ?? 'encadrement'),
            'source_reliability' => 'B',
            'info_credibility' => 2,
            'search_blob' => $label . ' ' . (string) ($case['summary'] ?? ''),
            'last_event_at' => $case['last_activity_at'] ?? $case['updated_at'] ?? null,
        ]);
    }

    /**
     * @param array<string, mixed> $row
     * @return array{id:int,uuid:string,created:bool}
     */
    public function syncInterestCase(int $tenantId, array $row): array
    {
        $id = (int) ($row['id'] ?? 0);
        $ref = (string) ($row['reference_code'] ?? '');
        $desig = trim((string) ($row['temporary_designation'] ?? $row['suspected_alias'] ?? ''));
        $label = $ref !== '' ? ($ref . ($desig !== '' ? ' — ' . $desig : '')) : ($desig ?: ('DI #' . $id));

        return $this->entities->upsert([
            'tenant_id' => $tenantId,
            'context_id' => (int) ($row['context_id'] ?? 1),
            'entity_type' => 'interest_case',
            'source_table' => 'sse_interest_cases',
            'source_id' => $id,
            'display_label' => $label,
            'reference_code' => $ref !== '' ? $ref : null,
            'status' => (string) ($row['status'] ?? ''),
            'identity_tier' => 'UNKNOWN',
            'classification' => (string) ($row['classification'] ?? 'encadrement'),
            'source_reliability' => 'D',
            'info_credibility' => 4,
            'search_blob' => $label . ' ' . (string) ($row['suspected_alias'] ?? ''),
            'last_event_at' => $row['updated_at'] ?? $row['created_at'] ?? null,
        ]);
    }

    /**
     * Enregistre un événement normalisé + met à jour l’index / activité dossier.
     *
     * @param array<string, mixed> $data
     * @return array{id:int,event_uuid:string,created:bool,row:?array<string,mixed>}
     */
    public function recordEvent(array $data): array
    {
        $result = $this->events->append($data);
        $tenantId = (int) ($data['tenant_id'] ?? 0);
        if ($tenantId > 0 && !empty($data['entity_uuid']) && $result['created']) {
            $this->entities->touchLastEvent(
                $tenantId,
                (string) $data['entity_uuid'],
                (string) ($data['event_time'] ?? '')
            );
        }
        if ($tenantId > 0 && !empty($data['case_id']) && $result['created']) {
            try {
                $this->db->execute(
                    'UPDATE sse_cases SET last_activity_at = COALESCE(:et, UTC_TIMESTAMP())
                     WHERE id = :id AND tenant_id = :t',
                    [
                        'id' => (int) $data['case_id'],
                        't' => $tenantId,
                        'et' => $data['event_time'] ?? null,
                    ]
                );
            } catch (\Throwable) {
            }
        }

        if ($tenantId > 0 && $result['created'] && is_array($result['row'] ?? null)) {
            $row = $result['row'];
            register_shutdown_function(static function () use ($tenantId, $row): void {
                try {
                    (new SseTransmissionDiscordService())->notifyNewTransmission($tenantId, $row);
                } catch (\Throwable) {
                }
            });
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function audit(array $data): int
    {
        return $this->audit->write($data);
    }

    /**
     * Backfill plafonné pour un tenant (persons / sites / cases / DI récents).
     *
     * @return array{persons:int,sites:int,cases:int,interest:int}
     */
    public function backfillTenant(int $tenantId, int $limit = 80): array
    {
        $stats = ['persons' => 0, 'sites' => 0, 'cases' => 0, 'interest' => 0];
        if ($tenantId < 1) {
            return $stats;
        }

        foreach ($this->persons->listForContext($tenantId, 1, ['limit' => $limit]) as $p) {
            $this->syncPerson($tenantId, $p);
            $stats['persons']++;
        }
        foreach ($this->sites->listForContext($tenantId, 1, ['limit' => $limit]) as $s) {
            $this->syncSite($tenantId, $s);
            $stats['sites']++;
        }
        foreach ($this->cases->listForTenant($tenantId, null) as $i => $c) {
            if ($i >= $limit) {
                break;
            }
            if (!empty($c['is_folder'])) {
                continue;
            }
            $this->syncCase($tenantId, $c);
            $stats['cases']++;
        }
        foreach ($this->interest->listForTenant($tenantId, []) as $i => $row) {
            if ($i >= $limit) {
                break;
            }
            $this->syncInterestCase($tenantId, $row);
            $stats['interest']++;
        }

        return $stats;
    }

    /**
     * Après ingest personne terrain : index + événement IDENTIFIED / BIOMETRIC.
     *
     * @param array<string, mixed> $person
     * @param array<string, mixed> $meta
     */
    public function onPersonIngested(int $tenantId, array $person, array $meta = []): void
    {
        try {
            $synced = $this->syncPerson($tenantId, $person);
            $sourceSystem = strtoupper(trim((string) ($meta['source_system'] ?? 'ARMA_SSE')));
            $idem = trim((string) ($meta['idempotency_key'] ?? $meta['event_uuid'] ?? ''));
            if ($idem === '') {
                $idem = 'person-ingest-' . (int) ($person['id'] ?? 0) . '-' . ($person['updated_at'] ?? $person['created_at'] ?? time());
            }

            $hasBio = !empty($person['biometric_samples']) || !empty($person['biometrics_simulated']);
            $this->recordEvent([
                'tenant_id' => $tenantId,
                'context_id' => (int) ($person['context_id'] ?? 1),
                'case_id' => $meta['case_id'] ?? null,
                'entity_uuid' => $synced['uuid'],
                'event_type' => $hasBio ? 'BIOMETRIC_CAPTURE' : 'IDENTIFIED',
                'source_system' => $sourceSystem,
                'raw_source_id' => $meta['raw_source_id'] ?? null,
                'identity_tier' => $hasBio ? 'DOCUMENTARY' : 'DECLARED',
                'event_time' => $meta['event_time'] ?? gmdate('Y-m-d H:i:s'),
                'author_label' => $meta['author_label'] ?? ($person['submitter_callsign'] ?? 'Terrain'),
                'unit_label' => $meta['unit_label'] ?? null,
                'lat' => $meta['lat'] ?? $person['capture_pos_x'] ?? null,
                'lng' => $meta['lng'] ?? $person['capture_pos_y'] ?? null,
                'source_reliability' => $meta['source_reliability'] ?? 'C',
                'info_credibility' => $meta['info_credibility'] ?? ($hasBio ? 2 : 3),
                'summary' => sprintf(
                    'Fiche personne reçue : %s',
                    (string) ($person['display_name'] ?? 'sans nom')
                ),
                'payload' => [
                    'person_id' => (int) ($person['id'] ?? 0),
                    'source_system' => $sourceSystem,
                    'client' => is_array($meta['client'] ?? null) ? $meta['client'] : [],
                    'fields' => is_array($meta['transmission_fields'] ?? null) ? $meta['transmission_fields'] : [],
                ],
                'idempotency_key' => $idem,
                'event_uuid' => $meta['event_uuid'] ?? null,
            ]);

            $this->audit([
                'tenant_id' => $tenantId,
                'actor_label' => (string) ($meta['author_label'] ?? $person['submitter_callsign'] ?? 'Terrain'),
                'action' => 'person.ingest',
                'object_type' => 'person',
                'object_id' => (int) ($person['id'] ?? 0),
                'object_uuid' => $synced['uuid'],
                'reason' => 'Transmission terrain',
                'after' => ['source_system' => $sourceSystem],
            ]);
        } catch (\Throwable) {
            // Ne jamais faire échouer l’ingest terrain.
        }
    }

    /**
     * @param array<string, mixed> $site
     * @param array<string, mixed> $meta
     */
    public function onSiteIngested(int $tenantId, array $site, array $meta = []): void
    {
        try {
            $synced = $this->syncSite($tenantId, $site);
            $sourceSystem = strtoupper(trim((string) ($meta['source_system'] ?? 'ARMA_SSE')));
            $idem = trim((string) ($meta['idempotency_key'] ?? ''));
            if ($idem === '') {
                $idem = 'site-ingest-' . (int) ($site['id'] ?? 0) . '-' . ($site['updated_at'] ?? time());
            }

            $this->recordEvent([
                'tenant_id' => $tenantId,
                'context_id' => (int) ($site['context_id'] ?? 1),
                'case_id' => $meta['case_id'] ?? null,
                'entity_uuid' => $synced['uuid'],
                'event_type' => 'SITE_SEARCHED',
                'source_system' => $sourceSystem,
                'raw_source_id' => $meta['raw_source_id'] ?? null,
                'event_time' => $meta['event_time'] ?? gmdate('Y-m-d H:i:s'),
                'author_label' => $meta['author_label'] ?? 'Terrain',
                'source_reliability' => $meta['source_reliability'] ?? 'C',
                'info_credibility' => $meta['info_credibility'] ?? 3,
                'summary' => sprintf('Site transmis : %s', (string) ($site['name'] ?? $site['title'] ?? 'site')),
                'payload' => [
                    'site_id' => (int) ($site['id'] ?? 0),
                    'client' => is_array($meta['client'] ?? null) ? $meta['client'] : [],
                    'fields' => is_array($meta['transmission_fields'] ?? null) ? $meta['transmission_fields'] : [],
                ],
                'idempotency_key' => $idem,
            ]);
        } catch (\Throwable) {
        }
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function listRelations(int $tenantId, array $filters = []): array
    {
        $sql = 'SELECT * FROM sse_relations WHERE tenant_id = :t AND (deleted_at IS NULL OR deleted_at = \'0000-00-00 00:00:00\')';
        $params = ['t' => $tenantId];
        if (!empty($filters['case_id'])) {
            $sql .= ' AND case_id = :c';
            $params['c'] = (int) $filters['case_id'];
        }
        if (!empty($filters['status'])) {
            $sql .= ' AND status = :st';
            $params['st'] = (string) $filters['status'];
        }
        $limit = min(200, max(1, (int) ($filters['limit'] ?? 50)));
        $sql .= ' ORDER BY id DESC LIMIT ' . $limit;

        try {
            $rows = $this->db->fetchAll($sql, $params);
        } catch (\Throwable) {
            // Colonne status absente sur très vieux schémas
            $rows = $this->db->fetchAll(
                'SELECT * FROM sse_relations WHERE tenant_id = :t ORDER BY id DESC LIMIT ' . $limit,
                ['t' => $tenantId]
            );
        }

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => (int) ($row['id'] ?? 0),
                'uuid' => (string) ($row['uuid'] ?? ''),
                'case_id' => isset($row['case_id']) ? (int) $row['case_id'] : null,
                'from_type' => (string) ($row['from_type'] ?? ''),
                'from_type_label' => \App\Support\SseWorkspaceUi::entityTypeLabel((string) ($row['from_type'] ?? '')),
                'from_id' => (int) ($row['from_id'] ?? 0),
                'to_type' => (string) ($row['to_type'] ?? ''),
                'to_type_label' => \App\Support\SseWorkspaceUi::entityTypeLabel((string) ($row['to_type'] ?? '')),
                'to_id' => (int) ($row['to_id'] ?? 0),
                'relation' => (string) ($row['relation'] ?? ''),
                'relation_label' => \App\Support\SseWorkspaceUi::relationLabel((string) ($row['relation'] ?? '')),
                'status' => (string) ($row['status'] ?? 'confirmed'),
                'reliability' => (string) ($row['reliability'] ?? 'unverified'),
                'note' => $row['note'] ?? null,
                'justification' => $row['justification'] ?? null,
                'author_label' => $row['author_label'] ?? null,
                'created_at' => $row['created_at'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $data
     * @return array{ok:bool,id?:int,message?:string}
     */
    public function createRelation(int $tenantId, array $data): array
    {
        $fromId = (int) ($data['from_id'] ?? 0);
        $toId = (int) ($data['to_id'] ?? 0);
        $fromType = (string) ($data['from_type'] ?? 'person');
        $toType = (string) ($data['to_type'] ?? 'person');
        if ($fromId < 1 || $toId < 1) {
            return ['ok' => false, 'message' => 'Les deux extrémités de la relation sont requises.'];
        }
        if ($fromId === $toId && $fromType === $toType) {
            return ['ok' => false, 'message' => 'Une entité ne peut pas être liée à elle-même.'];
        }

        $uuid = SseEntityIndexRepository::newUuid();
        $status = (string) ($data['status'] ?? 'confirmed');
        if (!in_array($status, ['proposed', 'confirmed', 'rejected'], true)) {
            $status = 'confirmed';
        }

        try {
            $id = (int) $this->db->insert(
                'INSERT INTO sse_relations (
                    uuid, tenant_id, case_id, from_type, from_id, to_type, to_id,
                    relation, status, reliability, note, justification, author_label,
                    source_reliability, info_credibility
                ) VALUES (
                    :uuid, :t, :c, :ft, :fi, :tt, :ti,
                    :r, :st, :rel, :n, :j, :a,
                    :sr, :ic
                )
                ON DUPLICATE KEY UPDATE
                    status = VALUES(status),
                    reliability = VALUES(reliability),
                    note = VALUES(note),
                    justification = VALUES(justification),
                    deleted_at = NULL',
                [
                    'uuid' => $uuid,
                    't' => $tenantId,
                    'c' => isset($data['case_id']) && (int) $data['case_id'] > 0 ? (int) $data['case_id'] : null,
                    'ft' => $fromType,
                    'fi' => $fromId,
                    'tt' => $toType,
                    'ti' => $toId,
                    'r' => (string) ($data['relation'] ?? 'associe'),
                    'st' => $status,
                    'rel' => (string) ($data['reliability'] ?? 'unverified'),
                    'n' => ($data['note'] ?? null) ?: null,
                    'j' => ($data['justification'] ?? null) ?: null,
                    'a' => ($data['author_label'] ?? null) ?: null,
                    'sr' => strtoupper(substr((string) ($data['source_reliability'] ?? 'C'), 0, 1)),
                    'ic' => max(1, min(6, (int) ($data['info_credibility'] ?? 3))),
                ]
            );

            $this->audit([
                'tenant_id' => $tenantId,
                'actor_label' => (string) ($data['author_label'] ?? 'Analyste'),
                'action' => 'relation.create',
                'object_type' => 'relation',
                'object_id' => $id,
                'object_uuid' => $uuid,
                'reason' => (string) ($data['justification'] ?? $data['note'] ?? 'Relation enregistrée'),
            ]);

            return ['ok' => true, 'id' => $id];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Impossible d’enregistrer la relation.'];
        }
    }
}
