# Marqueurs joueurs absents du poste ATAK

## Contexte

Sur la carte du poste, les IA et les vehicules restaient visibles alors que les
marqueurs des joueurs disparaissaient, y compris celui de l'operateur connecte.

## Cause

Deux rendus coexistent pendant le chargement de la couche C2. Tous les deux
supprimaient les humains des que leur liaison etait classee `offline`, et la
couche C2 pouvait aussi les supprimer avec le filtre local des positions en
retard. Les IA, elles, beneficiaient deja d'une conservation de leur derniere
position connue, ce qui donnait l'impression d'un probleme de droits reserve
aux joueurs.

## Correctif

- Une position BFT humaine valide reste visible sur la carte partagee.
- Les etats `STALE` et `LOST` modifient encore l'apparence du symbole, mais ne
  retirent plus le joueur.
- Le comportement est identique dans la couche C2 et dans son rendu de secours.
- Le filtrage des positions IA en retard reste disponible.

## Fichiers touches

- `public/assets/js/map/atak-c2-bridge.js`
- `public/assets/js/atak-map.js`
- `tests/Unit/AtakC2PlayerMarkerAppearanceAssetTest.php`

## Verification

Recharger le poste et verifier qu'un joueur ayant deja transmis une position
reste affiche avec son indicatif, meme lorsque sa liaison devient differee ou
perdue. Les IA et vehicules continuent de s'afficher normalement.

## Statut

corrige
