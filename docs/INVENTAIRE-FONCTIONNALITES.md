# Inventaire des fonctionnalités — Athena / COMSPEC MILSIM

Document de synthèse de l’état **actuel** du dépôt (code et routes). Pour la vision produit à long terme et un cahier des charges de type « communauté », voir [GUIDE-FONCTIONNEL-REFERENCE-COMMUNAUTE.md](GUIDE-FONCTIONNEL-REFERENCE-COMMUNAUTE.md) et [VISION-COMMUNAUTES-PREMIUM.md](VISION-COMMUNAUTES-PREMIUM.md).

**Sources principales** : `[routes/web.php](../routes/web.php)`, `[migrations/schema.sql](../migrations/schema.sql)`, migrations additionnelles dans `migrations/`, `[README-ATHENA.md](../README-ATHENA.md)`, `[CONFIG.md](../CONFIG.md)`.

---

## Architecture technique


| Élément             | Description                                                                           |
| ------------------- | ------------------------------------------------------------------------------------- |
| **Runtime**         | PHP 8.4, application maison (Router, Request/Response, Container DI).                 |
| **Données**         | MySQL / MariaDB ; schéma principal + scripts SQL incrémentaux (`run-migrations.php`). |
| **Front**           | Vues PHP dans `views/`, assets dans `public/assets/`.                                 |
| **Temps réel ATAK** | Service Node optionnel dans `server/` (carte / WebSocket selon déploiement).          |
| **Auth**            | Session ; utilisateur lié à un **tenant** (`tenant_id`).                              |


---

## Multi-tenant

- Table `tenants` : chaque enregistrement représente une instance logique de communauté / organisation côté données.
- Les utilisateurs (`users`) portent un `tenant_id` ; la session conserve `user_id`, `tenant_id`, `role_id`.
- **Limite actuelle** : pas d’interface publique « créer ma communauté » ni d’inscription multi-tenant self-service documentée dans les routes. Le déploiement type seed crée un tenant par défaut.
- Paramètres par tenant : table `site_settings` (clé/valeur), utilisée entre autres pour le forum.

---

## Authentification et compte


| Fonction                | Routes / emplacement                                                                                                           |
| ----------------------- | ------------------------------------------------------------------------------------------------------------------------------ |
| Connexion / déconnexion | `GET/POST /login`, `POST /logout` (GuestMiddleware sur login).                                                                 |
| Mot de passe oublié     | `GET/POST /forgot-password`, `GET/POST /reset-password`.                                                                       |
| Compte                  | `GET /account` — préférences, e-mail, avatar, portrait, mot de passe (`/account/`*).                                           |
| Tableau de bord         | `GET /dashboard`.                                                                                                              |
| Hub applicatif          | `GET /hub` — menu des modules accessibles selon les permissions (`[HubController](../app/Controllers/Web/HubController.php)`). |


Les permissions effectives sont chargées à chaque requête authentifiée via `[RbacService](../app/Services/Rbac/RbacService.php)` et injectées dans `[Gate](../app/Core/Gate.php)` (liste de slugs, ou `*`).

---

## Personnel, ORBAT et dossier opérationnel


| Fonction                                | Description                                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| --------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Fiches**                              | `GET /personnel/me`, `/personnel/me/edit`, `/personnel/{id}`, édition et mise à jour POST.                                                                                                                                                                                                                                                                                                                                                                        |
| **Matricule**                           | `POST /personnel/{id}/generate-matricule` (logique métier `[MatriculeService](../app/Services/Personnel/MatriculeService.php)`).                                                                                                                                                                                                                                                                                                                                  |
| **Notes**                               | `POST /personnel/{id}/notes`.                                                                                                                                                                                                                                                                                                                                                                                                                                     |
| **ORBAT (page)**                        | `GET /orbat` — arbre des unités (`[PersonnelController::orbat](../app/Controllers/Web/PersonnelController.php)`), types dans `[app/Config/units.php](../app/Config/units.php)`. Accès : permission « Consulter l’ORBAT » (`organization.orbat.view`) ; liens de menu conditionnés dans `[config/navigation.php](../config/navigation.php)`.                                                                                                                       |
| **ORBAT (échanges avec le navigateur)** | Préfixe `/api/orbat/`* (`[OrbatApiController](../app/Controllers/Api/OrbatApiController.php)`) : effectifs, options de structure, type d’organigramme, médias d’unité, enregistrement de la structure et des fiches unité — consommé par la page ORBAT (`[views/personnel/orbat.php](../views/personnel/orbat.php)`). Les actions de modification reprennent le périmètre « Gérer la structure ORBAT » (`organization.orbat.manage`) là où le contrôleur l’exige. |


Données étendues (migrations `personnel_dossier.sql` et associées) : `personnel_profiles`, qualifications par lignes, affectations, historique de service, médias de profil, etc. Le modèle **n’est pas** celui des « rangs à image PNG par groupe » décrit dans le guide de référence communautaire : voir référentiel de grades ci-dessous.

---

## Mur opérationnel

- **Consultation** : `GET /tableau-operationnel` — portail « Mur opérationnel » ; accès via `[OperationalBoardViewMiddleware](../app/Middleware/OperationalBoardViewMiddleware.php)` (permission « Consulter le tableau opérationnel » `operational.board.view`, ou pilotage / rôles d’administration listés dans le middleware).
- **Pilotage** : routes `GET` / `POST` sous `/back-office/tableau-operationnel` (liste, création, fiches, validation, statuts opérationnels, FRAGO, checklist, **retrait de publication**, duplication, modèle, posture, publication liée, flux temps réel). Accès via `[OperationalBoardEditMiddleware](../app/Middleware/OperationalBoardEditMiddleware.php)` (`operational.board.edit` ou administrateurs).
- **Catalogue des permissions** : entrées `operational.board.`* dans `[TenantPermissionCatalog](../app/Authorization/TenantPermissionCatalog.php)`.
- **Interface** : vues `[views/operations/board_portal.php](../views/operations/board_portal.php)`, `[board.php](../views/operations/board.php)`, `[board_entry_form.php](../views/operations/board_entry_form.php)`.

---

## Référentiel de grades (doctrine)

- Tables `grade_categories`, `grade_systems`, `grades_referentiel` (`[migrations/grade_referentiel.sql](../migrations/grade_referentiel.sql)`) : grades structurés par système (ex. FR/US), catégories, libellés courts/longs, ordre.
- Administration : `/admin/organization/referentiels/grades` (liste, création, édition, désactivation) — `[GradeReferentielController](../app/Controllers/Admin/Organization/GradeReferentielController.php)`.

---

## Organisation : unités, groupes, équipes, catégories

- **Unités ORBAT** : gérées notamment via les routes legacy `POST /admin/units/`* (`[AdminUnitsController](../app/Controllers/Admin/AdminUnitsController.php)`) ; les GET `/admin/units` redirigent vers `/admin/organization/groups`.
- **Admin organisationnel** (middleware `OrganizationAdminMiddleware`, permissions `admin.organization` ou `admin.access`) :
  - Utilisateurs : CRUD sous `/admin/organization/users`.
  - Rôles : consultation `/admin/organization/roles`.
  - Catégories : `/admin/organization/categories`.
  - Groupes : `/admin/organization/groups`.
  - Équipes : `/admin/organization/teams`.

---

## Administration


| Niveau                  | Accès                                                   | Contenu principal                                                                                                                                                                                                                              |
| ----------------------- | ------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Hub admin**           | `GET /admin`                                            | `[AdminHubController](../app/Controllers/Admin/AdminHubController.php)` — choix entre administration système et organisation (redirection si un seul accès).                                                                                   |
| **Système**             | `SystemAdminMiddleware` — `/admin/system/`*             | Tableau de bord, rôles système, paramètres, audit.                                                                                                                                                                                             |
| **Organisation**        | `OrganizationAdminMiddleware` — `/admin/organization/`* | Utilisateurs, rôles, catégories, référentiel grades, groupes, équipes.                                                                                                                                                                         |
| **Legacy / transverse** | Auth requis                                             | Configuration `[/admin/configuration](../app/Controllers/Admin/AdminConfigurationController.php)`, recrutements (redirect `GET /admin/recruitments` → `/back-office/recruitments`), modpacks, ATAK config/mod, formations admin, forum-config. |


---

## Documents


| Parcours                                | Routes                                                                                                                              |
| --------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------- |
| **Lecture** (liste et détail par slug)  | `GET /documents`, `GET /documents/{slug}`, téléchargement `GET /documents/{id}/download`, fichier `GET /documents/{id}/file`.       |
| **Gestion** (permissions `documents.`*) | `GET /documents/gestion`, ajout, arborescence, fiche document, édition, nouvelle version, archivage, historique, gestion des accès. |


Contrôle d’accès documentaire : `[DocumentAccessService](../app/Services/Documents/DocumentAccessService.php)`, tables de permissions et collaborations selon migrations `document_module_refactor.sql` et schéma.

---

## Formations (LMS)


| Parcours                | Routes                                                                                                                                                                                                                                                                                                       |
| ----------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| **Apprenant**           | `/formations`, `/formations/mes-formations`, `/formations/{slug}`, leçons, quiz, certificats.                                                                                                                                                                                                                |
| **Pilotage communauté** | Canonique : `/back-office/ressources/training` (tableau de bord, catalogue, inscriptions, rapports, certificats, journal). Studio : `/back-office/ressources/training/studio`. Les **GET** `/admin/training/…` ne font que **rediriger** vers ces chemins ; les écritures (POST) passent par le back-office. |
| **API JSON**            | Préfixe `/api/training/`* — catalogue, inscription, progression, quiz, certificats, endpoints admin cours / assignation.                                                                                                                                                                                     |


Schéma : `migrations/lms_training.sql` et tables associées.

---

## Forum (Briefing)

- Pages : index, catégorie par slug, sujet, nouveau sujet, réponses, abonnements sujet.
- Modération : `/forum/moderation` (middleware `ForumModerateMiddleware`) — signalements, épinglage, verrouillage.
- API : `GET/POST /api/forum`, modération forum, upload ; endpoints admin catégories forum et paramètres site (`/api/admin/forum-categories`, `/api/admin/site-settings`, etc.).

---

## Courrier (correspondance officielle)

- Espace dédié sous `/courrier/*` : tableau de bord, éditeur, lecture, templates, presets, workflow, signatures (y compris vérification publique partielle), PDF/impression, historique, archives.
- Voir contrôleurs dans `app/Controllers/Courrier/`.

---

## Équipement

- `GET /equipment`, `GET /equipment/{slug}` — catalogue / fiches équipement (`[EquipmentController](../app/Controllers/Web/EquipmentController.php)`).

---

## Modpacks

- Consultation : `/modpacks`, détail par slug, images, téléchargement.
- Administration : `/admin/modpacks/*` (CRUD).

---

## Recrutement (enlistment)

- Public : `GET/POST /enlistment`, pages succès / erreur.
- Pages vitrine : `/recrutement`, `/equipement` (HomeController).
- Administration des candidatures : `/back-office/recruitments` (liste, fiche, décision, finalisation d’adhésion, messages prêts à l’emploi) — `GET /admin/recruitments` redirige vers ce chemin. Offres et paramètres : `/back-office/recruitment/offers/*`, format de référence sous `/back-office/recruitment/reference-format`.

---

## ATAK, TACMAP, Overwatch et API C2


| Zone             | Description                                                                                                                                                                                                                                                         |
| ---------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Pages web**    | `/atak`, `/atak/setup`, `/atak/tuto`, téléchargement mod ATAK ; `/tacmap` ; `/overwatch` (vue commandement / santé / couches).                                                                                                                                      |
| **Config admin** | `/admin/atak-config`, `/admin/atak-mod`.                                                                                                                                                                                                                            |
| **API REST**     | Nombreux endpoints sous `/api/`* : marqueurs, unités temps réel, chat, pings, nine-line, CAS, reconnaissance, formes carte, codes laser, manifeste vol, designator, SIGINT, intel photos, fire-support, danger zones, logistics, intel fusion, replay, IFF, health. |


Certaines routes sont utilisées par le client carte (JavaScript embarqué dans les pages) et/ou par le mod Arma ; le tenant peut être forcé via session ou variables d’environnement pour l’ATAK.

**Couverture côté navigateur (extrait)** — repères dans `public/assets/js/` (et miroirs éventuels sous `app/assets/js/`) :


| Fichier (repère)                                   | Préfixes d’URL appelés                        |
| -------------------------------------------------- | --------------------------------------------- |
| `atak-map.js`                                      | `/api/atak/markers`, `/api/atak/sigint/zones` |
| `atak-units.js`                                    | `/api/units`                                  |
| `atak-air-assets.js`                               | `/api/atak/air-assets`                        |
| `atak-chat.js`                                     | `/api/chat`                                   |
| `atak-pings.js`                                    | `/api/pings`                                  |
| `atak-jtac.js`                                     | `/api/cas`, `/api/nine-line`                  |
| `atak-cams.js`                                     | `/api/recon/images`, `/api/intel/photos`      |
| `atak-laser-codes.js`                              | `/api/atak/laser-codes`                       |
| `atak-map-shapes.js`, `comspec-operational-map.js` | `/api/map-shapes`                             |
| `training_quiz_player.js`                          | `/api/training/quiz/`*                        |


Les vues **forum** et **configuration forum** appellent aussi `/api/forum`, `/api/forum-upload`, `/api/forum-moderation`, `/api/admin/`*, etc. La page **ORBAT** consomme `/api/orbat/`* (voir section Personnel / ORBAT). Tout endpoint listé dans `routes/web.php` sans occurrence dans ces inventaires peut encore être utilisé par un autre client (module jeu, outil externe, script) : traiter comme « non couvert par le JS livré dans le dépôt » plutôt que comme mort.

---

## Déploiement et exploitation

- Installation, `.env`, document root : `[README-ATHENA.md](../README-ATHENA.md)`.
- Maintenance, unités ORBAT côté config : `[CONFIG.md](../CONFIG.md)`.

---

## Matrice synthétique (modules principaux)

Vue condensée **parcours × droits × API × navigation** (les détails restent dans `[ROUTES.md](ROUTES.md)` et `[TenantPermissionCatalog](../app/Authorization/TenantPermissionCatalog.php)`). « R » = consultation, « W » = création / mise à jour / suppression selon le module.


| Domaine                | Parcours principal                                           | R                   | W              | API / temps réel                  | Menu / hub (repères)          | Permissions (slugs)                                    |
| ---------------------- | ------------------------------------------------------------ | ------------------- | -------------- | --------------------------------- | ----------------------------- | ------------------------------------------------------ |
| Mur opérationnel       | `/tableau-operationnel`, `/back-office/tableau-operationnel` | ✅ portail           | ✅ back-office  | SSE `…/stream`                    | Mur opérationnel, Pilotage    | `operational.board.view`, `operational.board.edit`     |
| ORBAT                  | `/orbat`                                                     | ✅                   | ✅ (structure)  | `/api/orbat/`*                    | ORBAT, Organisation (ORBAT)   | `organization.orbat.view`, `organization.orbat.manage` |
| Personnel (hors ORBAT) | `/personnel/*`                                               | ✅                   | ✅ fiche        | —                                 | Dossier personnel, fiches     | Selon routes (ex. édition fiche)                       |
| Documents              | `/documents`, `/documents/gestion`                           | ✅ lecture           | ✅ gestion      | —                                 | Module documents              | `documents.*`                                          |
| Formations             | `/formations`, `/back-office/ressources/training`            | ✅                   | ✅ pilotage     | `/api/training/*`                 | Formations, Studio / pilotage | Rôles formation + catalogue LMS                        |
| Forum                  | `/forum/*`                                                   | ✅                   | ✅ modération   | `/api/forum*`, uploads            | Briefing                      | Modération, droits forum                               |
| Courrier               | `/courrier/*`                                                | ✅                   | ✅              | —                                 | Courrier                      | Selon politique courrier                               |
| Recrutement            | `/enlistment`, `/back-office/recruitments`                   | ✅ dépôt candidature | ✅ traitement   | —                                 | Recrutement (admin)           | `OrganizationAdminMiddleware` côté back-office         |
| ATAK / C2              | `/atak`, `/tacmap`, `/overwatch`                             | ✅                   | ✅ config admin | `/api/atak/*`, `/api/units`, etc. | TACMAP, ATAK                  | Accès pages + rôles admin ATAK                         |


---

## Synthèse : fonctionnalités distinctives

Athena combine **gestion RH / milsim** (personnel, ORBAT, grades référentiels, recrutement), **LMS**, **forum**, **documents avancés**, **courrier officiel**, **modpacks**, **mur opérationnel**, et une **couche C2 / ATAK** importante (API + pages tactiques). Les modules type « événements calendrier », « campagnes », « analytics de présence », « alertes globales », « intégration Discord » ou « rangs entièrement graphiques par communauté » **ne sont pas couverts par ce inventaire** comme modules dédiés équivalents au guide de référence communautaire — voir le guide et la vision premium pour le positionnement produit.

---

*Document généré pour refléter la structure du dépôt ; en cas de divergence, `routes/web.php` fait foi. Dernière extension : mur opérationnel, ORBAT (page + `/api/orbat`), recrutements back-office, matrice et couverture JS carte / quiz.*