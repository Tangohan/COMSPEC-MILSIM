# Liaison Athena — codes 0, -1, 401 et 503 au spawn

**Date :** 2026-08-24  
**Statut :** corrigé (sources) — rebuild extension 2.0.14 + connect 1.4.67 + atak_athena 1.0.49

## Contexte

Journal Overwatch juste après le spawn (handshake déjà OK). Session observée ~17:03–17:06 avec l’extension **2.0.12** encore en jeu (avant rebuild).

## Symptôme

- `HTTP POST code 0` puis `-1` sur `/public/api/atak/video-feeds` et `/public/api/atak/weather`
- `503` sur `/public/api/recon/images` — même fichier `COMSPEC_1374_44091.png` renvoyé ~53 s plus tard
- `HTTP POST code -1` répété sur `/public/api/atak/position`
- `401` ponctuel sur `/public/api/atak/weather`
- Une autre photo a fini par passer (`COMSPEC_1461_13515.png`) ; le chat (`SendChat`) passait déjà

Le terminal est en liaison, mais le journal se remplit d’échecs.

## Cause

Code **0** : pas de réponse HTTP (timeout 8 s). Code **-1** : exception réseau. **503** : Athena saturé. **401** : session / clé encore incohérente (`authArma`).

Après un échec, chaque POST (caméras, météo, position, photo) était **ré-enfilé tout de suite**. Pendant la pause, météo et caméras s’empilaient encore dans la file DLL, puis partaient en rafale. Les photos 503 libéraient le dédup : le watcher renvoyait le même PNG. Les caméras partaient dès la liaison prête, trop tôt pour un Athena encore chargé.

La signature météo était posée **avant** l’envoi : un 401 laissait le bandeau bloqué jusqu’au prochain changement de ciel (ou un Resynch).

## Correctif

- Timeout / 503 : pause globale (comme un trop de requêtes), on ne retente que la position.
- Pendant la pause : ne plus empiler météo / caméras / marqueurs dans la file DLL.
- Roster caméras : attendre 15 s après le handshake, et se taire pendant la pause.
- Météo : attendre 20 s après le handshake ; ne plus journaliser les 0 / -1 / 401 / 503 ; ne figer le ciel que lorsque le poste a bien reçu (`WeatherOk`).
- Photo 503 : le watcher ne renvoie pas le même PNG ; le worker remet le job en file et attend la fin de saturation (jusqu’à 6 essais).
- Journal : 0 / -1 / 503 / PhotoUpload saturé en avertissement, pas en erreur rouge.

## Fichiers touchés

- `mod/UptoDate/COMSPECExtension/Extension.cs` (2.0.14)
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_extensionCallback.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_waitAthenaReady.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_bridgeVideoFeeds.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_bridgeWeather.sqf`

## Vérification

Rebuild DLL Native AOT + PBO connect / atak_athena, relancer Arma **entièrement**. Au spawn : handshake OK, pas de rafale rouge video-feeds / position / weather. Si Athena est lent : un avertissement, silence quelques secondes, puis reprise. Une photo 503 ne doit plus revenir en boucle sous le même nom ; elle part une fois le poste disponible.

Sans ce rebuild, le journal 2.0.12 reste identique.

## Statut

corrigé (sources) — rebuild requis
