-- Progression d'accueil durable, indépendante de la durée de la session PHP.
ALTER TABLE `user_profiles`
  ADD COLUMN `onboarding_persona` varchar(32) DEFAULT NULL AFTER `emergency_contact`,
  ADD COLUMN `onboarding_steps_json` text DEFAULT NULL AFTER `onboarding_persona`,
  ADD COLUMN `onboarding_completed_at` datetime DEFAULT NULL AFTER `onboarding_steps_json`;
