# Instrumentation debug STACK_OVERFLOW / ACE

Date : 2026-08-16

## Contexte

Besoin d’identifier précisément les causes de `C00000FD STACK_OVERFLOW`, récursions, doubles init ACE et EventHandlers multiples.

## Livrable

Nouvel addon `comspec_sse_debug` + instrumentation de :

- `core` XEH pre/postInit
- `fn_initACE` / `fn_initDigitalACE` / `fn_initBiometricsACE`
- postInit interaction / digital / biometrics
- Overwatch `fn_initSseAce`

## Usage diagnostic

1. Rebuild PBO (`debug` inclus dans `build_pbo.bat`).
2. Lancer une mission ; lire le `.rpt` pour :
   - `BREADCRUMB` (dernier point avant crash)
   - `ACE BEGIN/END class=…` (classe fautive)
   - `WATCHDOG Alive after Xs` (fenêtre temporelle)
   - `ACE DUPLICATE` / `EH DUPLICATE`
3. Isolation CBA : désactiver Digital puis Biometrics, etc.
4. Safe Mode : `COMSPEC_SAFE_MODE` (settings ou missionNamespace).

## Statut

Implémenté — à valider in-game après rebuild.
