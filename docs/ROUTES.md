# Feuille de route — Athena

Routes de l’application, statut et pages manquantes ou partielles.

---

## Légende

| Statut | Signification |
|--------|----------------|
| ✅ Complet | Route + contrôleur + vue fonctionnels |
| 🔶 Partiel | Route/vue existante mais fonctionnalité incomplète |
| ⬜ À faire | Lien ou fonctionnalité prévue, non implémentée |

---

## 1. Public (sans auth)

| Méthode | Chemin | Contrôleur | Statut | Notes |
|---------|--------|------------|--------|-------|
| GET | `/` | HomeController::index | ✅ | Accueil |
| GET | `/login` | AuthController::showLogin | ✅ | Connexion (GuestMiddleware) |
| POST | `/login` | AuthController::login | ✅ | |
| POST | `/logout` | AuthController::logout | ✅ | |
| GET | `/forgot-password` | AuthController::showForgotPassword | ✅ | |
| POST | `/forgot-password` | AuthController::sendResetLink | ✅ | |
| GET | `/reset-password` | AuthController::showResetPassword | ✅ | |
| POST | `/reset-password` | AuthController::processResetPassword | ✅ | |
| GET | `/enlistment` | EnlistmentController::show | ✅ | Formulaire candidature |
| POST | `/enlistment` | EnlistmentController::store | ✅ | Soumission candidature |
| GET | `/recrutement` | HomeController::recrutement | ✅ | Page info recrutement |
| GET | `/equipement` | HomeController::equipement | ✅ | Page équipement |

---

## 2. Authentifié (AuthMiddleware)

| Méthode | Chemin | Contrôleur | Statut | Notes |
|---------|--------|------------|--------|-------|
| GET | `/dashboard` | HomeController::dashboard | ✅ | Dashboard principal |
| GET | `/account` | AccountController::index | ✅ | Compte |
| GET/POST | `/account/preferences` | AccountController::preferences | ✅ | |
| GET/POST | `/account/mail` | AccountController::mail | ✅ | |
| GET/POST | `/account/image` | AccountController::image | ✅ | Avatar |
| GET/POST | `/account/password` | AccountController::password | ✅ | |
| GET | `/personnel/me` | PersonnelController::me | ✅ | Ma fiche personnel |
| GET | `/personnel/{id}` | PersonnelController::show | ✅ | Fiche d’un membre |
| POST | `/personnel/{id}/generate-matricule` | PersonnelController::generateMatricule | ✅ | |
| GET | `/orbat` | PersonnelController::orbat | ✅ | ORBAT |
| GET | `/documents` | DocumentsController::index | ✅ | Liste documents |
| GET | `/documents/{id}/download` | DocumentsController::download | ✅ | Téléchargement |
| GET | `/modpacks` | ModpackController::index | ✅ | Liste modpacks |
| GET | `/modpacks/images/{id}` | ModpackController::image | ✅ | Image modpack |
| GET | `/modpacks/{id}/download` | ModpackController::download | ✅ | Téléchargement modpack |
| GET | `/modpacks/{slug}` | ModpackController::show | ✅ | Détail modpack |
| GET | `/formations` | TrainingController::index | ✅ | Catalogue formations |
| GET | `/formations/{slug}` | TrainingController::show | ✅ | Détail formation |
| GET | `/atak` | AtakController::index | ✅ | Page ATAK / TACMAP |
| GET | `/tacmap` | HomeController::tacmap | ✅ | Vue tacmap (simple) |
| GET | `/forum` | ForumController::index | ✅ | Forum (Briefing) |
| GET | `/forum/category/{slug}` | ForumCategoryController::show | ✅ | Catégorie |
| GET | `/forum/topic/{id}` | ForumTopicController::show | ✅ | Sujet |
| POST | `/forum/topic/{id}/reply` | ForumTopicController::reply | ✅ | Réponse |
| POST | `/forum/topic/{id}/subscribe` | ForumTopicController::subscribe | ✅ | |
| POST | `/forum/topic/{id}/unsubscribe` | ForumTopicController::unsubscribe | ✅ | |
| GET | `/forum/new-topic` | ForumNewTopicController::form | ✅ | Nouveau sujet |
| POST | `/forum/new-topic` | ForumNewTopicController::store | ✅ | |
| GET | `/forum/moderation` | ForumModerationController::index | ✅ | Modération (ForumModerateMiddleware) |
| POST | `/forum/report/{id}/handle` | ForumModerationController::handleReport | ✅ | Traiter signalement |
| POST | `/forum/topic/{id}/lock` | ForumModerationController::lockTopic | ✅ | |
| POST | `/forum/topic/{id}/unlock` | ForumModerationController::unlockTopic | ✅ | |
| POST | `/forum/topic/{id}/pin` | ForumModerationController::pinTopic | ✅ | |
| POST | `/forum/topic/{id}/unpin` | ForumModerationController::unpinTopic | ✅ | |

---

## 3. Admin (AuthMiddleware + droits admin)

| Méthode | Chemin | Contrôleur | Statut | Notes |
|---------|--------|------------|--------|-------|
| GET | `/admin` | AdminDashboardController::index | ✅ | Tableau de bord admin |
| GET | `/admin/configuration` | AdminConfigurationController::index | ✅ | Unités + données |
| GET | `/admin/recruitments` | AdminRecruitmentsController::index | ✅ | Liste candidatures (filtre statut) |
| GET | `/admin/users` | AdminUsersController::index | ✅ | Utilisateurs |
| GET | `/admin/users/create` | AdminUsersController::create | ✅ | Création utilisateur |
| GET | `/admin/units` | AdminUnitsController::index | ✅ | Unités |
| GET | `/admin/units/create` | AdminUnitsController::create | ✅ | |
| POST | `/admin/units/store` | AdminUnitsController::store | ✅ | |
| GET | `/admin/units/{id}/edit` | AdminUnitsController::edit | ✅ | |
| POST | `/admin/units/{id}/update` | AdminUnitsController::update | ✅ | |
| POST | `/admin/units/{id}/delete` | AdminUnitsController::delete | ✅ | |
| GET | `/admin/modpacks` | AdminModpackController::index | ✅ | Modpacks |
| GET | `/admin/modpacks/create` | AdminModpackController::create | ✅ | |
| POST | `/admin/modpacks/store` | AdminModpackController::store | ✅ | |
| GET | `/admin/modpacks/{id}/edit` | AdminModpackController::edit | ✅ | |
| POST | `/admin/modpacks/{id}/update` | AdminModpackController::update | ✅ | |
| POST | `/admin/modpacks/{id}/delete` | AdminModpackController::delete | ✅ | |
| GET | `/admin/atak-config` | AdminAtakConfigController::index | ✅ | Config ATAK |
| POST | `/admin/atak-config` | AdminAtakConfigController::store | ✅ | |

---

## 4. Options / pages manquantes ou partielles

### Complétées dans cette mise à jour

- **admin/recruitments** : route, contrôleur et vue liste des candidatures avec filtre par statut (submitted, reviewed, rejected). Le lien « Candidatures » du dashboard admin pointe maintenant vers une page fonctionnelle.

### Partiellement fait ou à compléter plus tard

| Élément | État | Suggestion |
|---------|------|------------|
| **Admin utilisateurs** | Pas de `edit` / `update` / `delete` | Ajouter GET `/admin/users/{id}/edit`, POST update, POST delete si besoin. |
| **Admin candidatures** | Liste seule, pas de détail ni changement de statut | Ajouter GET `/admin/recruitments/{id}` (détail) et POST pour accepter/rejeter + commentaire. |
| **Admin grades** | Aucune route dédiée | Gestion des grades actuellement via Configuration (lecture seule). Créer `admin/grades` (CRUD) si besoin. |
| **Admin panneaux personnel** | Aucune route dédiée | Idem, liste dans Configuration. Créer `admin/personnel-panels` pour CRUD si besoin. |
| **Config matricule** | Lecture seule sur Configuration | Ajouter formulaire d’édition (préfixe, format, prochain numéro) ou page dédiée. |
| **Tacmap** | Vue simple (HomeController::tacmap) | Enrichir avec carte / couches si nécessaire. |
| **Erreurs 404/500** | Vues présentes | S’assurer que le Router renvoie bien ces vues en cas d’erreur. |

### Redirections .htaccess

Les anciennes URLs `.html` sont redirigées vers les URLs propres (voir `public/.htaccess`). Ajouts : `equipement`, `documents`, `formations`, `modpacks`, `forum`, `compte`, `mot-de-passe-oublie`.

---

## 5. Résumé des fichiers modifiés / ajoutés

- **public/.htaccess** : redirections 301 étendues, Options -Indexes, en-têtes de sécurité.
- **routes/web.php** : ajout de `GET /admin/recruitments`.
- **app/Controllers/Admin/AdminRecruitmentsController.php** : nouveau.
- **views/admin/recruitments/index.php** : nouveau (liste + filtre statut).
- **app/Core/Container.php** : enregistrement de `AdminRecruitmentsController`.
- **docs/ROUTES.md** : cette feuille de route.

---

*Dernière mise à jour : feuille de route et complétion admin candidatures.*
