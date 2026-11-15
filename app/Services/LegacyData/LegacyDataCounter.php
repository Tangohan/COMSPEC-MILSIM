<?php

declare(strict_types=1);

namespace App\Services\LegacyData;

use App\Core\Database;
use App\Support\EnlistmentRetroAutoClose;
use App\Support\Migrations\SchemaInspector;
use PDO;
use Throwable;

/**
 * Compte, pour une communauté, les données anciennes ou incomplètes que les écrans récents affichent autrement ou
 * ne peuvent plus montrer tout à fait comme avant (avis « Données anciennes à vérifier »).
 *
 * LECTURE SEULE, quelques COUNT légers. Chaque table ou colonne facultative est vérifiée avant d'être lue : sur une base
 * ancienne, une table absente donne 0 (et non une erreur). Les requêtes sont portables (MySQL en production, SQLite en
 * tests) : les dates limites sont calculées ici, pas dans le SQL.
 */
final class LegacyDataCounter
{
    public const KEYS = ['members', 'no_civil_name', 'importable_names', 'no_profile', 'no_unit', 'no_grade', 'no_portrait', 'stale_dossiers', 'legacy_role_labels'];

    private PDO $pdo;

    private SchemaInspector $schema;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
        $this->schema = new SchemaInspector($this->pdo);
    }

    /**
     * @return array{members: int, no_civil_name: int, importable_names: int, no_profile: int, no_unit: int, no_grade: int, no_portrait: int, stale_dossiers: int, legacy_role_labels: int}
     */
    public function count(int $tenantId, ?int $now = null): array
    {
        $out = ['members' => 0, 'no_civil_name' => 0, 'importable_names' => 0, 'no_profile' => 0, 'no_unit' => 0, 'no_grade' => 0, 'no_portrait' => 0, 'stale_dossiers' => 0, 'legacy_role_labels' => 0];
        if ($tenantId < 1) {
            return $out;
        }
        $now ??= time();
        $out['members'] = $this->scalar('SELECT COUNT(*) FROM users u WHERE ' . $this->memberWhere(), $this->memberParams($tenantId));
        if ($out['members'] > 0) {
            $out['no_civil_name'] = $this->withoutCivilName($tenantId);
            $out['importable_names'] = $this->importableNames($tenantId);
            $out['no_profile'] = $this->withoutRow($tenantId, 'personnel_profiles');
            $out['no_unit'] = $this->withoutUnit($tenantId, $now);
            $out['no_grade'] = $this->withoutGrade($tenantId);
            $out['no_portrait'] = $this->withoutPortrait($tenantId);
        }
        $out['stale_dossiers'] = $this->staleDossiers($tenantId, $now);
        $out['legacy_role_labels'] = $this->legacyRoleLabels($tenantId);

        return $out;
    }

    /** Table qui porte le prénom et le nom d'état civil, ou null si la base n'en a aucune. */
    public function civilNameTable(): ?string
    {
        foreach (['user_legal_identities', 'user_profiles'] as $table) {
            if ($this->schema->tableExists($table) && $this->schema->columnExists($table, 'first_name') && $this->schema->columnExists($table, 'last_name')) {
                return $table;
            }
        }

        return null;
    }

    /** Condition SQL (alias users = u) : membres actifs de la communauté : compte rattaché, ou adhésion active (comme le reste du site), sans compte technique ni supprimé. */
    public function memberWhere(): string
    {
        $home = 'u.tenant_id = ?';
        if ($this->schema->tableExists('user_community_memberships')
            && $this->schema->columnExists('user_community_memberships', 'status')) {
            $home = '(u.tenant_id = ? OR EXISTS (SELECT 1 FROM user_community_memberships m WHERE m.user_id = u.id AND m.tenant_id = ? AND m.status = \'active\'))';
        }
        $where = $home;
        if ($this->schema->columnExists('users', 'status')) {
            $where .= " AND u.status = 'active'";
        }
        if ($this->schema->columnExists('users', 'deleted_at')) {
            $where .= ' AND u.deleted_at IS NULL';
        }
        if ($this->schema->columnExists('users', 'is_service_account')) {
            $where .= ' AND COALESCE(u.is_service_account, 0) = 0';
        }

        return $where;
    }

    /** @return list<int> paramètres de memberWhere() */
    public function memberParams(int $tenantId): array
    {
        return substr_count($this->memberWhere(), '?') === 2 ? [$tenantId, $tenantId] : [$tenantId];
    }

    /** @param list<scalar> $extra paramètres de la condition ajoutée */
    private function members(int $tenantId, string $and, array $extra = []): int
    {
        return $this->scalar('SELECT COUNT(*) FROM users u WHERE ' . $this->memberWhere() . ' AND ' . $and, array_merge($this->memberParams($tenantId), $extra));
    }

    private function withoutCivilName(int $tenantId): int
    {
        $table = $this->civilNameTable();
        if ($table === null) {
            return 0;
        }

        return $this->members(
            $tenantId,
            "NOT EXISTS (SELECT 1 FROM {$table} x WHERE x.user_id = u.id AND (TRIM(COALESCE(x.first_name, '')) <> '' OR TRIM(COALESCE(x.last_name, '')) <> ''))"
        );
    }

    /** Membres sans prénom ni nom dont l'ancien nom de personnage permettrait une reprise (voir LegacyIdentityImport). */
    private function importableNames(int $tenantId): int
    {
        $table = $this->civilNameTable();
        if ($table !== 'user_legal_identities' || !$this->schema->tableExists('personnel_profiles') || !$this->schema->columnExists('personnel_profiles', 'character_name')) {
            return 0;
        }

        return $this->members(
            $tenantId,
            "EXISTS (SELECT 1 FROM personnel_profiles p WHERE p.user_id = u.id AND TRIM(COALESCE(p.character_name, '')) <> '')
             AND NOT EXISTS (SELECT 1 FROM {$table} x WHERE x.user_id = u.id AND (TRIM(COALESCE(x.first_name, '')) <> '' OR TRIM(COALESCE(x.last_name, '')) <> ''))"
        );
    }

    private function withoutRow(int $tenantId, string $table): int
    {
        if (!$this->schema->tableExists($table)) {
            return 0;
        }

        return $this->members($tenantId, "NOT EXISTS (SELECT 1 FROM {$table} x WHERE x.user_id = u.id)");
    }

    private function withoutUnit(int $tenantId, int $now): int
    {
        $and = [];
        $params = [];
        if ($this->schema->tableExists('personnel_assignments') && $this->schema->columnExists('personnel_assignments', 'unit_id')) {
            $and[] = "NOT EXISTS (SELECT 1 FROM personnel_assignments a WHERE a.user_id = u.id AND a.unit_id IS NOT NULL AND a.status = 'active' AND (a.ended_at IS NULL OR a.ended_at > ?))";
            $params[] = date('Y-m-d', $now);
        }
        if ($this->schema->tableExists('personnel_profiles') && $this->schema->columnExists('personnel_profiles', 'primary_unit_id')) {
            $and[] = 'NOT EXISTS (SELECT 1 FROM personnel_profiles p WHERE p.user_id = u.id AND p.primary_unit_id IS NOT NULL AND p.primary_unit_id > 0)';
        }
        if ($this->schema->tableExists('user_units') && $this->schema->columnExists('user_units', 'unit_id')) {
            $and[] = 'NOT EXISTS (SELECT 1 FROM user_units uu WHERE uu.user_id = u.id AND uu.unit_id IS NOT NULL AND (uu.ended_at IS NULL OR uu.ended_at > ?))';
            $params[] = date('Y-m-d H:i:s', $now);
        }
        if ($and === []) {
            return 0;
        }

        return $this->members($tenantId, implode(' AND ', $and), $params);
    }

    private function withoutGrade(int $tenantId): int
    {
        if (!$this->schema->columnExists('users', 'grade_id')) {
            return 0;
        }

        return $this->members($tenantId, '(u.grade_id IS NULL OR u.grade_id = 0)');
    }

    private function withoutPortrait(int $tenantId): int
    {
        if (!$this->schema->tableExists('personnel_profiles') || !$this->schema->columnExists('personnel_profiles', 'character_portrait_path')) {
            return 0;
        }

        return $this->members(
            $tenantId,
            "NOT EXISTS (SELECT 1 FROM personnel_profiles p WHERE p.user_id = u.id AND TRIM(COALESCE(p.character_portrait_path, '')) <> '')"
        );
    }

    /**
     * Candidatures acceptées sans bilan d'équipe, plus anciennes que la fenêtre de sécurité de la clôture automatique
     * (EnlistmentRetroAutoClose::AUTO_CLOSE_MAX_LATE_DAYS) : la tâche planifiée ne les touche pas.
     */
    public function staleDossiers(int $tenantId, ?int $now = null): int
    {
        if (!$this->schema->tableExists('enlistments') || !$this->schema->tableExists('enlistment_retro_feedbacks')) {
            return 0;
        }
        $now ??= time();
        $cutoff = date('Y-m-d 00:00:00', strtotime('-' . EnlistmentRetroAutoClose::acceptanceMaxAgeDays() . ' days', $now) ?: $now);

        return $this->scalar(
            "SELECT COUNT(*) FROM enlistments e
             WHERE e.tenant_id = ? AND e.status = 'reviewed'
               AND COALESCE(e.reviewed_at, e.created_at) < ?
               AND NOT EXISTS (
                   SELECT 1 FROM enlistment_retro_feedbacks r
                   WHERE r.tenant_id = e.tenant_id AND r.enlistment_id = e.id
                     AND (r.feedback_scope = 'staff_one_month' OR r.feedback_scope = 'staff' OR r.feedback_scope LIKE 'staff!_%' ESCAPE '!')
               )",
            [$tenantId, $cutoff]
        );
    }

    /** Niveaux d'accès de base encore nommés « Opérateur » (migration de libellé non passée, ou nom gardé par la communauté). */
    private function legacyRoleLabels(int $tenantId): int
    {
        if (!$this->schema->tableExists('roles')) {
            return 0;
        }

        return $this->scalar("SELECT COUNT(*) FROM roles WHERE tenant_id = ? AND slug = 'member' AND name = ?", [$tenantId, 'Opérateur']);
    }

    /** @param list<scalar> $params */
    private function scalar(string $sql, array $params): int
    {
        try {
            $st = $this->pdo->prepare($sql);
            $st->execute($params);

            return (int) $st->fetchColumn();
        } catch (Throwable $e) {
            error_log('[legacy_data] ' . $e->getMessage());

            return 0;
        }
    }
}
