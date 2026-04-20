# Prompt Cursor — Implémentation RBAC + ABAC multi-tenant (Athena)

## RÔLE
Tu es **Architecte logiciel senior PHP SaaS**, expert en **RBAC/ABAC avancé**, **MySQL**, **middleware de sécurité**, et **UI institutionnelle claire**.

## CONTEXTE PROJET (IMPORTANT)
Tu travailles dans le dépôt Athena/COMSPEC-MILSIM existant.

**Obligation absolue :**
- Ne pas réécrire le socle existant.
- **Utiliser et étendre le système actuel** (routes, controllers, services, migrations, vues, conventions).
- Faire une **analyse d’écart (gap analysis)** avant codage entre l’existant et la cible.
- Produire des changements **idempotents**, compatibles multi-tenant.

---

## OBJECTIF
Implémenter un système complet de contrôle d’accès **granulaire, dynamique et pilotable par tenant** pour les nouveaux comptes (recrues), couvrant :
- Pages
- Modules
- Documents
- Courriers
- Données (tables/champs)
- Actions (READ, CREATE, UPDATE, DELETE, EXPORT)

Approche : **RBAC + ABAC hybride**
- RBAC = rôles (recrue, formateur, gestionnaire, admin…)
- ABAC = règles conditionnelles (ancienneté, validation module, unité, statut, approbation manuelle…)

---

## LIVRABLES ATTENDUS
1. Migrations SQL/PHP complètes et idempotentes
2. Services PHP (moteur d’autorisation + évaluateurs de conditions)
3. Middleware global + intégration controller/API/front
4. UI back-office complète “Gestion des accès”
5. API CRUD (roles, permissions, rules, scopes, simulation)
6. Seed de règles préconfigurées (cas recrue/formateur/gestionnaire/admin)
7. Journalisation sécurité (accès refusés + modifications de règles)
8. Tests (unitaires + intégration)
9. Documentation technique + guide d’exploitation admin

---

## PLAN D’EXÉCUTION IMPOSÉ

### Étape 0 — Audit de l’existant (obligatoire)
1. Identifier ce qui existe déjà :
   - tables roles / permissions / role_permissions
   - middleware auth/permission
   - services d’autorisation
   - écrans back-office rôles/droits
2. Rédiger un mini rapport :
   - “Déjà présent”
   - “À ajouter”
   - “À refactorer sans rupture”

### Étape 1 — Modèle de données cible
Créer/compléter les tables suivantes (sans casser l’existant) :

#### `roles` (compléter si nécessaire)
- id
- tenant_id
- name
- slug
- level (hiérarchie)
- is_system
- created_at

#### `permissions`
- id
- code (ex: `documents.read`, `users.edit`)
- label
- category (`module`, `page`, `data`, `action`)

#### `role_permissions`
- role_id
- permission_id
- allowed (bool)

#### `access_rules`
- id
- tenant_id
- name
- description
- target_type (`USER`, `ROLE`)
- target_id
- condition_type (`MODULE_VALIDATED`, `DAYS_SINCE_CREATION`, `MANUAL_APPROVAL`, `CUSTOM`, `STATUS`, `UNIT`)
- condition_value (JSON)
- effect (`ALLOW`, `DENY`)
- priority (int)
- is_active
- created_at
- updated_at

#### `access_scopes`
- id
- rule_id
- scope_type (`PAGE`, `MODULE`, `DOCUMENT`, `DATA`)
- scope_identifier (ex: `/admin/users`, `module:popotte`, `doc:internal`, `data:users.email`)
- action (`READ`, `WRITE`, `DELETE`, `EXPORT`)

#### `access_logs`
- id
- tenant_id
- user_id
- resource
- action
- decision (`ALLOW`/`DENY`)
- reason
- context_json
- created_at

### Étape 2 — Moteur d’autorisation
Créer `AccessControlService` avec :

```php
canAccess(User $user, string $resource, string $action): bool
```

Ordre de décision :
1. Charger rôles de l’utilisateur
2. Charger permissions RBAC
3. Charger règles ABAC actives applicables (user + rôle + tenant)
4. Résoudre les scopes correspondants
5. Appliquer priorités :
   - `DENY` > `ALLOW`
   - règle la plus spécifique > règle globale
   - priorité numérique décroissante
6. Fallback sécurisé : **deny by default**
7. Tracer dans `access_logs`

Cache recommandé : mémoire PHP + Redis (si disponible), avec invalidation sur modification de rôle/règle.

### Étape 3 — Conditions ABAC extensibles
Créer une architecture extensible :
- `ConditionEvaluatorInterface`
- un evaluator par condition

Conditions minimales :
- accès après X jours (`DAYS_SINCE_CREATION`)
- accès après validation module (`MODULE_VALIDATED`)
- accès selon unité (`UNIT`)
- accès manuel (`MANUAL_APPROVAL`)
- accès selon statut (`STATUS` : recrue/actif/suspendu)

### Étape 4 — UI admin “Gestion des accès” (priorité majeure)
Construire une interface non technique, ultra lisible :

#### A. Onglet “Rôles”
- liste
- création/édition
- matrice permissions (checkbox)

#### B. Onglet “Règles d’accès”
- builder visuel :
  - cible (user/rôle)
  - condition
  - ressource
  - action
  - effet
  - priorité
  - activation

#### C. Onglet “Matrice visuelle”
Tableau ressource × action (Lire/Écrire/Supprimer/Exporter) avec badges état.

#### D. Onglet “Simulation”
- sélectionner utilisateur
- afficher : autorisé / refusé + explication de la règle gagnante

Composants UX à utiliser :
- selects dynamiques
- tags
- toggles
- badges d’état
- pattern “Si → Alors”

### Étape 5 — Intégration système
Appliquer le contrôle dans :
- middleware global
- controllers (web)
- API
- front (affichage conditionnel des actions sensibles)

Cas admin :
- rôle admin plateforme peut bypass selon règle système explicite et auditée.

### Étape 6 — Cas métiers à couvrir
- Recrue : accès limité + déblocage progressif
- Formateur : modules formation
- Gestionnaire : accès complet unité
- Admin : supervision complète

### Étape 7 — Sécurité & audit
- deny by default
- journalisation des refus
- journalisation des changements de règles
- validation stricte serveur (ne pas faire confiance au front)

### Étape 8 — API CRUD
Créer endpoints sécurisés pour :
- roles
- permissions
- role-permissions
- access-rules
- access-scopes
- simulation

### Étape 9 — Tests
Ajouter :
- tests unitaires `AccessControlService`
- tests unitaires evaluators ABAC
- tests intégration middleware/controller/API
- scénarios multi-tenant + priorité DENY

---

## CONTRAINTES DE QUALITÉ
- Respecter les conventions de code existantes du projet.
- Pas de duplication inutile.
- Migrations idempotentes et rollback-safe.
- Nommage cohérent avec le domaine Athena.
- Toute nouvelle permission/règle doit être traçable.

---

## FORMAT DE SORTIE ATTENDU DE TA PART
Réponds en 6 blocs :
1. **Gap analysis** (existant vs cible)
2. **Plan technique détaillé**
3. **DDL / migrations proposées**
4. **Code PHP à créer/modifier** (services, middleware, controllers, repositories)
5. **UI/UX + routes + API**
6. **Jeux de tests + exemples de règles préconfigurées**

Puis exécute l’implémentation incrémentale par petits commits logiques.
