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
    public const US_ARMY_INFANTRY = 'official.us_army_infantry';
    public const SOF_TASK_FORCE = 'official.sof_task_force';
    public const FRENCH_ARMOR = 'official.french_armor';
    public const AIRMOBILE = 'official.airmobile';
    public const PRIVATE_MILITARY = 'official.private_military';

    /**
     * @return list<array<string, mixed>>
     */
    public static function officialKits(): array
    {
        return [
            self::frenchArmy(),
            self::usSof(),
            self::infantryLight(),
            self::usArmyInfantry(),
            self::sofTaskForce(),
            self::frenchArmor(),
            self::airmobile(),
            self::privateMilitary(),
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
            self::US_ARMY_INFANTRY,
            self::SOF_TASK_FORCE,
            self::FRENCH_ARMOR,
            self::AIRMOBILE,
            self::PRIVATE_MILITARY,
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
            'summary' => 'Une compagnie, trois pelotons de deux groupes, une section d’appui et une cellule soutien, avec les fonctions d’encadrement, de combat et de soutien usuelles, et un système de grades français.',
            'version' => 2,
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
                ['key' => 'appui', 'parent_key' => 'cie', 'name' => 'Section d’appui', 'slug' => 'section-appui', 'type' => 'group', 'code' => 'SAP', 'display_order' => 60],
                ['key' => 'soutien', 'parent_key' => 'cie', 'name' => 'Cellule soutien', 'slug' => 'cellule-soutien', 'type' => 'group', 'code' => 'SOU', 'display_order' => 70],
            ],
            'job_role_categories' => [
                ['key' => 'cmd', 'parent_key' => null, 'name' => 'Commandement', 'slug' => 'commandement-infanterie', 'sort_order' => 10],
                ['key' => 'combat', 'parent_key' => null, 'name' => 'Combat', 'slug' => 'combat-infanterie', 'sort_order' => 20],
                ['key' => 'appui', 'parent_key' => null, 'name' => 'Appui', 'slug' => 'appui-infanterie', 'sort_order' => 25],
                ['key' => 'soutien', 'parent_key' => null, 'name' => 'Soutien', 'slug' => 'soutien-infanterie', 'sort_order' => 30],
            ],
            'job_roles' => [
                ['category_key' => 'cmd', 'name' => 'Chef de compagnie', 'slug' => 'chef-de-compagnie', 'description' => 'Conduit la compagnie et arbitre les priorités.', 'sort_order' => 10],
                ['category_key' => 'cmd', 'name' => 'Adjoint de compagnie', 'slug' => 'adjoint-de-compagnie', 'description' => 'Seconde le chef de compagnie et tient l’état-major.', 'sort_order' => 15],
                ['category_key' => 'cmd', 'name' => 'Chef de peloton', 'slug' => 'chef-de-peloton', 'description' => 'Conduit un peloton et relaye les ordres.', 'sort_order' => 20],
                ['category_key' => 'cmd', 'name' => 'Chef de groupe', 'slug' => 'chef-de-groupe', 'description' => 'Conduit un groupe au plus près du terrain.', 'sort_order' => 30],
                ['category_key' => 'cmd', 'name' => 'Chef d’équipe', 'slug' => 'chef-equipe-infanterie', 'description' => 'Conduit une équipe de trois ou quatre combattants.', 'sort_order' => 40],
                ['category_key' => 'combat', 'name' => 'Équipier', 'slug' => 'equipier-infanterie', 'description' => 'Combattant de base du groupe.', 'sort_order' => 10],
                ['category_key' => 'combat', 'name' => 'Radio', 'slug' => 'radio-infanterie', 'description' => 'Relais des transmissions du groupe ou du peloton.', 'sort_order' => 20],
                ['category_key' => 'combat', 'name' => 'Tireur de précision', 'slug' => 'tireur-precision', 'description' => 'Appui observation et tir précis.', 'sort_order' => 30],
                ['category_key' => 'combat', 'name' => 'Grenadier', 'slug' => 'grenadier-infanterie', 'description' => 'Met en œuvre le lance-grenades du groupe.', 'sort_order' => 40],
                ['category_key' => 'combat', 'name' => 'Mitrailleur', 'slug' => 'mitrailleur-infanterie', 'description' => 'Assure l’appui feu soutenu du groupe.', 'sort_order' => 50],
                ['category_key' => 'appui', 'name' => 'Tireur anti-char', 'slug' => 'tireur-antichar-infanterie', 'description' => 'Engage les véhicules et les positions renforcées.', 'sort_order' => 10],
                ['category_key' => 'appui', 'name' => 'Servant mortier', 'slug' => 'servant-mortier-infanterie', 'description' => 'Met en œuvre le mortier léger de la compagnie.', 'sort_order' => 20],
                ['category_key' => 'appui', 'name' => 'Observateur', 'slug' => 'observateur-infanterie', 'description' => 'Demande et ajuste les tirs d’appui.', 'sort_order' => 30],
                ['category_key' => 'soutien', 'name' => 'Logisticien', 'slug' => 'logisticien-compagnie', 'description' => 'Suit le matériel et les besoins de la compagnie.', 'sort_order' => 10],
                ['category_key' => 'soutien', 'name' => 'Auxiliaire sanitaire', 'slug' => 'auxiliaire-sanitaire-infanterie', 'description' => 'Porte les premiers soins et prépare l’évacuation.', 'sort_order' => 20],
                ['category_key' => 'soutien', 'name' => 'Conducteur', 'slug' => 'conducteur-infanterie', 'description' => 'Assure les déplacements et le ravitaillement.', 'sort_order' => 30],
            ],
            'roles' => [
                ['name' => 'Commandement de compagnie', 'slug' => 'kit-cie-commandement', 'description' => 'Pilotage de la compagnie et de l’organigramme.', 'preset' => 'commandement_unite'],
                ['name' => 'Encadrement de peloton', 'slug' => 'kit-peloton-encadrement', 'description' => 'Encadrement d’un peloton, sans administration complète.', 'preset' => 'commandement_unite'],
                ['name' => 'Instruction', 'slug' => 'kit-cie-instruction', 'description' => 'Formations, évaluations et qualifications.', 'preset' => 'instructor'],
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
            'summary' => 'Une structure légère pour une communauté de joueurs : animation, accueil, modération, technique, création de missions et événements, avec un système de grades français.',
            'version' => 2,
            'grade_system_code' => 'FR_CLASSIC',
            'units' => [
                ['key' => 'root', 'parent_key' => null, 'name' => 'Communauté', 'slug' => 'communaute', 'type' => 'group', 'code' => 'COM', 'display_order' => 10],
                ['key' => 'anim', 'parent_key' => 'root', 'name' => 'Animation', 'slug' => 'animation', 'type' => 'group', 'code' => 'ANM', 'display_order' => 20],
                ['key' => 'accueil', 'parent_key' => 'root', 'name' => 'Accueil', 'slug' => 'accueil', 'type' => 'group', 'code' => 'ACC', 'display_order' => 30],
                ['key' => 'tech', 'parent_key' => 'root', 'name' => 'Technique', 'slug' => 'technique', 'type' => 'group', 'code' => 'TEC', 'display_order' => 40],
                ['key' => 'events', 'parent_key' => 'root', 'name' => 'Événements', 'slug' => 'evenements', 'type' => 'group', 'code' => 'EVT', 'display_order' => 50],
                ['key' => 'missions', 'parent_key' => 'root', 'name' => 'Création de missions', 'slug' => 'creation-missions', 'type' => 'group', 'code' => 'MIS', 'display_order' => 60],
                ['key' => 'modo', 'parent_key' => 'root', 'name' => 'Modération', 'slug' => 'moderation', 'type' => 'group', 'code' => 'MOD', 'display_order' => 70],
            ],
            'job_role_categories' => [
                ['key' => 'encad', 'parent_key' => null, 'name' => 'Encadrement', 'slug' => 'encadrement-communaute', 'sort_order' => 10],
                ['key' => 'accueil', 'parent_key' => null, 'name' => 'Accueil', 'slug' => 'accueil-communaute', 'sort_order' => 20],
                ['key' => 'tech', 'parent_key' => null, 'name' => 'Technique', 'slug' => 'technique-communaute', 'sort_order' => 30],
                ['key' => 'jeu', 'parent_key' => null, 'name' => 'Jeu et missions', 'slug' => 'jeu-missions-communaute', 'sort_order' => 40],
            ],
            'job_roles' => [
                ['category_key' => 'encad', 'name' => 'Administrateur', 'slug' => 'administrateur-communaute', 'description' => 'Pilote la communauté au quotidien.', 'sort_order' => 10],
                ['category_key' => 'encad', 'name' => 'Animateur', 'slug' => 'animateur-communaute', 'description' => 'Propose et conduit les sessions.', 'sort_order' => 20],
                ['category_key' => 'encad', 'name' => 'Modérateur', 'slug' => 'moderateur-communaute', 'description' => 'Veille au respect des règles sur le forum et le serveur vocal.', 'sort_order' => 30],
                ['category_key' => 'encad', 'name' => 'Responsable communication', 'slug' => 'communication-communaute', 'description' => 'Publie les annonces, les médias et les comptes rendus.', 'sort_order' => 40],
                ['category_key' => 'accueil', 'name' => 'Recruteur', 'slug' => 'recruteur-communaute', 'description' => 'Accueille les nouveaux et suit les candidatures.', 'sort_order' => 10],
                ['category_key' => 'accueil', 'name' => 'Guide', 'slug' => 'guide-communaute', 'description' => 'Accompagne les arrivants dans les premiers pas.', 'sort_order' => 20],
                ['category_key' => 'accueil', 'name' => 'Formateur', 'slug' => 'formateur-communaute', 'description' => 'Organise les entraînements et les formations de base.', 'sort_order' => 30],
                ['category_key' => 'tech', 'name' => 'Référent technique', 'slug' => 'referent-technique', 'description' => 'Suit les outils, les mods et les accès.', 'sort_order' => 10],
                ['category_key' => 'tech', 'name' => 'Administrateur serveur', 'slug' => 'admin-serveur-communaute', 'description' => 'Maintient le serveur de jeu, les mises à jour et les sauvegardes.', 'sort_order' => 20],
                ['category_key' => 'tech', 'name' => 'Responsable modpack', 'slug' => 'modpack-communaute', 'description' => 'Compose et teste la liste de mods de la communauté.', 'sort_order' => 30],
                ['category_key' => 'jeu', 'name' => 'Concepteur de missions', 'slug' => 'concepteur-missions', 'description' => 'Écrit, construit et teste les missions jouées en session.', 'sort_order' => 10],
                ['category_key' => 'jeu', 'name' => 'Maître du jeu', 'slug' => 'maitre-du-jeu', 'description' => 'Pilote la session en direct (Zeus, game master) et fait vivre le scénario.', 'sort_order' => 20],
            ],
            'roles' => [
                ['name' => 'Administration de communauté', 'slug' => 'kit-communaute-admin', 'description' => 'Pilotage de la communauté.', 'preset' => 'commandement_unite'],
                ['name' => 'Cellule recrutement', 'slug' => 'kit-communaute-recrutement', 'description' => 'Accueil et dossiers d’arrivée.', 'preset' => 'cellule_recrutement'],
                ['name' => 'Modération du forum', 'slug' => 'kit-communaute-moderation', 'description' => 'Modération des échanges de la communauté.', 'preset' => 'forum_moderator_org'],
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
     * Compagnie d’infanterie de l’US Army (brigade d’infanterie), grades américains.
     *
     * @return array<string, mixed>
     */
    public static function usArmyInfantry(): array
    {
        $units = [
            self::unit(null, 'coy', 'Compagnie d’infanterie', 'usinf-compagnie', 'COY', 10),
            self::unit('coy', 'hq', 'Section de commandement', 'usinf-commandement', 'HQ', 20),
            self::unit('hq', 'fist', 'Équipe d’appui feu', 'usinf-equipe-appui-feu', 'FIST', 21),
            self::unit('hq', 'mort', 'Section mortiers 60 mm', 'usinf-mortiers', 'MORT', 22),
        ];
        $order = 30;
        foreach ([1 => '1er', 2 => '2e', 3 => '3e'] as $n => $label) {
            $plt = 'plt' . $n;
            $units[] = self::unit('coy', $plt, $label . ' peloton', 'usinf-' . $n . '-peloton', $n . 'PLT', $order);
            $units[] = self::unit($plt, $plt . 'hq', 'Commandement du ' . $label . ' peloton', 'usinf-' . $n . '-peloton-cdt', $n . 'HQ', $order + 1);
            foreach ([1 => '1er', 2 => '2e', 3 => '3e'] as $s => $sLabel) {
                $sq = $plt . 'sq' . $s;
                $units[] = self::unit($plt, $sq, $sLabel . ' groupe de combat', 'usinf-' . $n . '-groupe-' . $s, $n . '-' . $s, $order + 1 + $s);
                $units[] = self::unit($sq, $sq . 'a', 'Équipe Alpha', 'usinf-' . $n . '-' . $s . '-alpha', $n . '-' . $s . 'A', 1);
                $units[] = self::unit($sq, $sq . 'b', 'Équipe Bravo', 'usinf-' . $n . '-' . $s . '-bravo', $n . '-' . $s . 'B', 2);
            }
            $units[] = self::unit($plt, $plt . 'wpn', 'Groupe d’armes', 'usinf-' . $n . '-groupe-armes', $n . 'WPN', $order + 5);
            $order += 10;
        }

        return [
            'code' => self::US_ARMY_INFANTRY,
            'title' => 'Compagnie d’infanterie US Army',
            'summary' => 'Une compagnie de fusiliers type brigade d’infanterie américaine : trois pelotons de trois groupes à deux équipes Alpha et Bravo, un groupe d’armes par peloton, des mortiers et une équipe d’appui feu, avec les grades américains.',
            'version' => 1,
            'grade_system_code' => 'US_CLASSIC',
            'units' => $units,
            'job_role_categories' => [
                self::cat('cmd', 'Commandement', 'usinf-commandement', 10),
                self::cat('fus', 'Groupe de combat', 'usinf-groupe-combat', 20),
                self::cat('armes', 'Armes collectives', 'usinf-armes-collectives', 30),
                self::cat('appui', 'Appui feu', 'usinf-appui-feu', 40),
                self::cat('sout', 'Soutien', 'usinf-soutien', 50),
            ],
            'job_roles' => [
                self::job('cmd', 'Commandant de compagnie', 'usinf-commandant-compagnie', 'Company Commander : conduit la compagnie, en général capitaine.', 10),
                self::job('cmd', 'Officier adjoint', 'usinf-officier-adjoint', 'Executive Officer : seconde le commandant et tient la logistique de la compagnie.', 20),
                self::job('cmd', 'Premier sergent', 'usinf-premier-sergent', 'First Sergeant : sous-officier le plus ancien, discipline et soutien du personnel.', 30),
                self::job('cmd', 'Chef de peloton', 'usinf-chef-peloton', 'Platoon Leader : lieutenant qui conduit le peloton.', 40),
                self::job('cmd', 'Sergent de peloton', 'usinf-sergent-peloton', 'Platoon Sergeant : seconde le chef de peloton et tient le soutien.', 50),
                self::job('cmd', 'Radio de compagnie', 'usinf-radio-compagnie', 'RTO : tient les liaisons du commandement de compagnie.', 60),
                self::job('fus', 'Chef de groupe', 'usinf-chef-groupe', 'Squad Leader : conduit un groupe de neuf soldats.', 10),
                self::job('fus', 'Chef d’équipe', 'usinf-chef-equipe', 'Team Leader : conduit une équipe de feu de quatre.', 20),
                self::job('fus', 'Fusilier', 'usinf-fusilier', 'Rifleman : combattant de base de l’équipe.', 30),
                self::job('fus', 'Fusilier automatique', 'usinf-fusilier-automatique', 'Automatic Rifleman : appui feu soutenu de l’équipe.', 40),
                self::job('fus', 'Grenadier', 'usinf-grenadier', 'Grenadier : lance-grenades de l’équipe.', 50),
                self::job('fus', 'Tireur désigné', 'usinf-tireur-designe', 'Designated Marksman : tir précis à moyenne portée.', 60),
                self::job('armes', 'Chef du groupe d’armes', 'usinf-chef-groupe-armes', 'Weapons Squad Leader : place les mitrailleuses et les missiles du peloton.', 10),
                self::job('armes', 'Mitrailleur', 'usinf-mitrailleur', 'Machine Gunner : sert la mitrailleuse moyenne.', 20),
                self::job('armes', 'Pourvoyeur', 'usinf-pourvoyeur', 'Assistant Gunner : approvisionne et seconde le mitrailleur.', 30),
                self::job('armes', 'Tireur missile anti-char', 'usinf-tireur-antichar', 'Javelin Gunner : engage les blindés et les positions.', 40),
                self::job('appui', 'Chef de section mortiers', 'usinf-chef-mortiers', 'Section Sergeant : conduit les pièces de mortier.', 10),
                self::job('appui', 'Servant mortier', 'usinf-servant-mortier', 'Mortarman : met en œuvre le mortier de 60 mm.', 20),
                self::job('appui', 'Observateur avancé', 'usinf-observateur-avance', 'Forward Observer : demande et ajuste les feux indirects.', 30),
                self::job('appui', 'Officier appui feu', 'usinf-officier-appui-feu', 'Fire Support Officer : coordonne les feux au profit de la compagnie.', 40),
                self::job('sout', 'Infirmier de combat', 'usinf-infirmier-combat', 'Combat Medic : soins au plus près et évacuation.', 10),
                self::job('sout', 'Sous-officier ravitaillement', 'usinf-ravitaillement', 'Supply Sergeant : matériel, munitions et vivres.', 20),
            ],
            'roles' => [],
        ];
    }

    /**
     * Task force d’opérations spéciales pensée pour le milsim (structure générique, non nominative).
     *
     * @return array<string, mixed>
     */
    public static function sofTaskForce(): array
    {
        return [
            'code' => self::SOF_TASK_FORCE,
            'title' => 'Task force d’opérations spéciales',
            'summary' => 'Une task force générique pour le milsim : poste de commandement, deux éléments d’assaut, reconnaissance et tireurs d’élite, appui (guidage aérien, déminage, santé), aviation et cellule renseignement, avec les grades américains.',
            'version' => 1,
            'grade_system_code' => 'US_CLASSIC',
            'units' => [
                self::unit(null, 'tf', 'Task force', 'tfsof-task-force', 'TF', 10),
                self::unit('tf', 'toc', 'Poste de commandement', 'tfsof-poste-commandement', 'TOC', 20),
                self::unit('tf', 'as1', 'Élément d’assaut 1', 'tfsof-assaut-1', 'AS1', 30),
                self::unit('as1', 'as1a', 'Équipe d’assaut 1A', 'tfsof-assaut-1a', 'AS1A', 31),
                self::unit('as1', 'as1b', 'Équipe d’assaut 1B', 'tfsof-assaut-1b', 'AS1B', 32),
                self::unit('tf', 'as2', 'Élément d’assaut 2', 'tfsof-assaut-2', 'AS2', 40),
                self::unit('as2', 'as2a', 'Équipe d’assaut 2A', 'tfsof-assaut-2a', 'AS2A', 41),
                self::unit('as2', 'as2b', 'Équipe d’assaut 2B', 'tfsof-assaut-2b', 'AS2B', 42),
                self::unit('tf', 'recce', 'Reconnaissance et tireurs d’élite', 'tfsof-reconnaissance', 'RECCE', 50),
                self::unit('tf', 'spt', 'Élément d’appui', 'tfsof-appui', 'SPT', 60),
                self::unit('tf', 'avn', 'Élément aviation', 'tfsof-aviation', 'AVN', 70),
                self::unit('tf', 'intel', 'Cellule renseignement', 'tfsof-renseignement', 'S2', 80),
            ],
            'job_role_categories' => [
                self::cat('cmd', 'Commandement', 'tfsof-commandement', 10),
                self::cat('assaut', 'Assaut', 'tfsof-assaut', 20),
                self::cat('recce', 'Reconnaissance', 'tfsof-reconnaissance', 30),
                self::cat('appui', 'Appui', 'tfsof-appui', 40),
                self::cat('avn', 'Aviation', 'tfsof-aviation', 50),
                self::cat('rens', 'Renseignement', 'tfsof-renseignement', 60),
            ],
            'job_roles' => [
                self::job('cmd', 'Commandant de task force', 'tfsof-commandant', 'Fixe l’intention et conduit l’ensemble de la task force.', 10),
                self::job('cmd', 'Officier opérations', 'tfsof-officier-operations', 'Prépare les ordres, tient le tempo et le suivi de situation.', 20),
                self::job('cmd', 'Opérateur transmissions', 'tfsof-transmissions', 'Tient le réseau radio, les fréquences et les comptes rendus.', 30),
                self::job('assaut', 'Chef d’élément d’assaut', 'tfsof-chef-element', 'Conduit un élément d’assaut de deux équipes.', 10),
                self::job('assaut', 'Chef d’équipe d’assaut', 'tfsof-chef-equipe', 'Conduit une équipe au contact.', 20),
                self::job('assaut', 'Opérateur d’assaut', 'tfsof-operateur-assaut', 'Combat rapproché, nettoyage et saisie d’objectifs.', 30),
                self::job('assaut', 'Mitrailleur', 'tfsof-mitrailleur', 'Appui feu soutenu de l’équipe.', 40),
                self::job('assaut', 'Opérateur effraction', 'tfsof-effraction', 'Ouvre les accès par moyens mécaniques, balistiques ou explosifs.', 50),
                self::job('assaut', 'Maître-chien', 'tfsof-maitre-chien', 'Met en œuvre le chien d’assaut ou de recherche.', 60),
                self::job('recce', 'Chef d’équipe reconnaissance', 'tfsof-chef-reconnaissance', 'Conduit l’observation avancée et rend compte.', 10),
                self::job('recce', 'Tireur d’élite', 'tfsof-tireur-elite', 'Neutralise à longue portée et couvre les assauts.', 20),
                self::job('recce', 'Observateur', 'tfsof-observateur', 'Seconde le tireur d’élite, mesure et renseigne.', 30),
                self::job('appui', 'Contrôleur aérien avancé', 'tfsof-jtac', 'JTAC : guide l’appui aérien et les frappes.', 10),
                self::job('appui', 'Démineur', 'tfsof-demineur', 'EOD : repère et neutralise les engins explosifs.', 20),
                self::job('appui', 'Infirmier opérations spéciales', 'tfsof-infirmier', 'Soins avancés et préparation des évacuations.', 30),
                self::job('appui', 'Opérateur de drone', 'tfsof-operateur-drone', 'Observe et appuie avec un drone tactique.', 40),
                self::job('avn', 'Pilote d’hélicoptère', 'tfsof-pilote', 'Insère, extrait et appuie la force.', 10),
                self::job('avn', 'Chef de bord', 'tfsof-chef-bord', 'Commande l’équipage et la soute.', 20),
                self::job('avn', 'Tireur de sabord', 'tfsof-tireur-sabord', 'Protège l’appareil et appuie au poser.', 30),
                self::job('rens', 'Analyste renseignement', 'tfsof-analyste', 'Prépare le dossier d’objectif et suit la menace.', 10),
                self::job('rens', 'Opérateur exploitation', 'tfsof-exploitation', 'Exploite documents, matériels et personnes capturés.', 20),
            ],
            'roles' => [],
        ];
    }

    /**
     * Escadron blindé de l’Armée de terre (chars et reconnaissance), grades français.
     *
     * @return array<string, mixed>
     */
    public static function frenchArmor(): array
    {
        return [
            'code' => self::FRENCH_ARMOR,
            'title' => 'Escadron blindé',
            'summary' => 'Un escadron de cavalerie blindée : peloton de commandement et de logistique, quatre pelotons de chars et un peloton de reconnaissance, avec les équipages et le soutien mécanique, et les grades français.',
            'version' => 1,
            'grade_system_code' => 'FR_CLASSIC',
            'units' => [
                self::unit(null, 'esc', 'Escadron blindé', 'blinde-escadron', 'ESC', 10),
                self::unit('esc', 'pcl', 'Peloton de commandement et de logistique', 'blinde-commandement-logistique', 'PCL', 20),
                self::unit('esc', 'p1', '1er peloton', 'blinde-1er-peloton', '1PEL', 30),
                self::unit('esc', 'p2', '2e peloton', 'blinde-2e-peloton', '2PEL', 40),
                self::unit('esc', 'p3', '3e peloton', 'blinde-3e-peloton', '3PEL', 50),
                self::unit('esc', 'p4', '4e peloton', 'blinde-4e-peloton', '4PEL', 60),
                self::unit('esc', 'recce', 'Peloton de reconnaissance', 'blinde-reconnaissance', 'PRI', 70),
            ],
            'job_role_categories' => [
                self::cat('cmd', 'Commandement', 'blinde-commandement', 10),
                self::cat('equipage', 'Équipages', 'blinde-equipages', 20),
                self::cat('recce', 'Reconnaissance', 'blinde-reconnaissance', 30),
                self::cat('sout', 'Soutien', 'blinde-soutien', 40),
            ],
            'job_roles' => [
                self::job('cmd', 'Commandant d’escadron', 'blinde-commandant-escadron', 'Conduit l’escadron et coordonne la manœuvre des pelotons.', 10),
                self::job('cmd', 'Officier adjoint', 'blinde-officier-adjoint', 'Seconde le commandant et tient le poste de commandement.', 20),
                self::job('cmd', 'Chef de peloton', 'blinde-chef-peloton', 'Conduit un peloton de chars.', 30),
                self::job('cmd', 'Sous-officier adjoint', 'blinde-sous-officier-adjoint', 'Seconde le chef de peloton et commande un char.', 40),
                self::job('equipage', 'Chef de char', 'blinde-chef-char', 'Commande l’engin, choisit les objectifs et tient la liaison.', 10),
                self::job('equipage', 'Tireur', 'blinde-tireur', 'Met en œuvre l’armement principal et les viseurs.', 20),
                self::job('equipage', 'Pilote', 'blinde-pilote', 'Conduit l’engin et tient sa mobilité.', 30),
                self::job('recce', 'Chef de patrouille', 'blinde-chef-patrouille', 'Conduit deux véhicules légers en reconnaissance.', 10),
                self::job('recce', 'Chef d’engin léger', 'blinde-chef-engin-leger', 'Commande un véhicule de reconnaissance.', 20),
                self::job('recce', 'Éclaireur', 'blinde-eclaireur', 'Débarque pour observer, renseigner et baliser.', 30),
                self::job('sout', 'Mécanicien dépanneur', 'blinde-mecanicien', 'Répare et dépanne les engins sur le terrain.', 10),
                self::job('sout', 'Ravitailleur', 'blinde-ravitailleur', 'Ravitaille en carburant et munitions.', 20),
                self::job('sout', 'Auxiliaire sanitaire', 'blinde-auxiliaire-sanitaire', 'Porte les premiers soins aux équipages.', 30),
            ],
            'roles' => [],
        ];
    }

    /**
     * Groupement aéromobile (hélicoptères de l’ALAT), grades français.
     *
     * @return array<string, mixed>
     */
    public static function airmobile(): array
    {
        return [
            'code' => self::AIRMOBILE,
            'title' => 'Groupement aéromobile',
            'summary' => 'Une unité d’hélicoptères : escadrilles de manœuvre, d’attaque et de reconnaissance, centre des opérations aériennes et maintenance, avec les équipages, les contrôleurs et les mécaniciens, et les grades français.',
            'version' => 1,
            'grade_system_code' => 'FR_CLASSIC',
            'units' => [
                self::unit(null, 'gam', 'Groupement aéromobile', 'aero-groupement', 'GAM', 10),
                self::unit('gam', 'cops', 'Centre des opérations aériennes', 'aero-operations', 'COPS', 20),
                self::unit('gam', 'ehm', 'Escadrille de manœuvre', 'aero-escadrille-manoeuvre', 'EHM', 30),
                self::unit('gam', 'eha', 'Escadrille d’attaque', 'aero-escadrille-attaque', 'EHA', 40),
                self::unit('gam', 'ehr', 'Escadrille de reconnaissance', 'aero-escadrille-reconnaissance', 'EHR', 50),
                self::unit('gam', 'maint', 'Maintenance et soutien', 'aero-maintenance', 'MCO', 60),
            ],
            'job_role_categories' => [
                self::cat('cmd', 'Commandement', 'aero-commandement', 10),
                self::cat('equipage', 'Équipages', 'aero-equipages', 20),
                self::cat('ops', 'Opérations aériennes', 'aero-operations-aeriennes', 30),
                self::cat('sout', 'Soutien', 'aero-soutien', 40),
            ],
            'job_roles' => [
                self::job('cmd', 'Commandant de groupement', 'aero-commandant', 'Conduit le groupement et répartit les missions.', 10),
                self::job('cmd', 'Commandant d’escadrille', 'aero-commandant-escadrille', 'Conduit une escadrille et ses équipages.', 20),
                self::job('cmd', 'Chef de patrouille', 'aero-chef-patrouille', 'Conduit une patrouille de deux appareils ou plus.', 30),
                self::job('equipage', 'Chef de bord', 'aero-chef-bord', 'Commande l’appareil et la mission de l’équipage.', 10),
                self::job('equipage', 'Pilote', 'aero-pilote', 'Pilote l’appareil.', 20),
                self::job('equipage', 'Copilote', 'aero-copilote', 'Seconde le pilote, navigue et met en œuvre les systèmes.', 30),
                self::job('equipage', 'Mécanicien navigant', 'aero-mecanicien-navigant', 'Suit l’appareil en vol et gère la soute et l’embarquement.', 40),
                self::job('equipage', 'Tireur de sabord', 'aero-tireur-sabord', 'Protège l’appareil avec l’arme de sabord.', 50),
                self::job('equipage', 'Tireur d’attaque', 'aero-tireur-attaque', 'Met en œuvre l’armement de l’hélicoptère d’attaque.', 60),
                self::job('ops', 'Officier opérations aériennes', 'aero-officier-operations', 'Planifie les vols, les itinéraires et les créneaux.', 10),
                self::job('ops', 'Contrôleur aérien', 'aero-controleur', 'Gère les mouvements autour des zones de poser.', 20),
                self::job('ops', 'Guide de poser', 'aero-guide-poser', 'Balise et guide les appareils au sol.', 30),
                self::job('sout', 'Mécanicien aéronautique', 'aero-mecanicien', 'Entretient et répare les appareils.', 10),
                self::job('sout', 'Ravitailleur carburant', 'aero-ravitailleur', 'Ravitaille les appareils en carburant.', 20),
                self::job('sout', 'Armurier', 'aero-armurier', 'Arme et désarme les appareils.', 30),
            ],
            'roles' => [],
        ];
    }

    /**
     * Société militaire privée (fiction milsim : contractors), grades français par défaut.
     *
     * @return array<string, mixed>
     */
    public static function privateMilitary(): array
    {
        return [
            'code' => self::PRIVATE_MILITARY,
            'title' => 'Société militaire privée',
            'summary' => 'Un cadre « contractors » pour le milsim : direction, équipes de protection et d’intervention, renseignement, logistique et formation. Les grades restent modifiables ; les fonctions décrivent les métiers.',
            'version' => 1,
            'grade_system_code' => 'FR_CLASSIC',
            'units' => [
                self::unit(null, 'smp', 'Société', 'smp-societe', 'SMP', 10),
                self::unit('smp', 'dir', 'Direction des opérations', 'smp-direction', 'DIR', 20),
                self::unit('smp', 'ops', 'Équipes opérationnelles', 'smp-equipes', 'OPS', 30),
                self::unit('ops', 'psd', 'Équipe protection rapprochée', 'smp-protection-rapprochee', 'PSD', 31),
                self::unit('ops', 'qrf', 'Équipe d’intervention rapide', 'smp-intervention-rapide', 'QRF', 32),
                self::unit('ops', 'convoi', 'Équipe escorte de convois', 'smp-escorte-convois', 'CVY', 33),
                self::unit('smp', 'rens', 'Renseignement et analyse', 'smp-renseignement', 'INT', 40),
                self::unit('smp', 'log', 'Logistique', 'smp-logistique', 'LOG', 50),
                self::unit('smp', 'form', 'Formation', 'smp-formation', 'TRG', 60),
            ],
            'job_role_categories' => [
                self::cat('dir', 'Direction', 'smp-direction', 10),
                self::cat('ops', 'Opérations', 'smp-operations', 20),
                self::cat('sout', 'Soutien', 'smp-soutien', 30),
            ],
            'job_roles' => [
                self::job('dir', 'Directeur des opérations', 'smp-directeur-operations', 'Négocie les contrats, fixe les priorités et répond du résultat.', 10),
                self::job('dir', 'Chef de projet', 'smp-chef-projet', 'Conduit un contrat sur le terrain et ses équipes.', 20),
                self::job('ops', 'Chef d’équipe', 'smp-chef-equipe', 'Conduit une équipe de protection ou d’intervention.', 10),
                self::job('ops', 'Opérateur de protection', 'smp-operateur-protection', 'Assure la sécurité d’une personne ou d’un site.', 20),
                self::job('ops', 'Opérateur d’intervention', 'smp-operateur-intervention', 'Intervient en renfort ou en extraction.', 30),
                self::job('ops', 'Chauffeur sécurité', 'smp-chauffeur', 'Conduit en convoi et maîtrise la conduite défensive.', 40),
                self::job('ops', 'Tireur de précision', 'smp-tireur-precision', 'Couvre les mouvements et les sites sensibles.', 50),
                self::job('ops', 'Infirmier tactique', 'smp-infirmier', 'Soins d’urgence et évacuation.', 60),
                self::job('sout', 'Analyste', 'smp-analyste', 'Évalue la menace et prépare les itinéraires.', 10),
                self::job('sout', 'Logisticien', 'smp-logisticien', 'Gère véhicules, équipements et approvisionnements.', 20),
                self::job('sout', 'Instructeur', 'smp-instructeur', 'Forme et qualifie les nouveaux opérateurs.', 30),
                self::job('sout', 'Opérateur transmissions', 'smp-transmissions', 'Tient les liaisons et le suivi des équipes.', 40),
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
