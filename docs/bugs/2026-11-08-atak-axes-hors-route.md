# Axes qui ne suivent pas la route

## Contexte
Capture de la 4.0.0 : ligne décalée, angle sec.

## Symptôme
Trait à côté de la route en vue rapprochée, saut vers une route voisine.

## Cause
Simplification à 12 m. `roadsConnectedTo [route, true]` ajoutait les routes simplement proches à tous les nœuds.

## Correctif
Simplification à 4 m. Routes proches prises seulement pour un bout de route sans suite. Limite : l'axe reste le plus long chemin du réseau, il peut tourner à un carrefour si la suite est plus longue.

## Fichiers touchés
`fn_zoneGen.sqf`.

## Vérification
`tests/Unit/AtakZonesRoutesTraceTest.php`, `hemtt check`. Rien ne peut être testé en jeu ici.

## Statut
corrigé à vérifier en jeu
