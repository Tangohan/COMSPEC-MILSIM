<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SqlText;
use PDO;
use PDOException;

/**
 * Données chaîne pédagogique (rôles par tenant, éligibilité instructeur, audit).
 * Les modules LMS restent dans training_* ; les modules compétences ALPHA/DELTA dans `modules` (autre domaine).
 */
final class PedagogyRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function hasTable(string $table): bool
    {
        $t = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if ($t === '') {
            return false;
        }
        try {
            $st = $this->pdo->query('SHOW TABLES LIKE ' . $this->pdo->quote($t));

            return (bool) $st->fetchColumn();
        } catch (PDOException) {
            return false;
        }
    }

    public function pedagogyRoleSetsAvailable(): bool
    {
        return $this->hasTable('tenant_pedagogy_role_sets');
    }

    public function trainingCoursesHavePedagogyColumns(): bool
    {
        if (!$this->hasTable('training_courses')) {
            return false;
        }
        foreach (['pedagogical_owner_user_id', 'final_validator_user_id'] as $col) {
            $st = $this->pdo->query(
                'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ' . $this->pdo->quote('training_courses') . ' AND COLUMN_NAME = ' . $this->pdo->quote($col) . ' LIMIT 1'
            );
            if (!$st || !$st->fetch()) {
                return false;
            }
        }

        return true;
    }

    /** @return list<int> */
    public function roleIdsForPedagogyKind(int $tenantId, string $kind): array
    {
        if (!$this->pedagogyRoleSetsAvailable()) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT role_id FROM tenant_pedagogy_role_sets WHERE tenant_id = ? AND pedagogy_kind = ? ORDER BY role_id ASC'
        );
        $st->execute([$tenantId, $kind]);

        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /** @param list<int> $roleIds */
    public function replacePedagogyKindRoles(int $tenantId, string $kind, array $roleIds, ?int $actorUserId): void
    {
        if (!$this->pedagogyRoleSetsAvailable()) {
            return;
        }
        $roleIds = array_values(array_unique(array_filter(array_map('intval', $roleIds), static fn (int $v): bool => $v > 0)));
        $this->pdo->beginTransaction();
        try {
            $del = $this->pdo->prepare('DELETE FROM tenant_pedagogy_role_sets WHERE tenant_id = ? AND pedagogy_kind = ?');
            $del->execute([$tenantId, $kind]);
            if ($roleIds !== []) {
                $ins = $this->pdo->prepare(
                    'INSERT INTO tenant_pedagogy_role_sets (tenant_id, role_id, pedagogy_kind, created_by_user_id, created_at) VALUES (?, ?, ?, ?, NOW())'
                );
                foreach ($roleIds as $rid) {
                    $ins->execute([$tenantId, $rid, $kind, $actorUserId && $actorUserId > 0 ? $actorUserId : null]);
                }
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Utilisateurs actifs du tenant ayant au moins un rôle organisationnel dont la définition est dans $slugs.
     *
     * @param list<string> $definitionSlugs
     */
    public function countUsersWithActiveRoleDefinitions(int $tenantId, array $definitionSlugs): int
    {
        if ($definitionSlugs === []) {
            return 0;
        }
        $hasTur = $this->hasTable('tenant_user_roles');
        $hasUr = $this->hasTable('user_roles');
        if (!$hasTur && !$hasUr) {
            return 0;
        }
        $count = count($definitionSlugs);
        $dSlugIn = SqlText::inPlaceholders($this->pdo, 'd.slug', $count);
        $rSlugIn = SqlText::inPlaceholders($this->pdo, 'r.slug', $count);
        $dup = array_merge($definitionSlugs, $definitionSlugs);
        $n = 0;
        if ($hasTur) {
            $sql = "SELECT COUNT(DISTINCT tur.user_id) FROM tenant_user_roles tur
                INNER JOIN roles r ON r.id = tur.role_id
                LEFT JOIN role_definitions d ON d.id = r.definition_id
                INNER JOIN users u ON u.id = tur.user_id AND u.tenant_id = ? AND " . SqlText::equalsLiteral($this->pdo, 'u.status', 'active') . "
                WHERE tur.tenant_id = ?
                  AND ({$dSlugIn} OR {$rSlugIn})
                  AND (tur.valid_until IS NULL OR tur.valid_until > NOW())";
            $st = $this->pdo->prepare($sql);
            $st->execute(array_merge([$tenantId, $tenantId], $dup));
            $n = (int) $st->fetchColumn();
        }
        if ($n > 0 || !$hasUr) {
            return $n;
        }
        $sqlUr = "SELECT COUNT(DISTINCT ur.user_id) FROM user_roles ur
            INNER JOIN roles r ON r.id = ur.role_id AND r.tenant_id = ?
            LEFT JOIN role_definitions d ON d.id = r.definition_id
            INNER JOIN users u ON u.id = ur.user_id AND u.tenant_id = ? AND " . SqlText::equalsLiteral($this->pdo, 'u.status', 'active') . "
            WHERE ({$dSlugIn} OR {$rSlugIn})";
        $stUr = $this->pdo->prepare($sqlUr);
        $stUr->execute(array_merge([$tenantId, $tenantId], $dup));

        return (int) $stUr->fetchColumn();
    }

    /**
     * Membres actifs ayant un rôle marqué « conception » pour le tenant (tenant_pedagogy_role_sets).
     */
    /**
     * Membres actifs qui ont au moins un rôle communauté / intra listé pour un type pédagogique
     * (ex. « validation des encadrants », « gouvernance des concepteurs »).
     *
     * @param 'design_trainer'|'delivery_instructor'|'instructor_certifier'|'trainer_certifier' $kind
     */
    public function countUsersWithPedagogyKindRoles(int $tenantId, string $kind): int
    {
        if (!$this->pedagogyRoleSetsAvailable()) {
            return 0;
        }
        if (!preg_match('/^[a-z_]+$/', $kind)) {
            return 0;
        }
        $sql = "SELECT COUNT(DISTINCT tur.user_id)
                FROM tenant_pedagogy_role_sets tprs
                INNER JOIN tenant_user_roles tur ON tur.tenant_id = tprs.tenant_id AND tur.role_id = tprs.role_id
                INNER JOIN users u ON u.id = tur.user_id AND u.tenant_id = tprs.tenant_id AND u.status = 'active'
                WHERE tprs.tenant_id = ? AND tprs.pedagogy_kind = ?
                  AND (tur.valid_until IS NULL OR tur.valid_until > NOW())";
        $st = $this->pdo->prepare($sql);
        $st->execute([$tenantId, $kind]);

        return (int) $st->fetchColumn();
    }

    public function countUsersWithDesignTrainerRoleSet(int $tenantId): int
    {
        $roleIds = $this->roleIdsForPedagogyKind($tenantId, 'design_trainer');
        if ($roleIds === []) {
            return 0;
        }
        $ph = implode(',', array_fill(0, count($roleIds), '?'));
        $sql = "SELECT COUNT(DISTINCT tur.user_id) FROM tenant_user_roles tur
            INNER JOIN users u ON u.id = tur.user_id AND u.tenant_id = ? AND u.status = 'active'
            WHERE tur.tenant_id = ? AND tur.role_id IN ($ph)
              AND (tur.valid_until IS NULL OR tur.valid_until > NOW())";
        $st = $this->pdo->prepare($sql);
        $st->execute(array_merge([$tenantId, $tenantId], $roleIds));

        return (int) $st->fetchColumn();
    }

    public function hasActiveInstructorEligibility(int $tenantId, int $userId, ?int $courseId = null): bool
    {
        if (!$this->hasTable('instructor_delivery_eligibility')) {
            return false;
        }
        if ($courseId !== null && $courseId > 0) {
            $st = $this->pdo->prepare(
                "SELECT 1 FROM instructor_delivery_eligibility
                 WHERE tenant_id = ? AND user_id = ? AND status = 'active'
                   AND (valid_until IS NULL OR valid_until > NOW())
                   AND (scope = 'family' OR course_id IS NULL OR course_id = ?)
                 LIMIT 1"
            );
            $st->execute([$tenantId, $userId, $courseId]);
        } else {
            $st = $this->pdo->prepare(
                "SELECT 1 FROM instructor_delivery_eligibility
                 WHERE tenant_id = ? AND user_id = ? AND status = 'active'
                   AND (valid_until IS NULL OR valid_until > NOW())
                   AND (scope = 'family' OR course_id IS NULL)
                 LIMIT 1"
            );
            $st->execute([$tenantId, $userId]);
        }

        return (bool) $st->fetchColumn();
    }

    public function tenantHasAnyInstructorEligibility(int $tenantId): bool
    {
        if (!$this->hasTable('instructor_delivery_eligibility')) {
            return false;
        }
        $st = $this->pdo->prepare(
            "SELECT 1 FROM instructor_delivery_eligibility WHERE tenant_id = ? AND status = 'active'
             AND (valid_until IS NULL OR valid_until > NOW()) LIMIT 1"
        );
        $st->execute([$tenantId]);

        return (bool) $st->fetchColumn();
    }

    /** @param list<string> $definitionSlugs */
    public function userHasOneOfDefinitions(int $tenantId, int $userId, array $definitionSlugs): bool
    {
        if ($definitionSlugs === [] || !$this->hasTable('tenant_user_roles')) {
            return false;
        }
        $count = count($definitionSlugs);
        $dSlugIn = SqlText::inPlaceholders($this->pdo, 'd.slug', $count);
        $rSlugIn = SqlText::inPlaceholders($this->pdo, 'r.slug', $count);
        $sql = "SELECT 1 FROM tenant_user_roles tur
            INNER JOIN roles r ON r.id = tur.role_id
            LEFT JOIN role_definitions d ON d.id = r.definition_id
            WHERE tur.tenant_id = ? AND tur.user_id = ?
              AND ({$dSlugIn} OR {$rSlugIn})
              AND (tur.valid_until IS NULL OR tur.valid_until > NOW()) LIMIT 1";
        $params = array_merge([$tenantId, $userId], $definitionSlugs, $definitionSlugs);
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return (bool) $st->fetchColumn();
    }

    /** Rôles tenant actifs alignés sur des définitions d’animation / conception reconnues. */
    public function userHasInstructorLikeDefinitions(int $tenantId, int $userId): bool
    {
        return $this->userHasOneOfDefinitions($tenantId, $userId, [
            'instructor', 'senior_instructor', 'trainer', 'instructor_trainer', 'trainer_of_trainers',
        ]);
    }

    public function logAudit(int $tenantId, ?int $actorUserId, string $actionCode, string $entityType, ?int $entityId, ?array $payload): void
    {
        if (!$this->hasTable('pedagogy_audit_events')) {
            return;
        }
        try {
            $st = $this->pdo->prepare(
                'INSERT INTO pedagogy_audit_events (tenant_id, actor_user_id, action_code, entity_type, entity_id, payload, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())'
            );
            $st->execute([
                $tenantId,
                $actorUserId && $actorUserId > 0 ? $actorUserId : null,
                $actionCode,
                $entityType,
                $entityId,
                $payload !== null ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
            ]);
        } catch (PDOException) {
        }
    }

    /** @return list<array<string, mixed>> */
    public function listRecentAudit(int $tenantId, int $limit = 50): array
    {
        if (!$this->hasTable('pedagogy_audit_events')) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        $st = $this->pdo->prepare(
            'SELECT * FROM pedagogy_audit_events WHERE tenant_id = ? ORDER BY id DESC LIMIT ' . $limit
        );
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listTrainerValidationTail(int $tenantId, int $limit = 30): array
    {
        if (!$this->hasTable('trainer_validation_logs')) {
            return [];
        }
        $limit = max(1, min(100, $limit));
        $st = $this->pdo->prepare(
            'SELECT tvl.*, u.display_name AS target_display_name
             FROM trainer_validation_logs tvl
             LEFT JOIN users u ON u.id = tvl.target_user_id
             WHERE tvl.tenant_id = ?
             ORDER BY tvl.id DESC LIMIT ' . $limit
        );
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
