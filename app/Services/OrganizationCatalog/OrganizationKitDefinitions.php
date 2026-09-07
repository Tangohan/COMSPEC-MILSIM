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
    public const FRENCH_ARMY = 'official.french_army';
    public const US_SOF = 'official.us_sof';

    /**
     * @return list<array<string, mixed>>
     */
    public static function officialKits(): array
    {
        return [
            self::frenchArmy(),
            self::usSof(),
            self::infantryLight(),
            self::gamingCommunity(),
        ];
    }

    /**
     * @return list<string>
     */
    public static function officialCodes(): array
    {
        return [
            self::FRENCH_ARMY,
            self::US_SOF,
            self::INFANTRY_LIGHT,
            self::GAMING_COMMUNITY,
        ];
    }

    /**
     * @return list<string>
     */
    public static function jobDoctrineCodes(): array
    {
        return [self::FRENCH_ARMY, self::US_SOF];
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
        if ($units > 0) {
            $bits[] = $units === 1 ? '1 unité' : $units . ' unités';
        }
        if ($functions > 0) {
            $bits[] = $functions === 1 ? '1 fonction' : $functions . ' fonctions';
        }
        if ($roles > 0) {
            $bits[] = $roles === 1 ? '1 rôle' : $roles . ' rôles';
        }
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

    /**
     * Catalogue d’emplois type Armée de terre / COS, sans droits d’accès.
     *
     * @return array<string, mixed>
     */
    public static function frenchArmy(): array
    {
        return [
            'code' => self::FRENCH_ARMY,
            'title' => 'Armée française',
            'summary' => 'Emplois d’une compagnie de combat et d’un détachement des forces spéciales, avec l’organigramme type et les grades français. Les niveaux d’accès de la communauté restent inchangés.',
            'version' => 1,
            'grade_system_code' => 'FR_CLASSIC',
            'units' => [
                self::unit(null, 'reg', 'Régiment', 'armeefr-regiment', 'RGT', 10),
                self::unit('reg', 'cie', 'Compagnie de combat', 'armeefr-compagnie', 'CIE', 20),
                self::unit('cie', 'em', 'Section de commandement', 'armeefr-commandement', 'SCD', 30),
                self::unit('cie', 's1', '1re section', 'armeefr-1re-section', '1SEC', 40),
                self::unit('s1', 's1g1', '1er groupe', 'armeefr-1er-groupe', '1G1', 41),
                self::unit('s1', 's1g2', '2e groupe', 'armeefr-2e-groupe', '1G2', 42),
                self::unit('cie', 's2', '2e section', 'armeefr-2e-section', '2SEC', 50),
                self::unit('cie', 's3', '3e section', 'armeefr-3e-section', '3SEC', 60),
                self::unit('cie', 'appui', 'Section d’appui', 'armeefr-section-appui', 'SAP', 70),
                self::unit('reg', 'fs', 'Détachement forces spéciales', 'armeefr-detachement-fs', 'DFS', 80),
            ],
            'job_role_categories' => [
                self::cat('cmd', 'Commandement', 'armeefr-commandement', 10),
                self::cat('inf', 'Infanterie', 'armeefr-infanterie', 20),
                self::cat('appui', 'Appui feu', 'armeefr-appui-feu', 30),
                self::cat('trans', 'Transmissions', 'armeefr-transmissions', 40),
                self::cat('genie', 'Génie', 'armeefr-genie', 50),
                self::cat('sante', 'Santé', 'armeefr-sante', 60),
                self::cat('log', 'Logistique', 'armeefr-logistique', 70),
                self::cat('rens', 'Renseignement', 'armeefr-renseignement', 80),
                self::cat('fs', 'Forces spéciales', 'armeefr-forces-speciales', 90),
            ],
            'job_roles' => [
                self::job('cmd', 'Chef de corps', 'armeefr-chef-de-corps', 'Conduit le régiment et fixe les priorités.', 10),
                self::job('cmd', 'Commandant d’unité', 'armeefr-commandant-unite', 'Conduit la compagnie et rend compte au chef de corps.', 20),
                self::job('cmd', 'Officier adjoint', 'armeefr-officier-adjoint', 'Relaye le commandant d’unité et coordonne l’état-major de compagnie.', 30),
                self::job('cmd', 'Chef de section', 'armeefr-chef-de-section', 'Conduit une section au combat et relaye les ordres.', 40),
                self::job('cmd', 'Adjudant de compagnie', 'armeefr-adjudant-compagnie', 'Suit la discipline, le matériel et la tenue de la compagnie.', 50),
                self::job('cmd', 'Chef de groupe', 'armeefr-chef-de-groupe', 'Conduit un groupe au plus près du terrain.', 60),
                self::job('cmd', 'Chef d’équipe', 'armeefr-chef-equipe', 'Conduit une équipe élémentaire au contact.', 70),
                self::job('inf', 'Voltigeur', 'armeefr-voltigeur', 'Combattant de base du groupe d’infanterie.', 10),
                self::job('inf', 'Grenadier-voltigeur', 'armeefr-grenadier-voltigeur', 'Appui grenades et armement d’accompagnement du groupe.', 20),
                self::job('inf', 'Mitrailleur', 'armeefr-mitrailleur', 'Assure l’appui feu soutenu du groupe.', 30),
                self::job('inf', 'Spécialiste anti-blindés', 'armeefr-anti-blindes', 'Engage les véhicules et les fortifications légères.', 40),
                self::job('inf', 'Tireur de précision', 'armeefr-tireur-precision', 'Observe et tire avec précision à moyenne portée.', 50),
                self::job('inf', 'Tireur d’élite', 'armeefr-tireur-elite', 'Neutralise des objectifs choisis à plus longue portée.', 60),
                self::job('inf', 'Éclaireur', 'armeefr-eclaireur', 'Reconnaît l’avant et rend compte du terrain.', 70),
                self::job('inf', 'Chef d’engin', 'armeefr-chef-engin', 'Conduit un engin de combat et son équipage.', 80),
                self::job('inf', 'Conducteur d’engin', 'armeefr-conducteur-engin', 'Pilote l’engin et tient la mobilité du groupe.', 90),
                self::job('appui', 'Chef de pièce mortier', 'armeefr-chef-piece-mortier', 'Conduit la pièce et la mise en œuvre du mortier.', 10),
                self::job('appui', 'Servant de mortier', 'armeefr-servant-mortier', 'Met en œuvre le mortier d’accompagnement.', 20),
                self::job('appui', 'Observateur d’artillerie', 'armeefr-observateur-artillerie', 'Demande et corrige les feux d’appui.', 30),
                self::job('appui', 'Pointeur', 'armeefr-pointeur', 'Règle le tir et tient la pièce prête.', 40),
                self::job('trans', 'Radio de groupe', 'armeefr-radio-groupe', 'Tient la liaison du groupe vers la section.', 10),
                self::job('trans', 'Radio de section', 'armeefr-radio-section', 'Tient la liaison de la section vers la compagnie.', 20),
                self::job('trans', 'Transmetteur', 'armeefr-transmetteur', 'Met en œuvre les moyens de transmission de l’unité.', 30),
                self::job('genie', 'Sapeur', 'armeefr-sapeur', 'Ouvre les passages et appuie le mouvement au contact.', 10),
                self::job('genie', 'Démineur', 'armeefr-demineur', 'Repère et neutralise les dangers explosifs.', 20),
                self::job('genie', 'Chef de groupe génie', 'armeefr-chef-groupe-genie', 'Conduit un groupe du génie de combat.', 30),
                self::job('sante', 'Auxiliaire sanitaire', 'armeefr-auxiliaire-sanitaire', 'Porte les premiers soins au plus près du groupe.', 10),
                self::job('sante', 'Infirmier', 'armeefr-infirmier', 'Assure les soins avancés et l’évacuation.', 20),
                self::job('sante', 'Médecin', 'armeefr-medecin', 'Dirige le soutien sanitaire de l’unité.', 30),
                self::job('log', 'Logisticien', 'armeefr-logisticien', 'Suit le matériel, les stocks et les besoins de l’unité.', 10),
                self::job('log', 'Conducteur', 'armeefr-conducteur', 'Assure les mouvements et le ravitaillement.', 20),
                self::job('log', 'Armurier', 'armeefr-armurier', 'Entretient les armes et les lots de munitions.', 30),
                self::job('log', 'Ravitailleur', 'armeefr-ravitailleur', 'Prépare et livre les recomplètements.', 40),
                self::job('rens', 'Guetteur', 'armeefr-guetteur', 'Observe, signale et tient le dispositif de surveillance.', 10),
                self::job('rens', 'Opérateur renseignement', 'armeefr-operateur-renseignement', 'Recueille et restitue l’information utile au commandement.', 20),
                self::job('fs', 'Chef de groupe commando', 'armeefr-chef-groupe-commando', 'Conduit un groupe des forces spéciales.', 10),
                self::job('fs', 'Opérateur commando', 'armeefr-operateur-commando', 'Exécute les missions d’action spéciale.', 20),
                self::job('fs', 'Chuteur opérationnel', 'armeefr-chuteur-operationnel', 'Intervient par saut pour l’infiltration ou le renfort.', 30),
                self::job('fs', 'Nageur de combat', 'armeefr-nageur-de-combat', 'Intervient par voie nautique et tient les abords.', 40),
                self::job('fs', 'Infirmier commando', 'armeefr-infirmier-commando', 'Porte les soins avancés en milieu dégradé.', 50),
                self::job('fs', 'Transmetteur commando', 'armeefr-transmetteur-commando', 'Tient les liaisons du détachement isolé.', 60),
                self::job('fs', 'Opérateur reconnaissance profonde', 'armeefr-reconnaissance-profonde', 'Observe en profondeur et rend compte sans se faire remarquer.', 70),
            ],
            'roles' => [],
        ];
    }

    /**
     * Catalogue d’emplois type SOF américain (ODA, Rangers, action navale, guerre spéciale aérienne).
     *
     * @return array<string, mixed>
     */
    public static function usSof(): array
    {
        return [
            'code' => self::US_SOF,
            'title' => 'SOF américain',
            'summary' => 'Emplois d’un groupement d’opérations spéciales américain : détachement, Rangers, action navale et guerre spéciale aérienne, avec les grades américains. Les niveaux d’accès de la communauté restent inchangés.',
            'version' => 1,
            'grade_system_code' => 'US_CLASSIC',
            'units' => [
                self::unit(null, 'sog', 'Groupement d’opérations spéciales', 'ussof-groupement', 'SOG', 10),
                self::unit('sog', 'sfc', 'Compagnie forces spéciales', 'ussof-compagnie-fs', 'SFC', 20),
                self::unit('sfc', 'oda1', 'Détachement Alpha', 'ussof-detachement-alpha', 'ODA1', 21),
                self::unit('sfc', 'oda2', 'Détachement Bravo', 'ussof-detachement-bravo', 'ODA2', 22),
                self::unit('sog', 'rng', 'Peloton Ranger', 'ussof-peloton-ranger', 'RGR', 30),
                self::unit('sog', 'af', 'Cellule guerre spéciale aérienne', 'ussof-guerre-aerienne', 'AFSOF', 40),
                self::unit('sog', 'nsw', 'Élément d’action navale', 'ussof-action-navale', 'NSW', 50),
            ],
            'job_role_categories' => [
                self::cat('cmd', 'Commandement de détachement', 'ussof-commandement', 10),
                self::cat('sf', 'Forces spéciales terre', 'ussof-forces-speciales', 20),
                self::cat('rng', 'Rangers', 'ussof-rangers', 30),
                self::cat('nsw', 'Action navale', 'ussof-action-navale', 40),
                self::cat('af', 'Guerre spéciale aérienne', 'ussof-guerre-aerienne', 50),
                self::cat('sout', 'Soutien des opérations spéciales', 'ussof-soutien', 60),
            ],
            'job_roles' => [
                self::job('cmd', 'Commandant de détachement', 'ussof-commandant-detachement', 'Conduit un détachement des forces spéciales et fixe le mode d’action.', 10),
                self::job('cmd', 'Adjoint de détachement', 'ussof-adjoint-detachement', 'Seconde le commandant et prend la conduite en son absence.', 20),
                self::job('cmd', 'Sergent opérations', 'ussof-sergent-operations', 'Prépare la manœuvre, tient le rythme et coordonne les équipes.', 30),
                self::job('sf', 'Sergent armement', 'ussof-sergent-armement', 'Tient les armes du détachement et l’appui feu rapproché.', 10),
                self::job('sf', 'Sergent génie', 'ussof-sergent-genie', 'Ouvre les accès, détruit ou aménage selon la mission.', 20),
                self::job('sf', 'Sergent médical', 'ussof-sergent-medical', 'Porte les soins avancés du détachement isolé.', 30),
                self::job('sf', 'Sergent transmissions', 'ussof-sergent-transmissions', 'Tient les liaisons du détachement vers le groupement.', 40),
                self::job('sf', 'Sergent renseignement', 'ussof-sergent-renseignement', 'Prépare, fusionne et restitue l’information de mission.', 50),
                self::job('sf', 'Opérateur des forces spéciales', 'ussof-operateur-fs', 'Exécute les missions du détachement au contact.', 60),
                self::job('rng', 'Chef de peloton Ranger', 'ussof-chef-peloton-ranger', 'Conduit le peloton Ranger et relie le groupement.', 10),
                self::job('rng', 'Chef de groupe Ranger', 'ussof-chef-groupe-ranger', 'Conduit un groupe Ranger au combat.', 20),
                self::job('rng', 'Chef d’équipe Ranger', 'ussof-chef-equipe-ranger', 'Conduit une équipe élémentaire Ranger.', 30),
                self::job('rng', 'Fusilier Ranger', 'ussof-fusilier-ranger', 'Combattant de base du groupe Ranger.', 40),
                self::job('rng', 'Fusilier automatique Ranger', 'ussof-fusilier-auto-ranger', 'Assure l’appui feu soutenu du groupe Ranger.', 50),
                self::job('rng', 'Grenadier Ranger', 'ussof-grenadier-ranger', 'Appuie le groupe avec les tirs d’accompagnement.', 60),
                self::job('rng', 'Radio Ranger', 'ussof-radio-ranger', 'Tient la liaison du groupe ou du peloton Ranger.', 70),
                self::job('rng', 'Infirmier Ranger', 'ussof-infirmier-ranger', 'Porte les soins du peloton Ranger.', 80),
                self::job('nsw', 'Opérateur d’action navale', 'ussof-operateur-action-navale', 'Intervient depuis la mer, le littoral ou les abords nautiques.', 10),
                self::job('nsw', 'Chef d’équipe d’action navale', 'ussof-chef-equipe-navale', 'Conduit une équipe maritime des opérations spéciales.', 20),
                self::job('nsw', 'Équipage d’embarcation spéciale', 'ussof-equipage-embarcation', 'Met en œuvre l’embarcation et extrait ou insère l’équipe.', 30),
                self::job('af', 'Contrôleur de combat', 'ussof-controleur-de-combat', 'Prépare une zone, tient la liaison air et intègre l’appui aérien.', 10),
                self::job('af', 'Sauveteur parachutiste', 'ussof-sauveteur-parachutiste', 'Récupère et soigne du personnel isolé ou blessé.', 20),
                self::job('af', 'Contrôleur aérien tactique', 'ussof-controleur-aerien-tactique', 'Conseille le commandement terrestre et relie l’appui aérien.', 30),
                self::job('af', 'Reconnaissance spéciale aérienne', 'ussof-reconnaissance-aerienne', 'Observe depuis l’air et prépare l’arrivée d’une force.', 40),
                self::job('sout', 'Contrôleur aérien avancé', 'ussof-controleur-aerien-avance', 'Demande et corrige l’appui aérien au profit du groupement.', 10),
                self::job('sout', 'Analyste renseignement', 'ussof-analyste-renseignement', 'Prépare le dossier de mission et suit la situation.', 20),
                self::job('sout', 'Logisticien des opérations spéciales', 'ussof-logisticien-sof', 'Suit le matériel spécifique et les recomplètements du groupement.', 30),
                self::job('sout', 'Opérateur de drone', 'ussof-operateur-drone', 'Met en œuvre un engin aérien pour observer ou appuyer.', 40),
            ],
            'roles' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function unit(?string $parent, string $key, string $name, string $slug, string $code, int $order): array
    {
        return [
            'key' => $key,
            'parent_key' => $parent,
            'name' => $name,
            'slug' => $slug,
            'type' => 'group',
            'code' => $code,
            'display_order' => $order,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function cat(string $key, string $name, string $slug, int $order): array
    {
        return [
            'key' => $key,
            'parent_key' => null,
            'name' => $name,
            'slug' => $slug,
            'sort_order' => $order,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function job(string $cat, string $name, string $slug, string $desc, int $order): array
    {
        return [
            'category_key' => $cat,
            'name' => $name,
            'slug' => $slug,
            'description' => $desc,
            'sort_order' => $order,
        ];
    }
}
