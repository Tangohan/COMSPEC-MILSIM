# Plan Cursor complet — Positionnement « The Unit Commander »

## 1) Analyse rapide du projet COMSPEC (état actuel)

COMSPEC a déjà une base très solide pour un produit type « The Unit Commander » :

- Architecture **multi-tenant** (`tenants`, `tenant_id` partout), avec notes d’audit déjà en place.
- Modules existants différenciants : **ORBAT**, **forum**, **LMS**, **documents**, **courrier**, **ATAK/Overwatch**.
- Administration séparée **système** / **organisation**.
- Gestion des comptes déjà présente (auth, mot de passe oublié, préférences, avatar).

Limites majeures à traiter pour une offre « self-service communauté » :

1. Création de communauté publique non finalisée (onboarding + provisioning).
2. Fonctionnalités « communauté moderne » encore partielles/absentes (events/campagnes/analytics/alertes).
3. Modération sécurité à renforcer (bannissement global + anti-abus + audit centralisé).
4. Packaging « plan premium » encore à industrialiser (feature gating + billing + quotas).

---

## 2) Vision produit cible (inspirée The Unit Commander)

### Proposition de valeur

- « Crée ta communauté milsim en 10 minutes ».  
- Gérer membres, unités, grades, documents, entraînements, opérations, discipline.
- Offres Freemium → Pro avec montée en puissance.

### ICP (client idéal)

- Unités milsim de 20–250 membres.
- Staff RP/OP structuré (recrutement, formations, doctrine).
- Besoin de gouvernance + sécurité (modération, bannissement, traçabilité).

---

## 3) Plan de delivery global (12 semaines)

## Phase 0 — Foundations (Semaine 1)

- Audit final `tenant_id` sur routes/repositories critiques.
- Baseline sécurité (headers, CSP, rate limiting auth).
- Journal d’audit standardisé pour actions admin/modération.

**DoD** : aucun endpoint sensible sans contrôle tenant + RBAC + log.

## Phase 1 — Communautés self-service (Semaines 2-4)

- Onboarding « Créer ma communauté » (tenant + owner + slug + setup initial).
- Assistant setup (branding, fuseau, règles, modules activés).
- Domaine communauté (`/c/{slug}`) stabilisé + switch de contexte.

**DoD** : une communauté peut être créée et utilisée sans intervention manuelle.

## Phase 2 — Comptes & organisation (Semaines 4-6)

- Profils enrichis (bio, unité principale, rôles secondaires, visibilité).
- Gestion invitations + adhésion + validation staff.
- Paramètres confidentialité + sécurité compte (2FA roadmap-ready).

**DoD** : cycle membre complet (invitation → activation → affectation ORBAT).

## Phase 3 — Bannissement & sécurité (Semaines 6-8)

- Système de sanctions : avertissement, mute, suspension, ban temporaire/permanent.
- Blocage multi-niveaux : compte, IP, device fingerprint (si légalement acceptable).
- Centre modération : preuve, notes, timeline, appels/recours.

**DoD** : bannir un membre coupe accès forum/API/modules en <1 min, tracé dans audit.

## Phase 4 — Features premium « Unit Commander » (Semaines 8-12)

- Events + campagnes + RSVP + présence.
- Analytics communauté (activité, rétention, participation events).
- Feature gating par plan + quotas + écran d’upgrade.

**DoD** : au moins 3 fonctionnalités premium monétisables activées.

---

## 4) Backlog Cursor par domaines (copier-coller utilisable)

## A. Mise en place communauté

### Epic A1 — Community Onboarding
**User stories**
- En tant que fondateur, je peux créer une communauté avec nom, slug, région.
- En tant que propriétaire, je reçois un espace prêt avec rôle Owner.

**Tâches Cursor**
- Créer `CommunityOnboardingController` + routes publiques.
- Créer service `TenantProvisioningService` (tenant, owner user, réglages initiaux).
- Ajouter validations slug unique + normalisation.
- Ajouter écran de succès + redirection dashboard communauté.

### Epic A2 — Context & Switching
**User stories**
- En tant qu’utilisateur multi-communautés, je peux changer de communauté active.

**Tâches Cursor**
- Service `CommunityContextService` (résolution slug/session).
- Middleware `EnsureTenantContext` durci.
- UI switcher communautés dans dashboard.

## B. Features des comptes

### Epic B1 — Lifecycle Membre
**User stories**
- Invitation membre, acceptation, profil initial, affectation unité.

**Tâches Cursor**
- Table invitations (`community_invitations`) + expiration token.
- Flux acceptation invitation avec création/liaison utilisateur.
- Écran admin membres : pending/active/suspended.

### Epic B2 — Sécurité compte
**User stories**
- Historique connexions, sessions actives, déconnexion forcée.

**Tâches Cursor**
- Table `user_sessions` / `security_events`.
- Écran « sécurité du compte » (sessions + kill session).
- Alertes e-mail login suspect (nouvelle IP/pays).

## C. Bannissement & modération

### Epic C1 — Sanctions Engine
**User stories**
- Un modérateur peut appliquer une sanction avec motif, durée, preuve.

**Tâches Cursor**
- Table `moderation_actions` (type, reason, actor_id, target_id, expires_at).
- Policy gates (`moderation.manage`, `moderation.review`).
- Hook blocage accès sur chaque middleware auth/API.

### Epic C2 — Ban Evasion Protection
**User stories**
- Empêcher la recréation immédiate de comptes abusifs.

**Tâches Cursor**
- Rate limit renforcé (`/login`, `/enlistment`, `/forum/new-topic`).
- Liste blocage IP hashée + seuils de réputation.
- Captcha adaptatif sur comportements suspects.

## D. Sécurité site globale

### Epic D1 — AppSec baseline
- Headers sécurité (CSP, X-Frame-Options, HSTS, Referrer-Policy).
- CSRF sur formulaires sensibles.
- Validation stricte upload médias (MIME réel + taille + antivirus optionnel).

### Epic D2 — Audit & Incident Response
- Journal audit central (auth, admin, sanctions, permissions).
- Export incidents CSV/JSON.
- Runbook incident (compte compromis, fuite inter-tenant, abus forum).

---

## 5) Plan technique détaillé « Bannissement & sécurité »

## Modèle de données recommandé

- `moderation_cases` : dossier (statut, priorité, tenant_id, opened_by).
- `moderation_actions` : action unitaire (warn/mute/suspend/ban).
- `moderation_evidence` : preuves (URL capture, fichier, hash, auteur).
- `security_events` : événements de sécurité (login fail/success, password reset, session revoked).
- `blocked_indicators` : IP hash, email hash, device hash, scope, expiration.

## Ordre d’implémentation

1. Schéma SQL + migrations + repository.
2. Service `ModerationService` transactionnel.
3. Middleware enforcement (`DeniedIfSanctionedMiddleware`).
4. UI admin modération + timeline cas.
5. Notification (mail + in-app).
6. Tests d’intégration tenant A/B.

---

## 6) Séquencement Cursor (sprints de prompts prêts à l’emploi)

## Sprint Prompt 1 — Onboarding communauté
> Analyse les routes/controllers existants et implémente un onboarding public de communauté : formulaire création tenant + owner, validation slug unique, transaction atomique, rôle owner attribué, redirection dashboard. Ajoute tests d’intégration tenant et documentation technique.

## Sprint Prompt 2 — Comptes & invitations
> Implémente un système d’invitation membre par e-mail/token expirant, activation compte et rattachement à une communauté, puis une page admin de suivi des invitations (pending/accepted/expired/revoked). Respecte le multi-tenant strict.

## Sprint Prompt 3 — Bannissement
> Ajoute un moteur de sanctions (warn, mute, suspension, ban temporaire, ban permanent) avec tables SQL, policies RBAC, enforcement middleware web+API, logs audit complets, et interface admin pour appliquer/lever sanctions.

## Sprint Prompt 4 — Sécurité plateforme
> Renforce la sécurité applicative : rate limiting adaptatif, événements sécurité, écran sessions actives, déconnexion de session distante, headers HTTP sécurité, validation upload stricte. Ajoute tests et checklist d’exploitation.

## Sprint Prompt 5 — Premium & monétisation
> Implémente FeatureGate par plan (free/standard/pro), quotas tenant, écran upgrade contextualisé, endpoints admin plans/subscriptions, et instrumentation analytics d’usage par feature.

## Sprint Prompt 6 — Refonte forum stratifié (global / tenant / mission)
> Analyse le module forum existant (schéma SQL + repositories + controllers) et corrige l’anomalie “forum global filtré tenant”. Implémente une architecture explicite par scope sans casser l’isolation multi-tenant:
> 1) **Scopes stricts**
>    - Introduire `scope` explicite pour catégories/topics/messages: `global`, `tenant`, `mission`.
>    - Règles de cohérence: `scope=global => tenant_id NULL`; `scope=tenant|mission => tenant_id obligatoire`.
>    - Interdiction des conventions implicites (ex: `tenant_id=1` pour global).
> 2) **Filtrage lecture**
>    - Requêtes de lecture: visibles si `scope='global'` OU `tenant_id=:current_tenant` (et pour mission selon droits mission).
>    - Aucun bypass de filtre tenant sans clause de scope explicite.
> 3) **Accès & RBAC**
>    - `global`: visible aux utilisateurs authentifiés.
>    - `tenant`: isolation stricte.
>    - `mission`: visibilité hiérarchique via RBAC/ABAC existant (commandement vs exécutants).
> 4) **Capacités opérationnelles**
>    - Messages typés (`INFO`, `ORDRE`, `INTEL`, `ALERTE`) avec rendu visuel + filtres + priorisation.
>    - Threads opérationnels liés à mission/dossier, avec archivage auto et verrouillage de fin.
>    - Accusés de réception (`ACK`) pour messages sensibles: qui a lu + timestamp.
>    - Journal de preuve: édition tracée, suppression loggée, export PDF.
>    - Signalement interne: flag, workflow de modération rapide, escalade auto.
>    - Liaison documentaire versionnée et références croisées.
>    - Enrichissement ATAK (position, screenshot CTAB, marker) si payload disponible.
>    - Mode canal persistant (`#commandement`, `#intel`, `#logistique`) hybride forum/chat.
>    - Résumé automatique des longs threads (points clés + décisions).
> 5) **Migration & compatibilité**
>    - Migration SQL idempotente + backfill des données existantes (`general/platform/organization` vers nouveaux scopes).
>    - Mise à jour repositories/services/controllers/API/UI + tests d’intégration multi-tenant.
>    - Ajouter une checklist anti-régression: non-fuite inter-tenant, cohérence scopes, RBAC, perf index.

---

## 7) KPI de succès (go-to-market)

- Activation : % communautés qui créent >10 membres en 14 jours.
- Engagement : % communautés avec 1 event/semaine + forum actif.
- Sécurité : temps moyen de traitement d’un cas modération.
- Rétention : taux de rétention à 30/90 jours par plan.
- Monétisation : conversion free → paid + churn mensuel.

---

## 8) Risques & mitigations

- **Fuite inter-tenant** → tests automatiques A/B + audit SQL obligatoire en PR.
- **Abus création compte** → captcha adaptatif + throttling + email verification.
- **Charge modération** → templates de décision + escalade staff + archive preuve.
- **Complexité produit** → feature flags et release progressive par cohorte.

---

## 9) Priorité immédiate recommandée (prochaine semaine)

1. Finaliser onboarding communauté (MVP utilisable).
2. Mettre en prod le moteur de sanctions minimum (suspend/ban + logs).
3. Durcir sécurité compte (sessions actives + login alerts).
4. Démarrer analytics de base (DAU/WAU, invitations, sanctions).

Ce lot suffit pour vendre une première version « The Unit Commander-like » crédible, puis enrichir avec campagnes/events/analytics avancés.
