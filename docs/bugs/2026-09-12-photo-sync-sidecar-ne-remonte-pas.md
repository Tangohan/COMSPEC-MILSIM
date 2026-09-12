# Photo sync « sidecar » sans grille / hors Quick Picture

## Contexte

12 septembre 2026. Sur le PANNEAU du poste, une prise de vue depuis le téléphone produit souvent deux entrées : une « Photo ATAK Enhanced » avec grille (Quick Picture), et une « Photo ATAK (sidecar) » en PNG `COMSPEC_….png` sans grille.

## Symptôme

- La photo issue du sync disque (watcher) apparaît comme « sidecar » sans référence de grille.
- Elle ne suit pas le même parcours que Quick Picture (légende Enhanced, métadonnées, anti-doublon).
- Un même cliché peut remonter deux fois (Screenshots + miroir Captures).

## Cause

1. Le watcher DLL déposait directement un envoi avec la légende « Photo ATAK (sidecar) », hors du pont Quick Picture.
2. La grille n’était jamais mémorisée depuis la sync de position (`_lastPhotoGrid` restait vide).
3. Le dédoublonnage se faisait sur le chemin complet : nom seul (SQF) et chemin absolu (watcher / miroir) passaient pour deux photos distinctes.

## Correctif

- Le watcher signale `PhotoDiskSync` vers le jeu, qui appelle le même pont que Quick Picture (`bridgeIcemanPhoto`) avec grille et légende Enhanced.
- La sync de position envoie aussi la grille pour les métadonnées de repli.
- Déduplication par nom de fichier (`leaf|…`) pour n’avoir qu’une remontée par cliché.

Relancer Arma complètement après le pack Overwatch 1.5.50 (liaison 2.0.30).

## Fichiers touchés

- `mod/UptoDate/COMSPECExtension/Extension.cs`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_extensionCallback.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_updatePosition.sqf`
- `docs/bugs/2026-09-12-photo-sync-sidecar-ne-remonte-pas.md`
- `tests/Unit/AtakPhotoDiskSyncAssetTest.php`

## Vérification

1. Prendre une photo Quick Picture : une seule entrée au poste, légende Enhanced avec grille.
2. Aucune entrée « Photo ATAK (sidecar) ».
3. Un PNG récent déposé dans Captures hors téléphone remonte aussi via Enhanced avec grille.

## Statut

corrigé (Overwatch 1.5.50 · liaison 2.0.30)
