<?php

declare(strict_types=1);

/**
 * Active queue for schema changes added after the 2026-10-29 baseline.
 *
 * Every SQL change goes through this queue and is applied by run-migrations.php (admin console,
 * single-use ticket, or `php8.4 run-migrations.php` over SSH). See docs/technical/migrations-active-queue.md.
 *
 * Historical entries are retained in registry-archive.php for reference only.
 * Never register the archive or scan migrations/*.sql from this queue. Each entry is an explicit,
 * self-contained and idempotent step (it checks the live schema before any ALTER / CREATE INDEX),
 * so every entry is marked 'rerun'. Successful steps are skipped on normal runs; --step=id is an
 * explicit retry of an active entry. Keep the order: a step may rely on the tables of earlier ones.
 */
return [
    ['id' => 'academy_tables', 'label' => 'Académie : tables des cours et des apprenants', 'group' => 'schema', 'file' => '3130_academy_tables.php', 'rerun' => true],
    ['id' => 'personnel_job_role_structure', 'label' => 'Emplois : hiérarchie descriptive (parent, unité, grade, spécialité)', 'group' => 'schema', 'file' => '3140_personnel_job_role_structure.php', 'rerun' => true],
    ['id' => 'personnel_job_role_recovery_log', 'label' => 'Emplois : journal des reprises d’emplois', 'group' => 'schema', 'file' => '3150_personnel_job_role_recovery_log.php', 'rerun' => true],
    ['id' => 'arsenal_wardrobes_visibility', 'label' => 'Équipement : visibilité d’une tenue (communauté ou propriétaire seul)', 'group' => 'schema', 'file' => '3160_arsenal_wardrobes_visibility.php', 'rerun' => true],
    ['id' => 'user_public_cv', 'label' => 'Page publique du joueur', 'group' => 'schema', 'file' => '3170_user_public_cv.php', 'rerun' => true],
    ['id' => 'user_public_cv_banner', 'label' => 'Page publique du joueur : bannière personnelle', 'group' => 'schema', 'file' => '3180_user_public_cv_banner.php', 'rerun' => true],
    ['id' => 'member_grade_changes', 'label' => 'Historique des changements de grade des membres', 'group' => 'schema', 'file' => '3190_member_grade_changes.php', 'rerun' => true],
    ['id' => 'unit_codes', 'label' => 'Codes unité : tables et numéro administratif des communautés', 'group' => 'schema', 'file' => '3200_unit_codes.php', 'rerun' => true],
    ['id' => 'steam_conflict_reports', 'label' => 'Steam en double : signalements des membres', 'group' => 'schema', 'file' => '3210_steam_conflict_reports.php', 'rerun' => true],
    ['id' => 'users_steam_id_index', 'label' => 'Index sur le Steam des comptes (détection des doublons)', 'group' => 'schema', 'file' => '3220_users_steam_id_index.php', 'rerun' => true],
    ['id' => 'atak_api_hot_indexes', 'label' => 'Index des lectures fréquentes de la liaison (positions, repères)', 'group' => 'schema', 'file' => '3230_atak_api_hot_indexes.php', 'rerun' => true],
    ['id' => 'user_totp_used_steps', 'label' => 'Double vérification : anti-rejeu des codes de l’application', 'group' => 'schema', 'file' => '3240_user_totp_used_steps.php', 'rerun' => true],
    ['id' => 'units_military_symbol', 'label' => 'ORBAT : symbole militaire MIL-STD-2525 et échelon des unités (units.sidc, units.echelon)', 'group' => 'schema', 'file' => '3250_units_military_symbol.php', 'rerun' => true],
    ['id' => 'member_profile_invites', 'label' => 'Liens pour compléter son profil (invitations des effectifs)', 'group' => 'schema', 'file' => '3260_member_profile_invites.php', 'rerun' => true],
    ['id' => 'academy_course_lifecycle_sharing', 'label' => 'Academy Studio : archivage, corbeille et partage des formations', 'group' => 'schema', 'file' => '3270_academy_course_lifecycle_sharing.php', 'rerun' => true],
    ['id' => 'academy_jtac_v2_refresh', 'label' => 'Académie : dernière version du cours JTAC (2.0.1, marquage fumigène) pour les inscrits qui ne l’ont pas encore commencé', 'group' => 'schema', 'file' => '3280_academy_jtac_v2_refresh.php', 'rerun' => true],
    ['id' => 'equipment_definitions_game_class', 'label' => 'Dotation : lien entre un article du référentiel et un objet vu en jeu', 'group' => 'schema', 'file' => '3335_equipment_definitions_game_class.php', 'rerun' => true],
    ['id' => 'competency_links_pathways', 'label' => 'Compétences : explication, liens vers l’Academy, les qualifications, les emplois et la dotation, parcours enregistrés', 'group' => 'schema', 'file' => '3340_competency_links_pathways.php', 'rerun' => true],
    ['id' => 'community_game_labels', 'label' => 'Annuaire : harmonisation des jeux déclarés par les communautés (Arma3, ARMA 3… → Arma 3)', 'group' => 'schema', 'file' => '3290_community_game_labels.php', 'rerun' => true],
    ['id' => 'org_low_member_reminders', 'label' => 'Communautés à un ou deux membres : rappels aux responsables et liste « Organisations à supprimer »', 'group' => 'schema', 'file' => '3310_org_low_member_reminders.php', 'rerun' => true],
    ['id' => 'tenant_generated_labels', 'label' => 'Libellés des unités et postes générés : personnalisation par communauté et par langue', 'group' => 'schema', 'file' => '3295_tenant_generated_labels.php', 'rerun' => true],
    ['id' => 'unit_assignment_repair', 'label' => 'Affectations : réparation des membres affectés sur leur fiche mais absents des effectifs (double envoi du formulaire)', 'group' => 'schema', 'file' => '3345_unit_assignment_repair.php', 'rerun' => true],
    ['id' => 'personnel_job_role_enrichment', 'label' => 'Fonctions : abrégé, icône, étiquettes de la communauté et fonctions du site masquées', 'group' => 'schema', 'file' => '3360_personnel_job_role_enrichment.php', 'rerun' => true],
    ['id' => 'advancement_proposal_decisions', 'label' => 'Avancement : suivi des propositions automatiques des campagnes (retenues, écartées avec motif)', 'group' => 'schema', 'file' => '3385_advancement_proposal_decisions.php', 'rerun' => true],
    ['id' => 'qualification_award_rules', 'label' => 'Qualifications : modes d’obtention (à la main, Academy, automatique sur heures de jeu, de vol, missions…), prérequis et visibilité', 'group' => 'schema', 'file' => '3430_qualification_award_rules.php', 'rerun' => true],
    ['id' => 'qualification_academy_reprise', 'label' => 'Qualifications : reprise des attestations Academy dans le référentiel (une qualification par formation, attestations rattachées)', 'group' => 'schema', 'file' => '3435_qualification_academy_reprise.php', 'rerun' => true],
    ['id' => 'personnel_job_role_prerequisites', 'label' => 'Emplois : prérequis (doctrines, heures de jeu et de vol, qualifications, compétences) et dérogations motivées', 'group' => 'schema', 'file' => '3440_personnel_job_role_prerequisites.php', 'rerun' => true],
    ['id' => 'qualification_issuers_enrichment', 'label' => 'Qualifications : émetteurs (description, unité, signataire du brevet, logo, archivage, qualifications rattachées, habilités)', 'group' => 'schema', 'file' => '3470_qualification_issuers_enrichment.php', 'rerun' => true],
];
