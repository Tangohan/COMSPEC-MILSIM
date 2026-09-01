# Tableau de bord — barre d’identité trop bas

## Contexte

Tableau de bord membre (`/public/dashboard`), chrome sous la navbar Athena (Dashboard / Hub / Forum).

## Symptôme

La barre d’identité (communauté, grade, matricule, rôle, statut, date, raccourcis) apparaissait plus bas sur la page, après le visuel de briefing et les tuiles d’annonces. Elle n’était pas collée sous le menu principal.

## Cause

Le bloc `.dash-idstrip` était rendu dans le flux du tableau de bord, après le hero et les annonces, au lieu de faire partie du chrome sous la navbar.

## Correctif

La barre est incluse immédiatement après la navbar Dashboard / Hub / Forum. Elle reste collée sous ce menu au défilement. Les raccourcis (fiche, demande à l’encadrement, signaler une anomalie, etc.) sont inchangés.

## Fichiers touchés

- `views/partials/dashboard_command_center.php`
- `views/partials/dashboard_idstrip.php`
- `views/partials/header_dashboard.php`
- `public/assets/css/dashboard-impact.css`
- `tests/Unit/DashboardIdentityStripPlacementAssetTest.php`
- `tests/Unit/AtakCommunitySwitchAssetTest.php`

## Vérification

Test unitaire de placement : la barre est adjacente à la navbar dans le layout, avant le visuel de briefing.

## Statut

Corrigé.
