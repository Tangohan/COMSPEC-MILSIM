# Téléphone : signal Relais, étage, journal SEEK, file d’attente

## Contexte

20 septembre 2026. Le mât Relais, le découpage d’étage, les interrogations SEEK et la file hors couverture existaient déjà, mais le téléphone ne les montrait pas clairement.

## Symptôme

1. Le joueur ne voyait le réseau Relais que dans Relais AT, ou un message de chat en sortant de portée.
2. Changer d’étage imposait de rouvrir le menu ACE à chaque niveau.
3. Une interrogation SEEK disparaissait dès la fermeture de l’écran.
4. Les messages tamponnés hors couverture restaient invisibles.

## Cause

Aucun indicateur de barres, aucune fiche immeuble dans l’app, un seul verdict SEEK en mémoire, aucun badge de file.

## Correctif

- Barres de signal (0 à 4) d’après le mât le plus proche, avec bascule hors portée / détruit / brouillé / zone dégradée.
- Fiche bâtiment sur la carte du téléphone : curseur d’étage synchronisé avec le badge.
- Journal des identifications dans BII-10, partagé avec le groupe.
- Bandeau « X messages en attente de synchro ».

Pack : Overwatch **1.6.7** · Athena **1.0.163** · liaison **2.0.50**. Relancer Arma complètement.

## Fichiers touchés

- `connect/functions/fn_atakSignalState.sqf`
- `connect/functions/fn_updateAtakLinkChrome.sqf`
- `connect/functions/fn_ecotiSetFloor.sqf`
- `connect/functions/fn_seekHistoryPush.sqf`
- `connect/functions/fn_pendingSyncCount.sqf`
- `atak_athena/functions/fn_athena_updateBuildingSheet.sqf`
- `atak_athena/functions/fn_athena_updateQueueBadge.sqf`
- `atak_athena/functions/fn_athena_biiOnOpened.sqf`
- `atak_athena/ui/bii_page.hpp`
- `COMSPECExtension/Extension.cs` (`GetPendingQueueCount`)

## Vérification

1. Quitter Arma. Recharger Overwatch 1.6.7 et Athena 1.0.163.
2. S’approcher d’un mât Relais : les barres se remplissent. S’éloigner : elles se vident. Détruire le mât : barres rouges.
3. Désigner un bâtiment, ouvrir le téléphone : changer d’étage au curseur ; le badge suit.
4. Interroger un sujet SEEK, ouvrir BII-10 : la ligne reste dans le journal.
5. Couper la couverture avec des messages en attente : le bandeau s’affiche.

## Statut

corrigé (Overwatch 1.6.7 / Athena 1.0.163)
