# Dossier personnel — vide sous le choix de vue

## Contexte

L’écran de choix Vue publique / Vue RH (accès RH) laissait un grand espace avant le pied de page.

## Symptôme

Sous les deux cartes, une bande vide ; le pied de page paraissait décroché ou trop haut, avec un trou en bas d’écran.

## Cause

La page était encore forcée à la hauteur de l’écran (zone principale et fond de page), malgré un contenu court. Un padding haut prévu pour un bandeau fixe restait aussi sur le choix de vue, alors que le bandeau est déjà dans le flux.

## Correctif

Le choix de vue n’étire plus la page. Le pied de page se colle aux cartes. Le padding haut inutile est retiré sur cet écran.

## Fichiers touchés

- `views/personnel/file.php`
- `views/layout/main.php`
- `public/assets/css/personnel-file.css`
- `public/assets/css/portal-footer.css`
- `app/Controllers/Web/PersonnelController.php`

## Vérification

Ouvrir un dossier avec accès RH, sans choisir la vue : les deux cartes, puis le pied de page, sans bande vide.

## Statut

corrigé
