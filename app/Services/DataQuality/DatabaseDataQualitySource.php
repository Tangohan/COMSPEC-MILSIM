<?php

declare(strict_types=1);

namespace App\Services\DataQuality;

use App\Core\Database;
use App\Repositories\DataCampaignRepository;
use App\Repositories\UserRepository;
use PDO;

/**
 * Lectures MySQL des tableaux de la qualité des données. Lecture seule, toujours bornée à la communauté, une requête
 * par jeu de données. Mêmes critères de membre actif que la campagne de fiabilisation (DataCampaignRepository::roster).
 * Les tables facultatives absentes donnent des colonnes absentes, jamais une erreur.
 */
final class DatabaseDataQualitySource implements DataQualitySource
{
    /** @var array<string, bool> */
    private array $tables = [];

    public function __construct(
        private ?PDO $pdo = null,
        private ?UserRepository $users = null,
        private ?DataCampaignRepository $campaigns = null,
    ) {}

    private function db(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    private function users(): UserRepository
    {
        return $this->users ??= new UserRepository($this->pdo);
    }

    private function campaigns(): DataCampaignRepository
    {
        return $this->campaigns ??= new DataCampaignRepository($this->pdo);
    }

    private function hasTable(string $table): bool
    {
        if (!array_key_exists($table, $this->tables)) {
            try {
                $st = $this->db()->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
                $st->execute([$table]);
                $this->tables[$table] = (bool) $st->fetchColumn();
            } catch (\Throwable) {
                $this->tables[$table] = false;
            }
        }

        return $this->tables[$table];
    }

    public function memberRows(int $tenantId, int $limit): array
    {
        $limit = max(1, min(20000, $limit));
        $select = ['u.id', 'u.display_name', 'u.callsign', 'u.email', 'u.steam_id', 'u.athena_identifier', 'u.avatar_url', 'u.grade_id',
            'u.last_login_at', 'u.created_at', 'u.updated_at'];
        $joins = [];
        if ($this->users()->hasTenantMemberNumberColumn()) {
            $select[] = 'u.tenant_member_number';
        }
        if ($this->hasTable('personnel_profiles')) {
            $joins[] = 'LEFT JOIN personnel_profiles pp ON pp.user_id = u.id';
            $joins[] = 'LEFT JOIN units pu ON pu.id = pp.primary_unit_id';
            array_push($select, 'pp.id AS pp_id', 'pp.rank_display', 'pp.primary_unit_id', 'pp.enlistment_date', 'pp.matricule_internal',
                'pp.character_portrait_path', 'pp.updated_at AS pp_updated', 'pu.tenant_id AS primary_unit_tenant');
        }
        if ($this->hasTable('personnel_extras')) {
            $joins[] = 'LEFT JOIN personnel_extras pe ON pe.user_id = u.id';
            $select[] = 'pe.service_number';
        }
        // Prénom et nom : ceux du personnage (user_profiles), saisis dans « État civil du personnage » et affichés partout.
        // L'identité légale ne garde que ses propres usages (téléphone, date de naissance, doublons de personne réelle).
        $profileNames = $this->hasTable('user_profiles');
        if ($profileNames) {
            $joins[] = 'LEFT JOIN user_profiles up ON up.user_id = u.id';
            array_push($select, 'up.first_name', 'up.last_name');
        }
        if ($this->hasTable('user_legal_identities')) {
            $joins[] = 'LEFT JOIN user_legal_identities uli ON uli.user_id = u.id';
            array_push($select, 'uli.phone', 'uli.birth_date', 'uli.updated_at AS uli_updated', 'uli.first_name AS legal_first_name', 'uli.last_name AS legal_last_name');
            if (!$profileNames) {
                array_push($select, 'uli.first_name', 'uli.last_name');
            }
        }
        if ($this->hasTable('grades')) {
            $joins[] = 'LEFT JOIN grades g ON g.id = u.grade_id';
            array_push($select, 'g.tenant_id AS grade_tenant', 'g.name AS grade_name', 'g.short_name AS grade_short');
        }
        if ($this->hasTable('personnel_assignments')) {
            $active = "pa.user_id = u.id AND pa.status = 'active' AND (pa.ended_at IS NULL OR pa.ended_at > CURDATE())";
            $select[] = "(SELECT COUNT(*) FROM personnel_assignments pa WHERE {$active}) AS unit_count";
            $select[] = "(SELECT pa.unit_id FROM personnel_assignments pa WHERE {$active} ORDER BY pa.is_primary DESC, pa.started_at DESC LIMIT 1) AS main_unit_id";
        }
        $member = $this->users()->sqlMemberOfTenantPredicate('u', $tenantId);
        $sql = 'SELECT ' . implode(', ', $select) . ' FROM users u ' . implode(' ', $joins)
            . " WHERE {$member} AND u.deleted_at IS NULL AND COALESCE(u.is_service_account, 0) = 0 AND COALESCE(u.status, 'active') = 'active'"
            . ' ORDER BY u.id ASC LIMIT ' . $limit;
        $st = $this->db()->query($sql);

        return $st === false ? [] : array_values($st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function qualificationDateIssues(int $tenantId): array
    {
        if (!$this->hasTable('personnel_qualifications')) {
            return [];
        }
        $member = $this->users()->sqlMemberOfTenantPredicate('u', $tenantId);
        try {
            $st = $this->db()->query(
                "SELECT pq.user_id,
                        SUM(pq.obtained_at IS NOT NULL AND pq.expires_at IS NOT NULL AND pq.expires_at < pq.obtained_at) AS bad_range,
                        SUM(pq.obtained_at IS NOT NULL AND pq.obtained_at > CURDATE()) AS future
                 FROM personnel_qualifications pq
                 INNER JOIN users u ON u.id = pq.user_id
                 WHERE {$member}
                 GROUP BY pq.user_id
                 HAVING bad_range > 0 OR future > 0"
            );
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($st === false ? [] : ($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) {
            $out[(int) $r['user_id']] = ['bad_range' => (int) $r['bad_range'], 'future' => (int) $r['future']];
        }

        return $out;
    }

    public function lastConfirmations(int $tenantId): array
    {
        if (!$this->campaigns()->schemaReady()) {
            return [];
        }
        $st = $this->db()->prepare(
            'SELECT user_id, MAX(confirmed_at) AS last_at FROM data_campaign_members
             WHERE tenant_id = ? AND confirmed_at IS NOT NULL GROUP BY user_id'
        );
        $st->execute([$tenantId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $out[(int) $r['user_id']] = (string) $r['last_at'];
        }

        return $out;
    }

    public function unitNames(int $tenantId): array
    {
        $out = [];
        foreach ($this->campaigns()->units($tenantId) as $id => $u) {
            $out[$id] = $u['name'];
        }

        return $out;
    }

    public function campaign(int $tenantId): ?array
    {
        if (!$this->campaigns()->schemaReady()) {
            return null;
        }
        $campaign = $this->campaigns()->currentOpenCampaign($tenantId) ?? ($this->campaigns()->listCampaigns($tenantId, 1)[0] ?? null);
        if ($campaign === null) {
            return null;
        }

        return ['campaign' => $campaign, 'members' => $this->campaigns()->members((int) $campaign['id'], $tenantId)];
    }
}
