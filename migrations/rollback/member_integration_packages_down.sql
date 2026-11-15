-- Retour arrière de l'étape member_integration_packages : retire les paquets d'intégration et l'historique de leur
-- application. Les grades, rôles, qualifications et formations déjà attribués aux membres ne sont pas touchés.
DROP TABLE IF EXISTS member_integration_applications;
DROP TABLE IF EXISTS member_integration_package_items;
DROP TABLE IF EXISTS member_integration_packages;
