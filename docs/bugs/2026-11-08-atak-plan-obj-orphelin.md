# Un plan « OBJ » à chaque tracé, réduit à une étiquette

## Contexte
Captures du 2026-11-08 : plaques OBJ CHARLIE et OBJ DELTA seules sur la carte, bandeau « calcul en cours ».

## Symptôme
Chaque tracé lancé depuis le clic droit laissait un plan nommé OBJ, sans forme visible.

## Cause
Aucun mode n'était resté actif. `ctxGen` créait toujours un plan et `new` le nommait « OBJ + alphabet OTAN ». Pour ZG_MAGO, `fn_zoneSnap` s'arrêtait ensuite sur une erreur (note `atak-zone-aimant-casse-variable`) : le plan existait, l'outil ne s'ouvrait pas. Le nom du plan se dessinait même sans contenu, et un calcul sans résultat gardait le plan. Son envoi différé pouvait partir avant la fin du calcul.

## Correctif
Nom neutre selon la demande (PLAN, TRAITS, AXES, GRG, ZONES), OBJ seulement par NOM AUTO. Plan automatique resté vide retiré (`pruneAuto`), avec message. Étiquette du plan seulement s'il a des zones ou dans son app. Rien n'est envoyé pendant un calcul ni sans contenu. Boutons de nettoyage dans MES PLANS, deux clics puis ANNULER. État de l'interface remis à zéro au début de mission.

## Fichiers touchés
`fn_zoneAction.sqf`, `fn_zoneDraw.sqf`, `fn_pageZones.sqf`, `XEH_postInitClient.sqf`, `fn_zoneGen.sqf`.

## Vérification
`tests/Unit/AtakZonesRoutesTraceTest.php`, `hemtt check`. Rien ne peut être testé en jeu ici.

## Statut
corrigé à vérifier en jeu
