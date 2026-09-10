# Indicatif tablette / suivi d’effectif non calé sur Athena

## Contexte

10 septembre 2026. Téléphone ATAK en jeu. L’opérateur a un indicatif sur sa fiche Athena. Le suivi d’effectif de la tablette et le bandeau du téléphone doivent afficher le même indicatif, et le garder d’une session à l’autre.

## Symptôme

Le suivi d’effectif refuse de reprendre l’indicatif Athena. Le téléphone montre encore le nom de groupe du jeu, un numéro d’équipe, ou un ancien indicatif. Après un changement de véhicule ou une relance, l’indicatif Athena n’est pas mémorisé.

## Cause

L’indicatif Athena était connu du pack, mais n’était jamais recopié dans l’emplacement que la tablette utilise pour le suivi d’effectif. Un calque posé trop tôt était ensuite écrasé. Une fois un indicatif local déjà présent, la fiche Athena n’était plus réappliquée.

## Correctif

Dès que l’indicatif Athena est connu, il est écrit sur l’opérateur et le véhicule, mémorisé sur le profil, et réappliqué à la montée en véhicule, au retour et à la liaison. Le suivi d’effectif est recalé en continu tant que le téléphone est ouvert. Un indicatif Athena différent remplace l’ancien.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_applyCtabBftCallsign.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_setCallsign.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_syncCallsignFromAthena.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_extensionCallback.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_initVehicleTracking.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/XEH_postInit.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_installBftLabels.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_relabelBft.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/XEH_postInitClient.sqf`

## Vérification

Tests d’assets + UPDATE 482. Rebuild du pack. En jeu : lier Athena, ouvrir le téléphone. Le bandeau et le symbole de suivi doivent montrer l’indicatif de la fiche. Monter dans un véhicule, relancer Arma : l’indicatif reste.

## Statut

corrigé (pack à recharger, quitter Arma complètement)
