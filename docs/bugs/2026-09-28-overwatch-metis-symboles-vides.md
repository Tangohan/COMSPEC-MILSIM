# Overwatch — kit Metis sans symboles (cases vides)

## Contexte

Tiroir Marqueur du théâtre, Overwatch Beta. Kits OTAN / Arma 3 / Metis / MarkersPlus.

## Symptôme

Avec le kit **Metis** sélectionné, chaque case du sélecteur montrait uniquement le libellé (Aéroporté, Blindé…) sur un carré noir, sans pictogramme. La ligne de liste « Ami - Aéroporté » était aussi sans vignette.

## Cause

L’index bibliothèque pointe vers des PNG sous `z/mts/addons/markers/...`, mais ce pack n’est pas déployé dans `public/assets/markers/arma/`.  
`markerThumb` préférait toujours cette URL PNG pour Metis, sans retomber sur le glyphe APP-6 déjà disponible via `ArmaMapMarkers.buildIconSpec` (Metis est décodé comme OTAN).

## Correctif

- Kit Metis : vignettes via glyphe APP-6 (cadre d’affiliation + rôle), comme OTAN.
- Kit MarkersPlus : conserve les PNG locaux.
- Documentation marqueurs : même repli SVG pour Metis.
- Rôles Metis aéroporté / aviation mieux mappés vers le pictogramme.

## Fichiers touchés

- `public/assets/js/atak-overwatch-ops.js`
- `public/assets/js/arma-marker-catalog.js`
- `views/documentation/site/marqueurs.php`
- `public/assets/markers/arma/README.md`
- `app/Support/DevDispatchCatalog.php` (UPDATE #00724)
- tests associés

## Vérification

- Recharge Overwatch Beta (Ctrl+F5) → Marqueur → Metis : cadres colorés visibles.
- MarkersPlus inchangé (PNG).

## Statut

Corrigé (client). Optionnel plus tard : convertir et déposer les PNG Metis sous `z/mts/` pour le rendu exact du pack jeu.
