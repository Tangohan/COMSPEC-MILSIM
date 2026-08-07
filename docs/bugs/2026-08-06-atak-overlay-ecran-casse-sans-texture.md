# ATAK — overlay « écran endommagé » sans texture fissurée

## Contexte

État roleplay / Zeus `screen_ok = false` sur cTab Android / Hub. Capture joueur : bandeau texte seul « ÉCRAN ENDOMMAGÉ ».

## Symptôme

L’overlay restait un fond sombre + texte. L’asset `comspec_overlay_screen_cracked_ca.png` existait mais n’était jamais chargé (macros pointaient vers des `.paa` absents).

## Cause

- `fn_updateDeviceOverlay.sqf` ne créait qu’un `RscStructuredText`.
- Chemins macros en `.paa` alors que seuls des `.png` sont packés (`-packonly`).

## Correctif

- Picture `RscPicture` sous le texte avec `comspec_overlay_screen_cracked_ca.png` (et variantes off / no_signal).
- Macros overlay pointent vers `.png`.
- Même texture pour le hub (`fn_updateAtakEnhancedRoleplay`).

## Fichiers touchés

- `connect/functions/fn_updateDeviceOverlay.sqf`
- `connect/functions/fn_updateAtakEnhancedRoleplay.sqf`
- `connect/display_device_macros.hpp`

## Vérification

- [x] Rebuild + deploy `connect.pbo` Workshop (texture PNG présente dans le PBO)
- [ ] En jeu : écran endommagé → fissures visibles derrière le texte

## Statut

corrigé — déployé ; confirmation in-game à faire
