<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SilentSchemaMigration;

final class SseInterestCaseRepository
{
    public const STATUSES = [
        'brouillon' => 'Brouillon', 'signalement_recu' => 'Signalement reçu',
        'a_qualifier' => 'À qualifier', 'en_collecte' => 'En collecte',
        'en_analyse' => 'En analyse', 'rapprochements_detectes' => 'Rapprochements détectés',
        'en_validation' => 'En validation', 'correspondance_probable' => 'Correspondance probable',
        'identite_consolidee' => 'Identité consolidée', 'identite_infirme' => 'Identité infirmée',
        'sans_suite' => 'Sans suite', 'archive' => 'Archivé',
    ];
    public const CONFIDENCE = ['non_evalue' => 'Non évalué', 'tres_faible' => 'Très faible', 'faible' => 'Faible', 'modere' => 'Modéré', 'eleve' => 'Élevé', 'tres_eleve' => 'Très élevé', 'confirme' => 'Confirmé'];
    public const INTEREST = ['courant' => 'Courant', 'a_surveiller' => 'À surveiller', 'prioritaire' => 'Prioritaire', 'critique' => 'Critique'];

    public const ACL_DESTINATAIRE = 'destinataire';
    public const ACL_INTERDIT = 'interdit';

    /** @var array<string, array{seconds: int, label: string}> */
    public const COOLDOWNS = [
        'status' => ['seconds' => 300, 'label' => 'Changer l’état du dossier'],
        'constitute' => ['seconds' => 600, 'label' => 'Constituer le dossier'],
        'cross_decide' => ['seconds' => 120, 'label' => 'Trancher un rapprochement'],
        'open_mesh' => ['seconds' => 300, 'label' => 'Ouvrir une investigation'],
        'publish' => ['seconds' => 600, 'label' => 'Soumettre à validation'],
    ];

    public function __construct(private ?Database $db = null)
    {
        $this->db ??= Database::getInstance();
        SilentSchemaMigration::runMany([
            base_path('bootstrap/atak_sse_interest_cases_migration.php'),
            base_path('bootstrap/atak_sse_interest_case_enrichment_migration.php'),
        ]);
    }

    /** @return list<array<string,mixed>> */
    public function listForTenant(int $tenantId, array $filters = []): array
    {
        $where = ['tenant_id = :tenant'];
        $params = ['tenant' => $tenantId];
        if (isset(self::STATUSES[(string) ($filters['status'] ?? '')])) {
            $where[] = 'status = :status'; $params['status'] = $filters['status'];
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $where[] = '(reference_code LIKE :q_ref OR temporary_designation LIKE :q_desig OR suspected_alias LIKE :q_alias)';
            $params['q_ref'] = $like;
            $params['q_desig'] = $like;
            $params['q_alias'] = $like;
        }
        $rows = $this->db->fetchAll('SELECT * FROM sse_interest_cases WHERE ' . implode(' AND ', $where) . ' ORDER BY updated_at DESC LIMIT 200', $params);
        return array_map(fn (array $r): array => $this->hydrate($r), $rows);
    }

    /**
     * File filtrée selon destinataires / interdits nominatifs.
     *
     * @return list<array<string,mixed>>
     */
    public function listVisibleForUser(int $tenantId, ?int $userId, bool $canBypassAcl, array $filters = []): array
    {
        $rows = $this->listForTenant($tenantId, $filters);
        if ($canBypassAcl) {
            return $rows;
        }

        return array_values(array_filter(
            $rows,
            fn (array $case): bool => $this->userCanAccessCase($case, $userId, false)
        ));
    }

    public function findForTenant(int $id, int $tenantId): ?array
    {
        $row = $this->db->fetchOne('SELECT * FROM sse_interest_cases WHERE id = :id AND tenant_id = :tenant LIMIT 1', ['id' => $id, 'tenant' => $tenantId]);
        return $row ? $this->hydrate($row) : null;
    }

    public function create(int $tenantId, array $data): int
    {
        $year = date('Y');
        $last = $this->db->fetchOne('SELECT MAX(id) AS id FROM sse_interest_cases WHERE tenant_id = :tenant', ['tenant' => $tenantId]);
        $reference = sprintf('DI-%s-%06d', $year, ((int) ($last['id'] ?? 0)) + 1);
        $fields = ['tenant_id','reference_code','temporary_designation','suspected_alias','apparent_sex','estimated_age_range','suspected_nationality','suspected_affiliation','status','confidence_level','interest_level','opening_reason','description','origin_operator','observed_elements','analysis_facts','analysis_assumptions','analysis_contradictions','analysis_questions','collection_needs','operational_risk','recommendations','source_label','source_reliability','signed_by_label','signed_at','acquisition_at','mission_label','created_by'];
        $values = ['tenant_id' => $tenantId, 'reference_code' => $reference, 'status' => 'signalement_recu'];
        foreach ($fields as $field) {
            if (!array_key_exists($field, $values)) {
                $values[$field] = $data[$field] ?? null;
            }
        }
        $values['confidence_level'] = isset(self::CONFIDENCE[(string) $values['confidence_level']]) ? $values['confidence_level'] : 'non_evalue';
        $values['interest_level'] = isset(self::INTEREST[(string) $values['interest_level']]) ? $values['interest_level'] : 'courant';
        if (!empty($values['acquisition_at'])) {
            $values['acquisition_at'] = str_replace('T', ' ', (string) $values['acquisition_at']);
        }
        if (!empty($values['signed_at'])) {
            $values['signed_at'] = str_replace('T', ' ', (string) $values['signed_at']);
        }
        try {
            return (int) $this->db->insert('INSERT INTO sse_interest_cases (' . implode(',', $fields) . ') VALUES (:' . implode(',:', $fields) . ')', $values);
        } catch (\Throwable) {
            // Colonnes enrichies absentes sur un tenant pas encore migré.
            foreach (['description', 'signed_by_label', 'signed_at'] as $optional) {
                unset($values[$optional]);
                $fields = array_values(array_filter($fields, static fn (string $f): bool => $f !== $optional));
            }
            return (int) $this->db->insert('INSERT INTO sse_interest_cases (' . implode(',', $fields) . ') VALUES (:' . implode(',:', $fields) . ')', $values);
        }
    }

    public function updateDescription(int $id, int $tenantId, ?string $description): bool
    {
        try {
            return $this->db->execute(
                'UPDATE sse_interest_cases SET description = :d WHERE id = :id AND tenant_id = :t',
                ['d' => $description !== null && trim($description) !== '' ? trim($description) : null, 'id' => $id, 't' => $tenantId]
            ) >= 0;
        } catch (\Throwable) {
            return false;
        }
    }

    public function updateStatus(int $id, int $tenantId, string $status): bool
    {
        if (!isset(self::STATUSES[$status])) {
            return false;
        }
        try {
            return $this->db->execute(
                'UPDATE sse_interest_cases SET status = :s WHERE id = :id AND tenant_id = :t',
                ['s' => $status, 'id' => $id, 't' => $tenantId]
            ) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listUpdates(int $tenantId, int $interestCaseId, int $limit = 40): array
    {
        try {
            return $this->db->fetchAll(
                'SELECT * FROM sse_interest_case_updates
                 WHERE tenant_id = :t AND interest_case_id = :c
                 ORDER BY created_at DESC, id DESC
                 LIMIT ' . max(1, min(100, $limit)),
                ['t' => $tenantId, 'c' => $interestCaseId]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    public function addUpdate(int $tenantId, int $interestCaseId, string $body, ?string $authorLabel, ?int $authorUserId): int
    {
        $body = trim($body);
        if ($body === '') {
            return 0;
        }
        try {
            return (int) $this->db->insert(
                'INSERT INTO sse_interest_case_updates
                    (tenant_id, interest_case_id, body, author_label, author_user_id)
                 VALUES (:t, :c, :b, :a, :u)',
                [
                    't' => $tenantId,
                    'c' => $interestCaseId,
                    'b' => mb_substr($body, 0, 4000),
                    'a' => $authorLabel !== null && trim($authorLabel) !== '' ? trim($authorLabel) : null,
                    'u' => ($authorUserId !== null && $authorUserId > 0) ? $authorUserId : null,
                ]
            );
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @return array{destinataires: list<array<string,mixed>>, interdits: list<array<string,mixed>>}
     */
    public function listAcl(int $tenantId, int $interestCaseId): array
    {
        $empty = ['destinataires' => [], 'interdits' => []];
        try {
            $rows = $this->db->fetchAll(
                'SELECT a.*, u.display_name, u.callsign
                 FROM sse_interest_case_acl a
                 INNER JOIN users u ON u.id = a.user_id AND u.tenant_id = a.tenant_id
                 WHERE a.tenant_id = :t AND a.interest_case_id = :c
                 ORDER BY u.display_name ASC, u.callsign ASC',
                ['t' => $tenantId, 'c' => $interestCaseId]
            );
        } catch (\Throwable) {
            return $empty;
        }

        $out = $empty;
        foreach ($rows as $row) {
            $mode = (string) ($row['access_mode'] ?? '');
            $label = trim((string) ($row['display_name'] ?? ''));
            if ($label === '') {
                $label = trim((string) ($row['callsign'] ?? '')) ?: 'Membre';
            } elseif (!empty($row['callsign'])) {
                $label .= ' (' . (string) $row['callsign'] . ')';
            }
            $row['member_label'] = $label;
            if ($mode === self::ACL_DESTINATAIRE) {
                $out['destinataires'][] = $row;
            } elseif ($mode === self::ACL_INTERDIT) {
                $out['interdits'][] = $row;
            }
        }

        return $out;
    }

    /**
     * Remplace les listes destinataires / interdits (cases à cocher).
     *
     * @param list<int> $destinataireIds
     * @param list<int> $interditIds
     */
    public function replaceAcl(int $tenantId, int $interestCaseId, array $destinataireIds, array $interditIds, ?int $actorUserId): bool
    {
        $destinataireIds = $this->normalizeUserIds($destinataireIds);
        $interditIds = $this->normalizeUserIds($interditIds);
        // Un même membre ne peut pas être à la fois destinataire et interdit.
        $interditIds = array_values(array_diff($interditIds, $destinataireIds));

        try {
            $this->db->execute(
                'DELETE FROM sse_interest_case_acl WHERE tenant_id = :t AND interest_case_id = :c',
                ['t' => $tenantId, 'c' => $interestCaseId]
            );
            foreach ($destinataireIds as $uid) {
                $this->db->insert(
                    'INSERT INTO sse_interest_case_acl (tenant_id, interest_case_id, user_id, access_mode, created_by)
                     VALUES (:t, :c, :u, :m, :b)',
                    ['t' => $tenantId, 'c' => $interestCaseId, 'u' => $uid, 'm' => self::ACL_DESTINATAIRE, 'b' => $actorUserId]
                );
            }
            foreach ($interditIds as $uid) {
                $this->db->insert(
                    'INSERT INTO sse_interest_case_acl (tenant_id, interest_case_id, user_id, access_mode, created_by)
                     VALUES (:t, :c, :u, :m, :b)',
                    ['t' => $tenantId, 'c' => $interestCaseId, 'u' => $uid, 'm' => self::ACL_INTERDIT, 'b' => $actorUserId]
                );
            }

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Accès lecture : interdit nominatif bloque ; destinataires (s’il y en a) restreignent.
     * Le créateur et les opérateurs qui contournent l’ACL (habilitation forte) restent admis.
     */
    public function userCanAccessCase(array $case, ?int $userId, bool $canBypassAcl): bool
    {
        if ($canBypassAcl) {
            return true;
        }

        $caseId = (int) ($case['id'] ?? 0);
        $tenantId = (int) ($case['tenant_id'] ?? 0);
        if ($caseId < 1 || $tenantId < 1) {
            return false;
        }

        $uid = $userId !== null && $userId > 0 ? $userId : 0;

        try {
            if ($uid > 0) {
                $denied = $this->db->fetchOne(
                    'SELECT 1 FROM sse_interest_case_acl
                     WHERE tenant_id = :t AND interest_case_id = :c AND user_id = :u AND access_mode = :m LIMIT 1',
                    ['t' => $tenantId, 'c' => $caseId, 'u' => $uid, 'm' => self::ACL_INTERDIT]
                );
                if ($denied !== null) {
                    return false;
                }
            }

            $createdBy = (int) ($case['created_by'] ?? 0);
            if ($uid > 0 && $createdBy > 0 && $uid === $createdBy) {
                return true;
            }

            $destCount = $this->db->fetchOne(
                'SELECT COUNT(*) AS n FROM sse_interest_case_acl
                 WHERE tenant_id = :t AND interest_case_id = :c AND access_mode = :m',
                ['t' => $tenantId, 'c' => $caseId, 'm' => self::ACL_DESTINATAIRE]
            );
            $n = (int) ($destCount['n'] ?? 0);
            if ($n < 1) {
                return true;
            }
            if ($uid < 1) {
                return false;
            }
            $allowed = $this->db->fetchOne(
                'SELECT 1 FROM sse_interest_case_acl
                 WHERE tenant_id = :t AND interest_case_id = :c AND user_id = :u AND access_mode = :m LIMIT 1',
                ['t' => $tenantId, 'c' => $caseId, 'u' => $uid, 'm' => self::ACL_DESTINATAIRE]
            );

            return $allowed !== null;
        } catch (\Throwable) {
            // Tables absentes : comportement historique (ouvert à la cellule SSE).
            return true;
        }
    }

    /**
     * @return array{blocked: bool, remaining_seconds: int, label: string, ready_at: ?string}
     */
    public function cooldownState(int $tenantId, int $interestCaseId, string $actionKey): array
    {
        $meta = self::COOLDOWNS[$actionKey] ?? null;
        $label = $meta['label'] ?? 'Cette action';
        $seconds = (int) ($meta['seconds'] ?? 0);
        $empty = ['blocked' => false, 'remaining_seconds' => 0, 'label' => $label, 'ready_at' => null];
        if ($seconds < 1 || $meta === null) {
            return $empty;
        }

        try {
            $row = $this->db->fetchOne(
                'SELECT last_at FROM sse_interest_case_cooldowns
                 WHERE tenant_id = :t AND interest_case_id = :c AND action_key = :a LIMIT 1',
                ['t' => $tenantId, 'c' => $interestCaseId, 'a' => $actionKey]
            );
        } catch (\Throwable) {
            return $empty;
        }

        if ($row === null || empty($row['last_at'])) {
            return $empty;
        }

        $last = strtotime((string) $row['last_at']);
        if ($last === false) {
            return $empty;
        }
        $ready = $last + $seconds;
        $remaining = $ready - time();
        if ($remaining <= 0) {
            return $empty;
        }

        return [
            'blocked' => true,
            'remaining_seconds' => $remaining,
            'label' => $label,
            'ready_at' => date('Y-m-d H:i:s', $ready),
        ];
    }

    public function touchCooldown(int $tenantId, int $interestCaseId, string $actionKey, ?int $userId): void
    {
        if (!isset(self::COOLDOWNS[$actionKey])) {
            return;
        }
        try {
            $this->db->execute(
                'INSERT INTO sse_interest_case_cooldowns (tenant_id, interest_case_id, action_key, last_at, last_by)
                 VALUES (:t, :c, :a, NOW(), :u)
                 ON DUPLICATE KEY UPDATE last_at = VALUES(last_at), last_by = VALUES(last_by)',
                [
                    't' => $tenantId,
                    'c' => $interestCaseId,
                    'a' => $actionKey,
                    'u' => ($userId !== null && $userId > 0) ? $userId : null,
                ]
            );
        } catch (\Throwable) {
            // ignore
        }
    }

    /**
     * @return array<string, array{blocked: bool, remaining_seconds: int, label: string, ready_at: ?string, human: string}>
     */
    public function allCooldownStates(int $tenantId, int $interestCaseId): array
    {
        $out = [];
        foreach (array_keys(self::COOLDOWNS) as $key) {
            $state = $this->cooldownState($tenantId, $interestCaseId, $key);
            $state['human'] = $this->formatCooldownHuman($state);
            $out[$key] = $state;
        }

        return $out;
    }

    /** @param array{blocked?: bool, remaining_seconds?: int, label?: string} $state */
    public function formatCooldownHuman(array $state): string
    {
        if (empty($state['blocked'])) {
            return '';
        }
        $sec = max(1, (int) ($state['remaining_seconds'] ?? 0));
        $mins = (int) ceil($sec / 60);
        $label = (string) ($state['label'] ?? 'Cette action');
        if ($mins <= 1) {
            return $label . ' : attendez encore environ une minute avant de recommencer.';
        }

        return $label . ' : attendez encore environ ' . $mins . ' minutes avant de recommencer.';
    }

    private function hydrate(array $row): array
    {
        $row['status_label'] = self::STATUSES[$row['status'] ?? ''] ?? 'À qualifier';
        $row['confidence_label'] = self::CONFIDENCE[$row['confidence_level'] ?? ''] ?? 'Non évalué';
        $row['interest_label'] = self::INTEREST[$row['interest_level'] ?? ''] ?? 'Courant';
        $row['description'] = $row['description'] ?? null;

        return $row;
    }

    /**
     * @param list<mixed> $ids
     * @return list<int>
     */
    private function normalizeUserIds(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            $n = (int) $id;
            if ($n > 0) {
                $out[$n] = $n;
            }
        }

        return array_values($out);
    }
}
