# RENS ne s’ouvre pas sur le téléphone ATAK

## Contexte

Le menu RENS du téléphone doit afficher un accueil puis le rédacteur de fiche plein cadre. Les opérateurs rapportaient que rien ne s’ouvrait.

## Symptôme

Toucher RENS ne montrait pas le rédacteur. Parfois l’accueil lui-même restait invisible, recouvert par Athena.

## Cause

Le rédacteur s’ouvrait trop tôt, pendant le calage du téléphone, qui le refermait sans message. Athena restait aussi dessinée par-dessus la page RENS.

## Correctif

L’accueil RENS masque Athena dès l’ouverture. Le rédacteur attend que le téléphone soit calé. S’il est refermé pendant ce calage, une seconde ouverture est tentée ; sinon un message invite à utiliser « Rédiger une fiche ».

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_noteOnOpened.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_openNote.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_intelNoteShow.sqf`

## Vérification

Test `AtakWindowsIsolationAssetTest`. Contrôle visuel en jeu après reconstruction du pack : RENS affiche l’accueil, puis le rédacteur, sans Athena par-dessus.

## Statut

Corrigé (pack à reconstruire).
