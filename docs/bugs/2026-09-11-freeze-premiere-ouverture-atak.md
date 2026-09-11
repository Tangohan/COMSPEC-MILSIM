# Gel à la première ouverture ATAK

## Contexte

11 septembre 2026. Pack 1.5.37 / liaison 2.0.27. À la première ouverture du téléphone, le jeu gèle fortement ; l’écran paraît blanc / laiteux. Journal : SendChat + fiche opérateur **avant** « Session Athena prête », puis spam `unauthorized` sur la fiche (~1/s), 401 chat / flight-manifest.

## Symptôme

- Hitch / freeze au premier open ATAK
- Écran téléphone blanchâtre
- Rafales `fiche opérateur — unauthorized`

## Cause

1. `startSyncLoops` armé dès `AthenaLinkChanged` ready, pendant `HandshakeQuiet` (jeton encore instable).
2. `OperatorRegister` / Sync = HTTP **synchrone** (jusqu’à 8 s) ; **pas de backoff** sur 401 ; `_force` au premier register ; `reopenTransmitChannel` remettait `SyncLoopsStarted` à false → PFH empilés.
3. Overlay device : textures `.paa` absentes du pack → `RscPicture` blanc laiteux.

## Correctif

- `canStartSync` exige `!HandshakeQuiet`
- `reopenTransmitChannel` ne réarme plus les boucles si déjà démarrées
- Fiche : backoff 401/403 ; register différé 8 s sans force ; init sync différé 5 s
- `sendFactionSettings` différé 3 s
- flight-manifest gated sur quiet
- Overlay : pas de picture si fichier manquant (voile sombre + texte)
- `AuthInvalidated` ne remet plus `SyncLoopsStarted` à false (évite un 2ᵉ empilement de PFH)

## Fichiers touchés

- `fn_canStartSync.sqf`, `fn_reopenTransmitChannel.sqf`
- `fn_syncOperatorProfile.sqf`, `fn_initOperatorProfileSync.sqf`
- `fn_startSyncLoops.sqf`, `fn_reportCrewedAirAssets.sqf`
- `fn_updateDeviceOverlay.sqf`, `fn_extensionCallback.sqf`

## Vérification

1. Recharger pack **1.5.38 / 2.0.28**, quitter Arma.
2. Se connecter, ouvrir ATAK : pas de gel multi-secondes.
3. Journal : pas de fiche/chat avant fin quiet ; pas de rafale 401 fiche.

## Statut

partiel — 1.5.38 coupe le spam 401 ; hitch UI 1ʳᵉ ouverture traité en **1.5.39** (hydratation menu + raccourcis différés, log handshake corrigé). FPANO non requis.

## Suite 1.5.39

- `waitAthenaReady` : ne plus juger via `canStartSync` pendant Quiet (faux « canal refusé »)
- `updateMapHud` : `BCE_fnc_ATAK_getAPPs` différé ~0,85 s
- `installDesktopShortcut` : ctrlCreate différé ~0,9 s après ouverture display

