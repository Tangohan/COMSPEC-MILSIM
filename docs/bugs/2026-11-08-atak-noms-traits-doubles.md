# Noms et traits en double sur les routes et les axes

## Contexte
Capture de la 4.0.0 : MSR COBRA et MSR VIPER écrits deux fois, trait double.

## Symptôme
Une plaque blanche et, dessous, le même nom en bleu illisible ; deux traits sur la même route.

## Cause
Le téléphone dessinait le tracé (`fn_zoneDraw`, `fn_routeDraw`) puis repassait les marqueurs natifs du même tracé (`fn_mapGameMarkers`, `fn_mapMarkerText`). Deux demandes au même endroit créaient aussi deux plans aux axes superposés.

## Correctif
Marqueurs réservés sautés sur le téléphone, partout où ils étaient lus. Axes redemandés au même endroit : même plan recalculé. Axe déjà tracé par un autre plan écarté. Nom posé sur le trait, plaque sombre, texte blanc, liseré à la couleur de l'axe.

## Fichiers touchés
`fn_markerOwned.sqf`, `fn_mapGameMarkers.sqf`, `fn_mapMarkerText.sqf`, `fn_mapOnDraw.sqf`, `fn_markerAt.sqf`, `fn_zoneDraw.sqf`, `fn_zoneGen.sqf`, `fn_planGfx.sqf`.

## Vérification
`tests/Unit/AtakZonesRoutesTraceTest.php`, `hemtt check`. Rien ne peut être testé en jeu ici.

## Statut
corrigé à vérifier en jeu
