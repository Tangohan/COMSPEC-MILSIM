# Bug — journal Overwatch en rafale au démarrage

## Contexte

Pack Overwatch encore en 1.4.74 en jeu (liaison 2.0.15). Session `tempMissionMPSEEK v2`, état client 0, liaison « connecting ».

## Symptôme

Le journal de session se remplit toutes les 1–2 secondes :

- `CBA_settingsInitialized — mavic enableConn…`
- `=== Dump journal (boot) ===` (vingt lignes de diagnostic)
- `Handshake démarré` (sans jamais `Handshake terminé`)

L’overlay ACE `isNull` (`fn_initSseAce.sqf` L169) réapparaît : le PBO `sse_ace` chargé n’est pas celui du source corrigé.

## Cause

`XEH_postInit.sqf` enregistrait deux handlers `CBA_settingsInitialized` **sans garde unique**. Cet événement CBA se rejoue pendant le briefing / la synchro des réglages (ici ~toutes les secondes, `clientState=0`).

Chaque rejeu :

1. relançait un dump diagnostic complet ;
2. ouvrait un nouveau handshake Athena (qui remet `COMSPEC_AthenaReady` à faux) ;
3. empilait des PFH (simu réseau, zones, file hors-ligne).

Le menu ACE SSE cassé vient du pack atelier encore en 1.4.74 : ACE envoie un **nom de classe**, pas un objet — voir `2026-08-26-sse-ace-isnull-newcontrollable.md`.

## Correctif

- Gardes `COMSPEC_CbaSettingsBootDone` / `COMSPEC_CbaSettingsBootArmed` : dump + boot une seule fois par mission.
- PFH réseau / zones / file hors-ligne protégés par `isNil`.
- `fn_logDump` : un dump « boot » au plus toutes les 45 s.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/XEH_postInit.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_logDump.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/sse_ace/functions/fn_initSseAce.sqf` (déjà corrigé, à recharger en 1.4.16)

## Vérification

1. Recharger le pack 1.4.75 (connect + sse_ace + liaison 2.0.16), **quitter Arma** puis relancer.
2. Journal : un seul `Dump journal (boot)`, un seul `Handshake démarré`, puis `Handshake terminé` ou mode dégradé.
3. Menu ACE sur une unité : plus d’erreur `isNull`.
4. Boot : `PreInit OK — connect v1.4.75` et `ping=OK|COMSPECExtension 2.0.16`.

## Statut

`corrigé à rebuild`
