-- Historique des changements de grade d’un membre (fiche membre Effectifs : frise des grades,
-- ancienneté dans le grade pour l’éligibilité à une élévation).
-- Une ligne par changement réel : grade avant / après, date, origine (modification manuelle ou
-- élévation validée) et auteur. Les libellés sont copiés au moment du changement pour rester lisibles
-- si le grade est renommé ou retiré du référentiel.
-- Appliqué par l'étape member_grade_changes de la file active (run-migrations.php) ;
-- MemberGradeChangeRepository::ensureTable() reste un filet de sécurité (CREATE TABLE IF NOT EXISTS).
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `member_grade_changes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL COMMENT 'Communauté dans laquelle le grade a changé',
  `user_id` int unsigned NOT NULL,
  `from_grade_id` int unsigned DEFAULT NULL COMMENT 'NULL : aucun grade avant le changement',
  `to_grade_id` int unsigned DEFAULT NULL COMMENT 'NULL : grade retiré',
  `from_label` varchar(150) DEFAULT NULL,
  `to_label` varchar(150) DEFAULT NULL,
  `source` varchar(20) NOT NULL DEFAULT 'manual' COMMENT 'manual | elevation',
  `actor_user_id` int unsigned DEFAULT NULL,
  `changed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `member_grade_changes_member` (`tenant_id`, `user_id`, `changed_at`),
  CONSTRAINT `member_grade_changes_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
