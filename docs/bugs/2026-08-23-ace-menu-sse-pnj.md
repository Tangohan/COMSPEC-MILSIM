# Menu ACE SSE absent sur les PNJ et joueurs

## Contexte

En jeu, le menu ACE « SSE » n’apparaissait plus sur les personnes (PNJ, autres joueurs, corps). Le journal technique montrait bien le bus SSE, la passerelle plaques ACE (sync only) et un site généré — mais le `makeSearchable` du cluster ne concernait qu’un **véhicule**.

## Symptôme

Molette ACE sur une personne : pas d’entrée SSE. Sur un objet de site généré, le menu pouvait encore apparaître. La fiche identité pouvait quand même être ouverte via le terminal personnel.

## Cause

Les actions ACE personnes n’étaient posées **que sur les entités déjà marquées SSE** (`setData` / `makeSearchable`). Overwatch ne greffe plus de repli sur `CAManBase` dès que l’addon interaction SSE est chargé. Un PNJ « normal » n’avait donc jamais la racine ACE.

Les plaques ACE en « sync only » (sans wrap `getDogtagData`) n’y sont pour rien : c’est voulu, pour éviter un débordement de pile.

## Correctif

Racine SSE enregistrée une fois sur la classe `CAManBase`. Condition légère : toute personne à portée, sauf soi-même. Véhicules / objets restent opt-in. Pas de génération dans la condition (toujours au clic).

## Fichiers touchés

- `mod/@COMSPEC_SSE/addons/interaction/functions/fn_initACE.sqf`
- `mod/@COMSPEC_SSE/addons/interaction/functions/fn_canInspect.sqf`
- `mod/@COMSPEC_SSE/addons/interaction/functions/fn_installEntityAceMenus.sqf`
- `mod/@COMSPEC_SSE/addons/main/script_mod.hpp` (0.7.13)

## Vérification

Contrôle du code. À retester en mission : molette ACE sur un PNJ et un autre joueur → nœud SSE, sans menu sur chaque véhicule de la carte.

## Statut

Corrigé (sources) — rebuild / rechargement de `@COMSPEC_SSE` requis
