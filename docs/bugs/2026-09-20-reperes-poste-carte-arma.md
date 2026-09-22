# Repère du poste absent de la carte Arma

## Contexte

20 septembre 2026. Un point posé depuis Overwatch Beta apparaît sur le téléphone ATAK, mais pas sur la carte d’Arma 3 ouverte avec M.

## Symptôme

Le poste voit le point. En jeu, seule la carte du téléphone le montre. Les autres opérateurs ne le voient pas sur la carte classique.

## Cause

Les points venus du poste étaient créés seulement pour le joueur local, au premier passage ils étaient ignorés, et le nom restait vide. Ils n’étaient donc pas des vrais repères d’équipe.

## Correctif

- Le point du poste est posé comme un vrai repère d’opérateur, visible par toute l’équipe.
- Il apparaît dès la première lecture, sans attendre un second passage.
- S’il n’a pas de nom, il s’affiche « Repère poste ».
- Un déplacement ou une suppression au poste se reflète sur la carte en jeu.

Pack : Overwatch **1.6.6** · Athena **1.0.161**. Relancer Arma complètement.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_pollAthenaMarkers.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/XEH_postInit.sqf`

## Vérification

1. Quitter Arma. Recharger Overwatch 1.6.6.
2. Depuis Overwatch Beta, poser un point sur la carte.
3. En jeu, ouvrir la carte Arma : le point est au même endroit, lisible par un autre opérateur.

## Statut

corrigé (Overwatch 1.6.6)
