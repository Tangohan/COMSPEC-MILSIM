# Suppressions incomplètes et tracés qui reviennent

## Contexte
Demande joueur : pouvoir tout supprimer, depuis la liste et la carte, sans que rien ne revienne.

## Symptôme
Axes, traits et étapes non supprimables sur la carte. Plan reçu retiré qui revenait à la mise à jour suivante. Suppression sur le web sans effet en jeu. Route rendue privée laissant ses marqueurs publics.

## Cause
Pas de pierre tombale pour les plans, pas de contrôle de révision. Plan retiré des autres seulement après les 8 s d'annulation. Le web masquait le marqueur sans prévenir le jeu. Rien ne reliait un marqueur à son élément.

## Correctif
Clic droit : axe, trait, point de contrôle, étape, plan sans zones, avec CONFIRMER puis ANNULER. Croix sur chaque ligne de MES PLANS et REÇUS. Pierres tombales et révisions. Retrait immédiat partout. Marqueur retiré sur le web ou la carte d'Arma : l'élément suit (`docs/ATAK_TRACES_COUVERTURE.md`).

## Fichiers touchés
`fn_zoneAction.sqf`, `fn_routeAction.sqf`, `fn_markerOwned.sqf`, `fn_mapCtxMenu.sqf`, `fn_pageZones.sqf`, `fn_markerWebSweep.sqf`, `fn_pollMarkers.sqf`, `AtakApiController.php`, `AtakDataRepository.php`.

## Vérification
`tests/Unit/AtakZonesRoutesTraceTest.php`, `hemtt check`. Rien ne peut être testé en jeu ici.

## Statut
corrigé à vérifier en jeu
