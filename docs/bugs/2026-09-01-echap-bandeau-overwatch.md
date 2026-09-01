# 2026-09-01 — Bandeau COMSPEC Overwatch dans le menu Échap

## Contexte
En session, le menu pause (Échap) affiche en haut un bandeau avec le nom du pack.

## Symptôme
Un bandeau noir « COMSPEC Overwatch » avec un pavé orange à droite recouvre le haut du menu pause, sans bouton ni information utile.

## Cause
Le pack se déclarait visible dans le menu pause d’Arma (`hideName` / `hidePicture` à 0).

## Correctif
Le pack ne s’affiche plus dans cette barre. Le bouton de gestion du pack, en haut à gauche, reste.

## Fichiers touchés
- `mod/UptoDate/mod.cpp`
- `mod/UptoDate/@COMSPECOverwatch/mod.cpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/main/config.cpp`

## Vérification
Après rechargement du pack et relance d’Arma : Échap en mission — plus de bandeau. Le bouton de gestion du pack est toujours là.

## Statut
Corrigé (visible après rechargement du pack)
