<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

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

    public function __construct(private ?Database $db = null)
    {
        $this->db ??= Database::getInstance();
        $migration = require base_path('bootstrap/atak_sse_interest_cases_migration.php');
        $migration(Database::getPdo());
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
            $where[] = '(reference_code LIKE :q OR temporary_designation LIKE :q OR suspected_alias LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }
        $rows = $this->db->fetchAll('SELECT * FROM sse_interest_cases WHERE ' . implode(' AND ', $where) . ' ORDER BY updated_at DESC LIMIT 200', $params);
        return array_map(fn (array $r): array => $this->hydrate($r), $rows);
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
        $fields = ['tenant_id','reference_code','temporary_designation','suspected_alias','apparent_sex','estimated_age_range','suspected_nationality','suspected_affiliation','status','confidence_level','interest_level','opening_reason','origin_operator','observed_elements','analysis_facts','analysis_assumptions','analysis_contradictions','analysis_questions','collection_needs','operational_risk','recommendations','source_label','source_reliability','acquisition_at','mission_label','created_by'];
        $values = ['tenant_id' => $tenantId, 'reference_code' => $reference, 'status' => 'signalement_recu'];
        foreach ($fields as $field) {
            if (!array_key_exists($field, $values)) $values[$field] = $data[$field] ?? null;
        }
        $values['confidence_level'] = isset(self::CONFIDENCE[(string) $values['confidence_level']]) ? $values['confidence_level'] : 'non_evalue';
        $values['interest_level'] = isset(self::INTEREST[(string) $values['interest_level']]) ? $values['interest_level'] : 'courant';
        if (!empty($values['acquisition_at'])) {
            $values['acquisition_at'] = str_replace('T', ' ', (string) $values['acquisition_at']);
        }
        return (int) $this->db->insert('INSERT INTO sse_interest_cases (' . implode(',', $fields) . ') VALUES (:' . implode(',:', $fields) . ')', $values);
    }

    private function hydrate(array $row): array
    {
        $row['status_label'] = self::STATUSES[$row['status'] ?? ''] ?? 'À qualifier';
        $row['confidence_label'] = self::CONFIDENCE[$row['confidence_level'] ?? ''] ?? 'Non évalué';
        $row['interest_label'] = self::INTEREST[$row['interest_level'] ?? ''] ?? 'Courant';
        return $row;
    }
}
