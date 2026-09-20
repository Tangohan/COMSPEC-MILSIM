# Découpage d’étage — surlignage jamais affiché

Date : 2026-09-20  
Statut : corrigé (Overwatch 1.6.3)

## Contexte

Sous affichage situation, un bâtiment désigné peut être découpé à un étage : la silhouette s’arrête au plafond choisi, avec des séparateurs entre les dalles. Une croix devait marquer l’étage regardé.

## Symptôme

Le joueur voyait le découpage et les séparateurs, mais aucune croix n’indiquait « c’est cet étage-ci que tu regardes ».

## Cause

La boucle des dalles s’arrêtait à l’indice de l’étage choisi (`_sel`), alors que le surlignage testait l’indice suivant (`_sel + 1`). Cette valeur n’était jamais atteinte, donc le test restait toujours faux. Le réglage découpage était aussi mémorisé deux fois (copie manuelle et options du jeu), ce qui pouvait les faire diverger.

## Correctif

La boucle va jusqu’au plafond de coupe. La croix se dessine sur cette dernière dalle. Le réglage découpage n’est plus mémorisé qu’une fois, via les options du jeu ; l’ancienne copie est lue une dernière fois puis oubliée. Les noms de repères de bâtiment sont uniques pour la session.

## Fichiers touchés

- `connect/functions/fn_ecotiDrawBuilding.sqf`
- `connect/functions/fn_ecotiApplyCutawaySetting.sqf`
- `connect/functions/fn_ecotiBuildingMarkerName.sqf`
- `connect/functions/fn_ecotiMarkBuilding.sqf`
- `connect/functions/fn_ecotiCutAtLook.sqf`
- `connect/XEH_preInit.sqf`
- `connect/XEH_postInit.sqf`
- `atak_athena/functions/fn_athena_updateSettings.sqf`
- `connect/config.cpp`

## Vérification

Tests d’assets : surlignage `_i == _lastFloor`, plus de copie manuelle du réglage, noms de repères uniques. En jeu : Overwatch 1.6.3, affichage situation et découpage actifs, désigner un bâtiment, changer d’étage — une croix doit apparaître sur la dalle de l’étage affiché. Le même choix doit rester après un redémarrage, qu’il ait été fait depuis le téléphone ou depuis les options.

## Statut

corrigé
