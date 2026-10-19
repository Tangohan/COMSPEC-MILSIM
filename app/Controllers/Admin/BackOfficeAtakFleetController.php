<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Session;

/**
 * Parc ATAK des membres (/back-office/atak/parc-atak) : la fiche et la liste de /admin/parc-atak,
 * limitées aux ATAK de la communauté active.
 * La gestion voit les chiffres de jeu, l'historique des compromissions et le journal ; elle peut déclarer ou annuler
 * une compromission, révoquer ou rétablir un certificat, imposer un message, ajouter une note, déconnecter
 * et bloquer dans sa communauté. Restent à l'administration de la plateforme : retrait définitif de l'ATAK,
 * blocage dans toutes les communautés (et sa levée), vue de toutes les communautés.
 */
final class BackOfficeAtakFleetController extends AdminAtakFleetController
{
    protected const BASE = 'back-office/atak/parc-atak';

    protected function scopeTenantId(): int
    {
        // Jamais 0 : sans communauté active, la page ne montre rien plutôt que tout le parc.
        $tenantId = (int) Session::get('tenant_id');

        return $tenantId > 0 ? $tenantId : PHP_INT_MAX;
    }

    protected function viewScope(): array
    {
        return [
            'tenant' => true,
            'base' => self::BASE,
            'home' => 'back-office',
            'home_label' => 'Gestion de la communauté',
            'by' => 'la gestion de la communauté',
        ];
    }

    /** @return array<string, mixed> */
    protected function shell(): array
    {
        return ['isBackOfficeShell' => true, 'backOfficePageCss' => ['platform-admin.css', 'admin-atak-fleet.css']];
    }

    protected function compromiseSource(): string
    {
        return 'gestion';
    }
}
