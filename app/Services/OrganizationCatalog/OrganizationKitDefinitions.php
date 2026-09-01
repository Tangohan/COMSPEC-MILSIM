<?php

declare(strict_types=1);

namespace App\Services\OrganizationCatalog;

/**
 * Modèles officiels Athena (source PHP versionnée). Jamais d’identifiants de communauté.
 */
final class OrganizationKitDefinitions
{
    public const INFANTRY_LIGHT = 'official.infantry_light';
    public const GAMING_COMMUNITY = 'official.gaming_community';

    /**
     * @return list<array<string, mixed>>
     */
    public static function officialKits(): array
    {
        return [
            self::infantryLight(),
            self::gamingCommunity(),
        ];
    }

    /**
     * @return list<string>
     */
    public static function officialCodes(): array
    {
        return [self::INFANTRY_LIGHT, self::GAMING_COMMUNITY];
    }

    /**
     * @param array<string, mixed> $definition
     */
    public static function volumeLabel(array $definition): string
    {
        $units = count($definition['units'] ?? []);
        $functions = count($definition['job_roles'] ?? []);
        $roles = count($definition['roles'] ?? []);
        $bits = [];
        $bits[] = $units === 1 ? '1 unité' : $units . ' unités';
        $bits[] = $functions === 1 ? '1 fonction' : $functions . ' fonctions';
        $bits[] = $roles === 1 ? '1 rôle' : $roles . ' rôles';
        if (!empty($definition['grade_system_code'])) {
            $bits[] = '1 système de grades';
        }

        return implode(' · ', $bits);
    }

    /**
     * @return array<string, mixed>
     */
    public static function infantryLight(): array
    {
        return [
            'code' => self::INFANTRY_LIGHT,
            'title' => 'Compagnie d’infanterie légère',
            'summary' => 'Une compagnie, trois pelotons et des groupes, avec les fonctions d’encadrement et de combat usuelles, et un système de grades français.',
            'version' => 1,
            'grade_system_code' => 'FR_CLASSIC',
            'units' => [
                ['key' => 'cie', 'parent_key' => null, 'name' => 'Compagnie', 'slug' => 'compagnie', 'type' => 'group', 'code' => 'CIE', 'display_order' => 10],
                ['key' => 'em', 'parent_key' => 'cie', 'name' => 'État-major de compagnie', 'slug' => 'etat-major-compagnie', 'type' => 'group', 'code' => 'EM', 'display_order' => 20],
                ['key' => 'plt1', 'parent_key' => 'cie', 'name' => '1er peloton', 'slug' => '1er-peloton', 'type' => 'group', 'code' => '1PLT', 'display_order' => 30],
                ['key' => 'plt1g1', 'parent_key' => 'plt1', 'name' => 'Groupe Alpha', 'slug' => 'groupe-alpha', 'type' => 'group', 'code' => '1A', 'display_order' => 31],
                ['key' => 'plt1g2', 'parent_key' => 'plt1', 'name' => 'Groupe Bravo', 'slug' => 'groupe-bravo', 'type' => 'group', 'code' => '1B', 'display_order' => 32],
                ['key' => 'plt2', 'parent_key' => 'cie', 'name' => '2e peloton', 'slug' => '2e-peloton', 'type' => 'group', 'code' => '2PLT', 'display_order' => 40],
                ['key' => 'plt2g1', 'parent_key' => 'plt2', 'name' => 'Groupe Charlie', 'slug' => 'groupe-charlie', 'type' => 'group', 'code' => '2A', 'display_order' => 41],
                ['key' => 'plt2g2', 'parent_key' => 'plt2', 'name' => 'Groupe Delta', 'slug' => 'groupe-delta', 'type' => 'group', 'code' => '2B', 'display_order' => 42],
                ['key' => 'plt3', 'parent_key' => 'cie', 'name' => '3e peloton', 'slug' => '3e-peloton', 'type' => 'group', 'code' => '3PLT', 'display_order' => 50],
                ['key' => 'plt3g1', 'parent_key' => 'plt3', 'name' => 'Groupe Echo', 'slug' => 'groupe-echo', 'type' => 'group', 'code' => '3A', 'display_order' => 51],
                ['key' => 'plt3g2', 'parent_key' => 'plt3', 'name' => 'Groupe Foxtrot', 'slug' => 'groupe-foxtrot', 'type' => 'group', 'code' => '3B', 'display_order' => 52],
            ],
            'job_role_categories' => [
                ['key' => 'cmd', 'parent_key' => null, 'name' => 'Commandement', 'slug' => 'commandement-infanterie', 'sort_order' => 10],
                ['key' => 'combat', 'parent_key' => null, 'name' => 'Combat', 'slug' => 'combat-infanterie', 'sort_order' => 20],
                ['key' => 'soutien', 'parent_key' => null, 'name' => 'Soutien', 'slug' => 'soutien-infanterie', 'sort_order' => 30],
            ],
            'job_roles' => [
                ['category_key' => 'cmd', 'name' => 'Chef de compagnie', 'slug' => 'chef-de-compagnie', 'description' => 'Conduit la compagnie et arbitre les priorités.', 'sort_order' => 10],
                ['category_key' => 'cmd', 'name' => 'Chef de peloton', 'slug' => 'chef-de-peloton', 'description' => 'Conduit un peloton et relaye les ordres.', 'sort_order' => 20],
                ['category_key' => 'cmd', 'name' => 'Chef de groupe', 'slug' => 'chef-de-groupe', 'description' => 'Conduit un groupe au plus près du terrain.', 'sort_order' => 30],
                ['category_key' => 'combat', 'name' => 'Équipier', 'slug' => 'equipier-infanterie', 'description' => 'Combattant de base du groupe.', 'sort_order' => 10],
                ['category_key' => 'combat', 'name' => 'Radio', 'slug' => 'radio-infanterie', 'description' => 'Relais des transmissions du groupe ou du peloton.', 'sort_order' => 20],
                ['category_key' => 'combat', 'name' => 'Tireur de précision', 'slug' => 'tireur-precision', 'description' => 'Appui observation et tir précis.', 'sort_order' => 30],
                ['category_key' => 'soutien', 'name' => 'Logisticien', 'slug' => 'logisticien-compagnie', 'description' => 'Suit le matériel et les besoins de la compagnie.', 'sort_order' => 10],
            ],
            'roles' => [
                ['name' => 'Commandement de compagnie', 'slug' => 'kit-cie-commandement', 'description' => 'Pilotage de la compagnie et de l’organigramme.', 'preset' => 'commandement_unite'],
                ['name' => 'Encadrement de peloton', 'slug' => 'kit-peloton-encadrement', 'description' => 'Encadrement d’un peloton, sans administration complète.', 'preset' => 'commandement_unite'],
                ['name' => 'Membre de section', 'slug' => 'kit-section-membre', 'description' => 'Profil de base pour un équipier.', 'preset' => 'member'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function gamingCommunity(): array
    {
        return [
            'code' => self::GAMING_COMMUNITY,
            'title' => 'Communauté gaming',
            'summary' => 'Une structure légère pour une communauté de joueurs : animation, accueil, technique et événements, avec un système de grades français.',
            'version' => 1,
            'grade_system_code' => 'FR_CLASSIC',
            'units' => [
                ['key' => 'root', 'parent_key' => null, 'name' => 'Communauté', 'slug' => 'communaute', 'type' => 'group', 'code' => 'COM', 'display_order' => 10],
                ['key' => 'anim', 'parent_key' => 'root', 'name' => 'Animation', 'slug' => 'animation', 'type' => 'group', 'code' => 'ANM', 'display_order' => 20],
                ['key' => 'accueil', 'parent_key' => 'root', 'name' => 'Accueil', 'slug' => 'accueil', 'type' => 'group', 'code' => 'ACC', 'display_order' => 30],
                ['key' => 'tech', 'parent_key' => 'root', 'name' => 'Technique', 'slug' => 'technique', 'type' => 'group', 'code' => 'TEC', 'display_order' => 40],
                ['key' => 'events', 'parent_key' => 'root', 'name' => 'Événements', 'slug' => 'evenements', 'type' => 'group', 'code' => 'EVT', 'display_order' => 50],
            ],
            'job_role_categories' => [
                ['key' => 'encad', 'parent_key' => null, 'name' => 'Encadrement', 'slug' => 'encadrement-communaute', 'sort_order' => 10],
                ['key' => 'accueil', 'parent_key' => null, 'name' => 'Accueil', 'slug' => 'accueil-communaute', 'sort_order' => 20],
                ['key' => 'tech', 'parent_key' => null, 'name' => 'Technique', 'slug' => 'technique-communaute', 'sort_order' => 30],
            ],
            'job_roles' => [
                ['category_key' => 'encad', 'name' => 'Administrateur', 'slug' => 'administrateur-communaute', 'description' => 'Pilote la communauté au quotidien.', 'sort_order' => 10],
                ['category_key' => 'encad', 'name' => 'Animateur', 'slug' => 'animateur-communaute', 'description' => 'Propose et conduit les sessions.', 'sort_order' => 20],
                ['category_key' => 'accueil', 'name' => 'Recruteur', 'slug' => 'recruteur-communaute', 'description' => 'Accueille les nouveaux et suit les candidatures.', 'sort_order' => 10],
                ['category_key' => 'accueil', 'name' => 'Guide', 'slug' => 'guide-communaute', 'description' => 'Accompagne les arrivants dans les premiers pas.', 'sort_order' => 20],
                ['category_key' => 'tech', 'name' => 'Référent technique', 'slug' => 'referent-technique', 'description' => 'Suit les outils, les mods et les accès.', 'sort_order' => 10],
            ],
            'roles' => [
                ['name' => 'Administration de communauté', 'slug' => 'kit-communaute-admin', 'description' => 'Pilotage de la communauté.', 'preset' => 'commandement_unite'],
                ['name' => 'Cellule recrutement', 'slug' => 'kit-communaute-recrutement', 'description' => 'Accueil et dossiers d’arrivée.', 'preset' => 'cellule_recrutement'],
                ['name' => 'Membre', 'slug' => 'kit-communaute-membre', 'description' => 'Profil de base des joueurs.', 'preset' => 'member'],
            ],
        ];
    }
}
