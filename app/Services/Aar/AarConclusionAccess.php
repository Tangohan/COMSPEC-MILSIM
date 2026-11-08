<?php

declare(strict_types=1);

namespace App\Services\Aar;

use App\Core\Gate;
use App\Core\Session;
use App\Repositories\OrbatBilletRepository;
use Throwable;

/**
 * Qui écrit la « Conclusion du commandement » d'un compte rendu : le droit atak.aar.conclude, le niveau Gestionnaire
 * (admin.organization, aussi avant le passage de la migration) et le titulaire d'un poste d'adjoint de l'ORBAT.
 */
final class AarConclusionAccess
{
    public const PERMISSION = 'atak.aar.conclude';

    public static function allowsCurrentUser(): bool
    {
        $gate = Gate::getInstance();
        if ($gate->allows(self::PERMISSION) || $gate->allows('admin.organization')) {
            return true;
        }

        try {
            return self::holdsDeputyPost(
                (new OrbatBilletRepository())->deputyHoldersByUnitForTenant((int) Session::get('tenant_id')),
                (int) Session::get('user_id')
            );
        } catch (Throwable) {
            return false;
        }
    }

    /** @param array<int, list<int>> $holdersByUnit unit_id => user_id */
    public static function holdsDeputyPost(array $holdersByUnit, int $userId): bool
    {
        if ($userId < 1) {
            return false;
        }
        foreach ($holdersByUnit as $holders) {
            if (in_array($userId, $holders, true)) {
                return true;
            }
        }

        return false;
    }
}
