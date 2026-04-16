<?php

declare(strict_types=1);

namespace App\Services\Rbac;

/**
 * Données du référentiel emplois métier : 16 domaines de carrière + « Statut » (affichage).
 *
 * Cartographie des slugs existants (slugs inchangés ; seuls category / subcategory évoluent) :
 * - command_unit_commander … command_duty_officer → Administrations et services / Commandement et direction
 * - operations_officer … staff_joint_coordination_officer → Administrations et services / Opérations et plans
 * - hr, admin_staff_officer, admin_unit_secretary → Administrations et services / Ressources humaines et secrétariat
 * - intelligence_officer, staff_intel_* → Renseignement / (sous-domaines dédiés)
 * - logistics_officer, staff_sustainment_lead, staff_logistics_flow_manager → Logistique et transports / Pilotage et soutien
 * - logistics_driver, logistics_convoy_chief → Logistique et transports / Transport et convois
 * - logistics_mechanic, logistics_maint_technician, logistics_fleet_manager → Maintenance / Atelier et parc roulant
 * - infantry_* → Infanterie / (même logique de sous-domaines)
 * - fires_* → Artillerie / Coordination des feux ou Pièces et servants
 * - engineer_* → Génie de combat, BTP et NRBC / Combat du génie ou BTP et ouvrages
 * - medical_* → Santé / Soins et médecine
 * - instructor, instruction_trainer, training_officer, instruction_evaluator → Enseignement, recherche et musique / Instruction et formation
 * - veteran … status_active_duty → Statut / Affichage
 *
 * @phpstan-type CatalogEntry array{
 *   slug: string,
 *   name: string,
 *   label_en: string,
 *   category: string,
 *   subcategory: string,
 *   description: string,
 *   semantic_tier: 'authority'|'function'|'specialty'|'status'|'support'|'liaison',
 *   is_visual_only: int,
 *   display_group: int,
 *   display_weight: int,
 *   display_priority: int,
 *   permission_baseline: 'member'|'officer'|'instructor'|'medic'|'logistics'|'hr'|'rto'|'probation',
 *   mos_code?: string|null,
 *   mos_specialty_title?: string|null
 * }
 */
final class MilitaryOperationalRoleCatalogData
{
    private const DG_ADMIN = 100;

    private const DG_AERO = 200;

    private const DG_ARTY = 300;

    private const DG_ARMOR = 400;

    private const DG_EDU = 500;

    private const DG_SF = 600;

    private const DG_ENG = 700;

    private const DG_INF = 800;

    private const DG_CYBER = 900;

    private const DG_LOG = 1000;

    private const DG_MAINT = 1100;

    private const DG_INTEL = 1200;

    private const DG_CATER = 1300;

    private const DG_MED = 1400;

    private const DG_SEC = 1500;

    private const DG_SPORT = 1600;

    private const DG_STAT = 9990;

    /** @return list<CatalogEntry> */
    public static function entries(): array
    {
        $rows = [];
        $add = self::adder($rows);

        self::appendAdministrations($add);
        self::appendAerocombat($add);
        self::appendArtillerie($add);
        self::appendCombatBlind($add);
        self::appendEnseignement($add);
        self::appendForcesSpeciales($add);
        self::appendGenie($add);
        self::appendInfanterie($add);
        self::appendCyber($add);
        self::appendLogistique($add);
        self::appendMaintenance($add);
        self::appendRenseignement($add);
        self::appendRestauration($add);
        self::appendSante($add);
        self::appendSecurite($add);
        self::appendSport($add);
        self::appendStatuts($add);

        $mosMap = UsArmyMosCatalog::byJobRoleSlug();
        foreach ($rows as $i => $row) {
            $slug = $row['slug'];
            if (isset($mosMap[$slug])) {
                $rows[$i]['mos_code'] = $mosMap[$slug][0];
                $rows[$i]['mos_specialty_title'] = $mosMap[$slug][1];
            } else {
                $rows[$i]['mos_code'] = null;
                $rows[$i]['mos_specialty_title'] = null;
            }
        }

        return $rows;
    }

    /** @return callable(string, string, string, string, string, string, string, int, int, int, int, string): void */
    private static function adder(array &$rows): callable
    {
        return static function (
            string $slug,
            string $name,
            string $labelEn,
            string $cat,
            string $sub,
            string $desc,
            string $tier,
            int $vis,
            int $dg,
            int $dw,
            int $dp,
            string $baseline
        ) use (&$rows): void {
            $rows[] = [
                'slug' => $slug,
                'name' => $name,
                'label_en' => $labelEn,
                'category' => $cat,
                'subcategory' => $sub,
                'description' => $desc,
                'semantic_tier' => $tier,
                'is_visual_only' => $vis,
                'display_group' => $dg,
                'display_weight' => $dw,
                'display_priority' => $dp,
                'permission_baseline' => $baseline,
            ];
        };
    }

    /** @param callable(string, string, string, string, string, string, string, int, int, int, int, string): void $a */
    private static function appendAdministrations(callable $a): void
    {
        $c = 'Administrations et services';
        $a('command_unit_commander', 'Chef de corps', 'Commanding Officer', $c, 'Commandement et direction', 'Autorité de commandement de l’unité.', 'authority', 0, self::DG_ADMIN, 10, 10, 'officer');
        $a('command_executive_officer', 'Chef adjoint', 'Executive Officer', $c, 'Commandement et direction', 'Adjoint au commandement et relais opérationnel.', 'authority', 0, self::DG_ADMIN, 20, 20, 'officer');
        $a('command_senior_officer', 'Officier supérieur', 'Senior Officer', $c, 'Commandement et direction', 'Encadrement supérieur et coordination générale.', 'authority', 0, self::DG_ADMIN, 30, 30, 'officer');
        $a('command_duty_officer', 'Officier de permanence', 'Duty Officer', $c, 'Commandement et direction', 'Responsable de la permanence et des décisions courantes.', 'function', 0, self::DG_ADMIN, 40, 40, 'officer');
        $a('operations_officer', 'Officier opérations', 'Operations Officer (S3)', $c, 'Opérations et plans', 'Coordination des opérations et activités.', 'function', 0, self::DG_ADMIN, 50, 50, 'officer');
        $a('staff_plans_officer', 'Officier planification', 'Plans Officer', $c, 'Opérations et plans', 'Plans, ordres et synchronisation des moyens.', 'function', 0, self::DG_ADMIN, 60, 60, 'officer');
        $a('staff_battle_captain', 'Officier conduite', 'Battle Captain', $c, 'Opérations et plans', 'Conduite de la manœuvre et de la situation tactique.', 'function', 0, self::DG_ADMIN, 70, 70, 'officer');
        $a('staff_joint_coordination_officer', 'Officier coordination interarmes', 'Joint Fires Coordinator', $c, 'Opérations et plans', 'Coordination des effets interarmes et appuis.', 'liaison', 0, self::DG_ADMIN, 80, 80, 'officer');
        $a('hr', 'Gestionnaire RH', 'Human Resources Specialist', $c, 'Ressources humaines et secrétariat', 'Gestion des effectifs et du dossier personnel.', 'support', 0, self::DG_ADMIN, 90, 90, 'hr');
        $a('admin_staff_officer', 'Officier administratif', 'Administrative Officer', $c, 'Ressources humaines et secrétariat', 'Courrier, dossiers et formalités administratives.', 'support', 0, self::DG_ADMIN, 100, 100, 'officer');
        $a('admin_unit_secretary', 'Secrétaire unité', 'Unit Secretary', $c, 'Ressources humaines et secrétariat', 'Secrétariat et suivi administratif de l’unité.', 'support', 0, self::DG_ADMIN, 110, 110, 'member');
        $a('admin_hr_officer', 'Officier ressources humaines', 'Human Resources Officer', $c, 'Ressources humaines et secrétariat', 'Pilotage RH, politiques personnel et conformité.', 'function', 0, self::DG_ADMIN, 120, 120, 'hr');
        $a('admin_hr_technician', 'Technicien ressources humaines', 'Human Resources Technician', $c, 'Ressources humaines et secrétariat', 'Traitement des dossiers, paie et formalités.', 'support', 0, self::DG_ADMIN, 130, 130, 'hr');
        $a('admin_finance_officer', 'Officier finances et pilotage', 'Comptroller / Financial Management Officer', $c, 'Finances et performance', 'Pilotage budgétaire et performance organisationnelle.', 'function', 0, self::DG_ADMIN, 140, 140, 'officer');
        $a('admin_finance_technician', 'Technicien finances et pilotage', 'Budget Analyst', $c, 'Finances et performance', 'Exécution budgétaire, contrôle et reporting.', 'support', 0, self::DG_ADMIN, 150, 150, 'member');
        $a('admin_operative_clerk', 'Opérateur administratif', 'Administrative Specialist', $c, 'Ressources humaines et secrétariat', 'Saisie, classement et suivi des dossiers courants.', 'support', 0, self::DG_ADMIN, 160, 160, 'member');
        $a('admin_legal_advisor', 'Conseiller juridique militaire', 'Staff Judge Advocate (advisor)', $c, 'Juridique et conformité', 'Conseil juridique et conformité réglementaire.', 'function', 0, self::DG_ADMIN, 170, 170, 'officer');
        $a('admin_protocol_officer', 'Officier protocole et relations', 'Protocol Officer', $c, 'Communication institutionnelle', 'Cérémonial, relations officielles et image.', 'liaison', 0, self::DG_ADMIN, 180, 180, 'officer');
    }

    /** @param callable(string, string, string, string, string, string, string, int, int, int, int, string): void $a */
    private static function appendAerocombat(callable $a): void
    {
        $c = 'Aérocombat';
        $a('aero_tactical_pilot', 'Pilote tactique', 'Aviator (tactical)', $c, 'Manœuvre aérienne', 'Pilotage en mission tactique.', 'specialty', 0, self::DG_AERO, 10, 10, 'officer');
        $a('aero_weapon_systems_officer', 'Officier systèmes d’armes aéro', 'Weapons Systems Officer', $c, 'Manœuvre aérienne', 'Emploi des capteurs et armements embarqués.', 'function', 0, self::DG_AERO, 20, 20, 'officer');
        $a('aero_loadmaster', 'Chef chargement aérien', 'Loadmaster', $c, 'Aérologistique', 'Préparation et sécurisation des chargements.', 'support', 0, self::DG_AERO, 30, 30, 'member');
        $a('aero_air_delivery_chief', 'Chef groupe livraison par air', 'Aerial Delivery / Drop Zone Team Lead', $c, 'Aérologistique', 'Largages et zone de largage.', 'function', 0, self::DG_AERO, 40, 40, 'officer');
        $a('aero_refuel_operator', 'Opérateur ravitaillement en vol', 'Aerial Refueling Specialist', $c, 'Soutien aérien', 'Assistance au ravitaillement et liaisons piste.', 'support', 0, self::DG_AERO, 50, 50, 'member');
        $a('aero_ground_support_chief', 'Chef détachement soutien sol', 'Aircraft Ground Support Lead', $c, 'Soutien aérien', 'Mise à poste, trafic au sol et sécurité mouvements.', 'function', 0, self::DG_AERO, 60, 60, 'officer');
    }

    /** @param callable(string, string, string, string, string, string, string, int, int, int, int, string): void $a */
    private static function appendArtillerie(callable $a): void
    {
        $c = 'Artillerie';
        $a('fires_jtac', 'JTAC', 'Joint Terminal Attack Controller', $c, 'Coordination des feux', 'Contrôleur d’attaques au sol.', 'liaison', 0, self::DG_ARTY, 10, 10, 'officer');
        $a('fires_forward_observer', 'Observateur avancé', 'Forward Observer', $c, 'Coordination des feux', 'Observation et ajustement des tirs.', 'liaison', 0, self::DG_ARTY, 20, 20, 'member');
        $a('fires_support_officer', 'Officier appuis feux', 'Fire Support Officer', $c, 'Coordination des feux', 'Synthèse et coordination des appuis.', 'liaison', 0, self::DG_ARTY, 30, 30, 'officer');
        $a('fires_gun_chief', 'Chef pièce', 'Gun Chief / Section Chief', $c, 'Pièces et servants', 'Chef de pièce et conduite du tir.', 'function', 0, self::DG_ARTY, 40, 40, 'member');
        $a('fires_gun_crew', 'Servant artillerie', 'Cannon Crewmember', $c, 'Pièces et servants', 'Mise en œuvre et service de pièce.', 'function', 0, self::DG_ARTY, 50, 50, 'member');
        $a('arty_group_leader', 'Chef groupe artillerie', 'Artillery Section Sergeant', $c, 'Encadrement', 'Encadrement d’un détachement pièce ou batterie.', 'authority', 0, self::DG_ARTY, 60, 60, 'officer');
        $a('arty_fire_direction', 'Calculateur tir', 'Fire Direction Specialist', $c, 'Conduite du tir', 'Calculs balistiques et transmission ordres de tir.', 'specialty', 0, self::DG_ARTY, 70, 70, 'member');
        $a('arty_mortar_squad', 'Chef section mortiers', 'Mortar Squad Leader', $c, 'Mortiers', 'Encadrement mortiers accompagnement.', 'function', 0, self::DG_ARTY, 80, 80, 'officer');
        $a('arty_radar_operator', 'Opérateur radar artillerie', 'Counter‑fire Radar Operator', $c, 'Acquisition', 'Acquisition cibles et renseignement d’acquisition.', 'specialty', 0, self::DG_ARTY, 90, 90, 'member');
    }

    /** @param callable(string, string, string, string, string, string, string, int, int, int, int, string): void $a */
    private static function appendCombatBlind(callable $a): void
    {
        $c = 'Combat blindé';
        $a('armor_platoon_leader', 'Chef peloton blindé', 'Armor Platoon Leader', $c, 'Manœuvre blindée', 'Conduite d’un peloton chars / blindés.', 'authority', 0, self::DG_ARMOR, 10, 10, 'officer');
        $a('armor_vehicle_commander', 'Chef d’engin', 'Tank Commander', $c, 'Manœuvre blindée', 'Commandement d’un char ou blindé de combat.', 'function', 0, self::DG_ARMOR, 20, 20, 'officer');
        $a('armor_driver', 'Conducteur blindé', 'Armor Crewman (driver)', $c, 'Équipage', 'Conduite tactique du blindé.', 'function', 0, self::DG_ARMOR, 30, 30, 'member');
        $a('armor_gunner', 'Tireur embarqué', 'Armor Crewman (gunner)', $c, 'Équipage', 'Emploi du canon et coaxiaux.', 'function', 0, self::DG_ARMOR, 40, 40, 'member');
        $a('armor_loader', 'Chargeur', 'Armor Crewman (loader)', $c, 'Équipage', 'Manipulation munitions et aide conduite de tir.', 'support', 0, self::DG_ARMOR, 50, 50, 'member');
        $a('armor_cavalry_scout', 'Éclaireur blindé', 'Cavalry Scout', $c, 'Reconnaissance', 'Reconnaissance blindée et renseignement.', 'specialty', 0, self::DG_ARMOR, 60, 60, 'member');
        $a('armor_recovery_operator', 'Conducteur dépannage blindé', 'Recovery Vehicle Operator', $c, 'Soutien technique', 'Dépannage et remorquage sur le champ.', 'support', 0, self::DG_ARMOR, 70, 70, 'member');
    }

    /** @param callable(string, string, string, string, string, string, string, int, int, int, int, string): void $a */
    private static function appendEnseignement(callable $a): void
    {
        $c = 'Enseignement, recherche et musique';
        $a('instructor', 'Instructeur', 'Drill Sergeant / Instructor', $c, 'Instruction et formation', 'Instruction collective et maintien des standards.', 'function', 0, self::DG_EDU, 10, 10, 'instructor');
        $a('instruction_trainer', 'Formateur', 'Training Instructor', $c, 'Instruction et formation', 'Conception et animation de modules pédagogiques.', 'function', 0, self::DG_EDU, 20, 20, 'instructor');
        $a('training_officer', 'Responsable formation', 'Training / Schools Officer', $c, 'Instruction et formation', 'Pilotage des parcours et des qualifications.', 'function', 0, self::DG_EDU, 30, 30, 'instructor');
        $a('instruction_evaluator', 'Évaluateur', 'Evaluator / Examiner', $c, 'Instruction et formation', 'Évaluation des compétences et des qualifications.', 'function', 0, self::DG_EDU, 40, 40, 'instructor');
        $a('edu_academic_instructor', 'Instructeur académique', 'Academic Instructor', $c, 'Recherche et doctrine', 'Cours théoriques et veille doctrinale.', 'function', 0, self::DG_EDU, 50, 50, 'instructor');
        $a('edu_doctrine_officer', 'Officier doctrine et études', 'Doctrine / G‑2 Training Developer', $c, 'Recherche et doctrine', 'Capitalisation et mise à jour des référentiels.', 'function', 0, self::DG_EDU, 60, 60, 'officer');
        $a('edu_bandmaster', 'Chef de musique', 'Bandmaster', $c, 'Musique militaire', 'Direction de la formation musicale.', 'function', 0, self::DG_EDU, 70, 70, 'officer');
        $a('edu_musician', 'Musicien militaire', 'Army Band Musician', $c, 'Musique militaire', 'Interprétation et cérémonies.', 'specialty', 0, self::DG_EDU, 80, 80, 'member');
    }

    /** @param callable(string, string, string, string, string, string, string, int, int, int, int, string): void $a */
    private static function appendForcesSpeciales(callable $a): void
    {
        $c = 'Forces spéciales';
        $a('sf_team_leader', 'Chef détachement spécialisé', 'Special Forces Team Leader', $c, 'Actions spéciales', 'Encadrement d’une équipe spécialisée.', 'authority', 0, self::DG_SF, 10, 10, 'officer');
        $a('sf_operator', 'Opérateur spécialisé', 'Special Forces Operator', $c, 'Actions spéciales', 'Exécution de missions à haute valeur ajoutée.', 'specialty', 0, self::DG_SF, 20, 20, 'member');
        $a('sf_comms', 'Transmetteur forces spéciales', 'Special Operations Communications Sergeant', $c, 'Soutien technique', 'Transmissions sécurisées et liaisons.', 'liaison', 0, self::DG_SF, 30, 30, 'rto');
        $a('sf_intelligence', 'Analyste opérations spéciales', 'Special Operations Intelligence Analyst', $c, 'Renseignement opérationnel', 'Préparation et fusion d’information ciblée.', 'function', 0, self::DG_SF, 40, 40, 'officer');
        $a('sf_medic', 'Médical opérations spéciales', 'Special Operations Combat Medic', $c, 'Soutien sanitaire avancé', 'Soins avancés en milieu dégradé.', 'specialty', 0, self::DG_SF, 50, 50, 'medic');
    }

    /** @param callable(string, string, string, string, string, string, string, int, int, int, int, string): void $a */
    private static function appendGenie(callable $a): void
    {
        $c = 'Génie de combat, BTP et NRBC';
        $a('engineer_sapper', 'Sapeur', 'Combat Engineer', $c, 'Combat du génie', 'Ouverture de passages et travaux au contact.', 'function', 0, self::DG_ENG, 10, 10, 'member');
        $a('engineer_eod', 'Démineur', 'Explosive Ordnance Disposal Specialist', $c, 'Combat du génie', 'Neutralisation des dangers explosifs.', 'specialty', 0, self::DG_ENG, 20, 20, 'member');
        $a('engineer_group_chief', 'Chef groupe génie', 'Engineer Squad Leader', $c, 'Combat du génie', 'Encadrement d’un groupe de combat du génie.', 'function', 0, self::DG_ENG, 30, 30, 'officer');
        $a('engineer_infra_technician', 'Technicien infrastructure', 'Horizontal Construction Engineer', $c, 'BTP et ouvrages', 'Travaux d’infrastructure et ouvrages.', 'support', 0, self::DG_ENG, 40, 40, 'member');
        $a('engineer_works_lead', 'Responsable travaux', 'Construction Supervisor', $c, 'BTP et ouvrages', 'Pilotage des chantiers et contrôle qualité.', 'support', 0, self::DG_ENG, 50, 50, 'officer');
        $a('engineer_cbrn_specialist', 'Spécialiste NRBC', 'CBRN Specialist', $c, 'NRBC', 'Détection, protection et décontamination.', 'specialty', 0, self::DG_ENG, 60, 60, 'member');
        $a('engineer_bridge_raft', 'Pontier / opérateur franchissement', 'Bridge Crewmember', $c, 'Franchissement', 'Appui franchissements et rivières.', 'function', 0, self::DG_ENG, 70, 70, 'member');
        $a('engineer_urban_breach', 'Spécialiste ouverture d’axes', 'Breacher / Urban Mobility', $c, 'Combat du génie', 'Ouverture d’accès en milieu bâti.', 'specialty', 0, self::DG_ENG, 80, 80, 'member');
    }

    /** @param callable(string, string, string, string, string, string, string, int, int, int, int, string): void $a */
    private static function appendInfanterie(callable $a): void
    {
        $c = 'Infanterie';
        $a('infantry_section_chief', 'Chef de section', 'Infantry Platoon Leader', $c, 'Commandement', 'Encadrement d’une section au combat.', 'authority', 0, self::DG_INF, 10, 10, 'officer');
        $a('infantry_group_chief', 'Chef de groupe', 'Infantry Squad Leader', $c, 'Commandement', 'Encadrement d’un groupe tactique.', 'function', 0, self::DG_INF, 20, 20, 'officer');
        $a('infantry_team_chief', 'Chef d’équipe', 'Team Leader', $c, 'Commandement', 'Encadrement d’une équipe élémentaire.', 'function', 0, self::DG_INF, 30, 30, 'officer');
        $a('infantry_rifleman', 'Fusilier', 'Rifleman', $c, 'Combattant', 'Combattant d’infanterie polyvalent.', 'function', 0, self::DG_INF, 40, 40, 'member');
        $a('infantry_grenadier', 'Grenadier', 'Grenadier', $c, 'Combattant', 'Appui grenades et armement lourd léger.', 'function', 0, self::DG_INF, 50, 50, 'member');
        $a('infantry_sharpshooter', 'Tireur d’élite', 'Sharpshooter', $c, 'Combattant', 'Précision renforcée et tir d’appui.', 'specialty', 0, self::DG_INF, 60, 60, 'member');
        $a('infantry_marksman', 'Tireur de précision', 'Designated Marksman', $c, 'Combattant', 'Neutralisation sélective à moyenne portée.', 'specialty', 0, self::DG_INF, 70, 70, 'member');
        $a('infantry_sniper', 'Tireur isolé', 'Sniper', $c, 'Combattant', 'Tir de précision longue portée en retrait.', 'specialty', 0, self::DG_INF, 75, 75, 'member');
        $a('infantry_machine_gunner', 'Mitrailleur', 'Machine Gunner', $c, 'Combattant', 'Appui feu soutenu et manœuvre d’appui.', 'function', 0, self::DG_INF, 80, 80, 'member');
        $a('infantry_radio_operator', 'Opérateur radio', 'Radio Telephone Operator', $c, 'Spécialités', 'Transmissions et liaisons tactiques.', 'liaison', 0, self::DG_INF, 90, 90, 'rto');
        $a('infantry_scout', 'Éclaireur', 'Scout / Observer', $c, 'Spécialités', 'Reconnaissance et renseignement terrain.', 'specialty', 0, self::DG_INF, 100, 100, 'member');
        $a('infantry_team_pair_chief', 'Chef binôme', 'Fire Team Leader', $c, 'Spécialités', 'Coordination d’un binôme au contact.', 'function', 0, self::DG_INF, 110, 110, 'member');
        $a('infantry_at_specialist', 'Spécialiste anti‑blindés', 'Anti‑armor Specialist', $c, 'Combattant', 'Engagement des blindés légers et fortifications.', 'specialty', 0, self::DG_INF, 120, 120, 'member');
        $a('infantry_mortar_operator', 'Servant mortier accompagnement', 'Indirect Fire Infantryman', $c, 'Appuis organiques', 'Mise en œuvre mortier d’accompagnement.', 'function', 0, self::DG_INF, 130, 130, 'member');
    }

    /** @param callable(string, string, string, string, string, string, string, int, int, int, int, string): void $a */
    private static function appendCyber(callable $a): void
    {
        $c = 'Cyber, informatique et télécoms';
        $a('cyber_telecoms_group_chief', 'Chef groupe télécoms', 'Signal Team Chief', $c, 'Télécoms de manœuvre', 'Encadrement détachement transmissions.', 'function', 0, self::DG_CYBER, 10, 10, 'officer');
        $a('cyber_signal_operator', 'Combattant télécoms', 'Signal Support Systems Operator', $c, 'Télécoms de manœuvre', 'Mise en œuvre des moyens de transmission tactiques.', 'function', 0, self::DG_CYBER, 20, 20, 'rto');
        $a('cyber_technician', 'Technicien cybersécurité', 'Cyber Operations Specialist', $c, 'Cybersécurité', 'Protection, détection et réponse sur systèmes.', 'specialty', 0, self::DG_CYBER, 30, 30, 'member');
        $a('cyber_systems_technician', 'Technicien systèmes d’information', 'Information Technology Specialist', $c, 'Systèmes d’information', 'Exploitation et maintenance applicative.', 'support', 0, self::DG_CYBER, 40, 40, 'member');
        $a('cyber_network_technician', 'Technicien réseaux informatiques', 'Network Systems Technician', $c, 'Réseaux et interconnexion', 'Réseaux campagne et accès sécurisés.', 'support', 0, self::DG_CYBER, 50, 50, 'member');
        $a('cyber_dev_technician', 'Technicien développeur informatique', 'Software Developer (military IT)', $c, 'Développement et intégration', 'Développement et scripts d’outillage.', 'specialty', 0, self::DG_CYBER, 60, 60, 'member');
        $a('cyber_telecom_expert_officer', 'Officier expert télécoms', 'Communications Officer (signal)', $c, 'Encadrement expert', 'Architecture des moyens de communication.', 'function', 0, self::DG_CYBER, 70, 70, 'officer');
        $a('cyber_is_expert_officer', 'Officier expert systèmes d’information', 'Information Systems Management Officer', $c, 'Encadrement expert', 'Pilotage des SI et interopérabilité.', 'function', 0, self::DG_CYBER, 80, 80, 'officer');
        $a('cyber_sec_expert_officer', 'Officier expert cybersécurité', 'Cyber Electromagnetic Activity Officer', $c, 'Encadrement expert', 'Stratégie cyber et supervision des opérations.', 'function', 0, self::DG_CYBER, 90, 90, 'officer');
        $a('cyber_it_telecom_officer', 'Officier informatique et télécoms', 'Signal Officer (branch)', $c, 'Encadrement expert', 'Synthèse SI et télécommunications.', 'function', 0, self::DG_CYBER, 100, 100, 'officer');
        $a('cyber_ew_technician', 'Technicien guerre électronique', 'Electronic Warfare Specialist', $c, 'Guerre électronique', 'Exploitation et brouillage électromagnétique.', 'specialty', 0, self::DG_CYBER, 110, 110, 'member');
        $a('cyber_satcom_operator', 'Opérateur satellite tactique', 'Satellite Communications Operator', $c, 'Télécoms de manœuvre', 'Liaisons satellitaires et relais.', 'liaison', 0, self::DG_CYBER, 120, 120, 'member');
    }

    /** @param callable(string, string, string, string, string, string, string, int, int, int, int, string): void $a */
    private static function appendLogistique(callable $a): void
    {
        $c = 'Logistique et transports';
        $a('logistics_officer', 'Officier logistique', 'Logistics Officer (S4)', $c, 'Pilotage et soutien', 'Pilotage du soutien et de la chaîne logistique.', 'function', 0, self::DG_LOG, 10, 10, 'officer');
        $a('staff_sustainment_lead', 'Responsable soutien', 'Property Book / Supply NCO', $c, 'Pilotage et soutien', 'Gestion des stocks et du soutien quotidien.', 'support', 0, self::DG_LOG, 20, 20, 'logistics');
        $a('staff_logistics_flow_manager', 'Gestionnaire flux logistiques', 'Transportation Management Coordinator', $c, 'Pilotage et soutien', 'Organisation des flux, convois et dotations.', 'support', 0, self::DG_LOG, 30, 30, 'logistics');
        $a('logistics_driver', 'Conducteur militaire', 'Motor Transport Operator', $c, 'Transport et convois', 'Conduite et manœuvre des véhicules logistiques.', 'support', 0, self::DG_LOG, 40, 40, 'member');
        $a('logistics_convoy_chief', 'Chef convoi', 'Convoy Commander', $c, 'Transport et convois', 'Responsabilité d’un convoi ou d’un détachement roulant.', 'support', 0, self::DG_LOG, 50, 50, 'officer');
        $a('log_supply_group_chief', 'Chef groupe approvisionnement', 'Supply Sergeant / Class IX lead', $c, 'Magasinage et dotations', 'Encadrement cellule approvisionnement.', 'function', 0, self::DG_LOG, 60, 60, 'officer');
        $a('log_warehouse_chief', 'Chef groupe magasinier', 'Warehouse Foreman', $c, 'Magasinage et dotations', 'Gestion d’un dépôt et rotations.', 'function', 0, self::DG_LOG, 70, 70, 'officer');
        $a('log_ammo_mag_chief', 'Chef groupe magasinier munitions', 'Ammunition Stock Control NCO', $c, 'Munitions', 'Sécurité et comptabilité des munitions.', 'specialty', 0, self::DG_LOG, 80, 80, 'logistics');
        $a('log_fuel_specialist', 'Spécialiste carburant', 'Petroleum Supply Specialist', $c, 'Carburant et fluides', 'Distribution carburants et sécurité.', 'support', 0, self::DG_LOG, 90, 90, 'logistics');
        $a('log_rations_manager', 'Responsable vivres', 'Food Service Supervisor (field)', $c, 'Vivres opérationnelles', 'Planification rations et dotations terrain.', 'support', 0, self::DG_LOG, 100, 100, 'logistics');
    }

    /** @param callable(string, string, string, string, string, string, string, int, int, int, int, string): void $a */
    private static function appendMaintenance(callable $a): void
    {
        $c = 'Maintenance';
        $a('logistics_mechanic', 'Mécanicien', 'Wheeled Vehicle Mechanic', $c, 'Atelier et parc roulant', 'Maintenance de premier et second échelon.', 'support', 0, self::DG_MAINT, 10, 10, 'logistics');
        $a('logistics_maint_technician', 'Technicien maintenance', 'All‑Systems Maintainer', $c, 'Atelier et parc roulant', 'Diagnostic et réparation des systèmes.', 'support', 0, self::DG_MAINT, 20, 20, 'logistics');
        $a('logistics_fleet_manager', 'Responsable parc matériel', 'Motor Sergeant / Fleet Manager', $c, 'Atelier et parc roulant', 'Gestion du parc et disponibilité opérationnelle.', 'support', 0, self::DG_MAINT, 30, 30, 'logistics');
        $a('maint_armorer', 'Armurier', 'Armorer', $c, 'Armement léger', 'Maintenance armes et optiques.', 'specialty', 0, self::DG_MAINT, 40, 40, 'member');
        $a('maint_ground_support', 'Technicien soutien sol aéronef', 'Aircraft Pneudraulic Systems Repairer', $c, 'Aéronautique légère', 'Maintenance légère et soutien piste.', 'support', 0, self::DG_MAINT, 50, 50, 'member');
        $a('maint_elec_tech', 'Technicien électromécanicien', 'Electrical Equipment Repairer', $c, 'Énergie et réseaux', 'Groupes électrogènes et distribution.', 'support', 0, self::DG_MAINT, 60, 60, 'member');
        $a('maint_quality_controller', 'Contrôleur qualité maintenance', 'Quality Assurance / Maintenance Inspector', $c, 'Qualité et sécurité', 'Contrôles et remise en condition.', 'function', 0, self::DG_MAINT, 70, 70, 'officer');
    }

    /** @param callable(string, string, string, string, string, string, string, int, int, int, int, string): void $a */
    private static function appendRenseignement(callable $a): void
    {
        $c = 'Renseignement';
        $a('intelligence_officer', 'Officier renseignement', 'Intelligence Officer (G2/S2)', $c, 'Direction et coordination', 'Collecte, analyse et diffusion du renseignement.', 'function', 0, self::DG_INTEL, 10, 10, 'officer');
        $a('staff_intel_analyst', 'Analyste renseignement', 'All‑Source Intelligence Analyst', $c, 'Analyse et production', 'Production d’analyses et de fiches situation.', 'function', 0, self::DG_INTEL, 20, 20, 'officer');
        $a('staff_intel_cell', 'Cellule renseignement', 'Intelligence Fusion Cell Operator', $c, 'Analyse et production', 'Traitement et diffusion au sein de la cellule.', 'function', 0, self::DG_INTEL, 30, 30, 'member');
        $a('staff_intel_exploitation', 'Officier exploitation', 'SIGINT / Collection Manager', $c, 'Exploitation technique', 'Exploitation technique des sources et des flux.', 'specialty', 0, self::DG_INTEL, 40, 40, 'member');
        $a('intel_humint', 'Opérateur renseignement source', 'Human Intelligence Collector', $c, 'Acquisition', 'Collecte auprès de sources et liaison.', 'liaison', 0, self::DG_INTEL, 50, 50, 'member');
        $a('intel_geo', 'Analyste géospatial', 'Geospatial Intelligence Analyst', $c, 'Analyse et production', 'Imagery et cartographie de situation.', 'specialty', 0, self::DG_INTEL, 60, 60, 'member');
        $a('intel_sigint_tech', 'Technicien interception des signaux', 'Signals Intelligence Analyst', $c, 'Exploitation technique', 'Interception et tri de signaux.', 'specialty', 0, self::DG_INTEL, 70, 70, 'member');
        $a('intel_osint', 'Analyste sources ouvertes', 'Open‑Source Intelligence Analyst', $c, 'Analyse et production', 'Veille médias et réseaux ouverts.', 'function', 0, self::DG_INTEL, 80, 80, 'member');
        $a('intel_counterint', 'Officier contre-ingérence', 'Counterintelligence Officer', $c, 'Sécurité du renseignement', 'Prévention des compromissions.', 'function', 0, self::DG_INTEL, 90, 90, 'officer');
    }

    /** @param callable(string, string, string, string, string, string, string, int, int, int, int, string): void $a */
    private static function appendRestauration(callable $a): void
    {
        $c = 'Restauration';
        $a('cater_field_kitchen_chief', 'Chef cuisine de campagne', 'Field Kitchen NCOIC', $c, 'Cuisine opérationnelle', 'Encadrement cuisine roulante ou fixe.', 'function', 0, self::DG_CATER, 10, 10, 'logistics');
        $a('cater_cook', 'Cuisinier militaire', 'Culinary Specialist', $c, 'Cuisine opérationnelle', 'Préparation des repas en masse.', 'support', 0, self::DG_CATER, 20, 20, 'member');
        $a('cater_baker', 'Boulanger militaire', 'Baker', $c, 'Boulangerie', 'Production pain et viennoiserie.', 'support', 0, self::DG_CATER, 30, 30, 'member');
        $a('cater_dietitian', 'Diététicien de collectivité', 'Dietitian (military feeding)', $c, 'Nutrition', 'Équilibre des menus et contraintes sanitaires.', 'function', 0, self::DG_CATER, 40, 40, 'medic');
        $a('cater_hygiene', 'Contrôleur hygiène alimentaire', 'Food Safety Inspector', $c, 'Qualité et sécurité', 'Contrôles HACCP et traçabilité.', 'support', 0, self::DG_CATER, 50, 50, 'member');
    }

    /** @param callable(string, string, string, string, string, string, string, int, int, int, int, string): void $a */
    private static function appendSante(callable $a): void
    {
        $c = 'Santé';
        $a('medical_officer', 'Médecin militaire', 'Medical Officer / Physician', $c, 'Médecine', 'Responsabilité médicale et décisions sanitaires.', 'function', 0, self::DG_MED, 10, 10, 'medic');
        $a('medical_nurse', 'Infirmier militaire', 'Practical Nursing Specialist', $c, 'Médecine', 'Soins infirmiers et stabilisation.', 'function', 0, self::DG_MED, 20, 20, 'medic');
        $a('medical_auxiliary', 'Auxiliaire sanitaire', 'Combat Medic Specialist (assistant)', $c, 'Médecine', 'Soutien sanitaire et assistance au poste de secours.', 'support', 0, self::DG_MED, 30, 30, 'medic');
        $a('medical_first_responder', 'Secouriste', 'Combat Medic Specialist', $c, 'Secours et évacuation', 'Premiers secours et évacuation sanitaire initiale.', 'support', 0, self::DG_MED, 40, 40, 'medic');
        $a('medical_dentist', 'Chirurgien‑dentiste militaire', 'Dental Officer', $c, 'Médecine spécialisée', 'Soins dentaires de détachement.', 'function', 0, self::DG_MED, 50, 50, 'medic');
        $a('medical_pharmacist', 'Pharmacien militaire', 'Pharmacy Officer', $c, 'Médecine spécialisée', 'Chaîne du médicament et stocks.', 'function', 0, self::DG_MED, 60, 60, 'medic');
        $a('medical_lab_tech', 'Technicien laboratoire médical', 'Medical Laboratory Specialist', $c, 'Biologie médicale', 'Analyses et dépistage.', 'specialty', 0, self::DG_MED, 70, 70, 'medic');
        $a('medical_combat_paramedic', 'Combattant soignant', 'Health Care Sergeant (line medic)', $c, 'Secours et évacuation', 'Soins au plus près du combat.', 'specialty', 0, self::DG_MED, 80, 80, 'medic');
    }

    /** @param callable(string, string, string, string, string, string, string, int, int, int, int, string): void $a */
    private static function appendSecurite(callable $a): void
    {
        $c = 'Sécurité et prévention';
        $a('sec_military_police', 'Membre police militaire', 'Military Police', $c, 'Police et sûreté', 'Ordre public militaire et sûreté.', 'function', 0, self::DG_SEC, 10, 10, 'officer');
        $a('sec_installation_guard', 'Chef poste de garde', 'Installation Access Control Supervisor', $c, 'Surveillance des sites', 'Planification des gardiennages.', 'function', 0, self::DG_SEC, 20, 20, 'officer');
        $a('sec_sentinel', 'Sentinelle', 'Guard', $c, 'Surveillance des sites', 'Surveillance statique et contrôle d’accès.', 'support', 0, self::DG_SEC, 30, 30, 'member');
        $a('sec_k9_team_lead', 'Chef de groupe maître‑chien', 'Military Working Dog Handler (team lead)', $c, 'Maîtres‑chiens', 'Encadrement équipes cynotechniques.', 'function', 0, self::DG_SEC, 40, 40, 'member');
        $a('sec_k9_handler', 'Maître‑chien', 'Military Working Dog Handler', $c, 'Maîtres‑chiens', 'Détection et protection avec chien.', 'specialty', 0, self::DG_SEC, 50, 50, 'member');
        $a('sec_firefighter', 'Sapeur‑pompier militaire', 'Firefighter (military)', $c, 'Incendie et secours', 'Intervention incendie et secours technique.', 'support', 0, self::DG_SEC, 60, 60, 'member');
        $a('sec_prevention_advisor', 'Conseiller prévention des risques', 'Safety Officer', $c, 'Prévention', 'Analyse des risques et sensibilisation.', 'function', 0, self::DG_SEC, 70, 70, 'officer');
    }

    /** @param callable(string, string, string, string, string, string, string, int, int, int, int, string): void $a */
    private static function appendSport(callable $a): void
    {
        $c = 'Sport';
        $a('sport_physical_instructor', 'Instructeur physique militaire', 'Master Fitness Trainer', $c, 'Préparation physique', 'Programmation et encadrement de la condition physique.', 'function', 0, self::DG_SPORT, 10, 10, 'instructor');
        $a('sport_high_level_athlete', 'Sportif de haut niveau', 'Athlete (service sports program)', $c, 'Représentation sportive', 'Représentation en compétitions officielles.', 'specialty', 0, self::DG_SPORT, 20, 20, 'member');
        $a('sport_rehab_coach', 'Coach réathlétisation', 'Physical Therapy / Reconditioning NCO', $c, 'Prévention des blessures', 'Retour à l’effort et prévention.', 'support', 0, self::DG_SPORT, 30, 30, 'medic');
        $a('sport_facility_manager', 'Responsable infrastructure sportive', 'Sports Facility Manager', $c, 'Installations', 'Gestion des équipements et plannings.', 'support', 0, self::DG_SPORT, 40, 40, 'member');
    }

    /** @param callable(string, string, string, string, string, string, string, int, int, int, int, string): void $a */
    private static function appendStatuts(callable $a): void
    {
        $c = 'Statut';
        $a('veteran', 'Vétéran', 'Veteran', $c, 'Affichage', 'Ancien combattant ou membre d’honneur actif en visibilité.', 'status', 1, self::DG_STAT, 10, 10, 'member');
        $a('status_in_training', 'En formation', 'In training', $c, 'Affichage', 'Parcours de formation en cours.', 'status', 1, self::DG_STAT, 20, 20, 'member');
        $a('probation_member', 'En probation', 'Probationary Member', $c, 'Affichage', 'Intégration sous période probatoire.', 'status', 1, self::DG_STAT, 30, 30, 'probation');
        $a('suspended_status', 'Suspendu', 'Suspended', $c, 'Affichage', 'Participation suspendue — visibilité limitée.', 'status', 1, self::DG_STAT, 40, 40, 'probation');
        $a('status_reservist', 'Réserviste', 'Reservist', $c, 'Affichage', 'Statut de réserve et disponibilité partielle.', 'status', 1, self::DG_STAT, 50, 50, 'member');
        $a('certified_instructor', 'Instructeur certifié', 'Certified Instructor', $c, 'Affichage', 'Qualification pédagogique reconnue.', 'status', 1, self::DG_STAT, 60, 60, 'instructor');
        $a('status_active_duty', 'En service actif', 'Active Duty', $c, 'Affichage', 'Engagement opérationnel à plein temps.', 'status', 1, self::DG_STAT, 70, 70, 'member');
    }
}
