# Barre de données en bas de la carte ATAK

## Contexte

Sur le téléphone ATAK, une ligne verte restait collée en bas de la carte : sync, fiabilité, volume, perte, indicatif, versions Overwatch / Athena / liaison.

## Symptôme

Le bas de la carte est masqué par un bandeau technique (OK · sync 15s · fiab. 100% · …). La boussole et le terrain y passent derrière. Le bandeau restait même après désactivation dans un profil déjà enregistré.

## Cause

Athena peignait un cadre (identifiant 99871) toutes les deux secondes. L’affichage était activé par défaut, et le profil de l’opérateur le réactivait au chargement.

## Correctif

Le cadre n’est plus créé. S’il existe encore, il est masqué puis détruit. Le réglage est forcé masqué au chargement. Athena 1.0.143.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updateLinkStrip.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/XEH_preInit.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/XEH_postInit.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/ui/settings_page.hpp`

## Vérification

Test unitaire `AtakLinkStripHiddenAssetTest` : plus de création du bandeau, masquage forcé, Athena 1.0.143. Pack à recharger après quitter Arma complètement.

## Statut

Corrigé côté sources (Athena 1.0.143, à valider in-game après relance).
