# Mât Relais invisible sur la carte Arma

## Contexte

20 septembre 2026. L’application Relais AT décrit le mât le plus proche, mais rien ne le montre sur la carte. En quittant la portée, aucun avis.

## Symptôme

L’opérateur doit ouvrir Relais AT pour savoir s’il est encore à portée. La carte Arma ne porte ni pastille ni cercle.

## Cause

La fiche Relais AT calculait déjà le mât le plus proche, sa portée et l’état « à portée / hors portée ». Rien n’en faisait un point de carte, ni un avis de sortie.

## Correctif

- Pastille du mât le plus proche sur la carte, avec un cercle de portée.
- Vert à portée, jaune hors portée, rouge si le mât est détruit.
- Un avis prévient en quittant la zone (pas plus d’une fois toutes les vingt secondes).

Pack : Overwatch **1.6.6** · Athena **1.0.161**. Relancer Arma complètement.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_updateNearestRelayMap.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/XEH_postInit.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updateRelay.sqf`

## Vérification

1. Quitter Arma. Recharger Overwatch 1.6.6.
2. S’approcher d’un mât Relais : pastille verte et cercle sur la carte.
3. S’éloigner au-delà de la portée : pastille jaune et avis « Vous quittez la portée du relais … ».

## Statut

corrigé (Overwatch 1.6.6)
