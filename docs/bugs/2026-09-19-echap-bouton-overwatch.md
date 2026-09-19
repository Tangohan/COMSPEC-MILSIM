# Menu Échap — bouton Overwatch inutile

## Contexte

Le menu Échap affichait un bouton « COMSPEC Overwatch » qui ouvrait un panneau de gestion peu utilisé.

## Symptôme

Un bouton Overwatch en haut du menu Échap, sans intérêt opérationnel.

## Cause

Le bouton était injecté à chaque ouverture du menu pause, à côté de « Dépannage liaison ».

## Correctif

Le bouton Overwatch n’est plus créé. « Dépannage liaison » reste en haut à gauche. Signaler un problème se fait depuis le menu d’interaction ACE.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_onInterruptLoad.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_ndaTexts.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp` (1.5.93)

## Vérification

Quitter Arma complètement. Recharger Overwatch 1.5.93. Échap : plus de bouton Overwatch. Dépannage liaison toujours présent.

## Statut

Corrigé
