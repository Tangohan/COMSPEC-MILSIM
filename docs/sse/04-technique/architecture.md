# SSE — Architecture technique et dictionnaire synthétique

Le module suit MVC : routes sous `/atak/sse`, contrôleurs d’orchestration, repositories filtrés par `tenant_id`, services de doctrine/matching et vues sans accès SQL.

## Tables cibles

`sse_interest_cases`, `sse_case_status_history`, `sse_persons`, `sse_person_identities`, `sse_aliases`, `sse_observations`, `sse_biometrics`, `sse_identifiers`, `sse_sites`, `sse_vehicles`, `sse_materials`, `sse_relationships`, `sse_matches`, `sse_match_factors`, `sse_match_reviews`, `sse_collection_requirements`, `sse_reports`, `sse_media`, `sse_sources`, `sse_exports`, `sse_audit_logs`, `sse_processing_jobs`.

Chaque table métier porte `tenant_id`; chaque clé naturelle est unique dans le tenant. Une lecture par identifiant combine toujours `id` et `tenant_id`. Les médias portent empreinte, sensibilité, auteur, acquisition et conservation. Les journaux de décision sont append-only.
