# Lot 6 : rapports de cohérence (lecture seule)

Cinq scripts en ligne de commande, dans `tools/`, préparent les changements de modèle du lot 3 : le plan demande un rapport AVANT chaque correction, jamais de mise à jour silencieuse. Ils ne modifient rien.

| Script | Constat | Lot concerné |
|---|---|---|
| `tools/audit-report-grades-campagnes.php` | RD-04 : grades publiés par une campagne d'avancement mais non appliqués à la fiche du membre (`users.grade_id`) | 3.3 |
| `tools/audit-report-affectations-postes.php` | RD-21 : (A) affectations principales sans poste ORBAT dans la même unité ; (B) postes occupés sans affectation dans l'unité du poste | 3.4 |
| `tools/audit-report-logos.php` | RD-44 : logos différents entre `tenants.logo_url` et `tenant_branding.logo_url`, avec la valeur la plus récente | 3.10 |
| `tools/audit-report-aar-liens.php` | RD-19 : événements passés sans compte rendu, liens ambigus, quasi-correspondances de titre, comptes rendus orphelins | 3.1 |
| `tools/audit-report-roles-semes.php` | RD-03 : rôles semés non visibles qui portent des droits, et « qui perd quoi » par membre | 3.9 |

## Garanties de lecture seule

- Une option d'écriture (`--write`, `--apply`, `--fix`, `--update`, `--delete`, `--commit`, `--force`, `--repair`, `--migrate`, `--purge`, `--sync`) est refusée (code 2).
- La session MySQL est ouverte avec `SET SESSION TRANSACTION READ ONLY` puis `START TRANSACTION READ ONLY` : le serveur refuse toute écriture.
- Toute requête passe par `AuditReadOnlyDb::rows()`, qui n'accepte qu'un `SELECT` ou `WITH … SELECT`, en une instruction, sans mot d'écriture.
- Exécution en ligne de commande uniquement.
- `--selftest` vérifie le garde-fou sans ouvrir de connexion.

## Comment les lancer

La connexion est celle de l'application : `app/Config/database.local.php`, sinon `.env` (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`). Un script lit donc la base que cette configuration désigne. Ne pas les lancer depuis un poste de développement dont la configuration pointe vers la production.

Depuis la racine du site, sur la machine qui héberge la base à contrôler :

```
php tools/audit-report-grades-campagnes.php
php tools/audit-report-affectations-postes.php --tenant=12
php tools/audit-report-logos.php --format=csv > logos.csv
php tools/audit-report-aar-liens.php --limit=500
php tools/audit-report-roles-semes.php --format=json > roles-semes.json
```

Options communes : `--tenant=ID` (une communauté), `--format=text|csv|json` (CSV avec « ; »), `--limit=N` (lignes affichées par section, 200 par défaut), `--selftest`, `--help`.

Code de retour : 0 sans écart, 3 avec au moins un écart, 1 si une table attendue manque, 2 pour une option refusée.

Les extractions CSV et JSON contiennent des noms de membres : les traiter comme des données personnelles (pas de dépôt dans le dépôt de code, pas de message public).

## Comment les requêtes sont vérifiées

`tests/Unit/AuditReportQueriesTest.php` charge les colonnes des `CREATE TABLE` des migrations (`migrations/schema.sql`, `migrations/personnel_dossier.sql`, `bootstrap/advancement_grade_migration.php`, `bootstrap/personnel_capability_axes_migration.php`, etc.) dans une base SQLite en mémoire, y exécute chaque rapport sur quelques lignes de test, et vérifie qu'il trouve l'écart attendu et ignore le cas sain. Une colonne ou une table fausse dans une requête fait échouer le test. Limite : SQLite ne contrôle pas la syntaxe propre à MySQL ; les requêtes n'utilisent que du SQL standard (jointures, `NOT EXISTS`, `COALESCE`, `CASE`).

## Hypothèses à confirmer sur une vraie base

- Le grade publié et le grade de la fiche sont rapprochés par code, sinon par libellé (même règle que `AdvancementWorkflowService::matchFicheToDefinition`) : un référentiel dont les codes diffèrent peut faire apparaître de faux écarts.
- Un poste est « actif » comme dans `OrbatBilletRepository` (`is_active`, `status`, `archived_at`) ; un membre est « actif » si `users.status = 'active'`.
- Les droits d'un membre viennent de `tenant_user_roles` (lignes en cours de validité), sinon de `users.role_id`, comme `RbacService`. Les droits « unité » ne sont pas distingués dans « qui perd quoi » (le périmètre est indiqué entre parenthèses).
