# Barre d’outils supplémentaire : panneau vide

## Contexte

Sur Overwatch Beta, le chevron du rail d’outils ouvre les outils moins fréquents (relief, visée, anneaux, etc.).

## Symptôme

Un grand rectangle sombre s’affiche à droite du rail, avec des lignes vides, des ascenseurs, et le libellé (ex. « Profil d’élévation ») coupé au milieu.

## Cause

Le panneau extra était une liste large avec défilement. Les infobulles du rail (nom + aide) se dessinaient **à l’intérieur** de ce panneau. Elles le faisaient défiler et recouvraient icônes et libellés.

## Correctif

- Seconde colonne d’icônes, même largeur que le rail.
- Infobulle au survol à droite, hors de la colonne.
- Plus d’ascenseur ni de rectangle vide.
- Le panneau se referme en reprenant un outil du rail principal.

## Fichiers touchés

- `public/assets/css/atak-overwatch-beta.css`
- `public/assets/js/atak-overwatch-beta.js`
- `views/atak-overwatch-beta.php`

## Vérification

Chevron › : une colonne d’icônes s’aligne à droite. Survol : le nom apparaît à côté, lisible. Pas d’ascenseur. Sélection : le nom reste visible. Clic sur Sélection : la colonne se referme.

## Statut

corrigé
