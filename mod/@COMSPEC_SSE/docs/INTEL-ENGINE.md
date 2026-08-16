# Moteur Intel SSE — V0.6

Addon : `comspec_sse_intel`

## Niveaux d’exploitation

`NONE → TACTICAL → FIELD → DETAILED → FUSION`

Chaque niveau révèle une couche d’intel (`intelLayers`).  
ACE : **Approfondir l’exploitation** / fouille avance automatiquement.

## Métadonnées renseignement

Chaque donnée : `INTEL_VALUE`, `TIME_SENSITIVITY`, `CONFIDENCE`, `RELEVANCE`,  
`confidenceKind` (`OBSERVED|EXTRACTED|PROBABLE|HYPOTHESIS`),  
`discoveryState` (`KNOWN|ASSESSED|CONFIRMED|DISPROVEN`), `tags`, `triage`.

## Triage

`EXPLOIT_NOW | COLLECT | DOCUMENT_ONLY | LOW_VALUE | UNKNOWN`  
Module Zeus **Site Manager** + fouille (si réglage auto).

## API mission

```sqf
["SSE-INTEL-004"] call comspec_sse_fnc_isDiscovered;
["SSE-INTEL-004", { params ["_entity","_datum","_id"]; /* ... */ }] call comspec_sse_fnc_registerZeusHook;
["camera", ["MonMod_Camera"]] call comspec_sse_fnc_registerModClasses;
["INSURGENT_CELL", player, 40] call comspec_sse_fnc_loadScenarioPack;
["cellule logistique de 5 personnes", player, 40] call comspec_sse_fnc_generateFromBrief;
[] call comspec_sse_fnc_exportMissionGraph;
```

## Events CBA

`comspec_sse_recordCreated`, `recordCollected`, `recordExploited`,  
`intelDiscovered`, `networkLinked`, `triageDone`, `hookFired`, `fusionUpdated`

## Zeus

- **Scenario Director** (dataset / niveau) — LOT 8, ex. FALCON  
- Site Manager SSE  
- Générer depuis brief / scénario (`FALCON`, `INSURGENT_CELL`, …)  
- Spoil Control  
- After Action + export  
- Sandbox site aléatoire  

## Scénarios

`FALCON`, `INSURGENT_CELL`, `SMUGGLING_NETWORK`, `WEAPONS_DEPOT`, `COMMAND_POST`,  
`SAFEHOUSE`, `IED_WORKSHOP`, `FINANCIAL_NODE`, `INTELLIGENCE_CELL`
