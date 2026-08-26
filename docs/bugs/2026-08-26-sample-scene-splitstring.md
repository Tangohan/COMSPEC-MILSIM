# Bug — relevé bâtiments : `splitString` reçoit un tableau

## Contexte

Pack Overwatch 1.4.75 chargé (liaison 2.0.16). Overlay Arma à l’ouverture d’un panneau Zeus (`rscdisplayattributesvehicleempty`) pendant le relevé automatique des volumes.

## Symptôme

```
Error splitstring: Type Tableau, Chaîne attendu
File …\fn_sampleScene.sqf, line 70
(_this splitString """" joinString "")
```

Le relevé s’arrête ; le drapeau interne peut rester bloqué jusqu’au changement de mission.

## Cause

`_fnc_esc` faisait `splitString` sur `_this`. Les appels sont du type `[typeOf _x] call _fnc_esc` : `_this` est alors un **tableau** d’une chaîne, pas la chaîne.

## Correctif

- `params ["_s"]` puis coercion `str` avant `splitString`.
- Même traitement pour `_fnc_num` (`params` + nombre).
- Filet 90 s pour relâcher `COMSPEC_SceneSampling` si le script s’interrompt.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_sampleScene.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp` (1.4.76)

## Vérification

1. Pack 1.4.76, relancer Arma.
2. Spawn + Zeus / menu ACE « Relever bâtiments et forêts » : plus d’overlay `splitString`.
3. Journal : `connect v1.4.76`.

## Statut

`corrigé` — pack 1.4.76 dans le dossier Workshop Steam et `@COMSPECOverwatch` local. La copie vers `!Workshop` a échoué tant qu’Arma tenait le fichier.
