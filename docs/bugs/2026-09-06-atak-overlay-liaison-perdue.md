# Overlay et son de perte de liaison sur l’ATAK

## Contexte

Lors d’une coupure de liaison (simulation roleplay, socket, ou qualité de liaison perdue), l’ATAK recouvrait l’écran d’un panneau « Liaison perdue » et jouait le son de coupure. L’opérateur ne voyait plus la carte ni les applications.

## Symptôme

- Écran du téléphone occulté par un panneau plein cadre.
- Son de coupure à chaque perte de liaison.
- Même comportement dans le navigateur du téléphone et sur la page ATAK.

## Cause

L’overlay natif (`fn_updateDeviceOverlay`) et l’injection dans le navigateur affichaient un panneau central dès que la liaison était marquée coupée. Le son était déclenché par la simulation réseau, le rappel d’extension, la page ATAK et le suivi de qualité de liaison du téléphone.

## Correctif

- Plus aucun panneau plein écran pour une perte de liaison.
- Plus de son de coupure associé.
- L’état de liaison reste visible dans la barre d’état (Hors ligne / Coupée).
- Les overlays d’appareil (écran cassé, éteint, gel, hors service) sont inchangés.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_updateDeviceOverlay.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_injectRoleplayEffectsInBrowser.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_playRoleplaySound.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_simulateNetworkDisconnect.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_extensionCallback.sqf`
- `mod/Overwatch 2026/ProdVersion/GPT/@COMSPEC_ATAK/addons/comspec_atak_core/functions/fn_athenaStart.sqf`
- `views/atak.php`
- `public/assets/js/atak-roleplay-ctab.js`
- `public/assets/js/atak-roleplay-effects.js`

## Vérification

- Tests unitaires `AtakLostLinkOverlayAssetTest` et `DevDispatchCatalogTest`.
- Contrôle des sources : aucun `_title = "Liaison perdue"`, aucun `classList.add('show')` sur l’overlay, aucun son de coupure sur la simulation réseau.

## Statut

Corrigé.
