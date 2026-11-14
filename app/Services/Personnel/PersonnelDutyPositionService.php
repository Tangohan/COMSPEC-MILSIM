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
 * Position administrative unique par membre : En formation à l’arrivée, puis En service actif.
 * Le responsable peut aussi placer un membre En service non actif ou En service statutaire de renforcement.
 * Chaque position est un rôle d’affichage de la communauté (semantic_tier « status »), créé à la demande.
 */
final class PersonnelDutyPositionService
{
    public const SLUG_TRAINING = 'status_in_training';
    public const SLUG_ACTIVE = 'status_active_duty';
    public const SLUG_INACTIVE = 'status_inactive_duty';
    public const SLUG_REINFORCEMENT = 'status_statutory_reinforcement';

    public const LABEL_TRAINING = 'En formation';
    public const LABEL_ACTIVE = 'En service actif';
    public const LABEL_INACTIVE = 'En service non actif';
    public const LABEL_REINFORCEMENT = 'En service statutaire de renforcement';

    /**
     * Les positions, dans l’ordre du parcours, avec le texte montré aux membres.
     *
     * @return array<string, array{label: string, short: string, help: string, mission: bool, priority: int}>
     */
    public static function positions(): array
    {
        return [
            self::SLUG_TRAINING => [
                'label' => self::LABEL_TRAINING,
                'short' => 'Parcours de formation en cours.',
                'help' => 'Nouvelle recrue en parcours d’intégration. Elle est en service pendant l’instruction et les stages, et ne part en mission qu’encadrée.',
                'mission' => false,
                'priority' => 20,
            ],
            self::SLUG_ACTIVE => [
                'label' => self::LABEL_ACTIVE,
                'short' => 'Engagement opérationnel à plein temps.',
                'help' => 'Membre pleinement engagé. Il peut être en mission (opération) ou en service (instruction, entraînement, permanence, liaison).',
                'mission' => true,
                'priority' => 70,
            ],
            self::SLUG_INACTIVE => [
                'label' => self::LABEL_INACTIVE,
                'short' => 'Dans les effectifs, temporairement non engagé.',
                'help' => 'Membre qui reste dans les effectifs avec son dossier, mais n’est pas engagé pour le moment (congé, disponibilité, absence longue). Il n’est ni en mission ni en service.',
                'mission' => false,
                'priority' => 40,
            ],
            self::SLUG_REINFORCEMENT => [
                'label' => self::LABEL_REINFORCEMENT,
                'short' => 'Rappelé pour renforcer une unité.',
                'help' => 'Membre (souvent réserviste ou ancien) rappelé pour renforcer une unité sur une période ou une opération. Pendant le renfort, il est en mission ou en service comme un membre actif.',
                'mission' => true,
                'priority' => 60,
            ],
        ];
    }

    /** @return list<string> */
    public static function slugs(): array
    {
        return array_keys(self::positions());
    }

    /** @return list<string> */
    public static function labels(): array
    {
        return array_values(array_map(static fn (array $p): string => $p['label'], self::positions()));
    }

    public static function isPositionSlug(string $slug): bool
    {
        return isset(self::positions()[strtolower(trim($slug))]);
    }

    public function __construct(
        private UserRepository $users,
        private RoleRepository $roles,
        private MemberIntegrationRepository $integrations,
        private TenantRepository $tenants,
        private PDO $pdo,
    ) {}

    public static function labelForSlug(string $slug): string
    {
        return self::positions()[$slug]['label'] ?? '';
    }

    /**
     * Règle unique, testable sans base : une seule position de service à la fois.
     * Ne rétrograde jamais « En service actif » vers « En formation », et ne touche pas une position
     * posée par un responsable (non actif, renforcement).
     */
    public static function decideSlug(?string $currentDuty, bool $isJoin, bool $forceActive, bool $hasOpenIntegration): string
    {
        if ($forceActive) {
            return self::SLUG_ACTIVE;
        }
        if ($currentDuty !== null && $currentDuty !== self::SLUG_TRAINING && self::isPositionSlug($currentDuty)) {
            return $currentDuty;
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
     * Choix d’un responsable depuis la fiche ou le registre des droits.
     * Le passage de « En formation » à « En service actif » garde ses règles (durée minimale, parcours clos).
     *
     * @return bool vrai si la position a changé
     */
    public function applyPosition(int $tenantId, int $userId, string $slug, int $actorUserId = 0): bool
    {
        if ($tenantId < 1 || $userId < 1 || !self::isPositionSlug($slug) || $this->users->isServiceAccount($userId)) {
            return false;
        }
        if ($slug === self::SLUG_ACTIVE && $this->currentDutySlug($tenantId, $userId) === self::SLUG_TRAINING) {
            return $this->applyActiveDuty($tenantId, $userId, $actorUserId);
        }

        return $this->setDutySlug($tenantId, $userId, $slug, $actorUserId, true);
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
        // Une seule position attendue ; si un ancien état en laisse deux, la plus engagée l’emporte.
        foreach ([self::SLUG_ACTIVE, self::SLUG_REINFORCEMENT, self::SLUG_INACTIVE, self::SLUG_TRAINING] as $slug) {
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
            $slugIn = SqlText::inLiterals($this->pdo, 'r.slug', self::slugs());
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
            $slugIn = SqlText::inLiterals($this->pdo, 'r.slug', self::slugs());
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
        foreach (self::positions() as $slug => $position) {
            $label = $position['label'];
            $id = $this->roles->getIdBySlug($tenantId, $slug);
            // Les deux positions du parcours existent partout ; les autres naissent à leur premier usage.
            $always = $slug === self::SLUG_TRAINING || $slug === self::SLUG_ACTIVE;
            if (($id === null || $id < 1) && $createMissing && $always) {
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
        if ($wantId < 1 && self::isPositionSlug($slug)) {
            $wantId = $this->createDutyRole($tenantId, $slug, self::labelForSlug($slug));
            if ($wantId > 0) {
                $map[$slug] = $wantId;
            }
        }
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
            $position = self::positions()[$slug] ?? ['short' => '', 'priority' => 50];
            $st->execute([
                $tenantId,
                $name,
                $slug,
                $position['short'],
                $position['priority'],
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
