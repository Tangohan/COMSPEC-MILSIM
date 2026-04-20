# Gap analysis — RBAC + ABAC multi-tenant (Athena)

## Déjà présent
- RBAC existant via `roles`, `permissions`, `role_permissions` et `tenant_user_roles`.
- Résolution des permissions au login/requête via `RbacService` + `Gate`.
- UI back-office rôles/droits disponible (`back-office/roles`, presets, rôles/fonctions).
- Middleware auth déjà en place, plus middlewares métiers.

## À ajouter
- Colonnes manquantes (`roles.level`, `permissions.code/label/category`, `role_permissions.allowed`).
- Tables ABAC (`access_rules`, `access_scopes`) + audit (`access_logs`).
- Moteur unifié `AccessControlService` (RBAC + ABAC, deny-by-default ABAC, priorités).
- Évaluateurs ABAC extensibles (`DAYS_SINCE_CREATION`, `MODULE_VALIDATED`, `UNIT`, `MANUAL_APPROVAL`, `STATUS`).
- UI “Gestion des accès” avec onglets Rôles / Règles / Matrice / Simulation.
- API CRUD dédiée pour rôles, permissions, règles, scopes et simulation.

## À refactorer sans rupture
- Intégrer un middleware d’accès global après auth sans supprimer `Gate` historique.
- Ajouter une entrée de navigation back-office “Gestion des accès”.
- Journaliser explicitement les changements de politiques d’accès dans `access_logs`.
