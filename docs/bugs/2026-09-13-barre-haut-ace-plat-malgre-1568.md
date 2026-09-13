# Barre NOK en haut + menu ACE à plat malgré 1.5.68

## Contexte

13 septembre 2026. Pack annoncé 1.5.68 / Athena 1.0.115 déployé Workshop + FN. En jeu : barre OK/NOK encore en haut de la carte, menu ACE COMSPEC Athena encore en longue liste.

## Symptôme

- Barre de liaison collée sous la barre d’état (haut), pas en bas de la carte.
- ACE self-interact : ~25 actions à plat sous COMSPEC Athena.
- Journal 20:29 : `connect v1.5.68` mais `Installation menus ACE SelfActions` **sans** `(arbre v3)` — contrairement à la session 14:12 (1.5.66) qui avait l’arbre v3.

## Cause

1. **Barre** : dans `fn_athena_updateLinkStrip`, variable `_hy` non définie + recherche carte sur IDC 1201/1202 seulement. Sur l’interface tablette V2 les IDC sont souvent à **+17000** → carte introuvable → repli haut.
2. **ACE** : un pack 1.5.68 incomplet (merge) a tourné sans arbre v3 ; les actions de **classe** ACE survivent d’une mission à l’autre dans la même session Arma, et le purge ne connaissait que la liste `missionNamespace` (vide au démarrage de mission).

## Correctif

- Barre : IDC V2 (+17000), repli bas sans `_hy`, tiroir apps 17000+4660.
- ACE : structure **v4**, purge explicite des feuilles legacy sous `COMSPEC_Main`, log `arbre v4`.
- Pack **1.5.69** / Athena **1.0.116**.

## Fichiers touchés

- `fn_athena_updateLinkStrip.sqf`, `fn_athena_updateMapHud.sqf`
- `fn_initACE.sqf`
- `connect/config.cpp`, `atak_athena/config.cpp`

## Vérification

1. Quitter Arma complètement (pas lobby).
2. Journal : `connect v1.5.69` + `Installation menus ACE SelfActions (arbre v4)`.
3. ACE → COMSPEC Athena → rubriques seulement.
4. Téléphone → barre OK/NOK en bas de la carte.

## Statut

corrigé — pack **1.5.69** / Athena **1.0.116** rebuild + déployé Workshop + FN
