# Feuille de route — Athena

Routes de l’application, statut et écarts connus. **Référence code** : [`routes/web.php`](../routes/web.php). Pour la description fonctionnelle détaillée, voir [INVENTAIRE-FONCTIONNALITES.md](INVENTAIRE-FONCTIONNALITES.md).

---

## Légende

| Statut | Signification |
|--------|----------------|
| ✅ Complet | Route + contrôleur + vue (ou API) fonctionnels |
| 🔶 Partiel | Existant mais fonctionnalité incomplète ou à consolider |
| ⬜ À faire | Prévu / manquant |

---

## 1. Public (sans auth)

| Méthode | Chemin | Contrôleur | Statut | Notes |
|---------|--------|------------|--------|-------|
| GET | `/` | HomeController::index | ✅ | Accueil |
| GET | `/login` | AuthController::showLogin | ✅ | GuestMiddleware |
| POST | `/login` | AuthController::login | ✅ | |
| POST | `/logout` | AuthController::logout | ✅ | |
| GET | `/forgot-password` | AuthController::showForgotPassword | ✅ | |
| POST | `/forgot-password` | AuthController::sendResetLink | ✅ | |
| GET | `/reset-password` | AuthController::showResetPassword | ✅ | |
| POST | `/reset-password` | AuthController::processResetPassword | ✅ | |
| GET | `/enlistment` | EnlistmentController::show | ✅ | Candidature |
| POST | `/enlistment` | EnlistmentController::store | ✅ | |
| GET | `/enlistment/success` | EnlistmentController::success | ✅ | |
| GET | `/enlistment/error` | EnlistmentController::error | ✅ | |
| GET | `/recrutement` | HomeController::recrutement | ✅ | Page vitrine |
| GET | `/equipement` | HomeController::equipement | ✅ | Page vitrine |

---

## 2. Authentifié (AuthMiddleware)

### 2.1 Général, compte, hub

| Méthode | Chemin | Contrôleur | Statut | Notes |
|---------|--------|------------|--------|-------|
| GET | `/dashboard` | HomeController::dashboard | ✅ | |
| GET | `/hub` | HubController::index | ✅ | Menu modules |
| GET | `/account` | AccountController::index | ✅ | Sans session : redirection vers `/login` avec message explicite ; après connexion, retour automatique vers la page demandée (GET) si encore valide (voir `App\Support\LoginIntendedDestination`). |
| GET/POST | `/account/preferences` | AccountController::preferences | ✅ | |
| GET/POST | `/account/mail` | AccountController::mail | ✅ | |
| GET/POST | `/account/image` | AccountController::image | ✅ | Avatar |
| GET/POST | `/account/banner` | AccountController::banner | ✅ | Couverture menu session |
| GET/POST | `/account/portrait` | AccountController::portrait | ✅ | Portrait |
| GET/POST | `/account/password` | AccountController::password | ✅ | |

### 2.2 Personnel et ORBAT

| Méthode | Chemin | Contrôleur | Statut | Notes |
|---------|--------|------------|--------|-------|
| GET | `/personnel/me` | PersonnelController::me | ✅ | |
| GET | `/personnel/me/edit` | PersonnelController::edit | ✅ | |
| GET | `/personnel/{id}` | PersonnelController::show | ✅ | |
| GET | `/personnel/{id}/edit` | PersonnelController::edit | ✅ | |
| POST | `/personnel/{id}/update` | PersonnelController::update | ✅ | |
| POST | `/personnel/{id}/generate-matricule` | PersonnelController::generateMatricule | ✅ | |
| POST | `/personnel/{id}/notes` | PersonnelController::updateNotes | ✅ | |
| GET | `/orbat` | PersonnelController::orbat | ✅ | Permission `organization.orbat.view` (ou dérivée : gestion ORBAT, administration organisation) |
| GET | `/api/orbat/roster` | OrbatApiController::roster | ✅ | Session ; même périmètre que la page ORBAT |
| GET | `/api/orbat/structure-options` | OrbatApiController::structureOptions | ✅ | Réservé aux gestionnaires de structure |
| POST | `/api/orbat/chart-type` | OrbatApiController::chartType | ✅ | Idem + jeton de formulaire |
| POST | `/api/orbat/unit-upload` | OrbatApiController::uploadUnitMedia | ✅ | Idem |
| POST | `/api/orbat/structure` | OrbatApiController::structure | ✅ | Idem |
| POST | `/api/orbat/unit` | OrbatApiController::updateUnit | ✅ | Idem |

### 2.3 Mur opérationnel (planning)

| Méthode | Chemin | Contrôleur | Statut | Notes |
|---------|--------|------------|--------|-------|
| GET | `/tableau-operationnel` | OperationalBoardController::portalIndex | ✅ | Lecture portail (`operational.board.view` ou équivalent) |
| GET | `/back-office/tableau-operationnel` | OperationalBoardController::index | ✅ | Pilotage (`operational.board.edit` ou admin) |
| GET | `/back-office/tableau-operationnel/stream` | OperationalBoardController::stream | ✅ | Flux JSON (pilotage) |
| GET | `/back-office/tableau-operationnel/fiche/nouvelle` | OperationalBoardController::formNew | ✅ | |
| GET | `/back-office/tableau-operationnel/fiche/{id}` | OperationalBoardController::formEdit | ✅ | |
| POST | `/back-office/tableau-operationnel` | OperationalBoardController::store | ✅ | |
| POST | `/back-office/tableau-operationnel/fiche/{id}` | OperationalBoardController::update | ✅ | |
| POST | `/back-office/tableau-operationnel/fiche/{id}/dupliquer` | OperationalBoardController::duplicate | ✅ | |
| POST | `/back-office/tableau-operationnel/publier-lie` | OperationalBoardController::storeLinked | ✅ | Même source déjà liée (fiche non annulée) : redirection vers la fiche existante ; réponse JSON 409 si `Accept: application/json` ou requête `X-Requested-With: XMLHttpRequest` |
| POST | `/back-office/tableau-operationnel/posture` | OperationalBoardController::setPosture | ✅ | |
| POST | `/back-office/tableau-operationnel/template` | OperationalBoardController::createFromTemplate | ✅ | |
| POST | `/back-office/tableau-operationnel/{id}/validation` | OperationalBoardController::transitionValidation | ✅ | |
| POST | `/back-office/tableau-operationnel/{id}/status` | OperationalBoardController::transitionOperationalStatus | ✅ | |
| POST | `/back-office/tableau-operationnel/{id}/frago` | OperationalBoardController::createFrago | ✅ | |
| POST | `/back-office/tableau-operationnel/{id}/checklist/{itemId}` | OperationalBoardController::toggleChecklist | ✅ | |
| POST | `/back-office/tableau-operationnel/{id}/retirer-du-mur` | OperationalBoardController::retireFromBoard | ✅ | Retrait du portail (confirmation obligatoire côté interface) |

### 2.4 Documents (lecture + fichiers)

| Méthode | Chemin | Contrôleur | Statut | Notes |
|---------|--------|------------|--------|-------|
| GET | `/documents` | DocumentsController::index | ✅ | Liste |
| GET | `/documents/{slug}` | DocumentsController::show | ✅ | Détail |
| GET | `/documents/{id}/file` | DocumentsController::file | ✅ | |
| GET | `/documents/{id}/download` | DocumentsController::download | ✅ | |

### 2.5 Documents (gestion — permissions `documents.*`)

| Méthode | Chemin | Contrôleur | Statut | Notes |
|---------|--------|------------|--------|-------|
| GET | `/documents/gestion` | AdminDocumentsController::index | ✅ | |
| GET | `/documents/gestion/ajout` | AdminDocumentsController::uploadForm | ✅ | |
| POST | `/documents/gestion/ajout` | AdminDocumentsController::upload | ✅ | |
| GET | `/documents/gestion/arborescence` | AdminDocumentsController::tree | ✅ | |
| GET | `/documents/gestion/{id}` | AdminDocumentsController::show | ✅ | |
| GET | `/documents/gestion/{id}/modifier` | AdminDocumentsController::edit | ✅ | |
| POST | `/documents/gestion/{id}/modifier` | AdminDocumentsController::update | ✅ | |
| POST | `/documents/gestion/{id}/nouvelle-version` | AdminDocumentsController::newVersion | ✅ | |
| POST | `/documents/gestion/{id}/archiver` | AdminDocumentsController::archive | ✅ | |
| GET | `/documents/gestion/{id}/historique` | AdminDocumentsController::history | ✅ | |
| GET | `/documents/gestion/{id}/acces` | AdminDocumentsController::access | ✅ | |

### 2.6 Équipement, modpacks, formations

| Méthode | Chemin | Contrôleur | Statut | Notes |
|---------|--------|------------|--------|-------|
| GET | `/equipment` | EquipmentController::index | ✅ | |
| GET | `/equipment/{slug}` | EquipmentController::show | ✅ | |
| GET | `/modpacks` | ModpackController::index | ✅ | |
| GET | `/modpacks/images/{id}` | ModpackController::image | ✅ | |
| GET | `/modpacks/{id}/download` | ModpackController::download | ✅ | |
| GET | `/modpacks/{slug}` | ModpackController::show | ✅ | |
| GET | `/formations` | TrainingController::index | ✅ | Catalogue |
| GET | `/formations/mes-formations` | TrainingController::myTraining | ✅ | |
| GET | `/formations/lesson/{id}` | TrainingController::lesson | ✅ | |
| GET | `/formations/quiz/{id}` | TrainingController::quiz | ✅ | |
| GET | `/formations/certificate/{id}` | TrainingController::certificate | ✅ | |
| GET | `/formations/{slug}` | TrainingController::showBySlug | ✅ | |
| GET | `/formation` | AdminTrainingController::dashboard | ✅ | Pilotage LMS staff (hors catalogue apprenant `/formations`) |

### 2.7 ATAK et vues tactiques

| Méthode | Chemin | Contrôleur | Statut | Notes |
|---------|--------|------------|--------|-------|
| GET | `/atak` | AtakController::index | ✅ | |
| GET | `/atak/setup` | AtakController::setup | ✅ | |
| GET | `/atak/mod/download` | AtakController::downloadMod | ✅ | |
| GET | `/atak/tuto` | AtakController::tuto | ✅ | |
| GET | `/tacmap` | HomeController::tacmap | ✅ | Carte tactique (Leaflet + données communauté) ; enrichissements carto = évolution produit |
| GET | `/overwatch` | HomeController::overwatch | ✅ | Poste de commandement |

### 2.8 Forum

| Méthode | Chemin | Contrôleur | Statut | Notes |
|---------|--------|------------|--------|-------|
| GET | `/forum` | ForumController::index | ✅ | |
| GET | `/forum/category/{slug}` | ForumCategoryController::show | ✅ | |
| GET | `/forum/topic/{id}` | ForumTopicController::show | ✅ | |
| POST | `/forum/topic/{id}/reply` | ForumTopicController::reply | ✅ | |
| POST | `/forum/topic/{id}/subscribe` | ForumTopicController::subscribe | ✅ | |
| POST | `/forum/topic/{id}/unsubscribe` | ForumTopicController::unsubscribe | ✅ | |
| GET | `/forum/new-topic` | ForumNewTopicController::form | ✅ | |
| POST | `/forum/new-topic` | ForumNewTopicController::store | ✅ | |
| GET | `/forum/moderation` | ForumModerationController::index | ✅ | ForumModerateMiddleware |
| POST | `/forum/report/{id}/handle` | ForumModerationController::handleReport | ✅ | |
| POST | `/forum/topic/{id}/lock` | ForumModerationController::lockTopic | ✅ | |
| POST | `/forum/topic/{id}/unlock` | ForumModerationController::unlockTopic | ✅ | |
| POST | `/forum/topic/{id}/pin` | ForumModerationController::pinTopic | ✅ | |
| POST | `/forum/topic/{id}/unpin` | ForumModerationController::unpinTopic | ✅ | |

### 2.9 Courrier

| Méthode | Chemin | Contrôleur | Statut | Notes |
|---------|--------|------------|--------|-------|
| GET | `/courrier` | CourrierDashboardController::index | ✅ | |
| GET | `/courrier/editor` | CourrierEditorController::index | ✅ | |
| GET | `/courrier/editor/{id}` | CourrierEditorController::edit | ✅ | |
| GET | `/courrier/read/{id}` | CourrierReadController::show | ✅ | |
| POST | `/courrier/editor/save` | CourrierEditorController::save | ✅ | |
| GET | `/courrier/templates` | CourrierTemplateController::index | ✅ | |
| GET | `/courrier/templates/create` | CourrierTemplateController::create | ✅ | |
| POST | `/courrier/templates` | CourrierTemplateController::store | ✅ | |
| GET | `/courrier/templates/{id}/edit` | CourrierTemplateController::edit | ✅ | |
| POST | `/courrier/templates/{id}` | CourrierTemplateController::update | ✅ | |
| GET | `/courrier/presets` | CourrierPresetController::index | ✅ | |
| POST | `/courrier/presets/{id}/default` | CourrierPresetController::setDefault | ✅ | |
| POST | `/courrier/documents/{id}/workflow` | CourrierWorkflowController::transition | ✅ | |
| POST | `/courrier/documents/{id}/sign` | CourrierSignatureController::sign | ✅ | |
| GET | `/courrier/documents/{id}/verify` | CourrierSignatureController::verify | ✅ | |
| GET | `/courrier/documents/{id}/signature-image` | CourrierSignatureController::documentSignatureImage | ✅ | |
| GET | `/courrier/verify` | CourrierSignatureController::verifyByUuid | ✅ | Public vérif |
| GET | `/courrier/my-signatures` | CourrierSignatureController::mySignatures | ✅ | |
| GET | `/courrier/signatures/{id}/image` | CourrierSignatureController::signatureImage | ✅ | |
| GET | `/courrier/documents/{id}/print` | CourrierPdfController::print | ✅ | |
| GET | `/courrier/history` | CourrierDashboardController::history | ✅ | |
| GET | `/courrier/archives` | CourrierDashboardController::archives | ✅ | |

---

## 3. Administration

### 3.1 Hub et legacy

| Méthode | Chemin | Contrôleur | Statut | Notes |
|---------|--------|------------|--------|-------|
| GET | `/admin` | AdminHubController::index | ✅ | Choix sys / org |
| GET | `/admin/users` | *redirect* | ✅ | → `admin/organization/users` |
| GET | `/admin/users/create` | *redirect* | ✅ | → `admin/organization/users/create` |
| GET | `/admin/units` | *redirect* | ✅ | → `admin/organization/groups` |
| GET | `/admin/units/create` | *redirect* | ✅ | → `admin/organization/groups/create` |
| GET | `/admin/units/{id}/edit` | *redirect* | ✅ | → `admin/organization/groups/{id}/edit` |
| POST | `/admin/units/store` | AdminUnitsController::store | ✅ | ORBAT / unités |
| POST | `/admin/units/{id}/update` | AdminUnitsController::update | ✅ | |
| POST | `/admin/units/{id}/delete` | AdminUnitsController::delete | ✅ | |

### 3.2 Administration système (SystemAdminMiddleware)

| Méthode | Chemin | Contrôleur | Statut |
|---------|--------|------------|--------|
| GET | `/admin/system` | SystemDashboardController::index | ✅ |
| GET | `/admin/system/roles` | SystemRoleController::index | ✅ |
| GET | `/admin/system/roles/{id}` | SystemRoleController::show | ✅ |
| GET | `/admin/system/roles/{id}/edit` | SystemRoleController::edit | ✅ |
| POST | `/admin/system/roles/{id}/update` | SystemRoleController::update | ✅ |
| GET | `/admin/system/settings` | SystemSettingsController::index | ✅ |
| GET | `/admin/system/audit` | SystemAuditController::index | ✅ |

### 3.3 Administration organisationnelle (OrganizationAdminMiddleware)

| Méthode | Chemin | Contrôleur | Statut |
|---------|--------|------------|--------|
| GET | `/admin/organization` | OrganizationDashboardController::index | ✅ |
| GET | `/admin/organization/users` | UserAdminController::index | ✅ |
| GET | `/admin/organization/users/create` | UserAdminController::create | ✅ |
| POST | `/admin/organization/users/store` | UserAdminController::store | ✅ |
| GET | `/admin/organization/users/{id}` | UserAdminController::show | ✅ |
| GET | `/admin/organization/users/{id}/edit` | UserAdminController::edit | ✅ |
| POST | `/admin/organization/users/{id}/update` | UserAdminController::update | ✅ |
| POST | `/admin/organization/users/{id}/deactivate` | UserAdminController::deactivate | ✅ |
| GET | `/admin/organization/roles` | RoleAdminController::index | ✅ |
| GET | `/admin/organization/roles/{id}` | RoleAdminController::show | ✅ |
| GET | `/admin/organization/categories` | CategoryAdminController::index | ✅ |
| GET | `/admin/organization/categories/create` | CategoryAdminController::create | ✅ |
| POST | `/admin/organization/categories/store` | CategoryAdminController::store | ✅ |
| GET | `/admin/organization/categories/{id}/edit` | CategoryAdminController::edit | ✅ |
| POST | `/admin/organization/categories/{id}/update` | CategoryAdminController::update | ✅ |
| GET | `/admin/organization/referentiels/grades` | GradeReferentielController::index | ✅ |
| GET | `/admin/organization/referentiels/grades/create` | GradeReferentielController::create | ✅ |
| POST | `/admin/organization/referentiels/grades/store` | GradeReferentielController::store | ✅ |
| GET | `/admin/organization/referentiels/grades/{id}/edit` | GradeReferentielController::edit | ✅ |
| POST | `/admin/organization/referentiels/grades/{id}/update` | GradeReferentielController::update | ✅ |
| POST | `/admin/organization/referentiels/grades/{id}/deactivate` | GradeReferentielController::deactivate | ✅ |
| GET | `/admin/organization/groups` | GroupAdminController::index | ✅ |
| GET | `/admin/organization/groups/create` | GroupAdminController::create | ✅ |
| POST | `/admin/organization/groups/store` | GroupAdminController::store | ✅ |
| GET | `/admin/organization/groups/{id}` | GroupAdminController::show | ✅ |
| GET | `/admin/organization/groups/{id}/edit` | GroupAdminController::edit | ✅ |
| POST | `/admin/organization/groups/{id}/update` | GroupAdminController::update | ✅ |
| POST | `/admin/organization/groups/{id}/delete` | GroupAdminController::delete | ✅ |
| GET | `/admin/organization/teams` | TeamAdminController::index | ✅ |
| GET | `/admin/organization/teams/create` | TeamAdminController::create | ✅ |
| POST | `/admin/organization/teams/store` | TeamAdminController::store | ✅ |
| GET | `/admin/organization/teams/{id}` | TeamAdminController::show | ✅ |
| GET | `/admin/organization/teams/{id}/edit` | TeamAdminController::edit | ✅ |
| POST | `/admin/organization/teams/{id}/update` | TeamAdminController::update | ✅ |
| POST | `/admin/organization/teams/{id}/delete` | TeamAdminController::delete | ✅ |

### 3.4 Autres pages admin (AuthMiddleware)

| Méthode | Chemin | Contrôleur | Statut | Notes |
|---------|--------|------------|--------|-------|
| GET | `/admin/configuration` | AdminConfigurationController::index | ✅ | |
| GET | `/admin/recruitments` | *redirect* | ✅ | Vers `back-office/recruitments` (`AuthMiddleware`) |
| GET | `/back-office/recruitments` | AdminRecruitmentsController::index | ✅ | `OrganizationAdminMiddleware` |
| GET | `/back-office/recruitments/{id}` | AdminRecruitmentsController::show | ✅ | Fiche, décision, finalisation adhésion |
| POST | `/back-office/recruitments/{id}/decision` | AdminRecruitmentsController::decision | ✅ | |
| POST | `/back-office/recruitments/{id}/finalize-membership` | AdminRecruitmentsController::finalizeMembership | ✅ | |
| GET | `/back-office/recruitments/messages-prefaits` | AdminRecruitmentsController::cannedMessagesIndex | ✅ | |
| POST | `/back-office/recruitments/messages-prefaits` | AdminRecruitmentsController::cannedMessageStore | ✅ | |
| POST | `/back-office/recruitments/messages-prefaits/{id}/update` | AdminRecruitmentsController::cannedMessageUpdate | ✅ | |
| POST | `/back-office/recruitments/messages-prefaits/{id}/delete` | AdminRecruitmentsController::cannedMessageDelete | ✅ |
| GET | `/admin/modpacks` | AdminModpackController::index | ✅ | |
| GET | `/admin/modpacks/create` | AdminModpackController::create | ✅ | |
| POST | `/admin/modpacks/store` | AdminModpackController::store | ✅ | |
| GET | `/admin/modpacks/{id}/edit` | AdminModpackController::edit | ✅ | |
| POST | `/admin/modpacks/{id}/update` | AdminModpackController::update | ✅ | |
| POST | `/admin/modpacks/{id}/delete` | AdminModpackController::delete | ✅ | |
| GET | `/admin/atak-config` | AdminAtakConfigController::index | ✅ | |
| POST | `/admin/atak-config` | AdminAtakConfigController::store | ✅ | |
| GET | `/admin/atak-mod` | AdminAtakModController::index | ✅ | |
| POST | `/admin/atak-mod/upload` | AdminAtakModController::upload | ✅ | |
| POST | `/admin/atak-mod/delete` | AdminAtakModController::delete | ✅ | |
| GET | `/admin/training` et sous-chemins | Redirection vers `/formation/…` (via `training_lms_admin_url`) | ✅ | Compatibilité URL ; pas de POST |
| GET/POST | `/formation`, `/formation/studio`, sous-chemins LMS | Mêmes contrôleurs qu’auparavant | ✅ | Pilotage LMS (middleware staff formation) ; UI sans navbar portail |
| POST | `/formation/studio/preamble-ack` | AdminTrainingStudioController::postPreambleAck | ✅ | Validation du préambule d’accès Studio (session) |
| GET/POST | `/back-office/ressources/training` et sous-chemins | Redirection vers `/formation/…` équivalent (query conservée ; POST → 307) | ✅ | Compatibilité URL historique |
| GET | `/admin/forum-config` | AdminForumConfigController::index | ✅ | |

---

## 4. API (JSON / usage client)

Les préfixes `/api/training/*`, `/api/atak/*`, `/api/orbat/*`, `/api/cas`, `/api/recon/*`, `/api/map-shapes`, `/api/fire-support/*`, `/api/danger-zones`, `/api/logistics/*`, `/api/intel/*`, `/api/replay/*`, `/api/iff/*`, `/api/forum*`, `/api/admin/*`, `/api/health` sont définis dans [`routes/web.php`](../routes/web.php). Détail : voir inventaire [INVENTAIRE-FONCTIONNALITES.md](INVENTAIRE-FONCTIONNALITES.md) (ATAK / API, couverture JavaScript).

---

## 5. Partiellement fait ou pistes

| Élément | État | Suggestion |
|---------|------|------------|
| **Admin candidatures** | ✅ | Parcours principal : `/back-office/recruitments` (offres : `/back-office/recruitment/offers`) |
| **TACMAP (roadmap)** | 🔶 | Pistes d’enrichissement cartographique optionnelles |
| **Erreurs 404/500** | ✅ | 404 : [`Router`](../app/Core/Router.php) → `views/errors/404.php` ; 500 : [`ExceptionHandler`](../app/Core/ExceptionHandler.php) et shutdown dans [`public/index.php`](../public/index.php) → `views/errors/500.php` hors mode debug |

### Redirections `.htaccess`

Les anciennes URLs `.html` sont redirigées vers les URLs propres (`public/.htaccess`).

---

*Dernière mise à jour : alignement sur `routes/web.php` (mur opérationnel, ORBAT, recrutements back-office, APIs).*
