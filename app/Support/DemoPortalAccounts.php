<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Comptes de démonstration (entité demo-comspec) — jamais des administrateurs de la plateforme.
 * Source unique pour le seed CLI et l’annonce après validation du code d’accès démo.
 */
final class DemoPortalAccounts
{
    public const SHARED_PASSWORD = 'demo';

    public const TENANT_SLUG = 'demo-comspec';

    public const TENANT_NAME = 'Force Démo COMSPEC';

    /**
     * Comptes présentés au visiteur après validation du code d’accès.
     *
     * @return list<array{email: string, role_label: string, hint: string}>
     */
    public static function announcedAccounts(): array
    {
        return [
            [
                'email' => 'gestionnaire@demo.local',
                'role_label' => 'Gestionnaire d’organisation',
                'hint' => 'Piloture de l’entité : paramètres, membres, vue d’ensemble.',
            ],
            [
                'email' => 'admin-orga@demo.local',
                'role_label' => 'Gestionnaire administratif',
                'hint' => 'Administration quotidienne de l’organisation (sans droits plateforme).',
            ],
            [
                'email' => 'instructeur@demo.local',
                'role_label' => 'Instructeur',
                'hint' => 'Formations, parcours et suivi pédagogique.',
            ],
            [
                'email' => 'operateur@demo.local',
                'role_label' => 'Opérateur',
                'hint' => 'Compte membre : forum, documents, formations selon affectation.',
            ],
        ];
    }

    /**
     * Toutes les adresses @demo.local créées par le seed (pour purge des rôles site).
     *
     * @return list<string>
     */
    public static function allDemoEmails(): array
    {
        $emails = array_column(self::announcedAccounts(), 'email');
        $extra = [
            'cadre@demo.local',
            'rh@demo.local',
            'recruteur@demo.local',
            'formateur@demo.local',
            'comms@demo.local',
            'opsan@demo.local',
            'logistique@demo.local',
            'rto@demo.local',
            'visiteur@demo.local',
            'seed-bootstrap@demo.local',
        ];

        return array_values(array_unique(array_merge($emails, $extra)));
    }

    public static function isDemoEmail(string $email): bool
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return false;
        }
        if (str_ends_with($email, '@demo.local')) {
            return true;
        }

        return in_array($email, array_map('strtolower', self::allDemoEmails()), true);
    }
}
