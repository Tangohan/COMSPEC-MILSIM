# Blueprint — Système compétences/modules MILSIM (multi-tenant)

Ce document formalise la mise en place du système demandé (frameworks, compétences, modules ALPHA/BRAVO/CHARLIE/DELTA, redondance intelligente, validation instructeur) et fournit un **prompt enrichi** prêt à l’emploi pour accélérer les prochaines itérations.

## 1) Architecture cible

### 1.1 Domaine fonctionnel

Le domaine est séparé en 5 sous-systèmes :

1. **Référentiel doctrinal**
   - `competency_frameworks`, `competency_levels`, `competency_domains`, `competencies`, `knowledge_units`
2. **Catalogue pédagogique**
   - `modules`, `module_knowledge`, `module_sequences`, `module_dependencies`, `recurrence_rules`
3. **Activation tenant & gouvernance locale**
   - `tenant_modules`
4. **Évaluation & validation opérationnelle**
   - `evaluations`, `user_progress`
5. **Projection RH / certifications / rôles**
   - `module_competencies`, `role_requirements`, `certification_modules`

### 1.2 Principes clés implémentés

- **Hiérarchie stricte** : Framework → Palier → Domaine → Compétence → Sous-compétence (auto-référence) → Knowledge Unit.
- **Chaînage intelligent** : prérequis/renforcement/recyclage par `module_dependencies`.
- **Récurrence native** : gestion d’expiration et recyclage (`recurrence_rules`, `expires_at` dans `user_progress`).
- **Double validation** : score automatique + visa humain (`requires_validator`, `validated_by`, `validated_at`).
- **Multi-tenant réel** : activation/obligation/séquence personnalisées (`tenant_modules`).
- **Traçabilité opérationnelle** : progression historisée, statut explicite, score, validateur.

## 2) Schéma de données livré

Le schéma SQL a été ajouté dans :

- `migrations/20260408000001_competency_progression_framework.sql`

Ce fichier crée les tables suivantes :

- `competency_frameworks`
- `competency_levels`
- `competency_domains`
- `competencies`
- `knowledge_units`
- `modules`
- `module_knowledge`
- `module_sequences`
- `module_dependencies`
- `recurrence_rules`
- `tenant_modules`
- `module_competencies`
- `evaluations`
- `user_progress`
- `role_requirements`
- `certification_modules`

## 3) Flux métier recommandé

1. Créer un framework (ex: RFL) puis ses paliers/domaines/compétences/KU.
2. Créer les modules ALPHA → BRAVO → CHARLIE → DELTA.
3. Définir les dépendances et la récurrence (ex: critique tous les 180 jours).
4. Activer les modules par tenant et ajuster obligations/fréquences.
5. Lancer évaluations (quiz/scénario/terrain) avec validation humaine si requis.
6. Mettre à jour `user_progress` puis calculer readiness et gaps de qualification.
7. Appliquer les exigences de rôle et certifications via pivots dédiés.

## 4) Prompt enrichi (version opérationnelle)

Tu es architecte logiciel senior PHP/MySQL orienté MILSIM.  
Objectif: implémenter un système de progression doctrinale multi-tenant, réaliste et contraignant.

### Contraintes métier non négociables
- Hiérarchie stricte: Framework > Palier > Domaine > Compétence > Sous-compétence > Knowledge Unit.
- Modules typés: ALPHA (théorie), BRAVO (pratique), CHARLIE (simulation), DELTA (validation terrain).
- Dépendances: PREREQUIS, RENFORCEMENT, RECYCLAGE.
- Récurrence: NONE, PERIODIC, EVENT_BASED avec expiration et recyclage automatique.
- Validation: automatique (score) + humaine (instructeur/cadre) selon le module.
- Multi-tenant: activation, obligation, séquence et fréquence modulables par tenant.
- Progression réaliste: perte de qualification si non entretien + verrouillage des spécialisations.

### Livrables attendus
1. SQL MySQL idempotent (CREATE TABLE IF NOT EXISTS + index + FK) pour:
   competency_frameworks, competency_levels, competency_domains, competencies, knowledge_units,
   modules, module_knowledge, module_sequences, module_dependencies, recurrence_rules,
   tenant_modules, module_competencies, evaluations, user_progress, role_requirements,
   certifications (ou extension existante), certification_modules.
2. Seed de démonstration (RFL + Communication tactique + chaîne ALPHA→DELTA).
3. Services PHP:
   - ProgressionService (déblocage modules + expiration)
   - EvaluationService (score + validation humaine)
   - ReadinessService (pourcentage prêt opérationnel par utilisateur/unité)
4. Endpoints REST JSON:
   - GET /api/frameworks/{id}/map
   - GET /api/users/{id}/progress
   - POST /api/modules/{id}/evaluate
   - POST /api/progress/recalculate-expiry
   - GET /api/command/readiness
5. Vues:
   - Commandement: heatmap compétences, expirations, readiness %
   - Utilisateur: parcours, prérequis, échéances
   - Instructeur: validations en attente, scoring, observations terrain
6. Tests:
   - Cas déblocage chaîne ALPHA→BRAVO→CHARLIE→DELTA
   - Cas expiration à J+180 sur modules critiques
   - Cas refus d’accès si prérequis non validé
   - Cas double validation obligatoire

### Règles d’implémentation
- Respect strict RBAC existant.
- Toujours filtrer par tenant.
- Ajouter index pour les requêtes de dashboard (status/expires_at/tenant_id).
- Fournir migration rollback quand possible.
- Produire un README technique d’exploitation (jobs de recyclage, KPI readiness, maintenance).

### Format de réponse attendu
- 1) DDL SQL complet
- 2) Exemples de seed
- 3) Pseudocode services métier
- 4) Contrats API JSON (request/response)
- 5) Plan de tests
- 6) Checklist sécurité et performance

## 5) Prochaine étape conseillée

Après exécution de la migration SQL, implémenter un cron quotidien qui bascule automatiquement `user_progress.status` à `EXPIRED` quand `expires_at < NOW()` et déclenche la création des modules de recyclage requis.


## 6) Journalisation renforcée (tenant + formateur)

Migration complémentaire livrée :

- `migrations/20260408000002_competency_progression_logs.sql`

Tables ajoutées :

- `tenant_training_logs` : journal d’administration tenant (activation module, changement de récurrence, exigences de rôle/certification, etc.).
- `trainer_validation_logs` : décisions formateur/instructeur (validation, rejet, override score, observation terrain).
- `user_progress_event_logs` : événements de cycle de vie progression (expiration automatique, réassignation recyclage, changement statut).

Événements minimum à tracer côté applicatif :

1. Activation/désactivation module tenant.
2. Changement `recurrence_rules` ou override tenant.
3. Validation DELTA par instructeur avec commentaire.
4. Passage automatique `COMPLETED -> EXPIRED` (job planifié).
5. Assignation automatique d’un module de recyclage.

## 7) Intégration applicative recommandée (prochaine itération)

- Ajouter un `TrainingAuditService` qui écrit simultanément dans `tenant_training_logs` et `trainer_validation_logs` selon le contexte.
- Brancher les écritures dans les actions API admin/instructeur (évaluation, validation, override).
- Ajouter une tâche planifiée quotidienne qui :
  - détecte les progressions expirées,
  - met à jour `user_progress.status`,
  - écrit un événement `AUTO_EXPIRED` dans `user_progress_event_logs`.
- Exposer une vue commandement filtrée par tenant sur les 3 journaux (timeline + filtres module/instructeur/utilisateur).
