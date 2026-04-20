# Plan technique — Administration centrale (audit + extension)

## 1) Existant identifié
- Authentification centralisée via `AuthController`, middlewares (`AuthMiddleware`, `SystemAdminMiddleware`, `PlatformHubMiddleware`).
- Back-office déjà structuré avec routes `/admin/*` (plateforme) et `/back-office/*` (tenant).
- Rôles et permissions déjà présents (`SystemRoleController`, `SystemSiteRoleAssignmentController`, `RoleAdminController`).
- Sanctions existantes côté plateforme (`SystemMemberSanctionsController`) et services de modération (`ModerationService`, `ModerationRepository`).
- Audit transversal existant (`audit_logs`, `AuditLogRepository`, écran `/admin/audit`).
- Analytics existant (`SystemAnalyticsController`, `TenantAnalyticsRepository`, événements usage).
- Gestion modules / releases existante (`platform_modules`, `PlatformDeploymentAdminController`).

## 2) Manques ciblés
- Pas de journal structuré d’**actions administratives undo/compensation** (au-delà de `audit_logs`).
- Pas de file opérationnelle unifiée pour les actions annulables.
- Pas de vue consolidée « command center » orientée exécution + réversibilité.
- Pas de tables dédiées `admin_actions`, `admin_action_undo`, `admin_action_compensations`.

## 3) Créations apportées
- Migration SQL idempotente :
  - `admin_actions`
  - `admin_action_undo`
  - `admin_action_compensations`
  - `page_registry`
  - `page_state_history`
- Nouveau module `/admin/command-center`:
  - KPI sécurité/modération/actions
  - historique d’actions admin filtrable
  - file des actions annulables
  - annulation contrôlée avec motif
- Services/repositories:
  - `AdminActionRepository`
  - `SecurityEventRepository`
  - `AdminActionService`
  - `UndoService`

## 4) Extensions de briques existantes
- `SystemMemberSanctionsController`: journalise les sanctions appliquées/levées dans `admin_actions`.
- `SystemSiteRoleAssignmentController`: journalise les affectations/révocations de rôles site avec métadonnées de rollback.
- `SiteRoleAssignmentRepository`: expose la recherche d’affectation active email+role (support annulation).
- Navigation admin rapide (`quick_actions_system.php`) + routes dédiées command center / undo.

## 5) Refactor / gouvernance
- Logique sensible déplacée vers services (`AdminActionService`, `UndoService`) pour éviter les contrôleurs obèses.
- Annulation métier explicite:
  - **technique** si possible (révocation affectation rôle, levée sanction)
  - **compensation** sinon (enregistrement `admin_action_compensations`).

## 6) Points d’extension futurs
- Brancher les actions massives (bulk) sur `admin_actions`.
- Étendre UndoService aux états de pages/modules.
- Ajouter exports CSV dédiés command center.
- Coupler avec `page_registry` pour supervision active/maintenance par route.
