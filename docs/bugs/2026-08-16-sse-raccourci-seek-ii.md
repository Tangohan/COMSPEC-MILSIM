# SEEK II via BII-10 + couche ATAK

## Contexte

Le raccourci « Ouvrir SEEK II » ouvrait uniquement le dialogue COMSPEC isolé.
L’opérateur utilise déjà **BII-10 Identifi** (S.O.A.R) comme terminal biométrique.

## Symptôme

Pas d’intégration native dans BII / tiroir ATAK ; double parcours (SEEK COMSPEC vs BII).

## Cause

Keybind branché sur `openSeek` seulement ; BII non enregistré comme app ATAK.

## Correctif

1. Keybind → **BII Identifi** (onglet Identify) si BII chargé, sinon fallback SEEK COMSPEC
2. Barre COMSPEC injectée dans le dialogue BII (`ID / SEEK`, `SSE`, `Fiche COMSPEC`)
3. App ATAK **BII-10** dans le tiroir (Overwatch `atak_athena`)

## Fichiers touchés

- `mod/@COMSPEC_SSE/addons/biometrics/functions/fn_openSeekKeybind.sqf`
- `mod/@COMSPEC_SSE/addons/compat_bii/functions/fn_biiOpen.sqf`
- `mod/@COMSPEC_SSE/addons/compat_bii/functions/fn_biiInjectLayer.sqf`
- `mod/@COMSPEC_SSE/addons/compat_bii/functions/fn_biiInstallHooks.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/*` (app BII-10)

## Vérification

- Ctrl+Shift+S avec BII-10 en inventaire → Identifi (scan)
- ATAK → applications → **BII-10**
- Dans Identifi : barre basse COMSPEC visible

## Statut

corrigé
