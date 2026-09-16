# Libellé d’anneau « 250 m » coupé

## Contexte

Anneaux de portée autour d’un contact sur Overwatch Beta.

## Symptôme

Le libellé « 250 m » passait à la ligne : le « m » se retrouvait sous le chiffre, parfois illisible.

## Cause

L’étiquette n’avait pas de taille d’icône figée. Sans cela, le moteur de carte force une petite case et le texte se plie.

## Correctif

Taille et ancrage explicites de l’étiquette, et consigne de ne pas couper le texte (« 250 m » reste sur une ligne).

## Fichiers touchés

- `public/assets/js/atak-overwatch-beta.js`
- `public/assets/js/atak-overwatch-tools.js`
- `public/assets/css/atak-overwatch-beta.css`

## Vérification

Les anneaux 100 / 250 / 500 / 1 000 m affichent le libellé entier à côté du cercle.

## Statut

corrigé
