# File d’ordres qui s’allonge à chaque lecture

**Date :** 2026-09-19  
**Statut :** corrigé (Overwatch 1.5.90)

## Contexte

Journal `COMSPEC_2026-09-19_111758_173.log`. Pendant « Ordres (réception) », le bandeau montrait 3 ordres dont 2 « nouveaux » à chaque cycle, et une mémoire qui grimpe : 11 → 14 → 17 → 20 → 23 → 26 → 29.

Le plafond à 40 (1.5.89) limitait l’affichage, pas la croissance.

## Symptôme

À chaque lecture des ordres du poste, le téléphone en empile de nouveaux au lieu de remplacer ceux déjà reçus. Le jeu finit par se fermer.

## Cause

Chaque lecture ajoutait les ordres du poste à ceux déjà en mémoire, même s’il s’agissait des mêmes. Les identifiants ne recouvraient pas toujours ceux déjà rangés, donc la file s’allongeait.

## Correctif

Les ordres du poste sont une photo de la dernière lecture : ceux déjà reçus du poste sont remplacés, pas empilés. Les ordres émis depuis le téléphone restent. Un identifiant déjà connu n’est plus enregistré deux fois.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_pollOrders.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_issueOrder.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp`

## Vérification

Quitter Arma complètement. Recharger Overwatch 1.5.90. Le bandeau doit afficher 1.5.90. Relancer le dépannage avec le téléphone. Pendant « Ordres (réception) », le compte « mémoire » doit rester stable d’une lecture à l’autre (plus de +3 à chaque cycle).

## Statut

Corrigé.
