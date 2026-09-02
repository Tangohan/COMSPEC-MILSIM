# DEPLOY — Migration ATHENA (back-office)

Checklist de déploiement pour **athena.ttrd.fr** : coque ATHENA (sidebar, topbar, KPIs, tableaux) et pages back-office migrées.

---

## Déploiement production (GitHub private → VPS)

Le déploiement nominal est **GitHub Actions → SSH → `git pull`** sur `72.62.22.55`
(`/var/www/athena.ttrd.fr`). Le dépôt GitHub est **privé**. Le VPS a une *deploy key*
en lecture seule vers `git@github.com:Tangohan/COMSPEC-MILSIM.git`. On ne pousse
**jamais** depuis le VPS : uniquement depuis le PC vers GitHub, le VPS tire.

Le FTP Hostinger et l’intégration Git hPanel sont abandonnés.

Flux normal :

1. **Commit** local des changements web.
2. **`git push origin main`**
3. L’Action **Deploy VPS** se connecte en SSH et exécute `git pull --ff-only` +
   `composer install --no-dev`.

### Secret GitHub (une seule fois)

Dépôt → *Settings → Secrets and variables → Actions* :

| Secret | Valeur |
|---|---|
| `VPS_SSH_KEY` | Clé **privée** dont la publique est dans `/root/.ssh/authorized_keys` du VPS |

Le `.env` production, `vendor/` et `storage/uploads` **ne sont pas** dans Git : un
`git pull` ne les écrase pas.

### Première fois sur le VPS (sparse-checkout)

Le clone complet pèse ~1,5 Go (mod Arma, archives, worktrees). Le site n’en a pas
besoin. Après le premier pull qui contient `deploy/vps-sparse-checkout.txt`,
l’Action réapplique le cone. À la main :

```bash
cd /var/www/athena.ttrd.fr
git remote set-url origin git@github.com:Tangohan/COMSPEC-MILSIM.git
git sparse-checkout init --cone
git sparse-checkout set $(tr '\n' ' ' < deploy/vps-sparse-checkout.txt)
```

Sans GitHub Actions (secours) : un cron `git fetch && git pull --ff-only` toutes
les minutes — plus simple, un peu moins propre. Ne pas cumuler cron **et** Action.

Les migrations SQL ne sont **pas** lancées par l’Action (décision humaine) :
`php8.4 run-migrations.php` en SSH après un commit qui change le schéma.

### Config locale (PC)

`.deploy.env` (gitignoré) en `DEPLOY_MODE=git` — le hook Cursor rappelle seulement
de committer / pousser vers `main`.

---

## Action utilisateur

1. Sauvegarder BDD + fichiers sur le serveur.
2. Pousser via Git (`git push origin main`) — l’Action VPS tire le commit.
3. Exécuter `php8.4 run-migrations.php` (SSH) si le commit touche le schéma.
4. Vider le cache opcode PHP si le reload FPM n’a pas suffi.
5. Tester les URLs de la section 8.

---

## 1. Migrations base de données (obligatoire)

```bash
php run-migrations.php
```

| Fichier bootstrap / SQL | Effet |
|---|---|
| `bootstrap/tenant_alerts_visual_migration.php` | Visuels annonces (`display_style`, images, bannières) |
| `bootstrap/tenant_alerts_features_migration.php` | `features_json` sur `tenant_alerts` |
| `bootstrap/community_event_rsvp_nominative_migration.php` | RSVP nominatif (disponibilité, relances, commentaire orga) |
| `migrations/20260419180000_tenant_required_role_definitions.sql` | Fonctions obligatoires Cellule S1 |

Post-check :

- [ ] `tenant_alerts.features_json` existe
- [ ] `community_event_rsvps` : colonnes disponibilité / relances / commentaire orga
- [ ] `tenant_required_role_definitions` existe

---

## 2. Fichiers à uploader — par domaine

### Shell / Sidebar / Layout

| Fichier | Rôle |
|---|---|
| `views/layout/main.php` | Coque ATHENA (`ath-bo-shell`, chargement CSS/JS) |
| `views/partials/back_office_sidebar.php` | Aside latéral |
| `views/partials/back_office_topbar.php` | Barre supérieure (fil d'Ariane, alertes) |
| `views/partials/ath_sidebar_nav.php` | Données navigation groupée |
| `app/Support/BackOfficePageContext.php` | Résolution auto titres / CSS / shell |
| `config/back_office_pages.php` | Métadonnées pages (kicker, titre, CSS page) |
| `public/assets/css/back-office-shell.css` | Styles coque ATHENA |
| `public/assets/css/back-office-rail.css` | Rail latéral |
| `public/assets/js/back-office-sidebar.js` | Toggle sidebar mobile / repli |

### Composants ATHENA partagés

| Fichier | Rôle |
|---|---|
| `views/partials/ath_kpis.php` | Bandeau KPI |
| `views/partials/ath_table.php` | Tableau standard (toolbar, badges, export) |
| `views/partials/ath_alerts.php` | Bandeau alertes dashboard |
| `app/Support/AthUi.php` | Badges / tons / métadonnées cellules |

### Pilotage (Dashboard)

| Fichier | Statut |
|---|---|
| `views/partials/ath_dashboard_dash.php` | ✅ ATHENA complet |
| `views/admin/organization/dashboard.php` | ✅ Branche ATHENA |
| `app/Controllers/Admin/Organization/OrganizationDashboardController.php` | Contrôleur |
| `public/assets/css/back-office-dashboard.css` | CSS page |
| `public/assets/css/announce-tiles.css` | Tuiles annonces dashboard |

### Opérations / Événements

| Fichier | Statut |
|---|---|
| `views/partials/ath_events_ops.php` | ✅ Registre opérations |
| `views/partials/ath_event_show.php` | ✅ Fiche créneau |
| `views/partials/ath_event_rsvp_nominative.php` | ✅ Réponses nominatives |
| `views/admin/organization/events.php` | ✅ Branche ATHENA |
| `views/admin/organization/event_show.php` | ✅ Branche ATHENA |
| `views/admin/organization/event_rsvp_nominative.php` | ✅ Branche ATHENA |
| `views/admin/organization/partials/event_details_fields.php` | Champs fiche créneau |
| `app/Controllers/Admin/Organization/CommunityEventsAdminController.php` | Contrôleur |
| `app/Controllers/Admin/Organization/EventRsvpNominativeAdminController.php` | Contrôleur RSVP |
| `app/Controllers/Api/EventRsvpNominativeApiController.php` | API export / MAJ |
| `app/Services/Attendance/EventRsvpNominativeService.php` | Logique métier |
| `app/Repositories/EventRsvpNominativeRepository.php` | Accès BDD |
| `app/Repositories/CommunityEventRepository.php` | Accès événements |
| `public/assets/css/back-office-events.css` | CSS opérations |

### Communauté — Annonces & alertes

| Fichier | Statut |
|---|---|
| `views/partials/ath_tenant_alerts.php` | ✅ Liste annonces |
| `views/partials/ath_tenant_alerts_form.php` | ✅ Formulaire création/édition |
| `views/admin/organization/tenant_alerts_index.php` | ✅ Branche ATHENA |
| `views/admin/organization/tenant_alerts_form.php` | ✅ Branche ATHENA |
| `app/Controllers/Admin/Organization/TenantAlertsController.php` | Contrôleur |
| `app/Repositories/TenantAlertRepository.php` | Accès BDD |
| `app/Services/Alerts/AlertPresentationService.php` | Présentation |
| `app/Support/TenantAlertVisuals.php` | Icônes / couleurs |
| `app/Support/TenantAlertFeatures.php` | Options fonctionnelles |
| `app/Support/AlertDisplayStyle.php` | Libellés emplacements |
| `public/assets/css/back-office-alerts.css` | CSS formulaire annonces (cartes radio, aperçu, grille icônes) |

### Personnel / Effectifs

| Fichier | Statut |
|---|---|
| `views/partials/ath_personnel_job_roles_index.php` | ✅ Référentiel emplois (KPIs + nav) |
| `views/partials/ath_personnel_job_roles_referentiel.php` | ✅ Corps référentiel emplois |
| `views/admin/organization/personnel_job_roles/index.php` | ✅ Branche ATHENA |
| `views/admin/organization/personnel_job_roles/assignments.php` | ⚡ Hybride (KPIs ATHENA + tableau custom) |
| `views/admin/organization/personnel_job_roles/_role_combobox.php` | Combobox emplois |
| `views/admin/organization/effectifs_hub.php` | ⚡ Hybride (KPIs ATHENA + tuiles legacy) |
| `app/Controllers/Admin/Organization/PersonnelJobRoleAdminController.php` | Contrôleur |
| `app/Repositories/PersonnelJobRoleRepository.php` | Accès BDD |
| `public/assets/css/back-office-effectifs-hub.css` | CSS effectifs |
| `public/assets/js/pjr_role_combobox.js` | JS combobox |
| `public/assets/js/pjr_member_job_permissions.js` | JS modal autorisations |

### Membres / Invitations

| Fichier | Statut |
|---|---|
| `views/admin/organization/users/index.php` | ⚡ Hybride (`ath_kpis` + `ath_table`) |
| `views/admin/organization/users/create.php` | 🔶 Legacy (coque ATHENA seulement) |
| `views/admin/organization/users/edit.php` | 🔶 Legacy |
| `views/admin/organization/users/show.php` | 🔶 Legacy |
| `views/admin/organization/invitations.php` | ⚡ Hybride (KPIs ATHENA) |
| `views/admin/organization/invitations_sent.php` | ⚡ Hybride (KPIs ATHENA) |
| `app/Controllers/Admin/Organization/UserAdminController.php` | Contrôleur |
| `app/Controllers/Admin/Organization/InvitationAdminController.php` | Contrôleur |
| `public/assets/css/back-office-users.css` | CSS annuaire |
| `public/assets/css/invitations-sheet.css` | CSS invitations |

### Rôles / Accès / Cellule S1

| Fichier | Statut |
|---|---|
| `views/partials/ath_roles_table.php` | ✅ Table des rôles |
| `views/partials/ath_roles_functions.php` | ✅ Doctrine des fonctions |
| `views/partials/ath_roles_referentiel.php` | ✅ Référentiel liens doctrinaux |
| `views/partials/ath_roles_catalogue.php` | ✅ Catalogue fonctions |
| `views/admin/organization/roles/index.php` | ✅ Branche ATHENA |
| `views/admin/organization/roles_functions.php` | ✅ Branche ATHENA |
| `views/admin/organization/roles_functions_referentiel.php` | ✅ Branche ATHENA |
| `views/admin/organization/roles_functions_catalogue.php` | ✅ Branche ATHENA |
| `views/admin/organization/access_management/index.php` | ⚡ Hybride (KPIs + onglets legacy) |
| `views/admin/roles_permissions/index.php` | ⚡ Hybride (`ath_kpis` + `ath_table`) |
| `app/Controllers/Admin/Organization/RoleAdminController.php` | Contrôleur rôles |
| `app/Controllers/Admin/Organization/RolesFunctionsAdminController.php` | Contrôleur Cellule S1 |
| `app/Controllers/Admin/Organization/RolePermissionMatrixController.php` | Matrice permissions |
| `app/Controllers/Admin/Organization/AccessManagementController.php` | Gestion accès |
| `app/Controllers/Api/RolePermissionMatrixApiController.php` | API matrice |
| `app/Repositories/RolePermissionMatrixRepository.php` | Accès BDD matrice |
| `app/Services/Rbac/RolePermissionMatrixService.php` | Service matrice |
| `app/Support/RoleDoctrineUiLabels.php` | Libellés métier doctrine |
| `bootstrap/role_permission_matrix_migration.php` | Migration matrice |
| `public/assets/css/back-office-roles.css` | CSS rôles |
| `public/assets/css/back-office-doctrine.css` | CSS Cellule S1 |
| `public/assets/css/back-office-access.css` | CSS gestion accès |

### Système — Audit

| Fichier | Statut |
|---|---|
| `views/partials/ath_audit_log.php` | ✅ Journal d'audit |
| `views/admin/organization/audit.php` | ✅ Branche ATHENA |
| `app/Controllers/Admin/Organization/OrganizationAuditController.php` | Contrôleur |
| `app/Repositories/AuditLogRepository.php` | Accès BDD |
| `app/Services/Audit/AuditActionLabel.php` | Libellés actions |
| `app/Support/Audit/AuditSnapshotPresenter.php` | Détail entrées |

### Opérations — Comptes rendus (AAR)

| Fichier | Statut |
|---|---|
| `views/admin/aar_reports/index.php` | ✅ Liste ATHENA (KPIs, filtres pastilles, dépôt, `ath_table`) |
| `views/admin/aar_reports/show.php` | ✅ Lecture ATHENA (document + panneau latéral) |
| `views/admin/aar_reports/edit.php` | 🔶 Legacy (formulaire édition) |
| `views/admin/aar_reports/partials/form_fields.php` | Champs formulaire |
| `app/Controllers/Admin/AdminAarReportsController.php` | Contrôleur (KPIs, filtres, garde édition) |
| `app/Repositories/AarReportRepository.php` | Accès BDD + index CR registre opérations |
| `app/Core/Container.php` | Injection `AarReportRepository` |
| `app/Support/AthUi.php` | Badge « En relecture » |
| `public/assets/css/back-office-aar.css` | CSS AAR |

> La colonne **CR** du registre opérations (`ath_events_ops.php` + `CommunityEventsAdminController.php`) utilise `operationStatusIndexForTenant()` — déployer avec la section Opérations / Événements.

### ATAK 1.4.11 — classes de support (À UPLOADER EN PRIORITÉ)

Ces deux classes sont `use`-ées en tête de `app/Controllers/Api/AtakApiController.php`. Tant
qu’elles manquent sur le serveur, **deux endpoints ATAK renvoient une erreur 500** :

| Fichier | Sans lui |
|---|---|
| `app/Support/AtakOrderWaypoint.php` | `GET /api/atak/orders` → `Class "App\Support\AtakOrderWaypoint" not found` (`serializeOrder()`) |
| `app/Support/ArmaMarkerLabel.php` | `POST /api/atak/marker` → `Class "App\Support\ArmaMarkerLabel" not found` (`normalizeArmaMarkerData()`) |

Symptôme côté jeu : les ordres ne remontent plus dans l’ATAK et les marqueurs Arma ne se
synchronisent plus. Uploader ces deux fichiers suffit à rétablir les deux endpoints — c’est
exactement l’écueil décrit en section 9 (fichier absent des listes, donc jamais transféré).

### Renseignement SSE 1.4.12 — terminal SEEK

Ensemble cohérent : uploader **en une fois**, sinon le terminal envoie des champs que
le serveur ignore (fiche enregistrée mais sans constat, sans relevé ni classement).

| Fichier | Rôle |
|---|---|
| `bootstrap/atak_sse_persons_migration.php` | Colonnes constat / signature, index unité, table `sse_biometric_samples` |
| `app/Repositories/SsePersonRepository.php` | Persistance constat, signature, échantillons, recherche par unité |
| `app/Repositories/SseCaseRepository.php` | `findByReferenceCode()` — résolution du code dossier saisi sur le terrain |
| `app/Controllers/Api/SseApiController.php` | `case_code`, `signature`, `medical_context`, `biometric_samples`, `by-unit` |
| `app/Controllers/Web/SsePortalController.php` | Relevés joints au registre des personnes |
| `routes/web.php` | Route `/api/sse/persons/by-unit` (**avant** `/{id}`) |
| `views/atak/sse/persons.php` | Registre en fiches |
| `public/assets/css/sse_portal.css` | Styles des fiches et jauges de qualité |

La migration est idempotente et se rejoue seule au premier appel du dépôt. Si les
`ALTER TABLE` échouent (droits), l’enregistrement d’une fiche échouera : vérifier
`sse_persons.medical_context_json` après le premier envoi terrain.

### Exploitation de site SSE 1.4.13

| Fichier | Rôle |
|---|---|
| `bootstrap/atak_sse_persons_migration.php` | `sse_sites.reference_code` + index |
| `app/Repositories/SseSiteRepository.php` | **Nouveau** — sites, pièces, saisies, compte rendu |
| `app/Controllers/Api/SseApiController.php` | Endpoints `/api/sse/sites*` |
| `app/Controllers/Web/SsePortalController.php` | Écrans portail sites |
| `routes/web.php` | Routes API et portail |
| `views/atak/sse/sites.php` | **Nouveau** — registre des sites |
| `views/atak/sse/site_show.php` | **Nouveau** — checklist, saisies, clôture |
| `views/atak/sse/_layout.php` | Entrée de navigation « Sites exploités » |
| `public/assets/css/sse_portal.css` | Styles checklist et compte rendu |

### Page d'accueil — vidéos hero

| Fichier | Rôle |
|---|---|
| `app/Support/Media/VideoSourceProbe.php` | **Nouveau** — sonde de codec, écarte les sources indécodables |
| `views/home/index.php` | Sélection des sources hero |

Les fichiers `public/assets/video/hero-athena*.mp4` sont en HEVC / QuickTime et
**illisibles en navigateur** : les réencoder en H.264 avant transfert, voir
`docs/VIDEO-HERO-ENCODAGE.md` (script `scripts/reencode-hero-videos.ps1`).
Transférer les vidéos **en mode binaire**. Sans cela, l’accueil reste en photos
(`data-hero-videos-ready="0"`).

### Portail SSE 1.4.13 — charte et requête d'identité

| Fichier | Rôle |
|---|---|
| `public/assets/css/sse_portal.css` | Charte « SSE Case File » — jetons, balayage, hachures |
| `views/atak/sse/_layout.php` | Polices Archivo / JetBrains Mono, cache-buster CSS |
| `views/atak/sse/persons.php` | Verdict de requête d'identité sur la fiche |
| `bootstrap/atak_sse_persons_migration.php` | `sse_persons.identity_query_json` |
| `app/Repositories/SsePersonRepository.php` | Persistance du verdict |

### Comptes rendus SSE et rattachement des sites

| Fichier | Rôle |
|---|---|
| `app/Services/Sse/SseReportService.php` | **Nouveau** — flash et compte rendu initial |
| `app/Repositories/SseSiteRepository.php` | `case_id`, `listForCase()`, `attachToCase()` |
| `app/Controllers/Web/SsePortalController.php` | Écran compte rendu, sites du dossier |
| `app/Controllers/Api/SseApiController.php` | `case_code` sur l'ouverture de site |
| `bootstrap/atak_sse_persons_migration.php` | `sse_sites.case_id` + index |
| `routes/web.php` | `/atak/sse/dossiers/{id}/compte-rendu` |
| `views/atak/sse/case_report.php` | **Nouveau** — écran compte rendu |
| `views/atak/sse/case_show.php` | Blocs sites rattachés et produits |

### Corrélation et automatismes SSE 1.4.14

Nouveau service d'automatismes + graphe de corrélation. La table `sse_relations` est
créée par la migration SSE (§ 1) : **uploader `bootstrap/atak_sse_persons_migration.php`
avant** de rejouer les migrations, sinon la page corrélations ne stockera rien.

| Fichier | Rôle |
|---|---|
| `app/Services/Sse/SseCorrelationService.php` | **Nouveau** — graphe du dossier, arêtes déduites + posées |
| `app/Services/Sse/SseAutomationService.php` | **Nouveau** — règles A1 à A6 |
| `app/Repositories/SseSiteRepository.php` | `findSeizure()` — nature normalisée pour les automatismes |
| `app/Controllers/Web/SsePortalController.php` | Écran corrélations, pose et retrait de relation |
| `app/Controllers/Api/SseApiController.php` | Déclenchement des automatismes, champ `automation` |
| `bootstrap/atak_sse_persons_migration.php` | Table `sse_relations` |
| `routes/web.php` | `/atak/sse/dossiers/{id}/correlations` (+ POST et suppression) |
| `views/atak/sse/case_correlations.php` | **Nouveau** — écran corrélations |
| `views/atak/sse/case_show.php` | Bouton « Voir les corrélations » |
| `public/assets/css/sse_portal.css` | Styles graphe, arêtes, formulaire de relation |

**Ordre d'upload** : le service `SseCorrelationService.php` est référencé par
`SseAutomationService.php` **et** par la vue. Uploader les deux services avant la vue
et les routes, sinon la page 500 le temps du transfert.

Post-check :

- [ ] `/atak/sse/dossiers/{id}/correlations` s'ouvre sans erreur
- [ ] La table `sse_relations` existe
- [ ] Une fiche transmise sans code dossier, avec **un seul** dossier ouvert, y arrive seule
- [ ] Le journal d'activité montre une ligne `SSE_AUTO` après ce classement

### Déclassification et caviardage SSE 1.4.14

Table `sse_redactions` créée par la même migration SSE que `sse_relations` (§ 1).

| Fichier | Rôle |
|---|---|
| `app/Services/Sse/SseRedactionService.php` | **Nouveau** — niveaux, catégories, barres, caviardage manuel |
| `app/Services/Sse/SseReportService.php` | `gatherForRelease()`, niveau de diffusion sur les deux comptes rendus |
| `app/Controllers/Web/SsePortalController.php` | Écran déclassification, pose et levée de caviardage |
| `bootstrap/atak_sse_persons_migration.php` | Table `sse_redactions` |
| `routes/web.php` | `/atak/sse/dossiers/{id}/declassification` et `/caviardage` |
| `views/atak/sse/case_declassify.php` | **Nouveau** — écran version expurgée |
| `views/atak/sse/case_report.php` | Renvoi vers la déclassification |
| `views/atak/sse/case_show.php` | Bouton « Version expurgée » |
| `public/assets/css/sse_portal.css` | Sélecteur de niveau, tableau des catégories, styles d'impression |

**Ordre d'upload** : `SseRedactionService.php` avant `SseReportService.php` — ce
dernier l'instancie dans son constructeur, et une classe manquante fait tomber
les deux écrans de compte rendu, pas seulement la déclassification.

Post-check :

- [ ] La table `sse_redactions` existe
- [ ] `/atak/sse/dossiers/{id}/declassification` s'ouvre sur « Diffusion interne »
- [ ] À ce niveau, aucun nom de personne n'apparaît dans le code source de la page
      (`Ctrl+U` puis rechercher un nom connu du dossier — il ne doit rien ressortir)
- [ ] Le compte rendu intégral (`/compte-rendu`) reste, lui, en clair

### Habilitation de lecture SSE 1.4.14 (CORRECTIF DE SÉCURITÉ)

Sans ces fichiers, l'écran de déclassification sert la version intégrale à quiconque
change le paramètre `?niveau=` dans l'adresse. **À uploader avec la déclassification,
pas après.**

| Fichier | Rôle |
|---|---|
| `app/Services/Sse/SseClearanceService.php` | **Nouveau** — plafond de lecture par session |
| `app/Services/Sse/SseAccessCodeService.php` | Habilitation portée par un code, déposée en session |
| `app/Repositories/SseAccessCodeRepository.php` | Colonne `clearance_level` en écriture et lecture |
| `app/Controllers/Web/SsePortalController.php` | Rabattement du niveau, journal des refus |
| `bootstrap/atak_sse_portal_migration.php` | `sse_access_codes.clearance_level` + aide `columnExists` |
| `views/atak/sse/case_declassify.php` | Bandeau d'habilitation, niveaux verrouillés |
| `views/atak/sse/access.php` | Choix de l'habilitation à l'émission d'un code |
| `public/assets/css/sse_portal.css` | Styles bandeau et niveau verrouillé |

Post-check :

- [ ] `sse_access_codes.clearance_level` existe
- [ ] Ouvrir un dossier avec `?niveau=tres_restreint` en compte non habilité :
      le bandeau « Lecture rabattue » s'affiche et les noms restent noircis
- [ ] Le journal d'activité porte une ligne `SSE_CLEARANCE` après cette tentative
- [ ] L'émission d'un code d'accès propose bien « Habilitation de lecture accordée »

**Permissions à assigner** (facultatif — le report des rôles existants fonctionne sans) :
`atak.sse.clearance.encadrement`, `atak.sse.clearance.confidentiel`,
`atak.sse.clearance.tres_restreint`.

### Verrou de classification SSE 1.4.14

| Fichier | Rôle |
|---|---|
| `app/Repositories/SsePortalSettingsRepository.php` | **Nouveau** — réglages portail par communauté |
| `app/Services/Sse/SseClearanceService.php` | Bascule du verrou, libellés de revue |
| `app/Controllers/Web/SsePortalController.php` | Verrou dans `requireCase()`, données de revue, bascule |
| `bootstrap/atak_sse_portal_migration.php` | Table `sse_portal_settings` |
| `routes/web.php` | `POST /atak/sse/dossiers/verrou-classification` |
| `views/atak/sse/cases.php` | Panneau de revue, colonne « Qui pourra encore l'ouvrir » |
| `public/assets/css/sse_portal.css` | État du verrou |

**Attention à l'ordre des routes** : `POST /atak/sse/dossiers/verrou-classification`
doit rester déclarée **avant** `POST /atak/sse/dossiers/{id}`. Le routeur retient le
premier motif qui correspond ; dans l'autre ordre, `{id}` capte
« verrou-classification » et la bascule part dans la mise à jour de dossier.

Post-check :

- [ ] La table `sse_portal_settings` existe
- [ ] Le registre affiche le panneau « Verrou d'ouverture par classification » en **DÉSARMÉ**
- [ ] La colonne « Qui pourra encore l'ouvrir » est renseignée sur chaque dossier
- [ ] Après armement, un compte non habilité ne peut plus ouvrir un dossier confidentiel
- [ ] Le journal porte une ligne `SSE_CLEARANCE` à l'armement

> Ne pas armer avant d'avoir relu les classifications existantes. Elles n'ont jamais
> filtré : certaines ont été posées sans conséquence.

### Contournements compte rendu / PDF 1.4.14 (CORRECTIF DE SÉCURITÉ)

`/compte-rendu` et l'export PDF servaient le dossier **intégral** sans contrôle
d'habilitation, ce qui rendait l'écran de déclassification inopérant. **À uploader
avec le reste du lot habilitation, pas après.**

| Fichier | Rôle |
|---|---|
| `app/Services/Sse/SseCasePdfService.php` | Caviardage de l'export + bandeau de niveau imprimé |
| `app/Services/Sse/SseClearanceService.php` | Caviardage des écrans de travail |
| `app/Repositories/SsePortalSettingsRepository.php` | Réglage `redact_working_screens` |
| `app/Controllers/Web/SsePortalController.php` | Rabattement compte rendu / PDF / écrans, bascule à deux réglages |
| `views/atak/sse/case_report.php` | Bandeau « compte rendu partiel » |
| `views/atak/sse/cases.php` | Second interrupteur |
| `public/assets/css/sse_portal.css` | Séparateur de panneau |

Post-check :

- [ ] Avec un compte non pleinement habilité, `/compte-rendu` affiche le bandeau
      « Compte rendu partiel » et les noms sont noircis
- [ ] L'export PDF du même dossier porte le bandeau rouge « VERSION EXPURGÉE »
      avec la liste des catégories au noir
- [ ] Le registre des dossiers propose les **deux** interrupteurs, tous deux DÉSARMÉS
- [ ] Le journal porte une ligne `SSE_CLEARANCE` après chaque export PDF

### Correctif audit système (CORRECTIF BLOQUANT)

| Fichier | Rôle |
|---|---|
| `app/Controllers/Admin/System/SystemAuditController.php` | Homonymie `rollback()` levée — la classe ne se chargeait pas |

Les quatre routes `/admin/audit` étaient inaccessibles. À uploader indépendamment
du reste, le correctif ne dépend de rien.

Post-check :

- [ ] `/admin/audit` s'ouvre
- [ ] Le détail d'une entrée s'ouvre et propose reprise et alerte

### Diffusion dirigée des rapports — phase A (dont CORRECTIF D'ISOLATION)

| Fichier | Rôle |
|---|---|
| `app/Services/Tactical/AtakReportRoutingService.php` | **Nouveau** — branchement du moteur de règles |
| `app/Repositories/AtakReportRoutingRepository.php` | `listForReport()` |
| `app/Repositories/AtakTacticalReportRepository.php` | Cloisonnement de `findById()` et `acknowledge()` |
| `app/Controllers/Api/AtakApiController.php` | Appel du routage, cloisonnement des deux endpoints |

Prérequis base : `migrations/2026_07_24_007_atak_intelligence_enhancements.sql`,
appliquée par le lanceur de migrations. Si elle ne l'est pas, le routage est inerte
et tracé, sans faire échouer la soumission.

Post-check :

- [ ] Soumettre un rapport tactique : il est enregistré (aucune règle en base = aucun routage, c'est normal)
- [ ] `GET /api/atak/reports/{id}` avec l'identifiant d'un rapport d'une **autre**
      communauté renvoie désormais 404, plus le rapport
- [ ] Même vérification sur `/acknowledge`

> Aucune règle de diffusion n'existe en base : le moteur tourne à vide tant que
> personne n'en crée. C'est l'état attendu après cette montée.

### Écran des règles de diffusion

| Fichier | Rôle |
|---|---|
| `app/Controllers/Admin/AdminAtakReportRoutingController.php` | **Nouveau** — gestion des règles |
| `app/Repositories/AtakReportRoutingRepository.php` | CRUD des règles + correctif du drapeau de notification |
| `views/admin/atak_report_routing/index.php` | **Nouveau** — écran |
| `routes/web.php` | `/admin/atak-diffusion-rapports` (+ état, suppression) |
| `tools/audit-integrite.php` | Couvre désormais les vues passées par `content` |

Post-check :

- [ ] `/admin/atak-diffusion-rapports` s'ouvre et annonce « aucune règle »
- [ ] Créer une règle sans destinataire est refusé avec un motif explicite
- [ ] Une règle créée apparaît dans la liste avec sa condition en clair

### Émission des notifications de diffusion

| Fichier | Rôle |
|---|---|
| `app/Services/Tactical/AtakReportRoutingService.php` | Émission de la notification |
| `app/Repositories/AtakReportRoutingRepository.php` | `markNotified()` |
| `app/Controllers/Api/AtakApiController.php` | `GET /api/atak/notifications` |
| `routes/web.php` | Route de relève |

Post-check :

- [ ] Créer une règle, soumettre un rapport correspondant
- [ ] `GET /api/atak/notifications` renvoie la notification, avec l'urgence du rapport
- [ ] La fiche du rapport montre les destinataires ; `notification_sent` vaut 1

### Fichier orphelin (ne pas uploader seul)

| Fichier | Note |
|---|---|
| `views/partials/ath_roles_functions_catalogue.php` | Doublon non référencé — `ath_roles_catalogue.php` est utilisé |

---

## 3. Légende statuts

| Symbole | Signification |
|---|---|
| ✅ ATHENA-ready | Partial dédié, rendu complet sous `$isBackOfficeShell` |
| ⚡ Hybride | Coque ATHENA + KPIs/tableaux partagés ; corps de page partiellement legacy |
| 🔶 Legacy | Coque ATHENA (sidebar/topbar) mais formulaire ou mise en page ancienne |

---

## 3bis. Contrôle d'intégrité après envoi (recommandé)

```bash
php tools/audit-integrite.php
```

Cherche précisément ce qu'un envoi FTP incomplet produit :

| Contrôle | Symptôme sans lui |
|---|---|
| Route → classe ou méthode absente | HTTP 500 (déjà vu : `AtakOrderWaypoint`, `ArmaMarkerLabel`) |
| Vue référencée mais absente | **Page blanche en HTTP 200**, aucune trace dans les journaux |
| Classe qui ne se charge pas | Écran mort sur tout un domaine |
| Méthode déclarée deux fois | Cause fréquente du point précédent |

Code de retour 0 si tout est en place, 1 sinon — enchaînable dans un script.

`php -l` ne remplace pas ce contrôle : une méthode déclarée deux fois passe
l'analyse syntaxique et n'échoue qu'à la compilation de la classe. C'est ainsi
que les quatre routes `/admin/audit` sont restées mortes sans que rien ne le
signale.

---

## 4. Vérification syntaxe PHP (sur le serveur)

```bash
php -l views/admin/organization/tenant_alerts_index.php
php -l views/partials/ath_tenant_alerts.php
php -l views/partials/ath_tenant_alerts_form.php
php -l app/Repositories/AarReportRepository.php
php -l app/Controllers/Admin/AdminAarReportsController.php
php -l views/admin/aar_reports/index.php
php -l views/admin/aar_reports/show.php
php -l views/partials/ath_personnel_job_roles_index.php
php -l app/Support/AthUi.php
php -l app/Support/BackOfficePageContext.php
```

---

## 5. Tests manuels post-déploiement

| URL | Attendu |
|---|---|
| `/back-office` | Dashboard ATHENA (alertes + KPIs + tableau) |
| `/back-office/events` | Registre opérations ATHENA |
| `/back-office/events/{id}` | Fiche créneau ATHENA |
| `/back-office/events/{id}/reponses-nominatives` | KPIs + filtres + export |
| `/back-office/alerts` | Liste annonces ATHENA |
| `/back-office/alerts/create` | Formulaire ATHENA |
| `/back-office/roles-functions` | Doctrine Cellule S1 |
| `/back-office/roles-functions/referentiel` | Référentiel filtrable |
| `/back-office/roles-functions/catalogue` | Catalogue filtrable |
| `/back-office/personnel-job-roles` | Référentiel emplois ATHENA |
| `/back-office/personnel-job-roles/assignments` | Attributions + modal droits |
| `/back-office/users` | KPIs + tableau membres |
| `/back-office/roles` | Table rôles ATHENA |
| `/back-office/audit` | Journal d'audit ATHENA |
| `/back-office/atak/comptes-rendus` | Liste AAR (KPIs, filtres, dépôt) |
| `/back-office/atak/comptes-rendus/{id}` | Lecture compte rendu + badge statut |
| `/back-office/organisation-effectifs` | Hub effectifs (KPIs ATHENA) |

---

## 6. Hors périmètre ATHENA (legacy, non bloquant)

Pages back-office avec coque ATHENA mais contenu non migré :

- Paramètres communauté (`settings.php`, `community/*`)
- Formations / LMS (`formation/*`)
- ATAK mod / bêta / briefing slides (CSS dédiés, pas de partial `ath_*`)
- Modération forum, recrutement, communications
- Formulaires utilisateur create/edit/show

Migration ultérieure possible page par page ; aucun blocage déploiement pour le périmètre ci-dessus.

---

## 7. Rollback rapide

1. Restaurer la copie de données (base + fichiers) :
   `php scripts/data-snapshot.php restore <id> --yes`
   (voir `docs/technique/sauvegarde-donnees-completes.md`).
2. Restaurer `views/partials/ath_*`, vues `views/admin/organization/*`, `config/back_office_pages.php`, `app/Support/BackOfficePageContext.php` si le code a changé.
3. Redémarrer PHP-FPM ou `opcache_reset`.

---

## 8. Correctifs critiques appliqués

- `tenant_alerts_index.php` référençait un partial inexistant (`ath_tenant_alerts_index.php`) → corrigé vers `ath_tenant_alerts.php`.
- **`views/admin/system/platform_alerts_form.php` — À UPLOADER EN PRIORITÉ.** Le fichier
  contenait une erreur de syntaxe (`'paid' => true';` au lieu de `'paid' => true];`) qui
  fait planter `/admin/system/alerts/create` avec
  `syntax error, unexpected single-quoted string ";", expecting "]"`.
  Le correctif est dans le dépôt depuis la migration ATHENA, mais **ce fichier ne figurait
  dans aucune liste de la section 2** : il a donc été omis à l’upload et la production est
  restée sur la version cassée. Uploader ce seul fichier suffit à rétablir la page.

## 9. Écueil connu de l’upload manuel

Un fichier absent des listes de la section 2 n’est pas uploadé, et rien ne le signale : la
page concernée casse en production alors que le dépôt est sain. Deux réflexes :

1. Après un upload, lancer la vérification de la section 4 (`php -l`) **sur l’ensemble des
   fichiers modifiés depuis le dernier déploiement**, pas seulement sur ceux de la liste.
   Pour obtenir cette liste depuis le dépôt :

   ```bash
   git diff --name-only <dernier-commit-déployé>..HEAD -- 'views/**' 'app/**' 'public/**'
   ```

2. Transférer en mode **binaire**. Un transfert en mode ASCII peut réécrire les fins de
   ligne et corrompre un fichier pourtant valide côté dépôt — même symptôme, autre cause.

---

*Dernière mise à jour : 27 juillet 2026 — checklist production athena.ttrd.fr*
