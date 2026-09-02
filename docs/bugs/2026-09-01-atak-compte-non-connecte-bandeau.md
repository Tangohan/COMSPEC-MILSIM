# Bandeau « Compte non connecté » manquant sur la carte et Athena

## Contexte

Carte ATAK (cartouche indicatif / rôle / groupe) et tuile Athena, lorsque le compte n’est pas encore associé.

## Symptôme

Rien n’indiquait clairement que le compte n’était pas connecté. L’opérateur voyait l’indicatif et le rôle, sans savoir que rien n’était transmis au poste.

## Cause

Le bandeau d’identité de la carte ne distinguait pas la liaison de compte. La tuile Athena affichait un état de liaison peu lisible (« Hors liaison ») au lieu d’un message d’action.

## Correctif

- Petit bandeau ambre « Compte non connecté » au-dessus du cartouche unité sur la carte, uniquement sans compte associé.
- Même message en haut de la tuile Athena et dans la ligne Compte Athena (Poste), avec l’instruction d’ouvrir Connexion Athena.
- Le bandeau disparaît dès que le compte est associé.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updateMapHud.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updatePanel.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp` (1.0.75)

## Vérification

Tests d’assets HUD / tuile Athena / catalogue UPDATE 363. Rebuild Overwatch. En jeu : sans compte, bandeau ambre sur la carte et sur Athena ; une fois associé, le bandeau disparaît.

## Statut

corrigé
