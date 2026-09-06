# Emplois de dossier depuis l’ORBAT

## Contexte

Le référentiel d’emplois était recopié depuis un catalogue militaire à chaque communauté. En base : des centaines d’emplois pour presque aucune attribution réelle.

## Symptôme

Les communautés voyaient une liste d’emplois qu’elles n’avaient pas choisis. Semer ce catalogue ne servait pas l’usage quotidien : l’organigramme et les affectations portent déjà les libellés utiles.

## Cause

Un sync automatique recopiait le catalogue vers les emplois de dossier, à la création de communauté et à chaque migration.

## Correctif

Plus aucun remplissage automatique depuis le catalogue. Créer une unité dans l’ORBAT prépare un emploi du même nom. Nommer un poste à l’affectation le crée s’il n’existe pas. Les emplois catalogue jamais attribués sont retirés, y compris ceux déjà « déverrouillés » ; ceux déjà collés à un dossier sont conservés. Un emploi ne porte plus de droits d’accès.

## Fichiers touchés

- `app/Services/Personnel/UnitJobRoleSyncService.php`
- `app/Services/Personnel/PersonnelJobRoleBootstrapService.php`
- `app/Services/Rbac/MilitaryRoleCatalogSyncService.php`
- `app/Repositories/UnitRepository.php`
- `app/Repositories/PersonnelAssignmentRepository.php`
- `bootstrap/unit_derived_job_roles_migration.php`
- `run-migrations.php`

## Vérification

- Tests `UnitJobRoleSyncServiceTest`, `EffectifsRolesUiAssetTest`
- Conversion au prochain passage des migrations

## Statut

Corrigé (modèle en place ; nettoyage des communautés existantes à la migration).
