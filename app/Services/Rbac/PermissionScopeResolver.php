<?php

declare(strict_types=1);

namespace App\Services\Rbac;

/**
 * Décide où placer une habilitation issue d'une affectation de rôle.
 *
 * Un rôle attribué sans unité vaut pour toute l'organisation, y compris pour
 * les actions dont le périmètre technique est « unit ». Une affectation à une
 * unité ne doit en revanche jamais promouvoir un droit tenant/global.
 */
final class PermissionScopeResolver
{
    /** @return array{flat: bool, unit_id: ?int} */
    public static function resolve(string $scope, ?int $orgUnitId): array
    {
        if ($orgUnitId === null || $orgUnitId < 1) {
            return ['flat' => true, 'unit_id' => null];
        }

        if ($scope === 'unit') {
            return ['flat' => false, 'unit_id' => $orgUnitId];
        }

        return ['flat' => false, 'unit_id' => null];
    }
}
