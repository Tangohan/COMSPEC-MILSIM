<?php

declare(strict_types=1);

namespace App\Services\Rbac;

use App\Authorization\PermissionImplication;
use App\Authorization\SystemReservedPermissions;
use App\Core\Gate;

/**
 * Droits effectifs d'un membre : l'union calculée par RbacService, figée en lecture seule.
 *
 * Les réponses viennent d'une instance de Gate : implications (PermissionImplication), droits du site et
 * administrateur de la plateforme se comportent donc exactement comme pour la session du membre.
 */
final class EffectivePermissions
{
    public const SOURCE_DIRECT = 'direct';
    public const SOURCE_IMPLIED = 'implied';
    public const SOURCE_PLATFORM_ADMIN = 'platform_admin';
    public const SOURCE_UNIT = 'unit';
    public const SOURCE_NONE = 'none';

    private Gate $gate;

    /**
     * @param list<string> $flat Droits valables partout dans la communauté (union RbacService)
     * @param array<string, list<int>> $unitScoped Droits valables seulement dans certaines unités
     */
    public function __construct(array $flat, array $unitScoped, bool $platformAdmin)
    {
        $this->gate = new Gate();
        $this->gate->setFullRbacState($flat, $unitScoped);
        $this->gate->setPlatformAdmin($platformAdmin);
    }

    public static function fromGate(Gate $gate): self
    {
        return new self($gate->permissionSlugs(), $gate->getUnitPermissionMap(), $gate->isPlatformAdmin());
    }

    /** @return list<string> */
    public function flat(): array
    {
        return $this->gate->permissionSlugs();
    }

    /** @return array<string, list<int>> */
    public function unitScoped(): array
    {
        return $this->gate->getUnitPermissionMap();
    }

    public function isPlatformAdmin(): bool
    {
        return $this->gate->isPlatformAdmin();
    }

    public function allows(string $permission): bool
    {
        return $this->gate->allows($permission);
    }

    public function allowsInUnit(string $permission, ?int $unitId): bool
    {
        return $this->gate->allowsWithUnitContext($permission, $unitId);
    }

    /**
     * D'où vient le droit : accordé tel quel, déduit d'un autre, administrateur plateforme, limité à des unités, ou absent.
     */
    public function source(string $permission): string
    {
        if (!$this->gate->allows($permission)) {
            return isset($this->gate->getUnitPermissionMap()[$permission]) ? self::SOURCE_UNIT : self::SOURCE_NONE;
        }
        if (!SystemReservedPermissions::isReserved($permission)
            && in_array($permission, SystemReservedPermissions::filter($this->gate->permissionSlugs()), true)) {
            return self::SOURCE_DIRECT;
        }
        if (PermissionImplication::isGranted($this->gate->permissionSlugs(), $permission)) {
            return self::SOURCE_IMPLIED;
        }

        return self::SOURCE_PLATFORM_ADMIN;
    }

    /**
     * Parmi une liste de droits (le catalogue, par exemple), ceux que le membre a réellement, dans l'ordre reçu.
     *
     * @param list<string> $permissions
     * @return list<string>
     */
    public function allowedAmong(array $permissions): array
    {
        $out = [];
        foreach ($permissions as $permission) {
            if ($this->gate->allows((string) $permission)) {
                $out[] = (string) $permission;
            }
        }

        return $out;
    }

    /**
     * @return array{flat: list<string>, unit_scoped: array<string, list<int>>, platform_admin: bool}
     */
    public function toArray(): array
    {
        return [
            'flat' => $this->flat(),
            'unit_scoped' => $this->unitScoped(),
            'platform_admin' => $this->isPlatformAdmin(),
        ];
    }
}
