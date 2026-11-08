# Maillage ATAK : deux calculs différents, lectures sans bornes

## Contexte

Réseau maillé du téléphone, liaison réaliste (`comspec_atak_native_mpu_real`, désactivée par défaut). Plan : `docs/technical/reseau-mesh-mpu5.md`, défauts 1, 4 et 5.

## Symptôme

Constaté à la lecture du code, non vu en jeu. L'app Wave Relay pouvait afficher d'autres sauts que ceux des données et de la voix. Deux clients pouvaient se voir différemment pendant quelques secondes. Une valeur absurde publiée par un client (portée sans plafond, nombre non fini, mauvais type) entrait dans le graphe de tout son camp.

## Cause

- `fn_waveRelayAction` « scan » faisait son propre parcours en largeur, `fn_mpuLink` « mesh » le chemin le plus large.
- Chaque client calculait depuis sa propre vue, et au-delà de 30 nœuds le parcours s'arrêtait à la première passerelle.
- `COMSPEC_ATAK_MpuNode`, `Mpu5` et `MpuVoice` étaient lues sans bornes dans `fn_mpuLink`, `fn_relayNet` et `fn_mpuVoice`.
- Le cache des bilans radio se vidait à 600 entrées, alors que 64 nœuds font 2 016 paires.

## Correctif

- `fn_meshRead` : seule lecture de ces variables, avec type, taille et bornes.
- `fn_meshGraph` : un seul graphe, calculé sur le serveur par défaut, lu par l'écran, les données et la voix. Hystérésis, plafond de sauts, tranches, cache de 4 096 entrées.
- Ancien calcul conservé seulement liaison réaliste décochée.

## Fichiers touchés

`fn_meshRead`, `fn_meshGraph`, `fn_mpuLink`, `fn_mpuVoice`, `fn_mpu5`, `fn_relayNet`, `fn_vehicleGateway`, `fn_waveRelayAction`, `fn_pageWaveRelay`.

## Vérification

`hemtt check` : seules les 2 erreurs connues sur `ctrlWebBrowserAction`. Pas de partie lancée. Tests en jeu listés dans la PR #1395 et cas purs dans `fn_devMeshTest`.

## Statut

corrigé à vérifier en jeu
