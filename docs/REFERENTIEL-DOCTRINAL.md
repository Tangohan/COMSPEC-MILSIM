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

## Référentiel des échelons et adéquation de l’organisation

Chaque échelon (équipe / fire team, groupe / squad, section / platoon, compagnie / company, bataillon / battalion,
régiment) porte des attentes : fonction du chef, adjoint attendu ou non, plage de grades du chef et de l’adjoint,
effectif de référence et sous-unités attendues. Code : `App\Services\Organization\EchelonStandards` (valeurs par
défaut), `OrgAdequacySettings` (réglages par communauté), `OrgAdequacyEngine` (moteur déterministe),
`OrgAdequacyService` (chargement), `OrgAdequacyView` (extraits fiche, unité, ORBAT).

Valeurs par défaut (niveaux OTAN STANAG 2116 : OR-n, OF-n ; les codes US E-n / O-n du référentiel des grades y sont
ramenés) :

| Échelon | US : chef | US : adjoint | FR : chef | FR : adjoint | Effectif US / FR | Sous-unités |
| --- | --- | --- | --- | --- | --- | --- |
| Équipe / Fire team | Team leader, SGT (OR-5) | aucun | Chef d’équipe, CCH–SGT (OR-4–5) | aucun | 4 / 3–4 | — |
| Groupe / Squad | Squad leader, SSG (OR-6) | team leader le plus ancien, SGT (adjoint de fait) | Chef de groupe, SGT–SCH (OR-5–6) | chef de la seconde équipe (adjoint de fait) | 8–9 / 8–10 | 2 équipes |
| Section / Platoon | Platoon leader, 2LT/1LT (OF-1) | Platoon sergeant, SFC (OR-7) | Chef de section, ADJ à LT (OR-7–OF-1) | sous-officier adjoint, SCH–ADJ | 30–42 / 30–40 | 3–4 groupes |
| Compagnie / Company | Company commander, CPT (OF-2) | First sergeant ou XO (OR-8–OF-1) | Commandant d’unité, CNE (OF-2) | officier adjoint, LT–CNE | 100–150 | 3–4 (US) / 3–5 (FR) sections |
| Bataillon / Battalion | Battalion commander, LTC (OF-4) | CSM ou XO (OR-9–OF-3) | Chef de bataillon, CDT–LCL | officier adjoint, CNE–CDT | 500–800 | 3–5 compagnies |
| Régiment | COL (OF-5), ordre de grandeur | CSM ou XO | Chef de corps, COL | chef de corps adjoint, LCL | 1 500–3 500 / 600–1 200 | 2–4 bataillons / 3–8 compagnies |

Sources : ATP 3-21.8 *Infantry Platoon and Squad* (2016, remplace FM 3-21.8), ATP 3-21.10 *Infantry Rifle Company*,
ATP 3-21.20 *Infantry Battalion*, DA PAM 600-25 (cursus NCO) ; documentation publique de l’armée de Terre sur
l’organisation de l’infanterie, Code de la défense art. L4131-1 (hiérarchie des grades) ; STANAG 2116.

Les peloton, escadron et batterie sont évalués comme la section et la compagnie. L’échelon d’une unité est celui
choisi dans l’ORBAT (`units.echelon`), sinon celui déduit de son nom et de son type.

Écarts produits (chacun expliqué en clair, avec des actions) : chef à désigner, sous-capacité de commandement (grade,
qualification de commandement ou emploi du chef ou de l’adjoint sous l’attendu), adjoint attendu manquant,
surqualification (grade d’au moins *marge* niveaux au-dessus de la plage, 2 par défaut, ou qualifications d’un échelon
supérieur), sous-effectif, sureffectif, sous-unités manquantes, échelon ou grade à renseigner, personne sans place,
personne sans chef. Formulation neutre : capacité de commandement du poste, jamais jugement sur la personne.

Réglages (Effectifs › Chaîne › Référentiel et réglages de l’adéquation, stockés dans `settings.admin_runtime.org_adequacy`
de la communauté, sans migration) : contrôle actif, référentiel appliqué (doctrine de la communauté, FR ou US), marge de
surqualification, règles activables, et par échelon : actif, plages de grades du chef et de l’adjoint, adjoint exigé,
effectif, sous-unités, qualifications de commandement et emploi attendus du chef. Seules les valeurs modifiées sont
stockées ; le reste suit le référentiel.

Affichage : chaîne de commandement (pastilles, adjoint sous chaque chef, panneau des écarts, filtre `?ecarts=1`),
fiche membre du bureau effectifs et fiche personnel (« Votre place dans l’organisation » pour le titulaire), fiche
unité, ORBAT (pastille par unité, gestionnaires seulement, jamais en aperçu « vu par un membre »), alertes RH.

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

`doctrine_demo_cleanup.php` archive, pour chaque tenant, uniquement ces documents (référence **et** titre ou slug). Idempotent. Ne touche pas SIC/ATAK/2026-001 ni DRH/PERS/2026-001, ni un dépôt utilisateur dont la référence ou le titre diffère, ni un média pédagogique (catégorie `media`).

`doctrine_atak_employment_seed.php` ajoute idempotemment la doctrine **SIC/ATAK/2026-001** (*Doctrine d’emploi d’ATAK / Overwatch Athena*). Le fichier officiel est le manuel PDF v1.1. Si un stub de démonstration existe déjà (résumé « Document de démonstration » ou fichier `storage/documents/demo/`), le manuel officiel est substitué sans créer de prises en compte. Un ancien pointeur Markdown est remplacé par ce PDF.

`doctrine_rh_employment_seed.php` ajoute idempotemment la doctrine **DRH/PERS/2026-001** (*Doctrine d’emploi RH — Recrutement et Avancement*). Manuel officiel **FM ATHENA RH-01** PDF v1.1, **lecture et prise en compte obligatoires**, diffusion **tous les membres**. Même logique d’upgrade stub → PDF que pour ATAK ; une ancienne v1.0 au pointeur officiel est alignée sur v1.1.

Déploiement : `php run-migrations.php` (ou `setup-database.php`). Un rechargement de page ne suffit pas. Seeds isolés : `php bootstrap/run_doctrine_atak_seed.php`, `php bootstrap/run_doctrine_rh_seed.php`.

## Évolutions prévues

- Relances automatiques planifiées (avant / après échéance) via cron ;
- Admin plateforme `/admin/system/documents` ;
- Export CSV conformité ;
- Onglet lectures obligatoires enrichi sur la fiche personnel ;
- Panneau latéral détail dans le registre.

## Publication interne (2026-09)

Le référentiel porte aussi les **notes de service**, **instructions**, **directives** et autres types configurables :

- `/back-office/documents/types` — référentiel de types ;
- `/back-office/documents/publier` — rédaction, ciblage, publication ;
- `/documents/mes-documents` — boîte personnelle ;
- `/back-office/documents/compliance` — suivi de diffusion + relances ;
- Migration : `bootstrap/document_publication_system_migration.php`.
