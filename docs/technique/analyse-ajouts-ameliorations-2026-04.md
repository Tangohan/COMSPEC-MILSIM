# Analyse du projet et propositions d’ajouts/améliorations (avril 2026)

## 1) Périmètre analysé

Cette analyse s’appuie sur :
- la documentation racine et technique existante ;
- la structure du dépôt (backend PHP, vues, service Node archivé) ;
- un relevé rapide de volumétrie (contrôleurs, services, repositories, tests).

## 2) Constat global

Le projet est déjà **fonctionnellement riche** (RH, LMS, documents, courrier, forum, ATAK, multi-tenant), avec une architecture MVC claire et une séparation contrôleurs/services/repositories bien installée.

### Forces observées

1. **Couverture fonctionnelle élevée** pour un monolithe PHP multi-tenant.
2. **Documentation présente** (utilisateur + technique + plans d’amélioration ciblés).
3. **Outillage qualité de base** déjà en place (`phpunit`, `phpstan`, scripts composer).
4. **Organisation modulaire du code** avec dossiers dédiés (controllers/services/repositories/middleware).

### Risques / points de vigilance

1. **Risque de complexité croissante** (beaucoup de contrôleurs/services) si les conventions ne sont pas standardisées davantage.
2. **Écart probable entre richesse fonctionnelle et couverture de tests** (volume de tests limité au regard du volume PHP).
3. **Dette UX/front potentielle** liée à la multiplication des vues PHP et des partials.
4. **Observabilité à industrialiser** (KPIs techniques + produit + sécurité en continu).

## 3) Propositions d’amélioration priorisées

## Priorité P0 (0–4 semaines) — “sécuriser la base”

### P0.1 — Gouvernance qualité et conventions transverses

- Ajouter un document de référence unique :
  - conventions de nommage contrôleur/service/repository,
  - structure recommandée d’une feature,
  - règles de revue (checklist PR),
  - critères de “Definition of Done”.
- Objectif : limiter l’hétérogénéité et accélérer l’onboarding.

### P0.2 — Pipeline CI minimal obligatoire

- Mettre en place une CI (GitHub Actions ou équivalent) avec, à minima :
  - `composer validate`,
  - `composer install --no-interaction --prefer-dist`,
  - `composer test`,
  - `composer phpstan`.
- Bloquer la fusion PR si la CI échoue.

### P0.3 — Baseline de test orientée risques

- Créer un plan de test prioritaire sur 5 domaines critiques :
  - Auth/permissions,
  - Multi-tenant isolation,
  - Courrier (workflow + signatures),
  - Formations (progression/quiz),
  - Endpoints API tactiques.
- Commencer par des tests “happy path + contrôle d’accès” à forte valeur.

### P0.4 — Durcissement sécurité opérationnelle

- Vérifier et formaliser :
  - politiques de sessions et cookies,
  - rotation et stockage des secrets,
  - standardisation des headers sécurité,
  - stratégie anti-abus (rate-limit, audit trail).
- Ajouter une checklist de sécurité de release.

## Priorité P1 (1–2 trimestres) — “accélérer le delivery”

### P1.1 — Catalogue API interne (contrat)

- Documenter les endpoints (OpenAPI léger) pour les zones API critiques.
- Définir des conventions d’erreurs JSON homogènes.
- Ajouter des tests de contrat pour éviter les régressions côté front/intégrations.

### P1.2 — Rationalisation front-end

- Inventorier les partials réutilisables et harmoniser composants UI.
- Créer un mini “design system” pragmatique (tokens, boutons, formulaires, états).
- Réduire la duplication des structures de pages et des scripts inline.

### P1.3 — Observabilité produit + technique

- Définir des indicateurs essentiels :
  - erreurs 5xx/4xx,
  - latence p95 sur routes clés,
  - conversion enrôlement,
  - taux de complétion formations,
  - usage courrier/signature.
- Mettre en place un tableau de bord de pilotage mensuel.

### P1.4 — Fiabilisation migrations et déploiements

- Standardiser un workflow de migration idempotent (pré-prod → prod).
- Ajouter des scripts de vérification post-déploiement (smoke tests).
- Documenter rollback minimal par module critique.

## Priorité P2 (2–4 trimestres) — “scalabilité et différenciation”

### P2.1 — Architecture modulaire par domaine (sans big bang)

- Introduire progressivement des “bounded contexts” (ex. LMS, Courrier, Forum, Personnel).
- Isoler interfaces de services/domaines avant toute extraction microservice.
- Conserver le monolithe modulaire tant que le coût d’extraction > bénéfice.

### P2.2 — Moteur de permissions unifié

- Formaliser un modèle RBAC/ABAC unique (site, organisation, métier).
- Centraliser les policies et journaliser les décisions critiques.

### P2.3 — Expérience admin orientée productivité

- Ajouter actions bulk, templates avancés, raccourcis, filtres sauvegardés.
- Introduire workflows assistés (wizards) sur tâches lourdes (recrutement, RH, contenus).

### P2.4 — IA assistive (si alignée stratégie)

- Cas d’usage à fort ROI :
  - assistance rédaction courrier,
  - synthèse dossier opérateur,
  - recommandations de parcours formation,
  - aide modération forum.
- Encadrer avec garde-fous : traçabilité, opt-in, confidentialité, revue humaine.

## 4) Ajouts concrets recommandés au dépôt

1. `docs/technique/quality-gates.md` (DoD, checklist PR, standards de revue).
2. `.github/workflows/ci.yml` (test + phpstan + validation composer).
3. `docs/technique/test-strategy.md` (pyramide de test + plan priorisé par risque).
4. `docs/technique/api-contracts.md` (conventions JSON + versioning + erreurs).
5. `docs/technique/observability-kpis.md` (KPI techniques et business).
6. `docs/technique/release-security-checklist.md` (release hardening).

## 5) Plan d’exécution synthétique (90 jours)

### Mois 1
- Installer quality gates + CI minimale.
- Lancer baseline de tests sur parcours critiques.
- Produire checklist sécurité de release.

### Mois 2
- Démarrer catalogue API + conventions d’erreurs.
- Lancer rationalisation UI sur 2 modules pilotes (ex. Courrier, Formations).
- Mettre en place un premier dashboard KPI mensuel.

### Mois 3
- Étendre les tests automatisés sur domaines secondaires.
- Industrialiser migration/deploy avec smoke tests.
- Préparer feuille de route modulaire par domaine (P2.1).

## 6) Critères de succès mesurables

- CI obligatoire sur 100% des PR.
- Réduction du taux de régression fonctionnelle release après release.
- Couverture tests en hausse sur les parcours critiques.
- Diminution du temps moyen de livraison d’une évolution.
- Hausse de la satisfaction des administrateurs organisation.

## 7) Conclusion

La fondation est solide : le produit est large, documenté et déjà structuré. Le meilleur levier à court terme est de **renforcer la qualité opérationnelle** (CI, tests à risque, sécurité release, observabilité), puis de **rationaliser l’UX et les contrats API** pour soutenir une croissance durable sans explosion de complexité.
