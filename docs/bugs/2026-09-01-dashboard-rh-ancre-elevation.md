# Tableau de bord — ancre Élévation en double

## Contexte

Fusion de `main` (bloc « Mon dossier RH » au milieu de page) dans `split/iceman-tablette-overwatch` (parcours Absence / Élévation / Avancement en bas de page).

## Symptôme

Le lien ou le retour vers `#elevation` ouvrait le formulaire du milieu de page, pas l’étape Élévation du parcours en bas.

## Cause

Les deux blocs utilisaient le même identifiant `elevation`.

## Correctif

Le bloc du milieu s’appelle désormais `dash-hub-elevation`. `#elevation` reste l’étape du parcours en bas de page. Les offres de recrutement ne sont plus répétées en bas, elles restent dans le bloc déjà affiché plus haut.

## Fichiers touchés

- `views/partials/dashboard_member_rh.php`
- `views/partials/dashboard_command_center.php`
- `public/assets/js/atak-geo-live.js`

## Vérification

Recherche : un seul `id="elevation"` dans les vues tableau de bord, sur le parcours du bas.

## Statut

Corrigé
