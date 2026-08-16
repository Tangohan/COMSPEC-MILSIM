# SSE Intelligence Workspace — LOT 6 (Analyse)

Date : 2026-08-16

## Objectif

Fournir à l’analyste une surface d’**analyse** sans décider à sa place :

- rythme d’activité (Pattern of Life) ;
- carte de densité (heatmap positions) ;
- contradictions explicables ;
- rapprochements proposés ;
- anomalies avec justification métier.

Les constats restent des **propositions** (retenir / écarter). Aucune confirmation d’identité automatique.

## Schéma

Migration : `bootstrap/atak_sse_analysis_lot6_migration.php` (branchée dans `run-migrations.php`).

| Table | Rôle |
|-------|------|
| `sse_analysis_findings` | Constats (contradiction, anomalie, écart de rythme, rapprochement) |
| `sse_pol_snapshots` | Instantanés de profil d’activité |

## Service

`App\Services\Sse\SseAnalysisService` :

1. agrège les événements normalisés sur une fenêtre (14 j. par défaut) ;
2. calcule histogrammes horaires / jours + zones récurrentes ;
3. produit une grille de densité (`pos_x`/`pos_y` ou lat/lng) ;
4. détecte présences impossibles (écart spatial / fenêtre temporelle) ;
5. importe les contradictions de la file de suggestions ;
6. signale écarts de rythme, zones inhabituelles, biais d’origine unique — **avec texte d’explication**.

## API

- `GET /api/sse/v1/analysis?case_id=&entity_uuid=&days=`
- `POST /api/sse/v1/analysis/findings/{id}/decide`

## UI

Onglet **Analyse** du workspace (`#analyse`) :

- barres du rythme d’activité ;
- top zones de densité ;
- listes contradictions / rapprochements / anomalies ;
- actions Retenir / Écarter (CSRF).

Inbox : contradictions ouvertes (plus de placeholder).

## Vérification

1. Lancer les migrations (LOT 6).
2. Ouvrir le workspace → onglet Analyse.
3. Avec des événements géolocalisés : voir densités + éventuelles contradictions.
4. `GET /api/sse/v1/analysis` renvoie `pattern_of_life`, `heatmap`, `counts`.
5. Retenir / écarter un constat → statut mis à jour.

## Hors LOT 6 (reporté)

Heatmap live sur carte ATAK (calques LOT 5 restent la projection géo), replay PoL animé, scoring ML.
