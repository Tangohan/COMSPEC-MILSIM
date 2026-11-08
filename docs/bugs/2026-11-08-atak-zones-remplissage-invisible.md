# Remplissage des zones invisible

## Contexte
Lecture de `fn_zoneAction` « pal » et capture de la 4.0.0.

## Symptôme
Zones sans remplissage, contour « Repère » presque noir sur fond sombre : on croit qu'il n'y a qu'une étiquette.

## Cause
`private _fA` écrasait `_fa`, l'opacité du thème (même variable en SQF) : -1 par défaut, donc alpha négatif. La couleur du type « Repère » du thème OTAN est presque noire.

## Correctif
Variable renommée. « Repère » passe en clair sur fond sombre.

## Fichiers touchés
`fn_zoneAction.sqf`, `fn_zoneDraw.sqf`.

## Vérification
`tests/Unit/AtakZonesRoutesTraceTest.php`, `hemtt check`. Rien ne peut être testé en jeu ici.

## Statut
corrigé à vérifier en jeu
