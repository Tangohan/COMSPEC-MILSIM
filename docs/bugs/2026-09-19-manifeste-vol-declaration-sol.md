# Manifeste de vol : déclaration sol trop pauvre

## Contexte

19 septembre 2026. Manifeste de vol ouvert à pied. L’écran montrait « Déclaration sol », « À préciser (déclaration sol) » et « Non détectée ». Le poste ne recevait pas de rôle, de destination ni de note.

## Symptôme

Une déclaration depuis le sol n’était pas exploitable par le poste.

## Cause

Le formulaire ne proposait que l’indicatif, un type figé, un modèle placeholder et une fréquence non modifiable.

## Correctif

- Type d’appareil, rôle, destination, grille, carburant, personnes à bord et note.
- En vol, appareil, grille et carburant remplis automatiquement, encore corrigeables.
- Réponse pilote **À poste**.
- Fiche du poste : rôle, destination, carburant, authentification, notes.

Pack : Overwatch **1.5.97**. Relancer Arma complètement.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/display_flight_manifest.hpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_fillFlightManifest.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_submitFlightManifest.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_pilotResponse.sqf`
- `public/assets/js/atak-air-assets.js`
- `public/assets/js/atak-unit-popup.js`

## Vérification

1. Quitter Arma. Recharger Overwatch 1.5.97.
2. À pied, ouvrir Manifeste de vol : indiquer un appareil et un rôle, transmettre.
3. Au poste, la fiche aérienne montre ces informations.
4. En vol, appareil, grille et carburant sont déjà remplis.

## Statut

corrigé
