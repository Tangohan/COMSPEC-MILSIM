# Marqueurs Tacmap trop gros (enveloppe / Leaflet)

## Contexte

Sur la Tacmap ATAK / Athena, un repère (icône enveloppe rouge notamment) occupait une surface trop large et masquait routes, courbes de niveau et bâtiments.

## Symptôme

Marqueurs Leaflet d’environ 88 × 30 px (libellé permanent sous le symbole), donc bien trop gros par rapport au fond topographique, surtout en vue tactique rapprochée.

## Cause

`iconSize` Leaflet et les libellés permanents étaient définis localement dans plusieurs fichiers (`arma-map-markers.js`, `nato-sidc-icons.js`, `atak-map.js`, calques SSE). Réduire le CSS ne suffisait pas : la boîte Leaflet restait immense.

## Correctif

Système central `ATAKMarkerSizes` (10 / 14 / 17 / 19 / 22 px), symboles seuls sur la carte, renseignement au survol / clic, ancrages Leaflet recalculés, léger facteur CSS selon le zoom sans changer les coordonnées.

## Fichiers touchés

- `public/assets/js/atak-marker-sizes.js`
- `public/assets/js/arma-map-markers.js`
- `public/assets/js/nato-sidc-icons.js`
- `public/assets/js/atak-map.js`
- `public/assets/js/atak-sse-layers.js`
- `public/assets/js/atak-map-shapes.js`
- `public/assets/js/atak-unit-popup.js`
- `public/assets/css/atak.css`

## Vérification

Contrôle du code : plus de `iconSize` 72–96 px sur la Tacmap Athena. Les anciennes tailles restent uniquement sur d’autres cartes (JNET / SSE dossier), hors ATAK web.

## Statut

Corrigé
