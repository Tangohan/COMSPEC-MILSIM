# ATAK — icônes marqueurs Arma en 404

## Contexte

Carte ATAK (`/atak/`) sur athena.ttrd.fr : les repères issus du jeu demandent des textures vanilla / Orange absentes du dossier web.

## Symptôme

Console navigateur : 404 en boucle sur

- `…/assets/markers/arma/a3/ui_f/data/map/mapcontrol/tourism_ca.png`
- `…/assets/markers/arma/a3/ui_f/data/map/mapcontrol/quay_ca.png`
- `…/assets/markers/arma/a3/ui_f_orange/data/cfgmarkers/safetyzone_ca.png`
- `…/assets/markers/arma/a3/ui_f_orange/data/cfgmarkers/redcrystal_ca.png`

## Cause

La conversion initiale des PNG vanilla n’incluait pas `mapcontrol`, ni les marqueurs du DLC Laws of War (`ui_f_orange`).

## Correctif

1. Convertir `Addons/a3/ui_f/data/map/mapcontrol/*.paa` → PNG (35 fichiers).
2. Extraire `Orange/Addons/ui_f_orange.pbo` (BankRev) puis convertir `data/cfgmarkers/*.paa` → PNG (11 fichiers).
3. Déposer sous `public/assets/markers/arma/a3/…` et déployer sur le VPS.

Script : `mod/UptoDate/tools/vanilla-paa-to-png.ps1` (inclut désormais `mapcontrol`).

## Fichiers touchés

- `public/assets/markers/arma/a3/ui_f/data/map/mapcontrol/`
- `public/assets/markers/arma/a3/ui_f_orange/data/cfgmarkers/`
- `mod/UptoDate/tools/vanilla-paa-to-png.ps1`

## Vérification

Ouvrir `/atak/` : plus de 404 sur tourism / quay / safetyzone / redcrystal ; icônes visibles sur la carte.

## Statut

corrigé (local) — déploiement VPS à confirmer après push / FTP
