# Modules fonctionnels (cartographie)

Vue **haut niveau** : fonctionnalité → zone principale du code. Les chemins sont relatifs à la racine du dépôt.

## Authentification et comptes

| Fonctionnalité | Contrôleurs / zones |
|----------------|---------------------|
| Connexion, déconnexion, mot de passe | `app/Controllers/Auth/AuthController.php` |
| Inscription, vérification e-mail | `RegisterController`, `VerifyEmailController` |
| Compte utilisateur, préférences | `app/Controllers/Web/AccountController.php` |
| Appareils / sécurité (selon activation) | `SecurityDeviceController` |

## Communautés multi-tenant

| Fonctionnalité | Contrôleurs / zones |
|----------------|---------------------|
| Page publique `/c/{slug}`, contact, entrée forum/enrôlement | `CommunityController` |
| Création de communauté, paiement Stripe | `CommunityController`, routes dédiées |
| Invitations, parrainage | `InvitationAcceptController`, `ReferralInviteController`, `JoinController` |

## Personnel et ORBAT

| Fonctionnalité | Contrôleurs / zones |
|----------------|---------------------|
| Fiches, annuaire, organigramme | `PersonnelController` |
| Dossier opérateur / accréditation | `DossierOperateurController` |
| Back-office : unités, grades, équipes, rôles métier | `AdminUnitsController`, `GradeReferentielController`, `GroupAdminController`, `TeamAdminController`, `PersonnelJobRoleAdminController` |

## Documents

| Fonctionnalité | Contrôleurs / zones |
|----------------|---------------------|
| Consultation membre | `DocumentsController` |
| Administration documentaire | `AdminDocumentsController` |
| Accès métier | `DocumentAccessService`, `DocumentRepository` |

## Formations (LMS / studio)

| Fonctionnalité | Contrôleurs / zones |
|----------------|---------------------|
| Catalogue, cours, leçons côté web | `TrainingController` |
| API formations | `TrainingApiController` |
| Administration parcours et studio | `AdminTrainingController`, `AdminTrainingStudioController` |
| Support canvas / LMS | `app/Support/training_canvas.php`, `training_lms.php`, assets JS/CSS associés |

## Forum

| Fonctionnalité | Contrôleurs / zones |
|----------------|---------------------|
| Liste, catégories, sujets, nouveau sujet | `ForumController`, `ForumCategoryController`, `ForumTopicController`, `ForumNewTopicController` |
| API forum, upload, REST | `ForumApiController`, `ForumUploadController`, `ForumRestController` |
| Modération (web + API) | `ForumModerationController`, `ForumModerationDashboardController`, `ForumModerationApiController`, middlewares forum |

## Événements et pointage

| Fonctionnalité | Contrôleurs / zones |
|----------------|---------------------|
| Calendrier / événements membre | `CommunityEventsController`, `PointageController` |
| Administration événements | `CommunityEventsAdminController` |
| Services présence / rappels | `app/Services/Attendance/`, scripts cron type `send-attendance-reminders.php` |

## Messagerie interne

| Fonctionnalité | Contrôleurs / zones |
|----------------|---------------------|
| Fil tenant | `TenantMessagesController` |

## Courrier officiel

| Fonctionnalité | Contrôleurs / zones |
|----------------|---------------------|
| Tableau de bord, éditeur, modèles, workflow, PDF, signatures | `app/Controllers/Courrier/*` |

## Recherche portail

| Fonctionnalité | Contrôleurs / zones |
|----------------|---------------------|
| UI recherche | `PortalSearchController`, `public/assets/js/portal_search.js` |

## Enrôlement / recrutement

| Fonctionnalité | Contrôleurs / zones |
|----------------|---------------------|
| Formulaires publics | `EnlistmentController` |
| Back-office recrutements, messages préfaits | `AdminRecruitmentsController`, dépôt `EnlistmentCannedMessageRepository` |

## Équipement, modpacks, ATAK

| Fonctionnalité | Contrôleurs / zones |
|----------------|---------------------|
| Pages web équipement / modpacks | `EquipmentController`, `ModpackController` |
| ATAK (web + admin config) | `AtakController`, `AdminAtakConfigController`, `AdminAtakModController` |
| API tactiques (intel, logistique, etc.) | `app/Controllers/Api/*` (Atak, FireSupport, Logistics, Intel, Replay, Iff, DangerZone…) |

## Administration plateforme (système)

| Fonctionnalité | Contrôleurs / zones |
|----------------|---------------------|
| Tableau de bord système, rôles site, paramètres, alertes plateforme, maintenance, audit | `app/Controllers/Admin/System/*` |

## Administration organisation

| Fonctionnalité | Contrôleurs / zones |
|----------------|---------------------|
| Dashboard org, utilisateurs, rôles, invitations, audit org, analytics, modération org, épingle dashboard | `app/Controllers/Admin/Organization/*` |

## Intégrations transverses

| Sujet | Emplacement |
|-------|-------------|
| Navigation | `config/navigation.php`, `app/Support/navigation_menu.php` |
| E-mail | `config/email.php`, `EmailService`, `EmailTransportResolver` |
| Modération fichiers | `ContentModerationController`, configuration `MODERATION_*` dans `.env` |

## Voir aussi

- [Architecture](architecture.md) — routage et middlewares.
- [Sécurité et permissions](securite-et-permissions.md).
