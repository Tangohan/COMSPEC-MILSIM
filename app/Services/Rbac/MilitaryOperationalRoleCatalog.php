<?php

declare(strict_types=1);

namespace App\Services\Rbac;

/**
 * Référentiel unique : rôles militaires / organisationnels (FR + label_en), hiérarchie catégorie → sous-catégorie.
 * Alimente `roles` (intra) et `personnel_job_roles` via MilitaryRoleCatalogSyncService.
 *
 * @phpstan-type CatalogEntry array{
 *   slug: string,
 *   name: string,
 *   label_en: string,
 *   category: string,
 *   subcategory: string,
 *   description: string,
 *   semantic_tier: 'authority'|'function'|'specialty'|'status',
 *   is_visual_only: int,
 *   display_group: int,
 *   display_weight: int,
 *   display_priority: int,
 *   permission_baseline: 'member'|'officer'|'instructor'|'medic'|'logistics'|'hr'|'rto'|'probation'
 * }
 */
final class MilitaryOperationalRoleCatalog
{
    /** @return list<CatalogEntry> */
    public static function entries(): array
    {
        $rows = [];

        $add = static function (
            array &$rows,
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
        ): void {
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

        $em = 20;
        $add($rows, 'command_unit_commander', 'Chef de corps', 'Commanding Officer', 'État-major', 'Commandement', 'Autorité de commandement de l’unité.', 'authority', 0, $em, 10, 10, 'officer');
        $add($rows, 'command_executive_officer', 'Chef adjoint', 'Executive Officer', 'État-major', 'Commandement', 'Adjoint au commandement et relais opérationnel.', 'authority', 0, $em, 20, 20, 'officer');
        $add($rows, 'command_senior_officer', 'Officier supérieur', 'Senior Officer', 'État-major', 'Commandement', 'Encadrement supérieur et coordination générale.', 'authority', 0, $em, 30, 30, 'officer');
        $add($rows, 'command_duty_officer', 'Officier de permanence', 'Duty Officer', 'État-major', 'Commandement', 'Responsable de la permanence et des décisions courantes.', 'function', 0, $em, 40, 40, 'officer');

        $add($rows, 'operations_officer', 'Officier opérations', 'Operations Officer (S3)', 'État-major', 'Opérations', 'Coordination des opérations et activités.', 'function', 0, $em, 50, 50, 'officer');
        $add($rows, 'staff_plans_officer', 'Officier planification', 'Plans Officer', 'État-major', 'Opérations', 'Plans, ordres et synchronisation des moyens.', 'function', 0, $em, 60, 60, 'officer');
        $add($rows, 'staff_battle_captain', 'Officier conduite', 'Battle Captain', 'État-major', 'Opérations', 'Conduite de la manœuvre et de la situation tactique.', 'function', 0, $em, 70, 70, 'officer');
        $add($rows, 'staff_joint_coordination_officer', 'Officier coordination interarmes', 'Joint Fires Coordinator', 'État-major', 'Opérations', 'Coordination des effets interarmes et appuis.', 'function', 0, $em, 80, 80, 'officer');

        $add($rows, 'intelligence_officer', 'Officier renseignement', 'Intelligence Officer (S2)', 'État-major', 'Renseignement', 'Collecte, analyse et diffusion du renseignement.', 'function', 0, $em, 90, 90, 'officer');
        $add($rows, 'staff_intel_analyst', 'Analyste renseignement', 'Intelligence Analyst', 'État-major', 'Renseignement', 'Production d’analyses et de fiches situation.', 'function', 0, $em, 100, 100, 'officer');
        $add($rows, 'staff_intel_exploitation', 'Officier exploitation', 'SIGINT Specialist', 'État-major', 'Renseignement', 'Exploitation technique des sources et des flux.', 'specialty', 0, $em, 110, 110, 'member');
        $add($rows, 'staff_intel_cell', 'Cellule renseignement', 'Intelligence Cell Operator', 'État-major', 'Renseignement', 'Traitement et diffusion au sein de la cellule.', 'function', 0, $em, 120, 120, 'member');

        $add($rows, 'logistics_officer', 'Officier logistique', 'Logistics Officer (S4)', 'État-major', 'Logistique', 'Pilotage du soutien et de la chaîne logistique.', 'function', 0, $em, 130, 130, 'officer');
        $add($rows, 'staff_sustainment_lead', 'Responsable soutien', 'Supply Specialist', 'État-major', 'Logistique', 'Gestion des stocks et du soutien quotidien.', 'function', 0, $em, 140, 140, 'logistics');
        $add($rows, 'staff_logistics_flow_manager', 'Gestionnaire flux logistiques', 'Motor Transport Operator', 'État-major', 'Logistique', 'Organisation des flux, convois et dotations.', 'function', 0, $em, 150, 150, 'logistics');

        $inf = 40;
        $add($rows, 'infantry_section_chief', 'Chef de section', 'Platoon Leader', 'Infanterie', 'Commandement', 'Encadrement d’une section au combat.', 'authority', 0, $inf, 10, 10, 'officer');
        $add($rows, 'infantry_group_chief', 'Chef de groupe', 'Squad Leader', 'Infanterie', 'Commandement', 'Encadrement d’un groupe tactique.', 'function', 0, $inf, 20, 20, 'officer');
        $add($rows, 'infantry_team_chief', 'Chef d’équipe', 'Team Leader', 'Infanterie', 'Commandement', 'Encadrement d’une équipe élémentaire.', 'function', 0, $inf, 30, 30, 'officer');

        $add($rows, 'infantry_rifleman', 'Fusilier', 'Rifleman', 'Infanterie', 'Combattant', 'Combattant d’infanterie polyvalent.', 'function', 0, $inf, 40, 40, 'member');
        $add($rows, 'infantry_grenadier', 'Grenadier', 'Grenadier', 'Infanterie', 'Combattant', 'Appui grenades et armement lourd léger.', 'function', 0, $inf, 50, 50, 'member');
        $add($rows, 'infantry_sharpshooter', 'Tireur d’élite', 'Sharpshooter', 'Infanterie', 'Combattant', 'Précision renforcée et tir d’appui.', 'specialty', 0, $inf, 60, 60, 'member');
        $add($rows, 'infantry_marksman', 'Tireur de précision', 'Designated Marksman', 'Infanterie', 'Combattant', 'Neutralisation sélective à moyenne portée.', 'specialty', 0, $inf, 70, 70, 'member');
        $add($rows, 'infantry_sniper', 'Tireur isolé', 'Sniper', 'Infanterie', 'Combattant', 'Tir de précision longue portée en retrait.', 'specialty', 0, $inf, 75, 75, 'member');
        $add($rows, 'infantry_machine_gunner', 'Mitrailleur', 'Automatic Rifleman', 'Infanterie', 'Combattant', 'Appui feu soutenu et manœuvre d’appui.', 'function', 0, $inf, 80, 80, 'member');

        $add($rows, 'infantry_radio_operator', 'Opérateur radio', 'Radio Operator', 'Infanterie', 'Spécialités', 'Transmissions et liaisons tactiques.', 'specialty', 0, $inf, 90, 90, 'rto');
        $add($rows, 'infantry_scout', 'Éclaireur', 'Scout', 'Infanterie', 'Spécialités', 'Reconnaissance et renseignement terrain.', 'specialty', 0, $inf, 100, 100, 'member');
        $add($rows, 'infantry_team_pair_chief', 'Chef binôme', 'Buddy team leader', 'Infanterie', 'Spécialités', 'Coordination d’un binôme au contact.', 'function', 0, $inf, 110, 110, 'member');

        $fires = 50;
        $add($rows, 'fires_jtac', 'JTAC', 'JTAC', 'Appuis & feux', 'Coordination', 'Contrôleur d’attaques au sol.', 'specialty', 0, $fires, 10, 10, 'officer');
        $add($rows, 'fires_forward_observer', 'Forward Observer', 'Forward Observer', 'Appuis & feux', 'Coordination', 'Observation et ajustement des tirs.', 'specialty', 0, $fires, 20, 20, 'member');
        $add($rows, 'fires_support_officer', 'Officier appuis feux', 'Fire Support Officer', 'Appuis & feux', 'Coordination', 'Synthèse et coordination des appuis.', 'function', 0, $fires, 30, 30, 'officer');

        $add($rows, 'fires_gun_chief', 'Chef pièce', 'Fire Direction Specialist', 'Appuis & feux', 'Artillerie', 'Chef de pièce et conduite du tir.', 'function', 0, $fires, 40, 40, 'member');
        $add($rows, 'fires_gun_crew', 'Servant artillerie', 'Artillery Crew', 'Appuis & feux', 'Artillerie', 'Mise en œuvre et service de pièce.', 'function', 0, $fires, 50, 50, 'member');

        $eng = 60;
        $add($rows, 'engineer_sapper', 'Sapeur', 'Combat Engineer', 'Génie', 'Combat', 'Ouverture de passages et travaux au contact.', 'function', 0, $eng, 10, 10, 'member');
        $add($rows, 'engineer_eod', 'Démineur', 'EOD Specialist', 'Génie', 'Combat', 'Neutralisation des dangers explosifs.', 'specialty', 0, $eng, 20, 20, 'member');
        $add($rows, 'engineer_group_chief', 'Chef groupe génie', 'Engineer Squad Leader', 'Génie', 'Combat', 'Encadrement d’un groupe de combat du génie.', 'function', 0, $eng, 30, 30, 'officer');

        $add($rows, 'engineer_infra_technician', 'Technicien infrastructure', 'Construction Engineer', 'Génie', 'Infrastructure', 'Travaux d’infrastructure et ouvrages.', 'function', 0, $eng, 40, 40, 'member');
        $add($rows, 'engineer_works_lead', 'Responsable travaux', 'Works Supervisor', 'Génie', 'Infrastructure', 'Pilotage des chantiers et contrôle qualité.', 'function', 0, $eng, 50, 50, 'officer');

        $log = 70;
        $add($rows, 'logistics_driver', 'Conducteur militaire', 'Motor Transport Operator', 'Logistique', 'Transport', 'Conduite et manœuvre des véhicules logistiques.', 'function', 0, $log, 10, 10, 'member');
        $add($rows, 'logistics_convoy_chief', 'Chef convoi', 'Convoy Commander', 'Logistique', 'Transport', 'Responsabilité d’un convoi ou d’un détachement roulant.', 'function', 0, $log, 20, 20, 'officer');

        $add($rows, 'logistics_mechanic', 'Mécanicien', 'Mechanic', 'Logistique', 'Maintenance', 'Maintenance de premier et second échelon.', 'function', 0, $log, 30, 30, 'logistics');
        $add($rows, 'logistics_maint_technician', 'Technicien maintenance', 'Maintenance Technician', 'Logistique', 'Maintenance', 'Diagnostic et réparation des systèmes.', 'specialty', 0, $log, 40, 40, 'logistics');
        $add($rows, 'logistics_fleet_manager', 'Responsable parc matériel', 'Fleet Manager', 'Logistique', 'Maintenance', 'Gestion du parc et disponibilité opérationnelle.', 'function', 0, $log, 50, 50, 'logistics');

        $med = 80;
        $add($rows, 'medical_officer', 'Médecin militaire', 'Medical Officer', 'Santé', 'Médical', 'Responsabilité médicale et décisions sanitaires.', 'function', 0, $med, 10, 10, 'medic');
        $add($rows, 'medical_nurse', 'Infirmier militaire', 'Field Nurse', 'Santé', 'Médical', 'Soins infirmiers et stabilisation.', 'function', 0, $med, 20, 20, 'medic');
        $add($rows, 'medical_auxiliary', 'Auxiliaire sanitaire', 'Medical Assistant', 'Santé', 'Médical', 'Soutien sanitaire et assistance au poste de secours.', 'function', 0, $med, 30, 30, 'medic');
        $add($rows, 'medical_first_responder', 'Secouriste', 'Combat Medic', 'Santé', 'Médical', 'Premiers secours et évacuation sanitaire initiale.', 'specialty', 0, $med, 40, 40, 'medic');

        $ins = 90;
        $add($rows, 'instructor', 'Instructeur', 'Drill Instructor', 'Instruction', 'Formation', 'Instruction collective et maintien des standards.', 'function', 0, $ins, 10, 10, 'instructor');
        $add($rows, 'instruction_trainer', 'Formateur', 'Training Instructor', 'Instruction', 'Formation', 'Conception et animation de modules pédagogiques.', 'function', 0, $ins, 20, 20, 'instructor');
        $add($rows, 'training_officer', 'Responsable formation', 'Training Lead', 'Instruction', 'Formation', 'Pilotage des parcours et des qualifications.', 'function', 0, $ins, 30, 30, 'instructor');
        $add($rows, 'instruction_evaluator', 'Évaluateur', 'Evaluator', 'Instruction', 'Formation', 'Évaluation des compétences et des qualifications.', 'function', 0, $ins, 40, 40, 'instructor');

        $adm = 100;
        $add($rows, 'hr', 'Gestionnaire RH', 'Human Resources Specialist', 'Administration', 'Gestion', 'Gestion des effectifs et du dossier personnel.', 'function', 0, $adm, 10, 10, 'hr');
        $add($rows, 'admin_staff_officer', 'Officier administratif', 'Administrative Officer', 'Administration', 'Gestion', 'Courrier, dossiers et formalités administratives.', 'function', 0, $adm, 20, 20, 'officer');
        $add($rows, 'admin_unit_secretary', 'Secrétaire unité', 'Unit Secretary', 'Administration', 'Gestion', 'Secrétariat et suivi administratif de l’unité.', 'function', 0, $adm, 30, 30, 'member');

        $st = 110;
        $add($rows, 'veteran', 'Vétéran', 'Veteran', 'Statut', 'Affichage', 'Ancien combattant ou membre d’honneur actif en visibilité.', 'status', 1, $st, 10, 10, 'member');
        $add($rows, 'status_in_training', 'En formation', 'In training', 'Statut', 'Affichage', 'Parcours de formation en cours.', 'status', 1, $st, 20, 20, 'member');
        $add($rows, 'probation_member', 'En probation', 'Probationary Member', 'Statut', 'Affichage', 'Intégration sous période probatoire.', 'status', 1, $st, 30, 30, 'probation');
        $add($rows, 'suspended_status', 'Suspendu', 'Suspended', 'Statut', 'Affichage', 'Participation suspendue — visibilité limitée.', 'status', 1, $st, 40, 40, 'probation');
        $add($rows, 'status_reservist', 'Réserviste', 'Reservist', 'Statut', 'Affichage', 'Statut de réserve et disponibilité partielle.', 'status', 1, $st, 50, 50, 'member');
        $add($rows, 'certified_instructor', 'Instructeur certifié', 'Instructor Certified', 'Statut', 'Affichage', 'Qualification pédagogique reconnue.', 'status', 1, $st, 60, 60, 'instructor');
        $add($rows, 'status_active_duty', 'En service actif', 'Active Duty', 'Statut', 'Affichage', 'Engagement opérationnel à plein temps.', 'status', 1, $st, 70, 70, 'member');

        return $rows;
    }

    /** @return array<string, true> */
    public static function catalogSlugSet(): array
    {
        $out = [];
        foreach (self::entries() as $e) {
            $out[$e['slug']] = true;
        }

        return $out;
    }
}
