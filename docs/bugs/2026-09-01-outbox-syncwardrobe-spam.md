# 2026-09-01 — File d’attente saturée par les tenues hors liaison

## Contexte
Depuis l’arsenal ACE, l’opérateur peut envoyer ses tenues locales vers Athena.

## Symptôme
Sans session Athena, le journal se remplit en quelques secondes : des dizaines de « SyncWardrobe — hors ligne », puis tampon plein (50). Les vrais comptes rendus n’ont plus de place.

## Cause
Chaque tenue locale était mise en file, une par une, alors que le compte n’était pas relié. Le tampon n’est pas fait pour ça : seulement les saisies humaines (fiche SSE, photo, ordre).

## Correctif
Sans liaison, l’envoi des tenues s’arrête tout de suite, avec un seul message. Les tenues ne sont plus mises en attente. Les éventuelles tenues déjà coincées dans le tampon sont écartées au prochain chargement.

## Fichiers touchés
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_arsenalPushAll.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_outboxPush.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_outboxFlush.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_logTransmission.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_pauseManagerShow.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/XEH_postInit.sqf`

## Vérification
Sans session Athena : Envoyer les tenues → un seul avertissement. Journal : plus de rafale hors ligne. Le tampon reste disponible pour une fiche saisie sans couverture.

## Statut
Corrigé (visible après rechargement du pack)
