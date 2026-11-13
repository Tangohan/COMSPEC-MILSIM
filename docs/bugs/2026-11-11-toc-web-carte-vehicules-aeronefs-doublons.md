# Poste TOC web : véhicules et aéronefs absents de la carte, marqueurs en simples points, doublons

## Contexte

Poste TOC web (`/atak/toc`), première mise en ligne (PR #1537). Retour de TTRD sur la carte et sur le panneau ORBAT.

## Symptôme

- Aucun véhicule ni aéronef sur la carte ; les marqueurs du jeu, quand il y en a, sont tous des petits points ; les zones (ellipse, rectangle) et les tracés sont absents ; les points d'itinéraire ne s'affichent pas.
- Étiquettes petites et grises, « PERDU » écrit dans le libellé.
- ORBAT : chaque groupe est listé deux fois (en-tête du groupe puis le membre du même nom) et l'en-tête de colonnes « État » est répété ; un même joueur apparaît sous plusieurs indicatifs.

## Cause

- `toc-layers.js` ne demandait ni `/api/atak/vehicles` ni `/api/atak/air-assets` : seul `/api/atak/units` était lu, et une unité embarquée était dessinée en infanterie (`unitType: lead.vehicle ? 'motorized' : 'infantry'`).
- Marqueurs : tous dessinés avec `icon: 'dot'` quel que soit leur type ; `shape` (ELLIPSE, RECTANGLE, POLYLINE) ignoré ; une position écrite en texte (`"[x,y,0]"`) était rejetée.
- Calque « Itinéraires et points de passage » masqué au départ (`on: false`).
- `atak_units` garde une ligne par indicatif : un joueur qui change d'indicatif, de groupe ou de session laisse ses anciennes lignes (hors liaison), toutes renvoyées par `/api/atak/units`. Le panneau ORBAT dessinait un tableau par groupe.

## Correctif

- `/api/atak/units`, `/api/atak/vehicles` et `/api/atak/air-assets` acceptent `toc=1` : une ligne par identifiant stable (compte Steam, sinon indicatif), genre du symbole, âge (`App\Services\AtakToc\TocTracks`, après le brouillage). La console fusionne les trois sources sans doublon (aéronef > véhicule > nœud du même indicatif).
- Symboles OTAN par genre (`TocSymbols`), marqueurs Arma traduits en icônes du poste, zones avec leur pinceau, tracés, points d'itinéraire numérotés ; calques affichés au départ.
- Plaques lisibles placées sans chevauchement, état ancien ou perdu montré par le style et l'âge ; ORBAT à un seul en-tête, groupe d'un membre sur une ligne.

## Fichiers touchés

- `app/Services/AtakToc/TocSymbols.php`, `app/Services/AtakToc/TocTracks.php`, `app/Controllers/Api/AtakApiController.php`
- `public/assets/js/toc/toc-tracks.js`, `toc-overlay.js`, `toc-layers.js`, `toc-console.js`, `toc-console-ui.js`, `public/assets/css/atak-toc.css`, `views/atak/toc/console.php`

## Vérification

- `tests/Unit/AtakTocTracksTest.php`, `tests/Frontend/toc-tracks.test.cjs`.
- Rendu hors site avec données d'essai (12 unités, 3 véhicules, 4 aéronefs, 18 marqueurs, 7 points) à 2560 × 1300 et 1366 × 768.

## Statut

corrigé à vérifier avec une partie en liaison
