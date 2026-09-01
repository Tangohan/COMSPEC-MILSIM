# 2026-09-01 — Dossier RH du tableau de bord encore en deux colonnes

## Contexte

Tableau de bord membre (`/public/dashboard`). Un parcours en deux étapes (Absence, Élévation, Avancement) existe déjà en bas de page.

## Symptôme

Au milieu de l’écran, les formulaires d’élévation et d’avancement restent affichés côte à côte, sous les offres. Le membre voit deux démarches en même temps, au lieu de choisir d’abord.

## Cause

Le tableau de bord incluait encore l’ancien bloc « Mon dossier RH » (deux colonnes) en plus du parcours du bas. Les offres étaient aussi dupliquées, puis masquées dans le parcours.

## Correctif

L’ancien bloc n’est plus affiché. Offres et dossier RH n’apparaissent qu’en bas de page : d’abord le choix, puis un seul formulaire.

## Fichiers touchés

- `views/partials/dashboard_command_center.php`
- `views/partials/dashboard_rh_parcours.php`
- `views/partials/dashboard_aside.php`
- `app/Controllers/Web/RhWorkspaceController.php`
- `tests/Unit/DashboardMemberHubAssetTest.php`
- `tests/Unit/DashboardRhParcoursAssetTest.php`

## Vérification

Les tests d’assets vérifient que le centre de commandement n’inclut plus `dashboard_member_rh.php` et n’empêche plus les offres du parcours. Contrôle visuel : en bas de page, trois cartes ; un seul formulaire après le choix.

## Statut

Corrigé
