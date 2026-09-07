# Bandeau manquant sur les modules recrutement et formations

## Contexte

Le bureau recrutement et l’espace formations s’ouvrent dans une coque dédiée, distincte du tableau de bord Athena.

## Symptôme

Rien n’indique clairement que l’on a quitté le portail pour un module Athena. Le retour vers le tableau de bord n’était pas visible en haut d’écran (seulement un lien en bas de menu, côté formations).

## Cause

La coque recrutement et la coque formations n’avaient pas le bandeau d’orientation déjà présent sur le studio.

## Correctif

Bandeau commun « Vous visionnez un module ATHENA » avec un bouton Retour vers le tableau de bord, en haut de l’écran.

## Fichiers touchés

- `views/partials/lms_athena_module_banner.php`
- `public/assets/css/training_lms.css`
- `views/layout/recruitment_lms.php`
- `views/layout/training_lms_staff_shell.php`
- `views/training/catalogue.php`
- `views/training/sessions.php`

## Vérification

- `php -l` sur le partial
- Tests d’assets : le bandeau et le bouton Retour sont présents dans les quatre coques
- Parcours attendu : ouvrir le bureau recrutement, puis le catalogue des formations ; le bandeau reste en haut

## Statut

Corrigé
