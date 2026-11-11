<?php

declare(strict_types=1);

namespace App\Services\AtakToc;

/**
 * Droits du poste TOC web et des plans d'opération.
 *  - atak.toc.use    : tenir le poste (TOC web), créer et éditer des plans, les envoyer en jeu ;
 *  - atak.toc.manage : configurer le poste (modèles de la communauté, plans de tous, remise à zéro de la salle).
 * L'administration de la communauté (admin.organization) vaut les deux ; l'administrateur de la plateforme passe
 * partout (Gate). Plans : l'auteur, puis selon le partage (privé, unité, communauté) et les partages nominatifs.
 * Le lien de lecture (jeton) ouvre le plan en lecture seule à tout membre connecté de la même communauté.
 */
final class TocAccess
{
    public const USE = 'atak.toc.use';
    public const MANAGE = 'atak.toc.manage';

    /** @var callable(string): bool */
    private $can;

    /** @var callable(int, int): list<int> */
    private $unitIds;

    /** @var array<string, list<int>> */
    private array $unitCache = [];

    /**
     * @param callable(string): bool|null $can
     * @param callable(int, int): list<int>|null $unitIds unités actives d'un membre (tenant, membre)
     */
    public function __construct(?callable $can = null, ?callable $unitIds = null)
    {
        $this->can = $can ?? static fn (string $slug): bool => function_exists('can') && can($slug);
        $this->unitIds = $unitIds ?? static function (int $tenantId, int $userId): array {
            try {
                return array_map('intval', (new \App\Repositories\UnitRepository())->unitIdsForUser($tenantId, $userId));
            } catch (\Throwable) {
                return [];
            }
        };
    }

    public function canManage(): bool
    {
        return ($this->can)(self::MANAGE) || ($this->can)('admin.organization');
    }

    public function canUse(): bool
    {
        return ($this->can)(self::USE) || $this->canManage();
    }

    /** @return list<int> */
    public function unitsOf(int $tenantId, int $userId): array
    {
        $k = $tenantId . ':' . $userId;

        return $this->unitCache[$k] ??= ($this->unitIds)($tenantId, $userId);
    }

    /**
     * @param array<string, mixed> $plan
     * @param list<array{scope:string, ref_id:int, can_edit:bool}> $shares
     */
    public function canView(array $plan, int $tenantId, int $userId, array $shares = []): bool
    {
        if ((int) ($plan['tenant_id'] ?? 0) !== $tenantId || $tenantId < 1 || $userId < 1) {
            return false;
        }
        if ($this->canManage() || (int) $plan['owner_user_id'] === $userId) {
            return true;
        }
        if (!$this->canUse()) {
            return false;
        }

        return $this->sharedWith($plan, $tenantId, $userId, $shares, false);
    }

    /**
     * @param array<string, mixed> $plan
     * @param list<array{scope:string, ref_id:int, can_edit:bool}> $shares
     */
    public function canEdit(array $plan, int $tenantId, int $userId, array $shares = []): bool
    {
        if ((int) ($plan['tenant_id'] ?? 0) !== $tenantId || $tenantId < 1 || $userId < 1) {
            return false;
        }
        if ($this->canManage() || (int) $plan['owner_user_id'] === $userId) {
            return true;
        }
        if (!$this->canUse() || !empty($plan['locked'])) {
            return false;
        }
        if (!empty($plan['is_template'])) {
            return false;
        }

        return $this->sharedWith($plan, $tenantId, $userId, $shares, true);
    }

    /** Auteur du plan ou configuration du poste : verrous, partage, suppression. @param array<string, mixed> $plan */
    public function canAdminister(array $plan, int $tenantId, int $userId): bool
    {
        return (int) ($plan['tenant_id'] ?? 0) === $tenantId && ($this->canManage() || (int) $plan['owner_user_id'] === $userId);
    }

    /**
     * @param array<string, mixed> $plan
     * @param list<array{scope:string, ref_id:int, can_edit:bool}> $shares
     */
    private function sharedWith(array $plan, int $tenantId, int $userId, array $shares, bool $edit): bool
    {
        $sharing = (string) ($plan['sharing'] ?? 'private');
        if ($sharing === 'community') {
            return true;
        }
        $units = null;
        if ($sharing === 'unit' && ($plan['unit_id'] ?? null) !== null) {
            $units = $this->unitsOf($tenantId, $userId);
            if (in_array((int) $plan['unit_id'], $units, true)) {
                return true;
            }
        }
        foreach ($shares as $s) {
            if ($edit && !$s['can_edit']) {
                continue;
            }
            if ($s['scope'] === 'user' && $s['ref_id'] === $userId) {
                return true;
            }
            if ($s['scope'] === 'unit') {
                $units ??= $this->unitsOf($tenantId, $userId);
                if (in_array($s['ref_id'], $units, true)) {
                    return true;
                }
            }
        }

        return false;
    }
}
