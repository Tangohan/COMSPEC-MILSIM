# Tuile ATAK Paramètres — identité, équipe de feu, groupe

## Contexte

Les opérateurs n’avaient pas d’écran ATAK pour régler indicatif, rôle, équipe de feu et rattachement de groupe ; ces réglages passaient par d’autres dialogues ou n’étaient pas visibles (identifiant ATAK).

## Symptôme

Pas de tuile « Paramètres » sur le bureau ATAK. Impossible de voir l’identifiant ATAK, de modifier l’indicatif, de choisir un rôle / une équipe de feu, ou de rejoindre un autre groupe en jeu (celui qui apparaît sur la carte ATAK).

## Cause

L’addon `atak_athena` n’exposait pas d’app Paramètres. L’enregistrement d’indicatif renommait aussi le groupe, ce qui empêchait de lier un groupe distinct.

## Correctif

- App ATAK **Paramètres** (tuile bureau + tiroir) : identifiant ATAK (lecture), indicatif, rôle, équipe de feu (couleur Arma + équipes Athena), groupe en jeu.
- Enregistrement : indicatif / rôle, `assignTeam`, rattachement fire team Athena (`JoinFireTeam`), `joinSilent` vers le groupe choisi.
- L’indicatif depuis Paramètres ne renomme plus le groupe.

## Fichiers touchés

- `atak_athena/ui/settings_page.hpp`
- `atak_athena/functions/fn_athena_openSettings.sqf`
- `atak_athena/functions/fn_athena_settingsOnOpened.sqf`
- `atak_athena/functions/fn_athena_updateSettings.sqf`
- `atak_athena/functions/fn_athena_settingsSave.sqf`
- `atak_athena/functions/fn_athena_installDesktopShortcut.sqf`
- `atak_athena/config.cpp` (1.0.38)
- `connect/functions/fn_setCallsign.sqf`
- `connect/functions/fn_updatePosition.sqf`
- `connect/functions/fn_initATAK.sqf`
- `connect/functions/fn_onPlayerRespawn.sqf`
- `connect/XEH_preInit.sqf`
- `connect/config.cpp` (1.4.43)
- `COMSPECExtension/Extension.cs` (2.0.5, `JoinFireTeam`)

## Vérification

Compilation PBO `atak_athena` + `connect` et DLL Native AOT. Contrôle in-game : tuile Paramètres, enregistrement indicatif/rôle/équipe/groupe, identifiant visible. Relancer Arma après déploiement.

## Statut

Livré (à valider in-game après relance Arma).
