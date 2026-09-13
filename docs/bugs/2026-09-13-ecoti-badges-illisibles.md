# Affichage situation JVN — badges illisibles (texte doublé)

## Contexte

Affichage situation sous jumelles de vision nocturne (ECOTI). Capture utilisateur : pastilles marqueurs/alliés illisibles.

## Symptôme

Sous JVN, les libellés apparaissent en double (fantôme décalé), avec de grosses plaques cyan. Quand plusieurs marqueurs sont proches, tout se superpose en bouillie.

## Cause

`fn_ecotiDrawBadge.sqf` dessinait quatre couches : halo, plaque, icône+texte (ombre contour), puis **une seconde passe texte**. Sous le post-process JVN, cela produisait un empâtement et un double texte. Aucun anti-chevauchement écran.

## Correctif

- Une pastille + **une seule** ligne de texte (ombre légère).
- Plus de plaques / halo.
- Si deux badges se croisent à l’écran : le plus lointain passe en pastille seule.
- Libellés tronqués si trop longs ; taille texte un peu réduite avec la distance.

## Fichiers touchés

- `fn_ecotiDrawBadge.sqf`
- `fn_ecotiDraw.sqf`
- `fn_ecotiDrawBuilding.sqf` (étiquette d’étage)

## Vérification

Pack Overwatch **1.5.63**. Quitter Arma. Affichage situation ON → JVN → zone avec plusieurs marqueurs : un libellé net par cluster, pastilles pour le surplus.

## Statut

corrigé
