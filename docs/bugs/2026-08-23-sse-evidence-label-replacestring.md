# SSE — étiquette de preuve : `BIS_fnc_replaceString` et `_sec` indéfinis

## Contexte

Génération / mise sous scellé d’une preuve via Zeus SSE Control. `fn_attachIntelLayers` et `fn_bagEvidence` appellent `comspec_sse_fnc_makeEvidenceLabel`.

## Symptôme

Overlay Arma :

```
Error Variable indéfinie dans une expression: bis_fnc_replacestring
Error Variable indéfinie dans une expression: _sec
File z\comspec_sse\addons\intel\functions\fn_makeEvidenceLabel.sqf, line 28
```

Extrait : `_room = toUpper ([_room, " ", "-"] call BIS_fnc_replaceString);`

## Cause

1. `BIS_fnc_replaceString` n’est pas toujours compilé au moment de l’appel (Functions BIS absentes ou pas encore prêtes). `call` sur une fonction nil → variable indéfinie.
2. En cascade, `fn_bagEvidence` faisait `_sec isEqualType createHashMap` alors que `getSection` peut renvoyer `nil` (pas de section) → `_sec` indéfini.

## Correctif

- Remplacer les espaces par des tirets avec `splitString` / `joinString` (SQF natif, sans Functions BIS).
- Neutraliser `nil` / types inattendus pour mission, pièce, type, UID.
- Dans `fn_bagEvidence`, initialiser `_sec` à un hashmap vide si la section manque.

## Fichiers touchés

- `mod/@COMSPEC_SSE/addons/intel/functions/fn_makeEvidenceLabel.sqf`
- `mod/@COMSPEC_SSE/addons/intel/functions/fn_bagEvidence.sqf`
- Rebuild : `mod/@COMSPEC_SSE/addons/comspec_sse_intel.pbo`

## Vérification

1. Rebuild `intel`, copier vers `!Workshop\@COMSPEC_SSE\addons`.
2. Redémarrer Arma, générer / examiner une entité puis mettre une preuve sous scellé.
3. Plus d’overlay `BIS_fnc_replaceString` / `_sec`.

## Statut

Corrigé (source) — déploiement PBO à confirmer in-game.
