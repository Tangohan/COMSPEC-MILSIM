# Avertissement caméras code 0

## Contexte

Liaison Overwatch vers le poste. Le journal affichait un avertissement répété : échec d’envoi des caméras casque, code 0, sans réponse du poste.

## Symptôme

`WARN · HTTP POST — code 0 · /public/api/atak/video-feeds`. Le journal se remplit alors qu’il n’y a souvent aucune caméra à publier, et la relance immédiate après échec aggrave la charge.

## Cause

Code 0 = aucune réponse (coupure, délai dépassé), pas un refus du poste. Après l’échec, le jeu effaçait la signature du dernier envoi, donc le même message repartait au cycle suivant. L’envoi caméras partait tout de suite, en concurrence avec la position.

## Correctif

- Plus d’alerte journal pour un échec caméras (comme la météo).
- Après un échec : pause d’une minute, sans relancer tout de suite.
- Les caméras passent dans la file, après la position.
- Liste inchangée : nouvel envoi au plus toutes les 60 s (90 s s’il n’y a aucune caméra).

## Fichiers touchés

- `mod/UptoDate/COMSPECExtension/Extension.cs`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_extensionCallback.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_bridgeVideoFeeds.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/XEH_postInitClient.sqf`

## Vérification

Assertions sources (liaison 1.17.4, pause caméras, plus d’effacement de signature). Recette : pack 1.4.81, relance Arma, plus de WARN caméras en boucle.

## Statut

corrigé
