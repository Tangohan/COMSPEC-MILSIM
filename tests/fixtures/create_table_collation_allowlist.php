<?php

declare(strict_types=1);

/**
 * Historique figé au 16/11/2026 : CREATE TABLE sans COLLATE utf8mb4_unicode_ci déjà livrés (tables créées à la collation
 * par défaut du serveur). Ne pas allonger : une nouvelle table précise COLLATE=utf8mb4_unicode_ci
 * (tests/Unit/MigrationCreateTableCollationTest.php, docs/dev/COLLATIONS-MYSQL.md). Retirer une ligne quand le fichier est corrigé.
 *
 * @return array<string, list<string>> fichier => tables
 */
return [
    'bootstrap/atak_chat_channels_migration.php' => ['atak_chat_channels'],
    'bootstrap/atak_cop_terrain_migration.php' => ['atak_terrain_grids', 'atak_terrain_chunks', 'atak_unit_intel_events'],
    'bootstrap/atak_sse_identity_suite_migration.php' => ['sse_id_settings'],
    'bootstrap/atak_unit_motion_migration.php' => ['atak_unit_motion_samples', 'atak_unit_motion', 'atak_unit_assignments'],
    'bootstrap/atak_viewshed_overlays_migration.php' => ['atak_viewshed_overlays'],
    'bootstrap/community_platform_migration.php' => ['subscription_plans', 'tenant_usage_counters', 'pending_community_creates'],
    'bootstrap/content_tags_migration.php' => ['content_tags', 'content_tag_links'],
    'bootstrap/discord_recruitment_migration.php' => ['recruitment_discord_questions'],
    'bootstrap/elevation_requests_proposal_migration.php' => ['elevation_requests'],
    'bootstrap/member_departures_migration.php' => ['member_departures'],
    'bootstrap/personnel_role_consolidation_migration.php' => ['personnel_job_role_legacy_imports'],
    'bootstrap/platform_unit_commander_migration.php' => ['community_invitations', 'moderation_cases', 'moderation_actions', 'moderation_evidence', 'security_events', 'blocked_indicators', 'community_events', 'community_event_rsvps', 'platform_usage_events', 'referral_codes', 'referral_attributions'],
    'bootstrap/rbac_three_layer_migration.php' => ['site_role_assignments'],
    'bootstrap/tactical_tracks_migration.php' => ['tactical_tracks', 'tactical_observations', 'tactical_tracks', 'tactical_observations'],
    'bootstrap/training_formation_custom_page_feedback_migration.php' => ['training_formation_custom_page_feedback'],
    'bootstrap/transactional_email_migration.php' => ['email_tokens', 'user_login_devices', 'email_deliveries'],
    'bootstrap/user_roles_migration.php' => ['user_roles'],
    'database/steps/0810_profils_de_candidature_colonnes_enrolement.php' => ['recruitment_presets'],
    'database/steps/0840_table_atak_intel_logs_ping_chat.php' => ['atak_intel'],
    'database/steps/0850_tables_atak_live_parite_node_full.php' => ['atak_layers', 'atak_markers', 'atak_units', 'atak_chat_messages', 'atak_pings', 'atak_nine_line', 'atak_intel_photos', 'atak_designator_targets', 'atak_sigint_reports', 'atak_last_activity', 'atak_air_assets'],
    'database/steps/0870_comspec_c2_cas_tables_recon_images_atak_map_shap.php' => ['recon_images', 'atak_map_shapes', 'atak_laser_codes'],
    'database/steps/0920_table_categories_roles_utilisateurs_organisation.php' => ['categories'],
    'database/steps/0970_documents_athena_colonnes_et_tables.php' => ['equipment_classes', 'document_links'],
    'database/steps/0980_refonte_module_documentaire_colonnes_et.php' => ['document_collaborators', 'document_permissions', 'document_relations', 'document_audit_log'],
    'database/steps/3720_atak_nine_line_recipients.php' => ['atak_nine_line_recipients', 'atak_brief_callsigns'],
    'migrations/20260417103000_personnel_roleplay_timeline.sql' => ['personnel_roleplay_timeline_events'],
    'migrations/20260418140000_documents_security_tracking.sql' => ['document_access_sessions', 'document_access_events'],
    'migrations/20260825000001_atak_scene_objects.sql' => ['atak_scene_objects'],
    'migrations/20260902120000_doctrine_referential.sql' => ['document_reference_domains', 'document_reference_subdomains', 'document_reference_sequences', 'document_diffusion_levels', 'document_doctrines', 'document_audiences', 'document_acknowledgments', 'document_views', 'document_doctrine_reminders'],
    'migrations/20260904173000_athena_data_ingest_v2.sql' => ['athena_sources', 'athena_events', 'athena_ingest_receipts', 'athena_live_state', 'athena_map_objects', 'athena_sync_sessions', 'athena_sync_items', 'athena_ingest_metrics', 'athena_terrain_chunks', 'athena_scene_snapshots'],
    'migrations/20260913140000_document_publication_system.sql' => ['document_types', 'document_reminder_rules'],
    'migrations/c2_pillars.sql' => ['fire_units', 'fire_tables', 'danger_zones', 'asset_logistics_status', 'asset_logistics_status_history', 'intel_reports', 'intel_reports_events', 'logs_positions', 'iff_challenges', 'iff_asset_status'],
    'migrations/community_messaging.sql' => ['tenant_message_threads', 'tenant_message_thread_users', 'tenant_messages'],
    'migrations/courrier_module.sql' => ['document_presets', 'document_templates', 'courrier_documents', 'courrier_document_versions', 'document_template_versions', 'document_variables_catalog', 'document_workflows'],
    'migrations/courrier_refonte.sql' => ['courrier_snippets'],
    'migrations/courrier_signatures.sql' => ['user_signatures'],
    'migrations/document_module_refactor.sql' => ['document_collaborators', 'document_permissions', 'document_relations', 'document_audit_log'],
    'migrations/grade_referentiel.sql' => ['grade_categories', 'grade_systems', 'grades_referentiel'],
    'migrations/jnet_exchanges.sql' => ['jnet_exchanges', 'jnet_exchange_targets', 'jnet_exchange_reads'],
    'migrations/lms_training.sql' => ['training_courses', 'training_modules', 'training_lessons', 'training_resources', 'training_enrollments', 'training_progress', 'training_quizzes', 'training_quiz_questions', 'training_quiz_answers', 'training_quiz_attempts', 'training_quiz_responses', 'training_certificates', 'training_audit_log'],
    'migrations/moderation_content.sql' => ['moderation_artifacts', 'moderation_decisions'],
    'migrations/personnel_dossier.sql' => ['personnel_profiles', 'personnel_qualifications', 'personnel_assignments', 'personnel_service_history', 'personnel_media'],
    'migrations/recruitment_enlistment_account.sql' => ['recruitment_presets'],
    'migrations/schema.sql' => ['tenants', 'clearance_levels', 'roles', 'permissions', 'role_permissions', 'site_role_assignments', 'grades', 'users', 'sessions', 'password_resets', 'login_attempts', 'audit_logs', 'units', 'user_units', 'user_profiles', 'user_profile_display_settings', 'personnel_extras', 'recruitment_presets', 'enlistments', 'document_categories', 'documents', 'document_versions', 'equipment_classes', 'document_links', 'training_modules', 'training_progress', 'training_certificates', 'tenant_matricule_config', 'tenant_atak_config', 'atak_maps', 'atak_layers', 'atak_markers', 'atak_units', 'atak_unit_motion_samples', 'atak_unit_motion', 'atak_unit_assignments', 'atak_terrain_grids', 'atak_terrain_chunks', 'atak_unit_intel_events', 'atak_chat_channels', 'atak_chat_messages', 'atak_viewshed_overlays', 'atak_medical_alert_triage', 'atak_pings', 'atak_nine_line', 'atak_nine_line_recipients', 'atak_brief_callsigns', 'atak_orders', 'atak_intel_photos', 'atak_designator_targets', 'atak_sigint_reports', 'atak_intel', 'atak_last_activity', 'atak_air_assets', 'recon_images', 'atak_map_shapes', 'atak_laser_codes', 'personnel_admin_panels', 'personnel_admin_data', 'content_reports', 'content_report_timeline', 'member_notifications', 'modpacks', 'modpack_images', 'site_settings', 'elevation_requests', 'member_departures', 'tactical_briefing_slides', 'recon_transmission_sessions', 'recon_pv_entries', 'recon_pv_attachments', 'recon_poe_documents'],
    'migrations/site_settings.sql' => ['site_settings'],
    'migrations/user_profile_display_settings.sql' => ['user_profile_display_settings'],
];
