# Tableau de bord — cartes RH sans effet

## Contexte

Sur `https://athena.ttrd.fr/public/dashboard`, le bloc **Mon dossier RH** propose trois cartes (Absence, Élévation, Avancement). C’est l’étape 1 du parcours.

## Symptôme

Un clic sur une carte ne changeait rien : pas de formulaire, le libellé restait « Étape 1 sur 2 ».

## Cause

Le passage à l’étape 2 dépendait d’Alpine. Le tableau de bord chargeait Alpine depuis un hôte externe, refusé par la politique de scripts du portail (`script-src` n’autorise que le site lui-même, plus deux CDN déjà prévus). Les cartes s’affichaient, les clics n’étaient pas traités.

## Correctif

- Le choix de démarche est géré par un script servi avec la page, sans bibliothèque externe.
- Alpine du tableau de bord est chargé depuis le fichier local du site, pour les autres blocs qui en ont encore besoin (catalogue, fenêtres).

## Fichiers touchés

- `views/partials/dashboard_rh_parcours.php`
- `views/partials/dashboard_command_center.php`
- `views/dashboard.php`
- `public/assets/css/dashboard-impact.css`
- `tests/Unit/DashboardRhParcoursAssetTest.php`

## Vérification

`php vendor/bin/phpunit tests/Unit/DashboardRhParcoursAssetTest.php`

Sur le tableau de bord : cliquer Absence, Élévation ou Avancement ouvre le formulaire ; Retour au choix ramène aux trois cartes.

## Statut

Corrigé
