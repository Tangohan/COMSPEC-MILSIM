# Data Inspector : « Sources non disponibles : terminaux »

- **Contexte** : `/back-office/atak/data-inspector`, lectures de `AthenaInspectorMetricsRepository`.
- **Symptôme** : le bandeau annonce « Sources non disponibles sur cette installation : terminaux » et les sections terminaux restent vides.
- **Cause** : toutes les lectures « terminaux » interrogent `atak_terminals` (et `atak_terminal_sync`). Ces tables sont créées par des étapes de migration archivées, jamais rejouées sur une installation qui ne les avait pas reçues : la requête échoue, la source est déclarée absente. Aucune requête n'est fausse (colonnes vérifiées dans `bootstrap/atak_realism_registry_migration.php`).
- **Correctif** : nouvelle étape active `4000_atak_aar_annexes_syntheses.php` qui crée les deux tables si elles manquent (code des migrations d'origine, idempotent).
- **Fichiers touchés** : `database/steps/4000_atak_aar_annexes_syntheses.php`, `database/steps/registry.php`.
- **Vérification** : cause établie par lecture du code, sans accès à la base de production. Après la migration, le bandeau doit disparaître ; s'il reste, le journal PHP contient `[data-inspector] terminaux : <erreur SQL>` avec la vraie cause.
- **Statut** : corrigé sous réserve, à vérifier en production.
