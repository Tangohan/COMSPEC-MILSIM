# Dépannage : arrêt à l’étape Ordres

**Date :** 2026-09-18  
**Statut :** en cours (isolation raffinée)

## Contexte

Journal `COMSPEC_2026-09-18_130326_759.log`. Dépannage liaison lancé à 13:04:26. Téléphone ouvert à 13:06:17. Les étapes Messages, formes et repères du poste tiennent. Arrêt du jeu après l’étape Ordres (vers 13:10:55). Pack chargé : Overwatch 1.5.82, liaison 2.0.44.

## Symptôme

Le jeu se ferme pendant l’étape Ordres du dépannage, souvent avec le téléphone déjà ouvert. Les messages et les repères du poste ont déjà été reçus sans incident.

## Cause

La même étape faisait trois choses à la fois : lire les ordres, les livrer (alerte, vibration, plein écran) et les coller dans le fil téléphone. Un tampon trop grand côté liaison synchrone est écarté. Le plus probable : l’affichage téléphone / alerte, pas la lecture elle-même.

## Correctif

- Étape **Ordres (réception)** : lecture et comptage seulement.
- Étape **Ordres (affichage)** : livraison et fil téléphone.
- Bandeau : débit, volume transmis, poste, versions, compte, taux d’erreur.
- Journal : nombre de messages, de repères, d’ordres et de formes à chaque retour.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_diagIsolateCatalog.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_pollOrders.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_receiveOrder.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_startSyncLoops.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_syncOrdersToGroupChat.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_diagIsolateHud.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_diagStatusSnapshot.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_noteUplinkReturn.sqf`

## Vérification

Journal `COMSPEC_2026-09-18_155343_430.log`, pack 1.5.84 / Athena 1.0.137. Dépannage 15:54:35 → 16:14:08, **21/21 étapes tenues**, y compris Ordres (réception) puis Ordres (affichage).

Limite de cet essai : **pas de téléphone ATAK**. Les boucles de sync sont restées en attente. Aucune ligne `Retour messages / marqueurs / ordres`. L’arrêt observé à 13:10 se produisait **avec le téléphone ouvert**. Cet essai ne rejoue donc pas le chemin qui fermait le jeu.

Session `COMSPEC_2026-09-18_191918_348.log` : reprise d’équipement avec téléphone, arrêt à 19:20:53. Voir `docs/bugs/2026-09-18-atak-crash-prise-equipement.md`.

## Statut

Isolation 1.5.84 tenue **sans téléphone**. Crash confirmé **à la prise d’équipement** (1.5.84). Correctif 1.5.85 : à rejouer avec téléphone.
