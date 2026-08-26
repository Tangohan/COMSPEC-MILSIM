# Bug — IA ennemies visibles par défaut sur ATAK

## Contexte

Carte ATAK web. Un opérateur voyait une pastille amie et, à proximité, un losange rouge OTAN (contact hostile). Le journal d’analyse listait aussi des indicatifs du type ALLY-… à côté des opérateurs.

## Symptôme

Les IA du camp adverse apparaissaient sur le poste sans que Zeus ou l’éditeur l’aient demandé : losange rouge, affiliation hostile / EAST. Le journal d’analyse se remplissait de connexions pour ces contacts.

## Cause

Le suivi « unité de terrain » envoyait le camp réel de l’IA (est = hostile). Une IA ennemie suivie, ou un chef de groupe adverse remonté, se dessinait donc comme un contact ennemi. Les aéronefs occupés uniquement par des IA adverses partaient aussi vers le poste, même masqués à l’écran : avec des dizaines d’IA, la liaison se saturait.

## Correctif

Masquage par défaut (carte, effectifs, journal). **Aucun suivi** tant que Zeus / l’éditeur n’a pas demandé l’affichage : pas d’envoi de position ni d’occupation d’appareil. Afficher allume le suivi ; masquer le coupe. Plafond et rythme inchangés une fois le suivi demandé. Les opérateurs et les IA alliées restent suivis.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_shouldSkipEnemyAiTransmit.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_reportCrewedAirAssets.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_reportAllyPosition.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_reportEnemyPosition.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_reportEnemyAiPositions.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_setAtakShowEnemyAi.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_registerZenTrackActions.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/modules/module_atak_show_enemy_ai.hpp`
- `public/assets/js/atak-units.js`, `atak-map.js`, `atak-cop.js`, `atak-activity.js`
- `app/Repositories/AtakDataRepository.php`

## Vérification

Tests unitaires : filtre PHP (IA hostile masquée, joueur adverse et IA alliée visibles), présence Zeus/Eden/JS, garde SQF `shouldSkipEnemyAiTransmit` avant occupancy / téléphone / GPS, UPDATE #217 enrichie (pas de suivi tant que Zeus n’a pas demandé).

## Statut

corrigé
