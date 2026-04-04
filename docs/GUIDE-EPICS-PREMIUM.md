# Découpage en epics — guide fonctionnel et priorisation premium

Ce document découpe le [guide fonctionnel de référence](GUIDE-FONCTIONNEL-REFERENCE-COMMUNAUTE.md) en **epics livrables** et propose un **rattachement indicatif** aux paliers décrits dans [VISION-COMMUNAUTES-PREMIUM.md](VISION-COMMUNAUTES-PREMIUM.md). Les noms de plans (`free`, `standard`, `pro`) correspondent aux entrées `subscription_plans` et aux drapeaux dans `features_json` (voir `FeatureGateService`).

## Principes

- Une epic = migrations + permissions + UI + tests de non-régression multi-tenant.
- Chaque epic peut être **derrière un feature flag** ou une clé dans `features_json` du plan du tenant.
- L’ordre ci-dessous privilégie la **valeur utilisateur** et la **réutilisation** du schéma existant (personnel, ORBAT, forum, documents).

## Table des epics

| ID | Epic | Réf. guide (zones typiques) | Complexité | Palier cible (indicatif) |
|----|------|----------------------------|------------|---------------------------|
| E1 | Rangs avec groupes, rendu PNG, affichage profil | Grades / insignes | Moyenne–élevée | Standard+ |
| E2 | Distinctions (awards), groupes, médias profil | Distinctions | Moyenne | Standard+ |
| E3 | Qualifications groupées + médias | Qualifications | Moyenne | Standard |
| E4 | Unités « profil » décoratives vs ORBAT réel | Unités / présentation | Moyenne (cadrage produit) | Standard |
| E5 | Postes illustrés | Postes | Moyenne | Standard |
| E6 | ORBAT structures avancées (hors doublon ORBAT actuel) | ORBAT | Variable | Standard–Pro |
| E7 | Roster dédié (vue agrégée) | Effectifs / roster | Moyenne | Pro |
| E8 | Événements, présence, calendrier | Calendrier / ops | Élevée | Pro (`events` dans features) |
| E9 | Campagnes | Campagnes | Élevée | Pro |
| E10 | Analytics communauté (présence, engagement) | Analytics | Élevée | Pro (`analytics`) |
| E11 | Documents avancés + intégration cloud (ex. Google Drive) | Documents | Élevée | Pro / Enterprise |
| E12 | Alertes multi-pages | Notifications | Moyenne | Standard–Pro |
| E13 | Candidatures — constructeur + automations | Recrutement | Très élevée | Pro |
| E14 | Discord (bots, webhooks, secrets) | Intégrations | Élevée | Pro |

## Ordre de priorisation recommandé (backlog produit)

1. **E3, E5** — enrichissent le personnel sans toucher au cœur multi-tenant.
2. **E1, E2** — forte visibilité communauté ; dépend du référentiel grades / médias.
3. **E8** — gros morceau ; débloque présence et valeur « staff ».
4. **E10** — après E8 pour avoir des données à analyser.
5. **E13** — une fois le socle RH / personnel stabilisé.
6. **E11, E14** — intégrations externes et conformité secrets.

## Mapping technique (rappel)

- **FeatureGate** : `App\Services\Platform\FeatureGateService` — étendre les clés dans `subscription_plans.features_json` au fil des epics.
- **Facturation** : webhooks Stripe → `tenants.plan_slug`, `subscription_status` (voir `StripeWebhookController`).

---

*Document aligné sur le plan plateforme communautés premium ; à mettre à jour lorsque le guide de référence évolue.*
