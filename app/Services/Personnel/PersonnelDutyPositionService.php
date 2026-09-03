<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\MemberIntegrationRepository;
use App\Repositories\RoleRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Support\SqlText;
use PDO;
use Throwable;

/**
 * Position de service unique : En formation à l’arrivée, puis En service actif.
 */
final class PersonnelDutyPositionService
{
    public const SLUG_TRAINING = 'status_in_training';
    public const SLUG_ACTIVE = 'status_active_duty';

    public const LABEL_TRAINING = 'En formation';
    public const LABEL_ACTIVE = 'En service actif';

    public function __construct(
        private UserRepository $users,
        private RoleRepository $roles,
        private MemberIntegrationRepository $integrations,
        private TenantRepository $tenants,
        private PDO $pdo,
    ) {}

    public static function labelForSlug(string $slug): string
    {
        return match ($slug) {
            self::SLUG_TRAINING => self::LABEL_TRAINING,
            self::SLUG_ACTIVE => self::LABEL_ACTIVE,
            default => '',
        };
    }

    /**
     * Règle unique, testable sans base : une seule position de service à la fois.
     * Ne rétrograde jamais « En service actif » vers « En formation ».
     */
    public static function decideSlug(?string $currentDuty, bool $isJoin, bool $forceActive, bool $hasOpenIntegration): string
    {
        if ($forceActive) {
            return self::SLUG_ACTIVE;
        }
        if ($currentDuty === self::SLUG_ACTIVE) {
            return self::SLUG_ACTIVE;
        }
        if ($isJoin) {
            return self::SLUG_TRAINING;
        }

        return $hasOpenIntegration ? self::SLUG_TRAINING : self::SLUG_ACTIVE;
    }

    /**
     * Nouveau membre : toujours « En formation » (sans retirer les fonctions).
     */
    public function applyOnJoin(int $tenantId, int $userId, int $actorUserId = 0): bool
    {
        if ($tenantId < 1 || $userId < 1 || $this->users->isServiceAccount($userId)) {
            return false;
        }
        $current = $this->currentDutySlug($tenantId, $userId);
        $slug = self::decideSlug($current, true, false, false);

        return $this->setDutySlug($tenantId, $userId, $slug, $actorUserId, false);
    }

    /**
     * Parcours d’intégration terminé, ou action organisateur.
     */
    public function applyActiveDuty(int $tenantId, int $userId, int $actorUserId = 0, bool $bypassMinimumDuration = false): bool
    {
        if ($tenantId < 1 || $userId < 1 || $this->users->isServiceAccount($userId)) {
            return false;
        }
        if (!$bypassMinimumDuration) {
            if (!$this->trainingMinimumReached($tenantId, $userId)) {
                return false;
            }
            try {
                if ($this->integrations->tablesExist() && $this->integrations->findActiveForUser($tenantId, $userId) !== null) {
                    return false;
                }
            } catch (Throwable) {
                return false;
            }
        }

        return $this->setDutySlug($tenantId, $userId, self::SLUG_ACTIVE, $actorUserId, true);
    }

    /**
     * Membres déjà présents : En formation s’ils ont un parcours ouvert, sinon En service actif.
     * Ne rétrograde jamais quelqu’un déjà « En service actif ».
     */
    public function backfillTenant(int $tenantId, int $actorUserId = 0): int
    {
        if ($tenantId < 1) {
            return 0;
        }
        $rows = $this->users->listForTenant($tenantId, null, 'active', null, 500, 0, true);
        $changed = 0;
        foreach ($rows as $row) {
            $userId = (int) ($row['id'] ?? 0);
            if ($userId < 1) {
                continue;
            }
            if ($this->backfillOne($tenantId, $userId, $actorUserId)) {
                $changed++;
            }
        }

        return $changed;
    }

    public function backfillOne(int $tenantId, int $userId, int $actorUserId = 0): bool
    {
        if ($this->users->isServiceAccount($userId)) {
            return false;
        }
        $current = $this->currentDutySlug($tenantId, $userId);
        $open = null;
        try {
            $open = $this->integrations->tablesExist()
                ? $this->integrations->findActiveForUser($tenantId, $userId)
                : null;
        } catch (Throwable) {
            $open = null;
        }
        $mustRemainTraining = $open !== null || !$this->trainingMinimumReached($tenantId, $userId);
        $slug = self::decideSlug($current, false, false, $mustRemainTraining);

        return $this->setDutySlug($tenantId, $userId, $slug, $actorUserId, true);
    }

    public function remainingTrainingDays(int $tenantId, int $userId): int
    {
        $settings = PersonnelLifecycleSettings::resolve($this->tenants->getSettings($tenantId));
        $required = $settings['training_days'];
        if ($required === 0) {
            return 0;
        }
        $user = $this->users->findById($userId, $tenantId);
        $startedAt = strtotime((string) ($user['created_at'] ?? '')) ?: time();

        return self::remainingDaysFromStart($required, $startedAt, time());
    }

    public static function remainingDaysFromStart(int $requiredDays, int $startedAt, int $now): int
    {
        $elapsed = max(0, (int) floor(($now - $startedAt) / 86400));

        return max(0, min(3650, $requiredDays) - $elapsed);
    }

    private function trainingMinimumReached(int $tenantId, int $userId): bool
    {
        return $this->remainingTrainingDays($tenantId, $userId) === 0;
    }

    public function currentDutySlug(int $tenantId, int $userId): ?string
    {
        $ids = $this->users->listOrganizationRoleIdsForUser($userId, $tenantId);
        if ($ids === []) {
            return null;
        }
        $map = $this->dutyRoleIdsBySlug($tenantId, false);
        foreach ([self::SLUG_ACTIVE, self::SLUG_TRAINING] as $slug) {
            $rid = $map[$slug] ?? 0;
            if ($rid > 0 && in_array($rid, $ids, true)) {
                return $slug;
            }
        }

        return null;
    }

    public function currentDutyLabel(int $tenantId, int $userId): string
    {
        $slug = $this->currentDutySlug($tenantId, $userId);

        return $slug !== null ? self::labelForSlug($slug) : '';
    }

    public function countActiveMembersWithoutDuty(int $tenantId): int
    {
        if ($tenantId < 1) {
            return 0;
        }
        $have = array_flip($this->userIdsWithDuty($tenantId));
        $rows = $this->users->listForTenant($tenantId, null, 'active', null, 500, 0, true);
        $missing = 0;
        foreach ($rows as $row) {
            $userId = (int) ($row['id'] ?? 0);
            if ($userId > 0 && !isset($have[$userId])) {
                $missing++;
            }
        }

        return $missing;
    }

    /**
     * @return list<int>
     */
    private function userIdsWithDuty(int $tenantId): array
    {
        $ids = [];
        try {
            $slugIn = SqlText::inLiterals($this->pdo, 'r.slug', [self::SLUG_TRAINING, self::SLUG_ACTIVE]);
            $st = $this->pdo->prepare(
                "SELECT ur.user_id FROM user_roles ur
                 INNER JOIN roles r ON r.id = ur.role_id AND r.tenant_id = ?
                 WHERE {$slugIn}"
            );
            $st->execute([$tenantId]);
            $ids = array_map('intval', $st->fetchAll(\PDO::FETCH_COLUMN) ?: []);
        } catch (Throwable) {
            $ids = [];
        }
        try {
            $slugIn = SqlText::inLiterals($this->pdo, 'r.slug', [self::SLUG_TRAINING, self::SLUG_ACTIVE]);
            $st = $this->pdo->prepare(
                "SELECT tur.user_id FROM tenant_user_roles tur
                 INNER JOIN roles r ON r.id = tur.role_id AND r.tenant_id = tur.tenant_id
                 WHERE tur.tenant_id = ? AND {$slugIn}"
            );
            $st->execute([$tenantId]);
            $ids = array_values(array_unique(array_merge(
                $ids,
                array_map('intval', $st->fetchAll(\PDO::FETCH_COLUMN) ?: [])
            )));
        } catch (Throwable) {
        }

        return $ids;
    }

    /**
     * @return array<string, int> slug => role id
     */
    public function dutyRoleIdsBySlug(int $tenantId, bool $createMissing = true): array
    {
        $out = [];
        foreach ([self::SLUG_TRAINING => self::LABEL_TRAINING, self::SLUG_ACTIVE => self::LABEL_ACTIVE] as $slug => $label) {
            $id = $this->roles->getIdBySlug($tenantId, $slug);
            if (($id === null || $id < 1) && $createMissing) {
                $id = $this->createDutyRole($tenantId, $slug, $label);
            }
            if ($id !== null && $id > 0) {
                $out[$slug] = $id;
            }
        }

        return $out;
    }

    private function setDutySlug(int $tenantId, int $userId, string $slug, int $actorUserId, bool $allowReplace): bool
    {
        $map = $this->dutyRoleIdsBySlug($tenantId);
        $wantId = $map[$slug] ?? 0;
        if ($wantId < 1) {
            return false;
        }
        $current = $this->currentDutySlug($tenantId, $userId);
        if ($current === $slug) {
            return false;
        }
        if ($current === self::SLUG_ACTIVE && $slug === self::SLUG_TRAINING && !$allowReplace) {
            return false;
        }
        $dutyIds = array_values($map);
        $kept = [];
        foreach ($this->users->listOrganizationRoleIdsForUser($userId, $tenantId) as $rid) {
            if (!in_array($rid, $dutyIds, true)) {
                $kept[] = $rid;
            }
        }
        $kept[] = $wantId;
        $this->users->syncOrganizationRoles($userId, $tenantId, $kept, $actorUserId > 0 ? $actorUserId : null, true);

        return true;
    }

    private function createDutyRole(int $tenantId, string $slug, string $name): int
    {
        try {
            $st = $this->pdo->prepare(
                "INSERT INTO roles (tenant_id, name, slug, description, is_system, is_locked, role_layer, semantic_tier, is_visual_only, display_group, display_priority, created_at)
                 VALUES (?, ?, ?, ?, 1, 0, 'community', 'status', 1, 3, ?, NOW())"
            );
            $priority = $slug === self::SLUG_ACTIVE ? 70 : 20;
            $st->execute([
                $tenantId,
                $name,
                $slug,
                $slug === self::SLUG_ACTIVE
                    ? 'Engagement opérationnel à plein temps.'
                    : 'Parcours de formation en cours.',
                $priority,
            ]);

            return (int) $this->pdo->lastInsertId();
        } catch (Throwable) {
            $created = $this->roles->createOrganizationRole($tenantId, $name, $slug, $name);
            if ($created > 0) {
                try {
                    $this->pdo->prepare(
                        "UPDATE roles SET semantic_tier = 'status', is_visual_only = 1, display_group = 3 WHERE id = ? AND tenant_id = ?"
                    )->execute([$created, $tenantId]);
                } catch (Throwable) {
                }
            }

            return $created;
        }
    }
}
