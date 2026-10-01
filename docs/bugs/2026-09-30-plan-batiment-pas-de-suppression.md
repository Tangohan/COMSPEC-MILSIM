# Plan de bâtiment — pas de suppression

## Contexte

Panneau **Plan de bâtiment** sur Overwatch Beta : tracé mur / porte / fenêtre / brèche / pièce par étage.

## Symptôme

Une fois un trait posé, aucune action ne permettait de le retirer. Pas d’outil gomme, pas d’annulation, pas de vidage d’étage, pas de suppression de niveau.

## Cause

L’UI et `atak-overwatch-tacmap.js` ne proposaient que l’ajout de strokes dans `floors[floorKey]`. Aucun chemin de suppression.

## Correctif

- Outil **Effacer** (clic sur l’élément le plus proche)
- Boutons **Annuler** et **Vider l’étage**
- Clic droit = effacer l’élément le plus proche
- Ctrl+Z / Suppr / Retour arrière = annuler le dernier trait (hors champs de saisie)
- Croix sur les onglets d’étage (sauf RDC) pour retirer un niveau

## Fichiers touchés

- `views/atak-overwatch-beta.php`
- `public/assets/js/atak-overwatch-tacmap.js`
- `public/assets/css/atak-overwatch-beta.css`
- `app/Support/DevDispatchCatalog.php` (UPDATE #747)

## Vérification

Ouvrir Plan de bâtiment, tracer un mur, Effacer / Annuler / clic droit, Vider l’étage, ajouter un étage puis le retirer via ×.

## Statut

Corrigé (recharger Overwatch Beta : Ctrl+F5).
