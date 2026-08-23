# ATAK — ordres personnalisés affichés « Se déplacer »

## Contexte

Ordres C2 avec un type métier personnalisé (ex. TR-SSE) émis depuis l’ATAK web. Sur le téléphone / TASK, la liste les affiche tous comme « Se déplacer ».

## Symptôme

- ATAK web : carte « Ordre C2 » avec le vrai nom (ex. TR-SSE).
- App TASK (Iceman) : `Se déplacer · N-10 · Remis` pour ces mêmes ordres.
- Les FRAGO restent correctement libellés « Ordre fragmentaire ».

## Cause

Le type stocké est un code technique (`TYP_12`, etc.). Le libellé humain (`type_label`) est déjà dans la réponse Athena, mais :

1. l’extension ne le recopiait pas vers le jeu (colonnes TSV sans `type_label`) ;
2. l’affichage SQF ne connaissait que MOVE / HOLD / RECON / CAS / QRF / CUSTOM / FRAGO — tout le reste tombait sur « Se déplacer ».

## Correctif

- L’extension transmet le libellé en 11ᵉ colonne.
- Le poll jeu l’enregistre dans `typeLabel`.
- Un helper unique `orderTypeLabel` : libellé reçu, sinon type connu, sinon « Ordre personnalisé » pour TYP_ / TPL_ / CUSTOM_.
- Émission jeu : un type `TYP_…` n’est plus forcé en MOVE.

## Fichiers touchés

- `mod/UptoDate/COMSPECExtension/Extension.cs`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_orderTypeLabel.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_pollOrders.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_issueOrder.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_receiveOrder.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updateTask.sqf`
- (et les autres écrans TASK / notifs / chat de groupe qui affichaient le type)

## Vérification

1. Relancer Arma (Overwatch 1.4.28 + pont Athena 1.0.31).
2. Depuis l’ATAK web, émettre un ordre d’un type personnalisé (ex. TR-SSE) vers un terminal.
3. Ouvrir TASK : la ligne doit afficher `TR-SSE · …` et non `Se déplacer`.

## Statut

corrigé
