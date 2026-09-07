# Emplois : trois écrans pour un métier

## Contexte

Après le rangement du menu Effectifs / Organisation, les emplois du dossier restaient éclatés : liste du bureau, référentiel, attributions.

## Symptôme

Un responsable ouvrait Emplois dans le bureau, puis devait encore aller au « référentiel » pour créer ou corriger un libellé, et à « attributions » pour voir qui tient quel emploi.

## Cause

La page du bureau n’était qu’un aperçu. Le CRUD et les attributions vivaient sur d’anciennes adresses.

## Correctif

Un seul écran dans le bureau effectifs : catalogue à gauche (créer, corriger, titulaires) et onglet « Qui tient quel emploi ». Les anciennes adresses redirigent vers cet écran.

## Fichiers touchés

- `views/admin/effectifs_workspace/fonctions.php`
- `app/Controllers/Admin/EffectifsWorkspaceController.php`
- `app/Controllers/Admin/Organization/PersonnelJobRoleAdminController.php`
- `views/admin/organization/personnel_job_roles/assignments.php`
- `views/admin/organization/effectifs_hub.php`
- `app/Services/Portal/BackOfficeSearchService.php`

## Vérification

- Contrôle syntaxe PHP des contrôleurs
- Tests `EffectifsRolesUiAssetTest`, `EffectifsOrganisationNavAssetTest`, `RolePermissionMatrixUxTest`, `DevDispatchCatalogTest`

## Statut

Corrigé
