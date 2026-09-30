# Bus télémétrie Overwatch — Phase D (TRACKS / OBSERVATION / BDA / SIGINT)

Date : 2026-09-30  
Statut : livré  
Prérequis : Phase A + B + C

## Objectif

Séparer la réalité alliée (BFT) des observations terrain et des évaluations confirmées. Un bilan des dégâts reste candidat jusqu’à confirmation humaine — jamais marqué détruit automatiquement depuis le moteur.

## Couches

| Couche | Contenu |
| --- | --- |
| `reality` | Positions alliées / BFT |
| `observation` | SALUTE, reco, SIGINT, BDA candidat |
| `assessment` | Piste confirmée au poste (`bda_confirm` / confirm manuel) |

## SQF

| Élément | Comportement |
| --- | --- |
| `fn_saluteDialogSubmit` | Alerte SALUTE + event `salute` (P1) |
| `fn_reconPushNote` | Note reco existante + event `recon` (P2) |
| `fn_sendTacticalAlert` | Si BDA → event `bda` candidat (P1), sans statut détruit moteur |
| `fn_emitTelemetryEvent` | Priorités salute / bda / recon / sigint |

## Extension 2.0.56

- `EmitTelemetry` inchangé (types `salute`, `recon`, `bda`, `bda_confirm`)
- Version pack alignée

## Athena

- Tables `tactical_tracks` / `tactical_observations` (`AtakTacticalTracksSchema`)
- Batch : `obs` / `recon` / `salute` / `bda` / `bda_confirm` ; SIGINT → track + ellipse
- `GET /api/atak/tracks?mapId=&layer=`
- `POST /api/atak/tracks/{uid}/confirm`
- Rejeu : observations dans `ReplayRepository::getOperationalEvents`

## Règle BDA

Le moteur ne force jamais `DESTROYED`. L’évaluation passe par confirmation humaine (`assessment` + statut `confirmed` / couche `assessment`).

## Vérification

1. Portail + Extension 2.0.56 / pack jeu, relancer Arma
2. Envoyer un SALUTE : piste observation au poste
3. Note de reconnaissance : piste + rejeu
4. Bilan des dégâts : reste candidat jusqu’à confirmation
5. Deux bearings SIGINT croisés : piste « Émetteur probable »
