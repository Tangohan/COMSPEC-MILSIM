# Menu Effectifs / Organisation

## Contexte

Le back-office empilait plusieurs portes vers le même métier : Membres, Effectifs, Ordre de bataille, Structure, Accès dans Système, table des rôles, matrice, toile, profils.

## Symptôme

L’administrateur devait choisir entre des écrans qui menaient « presque » au même endroit. Le bureau effectifs mettait en avant Rôles et Droits d’accès alors que Droits renvoyait déjà vers Accès.

## Cause

Accumulation de coques (table, matrice, presets, doctrine des fonctions) sans retirer les anciennes entrées de menu après la simplification des niveaux d’accès.

## Correctif

Deux portes dans Personnel : **Effectifs** (tableur, accès, emplois, candidatures) et **Organisation** (organigramme, catalogue). Accès quitte Système. Les anciennes adresses redirigent vers Accès ou vers les emplois du dossier.

## Fichiers touchés

- `views/partials/ath_sidebar_nav.php`
- `views/admin/effectifs_workspace/partials/effectifs_lms_rail.php`
- `views/admin/effectifs_workspace/shell.php`
- `views/admin/organization/effectifs_hub.php`
- Contrôleurs table / matrice / presets / toile / gestion avancée
- `app/Services/Portal/BackOfficeSearchService.php`

## Vérification

- Tests `EffectifsOrganisationNavAssetTest`, `EffectifsRolesUiAssetTest`, `RolePermissionMatrixUxTest`
- Contrôle syntaxe PHP des contrôleurs redirigés

## Statut

Corrigé
