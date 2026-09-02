# Vitrine de tenues — responsables d’organisation sans droit plateforme

## Contexte

La rangée « Nos tenues » du tableau de bord se compose depuis `/back-office/dashboard-tenues`. Les gestionnaires d’organisation (rôles `community_owner`, `tenant_admin`) administrent la communauté, jamais le site.

## Symptôme

Un responsable d’organisation ne voyait pas « Choisir les tenues », ou était refusé à l’ouverture de la page. Le contrôle ne reconnaissait que `dashboard.pins.manage`, absent des rôles de gouvernance qui n’ont que `admin.organization`.

## Cause

`PermissionImplication` dérive beaucoup de droits `admin.*` depuis `admin.organization`, mais pas `dashboard.pins.manage` (module dashboard). Les écrans de vitrine et le menu ne vérifiaient que ce slug. Aucun droit plateforme (`admin.system`) n’était exigé, mais le trou laissait les organisateurs dehors.

## Correctif

- `admin.organization` implique `dashboard.pins.manage`.
- Accès unifié `DashboardPinsAccess` : `dashboard.pins.manage` **ou** `admin.organization` **ou** `admin.access`. Jamais `admin.system` ni `site.support`.
- Menu et rôles gouvernance (`community_owner`, `tenant_admin`) alignés.
- Les administrateurs du site gardent l’accès via le laissez-passer universel existant, sans en faire un prérequis.

## Fichiers touchés

- `app/Authorization/PermissionImplication.php`
- `app/Authorization/DashboardPinsAccess.php`
- `app/Controllers/Admin/Organization/DashboardWardrobePinsAdminController.php`
- `app/Controllers/Admin/Organization/DashboardPinsAdminController.php`
- `config/navigation.php`
- `app/Services/Community/TenantDefaultRoleDefinitions.php`
- `tests/Unit/DashboardPinsAccessTest.php`
- `tests/Unit/SystemReservedPermissionsTest.php`

## Vérification

- PHPUnit : `DashboardPinsAccessTest`, `SystemReservedPermissionsTest` (implication org ≠ plateforme).
- Contrôle statique : la page tenues n’exige pas `admin.system`.

## Statut

corrigé
