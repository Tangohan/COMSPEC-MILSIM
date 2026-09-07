# Pack Opérateur : ATAK et synthèse personnelle absents

## Contexte

Fiche du rôle **Opérateur** (`ROL-MBR`, socle Membre). La grille du haut montrait ATAK et Systèmes à « — », avec 31 droits forum / opérations, sans consultation ATAK ni back-office personnel.

## Symptôme

Un opérateur n’avait pas les droits utiles du quotidien liés au téléphone et à sa synthèse : la colonne ATAK restait vide, « Voir le back-office » n’apparaissait pas.

## Cause

Le profil matriciel du Membre laissait ATAK et Systèmes à « aucun ». Le niveau « Sa fiche » de ces deux modules n’accordait aucun droit. Le pack Membre, trop court, ne couvrait pas non plus le suivi de mission déjà présent sur la fiche.

## Correctif

- ATAK « Sa fiche » : consulter les terminaux liés au compte, sans administration ni renseignement interpersonnel.
- Systèmes « Sa fiche » : ouvrir la synthèse personnelle, sans paramètres ni audit.
- Pack Membre aligné sur la vie courante (forum, documents, formations, suivi de mission, médias, coopération en contribution).
- Finances inchangées (aucune).

## Fichiers touchés

- `app/Services/Rbac/CommunityAccessProfiles.php`
- `app/Services/Rbac/RolePermissionMatrixCatalog.php`
- `app/Services/Rbac/RolePermissionMatrixService.php`
- `app/Services/Community/TenantDefaultRoleDefinitions.php`
- `bootstrap/member_operator_daily_rights_migration.php`
- `migrations/20260906210000_member_operator_daily_rights.sql`

## Vérification

- Tests `CommunityAccessProfilesTest`, `RolePermissionMatrixUxTest`, `SsePortalPlanCoverageTest`
- Fiche Opérateur : ATAK = Sa fiche, Systèmes = Sa fiche, tuiles « Consulter les terminaux ATAK » et « Voir le back-office »
- Appliquer `run-migrations.php` pour les communautés déjà en place

## Statut

Corrigé.
