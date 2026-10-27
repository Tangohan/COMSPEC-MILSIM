# Gestionnaire — retrait des droits du niveau Gestionnaire

## Contexte

Sur l’écran Accès du bureau Effectifs, un responsable peut corriger les cases du niveau Gestionnaire. Un garde-fou existait déjà, mais trop étroit.

## Symptôme

Un gestionnaire décoche (volontairement ou via « Tout cocher » puis décochage) les droits du niveau Gestionnaire. Son compte perd l’accès à l’administration de la communauté. Une réinjection manuelle des droits est nécessaire.

## Cause

À l’enregistrement, seul le droit « gérer les rôles » était forcé de rester. Sans le droit d’administration de l’organisation, le responsable ne pouvait plus ouvrir le bureau Effectifs pour se rétablir. L’interface laissait aussi décocher ces cases, et « Tout cocher / décocher » les écrasait.

## Correctif

- Le niveau Gestionnaire conserve toujours l’administration de l’organisation et la gestion des niveaux d’accès, côté serveur, pour toutes les voies d’enregistrement.
- Ces cases sont affichées comme toujours requises (non décochables) ; « Tout cocher » ne les décoche plus.
- Un responsable qui n’aurait plus que la gestion des niveaux d’accès peut encore ouvrir le bureau Effectifs pour rétablir le reste.

## Fichiers touchés

- `app/Services/Rbac/CommunityAccessProfiles.php`
- `app/Services/Admin/RolePermissionService.php`
- `app/Support/EffectifsLmsAccess.php`
- `app/Middleware/OrganizationAdminMiddleware.php`
- `app/Controllers/Admin/EffectifsWorkspaceController.php`
- `views/admin/effectifs_workspace/roles.php`
- `public/assets/js/perm-check-all.js`
- `tests/Unit/ManagerProtectedPermissionsTest.php`

## Vérification

- Ouvrir Accès → Gestionnaire : les cases d’administration de l’organisation et de gestion des niveaux d’accès sont cochées, marquées « toujours requis », et non décochables.
- Enregistrer après avoir décoché d’autres droits : les deux clés restent présentes.
- « Tout cocher » puis décocher ne retire pas les clés.

## Statut

corrigé
