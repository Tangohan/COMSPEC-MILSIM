# Bus télémétrie Overwatch — Phase B (événements P0/P1)

Date : 2026-09-30  
Statut : livré  
Prérequis : Phase A (`docs/technique/telemetry-bus-phase-a.md`)

## Objectif

Remplacer le polling / chat pour les urgences par des **événements structurés** remontés via le bus batch.

## SQF

| Fichier | Rôle |
| --- | --- |
| `fn_emitTelemetryEvent.sqf` | Helper `["med"\|"unit"\|"combat"\|…, hashmap, priorité]` → `EmitTelemetry` |
| `fn_reportMedicalAlert.sqf` | Émet `med` P0 ; chat seulement si le bus échoue |
| `fn_selfCancelMedicalAlert.sqf` | Émet `med_clear` |
| `fn_initVehicleTracking.sqf` | `GetInMan` / `GetOutMan` → `unit` enter/exit |
| `fn_noteCombatEvent.sqf` | Tirs agrégés / impacts / missiles → `combat` |
| `fn_updatePosition.sqf` | Intervalle adaptatif (immobile → aéronef, ×2/×4 si backoff) |

## Extension 2.0.54

- Commande sync `EmitTelemetry` `[priority, type, coalesceKey, eventJson]`
- Capability `EmitTelemetry`

## Athena

Types batch ajoutés : `med` / `medical`, `med_clear`, `unit`, `combat`.

- Magasin `AtakTelemetryMedicalStore` (fichier cache)
- `GET /api/atak/medical-alerts` fusionne alertes structurées (prioritaires sur le doublon chat)
- Triage `telmed_*` supporté sans message chat

## Vérification

1. Portail Athena à jour + Extension 2.0.54 / pack jeu
2. Inconscient ACE → alerte au poste **sans** message « ALERTE MÉDICALE » au tchat (sauf secours)
3. Embarquement / débarquement → entrée journal activité
4. Position : immobile plus rare, véhicule/aéronef plus fréquent
