# Coordonnées ATAK / Arma — Carte tactique

## Contexte

- **Arma 3** envoie les positions en **mètres** dans un repère cartésien monde (ex. Altis : origine au sud-ouest, `[x, y]` ou `[x, y, z]`).
- La **Tacmap COMSPEC** (Leaflet) est configurée en **CRS Simple** (ou MGRS dérivé) : les unités de la carte sont les mêmes que celles d’Arma (mètres). Aucune conversion n’est nécessaire pour afficher unités, pings, designator, etc.

## Quand une conversion est nécessaire

Si un jour le **fond de carte** est en **Lat/Long** (Google Maps, OSM classique, etc.), il faudra convertir les positions reçues d’Arma (mètres) en latitude/longitude **avant** d’afficher sur la carte.

- **Où faire la conversion** : soit dans le **backend** (Node ou PHP) avant d’envoyer au front, soit dans le **front** (JS) juste avant d’appeler `L.marker` / `setView`.
- **Fonction type** : `gridToLatLong(x, y, worldName)` → `[lat, lng]`. Les paramètres dépendent de la carte Arma (Altis, Tanoa, etc.) et de la projection utilisée (souvent une projection locale ou des facteurs CRS comme pour les tuiles BI).

## Altis (exemple)

Pour Altis, les tuiles Bohemia utilisent des facteurs approximatifs (ex. `factorx` / `factory` dans la config). Une conversion générique mètres → Lat/Long pour une carte géographique nécessite :

1. L’origine du monde Arma en Lat/Long (si définie).
2. L’échelle (mètres par degré ou facteurs de la CRS).

En l’état, le dashboard Athena utilise **uniquement** des cartes en coordonnées monde (CRS Simple), donc **aucune conversion n’est appliquée** : `pos_x` / `pos_y` sont utilisés tels quels comme `[lng, lat]` (avec `applyOffset` si configuré).

## Résumé

| Source        | Format        | Carte Tacmap actuelle | Action        |
|---------------|---------------|------------------------|---------------|
| Arma / DLL    | `pos_x`, `pos_y` (mètres) | CRS Simple (mètres) | Utiliser directement |
| Même stack    | idem          | Carte Lat/Long (hypothétique) | Ajouter `gridToLatLong` côté backend ou front |
