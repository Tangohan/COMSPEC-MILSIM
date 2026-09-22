# ATAK web — apparition / disparition malgré bandeau OK

**Statut :** corrigé (sources)

## Contexte

Opérateur en jeu (bandeau téléphone) et carte ATAK web / Overwatch Beta. Pack observé : OW 1.6.9 · ATAK 1.0.165 · liaison 2.0.51.

## Symptôme

Le contact apparaît au poste puis disparaît. Il faut lancer **Resynch** en jeu pour réapparaître. Le téléphone peut encore afficher OK avec `sync 2m`.

## Cause

1. Le bandeau **OK** mesurait le compte / le canal, pas la fraîcheur de la position.
2. `sync 2m` = dernière position envoyée il y a ~2 minutes ; le poste coupait à 120 s.
3. Le filet `COMSPEC_PosUplinkWatchdog` ne forçait une position **que si aucune n’avait jamais été envoyée** (`LastPositionSync < 0`). Dès qu’une sync avait réussi une fois, un blocage (backoff, relais, zone) n’était plus rattrapé — d’où l’obligation de Resynch manuel.
4. `reopenTransmitChannel` avait le même trou.

## Correctif

- Seuil hors liaison poste : **180 s**.
- Bandeau : plus d’OK si sync position > ~90 s.
- Watchdog : force une position si sync absente **ou** vieille de > 60 s (avec anti-spam 20 s + levée des freins API).
- Rouverture canal / Resynch : même logique de force si sync vieille ; Resynch lève aussi les freins API.
- Pack : Overwatch **1.6.11** · Athena **1.0.166**.
- Journal session (20:38) : téléphone absent au boot, « Spawn non stabilisé » à l’annulation, puis téléphone pris après respawn — parcours propre à ce client (l’autre joueur n’a pas ça).

## Fichiers touchés

- `app/Repositories/AtakDataRepository.php`
- `public/assets/js/atak-units.js` / `atak-overwatch-beta.js` / `atak-terminals.js`
- `connect/XEH_postInit.sqf`
- `connect/functions/auth/fn_reopenTransmitChannel.sqf`
- `connect/functions/fn_forceSyncData.sqf`
- `connect/functions/fn_updatePosition.sqf`
- `atak_athena/functions/fn_athena_updateLinkStrip.sqf`
- `connect/config.cpp`, `atak_athena/config.cpp`

## Vérification

1. Portail : Ctrl+F5.
2. Rebuild pack → Overwatch 1.6.10 · Athena 1.0.166.
3. En jeu : laisser le téléphone ouvert ; sans Resynch, `sync` ne doit plus monter à 2m sans correction auto.
4. Au poste : plus besoin de Resynch pour réapparaître après une coupure brève.

## Statut

corrigé (sources) — rebuild pack requis pour le filet auto
