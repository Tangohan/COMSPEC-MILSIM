# Appui aérien et manifeste hors du téléphone

## Contexte

19 septembre 2026. La demande d’appui aérien (et le manifeste) s’ouvraient en grand par-dessus le terrain, même depuis Athena.

## Symptôme

Le formulaire recouvrait le jeu au lieu de rester dans le téléphone.

## Cause

Les boutons Athena et le menu situation ouvraient un dialogue overlay, pas une application du tiroir.

## Correctif

Deux applications dans le tiroir : **Appui aérien** et **Manifeste**. Les mêmes champs qu’avant, dans le téléphone.

Pack : Overwatch **1.5.98**, Athena **1.0.154**. Relancer Arma complètement.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/ui/cas_page.hpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/ui/manifest_page.hpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_casRequestShow.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_flightManifestShow.sqf`

## Vérification

1. Quitter Arma. Recharger Overwatch 1.5.98 / Athena 1.0.154.
2. Tiroir : Appui aérien — type, grille, envoyer. Le formulaire reste dans le téléphone.
3. Tiroir : Manifeste — transmettre. Le poste reçoit la fiche.

## Statut

corrigé
