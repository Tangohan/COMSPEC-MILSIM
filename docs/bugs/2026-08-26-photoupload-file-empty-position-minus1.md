# Bug — photo vide et position timeout pris pour une saturation

## Contexte

Session 26/08/2026 17:58–17:59, indicatif N-10, liaison déjà établie (pack 1.4.75 ou 1.4.76). Journal :

- `ÉCHEC · PhotoUpload — file_empty · COMSPEC_567_10772.png`
- `En liaison · latence 0 ms · pertes 0.0% · paquets 0/0`
- `HTTP POST — code -1 · /public/api/atak/position`
- `Rate limit — pause 2 s | Athena est saturé`

## Symptôme

Une photo casque / drone est refusée tout de suite. Juste après, la position échoue sans réponse HTTP, et le journal annonce à tort que le poste de commandement est saturé.

## Cause

### Photo `file_empty`

`screenshot` crée le PNG tout de suite, souvent à 0 octet, puis écrit le contenu plus tard. La liaison trouvait le nom (`COMSPEC_567_10772.png`) dès que le fichier existait, sans attendre une taille réelle, et criait `file_empty` au premier essai.

La « latence 0 ms / paquets 0/0 » n’est pas un ping réseau : c’est un appel local à la liaison, et les compteurs de paquets ne sont pas alimentés par l’envoi de position.

### Position `-1` puis « saturé »

`-1` = pas de réponse HTTP (timeout, DNS, TLS), pas un refus 429. Un relevé de scène / relief partait en parallèle et saturait la même liaison : la position tombait en timeout. Cet échec déclenchait le même traitement qu’un vrai trop-plein du poste (`RateLimited` → « Athena est saturé »).

Un 401 plus tôt dans la session (jeton de session) est un autre cas ; ici le code `-1` n’était pas un 401.

## Correctif

- Attendre que le cliché ait une taille stable (> 0) avant l’envoi ; ne plus crier d’échec au premier fichier vide ; chercher aussi dans le dossier local Arma.
- Relevés volumineux en file, derrière la position.
- Timeout / coupure : pause courte « liaison instable », jamais « poste saturé ».

## Fichiers touchés

- `mod/UptoDate/COMSPECExtension/Extension.cs` (liaison 1.17.2)
- `mod/UptoDate/COMSPECExtension/COMSPECExtension.csproj`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_captureReconImage.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_extensionCallback.sqf`
- Pack connect 1.4.77 (version déjà portée par le relevé théâtre Zeus)

## Vérification

1. Pack 1.4.77, **quitter Arma** puis relancer.
2. Photo casque / drone : plus d’échec immédiat « fichier vide » ; la photo arrive au poste.
3. Pendant un relevé de théâtre : la position continue de partir ; une coupure affiche « liaison instable », pas « saturé ».
4. Journal boot : `connect v1.4.77` et liaison `1.17.2`.

## Statut

`corrigé` — pack 1.4.77 + liaison 1.17.2 dans le dossier Workshop Steam (`3684656708`) et `@COMSPECOverwatch` local. La copie vers `!Workshop` a échoué : Arma tenait les fichiers.

**Complément 18:00–18:01** : le journal montre encore `Rate limit — pause 2 s / 8 s | Athena est saturé` avec HTTP **code 0** sur `/video-feeds` puis `/flight-manifest`. Cause : pack chargé encore ancien, **et** même en 1.17.2 un timeout caméra mettait toute la file en pause (le manifeste d’occupation était jeté). Correctif 1.17.3 : code 0 ≠ saturation ; les caméras ont un cooldown à part. Voir `docs/bugs/2026-08-26-atak-vehicule-occupants.md`.
