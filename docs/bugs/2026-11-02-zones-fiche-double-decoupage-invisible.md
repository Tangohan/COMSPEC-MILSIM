# App Zones : fiche de zone dessinée en double, découpage invisible, pas de bouton SIGNALER

## Contexte
ATAK natif 2.9.36, app Zones. Plan « OBJ ALPHA » sur Athira, zone de travail rectangle ~303 × 251 m, grille 200 m
calée sur le carroyage ; fiche « ZONE B-0 · OBJ BRAVO » ouverte depuis la carte.

## Symptôme
- Tous les textes de la fiche apparaissent deux fois, décalés d'environ 16 px, avec des valeurs différentes (distance,
  état « à l'instant ») : deux rendus d'époques différentes empilés.
- « On ne voit pas le découpage » : seul le rectangle pointillé de la zone de travail et le nom du plan se voient.
- Formulaire SIGNALER sans bouton d'envoi visible ; petit carré pâle sans légende devant « État de la zone ».

## Cause
- Fiche : la page complète passait par la suppression différée de `fn_pageClear` (contrôles masqués, supprimés à
  l'image suivante) ; un second rendu de la même image ou un rendu sur l'autre écran du téléphone laissait l'ancien
  formulaire visible. La cause exacte du second rendu n'a pas été reproduite (aucune erreur dans le RPT du
  02/11) ; la page ne compte plus sur ce délai.
- Découpage : le type de zone par défaut (« Repère ») était blanc, contour 1 px et remplissage à 12 % : sur la carte
  claire, les cases étaient pratiquement invisibles. Aucun aperçu avant GÉNÉRER, et une grille vide ne donnait aucun
  message.
- SIGNALER : bouton en bas d'un long formulaire défilant, hors écran ; le carré venait de la pastille de numéro de la
  ligne `lineseg`, dessinée même sans numéro.

## Correctif
- `fn_pageZones` (refait) : les contrôles de l'ancien rendu de l'app (panneau, liste, formulaire) sont supprimés tout de
  suite à chaque rendu ; la fiche est dans le panneau de la vue du plan, refaite seule, avec une barre d'actions fixe
  (SIGNALER UN ENNEMI, SIGNALER, EFFACER ; Entrée dans Effectif).
- `fn_zoneAction` / `fn_zoneDraw` : thèmes de couleurs (OTAN, contraste élevé, vision nocturne), « Repère » sombre dans le
  thème OTAN ; aperçu du découpage (`preview`) en traits fins ; `fn_zoneGen` : message « Zone de travail trop petite
  pour des cases de %1 m : choisissez %2 m », journal `[ZONES]`, garde-fou si le calcul meurt.
- `fn_formRender` : pas de pastille pour une ligne `lineseg` sans numéro.

## Fichiers touchés
`functions/zones/fn_pageZones.sqf`, `fn_zoneAction.sqf`, `fn_zoneDraw.sqf`, `fn_zoneGen.sqf`, `fn_zoneMap.sqf`,
`functions/ui/fn_formRender.sqf`.

## Vérification
En jeu : même plan (rectangle ~300 × 250 m, grille 200 m) : aperçu jaune avant GÉNÉRER, cases visibles après ; fiche
ouverte depuis la carte et depuis la liste : un seul jeu de textes ; SIGNALER visible et fonctionnel.

## Statut
corrigé à vérifier en jeu
