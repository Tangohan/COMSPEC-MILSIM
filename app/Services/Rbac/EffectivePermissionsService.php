<?php

declare(strict_types=1);

namespace App\Services\Rbac;

use App\Core\Container;
use App\Core\Gate;
use App\Repositories\UserRepository;
use Closure;

/**
 * « Droits effectifs » d'un membre, en lecture seule.
 *
 * Aucun calcul propre : on demande à RbacService de charger les droits du membre dans la porte (niveaux d'accès,
 * unités, droits individuels, droits du site, administrateur plateforme), on lit le résultat, puis on rend la porte
 * de la requête en cours telle qu'on l'a trouvée (même méthode qu'AtakGameAdminService::role). Le résultat est donc
 * celui de RbacService, implications comprises, et rien n'est écrit.
 */
final class EffectivePermissionsService
{
    /**
     * @param Closure(array<string, mixed>): void|null $loader Remplace RbacService (tests) : reçoit la ligne du membre et pose l'état dans Gate::getInstance()
     */
    public function __construct(
        private ?RbacService $rbac = null,
        private ?UserRepository $users = null,
        private ?Closure $loader = null,
    ) {}

    /**
     * @param array<string, mixed> $userRow Ligne users (id, email, role_id, tenant_id, is_platform_admin…)
     * @param int|null $tenantId Communauté dans laquelle lire les droits ; sinon celle de la ligne
     */
    public function forUserRow(array $userRow, ?int $tenantId = null): EffectivePermissions
    {
        if ($tenantId !== null && $tenantId > 0) {
            $userRow = ['tenant_id' => $tenantId] + $userRow;
        }
        $gate = Gate::getInstance();
        $before = [$gate->permissionSlugs(), $gate->getUnitPermissionMap(), $gate->isPlatformAdmin()];
        try {
            if ($this->loader !== null) {
                ($this->loader)($userRow);
            } else {
                $this->rbac ??= Container::get(RbacService::class);
                $this->users ??= new UserRepository();
                $this->rbac->setPermissionsForGateFromUserRow($userRow, $this->users);
            }

            return EffectivePermissions::fromGate($gate);
        } finally {
            $gate->setFullRbacState($before[0], $before[1]);
            $gate->setPlatformAdmin($before[2]);
        }
    }

    /** Droits d'un membre de la communauté ; null si le compte n'y existe pas. */
    public function forUser(int $userId, int $tenantId): ?EffectivePermissions
    {
        if ($userId < 1 || $tenantId < 1) {
            return null;
        }
        $this->users ??= new UserRepository();
        $row = $this->users->findById($userId, $tenantId);
        if ($row === null) {
            return null;
        }

        return $this->forUserRow($row, $tenantId);
    }

    /** Droits de la session en cours, sans rien recharger. */
    public function forCurrentSession(): EffectivePermissions
    {
        return EffectivePermissions::fromGate(Gate::getInstance());
    }
}
