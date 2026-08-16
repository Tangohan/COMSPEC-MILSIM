# Documentation bug — Include script_component.hpp introuvable (SSE)

## Contexte
Chargement du mod `@COMSPEC_SSE` dans Arma 3.

## Symptôme
Dialogue d’erreur au lancement :

```text
Include file
z\comspec_sse\addons\core\functions\script_component.hpp
not found.
```

## Cause
`fn_initSettings.sqf` (dans `addons/core/functions/`) faisait `#include "script_component.hpp"`.
Avec le chemin relatif au fichier SQF, Arma cherchait le header **dans** `functions/`, alors qu’il se trouve un niveau au-dessus (`addons/core/script_component.hpp`). Le fichier n’utilisait d’ailleurs aucune macro de ce header.

## Correctif
Suppression de l’`#include` inutilisé dans `fn_initSettings.sqf`, puis rebuild des PBO.

## Fichiers touchés
- `mod/@COMSPEC_SSE/addons/core/functions/fn_initSettings.sqf`
- `mod/@COMSPEC_SSE/addons/comspec_sse_core.pbo` (rebuild)

## Vérification
Relancer Arma avec `@COMSPEC_SSE` : l’erreur d’include ne doit plus apparaître.

## Statut
corrigé
