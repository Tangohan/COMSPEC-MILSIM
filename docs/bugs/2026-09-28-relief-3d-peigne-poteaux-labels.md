# Relief 3D — peigne de poteaux, labels illisibles, ligne verticale

## Contexte

Overwatch Beta, Relief 3D. Deuxième capture après les teintes/toits : artefacts le long des routes et lisibilité des contacts.

## Symptôme

- Dalles verticales régulièrement espacées le long de la chaussée (poteaux / glissières / segments de mur extrudés comme des bâtiments).
- Libellés blancs (« TA1 YA1 / Bravo ») illisibles sur volumes gris, superposés.
- Ligne sombre verticale traversant tout le cadre.
- Volumes lisses décalés de l’empreinte photo ; petits volumes lointains en bruit.

## Cause

- Classification morphologique absente : emprises fines + hauteur élevée restaient des bâtiments ; `MIN_EDGE` gonflait les poteaux en boîtes 2×2.
- Obstacles longs (murs / power) extrudés en hauteur → plan vertical qui coupe la vue.
- TextLayer sans fond opaque ni declutter, avec test de profondeur implicite / superposition.
- Pas de LOD écran ni d’ombre de contact.

## Correctif

- `AtakSceneBounds::shapeClass` : pole / ribbon / panel / building ; démotion vers obstacles (bake schéma 5).
- Client : `ColumnLayer` pour poteaux, rubans bas, `PathLayer` pour longs obstacles.
- Labels : fond sombre + contour, trait de liaison, declutter par cellule, détail seulement à fort zoom.
- Ombres de contact, toit avec débord, LOD taille à l’écran, désaturation hors focus.
- SQF : filtre anti-poteaux/rubans dans le relevé bâtiments.
- Séparateur 2D/3D explicite en mode côte à côte (évite une couture sombre ambiguë).

## Fichiers touchés

- `app/Services/Tactical/AtakSceneBounds.php`
- `app/Services/Tactical/AtakSceneMeshBake.php`
- `public/assets/js/overwatch-gl/OverwatchGlLayers.js`
- `mod/.../fn_sampleScene.sqf`
- `public/assets/css/atak-overwatch-beta.css`
- tests + `DevDispatchCatalog` UPDATE #00721

## Vérification

Ctrl+F5 → Relief 3D sur route bordée : plus de peigne. Indicatifs lisibles. Pas de dalle verticale géante. Contacts ancrés.

## Statut

Corrigé (rebuild mesh au prochain accès schéma 5 ; nouveau relevé jeu pour les futurs poteaux).
