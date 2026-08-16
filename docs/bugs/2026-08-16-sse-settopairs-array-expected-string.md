# SSE — Inc_setToPairs type ARRAY, expected STRING

## Contexte
Génération / attach intel sur une entité SSE (après génération, dump `_data` avec `sections` / `intelLayers`).

## Symptôme
```
Error type ARRAY, expected STRING, on index 1, in [...]
[815.Inc_setToPairs]
```
Le dump montre bien des `sections` (evidenceState, intelLayers, optical…), donc l’échec survient à l’écriture de la paire `sections`.

## Cause
`BIS_fnc_setToPairs` / `Inc_setToPairs` attend :
`[_array, _key STRING, _value]`

Le code passait :
`[_array, [_key, _value]]`

→ l’index 1 est un **ARRAY**, pas une STRING.

Affectait `setPair` (mauvais appel BIS) et les appels directs dans `generateData`, `setSection`, `setState`, etc.

## Correctif
- `fn_setPair.sqf` : écriture manuelle des paires (plus d’appel BIS ambigu)
- Remplacement de tous les `[_data, ["k", v]] call BIS_fnc_setToPairs` par `[_data, "k", v] call comspec_sse_fnc_setPair`
- Rebuild PBO `core`, `generator`, `eden`

## Fichiers touchés
- `addons/core/functions/fn_setPair.sqf`
- `addons/core/functions/fn_setSection.sqf`, `fn_setState.sqf`, `fn_setSeed.sqf`, `fn_revealFog.sqf`, `fn_getSection.sqf`
- `addons/generator/functions/fn_generateData.sqf`, `fn_ensureGenerated.sqf`, `fn_applyModel.sqf`
- `addons/eden/functions/fn_edenApplyAttributes.sqf`

## Vérification
Générer une unité SSE (Eden / lazy) : plus d’erreur `Inc_setToPairs` ; `sections` et `intelLayers` persistés.

## Statut
corrigé
