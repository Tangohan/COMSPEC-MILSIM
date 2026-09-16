# Chaleur de présence invisible (CRS Simple)

## Contexte

Overwatch Beta, calque « Chaleur de présence » sur la carte du théâtre (Leaflet CRS Simple, coordonnées monde Arma).

## Symptôme

Cocher le calque n’affichait rien. Les opérateurs pensaient que personne n’était relevé.

## Cause

Les pastilles étaient dessinées avec un cercle dont le rayon est exprimé en mètres géographiques. Sur une carte théâtre, ce rayon ne correspond pas à la grille du jeu.

## Correctif

Les pastilles sont dessinées comme les anneaux de portée : un polygone dont les sommets sont calculés en mètres monde autour de chaque contact.

## Fichiers touchés

- `public/assets/js/atak-overwatch-beta.js`
- `public/assets/js/atak-overwatch-ops.js`

## Vérification

Le calque affiche un disque autour de chaque contact localisé. Sans contact, le calque reste vide et un texte d’aide l’explique.

## Statut

corrigé
