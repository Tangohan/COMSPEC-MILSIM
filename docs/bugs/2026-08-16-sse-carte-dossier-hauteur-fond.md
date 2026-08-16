# Carte tactique dossier SSE — hauteur viewport + fond de carte

## Contexte
Panneau « Carte tactique » d’un dossier SSE (`#tacmap`) : pings, mémorisation de vue, capture.

## Symptôme
1. La carte restait coincée dans un bandeau bas (~380px) avec un grand vide sombre en dessous, au lieu d’occuper la hauteur visible restante.
2. Impossible de changer le fond de carte (uniquement le fond sombre fixe).

## Cause
- CSS `.sse-tacmap { height: 380px; }` sans remplissage du viewport.
- Une seule couche tuiles CARTO sombre, sans sélecteur.

## Correctif
- Layout flex/grid : la carte occupe `calc(100dvh - …)` et se redimensionne (`invalidateSize` + `ResizeObserver`).
- Sélecteur « Fond de carte » (Sombre / Clair / Plan / Relief) avec les tuiles déjà utilisées ailleurs (CARTO, OSM, OpenTopoMap).
- Préférence mémorisée (navigateur + `snapshot_meta.basemap` à l’enregistrement de la vue).

## Fichiers touchés
- `views/atak/sse/case_show.php`
- `public/assets/css/sse_portal.css`
- `public/assets/js/sse-case-map.js`
- `app/Controllers/Web/SsePortalController.php`

## Vérification
1. Ouvrir un dossier → section Carte tactique : la carte remplit l’espace visible en bas.
2. Changer le fond (Sombre / Clair / Plan / Relief) : les tuiles basculent immédiatement.
3. Recharger la page : le fond choisi est rétabli.
4. Capturer / mémoriser la vue : le comportement existant (pings, calque ATAK) reste intact.

## Statut
corrigé
