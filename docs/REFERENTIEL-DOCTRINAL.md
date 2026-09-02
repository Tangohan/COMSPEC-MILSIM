# Référentiel doctrinal ATHENA

## Vue d’ensemble

Le module **Référentiel doctrinal** étend le module Documents existant sans le remplacer. Lorsqu’un document appartient à la catégorie **Doctrine / SOP** (`document_categories.slug = doctrine`), il entre dans un workflow documentaire enrichi :

- référence officielle structurée ;
- versionnement avec historique ;
- diffusion flexible (ORBAT, grades, rôles, membres) ;
- prise en compte / signature électronique par version ;
- suivi de conformité admin ;
- doctrines **tenant** ou **plateforme** (`documents.scope`).

## Architecture

| Couche | Rôle |
|--------|------|
| `documents` | Fichier, titre, slug, statut publication, catégorie |
| `document_doctrines` | Métadonnées doctrinales (référence, autorité, exigence, échéance) |
| `document_versions` | Versions fichier + `version_major/minor`, `acknowledgment_reset` |
| `document_reference_domains` | Nomenclature tenant (codes, préfixes) |
| `document_audiences` | Cibles de diffusion |
| `document_acknowledgments` | Preuves de prise en compte |
| `document_views` | Consultations (distinct de la signature) |

### Services métier

- `DocumentAudienceResolver` — calcule si un membre est concerné.
- `DocumentComplianceService` — statuts `NOT_APPLICABLE`, `UNREAD`, `ACK_REQUIRED`, `ACKNOWLEDGED`, `ACK_OUTDATED`, `OVERDUE`.
- `DoctrineReferenceService` — génération `[SERVICE]/[DOMAINE]/[ANNÉE]-[NUMÉRO]`.
- `DoctrineAcknowledgmentService` — signature serveur avec CSRF, snapshot profil, hash d’intégrité.

## Routes principales

| Route | Description |
|-------|-------------|
| `GET /documents?category={id_doctrine}` | Registre tableur |
| `GET /documents/doctrine/{id}` | Fiche doctrine + modal prise en compte |
| `POST /documents/doctrine/{id}/acknowledge` | Signature |
| `GET /back-office/documents/nomenclature` | Admin nomenclature |
| `GET /back-office/documents/compliance` | Matrice conformité |

## Workflow documentaire

Statuts (`document_doctrines.doctrine_status`) alignés sur le cycle documents :

`draft` → `review` → `approval` → **`published`** → `suspended` / `obsolete` / `archived`

Seules les doctrines **publiées** sont opposables aux membres.

### Versionnement

Une modification d’une doctrine publiée crée une **nouvelle ligne** `document_versions`. Le flag `acknowledgment_reset` force une nouvelle prise en compte si la modification est doctrinale.

## Prise en compte

1. Le membre **consulte** le document → enregistrement `document_views`.
2. Si `acknowledgment_required` ou `requirement_level = mandatory`, un **modal bloquant** s’affiche.
3. Le membre coche la certification et envoie → `document_acknowledgments` avec snapshot grade/unité/référence/version/hash.
4. Une signature v1.0 **ne couvre pas** v2.0 si reset demandé.

## ORBAT

L’autorité émettrice et la diffusion utilisent :

- `units` (éléments ORBAT, préfixe documentaire optionnel sur l’unité) ;
- `personnel_job_roles` ;
- grades (`personnel_profiles`) ;
- rôles ATHENA (`tenant_user_roles`).

Aucune structure organisationnelle parallèle n’est créée.

## Tenant vs plateforme

| Scope | `tenant_id` | Édition |
|-------|-------------|---------|
| `tenant` | communauté | admins tenant |
| `platform` | NULL | super-admin uniquement |

Les requêtes tenant **filtrent toujours** `tenant_id` depuis la session. Les doctrines plateforme sont lues via `(dd.tenant_id = ? OR dd.scope = 'platform')` sans duplication par tenant.

## Permissions (RBAC)

| Slug | Usage |
|------|-------|
| `doctrine.view` | Consulter le référentiel |
| `doctrine.create` / `doctrine.edit` | Création / édition |
| `doctrine.publish` | Publication |
| `doctrine.manage_audience` | Diffusion |
| `doctrine.view_compliance` | Suivi admin |
| `doctrine.send_reminders` | Relances (service préparé) |
| `platform_doctrine.manage` | Réservé site (à brancher admin système) |

## Sécurité multi-tenant

- Jamais de lecture/écriture tenant par ID seul.
- Contexte tenant = `Session::get('tenant_id')` exclusivement.
- Audit : `AuditService` + `document_audit_log` sur publication, signature, etc.

## Migration

Exécuter `run-migrations.php` (bootstrap `doctrine_referential_migration.php`).

Fichier SQL : `migrations/20260902120000_doctrine_referential.sql`

## Seed ATAK et nettoyage des exemples

`doctrine_demo_seed.php` n’insère plus de documents : c’est le catalogue des paires référence / titre de l’ancien seed (hors SIC/ATAK).

`doctrine_demo_cleanup.php` archive, pour chaque tenant, uniquement ces documents (référence **et** titre ou slug). Idempotent. Ne touche pas SIC/ATAK/2026-001, ni un dépôt utilisateur dont la référence ou le titre diffère, ni un média pédagogique (catégorie `media`).

`doctrine_atak_employment_seed.php` ajoute idempotemment la doctrine **SIC/ATAK/2026-001** (*Doctrine d’emploi d’ATAK / Overwatch Athena*). Le fichier à consulter est le manuel d’emploi Athena C2 version 1.1 (PDF). Un ancien stub ou un fichier markdown est remplacé par ce manuel, sans créer de prises en compte.

Déploiement : `php run-migrations.php` (ou `setup-database.php`). Un rechargement de page ne suffit pas.

## Évolutions prévues

- Assistant création 6 étapes complet (BO) ;
- Relances e-mail via `DoctrineNotificationService` ;
- Admin plateforme `/admin/system/documents` ;
- Export CSV conformité (lien préparé) ;
- Panneau latéral détail dans le registre.
