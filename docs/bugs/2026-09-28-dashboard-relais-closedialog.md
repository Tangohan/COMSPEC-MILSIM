# Dashboard Relais — overlay bloquant (closeDialog)

**Date :** 2026-09-28  
**Statut :** corrigé

## Contexte

Le tableau de bord Relais ouvre un overlay via `createDisplay "RscDisplayEmpty"` sur le display 46.

## Symptôme

Fermeture (ESC / bouton) sans effet, ou overlay qui reste et bloque le focus clavier.

## Cause

Les handlers appelaient `closeDialog 0` (API `createDialog`), incompatible avec `createDisplay`. Le KeyDown ESC renvoyait aussi toujours `false` (dernière expression).

## Correctif

- Fermeture via `_display closeDisplay 2`.
- Handle stocké dans `uiNamespace` (`COMSPEC_RelayDashboard_Display` / `_Close`).
- ESC : `exitWith { true }` après `closeDisplay`.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_openRelayDashboard.sqf`

## Vérification

Ouvrir le dashboard Relais → ESC ou ✖ ferme l’overlay et rend le contrôle.

## Statut

Corrigé.
