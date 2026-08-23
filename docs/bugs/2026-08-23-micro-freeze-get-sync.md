# Micro-freezes périodiques en jeu (GET Athena synchrones)

## Contexte

En mission, le jeu se figait une fraction de seconde (parfois plus) de façon
régulière, sans lien avec une photo ou un screenshot.

## Symptôme

Saccades / micro-gels répétés toutes les quelques secondes, surtout une fois
la liaison Athena établie. Athena lent ou injoignable pouvait allonger le gel
jusqu’à plusieurs secondes.

## Cause

1. **Cause principale** — les lectures Athena (marqueurs, ordres, chat,
   alertes, CAS, formes, commandes de charge) passaient par un GET HTTP
   **bloquant** sur le thread jeu (`SendAsync(…).GetResult()`, plafond 8 s).
   Plusieurs sondes partaient en parallèle (4 à 10 s) : chaque round-trip
   réseau figeait Arma.
2. **Cause secondaire** — un balayage de **tous** les marqueurs carte
   (`allMapMarkers`, forme, position, texte, couleur…) toutes les **5 s**,
   alors que les événements MarkerCreated / Updated / Deleted existaient déjà.
3. **Causes tertiaires** — scan de tous les véhicules et de toutes les
   unités toutes les 8 s pour les balises GPS ; écriture fichier journal
   synchrone à chaque ligne de log.

## Correctif

- Les GET périodiques retournent tout de suite le dernier résultat connu et
  se rafraîchissent en arrière-plan (plus d’attente réseau sur le thread jeu).
- Resync complet des marqueurs toutes les 45 s ; flush de la file d’attente
  inchangé (5 s). Les événements carte restent la voie temps réel.
- Balises GPS / téléphones : listes tenues à jour à l’activation, plus un
  scan de sécurité moins fréquent.
- Journal fichier : écriture déportée hors du thread jeu.
- Sondes décalées pour ne plus toutes partir sur la même image.

## Fichiers touchés

- `mod/UptoDate/COMSPECExtension/Extension.cs` (2.0.8)
- `connect/functions/fn_startSyncLoops.sqf`
- `connect/functions/fn_initGpsBeacons.sqf`
- `connect/functions/fn_setGpsBeacon.sqf`
- `connect/functions/fn_setPhoneTrack.sqf`
- `connect/config.cpp` (1.4.47)

## Vérification

- Compilation Native AOT de l’extension + PBO connect (build_mod.bat).
- En jeu : après liaison Athena, plus de gel cadencé ; ordres / chat /
  marqueurs web arrivent avec au plus un cycle de sonde de retard.
- Quitter Arma complètement avant de recharger la DLL.

## Statut

corrigé
