# Guide d’exploitation — Gestion des accès

## Objectif
Piloter les accès par communauté (tenant) en combinant :
- RBAC : rôles + permissions.
- ABAC : règles conditionnelles dynamiques.

## UI back-office
Route : `/back-office/access-management`

### Onglet Rôles
- Créez des rôles avec un niveau hiérarchique (`level`).
- Affectez ensuite les permissions via API ou écrans rôles existants.

### Onglet Règles d’accès
Builder “Si → Alors” :
- Cible : ROLE / USER
- Condition : DAYS_SINCE_CREATION, MODULE_VALIDATED, UNIT, MANUAL_APPROVAL, STATUS
- Portée : module/page/document/donnée
- Action : READ/CREATE/UPDATE/DELETE/EXPORT
- Effet : ALLOW ou DENY
- Priorité : nombre (plus grand = plus prioritaire)

### Onglet Matrice
Vue simplifiée ressource × action pour visualiser la politique effective.

### Onglet Simulation
Permet de tester un utilisateur + ressource + action et d’obtenir la décision (ALLOW/DENY) et sa raison.

## API
Préfixes :
- `GET|POST /api/access-control/roles`
- `GET|POST /api/access-control/permissions`
- `POST /api/access-control/role-permissions`
- `GET|POST /api/access-control/rules`
- `GET|POST /api/access-control/scopes`
- `GET|POST /api/access-control/simulation`

## Sécurité
- Deny by default pour ABAC si des règles existent mais aucune règle ALLOW applicable.
- Journalisation de toutes les décisions dans `access_logs`.
- Journalisation des changements de politique (création rôle/règle).
- Validation serveur stricte (l’UI est informative, non fiable comme source de vérité).
