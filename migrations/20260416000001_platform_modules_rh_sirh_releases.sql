-- Modules plateforme RH / SIRH : enregistrement + version 1.0.0 publiée sur tous les canaux de déploiement.
-- Idempotent : réexécution sans doublon de version ; publications courantes ajoutées seulement si absentes par canal.
SET NAMES utf8mb4;

INSERT INTO `platform_modules` (`code`, `name`, `description`, `is_active`, `is_public`) VALUES
  ('RH', 'Ressources humaines', 'Dossiers personnels, charte, habilitations et parcours RH côté membre.', 1, 1),
  ('SIRH', 'SIRH', 'Système d’information des ressources humaines et des effectifs (vue consolidée).', 1, 1)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `description` = VALUES(`description`),
  `is_active` = VALUES(`is_active`),
  `is_public` = VALUES(`is_public`);

INSERT IGNORE INTO `platform_module_versions` (`module_id`, `version`, `status`, `created_by`)
SELECT `id`, '1.0.0', 'published', NULL FROM `platform_modules` WHERE `code` IN ('RH', 'SIRH');

INSERT INTO `platform_module_channel_releases` (`module_id`, `module_version_id`, `channel_id`, `is_current`, `deployed_by`)
SELECT pm.`id`, pmv.`id`, dc.`id`, 1, NULL
FROM `platform_modules` pm
INNER JOIN `platform_module_versions` pmv ON pmv.`module_id` = pm.`id` AND pmv.`version` = '1.0.0'
CROSS JOIN `deployment_channels` dc
WHERE pm.`code` IN ('RH', 'SIRH')
  AND NOT EXISTS (
    SELECT 1 FROM `platform_module_channel_releases` cur
    WHERE cur.`module_id` = pm.`id`
      AND cur.`channel_id` = dc.`id`
      AND cur.`is_current` = 1
  );
