# Membre / Opérateur : back-office et données ATAK absents de la fiche

## Contexte

Fiche du rôle communautaire **Opérateur** (socle `member`, famille Accès / Socle). Le badge indiquait 31 habilitations, toutes du forum, des documents et des vues opérationnelles.

## Symptôme

La liste des habilitations actives n’incluait pas **Voir le back-office** ni **Consulter les terminaux ATAK**, alors qu’un membre authentifié peut déjà ouvrir `/back-office` pour sa synthèse personnelle (« Mes données ATAK », « Mes données RP »).

## Cause

Le pack du niveau Membre (`CommunityAccessProfiles::memberPermissionSlugs`) s’arrêtait au forum, aux documents, à la formation en lecture et à quelques vues opérationnelles. `admin.backoffice.view` n’était accordé qu’à partir des Ressources humaines. `atak.terminals.view` n’était accordé à aucun des trois niveaux hors Gestionnaire.

## Correctif

Le socle Membre inclut désormais :

- **Voir le back-office** — consultation personnelle, sans administration ;
- **Consulter les terminaux ATAK** — données ATAK liées au compte.

Le rail du tableau de bord propose **Mes données** aux membres qui ne sont pas gestionnaires. Le tableur des liaisons, les utilisateurs et les paramètres restent fermés.

Les communautés déjà en place reçoivent ces deux habilitations à la prochaine mise à jour (conversion des trois niveaux, puis ajout ciblé).

## Fichiers touchés

- `app/Services/Rbac/CommunityAccessProfiles.php`
- `app/Services/Community/TenantDefaultRoleDefinitions.php`
- `views/partials/dashboard_aside.php`
- `bootstrap/member_backoffice_atak_view_migration.php`
- `migrations/20260906190000_member_backoffice_atak_view.sql`
- `run-migrations.php`

## Vérification

- Tests `CommunityAccessProfilesTest`, `SimplifiedCommunityRolesTest`, `OperatorBackOfficeAccessAssetTest`
- Fiche du niveau Membre : « Voir le back-office » et « Consulter les terminaux ATAK » doivent apparaître
- Compte Membre : Back-office → synthèse personnelle ; pas d’accès utilisateurs / paramètres

## Statut

Corrigé (appliquez `run-migrations.php` pour les communautés déjà en place).
