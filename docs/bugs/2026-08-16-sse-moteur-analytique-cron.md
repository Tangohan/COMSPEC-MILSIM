# Moteur analytique SSE — file de suggestions + cron nocturne

## Contexte
Besoin d’un pipeline qui propose des rapprochements / anomalies / lacunes
sans jamais trancher une hypothèse ni fusionner des dossiers.

## Principe
Le moteur produit « possible / probable / candidat ». Seule la validation
humaine crée une relation analytique. Fusion jamais automatique.

## Correctif V1
- Tables `sse_suggestion_queue`, `sse_engine_signals`, `sse_case_completeness`
- `SseCompletenessService` — score 0–100 + digest de changements
- `SseAnalyticalEngineService` — pipeline INGESTION…SYNTHÈSE
- Cron `sse_analytical_nightly` via `CronRunner`
- UI `/atak/sse/rapprochements` + panneau dossier 01.14
- Pont léger intel terrain (INFANTRY/VEHICLE/…) → propositions pré-SSE

## Fichiers
- `bootstrap/atak_sse_engine_migration.php`, `run-migrations.php`
- `app/Repositories/SseSuggestionQueueRepository.php`
- `app/Services/Sse/SseCompletenessService.php`, `SseAnalyticalEngineService.php`
- `app/Services/Cron/Jobs/SseAnalyticalNightlyCronJob.php`, `Container.php`
- `SsePortalController`, `routes/web.php`, `_layout.php`
- `views/atak/sse/suggestions.php`, `partials/case_engine.php`, `case_progress.php`

## Vérification
1. Migrations
2. `php scripts/cron-run.php sse_analytical_nightly`
3. Ou bouton « Lancer un passage » sur /atak/sse/rapprochements
4. Valider / rejeter une proposition → registre + relation éventuelle

## Statut
corrigé (fondation moteur V1)
