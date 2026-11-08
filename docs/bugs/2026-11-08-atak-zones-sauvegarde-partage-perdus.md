# Plans de zones ni sauvegardés ni partagés

## Contexte
RPT du 2026-11-08 19:19 : `fn_zoneAction.sqf` ligne 410, « Error keys : Type Chaîne, HashMap attendu », puis rappel interrompu autour de `COMSPEC_ATAK_ZnSaveTok`.

## Symptôme
Plans perdus à la fin de la partie, jamais reçus par les autres joueurs, ANNULER sans effet pendant l'édition. Les marqueurs natifs partaient quand même : les autres ne voyaient qu'eux.

## Cause
`[_w, (keys _w) apply {...}] select (...)` : SQF évalue les deux éléments du tableau, `keys` échouait sur chaque valeur simple du style. `pack` s'arrêtait pour tout plan portant un style, donc tous. Perte de données : oui, les plans créés depuis cette régression n'ont pas été écrits au profil et ne sont pas récupérables ; ce qui s'y trouve déjà reste lisible, et une valeur illisible est écartée par `fn_zoneUnpack`.

## Correctif
`if / else` à la place du `select`.

## Fichiers touchés
`fn_zoneAction.sqf`, `fn_zoneUnpack.sqf`.

## Vérification
`tests/Unit/AtakZonesRoutesTraceTest.php`, `hemtt check`. Rien ne peut être testé en jeu ici.

## Statut
corrigé à vérifier en jeu
