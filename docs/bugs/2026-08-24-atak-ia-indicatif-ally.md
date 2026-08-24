# Indicatif IA ATAK affiché comme ALLY-0-1780311

## Contexte

Quand une IA est mise sur ATAK depuis Eden ou Zeus, la carte web et la fiche unité montraient un identifiant automatique du type `ALLY-0-1780311` (exemple : `ALLY-0-1780311 · AL…` dans la colonne Indicatif, `ALLY-0-1780311 - ALPHA 1-2 - JAMES BROWN` en en-tête).

## Symptôme

Le chef de mission ne pouvait pas choisir l’indicatif. Même le nom de groupe déjà présent (`Alpha 1-2`) restait collé derrière le numéro automatique.

## Cause

L’indicatif envoyé à la carte était toujours préfixé par l’identifiant interne de suivi (nécessaire pour ne pas coller l’IA au joueur relais). Zeus n’offrait pas de champ Indicatif ; Eden en avait un, mais l’affichage ignorait le nom lisible.

## Correctif

- Identifiant interne conservé pour le suivi ; l’indicatif affiché est le nom choisi, sinon le groupe / le nom de l’IA.
- Champ **Indicatif** : Eden (attributs de l’unité) et Zeus (panneau ATAK, module / menu « IA alliée sur l’ATAK »).
- Carte web : la liste, la fiche et le marqueur masquent le numéro automatique, y compris pour les IA déjà en place.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_allyTrackCallsign.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_allyTrackConfigure.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_setAllyTrack.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_zeusAttributesAtak.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_registerZenTrackActions.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/modules/eden_sse_attributes.hpp`
- `app/Repositories/AtakDataRepository.php`
- `public/assets/js/atak-unit-popup.js`

## Vérification

Tests unitaires d’affichage. Rebuild du pack `connect`. En session : poser une IA depuis Eden / Zeus avec un indicatif, puis sans ; contrôler la colonne Indicatif et l’en-tête de fiche.

## Statut

Corrigé.
