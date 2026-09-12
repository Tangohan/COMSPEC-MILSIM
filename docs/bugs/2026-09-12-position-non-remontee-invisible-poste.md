# Position non remontée — invisible au poste malgré liaison OK

## Contexte

12 septembre 2026. Pack joué **Overwatch 1.5.46** / Athena **1.0.91**. Compte appairé (TA1 Jake), canal poste « ouvert », photos reçues au poste, mais aucun contact dans Effectifs.

## Symptôme

- Poste : Réseau ACTIF, tableau Effectifs vide (« Aucun contact en liaison »).
- Jeu : « Liaison OK – canal ouvert », Steam associé, dernière confirmation récente.
- **« Position remontée : pas encore »** alors que la carte locale montre bien l’opérateur.
- Les photos partent (chemin distinct des boucles de position).

## Cause

1. **Boucles de sync manquées pendant HandshakeQuiet** — à l’ouverture du canal, `canStartSync` refuse tant que le quiet handshake (~20 s) est actif. Si les tentatives one-shot (événement liaison, spawn stabilisé) tombent dans cette fenêtre et que la rouverture de fin de quiet échoue ou est ratée, `COMSPEC_SyncLoopsStarted` reste faux : plus aucune position n’est poussée, alors que les photos (watchers indépendants) fonctionnent.
2. **Garde `isReady` trop stricte** — `updatePosition` exigeait `auth_state == READY` alors que l’UI s’appuie sur `COMSPEC_AthenaReady`. Un flicker d’état DLL hors READY laissait le canal « ouvert » à l’écran sans jamais appeler `UpdatePosition` (`COMSPEC_LastPositionSync` restait à -1).
3. **Terminal non reconnu** — si le téléphone cTab est ouvert sans classe exacte `ItemAndroid` / `ItemAndroidMisc`, la sync position était coupée (`no_terminal`) alors que l’UI Athena était utilisable.

## Correctif

- `canStartSync` : `AthenaReady` autorise les Tx (plus seulement `isReady` + `isC2Ok`).
- `updatePosition` : même priorité `AthenaReady`.
- `AthenaLinkChanged` : si quiet bloque, retry à +22 s via `reopenTransmitChannel`.
- Watchdog 8 s : canal ouvert sans boucles → rouverture ; boucles OK mais jamais de position → force.
- `reopenTransmitChannel` / démarrage des boucles : force position dès l’ouverture.
- `hasTerminal` : téléphone cTab déjà ouvert = équipé ; variantes de nom Android/cTab.
- Panneau Athena : aide lisible quand la position n’est « pas encore » remontée.

## Fichiers touchés

- `connect/functions/auth/fn_canStartSync.sqf`
- `connect/functions/auth/fn_reopenTransmitChannel.sqf`
- `connect/functions/fn_updatePosition.sqf`
- `connect/functions/fn_hasTerminal.sqf`
- `connect/functions/fn_startSyncLoops.sqf`
- `connect/XEH_postInit.sqf`
- `atak_athena/functions/fn_athena_updatePanel.sqf`
- `connect/config.cpp` (1.5.52), `atak_athena/config.cpp` (1.0.97)

## Vérification

1. Rebuild Overwatch **1.5.52** + Athena **1.0.97**. Quitter Arma complètement.
2. Mission : appairer / canal ouvert → sous ~10 s, « Position remontée » passe à « il y a Xs ».
3. Poste Effectifs : contact TA1 visible sur la carte, plus « Aucun contact en liaison ».
4. Sans rien cliquer sur Transmettre / Resynch.

## Statut

corrigé (sources) — rebuild / déploiement à faire
