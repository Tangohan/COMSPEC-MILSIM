# Plan Cursor — « The Unit Commander » (v2, avril 2026)

Document de feuille de route **aligné sur le code actuel** de COMSPEC. Les **leviers de croissance** (parrainage, codes communauté, avantages créateur) sont le fil conducteur produit ; la baseline technique et le backlog d’exécution suivent.

**Liens utiles** : [Audit tenant `tenant_id`](AUDIT-TENANT-FILTRAGE.md), [Vision communautés premium](VISION-COMMUNAUTES-PREMIUM.md).

---

## Proposition de valeur — trois piliers à mettre en avant

Ces trois leviers doivent être **visibles marketing** (site vitrine, onboarding, emails) et **traçables produit** (métriques, plans, audit).

### 1. Parrainage

- **Objectif** : un créateur ou un staff qui fait adopter COMSPEC à une autre unité est récompensé ; la plateforme acquiert des communautés qualifiées par le réseau milsim.
- **Principes** : lien ou code parrain unique par utilisateur ; attribution à la **création de communauté** ou au **premier abonnement payant** du filleul ; récompense configurable (crédit Stripe, mois offerts, extension de quota `max_members`, badge).
- **État code** : à concevoir (tables `referrals`, `referral_codes` ou équivalent ; pas encore dans le dépôt). À prioriser après invitations + Stripe stables.

### 2. Codes communauté

- **Objectif** : rejoindre une communauté sans lien d’invitation long — saisie d’un **code court** (ex. `FOG-2026`, `UNIT-ALPHA`) sur la page d’accueil ou à l’inscription, mappé au `tenant_id` / slug.
- **Usages** : affichage sur le terrain, réseaux sociaux, partenaires ; complémentaire aux **invitations email** déjà livrées (`community_invitations`).
- **État code** : champ `tenants.community_code` (unique, normalisé) + flux « J’ai un code » sur `/register` ou `/c/{slug}` ; règles anti-abus (rate limit, pas de brute-force).

### 3. Avantages créateur

- **Objectif** : le **fondateur** (owner du tenant) a un statut clair : visibilité dans l’admin, garde-fous sur la facturation, et **avantages plan** au lancement.
- **Exemples d’avantages** : période d’essai **Pro** (événements + analytics + ATAK) les N premiers mois ; quota membres supérieur en phase de lancement ; badge « Fondateur » sur le profil / page communauté ; accès prioritaire au support.
- **État code** : `owner_user_id` et `plan_slug` existent sur `tenants` ; à **expliciter en UX** (dashboard créateur, comparaison des paliers, CTA upgrade contextualisé dans [`views/platform/upgrade.php`](../views/platform/upgrade.php)) et éventuellement en **règles métier** (coupon Stripe, metadata `founder_promo`).

---

## 1. Baseline déjà livrée (ne pas re-planifier à zéro)

| Domaine | Emplacement principal |
|--------|------------------------|
| Résolution tenant API ATAK (pas de fallback silencieux sur tenant 1) | `app/Controllers/Api/AtakApiController.php`, `docs/AUDIT-TENANT-FILTRAGE.md` |
| Headers sécurité + rate limiting (login, register, enlistment, etc.) | `app/Middleware/SecurityHeadersMiddleware.php`, `RateLimitMiddleware.php`, `app/Core/Application.php` |
| Conventions audit (`AuditAction`) + logs auth / tenant / modération forum | `app/Services/Audit/AuditAction.php`, `AuthController`, `ForumModerationApiController` |
| Onboarding post-création (fuseau, `tenants.settings`) | Routes `/c/{slug}/setup`, `CommunityController`, `views/community/setup.php` |
| Inscription publique | `/register`, `RegisterController` |
| Invitations (token, expiration, admin, acceptation) | `bootstrap/platform_unit_commander_migration.php`, `InvitationAdminController`, `InvitationAcceptController` |
| Sanctions (warn/mute/suspend/ban) + enforcement après auth | `ModerationService`, `AuthMiddleware`, `/admin/organization/moderation` |
| Feature gating + quotas membres | `FeatureGateService` sur ATAK, formations web, événements, analytics, invitations / création utilisateur |
| Événements + RSVP + analytics de base | `CommunityEventsController`, `CommunityEventsAdminController`, `OrganizationAnalyticsController`, `PlatformUsageRepository` |

---

## 2. Backlog priorisé (écarts vs. vision produit)

### P0 — Cohérence API / web

- [ ] Aligner **`TrainingApiController`** (et autres API authentifiées sensibles) sur **`FeatureGateService`** (ex. `training`) comme le web, pour éviter un contournement par JSON.
- [ ] Vérifier **sanctions** sur les mêmes API si l’utilisateur est authentifié en session (même logique que `AuthMiddleware`).

### P0.5 — Leviers parrainage / codes / créateur (produit)

- [ ] **Codes communauté** : schéma + UX « rejoindre avec un code » + lien avec invitations existantes.
- [ ] **Avantages créateur** : règles documentées + reflet UI (dashboard, upgrade, badge owner si retenu).
- [ ] **Parrainage** : modèle de données + flux attribution + page « inviter une autre unité » (peut s’appuyer sur `audit_logs` / `platform_usage_events` pour les KPIs).

### P1 — Sécurité compte & anti-abus

- [ ] **Sessions actives / kill session** : aujourd’hui sessions **PHP natives** (`Session`) ; décider si la table `sessions` du schéma doit servir l’auth ou uniquement un futur mécanisme, puis écran « sécurité du compte » si besoin métier.
- [ ] Brancher **`security_events`** et **`blocked_indicators`** (connexions suspectes, IP hash, etc.) avec cadre RGPD.
- [ ] Étendre le **throttling** aux actions sensibles forum (création sujet, endpoints modération) si besoin ; **codes communauté** : anti brute-force sur la validation du code.

### P2 — Modération « centre de cas »

- [ ] UI **preuves** (`moderation_evidence`) et **timeline** par dossier (`moderation_cases`) — données déjà migrées ; enrichir l’admin.
- [ ] Notifications (email / in-app) à l’application d’une sanction — optionnel.

### P3 — Monétisation & exploitation

- [ ] Lier **`views/platform/upgrade.php`** et parcours admin aux **prix Stripe** (`subscription_plans.stripe_price_id_*`) quand configurés ; **mettre en avant** les avantages créateur dans le copy (essai Pro, paliers).
- [ ] **Export audit** CSV/JSON depuis l’admin audit système (`/admin/system/audit`).

### P4 — Qualité

- [ ] **Tests d’isolement deux tenants** (PHPUnit ou script CLI), comme recommandé dans `AUDIT-TENANT-FILTRAGE.md`.

---

## 3. Séquence d’itération suggérée

1. **Codes communauté + avantages créateur (UX + règles simples)** — quick wins visibles.
2. Gates + sanctions sur **API training** (et autres API critiques).
3. **`security_events`** + **`blocked_indicators`** + règles de données.
4. **Parrainage** (données + attribution + récompense).
5. Modération : **preuves + timeline** dossiers.
6. **Stripe / upgrade UX** (avantages créateur dans le message) + exports audit.
7. **Tests** isolement tenant.

---

## 4. DoD par thème (rappel)

| Thème | DoD |
|-------|-----|
| API | Aucun module premium accessible via API si le plan interdit, même avec session valide. |
| Anti-abus | Événements de sécurité traçables ; blocages IP hash documentés. |
| Modération | Sanction visible dans l’audit ; dossier enrichissable (preuves). |
| Monétisation | CTA upgrade mène à un flux de paiement ou à une procédure claire ; **avantages créateur** mentionnés sur le parcours fondateur. |
| Parrainage / codes | Attribution traçable ; code communauté unique et vérifiable sans fuite inter-tenant. |

---

*Dernière mise à jour : plan v2 — piliers parrainage, codes communauté, avantages créateur ; backlog §2.*
