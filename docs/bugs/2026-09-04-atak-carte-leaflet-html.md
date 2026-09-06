# Carte du téléphone : Leaflet à la place de la carte Arma

- Date : 2026-09-04
- Statut : corrigé (pack 1.8.14)

## Contexte

L’écran du téléphone est entièrement HTML. Une carte Arma placée dans un trou, derrière ou à la place de l’écran, ne peut pas transparaître : fond noir, ou menus masqués.

## Symptôme

- Terrain : fond noir, ou carte Arma sans bandeau / panneau.
- Impossible de garder menus HTML et carte du terrain dans le même écran.

## Cause

La carte native Arma ne traverse pas l’écran web. Les tuiles satellite n’étaient pas autorisées pour l’écran du téléphone.

## Correctif

Terrain dessine la carte du terrain dans l’écran (fond satellite, grille, position, équipe, marqueurs). Vue satellite ou plan, zoom et mesure restent dans cette carte. Plus de trou vers une carte Arma. Pack **1.8.14**.

## Fichiers touchés

- `web/live-map.js`, `web/phone.html`, `web/vendor/leaflet/`
- `ui/runtime.hpp`, `config.cpp`
- `fn_webLayout.sqf`, `fn_webOnLoad.sqf`, `fn_webMapShow.sqf`, `fn_webMapHide.sqf`, `fn_webMapRaise.sqf`, `fn_webPageLoaded.sqf`
- `fn_webPushState.sqf`, `fn_webPushTelemetry.sqf`, `fn_webExecJS.sqf`, `fn_webMapHtmlChrome.sqf`

## Vérification

- Journal : `mod=1.8.14`.
- Téléphone, Terrain : carte du terrain dans tout l’écran, position qui bouge, bandeau et panneau visibles.
- Preview navigateur : `web/phone.html?preview=map`.

## Statut

Corrigé dans les sources 1.8.14 — reconstruire le pack et relancer Arma complètement.
