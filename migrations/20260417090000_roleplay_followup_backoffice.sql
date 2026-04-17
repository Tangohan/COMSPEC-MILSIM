SET NAMES utf8mb4;

ALTER TABLE `personnel_profiles`
  ADD COLUMN IF NOT EXISTS `rp_followup_stage` varchar(60) DEFAULT NULL AFTER `deployable`,
  ADD COLUMN IF NOT EXISTS `rp_followup_status` varchar(60) DEFAULT NULL AFTER `rp_followup_stage`,
  ADD COLUMN IF NOT EXISTS `rp_followup_progress` tinyint unsigned DEFAULT NULL AFTER `rp_followup_status`,
  ADD COLUMN IF NOT EXISTS `rp_tutor_user_id` int unsigned DEFAULT NULL AFTER `rp_followup_progress`,
  ADD COLUMN IF NOT EXISTS `rp_recruitment_stream` varchar(120) DEFAULT NULL AFTER `rp_tutor_user_id`,
  ADD COLUMN IF NOT EXISTS `rp_next_interview_date` date DEFAULT NULL AFTER `rp_recruitment_stream`,
  ADD COLUMN IF NOT EXISTS `rp_medical_due_date` date DEFAULT NULL AFTER `rp_next_interview_date`,
  ADD COLUMN IF NOT EXISTS `rp_service_rotation_date` date DEFAULT NULL AFTER `rp_medical_due_date`,
  ADD COLUMN IF NOT EXISTS `rp_followup_notes` text DEFAULT NULL AFTER `rp_service_rotation_date`,
  ADD COLUMN IF NOT EXISTS `rp_eligibility_snapshot_json` longtext DEFAULT NULL AFTER `rp_followup_notes`;
