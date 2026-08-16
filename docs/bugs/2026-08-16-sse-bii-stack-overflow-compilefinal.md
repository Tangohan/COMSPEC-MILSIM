# Crash SSE — STACK_OVERFLOW override compileFinal (BII)

## Contexte

Session `Arma3_x64_2026-08-16_17-44-00.rpt` (~17:47:41), juste après « Menu SSE installé ».

## Symptôme

- Crash client `Exception code: C00000FD STACK_OVERFLOW`
- Immédiatement précédé de :
  - `Attempt to override final function - bii_fnc_identifi_processscan`
  - idem `collectevidence`, `moduleidentity`, `moduleevidence`
  - `Attempt to override final function - comspec_sse_fnc_ensuregenerated`

## Cause

`fn_biiInstallHooks.sqf` tentait de réassigner des fonctions **compileFinal** (CfgFunctions BII + SSE).
Le moteur loggue l’échec puis peut déborder la pile — le `try/catch` SQF ne protège pas contre ça.

## Correctif

1. **Plus aucun override** des fonctions BII / `ensureGenerated`.
2. Fusion identité BII ↔ SSE **dans le source** `fn_ensureGenerated.sqf` (garde anti-réentrée).
3. Scans / preuves BII : **poll** de `BII_fnc_identifi_getState` toutes les 2 s.
4. Hooks ACE dogtags : skip si `isFinal`.

## Fichiers touchés

- `mod/@COMSPEC_SSE/addons/compat_bii/functions/fn_biiInstallHooks.sqf`
- `mod/@COMSPEC_SSE/addons/generator/functions/fn_ensureGenerated.sqf`
- `mod/@COMSPEC_SSE/addons/compat_ace/functions/fn_aceDogtagInstallHooks.sqf`

## Vérification

1. Fermer Arma, rebuild `build_pbo.bat`, copier les PBO vers le Workshop `@COMSPEC_SSE`.
2. Relancer mission avec BII Identifi.
3. RPT : **aucun** `Attempt to override final function` lié à BII/SSE ; pas de `STACK_OVERFLOW` après Menu SSE.
4. Log SSE : `Passerelle BII: poll actif`.

## Statut

corrigé (sources) — rebuild + déploiement Workshop requis
