# Bug — Quick Picture ATAK : plus de déplacement, photos absentes du poste

## Contexte

1er septembre 2026. Un opérateur ouvre la caméra plein écran du téléphone ATAK (Quick Picture). Il doit pouvoir marcher et envoyer la photo au poste.

## Symptôme

- Une fois la caméra ouverte, le déplacement (marche) est bloqué.
- La photo ne remonte pas au poste.

Journal Overwatch :

```
[Tx] → NotifyNewPhoto — COMSPEC_644_63259.png
[Tx] OK · NotifyNewPhoto — COMSPEC_644_63259.png
[Tx] ÉCHEC · PhotoUpload — file_not_found · COMSPEC_644_63259.png | ERR|file_not_found|…|name_only|dirs=3|newest_<1h| …\Screenshots ; …\Captures ; …\@# S.O.A.R - FN\Screenshot
```

Le signal part, le fichier annoncé n’est pas sur le disque. D’autres captures récentes existent dans les dossiers balayés.

## Cause

1. **Déplacement.** Notre remplacement de la caméra plein écran forçait toujours une vue cinématique (écran principal). L’amont, pour le téléphone, rend vers une texture puis laisse le soldat contrôlable. La vue cinématique bloque la marche.
2. **Photos.** Le cliché BCE / SOAR écrit déjà un JPEG. Le pont Athena recevait souvent le *dossier* et le *nom* séparément, ne voyait pas l’extension, puis déclenchait un second cliché PNG. Ce PNG n’était jamais créé (vue cinématique + overlay). Athena cherchait `COMSPEC_….png` par nom seul et échouait, alors qu’un JPEG récent était bien dans le dossier Screenshot du pack SOAR.

## Correctif

- Téléphone : rendu vers texture + contrôle du soldat (on peut marcher). Casque : inchangé (vue scène).
- Au déclenchement photo : bascule brève en vue scène pour enregistrer l’overlay, puis restauration pour marcher à nouveau.
- Reconstituer le chemin complet dossier + nom ; notifier le JPEG déjà écrit, sans second PNG fantôme.
- Recherche fichier : fenêtre plus large autour de l’heure du cliché, dossiers Captures / Screenshots du profil, dossier de jeu pour un nom seul.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_ATAK_FullScreenCamera.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_ATAK_TakePicture.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_bridgeIcemanPhoto.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/XEH_postInitClient.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_promoteCaptureCam.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_restoreCaptureCam.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_getActiveCaptureCam.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_captureReconImage.sqf`
- `mod/UptoDate/COMSPECExtension/Extension.cs`

## Vérification

Tests d’assets Quick Picture + catalogue UPDATE 339. Rebuild du pack. En jeu : ouvrir Quick Picture, marcher, prendre une photo, contrôler l’arrivée au poste et l’absence de `file_not_found` sur un `COMSPEC_….png`.

## Statut

corrigé (pack à recharger, quitter Arma complètement)
