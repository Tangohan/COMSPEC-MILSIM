# Messagerie : rectangle sombre sur Création

## Contexte

Liste des canaux radio (Groupe, Général, Commandement, JTAC, Air), Athena 1.0.133.

## Symptôme

Un rectangle sombre recouvre le titre « Création ». Les boutons Envoyer,
Effacer l’écran et Supprimer apparaissent par-dessus Créer, alors qu’aucun
fil n’est ouvert.

## Cause

La page Messagerie contient à la fois la liste et le fil. Le calage du
téléphone réaffiche tous les éléments. Le champ de saisie du fil et les
boutons d’envoi restent visibles sur la liste. Le champ de saisie d’origine
du téléphone (fond noir) peut aussi rester collé en bas.

## Correctif

Athena 1.0.134 : à l’ouverture, puis après le calage, seul l’écran utile
reste visible (liste ou fil). Les éléments de l’autre écran sont masqués.

## Fichiers touchés

- `atak_athena/functions/fn_athena_commsApplyChrome.sqf`
- `atak_athena/functions/fn_athena_updateComms.sqf`
- `atak_athena/functions/fn_athena_commsOnOpened.sqf`
- `atak_athena/ui/comms_page.hpp`
- `atak_athena/config.cpp` (1.0.134)

## Vérification

Ouvrir Messagerie : canaux lisibles, Création et Créer en bas, pas de bandeau
Envoyer / Effacer. Ouvrir un canal : fil + Envoyer. Retour : liste propre.

## Statut

corrigé (Athena 1.0.134)
