# SSE : N3 signalait tout porteur d'un nom, alias placé dans le champ nom

## Contexte
Contrôle N3 hors ligne : comparaison du nom imprimé aux avis (fn_idEvaluate), paquet Athena et BOLO (fn_idAthenaPack, fn_idHvt).

## Symptôme
Un avis sans prénom déclenchait « RECHERCHÉE » ou « HVT » chez tout porteur de ce nom. Un alias recopié dans le champ nom produisait la même alerte chez un homonyme de l'alias.

## Cause
Condition `_wf isEqualTo ""` acceptée comme correspondance. `fn_idAthenaPack` et `fn_idHvt` écrivaient l'alias à la place du nom vide.

## Correctif
Alerte seulement sur nom et prénom identiques, ou alias égal au nom complet imprimé. Sinon « homonyme possible » (état doute, code `watch_homonym`), à recouper. L'alias reste dans son champ.

## Fichiers touchés
`mod/@COMSPEC_SSE/addons/identity/functions/fn_idEvaluate.sqf`, `fn_idAthenaPack.sqf`, `fn_idHvt.sqf`.

## Vérification
Syntaxe vérifiée avec `hemtt check`. Non exécuté en jeu : voir la liste de tests de la PR.

## Statut
corrigé à vérifier en jeu
