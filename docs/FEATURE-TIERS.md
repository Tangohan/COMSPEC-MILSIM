# Classification des fonctionnalités (Gratuit / Premium / Gratuit limité)

Ce document aligne la méthode produit (score, tiers, limites) sur l’implémentation COMSPEC : plans `subscription_plans`, `FeatureGateService`, quotas (`limits_json` + `tenant_usage_counters`).

## Décision : mapping des paliers commerciaux

| Catégorie produit | Implémentation |
|-------------------|----------------|
| **Gratuit** | Plan `free` — fonctionnalités booléennes dans `features_json` + **limites** optionnelles dans `limits_json` (gratuit limité sans nouveau slug Stripe). |
| **Gratuit limité** | Même plan `free` : quotas / rétention décrits par entrée dans `limits_json.quotas` ; l’accès partiel est accordé tant qu’il reste du quota (voir `FeatureGateService::allows`). |
| **Premium** | Plans payants `standard`, `pro` et `pro_plus` (facturation PayPal, Stripe en secours) : fonctionnalités activées dans `features_json` ; `limits_json` vide ou sans quota pour la feature (accès illimité côté quota). |

**Pourquoi un seul slug pour le gratuit limité** : `effectivePlanSlug()` et le webhook Stripe restent simples ; les plafonds sont **configurables en base** sans nouveau produit Stripe.

## Inventaire des `feature_key` (code)

Source unique : `App\Services\Billing\SubscriptionPlanFeaturesCatalog` (libellés FR/EN, description, type, unité, groupe,
bornes, défauts par palier). `tests/Unit/PlanFeaturesCatalogCoverageTest.php` relève dans `app/` et `views/` toutes les clés
lues par le code (contrôles de `FeatureGateService`, `PlanFeatureDenial`, lectures directes de `features_json`) et échoue si
l’une manque au catalogue. Les quotas valent `0` pour « illimité » ; l’administration affiche une case « Illimité ».

Administration : `/admin/system/subscription-plans` (tableau comparatif, fiche par formule à onglets, aperçu de la page
publique, communautés qui dépasseraient un plafond abaissé, historique du journal d’audit, copie d’une formule).
La page publique `/platform/upgrade` génère son comparatif depuis les valeurs réelles (`PlanOfferSummary`).

Défauts : gratuit / standard / pro / pro+. « Lue » = non : clé enregistrée mais qu’aucun écran ne contrôle (sans effet).

| feature_key | Groupe | Libellé | Type | Défauts | Lue |
|-------------|--------|---------|------|---------|-----|
| `max_members` | Membres et organisation | Nombre max. de membres | quota (membres, 0 = illimité) | 10 / 25 / 50 / 80 | oui |
| `max_orbat_units` | Membres et organisation | Unités dans l’ORBAT | quota (unités, 0 = illimité) | 10 / 20 / 60 / illimité | oui |
| `personnel` | Membres et organisation | Effectifs et ORBAT | booléen | oui / oui / oui / oui | **non** |
| `messages` | Membres et organisation | Messagerie interne | booléen | oui / oui / oui / oui | oui |
| `events` | Membres et organisation | Événements et calendrier | booléen | — / oui / oui / oui | oui |
| `operational_board` | Membres et organisation | Mur opérationnel | booléen | — / oui / oui / oui | oui |
| `alerts` | Membres et organisation | Alertes communauté | booléen | — / oui / oui / oui | oui |
| `analytics` | Membres et organisation | Analytics organisation | booléen | — / — / oui / oui | oui |
| `pdf_exports` | Membres et organisation | Exports PDF des tableaux | booléen | — / oui / oui / oui | oui |
| `year_review_collective` | Membres et organisation | Rétrospective annuelle de l’unité | booléen | — / oui / oui / oui | oui |
| `community_create` | Membres et organisation | Création de communauté | booléen | oui / oui / oui / oui | **non** |
| `training` | Academy et qualifications | Formations | booléen | oui / oui / oui / oui | oui |
| `max_training_courses` | Academy et qualifications | Nombre max. de parcours | quota (parcours, 0 = illimité) | 5 / 25 / 100 / illimité | oui |
| `pilot_qualifications` | Academy et qualifications | Cursus pilote automatique | booléen | — / oui / oui / oui | oui |
| `qualification_catalog_bulk_import` | Academy et qualifications | Import d’un pays entier du catalogue de référence | booléen | — / — / oui / oui | oui |
| `max_catalog_qualification_imports` | Academy et qualifications | Qualifications importées du catalogue de référence | quota (qualifications, 0 = illimité) | 10 / 50 / illimité / illimité | oui |
| `max_real_issuer_imports` | Academy et qualifications | Émetteurs réels importés | quota (émetteurs, 0 = illimité) | 5 / 25 / illimité / illimité | oui |
| `max_custom_qualifications` | Academy et qualifications | Qualifications et brevets créés par la communauté | quota (créations, 0 = illimité) | 5 / 30 / illimité / illimité | oui |
| `recruitment` | Recrutement | Recrutement et enrôlement | booléen | oui / oui / oui / oui | **non** |
| `invite_link_custom_slug` | Recrutement | Liens d’invitation à adresse personnalisée | booléen | — / oui / oui / oui | oui |
| `max_active_invite_links` | Recrutement | Liens d’invitation actifs | quota (liens actifs, 0 = illimité) | 2 / illimité / illimité / illimité | oui |
| `max_recruitment_offers` | Recrutement | Offres de recrutement en cours | quota (offres, 0 = illimité) | 3 / 10 / illimité / illimité | oui |
| `atak` | ATAK et jeu | Carte tactique ATAK | booléen | — / oui / oui / oui | oui |
| `atak_link_basic` | ATAK et jeu | Liaison jeu de base | booléen | oui / oui / oui / oui | oui |
| `equipment_game_sync` | ATAK et jeu | Synchronisation de l’équipement avec le jeu | booléen | — / — / oui / oui | oui |
| `equipment_kits` | ATAK et jeu | Kits de départ et assistant de tenue | booléen | — / — / oui / oui | oui |
| `documents` | Documents et doctrine | Documents | booléen | oui / oui / oui / oui | **non** |
| `courrier` | Documents et doctrine | Courrier officiel | booléen | — / oui / oui / oui | oui |
| `max_mini_articles` | Documents et doctrine | Mini-articles de la communauté | quota (mini-articles, 0 = illimité) | 5 / 30 / illimité / illimité | oui |
| `max_email_templates` | Documents et doctrine | Modèles de message personnalisés | quota (modèles, 0 = illimité) | 3 / 10 / illimité / illimité | oui |
| `equipment` | Documents et doctrine | Équipement | booléen | oui / oui / oui / oui | oui |
| `registry_featured` | Marque, visibilité et intégrations | Mise en avant dans le registre | booléen | — / — / oui / oui | oui |
| `cooperation` | Marque, visibilité et intégrations | Coopération inter-unités | booléen | — / — / oui / oui | oui |
| `advanced_integrations` | Marque, visibilité et intégrations | Intégrations avancées | booléen | — / — / — / oui | oui |

Page Équipement (`/equipment`) : consulter les tenues, créer et modifier les siennes, les favoris et l’export ACE restent ouverts à toutes les
formules (clé `equipment`). Les **kits de départ** de la communauté (création, proposition à une unité ou à un rôle, copie par les membres)
et l’**assistant de création de tenue** demandent `equipment_kits` (Pro et plus par défaut, contrôlé par
`FeatureGateService::allowsWithCatalogDefault`) : une communauté sans accès voit la présentation de l’offre, jamais une erreur. La synchro
des tenues avec le jeu reste gouvernée par `equipment_game_sync`.

Plafonds `max_recruitment_offers`, `max_mini_articles`, `max_email_templates` (`PlanCreationQuota`) : ils ne bloquent que la **création**
d'un nouvel élément (jamais la lecture, la modification ni la suppression). Les valeurs ci-dessus ne servent qu'à la création initiale
des formules : une formule déjà enregistrée sans la clé reste **illimitée** (`FeatureGateService::intWhenDefined`, aucun UPDATE en
migration, `SubscriptionPlanFeaturesCatalog::OWNER_DECIDES`) jusqu'à ce que le propriétaire saisisse la valeur dans l'administration.

Clé historique `forum` : ni au catalogue ni lue par le code (orpheline si encore présente en base ; conservée à l’enregistrement).

## Facturation

- **PayPal** (prioritaire si `PAYPAL_CLIENT_ID` + `PAYPAL_CLIENT_SECRET`) : Billing Subscriptions + webhook `/api/paypal/webhook`.
- **Stripe** (secours) : Checkout + webhook `/api/stripe/webhook`.
- Variable `BILLING_PROVIDER=paypal|stripe|auto` (défaut `auto`).
- Identifiants de plans PayPal sur `subscription_plans.paypal_plan_id_monthly|yearly`.
- Upgrade communauté existante : `POST /platform/upgrade/checkout`.

## Matrice d’audit modules (synthèse)

| Domaine | Surface / action | Contrôle actuel (tenant) | Clé / limite |
|---------|------------------|--------------------------|--------------|
| Forum, documents, messagerie | Divers | Booléens plan | `forum`, `documents`, `messages` |
| Formations LMS | Accès apprenant / admin | `training` | `training` |
| Formations — nouveau parcours | Studio / import | `canCreateTenantCatalogTrainingCourse` | `max_training_courses` |
| Courrier | Dashboard courrier | `courrier` | `courrier` |
| Mur opérationnel | Tableau opérationnel | `operational_board` | `operational_board` |
| Coopération | Catalogue / annonces | `cooperation` | `cooperation` |
| Alertes | Admin alertes tenant | `alerts` | `alerts` |
| ATAK | Contrôleurs ATAK | `atak` | `atak` |
| Analytics | Org analytics | `analytics` | `analytics` |
| Événements | Web + admin | `allows` / quota | `events` |
| Intégrations | Back-office | `advanced_integrations` | `advanced_integrations` |
| Membres | Invitations, enrôlement | `canAddMember` | `max_members` |
| Liens d’invitation | Recrutement › Liens d’invitation | `InviteLinkService::canActivateAnother` / `customSlugAllowed` | `max_active_invite_links`, `invite_link_custom_slug` |

## Changelog pricing (interne)

| Date (approx.) | Changement |
|----------------|------------|
| 2026-04 | Ajout plan `pro_plus` ; clés `max_training_courses`, `advanced_integrations`. |
| 2026-07 | Catalogue élargi (courrier, coopération, mur, alertes, messagerie, équipement) ; paiements PayPal ; upgrade existant. |

## Tableau de classification (template produit)

À dupliquer pour chaque feature métier ; les scores sont indicatifs (méthode 5–25).

| Feature | Description | Score | Tier proposé | Limite free (si applicable) | Déblocage premium | Justification | KPI de suivi |
|---------|-------------|------:|--------------|----------------------------|-------------------|---------------|--------------|
| Événements communauté | Création et RSVP | 16 | Gratuit limité | 3 créations / mois (configurable) | Illimité + `events` dans `features_json` (Standard/Pro) | Levier conversion ; coût modéré | `quota_events` usage, conversion |
| ATAK | Carte / unités | 20 | Premium | — | `atak` = true (Standard+) | Différenciation forte | Activation ATAK |
| Analytics org | Métriques | 22 | Premium | — | `analytics` = true (Pro) | Valeur état-major | Visites dashboard analytics |
| Membres actifs | Taille d’unité | — | Gratuit limité | `max_members` dans plan | Plan supérieur | Coût déjà modélisé | `canAddMember` refus |

## Schéma JSON `limits_json` (par plan)

Stocké en colonne `subscription_plans.limits_json`. Exemple pour le plan `free` :

```json
{
  "quotas": {
    "events": {
      "limit": 3,
      "reset_period": "monthly",
      "soft_block_threshold": 0.8,
      "upgrade_cta": "platform/upgrade",
      "binds_feature": "events"
    }
  }
}
```

| Champ | Description |
|-------|-------------|
| `quotas` | Objet : clé = `feature_key` alignée sur le gating. |
| `limit` | Plafond sur la période (entier ≥ 1). |
| `reset_period` | `monthly` (extensible : `daily`, `weekly`). |
| `soft_block_threshold` | Entre 0 et 1 : en dessous, enregistrement analytics `soft_block_80pct` côté usage. |
| `upgrade_cta` | Route ou chemin relatif pour le lien « passer au premium » (ex. `platform/upgrade`). |
| `binds_feature` | Redondance explicite avec la feature contrôlée (doit correspondre à la clé parente). |

Variables globales optionnelles (env) : à terme `FREE_MAX_*` peuvent surcharger les défauts seed ; pour l’instant la source de vérité est la base.

## Persistance des quotas

- Table **`tenant_usage_counters`** : `tenant_id`, `metric_key` (= `feature_key` pour les quotas simples), `period_start` (date début de période), `amount` (consommation cumulée).
- Analytics : `platform_usage_events` avec `feature_key` dédiés pour le suivi produit : `quota_soft_block`, `quota_limit_reached` (voir implémentation).

## Cohérence API / web

Toute action soumise à quota doit appeler la même logique côté **web** et **API** (après création d’événement : `FeatureGateService::recordQuotaUse`).

## Implémentation technique (référence code)

| Méthode | Rôle |
|---------|------|
| `FeatureGateService::allows($tenantId, $feature)` | Accès « consommable » : plan inclut la feature **ou** quota gratuit restant. |
| `FeatureGateService::allowsLimitedFeatureModule($tenantId, $feature)` | Accès au module (liste, RSVP) : plan complet **ou** présence d’un quota configuré en `limits_json`, y compris après épuisement du quota mensuel. |
| `FeatureGateService::quotaStatusForFeature($tenantId, $feature)` | `mode` = `unlimited` \| `limited` + compteurs pour l’UI. |
| `FeatureGateService::recordQuotaUse` | À appeler après une action réussie (ex. création d’événement). |
| `FeatureGateService::recordQuotaLimitReached` | Tentative bloquée (analytics `feature_key` = `quota_limit_reached`). |
| `FeatureGateService::maybeRecordQuotaSoftBlock` | Seuil atteint (analytics `feature_key` = `quota_soft_block`, dédup session). |
| `FeatureGateService::canCreateTenantCatalogTrainingCourse` | Création de parcours autorisée si `training` + plafond `max_training_courses` non atteint (`0` = illimité). |
| `FeatureGateService::trainingCourseCapacityForTenant` | Chiffres utilisés par le Studio (bandeau / bouton désactivé). |

Table `tenant_usage_counters` ; colonne `subscription_plans.limits_json`.

## Revue

Ajuster `limits_json` (sans redéploiement si édition SQL/admin futur) ; documenter les changements dans un changelog pricing interne.

## Améliorations implémentées (évolution)

| Sujet | Détail |
|-------|--------|
| **Réconciliation** | Pour la feature `events`, le « used » est le max(compteur, nombre réel de lignes `community_events` sur la période), puis le compteur est remonté si la base dépasse le compteur (historique avant quotas). |
| **Fuseau horaire** | `TenantUsageCounterRepository::appTimezone()` : variable d’environnement `APP_TIMEZONE` (ex. `Europe/Paris`), défaut UTC — aligne les périodes mensuelles/hebdo/jour avec le calendrier produit. |
| **`metric_key`** | Dans `limits_json.quotas.<feature>`, clé optionnelle `metric_key` pour dissocier la clé métier (`events`) de la clé de stockage compteur. |
| **`soft_block_message`** | Texte configurable affiché dans le bandeau quand le seuil est atteint (voir partial). |
| **Partial** | `views/partials/quota_limited_banner.php` — bandeau réutilisable (variantes `light` / `dark`). |
| **Analytics upgrade** | `GET /platform/upgrade?from=quota_<feature>` enregistre `platform_usage_events` avec `feature_key = upgrade_view`, `action = from`. |
| **Surcharge `.env`** | `FREE_EVENTS_PER_MONTH` : remplace la limite « événements » du plan `free` (sans modifier la BDD). Utile pour tests ou ajustement rapide. |
| **Réconciliation multi-période** | Comptage SQL des créations aligné sur `monthly`, `weekly` et `daily` (bornes `created_at`). |
| **Index perf** | `community_events.ce_tenant_created` `(tenant_id, created_at)` pour les agrégations quota. |
| **Page upgrade** | Texte complémentaire automatique si `?from=quota_events` (ou autre clé `quota_*`). |
| **Plan Pro+** | Slug `pro_plus`, `sort_order` 40, seed dans `bootstrap/community_platform_migration.php`. |
| **Parcours** | Comptage `TrainingCourseRepository::countTenantCatalogCourses` (hors `lms_scope = platform`). |

## Pistes d’évolution (non implémentées)

- **Rétention** : `free_limit_type: retention` + filtre SQL sur `created_at` pour l’historique (documents, audit).
- **API** : appliquer les mêmes contrôles sur tous les endpoints JSON (création d’événements, etc.).
- **Autres surcharges env** : par ex. `FREE_MAX_MEMBERS` aligné sur `max_members` pour tests A/B.
- **Essai contextualisé** : webhook ou bannière à l’atteinte du soft-block avec coupon Stripe / trial 7 jours.

## Fins de formule (essai fondateur, formule offerte)

- `App\Support\PlanAccessWindow` : ce qui donne la formule actuelle (`gift`, `paid`, `trial`, `base`), fin prochaine, dernière fin passée, période de grâce.
- Réglages `platform_settings` (Administration > Formules offertes, section « Fins de formule ») :
  `plan_trial_days` (7 : durée de l'essai fondateur des nouvelles communautés, constante `PlanAccessWindow::DEFAULT_TRIAL_DAYS`), `plan_end_warning_days` (3), `plan_end_warning_email` (0), `plan_grace_enabled` (0), `plan_grace_days` (7).
- L'essai était de 30 jours ; il est de 7 jours pour les nouvelles communautés seulement : `tenants.settings.founder_trial_ends_at` déjà posé n'est jamais recalculé. Aucune migration.
- Bandeau `views/partials/plan_end_banner.php` pour le propriétaire, les fondateurs et les administrateurs de la communauté.
- E-mail `plan_end_warning` (tâche `plan_end_warning`, toutes les 6 h), une fois par fin (`tenants.settings.plan_end_warning_sent`).
- Grâce : `FeatureGateService::allows` / `allowsLimitedFeatureModule` laissent ouverts, pendant N jours après la fin, les modules de la formule terminée (dont `atak`, donc la liaison avec le jeu). Désactivée par défaut.

## Liaison jeu de base (décision TTRD 2026-10-14, variante B)

- Clé `atak_link_basic` (vraie sur toutes les formules, Gratuit compris). Clé absente d'une formule existante = défaut du catalogue (`FeatureGateService::allowsWithCatalogDefault`), donc aucune migration à lancer.
- `AtakApiController::requireTenant` : sans `atak`, seuls les chemins `AtakPlanAccess::BASIC_PATHS` passent (connexion du lien, positions, comptes rendus, briefing). Le reste (photos, vidéo, drone, commandes web, IFF, marqueurs, CAS…) répond 403 `feature_unavailable` avec un message qui renvoie à Standard.
- La page « Première liaison » s'ouvre avec la liaison de base ; la carte web et le téléphone web restent réservés à `atak`.
