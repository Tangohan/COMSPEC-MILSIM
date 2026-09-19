# Quick Pictures — pas d’envoi vers le poste

## Contexte

Les vues prises depuis Quick Pictures / Photo Library n’arrivaient pas au poste Overwatch. L’opérateur demandait un bouton d’envoi explicite dans la bibliothèque.

## Symptôme

- Une photo Quick Pictures est enregistrée dans Photo Library.
- Elle n’apparaît pas au poste.
- Le bouton Send n’envoyait que vers un autre téléphone, sauf si l’on choisissait manuellement « Poste Athena ».

## Cause

Le bouton Send d’IceMan vise un autre opérateur. Le destinataire Poste Athena existait mais n’était pas sélectionné. Un envoi automatique pouvait aussi échouer sans être retenté, la photo étant marquée comme déjà vue.

## Correctif

- Bouton **Vers Athena** dans Photo Library : envoie la photo sélectionnée au poste.
- Poste Athena est le destinataire par défaut.
- Une vue manquée n’est plus marquée comme transmise : elle peut partir au cycle suivant ou via le bouton.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_sendLibraryPhoto.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_installPhotoLibraryAthena.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_bridgeIcemanPhoto.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_pollIcemanPhotos.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp` (1.0.142)

## Vérification

Quitter Arma complètement. Recharger Athena 1.0.142. Prendre une vue Quick Pictures. Ouvrir Photo Library : bouton Vers Athena. Cliquer : la photo arrive au poste.

## Statut

Corrigé
