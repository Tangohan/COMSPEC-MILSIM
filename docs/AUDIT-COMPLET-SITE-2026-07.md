# Audit complet — Athena / COMSPEC-MILSIM

**Date** : 26 juillet 2026
**Périmètre** : application web PHP (`app/`, `routes/`, `views/`, `config/`, `migrations/`, `bootstrap/`)
**Base observée** : dump `u416380327_BDD_PROD.sql` (227 tables) + 93 migrations SQL + 128 migrations PHP `bootstrap/`
**Volume** : ~317 000 lignes PHP · 1 241 routes · 770 fichiers `app/` · 580 vues · 52 domaines de services · 196 repositories

**Convention de fiabilité** utilisée partout dans ce document :

| Marqueur | Sens |
|---|---|
| **[C]** Confirmé | Constat lu directement dans le code ou le schéma |
| **[P]** Probable | Comportement fortement suggéré, non exécuté/testé |
| **[V]** À vérifier | Information insuffisante pour conclure |
| **[→]** Proposition | N'existe pas aujourd'hui |

---

## 1. Résumé exécutif

Athena n'est pas un site communautaire : c'est une **plateforme SaaS multi-tenant de gestion
d'unité milsim**, avec un produit satellite embarqué (le mod Arma « Overwatch »/ATAK) qui
communique par API. Le périmètre fonctionnel réellement implémenté est considérable — recrutement
avec portail candidat, LMS complet avec studio d'édition et certificats PDF, forum modéré,
messagerie, gestion documentaire versionnée, courrier officiel, coopérations inter-unités,
facturation Stripe, ATAK temps réel. Peu de projets de cette taille livrent autant.

Le problème n'est donc pas la richesse : **c'est le branchement**. Les modules ont été construits
successivement, chacun avec son propre modèle de données, et les liaisons entre eux ont été soit
oubliées, soit remplacées par de la saisie manuelle, soit dupliquées.

### Les cinq constats structurants

1. **[C] La chaîne « Formation → Qualification → Effectif → Opération » est rompue en son milieu.**
   `personnel_qualifications` est une table **en lecture seule dans toute l'application** : aucun
   chemin de code n'appelle `add()` ni `updateStatus()` (`app/Repositories/PersonnelQualificationRepository.php`
   n'est consommé que via `listForUser()` et `getNextExpiration()`). Le panneau « Qualifications »
   de la fiche personnelle ne peut structurellement jamais se remplir. En parallèle,
   `TrainingCertificateService::issueCertificate()` écrit dans `training_certificates` et ne
   propage rien vers le dossier personnel. Les tables `certifications` / `user_certifications`
   existent, sont lues une seule fois (`UnitRepository.php:163-185`, pour calculer un score de
   « readiness ») et **ne sont jamais écrites nulle part**.
   → Conséquence directe : les questions métier « Qui est formé à quoi ? », « Qui peut occuper
   quelle fonction ? », « Quelle qualification expire bientôt ? » sont **sans réponse dans l'outil**.

2. **[C] Quatre objets « opération » coexistent sans se connaître.** `community_events`
   (agenda + RSVP + slots), `interteam_missions` (coopération inter-unités), `ops_board_items`
   (tableau opérationnel `/back-office/tableau-operationnel`), et le `missionId` des API
   `/api/operations/*` et `/api/replay/*` côté ATAK. Aucune clé étrangère ne relie ces quatre
   familles. Un briefing publié sur le tableau opérationnel ne se rattache pas à l'événement
   auquel les membres se sont inscrits.

3. **[C] Faille d'exposition : 14 routes `/api/operations/*` sont sans aucune authentification.**
   Elles sont déclarées sans middleware (`routes/web.php:1517-1531`) *et* le préfixe
   `/api/operations` est absent de `protected_prefixes` dans `config/tactical_api.php`. Cela
   inclut deux écritures : `POST /api/operations/sitrep/report` et
   `POST /api/operations/logistics/update`, ainsi que l'export AAR/RETEX en JSON et PDF.
   Voisin direct : `POST /api/operations/doctrine/documents` (ligne 356) *est*, lui, protégé par
   `AuthMiddleware` — l'incohérence est visible à l'œil nu dans le fichier de routes.

4. **[C] La protection CSRF globale ne couvre que `/back-office/`.**
   `CsrfPostMiddleware` retourne immédiatement `$next($request)` si le chemin ne commence pas par
   `/back-office/` (`app/Middleware/CsrfPostMiddleware.php:19-21`). Les 181 routes `/admin/*` et
   toutes les routes membres reposent donc sur 382 appels `Csrf::validate()` écrits à la main.
   La couverture est réelle mais lacunaire : `DocumentsController` (521 lignes, 3 routes POST —
   `unlock`, `signature`, `access-track`) n'en contient **aucun**.

5. **[C] Triple stockage des affectations, avec code de synchronisation explicite.**
   `user_units`, `personnel_assignments` et `personnel_profiles.primary_unit_id` décrivent la même
   réalité. `PersonnelAssignmentRepository` documente lui-même le problème (« Source métier :
   `personnel_assignments` en priorité ; si aucune ligne active, repli sur `user_units` ») et
   contient des méthodes `syncMissingFromUserUnits()` et une double écriture (lignes 343-350).
   La désynchronisation n'est pas un risque théorique : le code est écrit pour la réparer.

### Santé globale

| Axe | Note | En une phrase |
|---|---|---|
| Richesse fonctionnelle | **Excellent** | Très au-delà d'un outil communautaire classique |
| Qualité UI (pages récentes) | **Solide** | Documents / Événements / Messagerie sont d'un bon niveau mobile |
| Architecture applicative | **Fragile** | Contrôleurs de 8 000 lignes, 526 `catch (\Throwable)` silencieux |
| Intégrité des données | **Fragile** | Duplications, tables mortes, FK manquantes entre modules |
| Sécurité | **Fragile** | CSRF partiel, API `/api/operations/*` ouverte |
| Cohérence métier inter-modules | **Critique** | C'est le point faible principal du produit |
| Couverture de test | **Critique** | 22 fichiers de test pour 317 k lignes, aucun sur les workflows métier |

---

## 2. Cartographie actuelle du site

### 2.1 Socle technique **[C]**

Framework maison, pas de dépendance à Laravel/Symfony.

```
public/index.php
  → bootstrap/env.php + bootstrap/app.php (config globale $GLOBALS['__app_config'])
  → garde maintenance (fichier storage/maintenance.json PUIS table app_maintenance)
  → App\Core\Application::run()
       → Session::start()
       → 9 middlewares globaux
       → Router::dispatch() : boucle linéaire sur 1 241 routes, preg_match par route
```

**Middlewares globaux, dans l'ordre** (`app/Core/Application.php:26-45`) :
`RequestId` → `Locale` (optionnel, `class_exists`) → `AntiScraper` → `RequestTelemetry` →
`ComspecTacticalApi` → `SecurityHeaders` → `RateLimit` → `CsrfPost` → `DemoNdaGate` →
`TenantTypeModuleAccess`.

**Cœur** : 12 classes, 3 247 lignes au total. `Container.php` en pèse 2 170 à lui seul — c'est un
conteneur d'injection **écrit à la main**, un `switch` géant de fabriques.

### 2.2 Modules fonctionnels

| Module | Contrôleurs | Tables principales | Route racine |
|---|---|---|---|
| Public / vitrine | `HomeController`, `CommunityController`, `SeoController` | `tenants`, `tenant_branding`, `community_media` | `/`, `/c/{slug}` |
| Auth / compte | `AuthController`, `RegisterController`, `AccountController` | `users`, `sessions`, `email_tokens`, `password_resets` | `/login`, `/register` |
| Recrutement | `EnlistmentController`, `EnlistmentCandidatePortalController`, `AdminRecruitmentsController`, `RecruitmentWorkspaceController` | `enlistments`, `enlistment_timeline`, `recruitment_openings`, `recruitment_presets` | `/enlistment`, `/admin/recruitments` |
| Formation (LMS) | `TrainingController`, `AdminTrainingStudioController`, `TrainingCompetencyController` (+9) | `training_courses`, `training_modules`, `training_lessons`, `training_enrollments`, `training_progress`, `training_certificates`, `training_quizzes` | `/formation` |
| Effectifs | `PersonnelController`, `EffectifsWorkspaceController`, `RhWorkspaceController`, `DossierOperateurController` | `personnel_profiles`, `personnel_assignments`, `user_units`, `units`, `grades` | `/effectifs` |
| Opérations / RSVP | `CommunityEventsController`, `PointageController`, `OperationalBoardController` | `community_events`, `community_event_rsvps`, `community_event_slots`, `ops_board_items` | `/evenements`, `/pointage` |
| ATAK / Overwatch | `AtakController`, `AtakApiController` (8 230 lignes), `AtakMapGatewayController` (+5) | 15 tables `atak_*` + `atak_operator_ids` | `/atak`, `/api/atak/*` |
| Documents | `DocumentsController`, `AdminDocumentsController` | `documents`, `document_versions`, `document_links`, `document_permissions` | `/documents` |
| Courrier | 5 contrôleurs `Courrier\*` | `courrier_documents`, `courrier_document_versions` | `/courrier` |
| Forum | 8 contrôleurs | 20 tables `forum_*` | `/forum` |
| Messagerie | `TenantMessagesController`, `InboxController` | `tenant_message_threads`, `tenant_messages` | `/messages` |
| Coopération | `InterteamMissionWebController`, `CooperationCatalogWebController` | `interteam_missions` + 8 tables | `/admin/interteam-missions` |
| Admin org | 34 contrôleurs `Admin\Organization\*` | — | `/back-office/*` |
| Admin système | 22 contrôleurs `Admin\System\*` | `platform_*`, `subscription_plans` | `/admin/system/*` |

### 2.3 Graphe de dépendances réel **[C]**

```
                        ┌─────────┐
                        │ tenants │ (multi-tenant : tenant_id sur ~90 % des tables)
                        └────┬────┘
                             │
                        ┌────▼────┐        ┌──────────────────┐
                        │  users  │◄───────┤ roles/permissions│ (RBAC : Gate + PermissionImplication)
                        └────┬────┘        └──────────────────┘
             ┌───────────────┼───────────────┬──────────────┐
             │               │               │              │
    ┌────────▼───────┐ ┌─────▼──────┐ ┌──────▼──────┐ ┌─────▼──────────┐
    │personnel_      │ │training_   │ │community_   │ │atak_operator_  │
    │profiles        │ │enrollments │ │event_rsvps  │ │ids             │
    └────────┬───────┘ └─────┬──────┘ └──────┬──────┘ └─────┬──────────┘
             │               │               │              │ (join sur call_sign,
             │               │               │              │  pas de FK)
    ╔════════▼═══════╗       │               │        ┌─────▼──────┐
    ║personnel_      ║  ╔════▼═══════════╗   │        │ atak_units │
    ║qualifications  ║  ║training_       ║   │        └────────────┘
    ║ JAMAIS ÉCRITE  ║  ║certificates    ║   │
    ╚════════════════╝  ╚════════════════╝   │
             ▲                   ║           │
             ╚═══ ✗ AUCUN LIEN ══╝           │
                                             │
    ╔══════════════════╗              ┌──────▼─────────┐
    ║certifications /  ║              │community_events│
    ║user_certifications║             └────────────────┘
    ║ JAMAIS ÉCRITES   ║                      ✗ aucun lien vers
    ╚══════════════════╝                        ops_board_items
                                                interteam_missions
                                                /api/operations/{missionId}
```

Les cadres doublés signalent les impasses confirmées.

---

## 3. Parcours utilisateur global

### 3.1 Ce qui existe réellement **[C]**

| # | Étape demandée | État | Preuve |
|---|---|---|---|
| 1 | Visiteur | **Existe** | `/` → `HomeController::index`, `/c/{slug}` vitrine communauté, `/recrutement` |
| 2 | Inscription | **Existe** | `/register` → `RegisterController` (transaction, vérification e-mail) |
| 3 | Connexion | **Existe, riche** | Multi-communauté (sélection si plusieurs comptes sur le même e-mail), OTP e-mail optionnel, blocklist réseau, journal `login_attempts` |
| 4 | Intégration communauté | **Existe, fragmenté** | 3 chemins parallèles : `/join` + `community/resolve-code`, `/invitations/accept`, création de communauté (`TenantBootstrapService`) |
| 5 | Recrutement | **Existe, le plus abouti** | `/enlistment` (invité ou membre), portail candidat par token `/enlistment/suivi/{token}`, timeline, entretien Discord, grille d'évaluation, SLA |
| 6 | Formation | **Existe, très abouti** | LMS complet : cours → modules → leçons → quiz → progression → certificat PDF |
| 7 | Qualification | **⚠ Rompu** | Le certificat existe ; la qualification au dossier n'est jamais écrite |
| 8 | Affectation / effectif | **Existe, triplé** | `user_units` + `personnel_assignments` + `personnel_profiles.primary_unit_id` |
| 9 | Préparation opérationnelle | **Partiel** | Tableau opérationnel + slides de briefing existent, mais non rattachés à un événement |
| 10 | Inscription à une opération | **Existe** | RSVP oui/non/peut-être + slots nominatifs avec liste d'attente |
| 11 | Participation | **Partiel** | `checked_in_at` sur le RSVP (`/pointage`) ; pas de présence côté ATAK réconciliée |
| 12 | Suivi post-opération | **Partiel/isolé** | AAR/RETEX existe **uniquement** côté API ATAK (`/api/operations/aar/{missionId}`), sans lien avec `community_events` ; `interteam_mission_rex` pour les coopérations seulement |

### 3.2 Ruptures du parcours **[C]**

| Rupture | Description | Gravité |
|---|---|---|
| **7 → 8** | Une formation validée ne produit aucune qualification exploitable au dossier | **Critique** |
| **8 → 10** | Aucune vérification de prérequis (qualification, unité, grade) à l'inscription à une opération | **Élevée** |
| **9 → 10** | Le briefing/tableau opérationnel n'est pas rattachable à l'événement | **Élevée** |
| **11 → 12** | Le pointage (`community_event_rsvps.checked_in_at`) et l'AAR (`missionId` ATAK) sont dans deux univers de données disjoints | **Élevée** |
| **5 → 6** | Un candidat accepté n'est inscrit à aucun parcours de formation d'accueil automatiquement | **Moyenne** |
| **4** | Trois portes d'entrée dans une communauté, avec trois provisionnements distincts | **Moyenne** |

### 3.3 Saisies redondantes **[C]**

- **Disponibilité** : saisie 3 fois dans `enlistments` (`availability`, `weekly_availability`,
  `availability_wed_sat`), jamais reprise dans le profil membre après acceptation.
- **Identité** : `users.display_name` / `users.callsign` / `user_profiles.first_name+last_name` /
  `user_profiles.arma_callsign` / `personnel_profiles.character_name` / `personnel_profiles.callsign` /
  `atak_operator_ids.call_sign`. **Sept** emplacements pour deux informations.
- **Spécialité/rôle** : `enlistments.specialty`, `personnel_profiles.primary_role` (texte libre),
  `personnel_profiles.secondary_role` (texte libre), `personnel_profiles.personnel_job_role_id` (FK),
  `personnel_assignments.role_name` (texte libre), `positions` + `user_positions`.
- **Grade** : `users.grade_id` (FK), `personnel_profiles.rank_display` (texte),
  `personnel_profiles.rank_display_override` (texte), `tenant_grade_overrides`.

### 3.4 Automatisé vs manuel **[C]**

**Automatisé** — 7 tâches dans `app/Services/Cron/Jobs/` orchestrées par `CronRunner`
(`/cron/run` protégé par `CRON_SECRET`, `hash_equals`) :
`AccountDeletionAnonymize`, `HrWeeklyDigest`, `ModerationQuarantineExpire`,
`RecruitmentRetroReminders`, `RoleplayBilanDue`, `TrainingExpire`, `TrainingForgottenDocsDigest`.
Plus une file asynchrone `async_jobs` traitée par `worker-jobs.php` (uniquement `email_send`).

**⚠ Fragmentation [C]** : `send-attendance-reminders.php` est un **script autonome à la racine**,
hors du registre `CronRunner`, avec sa propre planification. Deux systèmes de tâches planifiées
coexistent, sans supervision commune.

**Manuel** : attribution des qualifications, rattachement briefing↔événement, vérification des
prérequis, réconciliation présence ATAK↔RSVP, inscription en formation après acceptation.

---

## 4. Audit — Site public, connexion, inscription, communauté

### 4.1 Ce qui est en place **[C]**

- **Vitrine communauté** `/c/{slug}` : présentation, unités publiques (`units.show_on_public_page`,
  `public_blurb`, `public_tags`), médias (`community_media` + reels), avis, contact,
  candidature directe. C'est correct.
- **Inscription** : `RegisterController` (301 lignes) — sous transaction, vérification e-mail
  obligatoire (`status = pending_verification`), e-mail « compagnon sécurité »
  (`REGISTER_SECURITY_COMPANION`).
- **Connexion** : parmi les points forts du projet. Gestion multi-appartenance
  (`listUsersForLoginByEmail` → sélection de communauté), OTP e-mail (`LoginSecurityOtpService`),
  détection de nouvel appareil (`user_login_devices`), alerte tentatives multiples, blocklist par
  tenant, audit `AUTH_LOGIN_FAILURE`.
- **RGPD** : sérieux et rare. Suppression de compte avec délai de rétractation, verrouillage de
  navigation pendant le délai (`AuthMiddleware:76-85`), export de données, anonymisation par cron.
- **Récupération de compte** : `/password/forgot` + `password_resets` + `email_tokens`. **[C]**

### 4.2 Points faibles

| Constat | Détail | Marqueur |
|---|---|---|
| **Trois portes d'entrée non unifiées** | `/join` (code communauté), `/invitations/accept` (invitation nominative), `/enlistment` (candidature). Trois provisionnements différents, trois expériences différentes. | **[C]** |
| **Onboarding post-acceptation faible** | `OnboardingController` et `MemberOnboardingService` existent, mais rien ne pousse le nouveau membre vers : compléter son dossier → suivre la formation d'accueil → lire les documents obligatoires → s'inscrire à sa première opération. | **[C]** |
| **Le visiteur ne sait pas ce qu'il rejoint** | La vitrine décrit la communauté, jamais le **fonctionnement** : rythme des opérations, engagement attendu, durée du recrutement, prérequis matériels. Ces informations existent pourtant dans `enlistments` (mods requis, disponibilité mer/sam) mais côté formulaire, pas côté présentation. | **[C]** |
| **Slot « Plus » de la barre mobile inerte** | `views/partials/bottom_nav.php:18` — l'entrée « Plus » pointe vers `url('hub')`, identique à « Accueil », et son `$active` est câblé à `false`. Un cinquième des slots de navigation mobile ne sert à rien. | **[C]** |
| **Deux hiérarchies d'administration** | `/admin/*` (181 routes) et `/back-office/*` (433 occurrences) coexistent, avec des règles de sécurité différentes (cf. CSRF). Pour l'utilisateur admin, la frontière n'a pas de logique métier. | **[C]** |

### 4.3 Évaluation « premier contact »

Quelqu'un qui arrive pour la première fois comprend **qui** est la communauté (identité, médias,
unités) mais pas **comment on y vit**. Manquent, par ordre d'impact :

1. Le rythme réel (« opérations le mercredi et samedi 21 h ») — **[→]**
2. Le parcours d'intégration en 4 étapes avec durées indicatives — **[→]**
3. Les prérequis techniques concrets (mods, micro, ACE/ACRE) — présents dans le formulaire, absents de la vitrine — **[→]**
4. Une preuve de vie (dernière opération, effectif actif, prochaine session ouverte) — les données existent, elles ne sont pas exposées — **[→]**

---

## 5. Audit — Recrutement

C'est **le module le plus abouti du projet**, et de loin.

### 5.1 Existant **[C]**

| Capacité | Implémentation |
|---|---|
| Candidature invité ou membre | `enlistments.submitted_via` (`guest`/`member`), `submitter_user_id` |
| Offres de recrutement | `recruitment_openings` + compteurs + publication automatique sur le forum (`RecruitmentOpeningForumPublisher`) |
| Codes d'invitation | `recruitment_invite_codes` + `RecruitmentInviteCodesController` |
| Préréglages de candidature | `recruitment_presets`, réutilisables par le candidat (`/account/recruitment-presets`) |
| Portail candidat | `/enlistment/suivi/{token}` — messagerie candidat↔staff, pièces jointes, bilan candidat, activation Discord |
| Modération automatique | `EnlistmentPortalTextModerationScanner` (725 lignes) + `EnlistmentPortalAutoModerationCoordinator` |
| Entretien Discord | `discord_interview_at`, `discord_interview_notes`, `discord_evaluation_json` (grille de critères, jamais exposée au candidat) |
| Timeline | `enlistment_timeline` (`entry_kind`) |
| SLA | migration `20260418120000_enlistment_context_and_sla.sql` + `/admin/recruitments/sla` |
| Provisionnement | `EnlistmentAcceptanceProvisioningService` (683 lignes) : création ou rattachement de compte, promotion de rôle `guest`/`invite` → `member`, token de mise en place 72 h, e-mails candidat + staff, garde de quota |
| Relances | `RecruitmentRetroRemindersCronJob` |
| Mur d'équipe | `recruitment_team_wall` — commentaires internes |

C'est bien **un workflow**, pas une succession de pages. `EnlistmentAcceptanceProvisioningService`
contient même des garde-fous rares (`assertAcceptAllowed` avant décision, `membershipRepairHint`
pour rattraper une acceptation partiellement échouée).

### 5.2 Problèmes

| # | Constat | Marqueur |
|---|---|---|
| R1 | **Double machine à états.** `enlistments.status` accepte `submitted / reviewed / rejected / blocked` (`EnlistmentRepository:525`), tandis que `PIPELINE_STAGES` (ligne 539) définit `submitted / interview_scheduled / on_hold / accepted / rejected / blocked / cancelled`. `effectivePipelineStage()` (ligne 566) traduit `reviewed → accepted`. **Le même état a deux noms selon la colonne consultée.** Toute requête analytique doit connaître la table de correspondance. | **[C]** |
| R2 | **`pipeline_stage` peut ne pas exister.** `updatePipelineStage()` retourne `false` silencieusement si `hasPipelineStageColumn()` est faux. Sur un déploiement non migré, l'étape de pipeline est perdue sans erreur ni trace. | **[C]** |
| R3 | **Aucun branchement vers la formation.** L'acceptation ne déclenche aucune inscription à un parcours d'accueil. `EnlistmentAcceptanceProvisioningService` n'importe aucun service `Training\*`. | **[C]** |
| R4 | **Aucun branchement vers le dossier personnel.** Les données riches de la candidature (spécialité, expérience milsim, niveau ACE/ACRE, disponibilité, config système) restent dans `enlistments`. `personnel_profiles` est créé vide. Le candidat re-saisira tout. | **[C]** |
| R5 | **Aucune affectation d'unité à l'acceptation.** Le membre entre « hors unité ». | **[C]** |
| R6 | **Champs de disponibilité éclatés.** `availability`, `weekly_availability`, `availability_wed_sat` — trois colonnes texte pour un concept. | **[C]** |
| R7 | **Vue admin monolithique.** `views/admin/recruitments/show.php` fait 1 997 lignes. | **[C]** |

### 5.3 Améliorations, par catégorie

**Contenu** — expliquer chaque statut au candidat dans le portail de suivi (aujourd'hui il voit un
état, pas ce qu'il implique ni le délai attendu). **[→]**

**Workflow** — unifier `status` et `pipeline_stage` en une seule colonne, avec migration de
`reviewed` vers `accepted` et une table de transitions autorisées explicite. **[→]**

**Automatisation** — à l'acceptation : (a) inscrire au parcours d'accueil, (b) pré-remplir
`personnel_profiles` depuis la candidature, (c) proposer une affectation d'unité, (d) créer une
tâche « affecter » si aucune unité choisie. **[→]**

**UX/UI** — découper `show.php` en onglets (Dossier / Entretien / Échanges / Décision / Historique),
en s'appuyant sur la référence `docs/frontend/reference-ux-mobile.md`. **[→]**

**Données** — normaliser la disponibilité en une structure unique (créneaux + fuseau). **[→]**

**Administration** — tableau de bord recrutement avec taux de conversion par étape et délai moyen
par étape (les données `enlistment_timeline` le permettent déjà). **[→]**

**Traçabilité** — la timeline existe ; y ajouter systématiquement les transitions de statut, qui
n'y sont aujourd'hui pas toutes tracées. **[V]** (à confirmer par lecture de `EnlistmentTimelineRepository`)

---

## 6. Audit — Formation

Le LMS est **techniquement le module le plus riche** du projet.

### 6.1 Existant **[C]**

- **Structure pédagogique** : `training_courses` → `training_modules` → `training_lessons`,
  avec `position`, `is_required`, `estimated_minutes`, `learning_objectives`, `instructor_notes`.
- **Inscriptions** : `training_enrollments` (`assignment_type`, `status`, `assigned_by`,
  `expires_at`, `motivation_text`) — assignation staff **et** auto-inscription avec approbation.
- **Prérequis** : `enrollment_policy_json.prerequisite_course_ids` évalué par
  `TrainingEnrollmentPolicyService` — **cours → cours uniquement**.
- **Progression** : `training_progress` par leçon (`progress_percent`, `time_spent_seconds`,
  `last_position_seconds`) — granularité fine, reprise de lecture incluse.
- **Quiz** : `training_quizzes` / `_questions` / `_answers` / `_attempts` / `_responses`.
- **Certification** : `training_certificates` avec numéro, score final, `expires_at` calculé
  depuis `training_courses.validity_days`, PDF généré (`TrainingCertificatePdfService`, 1 067 lignes),
  partage (`TrainingCertificateShareService`).
- **Compétences** : `competencies`, `competency_domains`, `competency_frameworks`,
  `competency_levels`, `training_competency_matrices`, `competency_grade_requirements`,
  `CompetencyUserJourneyService`, `CompetencyMatrixController`.
- **Instructeurs** : `training_trainer_roles`, `trainer_validation_logs`, `TrainingSessionInstructorGuard`.
- **Expiration** : `TrainingExpireCronJob`.
- **Studio d'édition** : `AdminTrainingStudioController` (2 078 lignes), canvas, thèmes,
  pages personnalisées, export PDF, échange de cours entre communautés.
- **Publications** : `training_publications` + révisions + annexes + accusés de lecture.
- **Documents pédagogiques** : `document_links.entity_type = 'training'` — **ce lien fonctionne**.

### 6.2 Le problème central **[C]**

Le LMS produit un **certificat**. Il ne produit pas de **qualification exploitable par le reste
du système**.

```
training_enrollments (completed)
        │
        ▼
TrainingCertificateService::issueCertificate()
        │
        ▼
training_certificates ──────► PDF ──────► fin de chaîne
        │
        ╳  aucune écriture vers personnel_qualifications
        ╳  aucune écriture vers user_certifications
        ╳  aucune notification aux effectifs
        ╳  aucun effet sur les permissions ou fonctions
```

`certifications` possède pourtant une colonne `training_course_id` : **le pont était prévu, il n'a
jamais été construit.** **[C]**

### 6.3 Réponses aux questions métier

| Question | Réponse aujourd'hui | Marqueur |
|---|---|---|
| Qui est formé à quoi ? | Partiellement — via `training_certificates` joint à `training_enrollments`, aucun écran ne l'expose en vue « effectif » | **[C]** |
| Qui peut occuper quelle fonction ? | **Non** — aucune relation fonction ↔ qualification requise | **[C]** |
| Qui doit encore être formé ? | **Non** — pas de notion de formation obligatoire par rôle/unité (`is_mandatory` existe sur le cours, mais globalement, pas par population) | **[C]** |
| Quelle qualification expire bientôt ? | Partiellement — `training_certificates.expires_at` existe et `TrainingExpireCronJob` tourne, mais aucun tableau de bord d'expiration par unité | **[C]** |
| Quelle formation faut-il pour telle activité ? | **Non** — `community_events` n'a aucun champ de prérequis | **[C]** |

### 6.4 Autres constats

- **[C]** `legacy_training_certificates`, `legacy_training_modules`, `legacy_training_progress`
  coexistent avec les tables actives — dette de migration non soldée.
- **[C]** Deux systèmes de compétences parallèles : `competencies`/`competency_*` (moderne,
  migration `20260408000001`) et `knowledge_units`/`module_knowledge`/`module_competencies`
  (chaîne pédagogique, migration `20260416000001_pedagogy_chain.sql`). Le recouvrement est **[V]**.
- **[C]** `TrainingController` : 1 789 lignes, 11 appels `Csrf::validate()` en dur.

---

## 7. Audit — Opérations / RSVP

### 7.1 Existant **[C]**

**Événement** (`community_events`) : titre, description, lieu, `campaign_tag`,
`event_type` (`operation`/`formation`/`evenement`/`autre`), début/fin, annulation avec motif.
Enrichi par migration : `conditions_general`, `conditions_special`, `cover_image_path`,
`schedule_json` (phases avec code couleur), `tags_json`.

**RSVP** (`community_event_rsvps`) : `yes`/`no`/`maybe`, `checked_in_at`, `reminder_sent_at`,
`absence_reason` + `absence_note` (migration `20260418113000`), historique
(`community_event_rsvp_history_migration.php`).

**Slots** (`community_event_slots` + `_assignments`) : postes nommés, capacité, `unit_id`,
`loadout_notes`, `position` d'affichage, **liste d'attente automatique** (`waitlist_position`),
contrainte `UNIQUE (event_id, user_id)` — un membre ne peut occuper qu'un poste.

**Autour** : flux calendrier iCal personnel (`CommunityCalendarFeedTokenService` → token signé),
rappels e-mail J-1, `/pointage` pour le check-in, API RSVP JSON pour le dashboard,
API publique `/integrations/v1/evenements` (clé API), tableau opérationnel séparé
(`ops_board_items` : fiches, FRAGO, postures, modèles, validation, lien public par token),
slides de briefing (`TacticalBriefingSlideRepository` + commentaires).

### 7.2 Vérification de la chaîne demandée

```
Opération → RSVP → effectifs disponibles → qualifications → affectation → briefing → présence → RETEX
    ✅        ✅           ⚠ partiel          ❌ absent        ✅ (slots)     ❌ non lié     ⚠ partiel   ❌ non lié
```

| Maillon | État | Preuve | Marqueur |
|---|---|---|---|
| Opération → RSVP | **OK** | `CommunityEventsController::rsvp` | **[C]** |
| RSVP → effectifs disponibles | **Fragile** | Aucun croisement avec `personnel_profiles.deployable` ni `personnel_absences` ; l'organisateur ne voit pas qui est indisponible sans avoir répondu | **[C]** |
| Qualifications → affectation | **Non implémenté** | `community_event_slots` n'a aucune colonne de qualification requise ; `CommunityEventSlotService` (77 lignes) ne vérifie que la capacité | **[C]** |
| Affectation → briefing | **Cassé** | `document_links.entity_type` = `enum('training','equipment_class','unit','user')` — **`event` n'existe pas**. Impossible d'attacher un document à une opération. | **[C]** |
| Briefing → présence | **Non lié** | `ops_board_items` et `community_events` n'ont pas de clé commune | **[C]** |
| Présence → RETEX | **Non lié** | `checked_in_at` (table `community_event_rsvps`) vs AAR indexé par `missionId` ATAK. Deux référentiels. | **[C]** |

### 7.3 Fonctionnalités sous-exploitées **[C]**

Ces capacités **existent et fonctionnent**, mais ne servent presque à rien faute de branchement :

1. **`community_event_slots.unit_id`** — permet de réserver un poste à une unité ; aucune UI ne
   semble filtrer dessus. **[V]** sur l'UI, **[C]** sur le schéma.
2. **`schedule_json`** (phases horaires colorées) — rendu joliment côté vue mais jamais exploité
   pour des rappels, un minutage ou un lien vers l'AAR.
3. **`absence_reason` / `absence_note`** — collectées, mais aucun tableau de bord d'absentéisme.
4. **`campaign_tag`** — un embryon de campagne multi-opérations, sans écran de campagne.
5. **Historique RSVP** — table alimentée, aucune statistique de fiabilité de présence par membre.
6. **Flux iCal personnel** — excellente fonctionnalité, exposée dans un `<input readonly>` en bas
   du bandeau, sans explication ni bouton « copier ».
7. **`event_type = 'formation'`** — un événement peut être une formation, mais il n'est pas
   rattachable à un `training_course`.

---

## 8. Audit — Effectifs

### 8.1 Existant **[C]**

`personnel_profiles` est riche : nom de personnage, indicatif, grade affiché (+ override),
rôle principal/secondaire, `personnel_job_role_id`, unité principale, niveau d'habilitation,
portrait/bannière, groupe sanguin, nationalité, langues, date d'engagement, devise,
`readiness_score`, notes de commandement, matricule interne, classe d'équipement, kit, radio,
véhicules autorisés, spécialité d'arme, `deployable`.

Autour : `personnel_extras`, `personnel_service_history`, `personnel_org_history`,
`personnel_absences`, `personnel_deployments`, `personnel_stage_bilans`,
`personnel_roleplay_timeline`, `personnel_admin_data`, `personnel_media`,
`SeniorityEngine` + `SenioritySummaryService` (ancienneté), `MatriculeService`,
`MemberOffboardingService`, `ElevationApprovalService`, ORBAT (`OrbatApiController`,
`orbat_canvas.php` — 1 404 lignes), `EffectifsWorkspaceController` (1 484 lignes).

### 8.2 Est-ce la source de vérité ? **Non.** **[C]**

| Information | Emplacements concurrents |
|---|---|
| Affectation d'unité | `user_units` · `personnel_assignments` · `personnel_profiles.primary_unit_id` |
| Fonction / rôle métier | `personnel_profiles.primary_role` (texte) · `personnel_profiles.secondary_role` (texte) · `personnel_profiles.personnel_job_role_id` (FK) · `personnel_assignments.role_name` (texte) · `positions` + `user_positions` · `personnel_profile_job_roles` |
| Grade | `users.grade_id` · `personnel_profiles.rank_display` · `personnel_profiles.rank_display_override` · `tenant_grade_overrides` |
| Identité affichée | `users.display_name` · `users.callsign` · `user_profiles.arma_callsign` · `personnel_profiles.character_name` · `personnel_profiles.callsign` · `atak_operator_ids.call_sign` |
| Qualifications | `personnel_qualifications` (morte) · `training_certificates` · `user_certifications` (morte) · `user_badges` · `competency_user_progress` |
| Rôles de sécurité | `roles`+`user_roles` · `role_definitions` · `tenant_user_roles` · `site_role_assignments` · `user_permission_overrides` · `personnel_job_role_permissions` |

Le code de `PersonnelAssignmentRepository` reconnaît explicitement la situation et tente de la
gérer par synchronisation, pas par unification (lignes 21-22, 97-124, 311-350).

### 8.3 Autres constats

- **[C]** `personnel_qualifications` n'a **pas de colonne `tenant_id`** et le repository ne filtre
  jamais par tenant. Isolation assurée indirectement par `user_id` seulement.
- **[C]** `readiness_score` est stocké en dur sur le profil (`tinyint`), alors qu'un score parallèle
  est recalculé dans `UnitRepository` depuis `user_certifications` — table jamais alimentée. Deux
  scores de préparation, dont un toujours faux.
- **[C]** `views/personnel/file.php` : 1 943 lignes, une seule vue pour toute la fiche.
- **[C]** Commit récent `b2550be` : « restreindre l'annuaire effectifs » — le module a reçu
  de l'attention récente, signe qu'il est actif.

---

## 9. Audit — ATAK / Overwatch

### 9.1 Objectif actuel **[C]**

Pont temps réel entre le mod Arma 3 « Overwatch » et le portail : positions d'unités, marqueurs,
chat tactique, 9-line/MEDEVAC/CAS, SALUTE/SIGINT, zones de danger, IFF, logistique, QRF,
codes laser, désignateurs, imagerie de reconnaissance, replay/AAR, ordres, SOI/PACE,
appariement téléphone (QR + token), roleplay médical.

Volume : `AtakApiController` = **8 230 lignes**, ~80 routes `/api/atak/*`, 15 tables `atak_*`,
plus de 40 fichiers JS dédiés, `atak.css` = 205 Ko. `CHANGELOG-ATAK.md` fait 38 Ko — c'est le
chantier le plus actif du dépôt.

### 9.2 Données et identité **[C]**

- `atak_units` : `tenant_id`, `map_id`, **`call_sign` (varchar)**, `role` (varchar), `status`,
  `grid_ref`, `heading`, `extra` (JSON). **Aucune colonne `user_id`** — vérifié : aucune migration
  `ALTER TABLE atak_units` n'en ajoute.
- `atak_operator_ids` : `tenant_id`, `user_id`, `call_sign`, `military_id` (format `MID-XXXX`).
  C'est le seul pont ATAK ↔ compte, et il fonctionne par **correspondance de chaîne** sur
  l'indicatif.
- Liaison en jeu : `POST /api/atak/game-link/redeem` (code court TTL) ou
  `/api/atak/game-link/by-steam` (`users.steam_id`).

**Conséquence [P]** : si un opérateur change d'indicatif en jeu, ou si deux membres utilisent le
même, le rattachement à l'effectif se rompt silencieusement. Le schéma ne peut pas l'empêcher.

### 9.3 Sécurité **[C]**

- Le préfixe `/api/atak/` est protégé par défaut (`ComspecApiKeyAuth::pathRequiresProtection`
  retourne `true`), avec une liste blanche courte et justifiée (`ping`, `whoami`, `game-link/*`,
  `beta-register`, `mod-report`, QR d'appariement).
- Deux natures de clé : clé plateforme (`X_COMSPEC_KEY` en env, **accès à tous les tenants**) et
  clé par communauté (`tenant_atak_config.access_key`, résout le tenant). Comparaison en
  `hash_equals`. Correct.
- **Contournement par session activé par défaut** : `TACTICAL_API_ALLOW_SESSION` vaut `1` si non
  défini (`ComspecApiKeyAuth:tacticalSessionBypassEnabled`). Tout membre connecté accède aux API
  tactiques sans clé. `resolveTenantId()` fait primer la session sur le paramètre `tenant_id`, donc
  **pas de traversée inter-tenant** par ce biais — c'est correct. **[C]**
- **Hors production sans clé plateforme configurée, les API tactiques sont ouvertes**
  (`!$strict && $platform === '' && $presented === ''` → `return null`). Acceptable en dev,
  dangereux si `APP_ENV` n'est pas correctement positionné en production. **[C]**
- **`resolveTenantId()` accepte `tenant_id` en query ou en corps JSON** en dernier recours. Combiné
  à la clé plateforme, cela donne un accès multi-tenant paramétrable — voulu, mais à documenter
  comme privilège d'administration. **[C]**

### 9.4 Duplications avec le reste du système **[C]**

| Concept | Côté ATAK | Côté portail | Lien |
|---|---|---|---|
| Opérateur | `atak_units.call_sign` | `personnel_profiles.callsign` | chaîne, via `atak_operator_ids` |
| Équipe | `fire_teams` / `fire_units` | `units` / `user_units` | **aucun** |
| Mission | `missionId` (API, non persisté en table dédiée) | `community_events.id` | **aucun** |
| Rôle | `atak_units.role` (texte) | `personnel_job_roles` | **aucun** |
| Présence | `atak_last_activity`, `/api/atak/presence`, `/api/atak/perstat` | `community_event_rsvps.checked_in_at` | **aucun** |
| Document | ordres, templates d'ordres | `documents` | **aucun** |

### 9.5 Ce que le module pourrait devenir **[→]**

**Court terme (sans nouveau schéma majeur)**
- Afficher, dans l'ORBAT et la fiche personnelle, l'état de liaison ATAK et le `military_id` —
  la donnée existe (`atakOperatorsLinkedCount` est déjà calculé pour le dashboard).
- Alimenter le fil d'activité du portail avec les événements ATAK majeurs (9-line ouvert, MEDEVAC).
- Rendre `atak_operator_ids.call_sign` non modifiable librement, dérivé de `personnel_profiles.callsign`.

**Moyen terme**
- Ajouter `atak_units.user_id` (nullable) alimenté à la liaison → remplacer progressivement le
  join par chaîne. C'est le geste à plus fort effet de levier du module.
- Introduire une table `operations` canonique (§ 17) et faire de `missionId` une FK vers elle :
  l'AAR devient le RETEX de l'événement auquel les gens se sont inscrits.
- Rapprocher `fire_teams` de `units` : soit FK, soit fusion.

**Long terme**
- **Boucle complète** : opération créée sur le portail → slots pourvus selon qualifications →
  ORBAT poussé vers ATAK au lancement → positions et événements collectés en jeu → présence réelle
  réconciliée avec le RSVP → AAR généré → RETEX attaché à l'opération → statistiques individuelles
  (heures en opération, rôles tenus) remontées au dossier personnel → détection des besoins de
  formation.

---

## 10. Audit — Logique métier et terminologie

### 10.1 Termes ambigus et synonymes **[C]**

| Concept | Termes rencontrés | Où |
|---|---|---|
| Opération | « Événement », « Opération », « Manœuvre », « Mission », « Fiche opérationnelle », « Activité » | UI `/evenements` (« Événements & opérations »), `/manoeuvres`, `event_type='operation'`, `interteam_missions`, `ops_board_items`, `missionId` |
| Candidature | « Enlistment », « Candidature », « Recrutement », « Dossier » | table `enlistments`, route `/recrutement`, UI « Candidature » |
| Accepté | `reviewed` (colonne `status`) ↔ `accepted` (colonne `pipeline_stage`) | `EnlistmentRepository:571` |
| Membre | « Membre », « Effectif », « Opérateur », « Personnel », « Utilisateur », « Profil » | `users`, `personnel_profiles`, `dossier_operateur`, `/effectifs` |
| Unité | « Unité », « Groupe », « Équipe », « Fire team », « Section » | `units`, `fire_teams`, `fire_units`, `training_groups` |
| Fonction | « Rôle », « Poste », « Fonction », « Position », « Job role », « MOS » | `roles`, `positions`, `personnel_job_roles`, `UsArmyMosCatalog`, `primary_role` |
| Formation | « Formation », « Cours », « Training », « Parcours », « Cursus », « Module », « Publication » | `training_courses`, `/formation`, `training_modules` (≠ modules produit !) |
| Document | « Document », « Doctrine », « Courrier », « Publication », « Bibliothèque » | `documents`, `courrier_documents`, `training_publications` |
| Rôle (sécurité) | même mot que « rôle métier » | `roles` (RBAC) vs `primary_role` (fiction) |

### 10.2 Collisions de vocabulaire les plus coûteuses **[C]**

1. **« Module »** désigne : un module produit (`tenant_modules`), un module de cours
   (`training_modules`), un module de compétence (`module_competencies`), un module du mod ATAK
   (`atak_mod-blocks`, `AtakModulesSchema`). Quatre sens dans un même dépôt.
2. **« Rôle »** désigne un rôle RBAC *et* une fonction fictionnelle. `personnel_profiles.primary_role`
   est du texte libre à côté de `roles.slug` qui pilote les permissions.
3. **« Mission »** désigne une coopération inter-unités (`interteam_missions`) *et* une session
   ATAK (`missionId`). Aucune des deux n'est un `community_event`.
4. **« Unit »** désigne une unité organisationnelle (`units`) *et* une entité tactique sur la carte
   (`atak_units`). Le pluriel `/api/units` est ambigu jusqu'à lecture du contrôleur.

### 10.3 Lexique canonique proposé **[→]**

| Terme officiel | Définition | Utilisé aujourd'hui | Remplacement conseillé |
|---|---|---|---|
| **Opération** | Activité planifiée à laquelle on s'inscrit, qui se joue et qui produit un RETEX | `community_events` (`event_type='operation'`), `ops_board_items`, `missionId` | Table `operations` unique ; `community_events` devient sa vue « agenda » |
| **Événement** | Terme générique de l'agenda (opération, formation, réunion, autre) | mélangé avec « opération » | Réserver au conteneur d'agenda ; ne jamais l'employer pour désigner une opération |
| **Manœuvre** | — | route `/manoeuvres` → `PointageController` | **Supprimer** : synonyme non défini d'« opération » |
| **Mission** | Engagement conjoint avec une autre communauté | `interteam_missions` + `missionId` ATAK | Réserver à la coopération ; renommer `missionId` en `operationId` côté API |
| **Membre** | Compte rattaché à une communauté | `users` | Conserver |
| **Profil** | Données personnelles hors jeu | `user_profiles` | Conserver |
| **Dossier opérateur** | Fiche de personnage in-game + carrière | `personnel_profiles` | Conserver, renommer la table `operator_dossiers` à terme |
| **Effectif** | Vue agrégée « membre + affectation + grade + qualifications » | `/effectifs` | Conserver comme **vue**, jamais comme table |
| **Affectation** | Rattachement daté membre ↔ unité ↔ fonction | 3 tables | Table `assignments` unique |
| **Fonction** | Poste tenu dans l'organisation | `positions`, `personnel_job_roles`, `primary_role` | Table `job_roles` unique ; supprimer les colonnes texte |
| **Rôle** | **Uniquement RBAC** | `roles` + 5 autres | Restreindre strictement au sens sécurité |
| **Qualification** | Droit d'exercer, obtenu par formation, avec validité | `personnel_qualifications` (morte), `training_certificates`, `user_certifications` (morte) | Table `qualifications` unique, alimentée par le LMS |
| **Certificat** | **Preuve documentaire** d'une qualification | `training_certificates` | Conserver, mais subordonné à la qualification |
| **Compétence** | Savoir-faire évaluable, plus fin que la qualification | `competencies` + `knowledge_units` | Choisir un seul des deux référentiels |
| **Unité** | Élément de l'ORBAT | `units` | Conserver |
| **Équipe** | Groupement tactique temporaire | `fire_teams` | Conserver, rattacher à `units` |
| **Présence** | Participation constatée à une opération | `checked_in_at` | Table `attendances` distincte du RSVP |
| **RSVP / Intention** | Réponse déclarative avant l'opération | `community_event_rsvps` | Renommer en « intention de participation » dans l'UI |

### 10.4 Séparation des objets métier **[C]**

L'exemple donné dans la demande est exactement le point de rupture observé :

| Objet | Devrait être | Est actuellement |
|---|---|---|
| Membre | compte | `users` — OK |
| Profil | données perso | `user_profiles` — OK |
| Effectif | **vue** | correctement une vue — OK |
| Affectation | relation datée | **3 tables** — ✗ |
| Qualification | entité avec validité | **table morte** — ✗ |
| Fonction | référentiel | **6 emplacements** — ✗ |
| Présence opérationnelle | fait constaté | **colonne du RSVP** — ✗ (une intention et un fait dans la même ligne) |

Le dernier point mérite attention : mettre `checked_in_at` dans `community_event_rsvps` fusionne
« ce que j'ai annoncé » et « ce que j'ai fait ». Un membre présent sans avoir répondu ne peut pas
être pointé sans créer une ligne RSVP rétroactive, ce qui fausse les statistiques d'intention.

---

## 11. Audit UX/UI desktop / mobile

### 11.1 Références internes

Le document `docs/frontend/reference-ux-mobile.md` (créé avec cet audit) formalise les principes
extraits de **Documents**, **Événements** et **Messagerie** : hiérarchie descendante, deux niveaux
de rayon, mobile-first strict, trois niveaux d'action, états vides avec phrase d'action,
divulgation progressive sans JavaScript, statut par couleur **et** texte.

Constat notable **[C]** : `views/messages/index.php` n'utilise qu'**un seul** point de rupture
responsive (`sm:p-7`). C'est un indicateur de conception mobile-first authentique, pas d'un
desktop rétréci.

### 11.2 État général de la couche de présentation **[C]**

| Constat | Détail |
|---|---|
| **60 fichiers CSS, 1,5 Mo** | Dont `atak.css` à 205 Ko. Un fichier par page/module, peu de mutualisation. |
| **Trois systèmes de style cohabitent** | Tailwind (via `tailwind.css` compilé + `tailwind_cdn_or_build.php`), le mini design system `design-system.css` (`.ds-btn`, `.ds-card`…) documenté dans `docs/frontend/design-system.md`, et des `<style>` inline en tête de vue (ex. `views/community/events.php`). |
| **`athena-ds/` sous-utilisé** | 6 fichiers de tokens (`colors`, `spacing`, `typography`, `effects`, `fonts`, `base`) — la fondation existe mais les vues utilisent majoritairement des classes Tailwind brutes. |
| **Vues monolithiques** | 5 vues dépassent 1 400 lignes. `views/atak.php` : 2 702. |
| **11 partials UI seulement** | `badge`, `button`, `empty_state`, `skeleton`, `stepper`, `tag`, `confirm_dialog`, `list_row_link`, `next_steps_block`, `soft_lock`, `user_avatar`. Une base saine, manifestement peu appelée au vu de la duplication de markup. |
| **Régression de build observée** | Commit `d5d4054` : « bannière cookies (build Tailwind obsolète) » — le CSS compilé peut diverger des vues. |

### 11.3 Par surface

**Desktop** — **Correct**. Largeurs maîtrisées (`max-w-[1600px]`/`[1800px]`), grilles denses
lisibles. Faiblesse : les écrans d'administration (recrutement, effectifs, studio) empilent
verticalement au lieu d'exploiter la largeur.

**Tablette** — **Fragile [P]**. Très peu de classes `md:` par rapport à `sm:` et `lg:` : la plage
768–1024 px hérite souvent de la mise en page mobile, y compris sur les écrans denses. Non
vérifiable sans rendu.

**Mobile** — **Solide** sur les trois pages de référence, **hétérogène** ailleurs. Les grosses
vues admin (1 500–2 000 lignes) sont **[P]** pénibles en mobile : formulaires longs, tableaux
larges, absence de repli en cartes.

### 11.4 Pages à rapprocher du niveau de référence **[→]**

Par priorité, sans uniformisation aveugle :

1. `views/personnel/file.php` (1 943 l.) — la page la plus consultée après le dashboard.
2. `views/admin/recruitments/show.php` (1 997 l.) — un workflow présenté comme un formulaire.
3. `views/partials/dashboard_command_center.php` (1 291 l.) — blocs sans hiérarchie stable.
4. Catalogue / parcours de formation — cas d'usage identique à Documents.

**À ne pas aligner** : `views/atak.php`, `views/overwatch/index.php`, les shells LMS/Studio,
la carte tactique. Interfaces outil plein écran, contraintes propres.

### 11.5 Défauts précis relevés **[C]**

- Slot « Plus » de la barre mobile inerte (`bottom_nav.php:18`).
- Le lien d'abonnement calendrier est un `<input readonly>` sans bouton de copie ni explication
  du fonctionnement (`views/community/events.php`).
- Les statistiques d'en-tête de la page Documents comportent une carte « Raccourci » qui n'est
  pas une statistique et occupe deux colonnes — bruit dans une grille de compteurs.

---

## 12. Audit des contenus

### 12.1 Constat général **[C]**

La qualité rédactionnelle des pages récentes est **au-dessus de la moyenne** : les messages
expliquent le « pourquoi », pas seulement le « quoi ». Exemples relevés :

> « Aucun destinataire n'est configuré pour recevoir les messages internes sur cette communauté.
> Préférez le forum ou contactez un responsable pour qu'un rôle habilité soit désigné. »

> « Chaque envoi avec un **objet renseigné** ouvre une nouvelle conversation. Sans objet, votre
> texte peut compléter une demande récente encore sans réponse, pour éviter les doublons. »

Ce niveau doit être la norme. Il ne l'est pas partout.

### 12.2 Manques par domaine

| Domaine | Ce qui manque | Catégorie |
|---|---|---|
| **Statuts de candidature** | Aucune explication de ce que `submitted` / `interview_scheduled` / `on_hold` impliquent pour le candidat, ni de délai indicatif | Mineur |
| **Statuts de qualification** | `valid` / `expiring` / `expired` / `in_progress` sans définition ni seuil (« expirant » = combien de jours ?) | Mineur |
| **États vides** | Le modèle de la messagerie (icône + état + action) n'est pas généralisé | Mineur |
| **Erreurs génériques** | `catch (\Throwable)` × 526 aboutit souvent à un message vague ; le message « Cette fonctionnalité n'est pas encore prête sur le serveur… » de `public/index.php` fuit un détail d'implémentation à l'utilisateur | Intermédiaire |
| **Onboarding nouveau membre** | Aucune séquence guidée après acceptation | Intermédiaire |
| **Vitrine publique** | Rythme, engagement attendu, prérequis matériels, durée du recrutement | Intermédiaire |
| **Dashboards** | Beaucoup de compteurs, peu de « et donc ? ». Un chiffre sans seuil ni tendance n'est pas actionnable | Intermédiaire |
| **Explication de l'écosystème** | Rien n'explique au membre comment formation, qualification et opération s'articulent — d'autant que l'articulation n'existe pas encore | Majeur |
| **Aide contextuelle** | `portal_help_modal.php` existe ; couverture réelle **[V]** | Majeur |

### 12.3 Classement des ajouts **[→]**

**Ajouts mineurs** (texte seul, aucun schéma)
- Définition de chaque statut, affichée en infobulle sur le badge.
- Phrase d'action dans tous les états vides.
- Explication du flux calendrier iCal + bouton « copier ».
- Seuils explicites (« expire dans moins de 30 jours »).

**Ajouts intermédiaires** (texte + petite adaptation fonctionnelle)
- Bandeau « Vos prochaines étapes » sur le dashboard d'un membre récent.
- Bloc « Comment ça marche » sur la vitrine, alimenté par les données réelles (prochaine
  opération, effectif actif, offres ouvertes).
- Message de refus de candidature paramétrable par motif.
- Tendance et seuil sur chaque compteur de dashboard.

**Ajouts majeurs** (nouveaux ensembles éditoriaux)
- Guide d'intégration en 4 étapes, opposable, avec suivi de complétion par membre.
- Référentiel des fonctions publié : pour chaque fonction, la formation requise, les
  qualifications associées, les titulaires actuels. C'est le contenu qui manque le plus, et il
  dépend du chantier « qualifications » (§ 17).

---

## 13. Audit des branchements

Format : **Source → Destination → Données → Mécanisme → État → Problème → Amélioration**

| # | Branchement | Données transmises | Mécanisme | État | Problème | Amélioration |
|---|---|---|---|---|---|---|
| B1 | Inscription → Communauté | compte, `tenant_id`, rôle initial | `RegisterController` (transaction) + `TenantBootstrapService` | **OK** | — | — |
| B2 | Communauté → Recrutement | `tenant_id`, offres publiées | `recruitment_openings` + `RecruitmentOpeningForumPublisher` | **OK** | — | — |
| B3 | Recrutement → Effectifs | compte, rôle `member`, `personnel_profiles` vide | `EnlistmentAcceptanceProvisioningService` | **À renforcer** | Le dossier n'hérite d'aucune donnée de la candidature ; aucune unité affectée | Pré-remplir le dossier ; proposer une affectation dans le même écran |
| B4 | Recrutement → Formation | — | **aucun** | **Non implémenté** | Le nouveau membre n'est inscrit à aucun parcours d'accueil | Déclencher l'inscription au parcours obligatoire à l'acceptation |
| B5 | Formation → Qualifications | — | **aucun** | **Cassé** | `issueCertificate()` n'écrit que `training_certificates` ; `personnel_qualifications` et `user_certifications` ne sont écrites nulle part | **Chantier n° 1** : émettre une qualification à la certification |
| B6 | Qualifications → Effectifs | lecture seule | `PersonnelQualificationRepository::listForUser` | **Cassé** | La table lue n'est jamais alimentée → panneau toujours vide | Découle de B5 |
| B7 | Qualifications → Opérations | — | **aucun** | **Non implémenté** | Aucun prérequis vérifié à l'inscription ; `community_event_slots` n'a pas de champ de qualification | Ajouter `required_qualification_id` au slot, vérifier dans `CommunityEventSlotService::signUp` |
| B8 | Effectifs → Opérations | `user_id` uniquement | RSVP / slots | **À renforcer** | `deployable`, `personnel_absences`, unité et grade ne sont pas exploités côté organisateur | Vue « effectif disponible » croisant absences et RSVP |
| B9 | Effectifs → ATAK | `user_id` ↔ `call_sign` ↔ `military_id` | `atak_operator_ids` + liaison Steam/code | **Fragile** | Pont par chaîne de caractères ; `atak_units` n'a pas de `user_id` ; rupture silencieuse si l'indicatif change | Ajouter `atak_units.user_id` nullable, alimenté à la liaison |
| B10 | RSVP → Opérations | statut, motif d'absence, slot | `CommunityEventAttendanceService` (436 l.) | **OK** | — | — |
| B11 | RSVP → Effectifs | — | **aucun** | **Non implémenté** | L'historique de présence n'alimente ni le dossier ni l'ancienneté ni la fiabilité | Indicateur de fiabilité de présence sur la fiche |
| B12 | Opérations → ATAK | — | **aucun** | **Non implémenté** | `missionId` (ATAK) et `community_events.id` sont deux référentiels disjoints | Table `operations` canonique, `missionId` = FK |
| B13 | Documents → Formations | `document_id` ↔ `training_course_id` | `document_links.entity_type='training'` | **OK** | — | — |
| B14 | Documents → Opérations | — | **impossible** | **Cassé** | `document_links.entity_type` = `enum('training','equipment_class','unit','user')` — pas de valeur `event` | Étendre l'enum, ou passer à un polymorphisme non contraint |
| B15 | Notifications → événements métier | — | 3 silos indépendants | **Fragile** | `forum_notifications`, `courrier_document_notifications`, `cooperation_notification_outbox` ; l'ActivityHub n'agrège que forum + courrier + messages. **Aucune notification in-app** pour formation, RSVP, recrutement, qualification | Table `notifications` unique polymorphe |
| B16 | Briefing → Opération | — | **aucun** | **Non implémenté** | `ops_board_items` et `tacticalialBriefingSlides` sans FK vers `community_events` | FK `operation_id` |
| B17 | Présence → RETEX | — | **aucun** | **Non implémenté** | AAR indexé par `missionId`, présence par `event_id` | Découle de B12 |
| B18 | Formation → Fonctions/Permissions | — | **aucun** | **Non implémenté** | Une qualification ne donne accès à rien dans l'outil | Optionnel, à décider (risque de rigidité) |

### 13.1 Problèmes transverses identifiés **[C]**

| Catégorie | Constat |
|---|---|
| **Données dupliquées** | Affectations ×3, fonctions ×6, identité ×7, qualifications ×5 |
| **Foreign keys manquantes** | `community_events` ↔ `ops_board_items` ; `community_events` ↔ `missionId` ; `atak_units` ↔ `users` ; `fire_teams` ↔ `units` ; `personnel_qualifications` ↔ `training_courses` |
| **Relations implicites** | ATAK ↔ effectifs par `call_sign` ; `missionId` en chaîne libre |
| **Logique dupliquée** | Validation CSRF réécrite 382 fois ; résolution de tenant réimplémentée par contrôleur ; garde de feature réécrite à chaque action de `CommunityEventsController` |
| **Statuts incompatibles** | `enlistments.status` vs `pipeline_stage` ; `training_certificates.status` vs `personnel_qualifications.status` (ENUM différent) vs `user_certifications.status` (accepte `active`/`valid`/`completed`/NULL/`''` — cf. `UnitRepository:167`) |
| **Suppressions risquées** | 376 FK déclarées, mais les liens **inter-modules** sont absents : supprimer un `training_course` ne casse rien car rien ne le référence hors du LMS. Le risque est inversé : les données orphelines ne sont pas détectables. |
| **Références orphelines** | `document_links.entity_id` sans FK (polymorphe) ; `personnel_qualifications.issued_by` sans FK |
| **Permissions incohérentes** | `/api/operations/*` sans garde vs `/api/operations/doctrine/*` avec `AuthMiddleware` ; CSRF global sur `/back-office/` seulement |
| **AJAX fragile** | `CustomMapsApiController`, `MePreferencesApiController`, `DoctrineApiController` valident le CSRF via des méthodes privées distinctes, chacune avec sa propre signature |
| **Transactions absentes** | 23 `beginTransaction` sur ~317 k lignes. `EnlistmentAcceptanceProvisioningService` en a une (bien) ; `TrainingCertificateService::issueCertificate` n'en a pas alors qu'il écrit un certificat **et** génère un PDF |
| **Journalisation** | `AuditLogRepository`, `SecurityEventRepository`, `AdminActionRepository`, `training_audit_log`, `document_audit_log`, `forum_moderation_logs`, `role_assignments_log` — **7 systèmes de journalisation distincts**, sans vue unifiée |
| **Contrôle d'intégrité** | 118 vérifications `information_schema.TABLES` à l'exécution : le code ne fait pas confiance à son propre schéma |

---

## 14. Dette technique et risques

### 14.1 Sécurité

| # | Risque | Détail | Gravité | Marqueur |
|---|---|---|---|---|
| S1 | **API `/api/operations/*` non authentifiée** | 14 routes sans middleware et hors `protected_prefixes`. Dont `POST /sitrep/report`, `POST /logistics/update`, et l'export AAR/RETEX PDF/JSON | **P0** | **[C]** |
| S2 | **CSRF global limité à `/back-office/`** | 181 routes `/admin/*` et toutes les routes membres dépendent d'appels manuels. `DocumentsController` : 3 routes POST, 0 validation | **P0** | **[C]** |
| S3 | **Ouverture hors production** | Si `APP_ENV` ≠ `production`/`prod`, sans `X_COMSPEC_KEY` ni `TACTICAL_API_STRICT`, toutes les API tactiques sont ouvertes | **P1** | **[C]** |
| S4 | **Erreurs qui fuient de l'information** | `public/index.php` renvoie « Cette fonctionnalité n'est pas encore prête sur le serveur. Demandez à un administrateur de lancer la mise à jour de la base » lorsqu'il détecte `1146`/`42S02` dans le message d'exception | **P2** | **[C]** |
| S5 | **Cookie de session non `secure` par défaut** | `app/Config/auth.php:7` fait défaut à `false`, et `.env.example:58` livre `SESSION_SECURE_COOKIE=false` **à côté de `APP_ENV=production` (ligne 8)**. Un déploiement qui recopie `.env.example` tel quel tourne en production avec un cookie de session transmissible en clair. `httponly` et `samesite=Lax` sont corrects, mais ne compensent pas un downgrade HTTP. | **P1** | **[C]** |
| S6 | **Token CSRF unique par session, sans rotation** | `Csrf::token()` génère une fois et ne tourne jamais, même après `Session::regenerate()` | **P2** | **[C]** |

### 14.2 Architecture

| # | Dette | Mesure | Marqueur |
|---|---|---|---|
| A1 | **God controllers** | `AtakApiController` 8 230 l. ; `AdminTrainingStudioController` 2 078 ; `AdminRecruitmentsController` 1 829 ; `InterteamMissionWebController` 1 822 ; `TrainingController` 1 789 | **[C]** |
| A2 | **God method** | `HomeController::dashboard()` s'étend des lignes 98 à ~984, avec 36 appels `Container::get()` en service locator | **[C]** |
| A3 | **Conteneur DI manuel** | `Container.php` : 2 170 lignes de fabriques écrites à la main | **[C]** |
| A4 | **Routeur linéaire** | `Router::dispatch()` boucle sur 1 241 routes et exécute un `preg_match` par route jusqu'à correspondance. Pas de table de hachage par méthode, pas de cache | **[C]** |
| A5 | **Deux systèmes de migration** | 93 fichiers `.sql` dans `migrations/` + 128 fichiers PHP dans `bootstrap/` + `run-migrations.php` de **167 Ko** | **[C]** |
| A6 | **Défiance envers le schéma** | 118 requêtes `information_schema` à l'exécution, dans le chemin de requête utilisateur | **[C]** |
| A7 | **526 `catch (\Throwable)` muets** | Beaucoup avalent l'erreur sans journalisation. Un incident métier passe inaperçu | **[C]** |
| A8 | **Deux planificateurs** | `CronRunner` (7 jobs) + `send-attendance-reminders.php` autonome | **[C]** |
| A9 | **Dump SQL de production versionné** | `u416380327_BDD_PROD.sql`, 782 Ko, à la racine du dépôt. À auditer pour données personnelles | **[C]** / risque **[V]** |
| A10 | **Vues monolithiques** | 5 vues > 1 400 lignes ; `views/atak.php` = 2 702 | **[C]** |

### 14.3 Tests

**[C]** 22 fichiers de test pour ~317 000 lignes. Répartition : 18 unitaires, 2 de contrat API,
2 sur le courrier. **Aucun test** ne couvre : le workflow de candidature, l'émission de certificat,
le RSVP et les slots, l'API ATAK, le RBAC, la résolution de tenant, la génération de PDF.
**[C]** `phpstan.neon` est configuré en **`level: 0`**, le niveau le plus permissif de l'outil
(il ne détecte guère que les classes et fonctions inconnues). La baseline vide
(`phpstan-baseline.neon` = 33 octets) ne traduit donc pas un code sain : l'analyse statique est
présente dans le projet mais pratiquement inerte. Monter progressivement à `level: 5` sur
`app/Repositories` puis `app/Services`, baseline à l'appui, donnerait un filet réel.

### 14.4 Risques de régression classés

| Zone | Risque | Pourquoi |
|---|---|---|
| Toute modification de schéma | **Élevé** | Deux systèmes de migration + 118 gardes `information_schema` |
| ATAK | **Élevé** | 8 230 lignes non testées, contrat partagé avec un mod déployé chez des tiers |
| Effectifs | **Élevé** | Triple stockage : corriger un côté désynchronise l'autre |
| Recrutement | **Moyen** | Bien structuré, mais dépend de la double machine à états |
| Formation | **Moyen** | Isolé du reste, donc peu de propagation |
| UI | **Faible** | Vues indépendantes |

---

## 15. Fonctionnalités existantes sous-exploitées

Classées par **rapport valeur/effort**. Toutes sont **[C]** présentes dans le code.

| # | Fonctionnalité | Où | Pourquoi elle ne sert pas | Débloquer par |
|---|---|---|---|---|
| 1 | **Slots d'opération avec liste d'attente** | `community_event_slots` + `CommunityEventSlotService` | Excellente mécanique, mais sans prérequis ni lien ORBAT, on ne sait pas qui *peut* tenir le poste | Prérequis de qualification (B7) |
| 2 | **`schedule_json`** (phases minutées) | `community_events` | Affiché, jamais exploité | Rappels par phase, minutage AAR |
| 3 | **Flux calendrier iCal signé** | `CommunityCalendarFeedTokenService` | Caché dans un `<input readonly>` sans explication | 3 lignes de contenu + bouton copier |
| 4 | **`absence_reason` / `absence_note`** | migration `20260418113000` | Collectées, jamais agrégées | Tableau d'absentéisme |
| 5 | **Historique RSVP** | `community_event_rsvp_history` | Table alimentée, non lue | Score de fiabilité de présence |
| 6 | **`campaign_tag`** | `community_events` | Embryon de campagne sans écran | Vue campagne (opérations liées, RETEX cumulé) |
| 7 | **Matrices de compétences** | `training_competency_matrices`, `CompetencyMatrixController` | Construites, non reliées aux fonctions ni aux opérations | Référentiel de fonctions (§ 17) |
| 8 | **`competency_grade_requirements`** | migration dédiée | Le lien compétence↔grade existe en base, sans exploitation visible | Parcours de progression de grade |
| 9 | **`personnel_absences`** | table | Non croisé avec les opérations | Vue « effectif disponible » |
| 10 | **`readiness_score`** | `personnel_profiles` | Champ stocké, alimentation **[V]** ; le score concurrent de `UnitRepository` se base sur une table morte | Recalcul depuis qualifications réelles |
| 11 | **`document_relations`** (parent/enfant) | table | Permet des recueils documentaires structurés ; usage **[V]** | Collections doctrinales |
| 12 | **`ops_board_items` — lien public par token** | `/back-office/tableau-operationnel/lien-public` | Partage externe d'un ordre d'opération : très utile en coopération, peu visible | Mise en avant dans le module coopération |
| 13 | **Échange de cours inter-communautés** | `TrainingCourseExchangeService` (1 020 l.) | Fonctionnalité forte et différenciante, enfouie dans le studio | Catalogue inter-communautés |
| 14 | **`atak_operator_ids.military_id`** | `MID-XXXX` | Identifiant stable et lisible, non affiché sur la fiche personnelle | 1 ligne dans la vue |
| 15 | **7 journaux d'audit** | `audit_logs`, `security_events`, `admin_actions`, `training_audit_log`, `document_audit_log`, `forum_moderation_logs`, `role_assignments_log` | Données de traçabilité riches, aucune vue unifiée | Journal d'activité transverse |

---

## 16. Petites modifications recommandées

Faible coût, faible risque.

| # | Modification | Objectif | Problème résolu | Modules | Complexité | Risque | Valeur | Priorité |
|---|---|---|---|---|---|---|---|---|
| P1 | Ajouter `/api/operations` à `protected_prefixes` (`config/tactical_api.php`) | Fermer l'API ouverte | S1 | ATAK, Opérations | Très faible | **Faible** (une ligne de config, cohérent avec `/api/replay/`) | **Critique** | **P0** |
| P2 | Ajouter `Csrf::validate()` dans les 3 actions POST de `DocumentsController` | Combler le trou CSRF le plus net | S2 | Documents | Très faible | Faible | Élevée | **P0** |
| P3 | Étendre `CsrfPostMiddleware` à `/admin/` (les appels manuels existants restent valides, le jeton est identique) | Défense en profondeur | S2 | Transverse | Faible | Moyen (à tester sur les POST admin sans jeton) | Élevée | **P1** |
| P4 | Corriger le slot « Plus » de `bottom_nav.php` | Récupérer 20 % de la nav mobile | UX | Navigation | Très faible | Nul | Moyenne | **P1** |
| P5 | Afficher `military_id` et l'état de liaison ATAK sur la fiche personnelle | Rendre visible un pont existant | Sous-exploité n° 14 | Effectifs, ATAK | Très faible | Nul | Moyenne | **P2** |
| P6 | Explication + bouton « copier » sur le flux iCal | Rendre utilisable une bonne fonctionnalité | Sous-exploité n° 3 | Opérations | Très faible | Nul | Moyenne | **P2** |
| P7 | Infobulle de définition sur chaque badge de statut | Compréhension métier | Contenu | Transverse | Faible | Nul | Moyenne | **P2** |
| P8 | Phrase d'action dans tous les états vides (modèle messagerie) | Homogénéité, guidage | Contenu | Transverse | Faible | Nul | Moyenne | **P2** |
| P9 | Journaliser les `catch (\Throwable)` des chemins métier critiques | Observabilité | A7 | Transverse | Faible | Faible | Élevée | **P1** |
| P10 | Supprimer la route `/manoeuvres` ou la fusionner avec `/pointage` | Terminologie | § 10 | Navigation | Très faible | Faible | Faible | **P3** |
| P11 | Rotation du jeton CSRF à `Session::regenerate()` | Durcissement | S6 | Core | Faible | Faible | Faible | **P3** |

---

## 17. Modifications importantes

Refonte ciblée d'une fonctionnalité ou d'un workflow.

### I1 — Chaîne de qualification **[chantier n° 1]**

| | |
|---|---|
| **Objectif** | Rendre les qualifications réelles et exploitables |
| **Problème résolu** | B5, B6, B7 — la rupture centrale du produit |
| **Modules** | Formation, Effectifs, Opérations |
| **Dépendances** | Décision préalable : conserver `personnel_qualifications` ou basculer sur `certifications`/`user_certifications` (qui possèdent déjà `training_course_id`) — **recommandation : garder `personnel_qualifications`, y ajouter `tenant_id`, `training_course_id`, `training_certificate_id`, et supprimer `certifications`/`user_certifications`** |
| **Étapes** | (1) migration de schéma ; (2) `TrainingCertificateService::issueCertificate()` émet une qualification dans la même transaction ; (3) `TrainingExpireCronJob` fait passer les qualifications en `expiring`/`expired` ; (4) écran « Qualifications » par unité ; (5) reprise de l'existant depuis `training_certificates` |
| **Complexité** | Moyenne |
| **Risque de régression** | **Faible** — on écrit dans une table aujourd'hui morte, rien ne peut casser en lecture |
| **Valeur** | **Maximale** — débloque à elle seule les 5 questions métier du § 6.3 |
| **Priorité** | **P0** |

### I2 — Objet « Opération » canonique **[chantier n° 2]**

| | |
|---|---|
| **Objectif** | Une opération = un identifiant, du briefing au RETEX |
| **Problème résolu** | B12, B16, B17 ; les 4 objets « opération » du § 2.3 |
| **Modules** | Opérations, ATAK, Documents, Coopération |
| **Dépendances** | Aucune technique ; forte dépendance de décision produit |
| **Approche recommandée** | Ne **pas** créer une 5ᵉ table. Promouvoir `community_events` en référentiel : y ajouter `operation_uid`, puis ajouter `event_id` (nullable) à `ops_board_items`, aux slides de briefing, et faire de `missionId` côté ATAK une référence à `operation_uid`. Rétrocompatibilité assurée par la nullabilité. |
| **Complexité** | Élevée |
| **Risque** | **Moyen** — contrat API partagé avec le mod déployé ; prévoir une période de double acceptation (`missionId` libre **ou** `operation_uid`) |
| **Valeur** | Très élevée |
| **Priorité** | **P1** |

### I3 — Unification des affectations

| | |
|---|---|
| **Objectif** | Une seule source de vérité pour « qui est où » |
| **Problème résolu** | Triple stockage (§ 8.2) |
| **Modules** | Effectifs, ORBAT, ATAK, Opérations |
| **Approche** | `personnel_assignments` devient canonique (le code la désigne déjà comme telle) ; `user_units` passe en vue SQL de compatibilité ; `personnel_profiles.primary_unit_id` devient dérivé et cesse d'être écrit |
| **Complexité** | Élevée |
| **Risque** | **Élevé** — 4 fichiers écrivent, plusieurs écrans lisent ; exige un audit de cohérence préalable et une bascule en deux temps |
| **Valeur** | Élevée |
| **Priorité** | **P2** |

### I4 — Machine à états unique du recrutement

| | |
|---|---|
| **Objectif** | Un statut, un nom |
| **Problème résolu** | R1, R2 |
| **Modules** | Recrutement |
| **Approche** | `pipeline_stage` devient la colonne unique ; migration `reviewed → accepted` ; table de transitions autorisées ; `status` conservé en colonne générée ou supprimé après purge des lectures |
| **Complexité** | Moyenne |
| **Risque** | Moyen |
| **Valeur** | Moyenne |
| **Priorité** | **P2** |

### I5 — Notifications unifiées

| | |
|---|---|
| **Objectif** | Un centre de notification unique, alimenté par les événements métier |
| **Problème résolu** | B15 |
| **Modules** | Transverse |
| **Approche** | Table `notifications` polymorphe (`tenant_id`, `user_id`, `kind`, `subject_type`, `subject_id`, `read_at`) ; les 3 silos existants deviennent des producteurs ; l'ActivityHub devient un consommateur unique ; les 60 `EmailEvents` deviennent des déclencheurs doubles (in-app + e-mail selon `user_notification_preferences`) |
| **Complexité** | Moyenne |
| **Risque** | Faible (additif) |
| **Valeur** | Élevée |
| **Priorité** | **P2** |

---

## 18. Ajouts mineurs

Nouvelle capacité limitée, intégration rapide.

| # | Ajout | Objectif | Modules | Dépend de | Complexité | Priorité |
|---|---|---|---|---|---|---|
| N1 | Colonne `required_qualification_id` sur `community_event_slots` + vérification dans `signUp()` | Prérequis d'inscription | Opérations, Formation | I1 | Faible | **P1** |
| N2 | Valeur `event` dans `document_links.entity_type` | Attacher un briefing à une opération | Documents, Opérations | — | Très faible | **P1** |
| N3 | `atak_units.user_id` nullable, alimentée à la liaison | Fiabiliser le pont ATAK↔effectif | ATAK, Effectifs | — | Faible | **P1** |
| N4 | Vue « Effectif disponible » pour l'organisateur (croise `deployable`, `personnel_absences`, RSVP) | Décision d'organisation | Opérations, Effectifs | — | Faible | **P2** |
| N5 | Tableau d'expiration des qualifications par unité | Anticipation des recyclages | Formation, Effectifs | I1 | Faible | **P2** |
| N6 | Score de fiabilité de présence sur la fiche personnelle | Valoriser l'historique RSVP | Effectifs, Opérations | — | Faible | **P2** |
| N7 | Inscription automatique au parcours d'accueil à l'acceptation | Combler B4 | Recrutement, Formation | — | Faible | **P1** |
| N8 | Pré-remplissage de `personnel_profiles` depuis `enlistments` | Combler B3, supprimer une double saisie | Recrutement, Effectifs | — | Faible | **P1** |
| N9 | Tableau d'absentéisme (motifs agrégés) | Exploiter `absence_reason` | Opérations | — | Faible | **P3** |
| N10 | Vue campagne (`campaign_tag`) | Regrouper les opérations liées | Opérations | — | Faible | **P3** |

---

## 19. Ajouts majeurs

| # | Ajout | Objectif | Problème résolu | Modules | Complexité | Risque | Priorité |
|---|---|---|---|---|---|---|---|
| M1 | **Référentiel des fonctions** : pour chaque fonction, les qualifications requises, les titulaires, les candidats éligibles | Répondre à « qui peut occuper quelle fonction ? » | § 6.3, § 10.4 | Effectifs, Formation, Opérations | Élevée | Moyen | **P1** |
| M2 | **Boucle opérationnelle complète** : ORBAT poussé vers ATAK → présence réelle collectée → réconciliation avec le RSVP → AAR → RETEX attaché à l'opération → statistiques individuelles | Fermer le cycle métier | B12, B17 | Opérations, ATAK, Effectifs | Très élevée | Élevé | **P3** |
| M3 | **Parcours d'intégration opposable** : séquence de N étapes avec suivi de complétion par membre, visible du staff | Combler le trou d'onboarding | § 4.2 | Recrutement, Formation, Documents | Moyenne | Faible | **P2** |
| M4 | **Journal d'activité transverse** : agrégation des 7 systèmes d'audit en une vue filtrable | Traçabilité et conformité | § 13.1 | Transverse | Moyenne | Faible | **P3** |
| M5 | **Recherche globale** : une barre unique sur documents, membres, opérations, formations, sujets de forum | Navigation dans un produit devenu vaste | UX | Transverse | Élevée | Faible | **P3** |
| M6 | **Planification de la montée en compétence** : à partir du référentiel de fonctions, proposer à chaque membre son prochain objectif de formation | Valoriser le LMS | Métier | Formation, Effectifs | Élevée | Moyen | **P4** |

---

## 20. Opportunités de mutualisation

| Fonction | État actuel | Recommandation | Priorité |
|---|---|---|---|
| **Notifications** | 3 silos + ActivityHub partiel | **Mutualiser** — table `notifications` polymorphe (I5) | **Élevée** |
| **Audit / journal** | 7 systèmes | **Mutualiser la lecture** ; garder les écritures spécialisées pour la performance | **Élevée** |
| **Validation CSRF** | 382 appels manuels + middleware partiel | **Mutualiser** — middleware couvrant tous les POST hors liste blanche explicite | **Élevée** |
| **Résolution de tenant** | réimplémentée par contrôleur | **Mutualiser** — un service `TenantContext` injecté | **Élevée** |
| **Permissions** | `Gate` + `PermissionImplication` + `TenantPermissionCatalog` | **Déjà mutualisé et bien fait** — ne pas toucher | — |
| **Pièces jointes** | `forum_attachments`, pièces du portail candidat, `training_resources`, `documents` | **Mutualiser** — service de stockage unique, métadonnées par module | Moyenne |
| **Commentaires** | `forum_posts`, `training_course_comments`, `recruitment_team_wall`, `briefing_slide_comments` | **Mutualiser** — table `comments` polymorphe | Moyenne |
| **Historique / timeline** | `enlistment_timeline`, `personnel_org_history`, `interteam_mission_events`, `ops_board_history`, `personnel_roleplay_timeline` | **Mutualiser** — un composant `timeline` alimenté par le journal unifié | Moyenne |
| **Filtres et tri** | réécrits par écran | **Mutualiser** — un partial de filtres paramétrable (la page Documents est le meilleur modèle) | Moyenne |
| **Exports** | PDF ×3 moteurs (TCPDF, `TrainingCertificatePdfEngine`, export AAR) | **Mutualiser** — un service d'export | Moyenne |
| **Dashboards / statistiques** | `TenantAnalyticsRepository`, `AdminDashboardMetricsService`, `OrganizationAnalyticsController`, `SystemAnalyticsController` | **Mutualiser** — une couche de métriques unique | Moyenne |
| **Recherche** | `PortalSearchController` existe, périmètre **[V]** | **Étendre** plutôt que dupliquer | Moyenne |
| **Tags** | `forum_tags`, `content_tags`, `tags_json` (événements), `public_tags` (unités) | **Mutualiser** — `content_tags` existe déjà, l'étendre | Faible |
| **Validation / workflow** | `document_workflows`, `ElevationApprovalService`, approbation d'inscription formation, décision de candidature | **Mutualiser** à terme — un moteur d'approbation générique | Faible |
| **Favoris** | `training_course_favorites` seulement | **Généraliser** si le besoin est avéré | Faible |
| **Rappels** | e-mail uniquement, 2 planificateurs | **Mutualiser** — un planificateur unique (fusionner `send-attendance-reminders.php` dans `CronRunner`) | Moyenne |

---

## 21. Plan d'évolution priorisé

### Plan 1 — Quick wins *(aucun changement structurel)*

1. **P1** — `/api/operations` dans `protected_prefixes` **[S1]**
2. **P2** — CSRF dans `DocumentsController`
3. **P4** — Slot « Plus » de la barre mobile
4. **P5** — `military_id` sur la fiche personnelle
5. **P6** — Explication du flux iCal + bouton copier
6. **P7/P8** — Définitions de statuts, états vides complets
7. **N2** — `event` dans `document_links.entity_type`
8. Fusionner `send-attendance-reminders.php` dans `CronRunner`

### Plan 2 — Consolidation *(dette, incohérences, branchements fragiles)*

1. **P3** — CSRF global étendu à `/admin/`
2. **P9** — Journalisation des `catch (\Throwable)` critiques
3. **I4** — Machine à états unique du recrutement
4. **N3** — `atak_units.user_id`
5. **N7/N8** — Recrutement → formation et recrutement → dossier
6. Découpage de `HomeController::dashboard()` et de `AtakApiController`
7. Premiers tests sur les workflows critiques (candidature, certificat, RSVP)
8. Unification des deux systèmes de migration

### Plan 3 — Enrichissement métier

1. **I1** — Chaîne de qualification **← le chantier à faire en premier de ce plan**
2. **N1** — Prérequis de qualification sur les slots
3. **N5** — Tableau d'expiration
4. **N4** — Vue « effectif disponible »
5. **N6** — Fiabilité de présence
6. **I5** — Notifications unifiées
7. **M3** — Parcours d'intégration opposable

### Plan 4 — Évolutions majeures

1. **I2** — Objet « Opération » canonique
2. **I3** — Unification des affectations
3. **M1** — Référentiel des fonctions
4. **M4** — Journal d'activité transverse
5. **M5** — Recherche globale

### Plan 5 — Vision cible

Voir § 22.

---

## 22. Vision cible

Aujourd'hui, Athena est **une collection d'outils excellents qui ne se parlent pas**. Un membre y
saisit son indicatif sept fois, obtient un certificat que personne ne peut interroger, s'inscrit à
une opération sans qu'aucun système ne sache s'il est qualifié, participe à un jeu qui produit un
AAR que le portail ne rattachera jamais à cette opération.

La cible n'est pas « plus de fonctionnalités ». C'est **la même richesse, branchée**.

### Le membre

Il a **une** identité (compte + dossier opérateur), **une** affectation datée, **une** liste de
qualifications avec leurs dates de validité. Son tableau de bord lui dit : ce qu'il doit finir de
remplir, quelle formation il doit suivre pour la fonction qu'il vise, quelle qualification expire,
à quelle opération il est attendu et sur quel poste, ce qu'il doit avoir lu avant.

### L'opération

Elle est **un seul objet**, de la création au RETEX. Publiée avec ses phases et ses postes. Chaque
poste porte ses prérequis : le système sait qui peut le tenir, et le propose. Les documents de
briefing y sont attachés. À l'ouverture, l'ORBAT part vers ATAK. Pendant, les positions et les
événements sont collectés. Après, la présence réelle est réconciliée avec les intentions, l'AAR est
généré et attaché, le RETEX s'écrit au même endroit. Les heures et les rôles tenus remontent au
dossier.

### La formation

Elle produit une **qualification**, pas seulement un PDF. Cette qualification ouvre des postes,
expire, déclenche un recyclage. Le référentiel de fonctions est public dans l'outil : chacun voit
ce qu'il faut pour tenir un poste, et l'encadrement voit qui manque.

### Le recrutement

Il ne s'arrête pas à l'acceptation. Il verse dans le dossier ce que le candidat a déjà saisi,
inscrit au parcours d'accueil, propose une affectation, et suit l'intégration jusqu'à la première
opération effectuée.

### Le staff

Il dispose d'**un** centre de notification, **un** journal d'activité, **une** recherche. Il ne
consulte plus sept écrans pour comprendre un dossier.

### Ce qui reste distinct

Cette convergence ne doit pas tout aplatir. ATAK reste une interface outil plein écran avec ses
propres contraintes de latence et de densité. Le forum reste un espace de discussion, pas un
workflow. Le studio LMS reste un outil d'auteur. **L'unification porte sur les données et les
identifiants, pas sur les interfaces.**

### La séquence

Un seul ordre est défendable :

> **fermer l'API ouverte** → **brancher les qualifications** → **unifier l'opération** →
> **unifier les affectations** → **publier le référentiel de fonctions** → **fermer la boucle ATAK**

Chaque étape rend la suivante moins risquée. Faire la boucle ATAK avant les qualifications
reviendrait à câbler un système sur des données qui n'existent pas.

---

## 23. Tableau synthétique

| Élément | État actuel | Problème | Proposition | Impact | Complexité | Priorité |
|---|---|---|---|---|---|---|
| API `/api/operations/*` | Ouverte | 14 routes sans auth, dont 2 écritures | Ajouter à `protected_prefixes` | Critique | Très faible | **P0** |
| CSRF global | `/back-office/` seulement | `DocumentsController` = 0 validation sur 3 POST | Validations manquantes puis middleware étendu | Critique | Faible | **P0** |
| `personnel_qualifications` | Jamais écrite | Panneau structurellement vide | Émission à la certification | Critique | Moyenne | **P0** |
| `certifications` / `user_certifications` | Jamais écrites, lues 1× | Score de readiness toujours faux | Supprimer, fusionner dans les qualifications | Élevé | Faible | **P1** |
| Statut de candidature | `status` + `pipeline_stage` | `reviewed` ≠ `accepted` selon la colonne | Colonne unique + transitions | Moyen | Moyenne | **P2** |
| Affectations | 3 tables + code de sync | Désynchronisation reconnue dans le code | `personnel_assignments` canonique | Élevé | Élevée | **P2** |
| Objet « opération » | 4 représentations | Briefing, RSVP et AAR disjoints | `community_events` en référentiel | Très élevé | Élevée | **P1** |
| `document_links.entity_type` | Enum sans `event` | Impossible d'attacher un briefing | Étendre l'enum | Élevé | Très faible | **P1** |
| Prérequis d'opération | Inexistants | On s'inscrit sans être qualifié | `required_qualification_id` sur le slot | Élevé | Faible | **P1** |
| Pont ATAK ↔ effectif | Chaîne `call_sign` | Rupture silencieuse | `atak_units.user_id` | Élevé | Faible | **P1** |
| Notifications | 3 silos, in-app partiel | Aucune notif pour formation/RSVP/recrutement | Table unique polymorphe | Élevé | Moyenne | **P2** |
| Recrutement → dossier | Aucun transfert | Double saisie complète | Pré-remplissage | Moyen | Faible | **P1** |
| Recrutement → formation | Aucun lien | Pas de parcours d'accueil | Inscription automatique | Moyen | Faible | **P1** |
| `AtakApiController` | 8 230 lignes | Non maintenable, non testé | Découpage par domaine | Moyen | Élevée | **P2** |
| `HomeController::dashboard()` | ~890 lignes, 36 `Container::get` | Non maintenable | Extraction en services | Moyen | Moyenne | **P2** |
| Migrations | 2 systèmes + 118 gardes runtime | Défiance envers le schéma | Système unique versionné | Moyen | Élevée | **P2** |
| Planificateurs | `CronRunner` + script autonome | Pas de supervision commune | Fusion | Faible | Très faible | **P1** |
| Tests | 22 fichiers / 317 k lignes | Aucune couverture métier | Tests sur les 5 workflows critiques | Élevé | Moyenne | **P1** |
| Slot « Plus » nav mobile | Inerte | 20 % de la nav mobile perdue | Menu ou suppression | Faible | Très faible | **P1** |
| `catch (\Throwable)` | 526 occurrences muettes | Incidents invisibles | Journaliser les chemins critiques | Moyen | Faible | **P1** |
| Terminologie | Synonymes non arbitrés | Ambiguïté UI/code/BDD | Lexique canonique (§ 10.3) | Moyen | Faible | **P2** |
| Dump SQL de production | Versionné, 782 Ko | Données personnelles possibles | Auditer et retirer | **[V]** | Très faible | **P1** |
| CSS | 60 fichiers, 1,5 Mo, 3 systèmes | Duplication, build divergent | Consolider sur `athena-ds` | Faible | Élevée | **P3** |

---

## 24. Backlog

### P0 — Correction critique

| Id | Titre | Effort | État |
|---|---|---|---|
| P0-1 | Protéger `/api/operations/*` (`config/tactical_api.php`) | 15 min | **Fait** |
| P0-2 | CSRF sur les 3 POST de `DocumentsController` | 30 min | **Fait** |
| P0-3 | Audit du dump SQL versionné (données personnelles) puis décision de retrait | 2 h | **Audit fait — retrait en attente d'arbitrage** |
| P0-4 | Émettre une qualification à l'émission d'un certificat | 2-3 j | **Fait** (socle) |

#### Détail de la livraison P0

**P0-1** — `/api/operations/` ajouté aux `protected_prefixes`. Les 14 routes passent sous
`ComspecApiKeyAuth` (clé plateforme, clé de communauté ou session membre), au même niveau que
`/api/replay/` et `/api/intel/`. Aucun consommateur interne n'a été trouvé dans le dépôt (ni JS,
ni vues, ni `mod/`) : le risque de régression est nul côté web.

**P0-2** — `DocumentsController::csrfGuard()` ajouté et appelé par `unlock()`, `signature()` et
`accessTrack()`, avec un 419 JSON explicite. Les trois `fetch` de `views/documents/show.php`
transmettent désormais `_csrf_token` : le lecteur de documents continue de fonctionner.

**P0-4** — Socle du chaînon Formation → Qualification :

- `bootstrap/personnel_qualifications_training_link_migration.php` (idempotent, enregistré dans
  `run-migrations.php`) : ajoute `tenant_id`, `training_course_id`, `training_certificate_id`,
  `source`, remplit `tenant_id` pour les lignes historiques, et pose un **index unique sur
  `training_certificate_id`** qui rend l'émission idempotente.
- `PersonnelQualificationRepository` : `upsertFromCertificate()`, `revokeForCertificate()`,
  `syncStatusesForTenant()`, `listExpiringForTenant()`, `userIdsQualifiedForCourse()`,
  `userHasValidQualificationForCourse()`. La table passe d'un usage strictement en lecture à un
  cycle de vie complet. `trainingLinkReady()` neutralise proprement l'émission sur un déploiement
  non migré, sans erreur.
- `TrainingCertificateService` : émission de la qualification à la création du certificat **et**
  rattrapage sur les certificats antérieurs (branche « certificat déjà valide »), révocation
  propagée. Synchronisation best-effort et journalisée : un échec ne peut pas invalider un
  certificat légitimement acquis.
- `TrainingExpireCronJob` : fait vivre les statuts dans le temps (`expiring` à 30 jours,
  `expired` à échéance) et désactive les qualifications adossées à un certificat non valide.

Reste à faire sur ce chantier, hors P0 : l'exposition UI (tableau de recyclage par unité — N5) et
la consommation par les prérequis d'opération (N1).

**P0-3 — résultat de l'audit du dump `u416380327_BDD_PROD.sql`** (782 Ko, racine du dépôt)

Le fichier ne contient pas que le schéma : **106 lignes de données de production réelles**.

| Table | Lignes | E-mails | Empreintes mot de passe | Jetons |
|---|---|---|---|---|
| `users` | 6 | 6 | **6 (argon2id)** | — |
| `user_profiles` | 3 | — | — | — |
| `personnel_profiles` | 3 | — | — | — |
| `enlistments` | 2 | 2 | — | — |
| `email_tokens` | 4 | — | — | 6 |
| `password_resets` | 1 | — | — | 1 |
| `login_attempts` | 28 | 28 | — | — |
| `user_login_devices` | 4 | — | — | 4 |
| `audit_logs` | 55 | 7 | — | — |

S'y ajoutent **3 adresses IPv4 publiques** (donc rattachables à des personnes).

Circonstances atténuantes, vérifiées :

- Le dépôt est **privé**, `forks_count = 0`. L'exposition se limite aux personnes ayant accès au
  dépôt (et aux environnements d'exécution qui le clonent, comme les sessions d'agent).
- Les empreintes sont en **argon2id**, pas en MD5/SHA1 : même divulguées, elles résistent.
- Les jetons (`email_tokens`, `password_resets`) datent d'avril 2026, soit environ 3,5 mois :
  **[P]** expirés selon les TTL du code, donc non rejouables.

Éléments aggravants :

- **Aucune règle `.gitignore` ne couvre les `.sql`** — la récidive est probable.
- Le fichier est présent dans **6 commits** : le retirer du répertoire de travail ne le retire pas
  de l'historique.
- Conserver des données de production dans un dépôt de code contrevient à la minimisation
  (RGPD art. 5.1.c), indépendamment du caractère privé du dépôt.

**Recommandation, en deux temps séparés :**

1. *Sans risque, réversible* — retirer le fichier du répertoire de travail (`git rm --cached`),
   ajouter `*.sql` (avec exception pour `migrations/*.sql`) au `.gitignore`, et conserver le dump
   hors dépôt. Corrige le présent et prévient la récidive.
2. *Irréversible, à arbitrer* — purge d'historique (`git filter-repo`) sur les 6 commits. Réécrit
   `main`, invalide les clones et les PR ouvertes. Au vu du dépôt privé sans fork, la valeur
   marginale est faible face au coût. **Non recommandé sauf obligation de conformité explicite.**

Dans les deux cas, la mesure réellement protectrice est ailleurs : **faire tourner les 6 mots de
passe concernés**, puisqu'ils ont circulé sous forme d'empreinte.

### P1 — Prioritaire

| Id | Titre | Effort |
|---|---|---|
| P1-1 | Étendre `CsrfPostMiddleware` à `/admin/` | 1 j (dont recette) |
| P1-2 | Valeur `event` dans `document_links.entity_type` + UI d'attachement | 1 j |
| P1-3 | `required_qualification_id` sur `community_event_slots` + contrôle à l'inscription | 2 j |
| P1-4 | `atak_units.user_id` alimentée à la liaison | 2 j |
| P1-5 | Pré-remplissage du dossier depuis la candidature acceptée | 1 j |
| P1-6 | Inscription automatique au parcours d'accueil à l'acceptation | 1 j |
| P1-7 | Tests des 5 workflows critiques (candidature, certificat, RSVP, RBAC, tenant) | 5 j |
| P1-8 | Journalisation des `catch (\Throwable)` des chemins métier | 2 j |
| P1-9 | Fusion de `send-attendance-reminders.php` dans `CronRunner` | 0,5 j |
| P1-10 | Correction du slot « Plus » de la barre mobile | 2 h |
| P1-11 | Suppression de `certifications` / `user_certifications` + correction du score de readiness | 1 j |
| P1-12 | Quick wins de contenu (P5–P8) | 1 j |

### P2 — Amélioration importante

| Id | Titre | Effort |
|---|---|---|
| P2-1 | Objet « Opération » canonique (`operation_uid`, FK depuis briefing et ATAK) | 10 j |
| P2-2 | Machine à états unique du recrutement | 3 j |
| P2-3 | Notifications unifiées | 6 j |
| P2-4 | Vue « effectif disponible » pour l'organisateur | 2 j |
| P2-5 | Tableau d'expiration des qualifications par unité | 2 j |
| P2-6 | Découpage de `AtakApiController` par domaine | 8 j |
| P2-7 | Découpage de `HomeController::dashboard()` | 3 j |
| P2-8 | Unification des affectations (`personnel_assignments` canonique) | 8 j |
| P2-9 | Application du lexique canonique (UI d'abord, puis code) | 4 j |
| P2-10 | Parcours d'intégration opposable | 6 j |
| P2-11 | Alignement UX de `personnel/file.php` et `admin/recruitments/show.php` | 5 j |

### P3 — Confort / évolution

| Id | Titre | Effort |
|---|---|---|
| P3-1 | Référentiel des fonctions publié | 10 j |
| P3-2 | Journal d'activité transverse | 5 j |
| P3-3 | Recherche globale | 8 j |
| P3-4 | Score de fiabilité de présence | 2 j |
| P3-5 | Vue campagne (`campaign_tag`) | 3 j |
| P3-6 | Tableau d'absentéisme | 2 j |
| P3-7 | Consolidation CSS sur `athena-ds` | 10 j |
| P3-8 | Système de migration unique | 8 j |
| P3-9 | Mutualisation des commentaires et pièces jointes | 6 j |

### P4 — Vision long terme

| Id | Titre |
|---|---|
| P4-1 | Boucle opérationnelle complète (ORBAT → ATAK → présence → AAR → dossier) |
| P4-2 | Planification individuelle de montée en compétence |
| P4-3 | Catalogue de formations inter-communautés (exploitation de `TrainingCourseExchangeService`) |
| P4-4 | Moteur d'approbation générique |

---

## Annexe A — Points à vérifier

Éléments que cet audit n'a pas pu trancher par lecture seule. Quatre ont depuis été résolus.

| # | Point | État |
|---|---|---|
| 1 | Niveau PHPStan | **Résolu [C]** — `level: 0`. Analyse statique quasi inerte (cf. § 14.3) |
| 2 | Rendu tablette (768–1024 px) | **[V]** — nécessite un rendu réel |
| 3 | `session_secure_cookie` en production | **Résolu [C]** — défaut `false`, et `.env.example` le livre à `false` avec `APP_ENV=production` (cf. S5) |
| 4 | Recouvrement `competencies/*` ↔ `knowledge_units/module_*` | **[V]** |
| 5 | Alimentation de `personnel_profiles.readiness_score` | **[V]** |
| 6 | Usage effectif de `document_relations` | **Résolu [C]** — **utilisée**, consommée par `AdminDocumentsController` via `DocumentRelationRepository`. Le constat « sous-exploité n° 11 » est donc à nuancer : la table vit, c'est son exposition produit qui est faible |
| 7 | Couverture de `portal_help_modal.php` | **[V]** |
| 8 | Exhaustivité de `enlistment_timeline` sur les transitions de statut | **[V]** |
| 9 | Contenu du dump `u416380327_BDD_PROD.sql` | **Résolu [C]** — cf. § 24, P0-3 |
| 10 | Exposition de `community_event_slots.unit_id` dans l'UI | **[V]** |

## Annexe B — Fichiers cités

| Constat | Fichier |
|---|---|
| CSRF global limité | `app/Middleware/CsrfPostMiddleware.php:19-21` |
| API operations ouverte | `routes/web.php:1517-1531` + `config/tactical_api.php` |
| Qualifications mortes | `app/Repositories/PersonnelQualificationRepository.php` |
| Certificat sans qualification | `app/Services/Training/TrainingCertificateService.php:44-100` |
| `user_certifications` lue une fois | `app/Repositories/UnitRepository.php:163-185` |
| Triple affectation | `app/Repositories/PersonnelAssignmentRepository.php:21-22, 97-124, 311-350` |
| Double machine à états | `app/Repositories/EnlistmentRepository.php:525, 539, 566-577` |
| Enum documents sans `event` | dump SQL, `CREATE TABLE document_links` |
| ATAK sans `user_id` | dump SQL, `CREATE TABLE atak_units` |
| Résolution de tenant ATAK | `app/Controllers/Api/AtakApiController.php:788-824` |
| Contournement session tactique | `app/Support/ComspecApiKeyAuth.php` |
| Routeur linéaire | `app/Core/Router.php:76-118` |
| Dashboard monolithique | `app/Controllers/Web/HomeController.php:98-984` |
| Slot « Plus » inerte | `views/partials/bottom_nav.php:18` |
| Références UX | `views/documents/index.php`, `views/community/events.php`, `views/messages/index.php` |
