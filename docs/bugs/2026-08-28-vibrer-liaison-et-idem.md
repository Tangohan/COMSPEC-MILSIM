# Vibrer : écran « Liaison perdue » et erreur `_idem` au Resynch

## Contexte

Après un « Vibrer » depuis la carte web, le téléphone ATAK en jeu restait sur « Liaison perdue / Reconnexion dans 15 s ». Un Resynch affichait une erreur de script `_idem` (file SEEK).

## Symptôme

- Overlay « Liaison perdue » qui ne part pas alors que le poste vient d’atteindre le terminal.
- Erreur : variable `_idem` indéfinie dans `fn_queueOffline.sqf` / `fn_buildAthenaDigitalPayload.sqf`.

## Cause

1. Une coupure simulée (aléatoire) n’était pas levée quand un signal TOC arrivait vraiment. L’injection web de l’overlay n’exécutait pas le JavaScript (mauvais écran, pas d’exécution).
2. `findIf` avec des variables `private` à l’intérieur rendait `_idem` invisible dans le reste de la fonction (portée SQF). Le Resynch SEEK passait par cette file.

## Correctif

- À la réception d’une vibration : lever la coupure simulée (sauf brouillage Zeus), rafraîchir l’overlay, exécuter le JavaScript pour retirer « Liaison perdue ».
- File SEEK : dédupliquer avec `forEach`, clé `_idemKey` hors du bloc interne.

## Fichiers touchés

- `mod/@COMSPEC_SSE/addons/network/functions/fn_queueOffline.sqf`
- `mod/@COMSPEC_SSE/addons/network/functions/fn_buildAthenaDigitalPayload.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_onVibrate.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_injectRoleplayEffectsInBrowser.sqf`

## Vérification

Tests `SseQueueOfflineIdemAssetTest`, `AtakLostLinkOverlayAssetTest`. Pack Overwatch 1.4.89 + Athena 1.0.54 + SSE 0.7.16, relancer Arma.

## Statut

corrigé — pack jeu à reconstruire
