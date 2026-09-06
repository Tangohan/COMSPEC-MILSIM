# ATHENA — modèle d’accès simplifié

## Décision retenue

ATHENA ne propose que **sept rôles d’accès classiques** :

1. Gestionnaire ;
2. Gestionnaire adjoint ;
3. Formateur ;
4. Recruteur ;
5. Responsable ressources humaines ;
6. Adjoint responsable ressources humaines ;
7. Membre.

Il n’existe plus de kit d’unité ni de tier militaire dans le contrôle d’accès. Gestionnaire adjoint possède exactement les droits de Gestionnaire. L’adjoint RH possède exactement les droits du responsable RH.

Les **fonctions militaires** et **emplois métiers** (`JTAC`, `PJ`, Operator, Crew Chief, Combat Controller, etc.) sont des données de profil sans droit : uniquement un libellé, un visuel et leur rattachement au référentiel réel de l’unité. Les contrôleurs, middlewares et services ne doivent jamais autoriser une action à partir de leur nom ou identifiant.

## Référentiel SOF inclus

Le référentiel métier contient explicitement **24th STS**, **B Squadron / 1st SFOD-D**, **160th SOAR / Night Stalkers**, ainsi que la famille **SOF** et sa chaîne USSOCOM/JSOC. Les emplois associés incluent notamment Combat Controller (CCT), Pararescue (PJ), TACP/JTAC, pilote 160th SOAR et opérateur B Squadron. Ce sont exclusivement des informations d’unité, de profil et d’affichage : `is_visual_only = 1`, `permission_baseline = none`, et le synchroniseur retire tout ancien lien `role_permissions` attaché à ces emplois.

## Permissions

Les permissions techniques restent atomiques et sont regroupées uniquement dans les sept rôles classiques par `FunctionBasedAccessCatalog`. Aucun rôle n’obtient `*`, `admin.system`, `admin.access`, `admin.organization`, `site.*`, `platform.*` ou `system.*`. Gestionnaire ne signifie donc jamais administrateur total de plateforme.

Les périmètres sont volontairement simples :

| Rôle | Périmètre fonctionnel |
|---|---|
| Membre | Consultation du contenu disponible dans son périmètre. |
| Gestionnaire / Gestionnaire adjoint | Gestion courante des membres, contenus et opérations du tenant. |
| Formateur | Consultation générale et gestion des formations. |
| Recruteur | Consultation générale, candidatures, invitations et intégration. |
| Responsable RH / Adjoint responsable RH | Consultation générale, dossiers du personnel, grades, affectations et statuts. |

## Audit de l’existant

`scripts/audit-function-access-v2.php` scanne les appels `allows`, `deny`, `can`, `hasPermission` et `requirePermission`, puis relève les slugs hors catalogue, doublons et patterns dangereux. Le dump statique de cette révision se trouve dans `docs/access-control/audit/functions-existing.json`.

Avec `DB_DSN`, le même script produit `roles-existing.json` et `roles-existing.csv` avec l’identifiant, le tenant, le nom, le slug, la date, l’origine code/dynamique, les permissions et le nombre d’utilisateurs. Cette exécution sur la base cible est obligatoire avant une migration de production.

## Migration

`run_function_based_access_v2_migration()` effectue dans cet ordre :

1. snapshot immuable des rôles, droits, rôles primaires, pivots utilisateur–rôle et anciennes attributions site ;
2. création des sept rôles manquants par tenant ;
3. choix d’un rôle d’accès classique par utilisateur ;
4. conservation des fonctions et emplois métiers comme rôles visuels sans permission ;
5. suppression des attributions plateforme globales et de tous les anciens grants tenant ;
6. reconstruction des grants des sept rôles depuis le catalogue fermé.

### Correspondance historique

| Ancien slug | Nouveau rôle |
|---|---|
| `community_owner` | Gestionnaire |
| `tenant_admin`, `deputy_commander` | Gestionnaire adjoint |
| `trainer`, `instructor`, `senior_instructor` | Formateur |
| `recruiter` | Recruteur |
| `hr` | Responsable ressources humaines |
| `deputy_hr` | Adjoint responsable ressources humaines |
| `member`, rôle personnalisé ou emploi militaire | Membre |

Le rôle historique reste enregistré dans `legacy_role_id`. `scripts/rollback-function-access-v2.php` restaure les permissions et toutes les affectations depuis les snapshots ; aucun snapshot n’est supprimé automatiquement.

## Journalisation

Toute action sensible doit appeler `SensitiveFunctionAuditLogger::record()` après autorisation et dans la transaction métier. Le journal exige l’acteur, la permission technique, le type et l’identifiant de cible, l’horodatage et accepte des métadonnées JSON sans contenu secret.
