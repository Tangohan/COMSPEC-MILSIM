# Affichage situation : impossible d’annuler un pointage de bâtiment

## Contexte

19 septembre 2026. Jumelles de vision nocturne, affichage situation. Un opérateur peut désigner un bâtiment (silhouette, badge, distance) mais n’avait aucun moyen d’annuler ce pointage, contrairement à l’itinéraire (Effacer).

## Symptôme

Le bâtiment reste marqué jusqu’à la fin de la mission.

## Cause

L’affichage situation proposait Désigner ce bâtiment, pas d’annulation.

## Correctif

Action **Annuler le pointage de bâtiment**, visible seulement si un bâtiment est déjà désigné. La silhouette, le badge et le repère local disparaissent.

Pack : Overwatch **1.5.96**. Relancer Arma complètement.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_ecotiClearBuilding.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_initACE.sqf`

## Vérification

1. Quitter Arma. Recharger Overwatch 1.5.96.
2. Désigner un bâtiment sous affichage situation.
3. Annuler le pointage de bâtiment : la silhouette disparaît.

## Statut

corrigé
