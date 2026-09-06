# Accès communauté réduits à trois profils

## Contexte

Le back-office membres mélangeait rôles, fonctions, emplois et habilitations. L’administrateur devait choisir entre une table des rôles, une matrice, des profils, une toile et des emplois qui pouvaient aussi porter des droits.

## Symptôme

Impossible de savoir si « rôle », « fonction » ou « emploi » ouvrait un droit, une étiquette de dossier, ou les deux.

## Cause

Plusieurs catalogues (rôles d’accès, catalogue militaire, emplois métier, presets) créaient et attribuaient des habilitations en parallèle.

## Correctif

Trois niveaux d’accès seulement : **Membre**, **Ressources humaines**, **Gestionnaire**. Les habilitations techniques restent en base (le portail s’en sert) mais ne sont plus choisies à la main. Les emplois du dossier restent des libellés (radio, médic…), sans droits associés. Les anciennes pages (matrice, toile, table, presets) redirigent vers le bureau effectifs.

Les copies restantes (adjoint, métiers du catalogue, opérateurs ATAK, etc.) sont **supprimées** par communauté. Une nouvelle communauté ne crée plus que les trois profils. Les positions « En formation » / « En service actif » restent une mention de service, distincte de l’accès.

## Fichiers touchés

- `app/Services/Rbac/CommunityAccessProfiles.php`
- `app/Services/Rbac/CommunityAccessCollapseService.php`
- `app/Services/Community/TenantSeedHelper.php`
- `app/Services/Community/TenantBootstrapService.php`
- `app/Controllers/Admin/EffectifsWorkspaceController.php`
- `views/admin/effectifs_workspace/roles.php`
- `views/admin/effectifs_workspace/member.php`
- `bootstrap/community_access_profiles_v1_migration.php`
- `bootstrap/community_access_roles_purge_v1_migration.php`

## Vérification

- Tests unitaires `CommunityAccessProfilesTest`, `SimplifiedCommunityRolesTest`, `EffectifsRolesUiAssetTest`, `CommunityAccessCollapseAssetTest`
- Conversion au prochain `run-migrations.php` (purge v1, même si la conversion v1 a déjà tourné)

## Statut

Corrigé (modèle en place ; suppression des copies à la migration).

## Suite — administration du site

L’administration du site n’est plus un rôle à attribuer. Elle est portée sur le compte (`users.is_platform_admin`) et se gère dans une liste fermée, pas sur chaque dossier. La reprise des anciennes affectations « Gestionnaire de la plateforme » se fait à la migration. L’assistance et la modération restent des rôles site distincts.
