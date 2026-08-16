# SSE — Params Eden (tableau) + `_data` indéfini dans attachIntelLayers

## Contexte
Chargement / init d’unités avec attributs Eden SSE activés.

## Symptôme
1. `fn_edenInitEntity.sqf` L4 — `Error Params: Type Tableau, Objet attendu`
2. `fn_attachIntelLayers.sqf` — `Variable indéfinie : _data` (vers L167–170)

## Cause
1. CBA `Extended_InitPost` passe déjà `_this = [_unit]`. Le config faisait `[_this] call …` → `[[_unit]]`, alors que `params` attendait un objet.
2. `attachIntelLayers` n’acceptait pas un argument imbriqué / données absentes ; en cas d’échec amont ou PBO obsolète, `_data` n’était pas garanti avant `BIS_fnc_setToPairs` / l’émission d’événement.

## Correctif
- Config Eden : `init = "_this call comspec_sse_fnc_edenInitEntity"`
- `edenInitEntity` / `edenApplyAttributes` : acceptent objet ou `[objet]`
- `attachIntelLayers` : unwrap, garde-fous sur `_data`, UID extrait avant l’événement
- Helpers `getPair` / `setPair` pour ne plus appeler `BIS_fnc_getFromPairs` sur un non-ARRAY
- Zeus inspector / viewData / makeEvidenceLabel sécurisés
- Rebuild PBO `eden` + `intel` + `core` + `zeus`

## Fichiers touchés
- `mod/@COMSPEC_SSE/addons/eden/config.cpp`
- `mod/@COMSPEC_SSE/addons/eden/functions/fn_edenInitEntity.sqf`
- `mod/@COMSPEC_SSE/addons/eden/functions/fn_edenApplyAttributes.sqf`
- `mod/@COMSPEC_SSE/addons/intel/functions/fn_attachIntelLayers.sqf`
- `mod/@COMSPEC_SSE/addons/intel/functions/fn_makeEvidenceLabel.sqf`
- `mod/@COMSPEC_SSE/addons/core/functions/fn_getPair.sqf`, `fn_setPair.sqf`, `fn_setData.sqf`, `config.cpp`
- `mod/@COMSPEC_SSE/addons/zeus/functions/fn_moduleDebugInspector.sqf`, `fn_moduleViewData.sqf`

## Vérification
Relancer mission avec unités SSE Eden / Zeus : plus d’erreur Params, `_data` indéfini, ni `BIS_fnc_getFromPairs … type ANY`.

## Statut
corrigé
