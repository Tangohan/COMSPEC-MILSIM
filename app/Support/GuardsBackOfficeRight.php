<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Request;
use App\Core\Response;

/**
 * Pour un contrôleur du back-office : chaque action publique commence par
 * `if (($denied = $this->guardBackOfficeRight($request)) !== null) { return $denied; }`.
 * Par défaut le droit exigé est l'administration de la communauté ou des accès ; un contrôleur ouvert à un droit fin
 * (le même que celui que OrganizationAdminMiddleware accepte pour son préfixe) redéfinit {@see self::backOfficeRights()}.
 */
trait GuardsBackOfficeRight
{
    /** @return list<string> un seul de ces droits suffit */
    protected function backOfficeRights(): array
    {
        return BackOfficeRightGuard::MANAGERS;
    }

    protected function guardBackOfficeRight(Request $request): ?Response
    {
        return BackOfficeRightGuard::deniedUnless($request, $this->backOfficeRights());
    }
}
