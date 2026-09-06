# Administration du site : plus un rôle

## Contexte

Après la réduction des accès communauté à trois profils, l’administration du site restait un rôle global à attribuer.

## Symptôme

On ne savait pas si « administrer le site » était un rôle, une habilitation, ou les deux. L’attribution passait par la page des rôles site.

## Cause

Un rôle global ouvrait tout le site. Le portail le traitait comme du RBAC, au même titre que l’assistance ou la modération.

## Correctif

Un bit sur le compte, géré depuis une liste fermée (`Administrateurs du site`). Ajouter ou retirer quelqu’un demande de recopier une phrase. Les dossiers personne n’ont plus de case. L’ancienne affectation est reprise à la migration. Le site garde au moins une personne habilitée.

## Fichiers touchés

- `app/Services/Rbac/PlatformAdminFlag.php`
- `bootstrap/users_platform_admin_flag_migration.php`
- `app/Services/Rbac/RbacService.php`
- `app/Core/Gate.php`
- `app/Repositories/UserRepository.php`
- `views/admin/system/platform_admins.php`
- `views/admin/system/user_person.php`
- `views/admin/system/user_edit.php`

## Vérification

- Tests `PlatformAdminFlagTest`, `PlatformAdminUiAssetTest`
- Migration au prochain `run-migrations.php`

## Statut

Corrigé.
