# Relief 3D — boîtes grises uniformes sur photo aérienne

## Contexte

Overwatch Beta, mode Relief 3D (MapLibre + deck.gl). Les volumes du relevé se dressent sur le fond photo.

## Symptôme

Les constructions apparaissaient toutes en gris uniforme, sans ombrage lisible, avec un « double village » (photo des toits + prismes opaques). Des dalles fines (murs / clôtures mal typées) se dressaient comme des immeubles. Certains volumes flottaient ou s’enfonçaient. Les arêtes étaient crénelées ; le relief montagneux montrait des pics artificiels.

## Cause

- `PolygonLayer` avec une seule teinte et un matériau peu contrasté.
- Pas de variété par famille de construction ni de toit distinct.
- Opacité fixe élevée même en très gros plan.
- Emprises longues et fines non filtrées ; `base_z` pris au centre seulement.
- Canvas MapLibre sans antialiasing / DPR adapté ; heightmap RGB sans lissage.

## Correctif

- Éclairage directionnel + ambiant (deck.gl), matériau Lambert-like, liseré d’arête.
- Palette mur/toit par famille (`facade`) + variation seedée sur l’ID.
- Transparence progressive au zoom élevé ; plaque de toit sombre.
- Filtrage des dalles fines (ratio) côté sanitize + client ; `base_z` = point le plus bas sous l’emprise (bake schéma 4).
- Antialiasing + `pixelRatio` ; exaggeration par défaut 2.0× ; lissage 3×3 du heightmap RGB (`rgb-v2`).
- Halo lisible autour des contacts.

## Fichiers touchés

- `public/assets/js/overwatch-gl/OverwatchGlLayers.js`
- `public/assets/js/overwatch-gl/OverwatchGlMap.js`
- `views/atak-overwatch-beta.php`
- `app/Services/Tactical/AtakSceneBounds.php`
- `app/Services/Tactical/AtakSceneKind.php`
- `app/Services/Tactical/AtakSceneMeshBake.php`
- `app/Services/Tactical/AtakTerrainRgb.php`
- tests unitaires associés
- `app/Support/DevDispatchCatalog.php` (UPDATE #00720)

## Vérification

- Tests asset / bounds / kind.
- En session : Ctrl+F5, Relief 3D sur un village — volumes nuancés, toits plus sombres, photo lisible en gros plan, dalles fines basses, contacts avec halo.

## Statut

Corrigé (client + bake ; le mesh se reconstruit au prochain accès schéma 4).
