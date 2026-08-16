# Crash STACK_OVERFLOW après applyModel HVT manquant

Date : 2026-08-16  
Statut : partiellement corrigé (registre modèles) + anti-réentrée / Eden différé — voir `sse-crash-analyse-complete.md`

## Contexte

Session SP Altis (`tempMissionSP`), mods `@COMSPEC_SSE` + `@COMSPECOverwatch` + pack S.O.A.R.  
RPT : `Arma3_x64_2026-08-16_20-04-01.rpt`  
Crash Reporter : 20:13:18.

## Symptôme

1. Toast in-game : `[COMSPEC SSE][ERROR] applyModel: modèle introuvable (builtin_iq_2010_2020_hvt)`
2. Immédiatement après dans le RPT :
   - `Exception code: C00000FD STACK_OVERFLOW`
   - faute dans `tbb4malloc_bi_x64.dll`
3. Overwatch boot OK (Athena handshake, menus ACE) ; uploads photo en `file_not_found` (secondaire).

## Cause

### A — Modèle « introuvable » (SSE)

- L’ID `builtin_iq_2010_2020_hvt` **est bien** dans le PBO Workshop `comspec_sse_generator.pbo`.
- Aucune trace RPT de `registerBuiltinModels` / `generator preInit` (logs INFO filtrés sans `comspec_sse_debug`).
- Hypothèse forte : registre `comspec_sse_models_builtin` absent ou **partiel** au moment de l’apply Eden (early-exit trop agressif si la liste existait déjà sans les IDs Irak 2010–2020).
- Conséquence : `loadModel` renvoyait `nil` → erreur soft.

### B — STACK_OVERFLOW (pas causé directement par l’erreur soft)

- Même signature que les crashes précédents : chaînes `"Was unit a player?"` / `"ACE Was detected, adding event handler for ace"` **hors** codebase COMSPEC.
- Corrélation temporelle avec pose d’unités + génération SSE (`generateCluster` à 20:13:13), pas avec un récursif `applyModel` (qui exitait déjà en `false`).
- Spam `cTabExtension could not be found` (toutes les ~1 s) : bruit / charge, pas la cause du stack overflow.

### C — Hors scope immédiat

- `PhotoUpload file_not_found` + `COMSPEC_AthenaFeed` sans extension : chemins captures / nom invalide.
- Stub ZEN : Overwatch compense, non bloquant.

## Correctif

Fichiers SSE generator / eden :

- `fn_registerBuiltinModels.sqf` — fusion par ID (plus d’exit « liste non vide ») + log **WARN**
- `fn_loadModel.sqf` — re-register + retry si miss
- `fn_applyModel.sqf` — fallback `generateData` si modèle absent
- `fn_edenApplyAttributes.sqf` — filet si données toujours vides
- `XEH_preInit.sqf` — rebuild forcé + log WARN

## Vérification

1. Rebuild / redeploy `comspec_sse_generator.pbo` + `comspec_sse_eden.pbo` (Arma fermé).
2. Au boot RPT : ligne `registerBuiltinModels: N modèles`.
3. Placer une unité avec modèle `builtin_iq_2010_2020_hvt` → plus d’erreur « introuvable », données générées.
4. Si `STACK_OVERFLOW` persiste après fix modèle → **désactiver le pack S.O.A.R / MRH** (voir `docs/bugs/2026-08-16-mrh-soar-objectobject-ace.md`) avant d’incriminer ACE Medical seul.

## Fichiers touchés

- `mod/@COMSPEC_SSE/addons/generator/functions/fn_registerBuiltinModels.sqf`
- `mod/@COMSPEC_SSE/addons/generator/functions/fn_loadModel.sqf`
- `mod/@COMSPEC_SSE/addons/generator/functions/fn_applyModel.sqf`
- `mod/@COMSPEC_SSE/addons/generator/XEH_preInit.sqf`
- `mod/@COMSPEC_SSE/addons/eden/functions/fn_edenApplyAttributes.sqf`
