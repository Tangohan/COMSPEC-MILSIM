# IA alliée ATAK — disparition en quittant Zeus, suivi non annulable

**Date :** 2026-08-24  
**Statut :** corrigé (sources) — rebuild Overwatch connect 1.4.65

## Contexte

Zeus : « IA alliée sur l’ATAK ». Session Overwatch 1.4.63, ZEN incomplet (stub `zen_attributes_fnc_addAttribute`).

## Symptôme

1. En quittant Zeus, les unités marquées disparaissent de la carte ATAK. Le suivi ne survit pas.
2. Impossible d’annuler l’attribution : recliquer réétend le groupe au lieu de couper le suivi.

## Cause

1. Le drapeau était posé seulement en local Zeus. Au transfert de localité (Zeus → serveur), la variable objet se perdait. La liste `COMSPEC_AllyTrackUnits` n’était pas publique.
2. Un clic sur une IA ajoutait tout son groupe. Tant qu’un membre n’avait pas le drapeau, le menu **rallumait** tout le monde au lieu d’éteindre.

## Correctif

- Registre public des identifiants réseau, re-pose du drapeau si le transfert l’a effacé, et rappel 0,4 / 2 / 6 s après l’activation.
- Diffusion du suivi à toutes les machines.
- Clic : uniquement la sélection. Menu **Retirer l’IA de l’ATAK**. Case équipage décochable.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_setAllyTrack.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_initGpsBeacons.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_registerZenTrackActions.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_zeusAttributesAtak.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp` (1.4.65)

## Vérification

Rebuild PBO `connect` 1.4.65, relancer Arma. Zeus : marquer une IA → quitter Zeus → elle reste sur l’ATAK. Clic droit **Retirer l’IA de l’ATAK** : le suivi s’arrête. Décochez « IA alliée » dans le panneau Éditer : même effet.
