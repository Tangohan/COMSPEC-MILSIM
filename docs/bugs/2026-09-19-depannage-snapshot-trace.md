# Dépannage — tracer l’appel en cours au plantage

## Contexte

Le bandeau de dépannage liaison rafraîchit chaque seconde un instantané (débit, versions, compte). Cet instantané appelle trois fonctions externes. Si le jeu s’arrête pendant le dépannage, on ne savait pas si c’était l’une d’elles ou une autre brique.

## Symptôme

Arrêt du jeu pendant le dépannage, sans ligne de journal indiquant l’appel en cours.

## Cause

`fn_diagStatusSnapshot` appelait `getPacketLossStats`, `extensionStatus` et `getCallsign` sans jalon avant/après. `window_in` (fenêtre de débit) n’était pas non plus dumpée avant d’être parcourue.

## Correctif

Chaque appel externe (`getPacketLossStats`, `extensionStatus`, `getCallsign`) est encadré par un `→` puis un `←` dans le journal Arma (`[COMSPEC Overwatch][DEBUG][Diag]`). Le parcours de `window_in` l’est aussi. Si le prochain plantage tombe dans cet instantané, le dernier `→` sans `←` identifie l’appel. Le contenu brut de la fenêtre de débit (3 premiers éléments) et du retour perte de paquets est écrit avant tout accès élément par élément. Un `skip extensionStatus (cached=…)` signale que l’extension n’a pas été rappelée ce cycle.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_diagStatusSnapshot.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp` (1.5.92)
- `tests/Unit/OverwatchDiagIsolateAssetTest.php`

## Vérification

Contrôles automatiques : présence des jalons `→` / `←` et des dumps `pkt raw` / `win count`. En jeu : relancer Arma (bandeau Overwatch 1.5.92), lancer le dépannage, ouvrir le `.rpt` : une paire `→` / `←` par cycle. Après un plantage, chercher le dernier `→` sans `←`.

## Statut

instrumenté — en attente du prochain journal de plantage
