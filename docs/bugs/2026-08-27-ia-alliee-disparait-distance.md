# IA alliées qui s’éloignent disparaissent de l’ATAK web

## Contexte

IA alliée suivie sur la carte (Zeus : « IA alliée sur l’ATAK »). Dès qu’elle s’éloigne (suivi, déplacement, autre groupe), la pastille disparaît complètement du poste.

## Symptôme

L’IA est encore en jeu. Sur l’ATAK web : plus aucune marque, même pas une dernière position.

## Cause

Un seul poste relais (indicatif Steam le plus petit) envoyait les positions, et seulement pour les IA encore dans sa bulle. Une IA qui suit un autre joueur, ou qui part au loin, n’existe plus pour ce relais : plus d’envoi, puis la carte retire tout contact hors liaison.

## Correctif

Le joueur le plus proche de l’IA relaie. Le serveur garde une position même hors bulle. La carte conserve la dernière position (plus pâle) au lieu d’effacer.

## Fichiers touchés

- `mod/.../fn_initGpsBeacons.sqf`
- `mod/.../fn_isNearestAtakReporter.sqf`
- `mod/.../fn_initProxyTrackServer.sqf`
- `mod/.../fn_reportAllySnapshot.sqf`
- `mod/.../fn_setAllyTrack.sqf`
- `mod/.../fn_reportEnemyAiPositions.sqf`
- `public/assets/js/atak-map.js`
- `public/assets/css/atak.css`
- `mod/.../connect/config.cpp` (1.4.88)

## Vérification

Rebuild connect 1.4.88. Marquer une IA, l’envoyer loin d’un joueur / la faire suivre un autre : elle reste sur la carte (à jour si un poste est proche, sinon dernière position atténuée). Recharger la carte pour le côté web sans pack.

## Statut

corrigé (rebuild pack requis pour le relais jeu)
