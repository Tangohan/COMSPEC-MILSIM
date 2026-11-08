<?php

declare(strict_types=1);

namespace App\Services\BackOffice\Menu;

/**
 * Compteur d'une pastille du menu back-office (déclaré dans config/back_office_menu.php).
 *
 * Appelé seulement quand la personne a l'un des droits déclarés et voit la page dans son menu. Ne compte que la
 * communauté passée en argument. Doit rester léger (un COUNT indexé) : le résultat est mis en cache peu de temps.
 */
interface BadgeProvider
{
    /**
     * @return array{count: int, important?: bool}|null null ou 0 : pas de pastille
     */
    public function badge(int $tenantId, int $userId): ?array;
}
