# Classification des fonctionnalités (Gratuit / Premium / Gratuit limité)

Ce document aligne la méthode produit (score, tiers, limites) sur l’implémentation COMSPEC : plans `subscription_plans`, `FeatureGateService`, quotas (`limits_json` + `tenant_usage_counters`).

## Décision : mapping des paliers commerciaux

| Catégorie produit | Implémentation |
|-------------------|----------------|
| **Gratuit** | Plan `free` — fonctionnalités booléennes dans `features_json` + **limites** optionnelles dans `limits_json` (gratuit limité sans nouveau slug Stripe). |
| **Gratuit limité** | Même plan `free` : quotas / rétention décrits par entrée dans `limits_json.quotas` ; l’accès partiel est accordé tant qu’il reste du quota (voir `FeatureGateService::allows`). |
| **Premium** | Plans payants `standard` et `pro` (facturation Stripe) : fonctionnalités activées dans `features_json` ; `limits_json` vide ou sans quota pour la feature (accès illimité côté quota). |

**Pourquoi un seul slug pour le gratuit limité** : `effectivePlanSlug()` et le webhook Stripe restent simples ; les plafonds sont **configurables en base** sans nouveau produit Stripe.

## Inventaire des `feature_key` (code)

Les clés suivantes sont utilisées dans `features_json` et/ou dans le gating applicatif :

| feature_key | Surfaces principales | Notes |
|-------------|----------------------|-------|
| `forum` | Forum communauté | Booléen plan ; pas de quota dans le code v1. |
| `documents` | Documents / forum pièces jointes | Booléen ; `past_due` conserve l’accès. |
| `training` | `TrainingController`, `TrainingApiController` | Booléen. |
| `atak` | `AtakController` | Booléen (ex. Standard+). |
| `analytics` | `OrganizationAnalyticsController` | Booléen (Pro). |
| `events` | `CommunityEventsController`, `CommunityEventsAdminController` | Booléen Pro **ou** quota gratuit (`limits_json.quotas.events`) — **premier flux quota implémenté**. |
| `community_create` | Bootstrap / création communauté | Booléen. |
| `max_members` | `FeatureGateService::maxMembers` / invitations | Quota sièges (nombre, pas période). |

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

## Pistes d’évolution (non implémentées)

- **Rétention** : `free_limit_type: retention` + filtre SQL sur `created_at` pour l’historique (documents, audit).
- **API** : appliquer les mêmes contrôles sur tous les endpoints JSON (création d’événements, etc.).
- **Autres surcharges env** : par ex. `FREE_MAX_MEMBERS` aligné sur `max_members` pour tests A/B.
- **Essai contextualisé** : webhook ou bannière à l’atteinte du soft-block avec coupon Stripe / trial 7 jours.
