# Erreurs au démarrage de la partie du 27/10

## Contexte

Journal `Arma3_x64_2026-10-27_13-15-43.rpt`, partie quittée à 13:31, pack 2.7.0 encore en mémoire. Le cercle blanc de la montre est traité à part.

## Symptôme

Trois erreurs au chargement, en plus de la montre :

- à l’arrivée en mission, le numéro de téléphone local plante ;
- l’écran Zeus qui copie les identifiants d’un opérateur plante ;
- la liste des tenues dans l’arsenal se redessine en erreur, plusieurs fois par seconde.

## Cause

- Numéro : les deux côtés d’un choix sont toujours calculés. Sans numéro principal, l’index vaut -1 et la lecture plante quand même.
- Zeus : un crochet ouvert dans le remplacement des retours à la ligne fermait l’expression trop tôt.
- Arsenal : une colonne ignorée était nommée avec un nom vide. Le jeu refuse une variable locale sans nom.

## Correctif

Le numéro n’est lu que s’il existe. Le retour à la ligne est préparé avant le remplacement. La colonne ignorée a un nom.

## Fichiers touchés

- `mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/core/fn_localCfg.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_zeusShowPlayerAtak.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_arsenalOverlayRefresh.sqf`

## Vérification

Les trois lignes du journal correspondent aux expressions corrigées. À confirmer en jeu après le pack 2.8.1 : arrivée en mission sans erreur de numéro, copie des identifiants depuis Zeus, liste des tenues dans l’arsenal.

## Statut

Corrigé dans les sources. Visible après reconstruction du pack.
