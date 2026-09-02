# Intégration des membres — photo de compte inexistante

## Contexte

Page back-office Athena `/public/back-office/integration-membres` (tenant 7, utilisateur 5, corrélation `7d1285671ce18342`). Appel : `MemberIntegrationAdminController::index` → `MemberIntegrationRepository::listDashboard()`.

## Symptôme

La page d’intégration des nouveaux membres ne s’ouvre pas. Erreur : colonne inconnue `u.avatar_path` dans le SELECT.

## Cause

La requête lisait `users.avatar_path`. Cette colonne n’existe pas. La photo de compte est `users.avatar_url` (schéma d’origine). Le portrait opérateur, s’il existe, est `personnel_profiles.character_portrait_path`. Aucune migration n’a jamais créé `avatar_path`.

## Correctif

La liste et la fiche d’un parcours lisent `avatar_url` et le portrait opérateur lorsqu’ils sont présents. Si une communauté historique n’a pas encore ces colonnes, la requête les remplace par une valeur vide : la page s’affiche, sans inventer de photo. Une mise à jour de schéma ajoute les colonnes vides si elles manquent, sans remplir de fausse image.

## Fichiers touchés

- `app/Repositories/MemberIntegrationRepository.php`
- `bootstrap/users_member_photo_columns_migration.php`
- `migrations/20260902140000_users_member_photo_columns.sql`
- `run-migrations.php`
- `app/Support/DevDispatchCatalog.php`
- `tests/Unit/MemberIntegrationRepositorySchemaTest.php`
- `tests/Unit/MemberIntegrationAssetTest.php`
- `tests/Unit/DevDispatchCatalogTest.php`

## Vérification

`php vendor/bin/phpunit tests/Unit/MemberIntegrationRepositorySchemaTest.php tests/Unit/MemberIntegrationAssetTest.php tests/Unit/DevDispatchCatalogTest.php`. Contrôle manuel : ouvrir Intégration des nouveaux membres ; la liste des parcours s’affiche.

## Statut

corrigé
