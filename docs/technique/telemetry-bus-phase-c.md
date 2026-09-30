# Bus télémétrie Overwatch — Phase C (LOGSTAT / COMMS / état / vol)

Date : 2026-09-30  
Statut : livré  
Prérequis : Phase A + B

## Objectif

Automatiser la logistique (LOGSTAT), journaliser les émissions radio (métadonnées), remonter les changements d’état en delta, enrichir les aéronefs suivis.

## SQF

| Élément | Comportement |
| --- | --- |
| `fn_initLogisticsLoop` | PFH ~12 s (×2/×4 si backoff) → `sendLogisticsStatus` |
| `fn_sendLogisticsStatus` | Delta local ; POST `Logistics.Update` + event `logstat` P2 |
| Embarquement | Reset delta LOGSTAT pour envoi immédiat |
| `fn_updatePosition` | `comms` tx_start / tx_end ; `state` fuel/équipage (min 8 s) |
| `fn_reportCrewedAirAssets` | damage, speed, asl_z, engine_on, pitch/bank |

## Extension 2.0.55

- Batchable : `/api/logistics/update` → type `logstat`
- `flight-manifest` → type `flight` (plus `veh`)

## Athena

Types batch : `logstat`, `comms`/`acre`, `state`, `flight`.

- `AtakTelemetryCommsJournal` + `GET /api/atak/telemetry/comms?after_id=`
- LOGSTAT → `AssetLogisticsRepository` (même table C2)
- Flight → `upsertAirAsset`

## Vérification

1. Portail + Extension 2.0.55 / pack jeu, relancer Arma
2. Embarquer un véhicule : LOGSTAT apparaît (carburant / équipage)
3. PTT ACRE : entrée journal COMMS (début / fin, pas de voix)
4. Aéronef occupé : manifeste enrichi (vitesse, dégâts)
