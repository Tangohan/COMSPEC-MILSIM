# Revue complète — modules, API, pages d'accès, routes et navigation (2026-04-19)

## Méthode d'audit

Audit réalisé via vérifications automatiques sur :

- le registre des routes (`routes/web.php`),
- la navigation portail (`config/navigation.php`),
- la cohérence routes ↔ navigation (`scripts/verify_routes_access_navigation.php`),
- la couverture par module (`scripts/audit_modules_routes_navigation.php`).

## Résultats

### 1) Cohérence routes / accès / navigation

- **806 routes** chargées.
- **111 liens navigation** audités.
- Aucune route dupliquée.
- Tous les liens de navigation pointent vers une route GET existante après correction.

### 2) Couverture par module (web + API + navigation)

Modules audités :

- Authentification & compte
- Communautés multi-tenant
- Personnel & ORBAT
- Documents
- Formations
- Forum
- Événements & pointage
- Messagerie interne
- Courrier officiel
- Équipement / Modpacks / ATAK
- Administration système / organisation
- Interopérations inter-équipes

Résultat global : **OK** pour chaque module (présence d'entrées de surface web, API quand applicable, et entrée navigation).

## Anomalie détectée et corrigée

### Lien de navigation forum prioritaire invalide

- **Avant** : `back-office/forum/priorite-mission/nouveau` (aucune route GET correspondante).
- **Après** : `forum/new-topic?mission_priority_level=high` (route existante, compatible avec le contrôleur de création de sujet).

## Recommandations

1. Conserver `scripts/verify_routes_access_navigation.php` dans la CI pour empêcher les régressions de navigation.
2. Étendre progressivement `scripts/audit_modules_routes_navigation.php` avec des assertions de permissions/middlewares par module si besoin d'un audit de sécurité plus strict.
