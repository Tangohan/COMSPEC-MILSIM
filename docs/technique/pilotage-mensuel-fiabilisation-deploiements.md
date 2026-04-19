# Pilotage mensuel (P1.4) & fiabilisation migrations/déploiements

Ce runbook met en place les demandes suivantes:

1. **Indicateurs essentiels** (4xx/5xx, p95 routes clés, conversion enrôlement, complétion formations, usage courrier/signature).
2. **Tableau de bord mensuel** automatisé.
3. **Workflow de migration idempotent** pré-prod → prod.
4. **Scripts post-déploiement** (smoke tests).
5. **Rollback minimal** par module critique.

---

## 1) Indicateurs essentiels

### Source de vérité

- **`request_telemetry`**: statut HTTP + latence par route (middleware global).
- **`usage_analytics_events`**: conversion enrôlement (ouverture formulaire vs soumission).
- **`training_enrollments`**: complétion de formation.
- **`courrier_documents`** + **`user_signatures`**: usage courrier/signature.

### Définitions KPI

- **Erreurs 4xx / 5xx**: comptage brut sur la période.
- **p95 latence routes clés**: percentile 95 des durées (`duration_ms`) pour:
  - `/dashboard`
  - `/formations`
  - `/formations/enroll`
  - `/courrier`
  - `/courrier/documents/{id}/sign`
- **Conversion enrôlement**: `enlistment_submitted / enlistment_form_open`.
- **Taux de complétion formations**: `enrollments.status='completed' / enrollments assignés dans le mois`.
- **Usage courrier/signature**:
  - courriers signés / courriers créés,
  - signatures utilisateur créées sur la période.

---

## 2) Tableau de bord mensuel

Script:

```bash
php scripts/monthly-pilotage-dashboard.php --month=2026-04
```

Options utiles:

- `--tenant-id=12` pour une communauté précise.
- `--output=/tmp/pilotage-2026-04.md` pour chemin custom.

Sortie par défaut:

- `storage/intel/pilotage-mensuel-YYYY-MM.md`

Planification recommandée (cron, le 1er de chaque mois à 02:15 UTC):

```cron
15 2 1 * * cd /var/www/COMSPEC-MILSIM && php scripts/monthly-pilotage-dashboard.php
```

---

## 3) Workflow idempotent migration (pré-prod → prod)

### Pré-prod

1. Sauvegarde base pré-prod.
2. Exécution pipeline:
   ```bash
   php setup-database.php
   ```
3. Vérification schéma:
   ```bash
   php scripts/check-c2-schema.php
   ```
4. Smoke tests:
   ```bash
   php scripts/post-deploy-smoke-tests.php --base-url=https://preprod.votre-domaine.tld
   ```
5. Génération dashboard mensuel pour valider la chaîne KPI.

### Prod

1. Sauvegarde base prod + validation restore.
2. Fenêtre de déploiement courte.
3. Exécution pipeline idempotent:
   ```bash
   php setup-database.php
   ```
4. Smoke tests immédiats:
   ```bash
   php scripts/post-deploy-smoke-tests.php --base-url=https://votre-domaine.tld
   ```
5. Validation KPI J+1 via `monthly-pilotage-dashboard.php`.

> Le pipeline est conçu pour être relançable: `CREATE TABLE IF NOT EXISTS` + migrations défensives déjà présentes.

---

## 4) Script de smoke tests post-déploiement

```bash
php scripts/post-deploy-smoke-tests.php --base-url=https://votre-domaine.tld
```

Le script vérifie:

- `/api/health`
- `/dashboard`
- `/formations`
- `/courrier`

Critère: réponse HTTP `< 500` + latence mesurée.

---

## 5) Rollback minimal (modules critiques)

### Auth / accès global

- Action immédiate: activer maintenance ciblée (`scripts/toggle-maintenance.php`) et restaurer release N-1.
- Si schéma en cause: restaurer backup DB prise avant migration.

### LMS / formations

- Symptômes: erreurs `/formations` ou API training.
- Rollback minimal:
  1. repasser release N-1,
  2. conserver DB si migration additive non bloquante,
  3. sinon restore DB pré-déploiement.

### Courrier / signature

- Symptômes: erreurs `/courrier`, signature KO.
- Rollback minimal:
  1. repasser release N-1,
  2. vérifier tables `courrier_*`, `user_signatures`,
  3. restore DB si corruption ou migration destructive non prévue.

### Observabilité / KPI

- Si `request_telemetry` indisponible: les KPI 4xx/5xx/p95 passent en **N/D** dans le dashboard.
- Correction: relancer `php setup-database.php` puis vérifier création table.

---

## Checklist opérationnelle mensuelle

- [ ] `php setup-database.php` validé sur pré-prod.
- [ ] `php scripts/post-deploy-smoke-tests.php` OK sur pré-prod.
- [ ] déploiement prod exécuté.
- [ ] smoke tests prod OK.
- [ ] `php scripts/monthly-pilotage-dashboard.php` généré et archivé.
