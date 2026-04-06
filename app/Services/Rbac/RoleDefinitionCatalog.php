<?php

declare(strict_types=1);

namespace App\Services\Rbac;

use PDO;

/**
 * Catalogue global de définitions de rôles (FR / US) et relations type « toile » (graphe).
 * Les instances tenant (`roles`) peuvent référencer `definition_id` pour aligner libellés et graphe.
 */
final class RoleDefinitionCatalog
{
    /**
     * @return list<array{slug: string, name_fr: string, name_us: string, family: string, description: string, sort_order: int}>
     */
    public static function definitions(): array
    {
        return [
            // Commandement / Gestion
            ['slug' => 'unit_manager', 'name_fr' => 'Gestionnaire d’unité', 'name_us' => 'Unit Manager', 'family' => 'command', 'description' => 'Ancrage fondateur / gestion d’unité (équivalent « Fondateur » historique).', 'sort_order' => 10],
            ['slug' => 'unit_commander', 'name_fr' => 'Commandant d’unité', 'name_us' => 'Unit Commander', 'family' => 'command', 'description' => 'Commandement de l’unité.', 'sort_order' => 20],
            ['slug' => 'unit_responsible', 'name_fr' => 'Responsable d’unité', 'name_us' => 'Unit Lead', 'family' => 'command', 'description' => 'Responsabilité opérationnelle de l’unité.', 'sort_order' => 30],
            ['slug' => 'platoon_leader', 'name_fr' => 'Chef de peloton', 'name_us' => 'Platoon Leader', 'family' => 'command', 'description' => 'Encadrement d’un peloton.', 'sort_order' => 40],
            ['slug' => 'squad_leader', 'name_fr' => 'Chef de groupe', 'name_us' => 'Squad Leader', 'family' => 'command', 'description' => 'Encadrement d’une équipe / groupe.', 'sort_order' => 50],
            ['slug' => 'team_leader', 'name_fr' => 'Chef d’équipe', 'name_us' => 'Team Leader', 'family' => 'command', 'description' => 'Encadrement d’une équipe réduite.', 'sort_order' => 60],
            ['slug' => 'section_adjutant', 'name_fr' => 'Adjoint de section', 'name_us' => 'Section Adjutant', 'family' => 'command', 'description' => 'Soutien au commandement de section.', 'sort_order' => 70],
            ['slug' => 'operations_officer', 'name_fr' => 'Officier opérations', 'name_us' => 'Operations Officer (S3)', 'family' => 'command', 'description' => 'Planification et conduite des opérations.', 'sort_order' => 80],
            ['slug' => 'executive_officer', 'name_fr' => 'Officier adjoint', 'name_us' => 'Executive Officer (XO)', 'family' => 'command', 'description' => 'Adjoint au commandement.', 'sort_order' => 90],
            // RH / Recrutement
            ['slug' => 'recruiter', 'name_fr' => 'Recruteur', 'name_us' => 'Recruiting Officer', 'family' => 'hr', 'description' => 'Pipeline de recrutement.', 'sort_order' => 100],
            ['slug' => 'recruitment_lead', 'name_fr' => 'Responsable recrutement', 'name_us' => 'Recruiting Lead', 'family' => 'hr', 'description' => 'Pilotage du recrutement.', 'sort_order' => 110],
            ['slug' => 'applications_analyst', 'name_fr' => 'Analyste candidatures', 'name_us' => 'Applications Analyst', 'family' => 'hr', 'description' => 'Analyse des dossiers.', 'sort_order' => 120],
            ['slug' => 'selection_officer', 'name_fr' => 'Officier sélection', 'name_us' => 'Selection Officer', 'family' => 'hr', 'description' => 'Décision de sélection.', 'sort_order' => 130],
            ['slug' => 'integration_lead', 'name_fr' => 'Responsable intégration', 'name_us' => 'Integration Lead', 'family' => 'hr', 'description' => 'Onboarding des nouveaux membres.', 'sort_order' => 140],
            // Formation
            ['slug' => 'trainer', 'name_fr' => 'Formateur', 'name_us' => 'Trainer', 'family' => 'training', 'description' => 'Animation de formation.', 'sort_order' => 200],
            ['slug' => 'senior_instructor', 'name_fr' => 'Instructeur senior', 'name_us' => 'Senior Instructor', 'family' => 'training', 'description' => 'Expertise pédagogique avancée.', 'sort_order' => 210],
            ['slug' => 'training_officer', 'name_fr' => 'Responsable instruction', 'name_us' => 'Training Officer', 'family' => 'training', 'description' => 'Pilotage des programmes.', 'sort_order' => 220],
            ['slug' => 'evaluator', 'name_fr' => 'Évaluateur', 'name_us' => 'Evaluator', 'family' => 'training', 'description' => 'Évaluation des compétences.', 'sort_order' => 230],
            ['slug' => 'pedagogy_coordinator', 'name_fr' => 'Coordinateur pédagogique', 'name_us' => 'Pedagogy Coordinator', 'family' => 'training', 'description' => 'Coordination des parcours.', 'sort_order' => 240],
            ['slug' => 'certification_lead', 'name_fr' => 'Responsable certification', 'name_us' => 'Certification Lead', 'family' => 'training', 'description' => 'Gestion des certifications.', 'sort_order' => 250],
            // Administration système
            ['slug' => 'super_admin', 'name_fr' => 'Super Admin', 'name_us' => 'Super Admin', 'family' => 'system', 'description' => 'Plateforme (hors tenant).', 'sort_order' => 300],
            ['slug' => 'system_admin', 'name_fr' => 'Admin système', 'name_us' => 'System Admin', 'family' => 'system', 'description' => 'Administration plateforme.', 'sort_order' => 310],
            ['slug' => 'tech_admin', 'name_fr' => 'Admin technique', 'name_us' => 'Technical Admin', 'family' => 'system', 'description' => 'Infrastructure et intégration.', 'sort_order' => 320],
            ['slug' => 'security_admin', 'name_fr' => 'Admin sécurité', 'name_us' => 'Security Admin', 'family' => 'system', 'description' => 'Sécurité et accès.', 'sort_order' => 330],
            ['slug' => 'rbac_manager', 'name_fr' => 'Gestionnaire RBAC', 'name_us' => 'RBAC Manager', 'family' => 'system', 'description' => 'Gouvernance des rôles et permissions.', 'sort_order' => 340],
            // Support / Logistique
            ['slug' => 'logistics_lead', 'name_fr' => 'Responsable logistique', 'name_us' => 'Logistics Officer (S4)', 'family' => 'support', 'description' => 'Logistique générale.', 'sort_order' => 400],
            ['slug' => 'equipment_manager', 'name_fr' => 'Gestionnaire matériel', 'name_us' => 'Equipment Manager', 'family' => 'support', 'description' => 'Suivi du matériel.', 'sort_order' => 410],
            ['slug' => 'fleet_lead', 'name_fr' => 'Responsable parc', 'name_us' => 'Fleet Lead', 'family' => 'support', 'description' => 'Parc véhicules / équipements lourds.', 'sort_order' => 420],
            ['slug' => 'mission_coordinator', 'name_fr' => 'Coordinateur missions', 'name_us' => 'Mission Coordinator', 'family' => 'support', 'description' => 'Coordination des missions.', 'sort_order' => 430],
            // Communication
            ['slug' => 'comms_lead', 'name_fr' => 'Responsable communication', 'name_us' => 'Communications Lead', 'family' => 'comms', 'description' => 'Stratégie de communication.', 'sort_order' => 500],
            ['slug' => 'global_moderator', 'name_fr' => 'Modérateur global', 'name_us' => 'Global Moderator', 'family' => 'comms', 'description' => 'Modération transverse.', 'sort_order' => 510],
            ['slug' => 'unit_moderator', 'name_fr' => 'Modérateur unité', 'name_us' => 'Unit Moderator', 'family' => 'comms', 'description' => 'Modération au sein d’une unité.', 'sort_order' => 520],
            ['slug' => 'content_analyst', 'name_fr' => 'Analyste contenu', 'name_us' => 'Content Analyst', 'family' => 'comms', 'description' => 'Qualité et analyse du contenu.', 'sort_order' => 530],
            // Intelligence (interop)
            ['slug' => 'intel_officer', 'name_fr' => 'Officier renseignement', 'name_us' => 'Intelligence Officer (S2)', 'family' => 'support', 'description' => 'Renseignement et synthèse.', 'sort_order' => 440],
            ['slug' => 'first_sergeant', 'name_fr' => 'Sous-officier référent', 'name_us' => 'First Sergeant', 'family' => 'command', 'description' => 'Encadrement et discipline.', 'sort_order' => 95],
        ];
    }

    /**
     * Relations entre slugs de définition (graphe global).
     *
     * @return list<array{from: string, to: string, type: string}>
     */
    public static function definitionRelationEdges(): array
    {
        return [
            ['from' => 'squad_leader', 'to' => 'platoon_leader', 'type' => 'reports_to'],
            ['from' => 'platoon_leader', 'to' => 'unit_responsible', 'type' => 'reports_to'],
            ['from' => 'unit_responsible', 'to' => 'unit_commander', 'type' => 'reports_to'],
            ['from' => 'executive_officer', 'to' => 'unit_commander', 'type' => 'reports_to'],
            ['from' => 'operations_officer', 'to' => 'unit_commander', 'type' => 'reports_to'],
            ['from' => 'recruiter', 'to' => 'recruitment_lead', 'type' => 'reports_to'],
            ['from' => 'trainer', 'to' => 'training_officer', 'type' => 'reports_to'],
            ['from' => 'trainer', 'to' => 'squad_leader', 'type' => 'independent'],
            ['from' => 'recruiter', 'to' => 'unit_responsible', 'type' => 'cross_cutting'],
        ];
    }

    /** Idempotent : INSERT IGNORE définitions + relations inter-définitions. */
    public static function seed(PDO $pdo): void
    {
        $ins = $pdo->prepare(
            'INSERT IGNORE INTO role_definitions (slug, name_fr, name_us, family, description, sort_order, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        foreach (self::definitions() as $row) {
            $ins->execute([
                $row['slug'],
                $row['name_fr'],
                $row['name_us'],
                $row['family'],
                $row['description'],
                $row['sort_order'],
            ]);
        }

        $idBySlug = [];
        $st = $pdo->query('SELECT id, slug FROM role_definitions');
        while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            $idBySlug[(string) $r['slug']] = (int) $r['id'];
        }

        $insR = $pdo->prepare(
            'INSERT IGNORE INTO role_definition_relations (from_definition_id, to_definition_id, relation_type) VALUES (?, ?, ?)'
        );
        foreach (self::definitionRelationEdges() as $e) {
            $from = $idBySlug[$e['from']] ?? null;
            $to = $idBySlug[$e['to']] ?? null;
            if ($from && $to) {
                $insR->execute([$from, $to, $e['type']]);
            }
        }

        try {
            // Même collation des deux côtés (MariaDB 10.10+ : roles.slug peut être utf8mb4_uca1400_ai_ci,
            // role_definitions.slug utf8mb4_unicode_ci → 1267 sans COLLATE explicite).
            $pdo->exec(
                'UPDATE roles r
                 INNER JOIN role_definitions d
                   ON (d.slug COLLATE utf8mb4_unicode_ci) = (r.slug COLLATE utf8mb4_unicode_ci)
                  AND r.tenant_id IS NOT NULL
                 SET r.definition_id = d.id
                 WHERE r.definition_id IS NULL'
            );
        } catch (PDOException) {
        }

        $tids = $pdo->query('SELECT id FROM tenants')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tids as $tid) {
            self::seedTenantRoleRelations($pdo, (int) $tid);
        }
    }

    /**
     * Copie le graphe catalogue vers `role_relations` pour un tenant (si les deux rôles existent par slug).
     */
    public static function seedTenantRoleRelations(PDO $pdo, int $tenantId): void
    {
        if ($tenantId <= 0) {
            return;
        }
        $find = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND slug = ? LIMIT 1');
        $ins = $pdo->prepare(
            'INSERT IGNORE INTO role_relations (tenant_id, from_role_id, to_role_id, relation_type, created_at) VALUES (?, ?, ?, ?, NOW())'
        );
        foreach (self::definitionRelationEdges() as $e) {
            $find->execute([$tenantId, $e['from']]);
            $fromId = (int) ($find->fetchColumn() ?: 0);
            $find->execute([$tenantId, $e['to']]);
            $toId = (int) ($find->fetchColumn() ?: 0);
            if ($fromId > 0 && $toId > 0) {
                $ins->execute([$tenantId, $fromId, $toId, $e['type']]);
            }
        }
    }
}
