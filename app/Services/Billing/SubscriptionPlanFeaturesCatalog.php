<?php

declare(strict_types=1);

namespace App\Services\Billing;

/**
 * Catalogue commercial des fonctionnalités de formule (libellés humains + défauts par palier).
 * Source de vérité pour seed migration, formulaire admin et documentation FEATURE-TIERS.
 */
final class SubscriptionPlanFeaturesCatalog
{
    public const BOOL_FEATURES = [
        'forum',
        'documents',
        'messages',
        'personnel',
        'training',
        'equipment',
        'events',
        'courrier',
        'operational_board',
        'recruitment',
        'cooperation',
        'alerts',
        'atak',
        'analytics',
        'advanced_integrations',
        'community_create',
    ];

    public const INT_FEATURES = [
        'max_members',
        'max_training_courses',
    ];

    /**
     * @return array<string, array{label: string, help: string, type: 'bool'|'int'}>
     */
    public static function definitions(): array
    {
        return [
            'forum' => [
                'label' => 'Forum communauté',
                'help' => 'Sujets, réponses et moderation du forum.',
                'type' => 'bool',
            ],
            'documents' => [
                'label' => 'Documents',
                'help' => 'Bibliothèque documentaire et pieces jointes.',
                'type' => 'bool',
            ],
            'messages' => [
                'label' => 'Messagerie interne',
                'help' => 'Echanges prives entre membres de la communauté.',
                'type' => 'bool',
            ],
            'personnel' => [
                'label' => 'Effectifs et ORBAT',
                'help' => 'Fiches personnel, grades, unites et organigramme.',
                'type' => 'bool',
            ],
            'training' => [
                'label' => 'Formations',
                'help' => 'Parcours LMS, Studio et suivi des apprenants.',
                'type' => 'bool',
            ],
            'equipment' => [
                'label' => 'Equipement',
                'help' => 'Manuels et fiches materiel.',
                'type' => 'bool',
            ],
            'events' => [
                'label' => 'Evenements et calendrier',
                'help' => 'Creation d’evenements, RSVP et planning (quota possible en gratuit).',
                'type' => 'bool',
            ],
            'courrier' => [
                'label' => 'Courrier officiel',
                'help' => 'Bureau courrier, modeles, validation et archivage.',
                'type' => 'bool',
            ],
            'operational_board' => [
                'label' => 'Mur operationnel',
                'help' => 'Permanences, consignes et tableau operationnel.',
                'type' => 'bool',
            ],
            'recruitment' => [
                'label' => 'Recrutement et enrôlement',
                'help' => 'Candidatures, offres et parcours d’enrôlement.',
                'type' => 'bool',
            ],
            'cooperation' => [
                'label' => 'Cooperation inter-unites',
                'help' => 'Missions et annonces entre communautés.',
                'type' => 'bool',
            ],
            'alerts' => [
                'label' => 'Alertes communauté',
                'help' => 'Alertes et bandeaux d’information pour les membres.',
                'type' => 'bool',
            ],
            'atak' => [
                'label' => 'Carte tactique ATAK',
                'help' => 'Carte, unites terrain et outils ATAK.',
                'type' => 'bool',
            ],
            'analytics' => [
                'label' => 'Analytics organisation',
                'help' => 'Tableaux de bord et metriques de pilotage.',
                'type' => 'bool',
            ],
            'advanced_integrations' => [
                'label' => 'Integrations avancees',
                'help' => 'Cles API et connecteurs externes.',
                'type' => 'bool',
            ],
            'community_create' => [
                'label' => 'Creation de communauté',
                'help' => 'Autorise la creation d’une nouvelle communauté via ce palier.',
                'type' => 'bool',
            ],
            'max_members' => [
                'label' => 'Nombre max. de membres',
                'help' => 'Plafond de comptes dans la communauté (0 = illimite).',
                'type' => 'int',
            ],
            'max_training_courses' => [
                'label' => 'Nombre max. de parcours',
                'help' => 'Plafond de formations catalogue (0 = illimite).',
                'type' => 'int',
            ],
        ];
    }

    /**
     * Defauts features_json par slug de plan.
     *
     * @return array<string, array<string, bool|int>>
     */
    public static function defaultsByPlanSlug(): array
    {
        return [
            'free' => [
                'forum' => true,
                'documents' => true,
                'messages' => true,
                'personnel' => true,
                'training' => true,
                'equipment' => true,
                'events' => false,
                'courrier' => false,
                'operational_board' => false,
                'recruitment' => true,
                'cooperation' => false,
                'alerts' => false,
                'atak' => false,
                'analytics' => false,
                'advanced_integrations' => false,
                'community_create' => true,
                'max_members' => 10,
                'max_training_courses' => 5,
            ],
            'standard' => [
                'forum' => true,
                'documents' => true,
                'messages' => true,
                'personnel' => true,
                'training' => true,
                'equipment' => true,
                'events' => true,
                'courrier' => true,
                'operational_board' => true,
                'recruitment' => true,
                'cooperation' => false,
                'alerts' => true,
                'atak' => true,
                'analytics' => false,
                'advanced_integrations' => false,
                'community_create' => true,
                'max_members' => 25,
                'max_training_courses' => 25,
            ],
            'pro' => [
                'forum' => true,
                'documents' => true,
                'messages' => true,
                'personnel' => true,
                'training' => true,
                'equipment' => true,
                'events' => true,
                'courrier' => true,
                'operational_board' => true,
                'recruitment' => true,
                'cooperation' => true,
                'alerts' => true,
                'atak' => true,
                'analytics' => true,
                'advanced_integrations' => false,
                'community_create' => true,
                'max_members' => 50,
                'max_training_courses' => 100,
            ],
            'pro_plus' => [
                'forum' => true,
                'documents' => true,
                'messages' => true,
                'personnel' => true,
                'training' => true,
                'equipment' => true,
                'events' => true,
                'courrier' => true,
                'operational_board' => true,
                'recruitment' => true,
                'cooperation' => true,
                'alerts' => true,
                'atak' => true,
                'analytics' => true,
                'advanced_integrations' => true,
                'community_create' => true,
                'max_members' => 80,
                'max_training_courses' => 0,
            ],
        ];
    }

    /**
     * @return array<string, bool|int>
     */
    public static function defaultsForSlug(string $slug): array
    {
        $all = self::defaultsByPlanSlug();

        return $all[$slug] ?? $all['free'];
    }

    /** Libelle court pour la page d’upgrade. */
    public static function featureLabel(string $featureKey): string
    {
        $defs = self::definitions();

        return (string) ($defs[$featureKey]['label'] ?? $featureKey);
    }

    /**
     * Construit features_json a partir des cases a cocher / champs numeriques du formulaire admin.
     *
     * @param array<string, mixed> $input
     * @param array<string, mixed> $existing
     *
     * @return array<string, bool|int>
     */
    public static function buildFeaturesFromForm(array $input, array $existing = []): array
    {
        $out = $existing;
        foreach (self::BOOL_FEATURES as $key) {
            $out[$key] = !empty($input['feature_' . $key]);
        }
        foreach (self::INT_FEATURES as $key) {
            $raw = $input['feature_' . $key] ?? ($existing[$key] ?? 0);
            $out[$key] = max(0, (int) $raw);
        }

        return $out;
    }
}
