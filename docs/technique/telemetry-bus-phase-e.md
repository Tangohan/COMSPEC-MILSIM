# Bus télémétrie Overwatch — Phase E (COP poste)

Date : 2026-09-30  
Statut : livré  
Prérequis : Phase A–D

## Objectif

Faire apparaître sur la carte du poste les pistes d’observation et d’évaluation, distinctes du suivi allié, avec confirmation humaine (surtout BDA).

## Carte Overwatch

| Élément | Comportement |
| --- | --- |
| Calque « Observations terrain » | Pistes `observation` / candidats (contour pointillé) |
| Calque « Évaluations confirmées » | Pistes `assessment` (plein) |
| Panneau latéral | Détail + Confirmer / Écarter |
| Bilan des dégâts | Liste « bilan observé » avant confirmation |

Ignoré : couche `reality` (déjà couverte par les unités BFT).  
Non fusionné : calques dossier SSE (`atak-sse-layers.js`).

## Fichiers

- `public/assets/js/atak-overwatch-tracks.js`
- `views/atak-overwatch-beta.php` (toggles + script)
- `public/assets/css/atak-overwatch-beta.css`
- Hook `refreshUnits` → `OverwatchTacticalTracks.refresh`
- `POST /api/atak/tracks/{uid}/confirm` accepte `assessment`

## Suite Phase E — consommation bus sur le poste (UPDATE #740)

En plus des pistes :

| Surface | Contenu |
| --- | --- |
| Mission | Alertes santé (`medical-alerts`) + triage ; soutien LOGSTAT (`logistics`) |
| Radio | Historique émissions (`telemetry/comms`) |
| Journal (Plus) | Filtres sanitaire / contact / soutien / radio / unités / renseignement |

Module : `public/assets/js/atak-overwatch-telemetry.js`

## Vérification

1. Recharger le portail (Ctrl+F5)
2. Ouvrir Overwatch → Calques → Observations terrain
3. Envoyer un SALUTE / note reco / BDA depuis le jeu (pack 2.0.56+)
4. Cliquer la piste → Confirmer (ou Écarter)
5. Mission : alertes santé + soutien ; Radio : historique émissions ; Plus → Journal : filtres
