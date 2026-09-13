# ATAK — Zoom au-delà des tuiles natives et cases blanches

**Date :** 2026-09-13  
**Statut :** corrigé

## Contexte

Sur la carte du poste (TACMAP / ATAK web), le zoom maximal était plafonné au zoom natif des tuiles du théâtre. Au-delà, ou en cas d’échec de chargement CDN, des cases blanches ou masquées apparaissaient.

## Symptôme

- Impossible de zoomer finement au-delà du niveau fourni par le fond de carte.
- Tuiles manquantes affichées en blanc (ou entièrement masquées), sans tentative de comblement.

## Cause

- `maxZoom` Leaflet était aligné sur le zoom natif des tuiles (`maxZoom` config = 6 typiquement), sans `maxNativeZoom` + suréchantillonnage.
- Sur `tileerror`, le fond se contentait de masquer l’image (`visibility: hidden`) sans retry ni repli sur la tuile parent.

## Correctif

- `maxNativeZoom` dérivé de la config (ou `maxZoom` historique) ; `maxZoom = maxNativeZoom + 2` pour autoriser un zoom fin avec étirement Leaflet.
- Sur erreur de tuile : court retry de la même URL, puis comblement par découpe de la tuile parent (z−1) vers un canvas / data URL — sans masquer si le comblement réussit.
- Bouton discret « Réparer le fond » (`ATAKMap.repairBaseTiles()` → `redraw()`).
- Alignement mobile (`atak-mobile.js`) et config Altis (`altis.js`).

## Fichiers touchés

- `public/assets/js/atak-map.js`
- `public/assets/js/maps/altis.js`
- `public/assets/js/atak-mobile/atak-mobile.js`
- `views/atak.php`
- `tests/Unit/AtakMapTileZoomAssetTest.php`

## Vérification

- Présence des chaînes `maxNativeZoom`, `fillTileFromParent` / `repairBaseTiles`, « Réparer le fond » dans les assets.
- Zoom au-delà du niveau natif : fond étiré plutôt que cases vides ; erreur CDN comblée par parent quand disponible.

## Suite (non livré ici)

- Lot 4 Grad / MEH : reporté (tuiles théâtre supplémentaires).
- Empreintes 2D des bâtiments en vue plate : limitation restante (calque scène 3D réservé à la vue inclinée).
