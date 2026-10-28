# Revue indépendante — troisième passage, `release/2.7.0`

Revue du code actuel (pas seulement le diff). Aucun fichier de code modifié par cette revue. Aucune partie jouée.

Les points acceptés aux passages précédents (SEC-042, SEC-044, SEC-045, DLL-001, ARCH-002, JavaScript, SEC-041, pause, SEC-046) ne sont pas rouverts : aucune régression claire dans le code actuel.

## Verdict

**ACCEPTER**

Le défaut marqueurs du deuxième passage est corrigé. Les deux chemins client qui doublaient encore le hub sortent dès que `COMSPEC_ServerHubActive` est vrai, avant tout `syncMapMarker`. Aucun autre appel client restant n’envoie encore un repère global que le hub envoie aussi.

## Marqueurs — ACCEPTER

### Correctifs du rejet (2ᵉ passage)

1. `atak_athena/XEH_postInitClient.sqf` — gestionnaire toutes les 2 s (`COMSPEC_Athena_BceMarkerQuickSnap` / noms `_…`) : première instruction utile = sortie si `COMSPEC_ServerHubActive`. Aucun balayage ni `syncMapMarker` ensuite.

2. `fn_syncNearbyMapMarkers.sqf` : sortie en tête si le hub est actif (avant `hasInterface` / réglages / boucle). Les enrobages double-clic et `PlaceMarker` du PostInit Athena appellent encore cette fonction ; avec le hub actif ils ne poussent plus.

### Chemins déjà acceptés (inchangés)

Dans `connect/XEH_postInit.sqf`, les gestionnaires `MarkerCreated` / `MarkerUpdated` / `MarkerDeleted` et la fermeture de carte sortent si le hub est actif, avant `syncMapMarker`. Sur création locale, la fiche d’auteur est encore écrite, puis sortie sans envoi. Le rattrapage différé (`COMSPEC_MarkerResyncSoon`) sort aussi en tête si le hub est actif.

`fn_syncUserMapMarkers.sqf`, `fn_resyncAllMapMarkers.sqf` et `fn_forceSyncMapMarkers.sqf` sortent en tête si le hub est actif. Les boucles de `fn_startSyncLoops.sqf` qui les appellent ne passent donc pas à l’envoi. Les gestionnaires de secours de `fn_startSyncLoops.sqf` ne s’installent que si `COMSPEC_MapMarkerEHs` est nul ; les précoces du PostInit connect le posent déjà. S’ils s’installaient quand même, ils sortent aussi sur hub actif.

### Hub serveur

`fn_initServerHub.sqf` pose `COMSPEC_ServerHubActive` (public), enfile tous les `allMapMarkers` déjà présents, puis garde `MarkerCreated` / `MarkerUpdated` / `MarkerDeleted` qui appellent `syncMapMarker` via la file. Ces gestionnaires ne se coupent pas : c’est l’émetteur unique attendu pour les repères globaux.

### Inventaire des autres appels client à `syncMapMarker`

Aucun doublon du hub pour un repère global visible du serveur :

| Appel | Hub actif |
| --- | --- |
| PostInit Athena 2 s (`_…`) | sort avant envoi |
| `fn_syncNearbyMapMarkers` | sort en tête |
| EH / fermeture carte PostInit connect | sort avant envoi |
| `fn_syncUserMapMarkers` / `resyncAll` / `forceSync` | sortent en tête |
| `fn_pollPoMarkers` | garde hub avant `syncMapMarker` du point d’objectif ; les anneaux restent `createMarkerLocal` et hors miroir générique |
| `fn_ecotiMarkBuilding` / cut / clear | `createMarkerLocal` : le hub serveur ne les voit pas ; envoi client nécessaire |
| `fn_placeMarkerFromTablet` | préfixe `comspec_tabletmk_` : le hub le traite comme déjà à lui (`isOwnedMapMarker`) ; envoi dédié côté poseur, pas un second miroir des repères carte Zeus / Widget |

Le pont cTab envoie sous `ctab_u_…`, laissé de côté par `syncMapMarker` : pas un doublon des gestionnaires carte.

## SEC-041 / pause / SEC-046

Non rouverts. Aucune régression claire constatée dans le code actuel par rapport au verdict du deuxième passage.

---

## Append — revue independante LOT-COMBAT / DLL (27/10/2026 soir)

Agent A8 (n'a pas ecrit les patches). Verdict : **ACCEPT**.

Risques residuels (non bloquants) : ordre create/update/delete marqueurs Async ; flicker HUD etranger a 10 Hz ; plafond 64 args DLL a confirmer ; Arma / MP non executes.

dotnet build Extension : OK (warnings nullable existants).

---

## Append — revue follow-up (Bugbot A8)

Verdict : **ACCEPT** pour le lot follow-up (KIA async, emitTelemetry non bloquant, early-exit degats natif, ARCH-006, parite screenTimeBatch).

Residus non bloquants deja notes : ordre SendMarker Async ; flicker foreignHud 10 Hz ; briefing Google face hors perimetre audit combat.

Arma / MP non executes.

---

## Append — revue A8 reprise LOT-DLL/MEM (27/10)

Agent [Bugbot](639d4cbf-2354-4f2c-86cc-039b5e74fc0a) : **ACCEPT** (lot follow-up combat/SEC deja en place). Residus connus : ordre marqueurs Async, flicker HUD 10 Hz, briefing Google `_face` (hors LOT-DLL).

LOT-DLL C01/C02 + MEM : `dotnet build` Extension OK ; gates `InAsyncWorker` / cache MapPack / plafonds logs verifies par lecture. Arma non execute.
