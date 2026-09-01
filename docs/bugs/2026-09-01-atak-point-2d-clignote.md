# Point d’effectif visible en 3D, clignotant puis absent en 2D

## Contexte

Sur la carte du poste, un contact (ex. TA1) reste bien affiché en vue relief. En passant à la carte à plat, le point apparaît un instant puis disparaît.

## Symptôme

Vue 3D : pastille et indicatif stables. Vue 2D : le point clignote une fois, puis plus rien, alors que le tableau des effectifs montre toujours le contact en liaison.

## Cause

En vue relief, la carte à plat était retirée de l’affichage (`display: none`). Leaflet mesurait alors une carte de taille nulle et recalé les pastilles. Au retour à plat, elles se dessinaient un instant sur des positions périmées, puis s’effaçaient.

## Correctif

La carte à plat reste dimensionnée sous le relief (masquée visuellement, sans quitter le flux). Au retour à plat, les pastilles d’effectifs sont redessinées. Tant que la carte n’a pas de taille utile, on ne vide plus la liste des pastilles.

## Fichiers touchés

- `public/assets/js/atak-terrain3d-premium.js`
- `public/assets/css/atak-terrain3d-premium.css`
- `public/assets/js/map/MarkerManager.js`

## Vérification

Tests `AtakTerrain3dPremiumAssetTest`. Contrôle visuel : 3D actif avec un contact, puis 2D — le point reste à sa place.

## Statut

Corrigé (recharger la page du poste, éventuellement vider le cache du navigateur).
