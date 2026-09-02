# Bug — Carte ATAK : slot de groupe à la place de l’indicatif

## Contexte

1er septembre 2026. Sur la carte du téléphone ATAK, le bandeau d’unité suivie affiche le bon indicatif (TA1). Le symbole sur la carte affiche encore le numéro de groupe Arma (01).

## Symptôme

Le bandeau indique INDICATIF TA1. Le rectangle bleu à côté du joueur est libellé 01. Les autres opérateurs montrent le nom de leur groupe Arma (Alpha, Hangar…) au lieu de leur indicatif.

## Cause

Le suivi d’effectif de la carte reprend le nom de groupe Arma et le numéro dans le groupe. L’indicatif Athena est déjà connu (bandeau, Effectifs) mais n’était pas recopié sur le symbole.

## Correctif

Les libellés de la carte reprennent l’indicatif de la fiche. Un numéro de groupe ou un nom d’éditeur n’est plus affiché quand l’indicatif est connu.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_bftUnitLabel.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_relabelBft.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_installBftLabels.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updateMapHud.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_ATAK_Check_Layout.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/XEH_postInitClient.sqf`

## Vérification

Tests d’assets + UPDATE 355. Rebuild du pack. En jeu : ouvrir la carte du téléphone. Votre symbole doit afficher TA1 (ou votre indicatif), comme le bandeau. Pas 01.

## Statut

corrigé (pack à recharger, quitter Arma complètement)
