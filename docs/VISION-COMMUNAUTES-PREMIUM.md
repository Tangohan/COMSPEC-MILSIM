# Vision — communautés auto-créées et offres premium

Ce document pose un cadre produit et technique **cible** pour évoluer d’Athena en déploiement « unité unique » vers une plateforme où des **communautés** (joueurs / staffs) peuvent adopter le service avec des **niveaux d’offre** différenciés. Il ne remplace pas un cahier des charges juridique ou commercial ; il aligne l’équipe technique et produit sur des objectifs communs.

**Liens** : inventaire actuel [INVENTAIRE-FONCTIONNALITES.md](INVENTAIRE-FONCTIONNALITES.md), référence fonctionnelle [GUIDE-FONCTIONNEL-REFERENCE-COMMUNAUTE.md](GUIDE-FONCTIONNEL-REFERENCE-COMMUNAUTE.md).

---

## 1. Objectif métier

- Permettre à un responsable ou à une équipe de **créer une communauté** (espace dédié) sans intervention manuelle du fournisseur à chaque fois.
- **Isoler les données** entre communautés (membres, documents, configuration, historiques).
- Monétiser via des **abonnements** (mensuel / annuel) et éventuellement des options à la carte.
- Utiliser les modules du guide de référence (calendrier, campagnes, distinctions, analytics, etc.) comme **leviers de valeur** inclus ou réservés aux offres supérieures.

---

## 2. Modèle « communauté » et données

### 2.1 Alignement avec l’existant

La table `tenants` et le champ `tenant_id` sur les utilisateurs et entités métier constituent déjà une base de **multi-tenant logique**. La vision consiste à :

- **Exposer** la création de tenant (formulaire, validation, slug unique).
- **Associer** un propriétaire (`owner`) et des limites de plan.
- **Durcir** les requêtes applicatives pour garantir l’absence de fuite inter-tenants (déjà attendu, à auditer systématiquement lors de l’ouverture publique).

### 2.2 Alternative (plus tard)

Si une communauté devait être un **sous-ensemble** d’un grand tenant (ex. fédération), on pourrait introduire une entité `organizations` sous `tenant_id`. Ce n’est pas nécessaire pour une première itération « une communauté = un tenant ».

---

## 3. Offres premium (exemple de paliers)

Les noms et prix sont indicatifs ; l’implémentation pourra les charger depuis la base ou la configuration.

| Niveau | Public cible | Exemples de limites / fonctions |
|--------|----------------|----------------------------------|
| **Gratuit** | Petite équipe, essai | Nombre de comptes plafonné, stockage documents limité, pas d’ATAK ou carte basique, pas d’exports massifs. |
| **Standard** | Unité active | Limites relevées, ORBAT complet, forum, LMS, documents, courrier de base. |
| **Pro** | Staff structuré | Intégrations (Discord, webhooks), analytics avancés, campagnes / événements, distinctions médias, support prioritaire. |
| **Enterprise** | Structures multiples | SLA, marque blanche partielle, tenant dédié ou infra optionnelle. |

Les fonctionnalités listées dans [GUIDE-FONCTIONNEL-REFERENCE-COMMUNAUTE.md](GUIDE-FONCTIONNEL-REFERENCE-COMMUNAUTE.md) peuvent être **cartographiées** vers ces paliers (ex. « analytics présence » réservé Pro).

---

## 4. Pistes techniques (implémentation future)

### 4.1 Données

- Colonne `plan` (enum ou FK vers `plans`) sur `tenants`, ou table `tenant_subscriptions` avec dates `current_period_start/end`, statut (`active`, `past_due`, `canceled`).
- Tables optionnelles : `plan_features` (fonctionnalité → plan minimum), `tenant_usage` (compteurs pour quotas).

### 4.2 Paiement

- **Stripe** (Billing, Checkout, Customer Portal) est un choix courant : webhooks `customer.subscription.updated`, `invoice.paid`, etc.
- Stocker l’identifiant client Stripe sur le tenant ; ne jamais faire confiance au client web pour le statut d’abonnement — **source de vérité** = webhooks + état en base.

### 4.3 Application

- Middleware ou service **`FeatureGate`** : avant une route ou une action, vérifier `tenant.plan` et éventuellement les compteurs (stockage, nombre d’utilisateurs actifs).
- Messages utilisateur cohérents : « Disponible à partir de l’offre Pro » + lien vers upgrade.
- **Admin système** : vue des tenants, plans, suspension pour impayé.

### 4.4 Sécurité et conformité

- Consentement et mentions légales (RGPD) pour la création de communauté et la facturation.
- Journalisation des accès admin multi-tenant.

---

## 5. Phasage recommandé

1. **Documentation et audit** : inventaire des requêtes sans `tenant_id` (régression sécurité).
2. **MVP création de communauté** : inscription propriétaire + tenant + plan gratuit, sans paiement.
3. **Stripe + paliers** : Checkout, webhooks, portail client.
4. **Fonctionnalités du guide** : livrées progressivement, chacune derrière un drapeau de plan ou un feature flag.

---

## 6. Relation avec la roadmap produit

La [GUIDE-FONCTIONNEL-REFERENCE-COMMUNAUTE.md](GUIDE-FONCTIONNEL-REFERENCE-COMMUNAUTE.md) identifie des écarts (*Absent* / *Partiel*). Chaque écart peut devenir une **epic** ; les paliers premium décident **quand** l’ouvrir au public et **à quel prix**.

---

*Document de cadrage — à affiner avec les objectifs business et les contraintes légales avant développement.*
