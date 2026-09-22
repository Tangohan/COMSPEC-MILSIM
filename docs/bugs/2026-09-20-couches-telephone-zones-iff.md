# Panneau de couches du téléphone jamais branché

## Contexte
Le téléphone avait déjà un panneau « Couches » et un filtrage des marqueurs, mais le HUD carte ne les appelait plus. Les modules Zeus (affichage des IA ennemies au lancement, etc.) restaient le seul levier.

## Symptôme
Impossible de masquer ou d’afficher, depuis le téléphone, les contacts, relais ou zones réseau. Les zones de brouillage se voyaient en jeu (perte de signal) mais pas comme ombre sur la carte du poste.

## Cause
Le HUD carte ne recréait ni le bouton Couches ni l’application des filtres. Les ellipses de zone réseau étaient exclues de la remontée vers le poste, et dessinées en contour seulement.

## Correctif
Bouton Couches sur la carte du téléphone, filtres locaux mémorisés, volumes de bâtiments d’après le relevé du théâtre, zones réseau ombragées, alerte IFF discrète à l’approche d’un contact non identifié.

## Fichiers touchés
- `public/assets/js/comspec-operational-map.js`, `views/tacmap.php`
- `fn_createLayerPanel.sqf`, `fn_applyMapLayers.sqf`, `fn_athena_updateMapHud.sqf`
- `fn_createRoleplayZone.sqf`, `fn_syncRoleplayZonesFromPortal.sqf`, `fn_syncMapMarker.sqf`
- `fn_athena_iffProximityTick.sqf`, `fn_athena_iffProximityAlert.sqf`

## Vérification
Tests d’assets `AtakTacmapSceneLayersIffAssetTest`. En jeu : Couches sur le téléphone, Bâtiments et Zones réseau sur la carte du poste, vibration unique à l’approche d’un contact adverse.

## Statut
corrigé (Overwatch 1.6.8 / Athena 1.0.164)
