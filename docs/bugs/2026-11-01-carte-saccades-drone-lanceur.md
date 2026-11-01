# Carte qui saccade, app Drone illisible, lanceur avec un bloc « groupe » en trop

## Contexte

Retours joueur (ATAK natif 2.9.20, S7) : freezes et saccades avec l'app Carte ouverte (plus rien en téléphone réduit
sans carte) ; app Drone, colonne de gauche aux textes superposés ; lanceur, filtre « Support and fires » avec
« Choisir un groupe / + Nouveau groupe » affiché.

## Symptôme

- Carte en mouvement : arrêt puis saut toutes les quelques secondes ; micro-gels sur les grosses missions.
- Drone : aides et boutons empilés les uns sur les autres.
- Lanceur : feuille « Au groupe… » restée ouverte d'une visite à l'autre, quel que soit le filtre.

## Cause

- `fn_statusUpdate` : recentrage complet tous les 30 m même quand le suivi image par image tenait (coupe le suivi 0,6 s).
- `fn_mapOnDraw` : toutes les unités et repères préparés à chaque image, même hors vue ; listes de noms de marqueurs
  et de contacts refaites chaque seconde ; caches trop petits vidés en boucle.
- `fn_formRender` : `ctrlTextHeight` sous-estimé dans la colonne ; boutons forcés sur une seule rangée.
- `fn_launcherRender` : mode « pick » sans bouton pour fermer et état gardé ; filtre GROUPES remis à TOUT.

## Correctif

Recentrage de secours seulement si l'écart dépasse 30 m ; unités et repères hors vue écartés ; listes refaites sur
changement ; caches agrandis ; échéances de `fn_localDataRefresh` étalées. Hauteur plancher et retour à la ligne des
boutons dans les formulaires ; liste des drones plafonnée à 26 %. Bouton « Annuler », changement de filtre qui ferme
la feuille, filtre GROUPES réparé.

## Fichiers touchés

`mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/` : ui/fn_statusUpdate, map/fn_mapOnDraw, map/fn_mapMarkerText,
map/fn_mapLabels, map/fn_markerAutoName, map/fn_mapTextW, map/fn_localDataRefresh, ui/fn_formRender,
drone/fn_pageDrone, ui/fn_launcherRender

## Vérification

En jeu (S7) : carte portée puis « Suivre », marcher et rouler ; mission chargée, comparer les lignes `[PERF]` du RPT ;
app Drone appairée en anglais (VOL, TÂCHES, FLOTTE) ; lanceur « Au groupe… » → Annuler, filtre GROUPES.

## Statut

corrigé à vérifier en jeu (2.9.21)
