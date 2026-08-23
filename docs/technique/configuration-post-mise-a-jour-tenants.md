# Configuration post-mise à jour des communautés

## Objectif

Faire évoluer la plateforme sans casser les communautés créées avant une nouveauté.

- **Migration technique** : schéma, conversions sûres, NULL maîtrisé.
- **Configuration métier** : décisions humaines via un moteur générique (non bloquant par défaut).

## Composants

| Élément | Rôle |
|---------|------|
| `system_configuration_updates` | Référentiel des évolutions (code stable) |
| `tenant_configuration_updates` | État lazy par communauté (absence = `PENDING`) |
| `ConfigurationUpdateCatalog` | Déclarer une nouveauté + probes d’éligibilité |
| `ConfigurationUpdateService` | API centrale (`list`, `mark*`, `hubSummary`, …) |
| `/back-office/mise-a-niveau` | Centre durable |
| `/back-office/nouveautes-organisation` | Première visite (une fois) |

## Déclarer une nouvelle évolution

1. Ajouter une `ConfigurationUpdateDefinition` dans `ConfigurationUpdateCatalog`.
2. Ajouter les probes dans `ConfigurationUpdateProbes` si besoin.
3. Seed SQL dans `bootstrap/configuration_updates_migration.php` (upsert).
4. Pointer `configure_path` vers l’écran settings **existant**.
5. Vérifier que `TenantBootstrapService` marque les nouveaux tenants comme satisfaits.

Ne pas disperser des `if (created_at < …)` dans les contrôleurs métier.

## Statuts

`PENDING` → `SEEN` → `IN_PROGRESS` → `COMPLETED`  
aussi : `DISMISSED` (avec `remind_at` optionnel), `NOT_APPLICABLE`.

## Niveaux

- `informative` — info, aucune action requise
- `recommended` — conseillé, site utilisable sans
- `required` — nécessaire pour **une** fonction (pas toute la plateforme)

## Routes

```text
GET  /back-office/mise-a-niveau
GET  /back-office/nouveautes-organisation
POST /back-office/nouveautes-organisation/continuer
POST /back-office/mise-a-niveau/demarrer
POST /back-office/mise-a-niveau/ignorer
POST /back-office/mise-a-niveau/rouvrir
```

Permission : `tenant.configuration.manage` (owners/admins via catalogue ; aussi `admin.organization` / `admin.settings.manage`).

## Déploiement

```bash
php setup-database.php
# ou run-migrations.php — exécute configuration_updates_migration
```

## Evolutions initiales seedées

- `MILITARY_AFFILIATION_V1`
- `TIMEZONE_V1`
- `GRADE_SYSTEM_V1`
- `ORGANIZATION_STRUCTURE_V1`
- `PUBLIC_PROFILE_V1`
- `ATAK_CONFIGURATION_V1` (éligibilité selon type de communauté)
- `SSE_PERSONS_V1`
- `SSE_DIGITAL_LAB_V1` — laboratoire numérique
- `SSE_DOMEX_QUEUE_V1` — file « à exploiter » ; configure via le laboratoire, nouveaux tenants marqués satisfaits
- `ATAK_INTEL_SCRAMBLE_V1` — données chiffrées roleplay (certificat / capture) ; configure via `admin/atak/roleplay#intel-scramble` ; nouveaux tenants : domaine « Réseau ami » seedé + marqué satisfait
