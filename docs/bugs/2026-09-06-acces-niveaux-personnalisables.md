# Niveaux d’accès — droits non modifiables

## Contexte

Les trois niveaux (Membre, Ressources humaines, Gestionnaire) simplifiaient l’ancien catalogue, mais leurs droits étaient figés. Un responsable ne pouvait pas corriger ce que RH a le droit de faire, ni créer un niveau plus étroit (par exemple Recrutement).

## Symptôme

Attribution possible, personnalisation impossible. L’écran Accès du bureau effectifs ne montrait que trois cartes descriptives.

## Cause

Les packs de droits étaient réécrits à chaque initialisation, les rôles marqués comme non modifiables, et la création d’un niveau supplémentaire refusée.

## Correctif

Écran Accès du type Discord : liste à gauche, cases groupées à droite. On peut corriger un modèle, en créer un autre à partir d’un existant, rétablir le modèle de départ, ou supprimer un niveau créé. Un membre a toujours un seul niveau. Les trois modèles ne peuvent pas être supprimés. Les mises à jour suivantes ne réécrivent plus les cases déjà enregistrées.

## Fichiers touchés

- `views/admin/effectifs_workspace/roles.php`
- `views/admin/effectifs_workspace/member.php`
- `app/Controllers/Admin/EffectifsWorkspaceController.php`
- `app/Services/Rbac/CommunityAccessProfiles.php`
- `app/Services/Rbac/CommunityAccessCollapseService.php`
- `app/Services/Community/TenantSeedHelper.php`
- `app/Repositories/RoleRepository.php`
- `app/Services/Admin/RolePermissionService.php`

## Vérification

- Bureau effectifs → Accès : ouvrir Ressources humaines, décocher un droit, enregistrer.
- Créer un niveau Recrutement à partir de Ressources humaines.
- Sur une fiche membre, le nouveau niveau apparaît dans la liste.
- Recharger l’écran : les cases décochées restent décochées.

## Statut

corrigé
