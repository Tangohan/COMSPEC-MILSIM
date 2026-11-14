-- Un séjour = une période d'appartenance à une communauté. Quitter puis revenir ouvre un NOUVEAU séjour :
-- les données d'un séjour précédent ne suivent pas (rôles, grade, ancienneté repartent de zéro).
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `user_community_membership_periods` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `tenant_id` int unsigned NOT NULL,
  `joined_at` datetime NOT NULL,
  `left_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ucmp_user_tenant` (`user_id`,`tenant_id`,`joined_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reprise de l'existant : un séjour par appartenance connue.
INSERT INTO `user_community_membership_periods` (`user_id`, `tenant_id`, `joined_at`, `left_at`)
SELECT m.`user_id`, m.`tenant_id`, m.`joined_at`, m.`left_at`
FROM `user_community_memberships` m
WHERE NOT EXISTS (
  SELECT 1 FROM `user_community_membership_periods` p WHERE p.`user_id` = m.`user_id` AND p.`tenant_id` = m.`tenant_id`
);

-- Les pièces d'identité de séjour passent par la date : l'historique de service reçoit aussi sa communauté.
ALTER TABLE `personnel_service_history` ADD COLUMN `tenant_id` int unsigned DEFAULT NULL AFTER `user_id`;
ALTER TABLE `personnel_service_history` ADD KEY `personnel_service_history_tenant` (`tenant_id`,`user_id`);
