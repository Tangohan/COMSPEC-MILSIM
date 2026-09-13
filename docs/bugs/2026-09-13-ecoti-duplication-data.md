# Affichage situation JVN — duplication des données au même lieu

## Contexte

Sous JVN, sur un bâtiment désigné (ex. Ranger Headquarters), plusieurs libellés identiques ou quasi identiques s’empilaient.

## Symptôme

Au même endroit : « Nom · Xm » en double, une variante « Nom [152173] · Xm » aussi en double, plus « Étage 1/5 ».

## Cause

Plusieurs sources dessinaient le même lieu :
1. marqueurs carte proches (mission / web / ECOTI) ;
2. badge du bâtiment désigné ;
3. libellé d’étage du découpage ;
4. ombre de texte (`shadow` 1) lue comme un second texte sous le post-process JVN.

## Correctif

- Fusion spatiale des marqueurs (~16 m) : un seul badge, libellé le plus propre.
- Marqueurs du bâtiment désigné ignorés ; un seul libellé `Nom · ét. x/y`.
- Suffixes `[id]` retirés à l’affichage ; ombre texte à 0 sous JVN.
- Ré-enregistrement sûr du Draw3D (retrait de l’ancien EH).

## Fichiers touchés

- `fn_ecotiDraw.sqf`
- `fn_ecotiDrawBadge.sqf`
- `fn_ecotiDrawBuilding.sqf`
- `fn_ecotiInit.sqf`

## Vérification

Pack Overwatch **1.5.64**. Quitter Arma. JVN + bâtiment désigné + découpage : un seul libellé.

## Statut

corrigé
