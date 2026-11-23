-- Lecture des tentatives récentes par résultat et par période dans la surveillance du site.
ALTER TABLE `login_attempts`
  ADD INDEX `idx_login_attempt_success_created` (`success`, `created_at`);
