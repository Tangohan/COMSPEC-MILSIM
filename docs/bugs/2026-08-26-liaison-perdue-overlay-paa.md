# Overlay « Liaison perdue » — ancien habillage et double compte à rebours

## Contexte

Terminal ATAK sur téléphone (carte web dans le cadre du terminal). Coupure de liaison roleplay.

## Symptôme

Un habillage plein écran recouvrait la carte : bande jaune et noire, tours radio, texte en capitales. Deux durées de reconnexion s’affichaient en même temps (une figée dans l’image, une réelle au-dessus).

## Cause

L’image d’overlay (ancien habillage) contenait déjà le titre, les tours et une durée « 16 s ». Par-dessus, le terminal ajoutait un second compte à rebours réel. Sur le poste web, la même image servait de fond.

## Correctif

Panneau verre du poste (bordure discrète, typographie habituelle). Titre unique « Liaison perdue ». Un seul libellé « Reconnexion dans Xs ». L’ancien habillage n’est plus posé pour cette coupure. L’écran plein ne s’ouvre pas quand la liaison est seulement ralentie (bandeau Différé).

## Fichiers touchés

- `views/atak.php`
- `public/assets/css/atak.css`
- `public/assets/css/atak-c2-shell.css`
- `public/assets/css/atak-roleplay-ctab.css`
- `public/assets/js/atak-roleplay-ctab.js`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_updateDeviceOverlay.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_updateAtakEnhancedRoleplay.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_injectRoleplayEffectsInBrowser.sqf`
- `tests/Unit/AtakLostLinkOverlayAssetTest.php`
- `app/Support/DevDispatchCatalog.php` (UPDATE 235)

## Vérification

Tests unitaires sur la vue, les feuilles, le script et les sources du terminal. Recette : coupure de liaison sur le téléphone — un panneau, une durée. Mauvaise connexion sans coupure : bandeau Différé, pas d’écran plein.

## Statut

corrigé
