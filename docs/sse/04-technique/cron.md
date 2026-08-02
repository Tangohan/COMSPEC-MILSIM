# SSE — Traitements automatisés et tâches planifiées

| Fréquence | Traitement | Sortie autorisée |
|---|---|---|
| 2 min | ingestion terrain idempotente | acquisition brute |
| 5 min | normalisation/indexation | index versionné |
| 10 min | rapprochements | propositions |
| 30 min | contradictions/doublons | signaux analytiques |
| 1 h | relations/tendances | propositions de liaison |
| nuit | réanalyse/obsolescence | scores recalculés/archives |

Chaque job reçoit explicitement un `tenant_id`, prend un verrou par tenant et type, journalise début/fin/version/erreur, reprend sans doublonner et ne déclenche jamais fusion ni consolidation. Une file opérateur reçoit les sorties.
