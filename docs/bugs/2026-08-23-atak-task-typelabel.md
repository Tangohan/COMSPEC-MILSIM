# TASK ATAK — `_typeLabel` indéfini

## Contexte

Ouverture de l’app TASK (ordres C2) sur l’ATAK Enhanced. Bannière d’erreur SQF
en haut de l’écran.

## Symptôme

```
Error Variable indéfinie dans une expression: _typelabel
File …\fn_athena_updateTask.sqf, line 72
```

La liste des ordres peut quand même s’afficher (ex. `any · NewPI · Annulé`).

## Cause

1. `orderTypeLabel` utilisait `exitWith` alors qu’il est appelé depuis un
   `forEach` : en SQF l’exitWith peut remonter au caller et sauter
   l’affectation `private _typeLabel = … call …`.
2. Un `switch` juste après d’autres `private` dans la même itération peut
   faire disparaître ces variables du scope (d’où `_prioMark` OK, `_typeLabel`
   plus défini).

## Correctif

- Plus d’`exitWith` dans le helper de libellé.
- TASK : plus de `switch` pour l’état ; chaînes `if` et noms de variables
  distincts du helper.

Nettoyage des tampons « SSE » encore visibles sur la carte de la tablette
(marqueurs locaux déjà posés).

## Fichiers touchés

- `connect/functions/fn_orderTypeLabel.sqf`
- `atak_athena/functions/fn_athena_updateTask.sqf`
- `atak_athena/functions/fn_athena_taskSelect.sqf`
- `connect/XEH_postInit.sqf`
- `intel/functions/fn_createMapMarkers.sqf`

## Vérification

Relancer Arma. Ouvrir TASK : plus de bannière d’erreur. Les tampons orange
« SSE » disparaissent de la carte tablette.

## Statut

Corrigé dans les sources. Le PBO chargé en jeu était antérieur (bannière encore
visible avec l’ancienne ligne `_typeLabel`). Rebuild **atak_athena 1.0.36** +
**connect 1.4.41** à redéployer, puis relancer Arma.
