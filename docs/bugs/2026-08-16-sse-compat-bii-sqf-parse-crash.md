# Crash SSE — parse SQF + forEach BII (2026-08-16)

## Contexte

Le mod `@COMSPEC_SSE` crashait au chargement / post-init dès que la compat BII Identifi et les addons SSE étaient présents (session du 16/08/2026, RPT `Arma3_x64_2026-08-16_16-52-33.rpt`, crash ~16:56 juste après PostInit Overwatch).

## Symptôme

- Crash client / session Arma peu après le chargement des fonctions SSE.
- Erreurs SQF au compile des fonctions, notamment :
  - `fn_generateWeapon.sqf` — `Error ] manquant` autour de `getOrDefault ["depotGrid", "?"])]`
  - `fn_generateTechint.sqf` — même famille (brackets imbriqués dans un littéral array)
  - `fn_toJsonApprox.sqf` — `Error } manquante` (échappement `"\""` invalide)
  - `fn_uiFillDigital.sqf` — `getOrDefault` imbriqué dans les args de `format`
- Warning : `Addon 'comspec_sse_evidence' requires addon 'cba_misc'` (addon CBA disparu / fusionné).
- Bug distinct : `forEach allUnits + vehicles + ...` dans `compat_bii/XEH_postInitServer.sqf` (priorité SQF) — crash différé uniquement si BII est présent.

## Cause

1. **Parseur SQF** : un `]` ferme le `[` le plus proche. Donc :
   - `getOrDefault ["a", obj getOrDefault ["b", x]]` est invalide ;
   - un `[_seed, "x"]` ou `format ["...", ...]` imbriqué dans un autre littéral `[...]` (ex. `createHashMapFromArray`) casse aussi le matching.
2. **Échappement JSON** : en SQF, `\` n’est pas un escape. `"\""` laisse une guillemet orpheline → `} manquante`.
3. **forEach BII** : `} forEach allUnits + vehicles + ...` parse comme `({...} forEach allUnits) + vehicles + ...`.
4. **cba_misc** : plus de PBO `cba_misc` dans CBA 3.19 — `CBA_MiscItem` vit dans `cba_common`.

## Correctif

- Extraire des variables intermédiaires à la place des `getOrDefault` / arrays imbriqués (generator, intel, ui, digital, network, compat_bii…).
- Réécrire l’échappement JSON avec `toString [92]`.
- `XEH_postInitServer.sqf` : `forEach _objs` avec parenthèses / collection pré-calculée ; appeler `biiInstallHooks` côté serveur.
- Garde anti-réentrée `comspec_sse_biiInEnsureWrap` autour du wrap `ensureGenerated`.
- `requiredAddons` : `cba_misc` → `cba_common` (evidence + main).

## Fichiers touchés

- `mod/@COMSPEC_SSE/addons/generator/functions/fn_generateWeapon.sqf`
- `mod/@COMSPEC_SSE/addons/generator/functions/fn_generateDocument.sqf`
- `mod/@COMSPEC_SSE/addons/generator/functions/fn_generateBuilding.sqf`
- `mod/@COMSPEC_SSE/addons/generator/functions/fn_generateVehicle.sqf`
- `mod/@COMSPEC_SSE/addons/generator/functions/fn_generateData.sqf`
- `mod/@COMSPEC_SSE/addons/intel/functions/fn_generateTechint.sqf`
- `mod/@COMSPEC_SSE/addons/intel/functions/fn_createIntelDatum.sqf`
- `mod/@COMSPEC_SSE/addons/intel/functions/fn_extractGeopoints.sqf`
- `mod/@COMSPEC_SSE/addons/intel/functions/fn_buildTimeline.sqf`
- `mod/@COMSPEC_SSE/addons/intel/functions/fn_deduplicateIntel.sqf`
- `mod/@COMSPEC_SSE/addons/intel/functions/fn_fireZeusHook.sqf`
- `mod/@COMSPEC_SSE/addons/network/functions/fn_toJsonApprox.sqf`
- `mod/@COMSPEC_SSE/addons/network/functions/fn_buildAthenaDigitalPayload.sqf`
- `mod/@COMSPEC_SSE/addons/network/functions/fn_sendViaOverwatch.sqf`
- `mod/@COMSPEC_SSE/addons/ui/functions/fn_uiFillDigital.sqf`
- `mod/@COMSPEC_SSE/addons/ui/functions/fn_uiFillGraph.sqf`
- `mod/@COMSPEC_SSE/addons/digital/functions/fn_revealDigitalFog.sqf`
- `mod/@COMSPEC_SSE/addons/digital/functions/fn_getDeviceSummary.sqf`
- `mod/@COMSPEC_SSE/addons/digital/functions/fn_getComputerSummary.sqf`
- `mod/@COMSPEC_SSE/addons/biometrics/functions/fn_getBiometricSummary.sqf`
- `mod/@COMSPEC_SSE/addons/compat_bii/XEH_postInitServer.sqf`
- `mod/@COMSPEC_SSE/addons/compat_bii/functions/fn_biiInstallHooks.sqf`
- `mod/@COMSPEC_SSE/addons/compat_bii/functions/fn_biiExportEntityVars.sqf`
- `mod/@COMSPEC_SSE/addons/compat_bii/functions/fn_biiRecordToSse.sqf`
- `mod/@COMSPEC_SSE/addons/evidence/config.cpp`
- `mod/@COMSPEC_SSE/addons/main/config.cpp`
- Rebuild + déploiement Workshop des `comspec_sse_*.pbo`

## Vérification

- Rebuild via `mod/@COMSPEC_SSE/build_pbo.bat` → Build OK dans `build_log.txt`.
- Copie des PBO vers `F:\SteamLibrary\steamapps\common\Arma 3\!Workshop\@COMSPEC_SSE\addons\`.
- À retester in-game : chargement mission avec SSE + CBA + BII Identifi, absence des erreurs `] manquant` / `} manquante` dans le RPT, pas de crash post-init, passerelle BII (scan / modules) fonctionnelle.

## Statut

Corrigé (en attente validation in-game utilisateur).
