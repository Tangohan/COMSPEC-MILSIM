# Fond aérien — script cassé au chargement

## Contexte
Overwatch Beta (et les autres cartes qui chargent le calque photo aérienne).

## Symptôme
La console affiche `Uncaught SyntaxError: missing ) after argument list` dans `atak-aerial.js`. Le calque photo aérienne ne se pose pas.

## Cause
Deux calculs de tuiles avaient une parenthèse manquante : le `Math.floor` n’était pas refermé.

## Correctif
Refermer le calcul comme pour le bord nord : `(1 - (south + epsilon) / W) * n`.

## Fichiers touchés
- `public/assets/js/atak-aerial.js`
- `views/atak-overwatch-beta.php`

## Vérification
Le fichier se charge sans erreur de syntaxe. Photo aérienne affiche le terrain.

## Statut
corrigé
