# Inventaire des fonctionnalités — Athena / COMSPEC MILSIM

Document de synthèse de l’état **actuel** du dépôt (code et routes). Pour la vision produit à long terme et un cahier des charges de type « communauté », voir [GUIDE-FONCTIONNEL-REFERENCE-COMMUNAUTE.md](GUIDE-FONCTIONNEL-REFERENCE-COMMUNAUTE.md) et [VISION-COMMUNAUTES-PREMIUM.md](VISION-COMMUNAUTES-PREMIUM.md).

**Sources principales** : [`routes/web.php`](../routes/web.php), [`migrations/schema.sql`](../migrations/schema.sql), migrations additionnelles dans `migrations/`, [`README-ATHENA.md`](../README-ATHENA.md), [`CONFIG.md`](../CONFIG.md).

---

## Architecture technique

| Élément | Description |
|--------|-------------|
| **Runtime** | PHP 8.4, application maison (Router, Request/Response, Container DI). |
| **Données** | MySQL / MariaDB ; schéma principal + scripts SQL incrémentaux (`run-migrations.php`). |
| **Front** | Vues PHP dans `views/`, assets dans `public/assets/`. |
| **Temps réel ATAK** | Service Node optionnel dans `server/` (carte / WebSocket selon déploiement). |
| **Auth** | Session ; utilisateur lié à un **tenant** (`tenant_id`). |

---

## Multi-tenant

- Table `tenants` : chaque enregistrement représente une instance logique de communauté / organisation côté données.
- Les utilisateurs (`users`) portent un `tenant_id` ; la session conserve `user_id`, `tenant_id`, `role_id`.
- **Limite actuelle** : pas d’interface publique « créer ma communauté » ni d’inscription multi-tenant self-service documentée dans les routes. Le déploiement type seed crée un tenant par défaut.
- Paramètres par tenant : table `site_settings` (clé/valeur), utilisée entre autres pour le forum.

---

## Authentification et compte

| Fonction | Routes / emplacement |
|----------|----------------------|
| Connexion / déconnexion | `GET/POST /login`, `POST /logout` (GuestMiddleware sur login). |
| Mot de passe oublié | `GET/POST /forgot-password`, `GET/POST /reset-password`. |
| Compte | `GET /account` — préférences, e-mail, avatar, portrait, mot de passe (`/account/*`). |
| Tableau de bord | `GET /dashboard`. |
| Hub applicatif | `GET /hub` — menu des modules accessibles selon les permissions ([`HubController`](../app/Controllers/Web/HubController.php)). |

Les permissions effectives sont chargées à chaque requête authentifiée via [`RbacService`](../app/Services/Rbac/RbacService.php) et injectées dans [`Gate`](../app/Core/Gate.php) (liste de slugs, ou `*`).

---

## Personnel, ORBAT et dossier opérationnel

| Fonction | Description |
|----------|-------------|
| **Fiches** | `GET /personnel/me`, `/personnel/me/edit`, `/personnel/{id}`, édition et mise à jour POST. |
| **Matricule** | `POST /personnel/{id}/generate-matricule` (logique métier [`MatriculeService`](../app/Services/Personnel/MatriculeService.php)). |
| **Notes** | `POST /personnel/{id}/notes`. |
| **ORBAT** | `GET /orbat` — arbre des unités (types configurables [`app/Config/units.php`](../app/Config/units.php)). |

Données étendues (migrations `personnel_dossier.sql` et associées) : `personnel_profiles`, qualifications par lignes, affectations, historique de service, médias de profil, etc. Le modèle **n’est pas** celui des « rangs à image PNG par groupe » décrit dans le guide de référence communautaire : voir référentiel de grades ci-dessous.

---

## Référentiel de grades (doctrine)

- Tables `grade_categories`, `grade_systems`, `grades_referentiel` ([`migrations/grade_referentiel.sql`](../migrations/grade_referentiel.sql)) : grades structurés par système (ex. FR/US), catégories, libellés courts/longs, ordre.
- Administration : `/admin/organization/referentiels/grades` (liste, création, édition, désactivation) — [`GradeReferentielController`](../app/Controllers/Admin/Organization/GradeReferentielController.php).

---

## Organisation : unités, groupes, équipes, catégories

- **Unités ORBAT** : gérées notamment via les routes legacy `POST /admin/units/*` ([`AdminUnitsController`](../app/Controllers/Admin/AdminUnitsController.php)) ; les GET `/admin/units` redirigent vers `/admin/organization/groups`.
- **Admin organisationnel** (middleware `OrganizationAdminMiddleware`, permissions `admin.organization` ou `admin.access`) :
  - Utilisateurs : CRUD sous `/admin/organization/users`.
  - Rôles : consultation `/admin/organization/roles`.
  - Catégories : `/admin/organization/categories`.
  - Groupes : `/admin/organization/groups`.
  - Équipes : `/admin/organization/teams`.

---

## Administration

| Niveau | Accès | Contenu principal |
|--------|--------|-------------------|
| **Hub admin** | `GET /admin` | [`AdminHubController`](../app/Controllers/Admin/AdminHubController.php) — choix entre administration système et organisation (redirection si un seul accès). |
| **Système** | `SystemAdminMiddleware` — `/admin/system/*` | Tableau de bord, rôles système, paramètres, audit. |
| **Organisation** | `OrganizationAdminMiddleware` — `/admin/organization/*` | Utilisateurs, rôles, catégories, référentiel grades, groupes, équipes. |
| **Legacy / transverse** | Auth requis | Configuration [`/admin/configuration`](../app/Controllers/Admin/AdminConfigurationController.php), recrutements `/admin/recruitments`, modpacks, ATAK config/mod, formations admin, forum-config. |

---

## Documents

| Parcours | Routes |
|----------|--------|
| **Lecture** (liste et détail par slug) | `GET /documents`, `GET /documents/{slug}`, téléchargement `GET /documents/{id}/download`, fichier `GET /documents/{id}/file`. |
| **Gestion** (permissions `documents.*`) | `GET /documents/gestion`, ajout, arborescence, fiche document, édition, nouvelle version, archivage, historique, gestion des accès. |

Contrôle d’accès documentaire : [`DocumentAccessService`](../app/Services/Documents/DocumentAccessService.php), tables de permissions et collaborations selon migrations `document_module_refactor.sql` et schéma.

---

## Formations (LMS)

| Parcours | Routes |
|----------|--------|
| **Apprenant** | `/formations`, `/formations/mes-formations`, `/formations/{slug}`, leçons, quiz, certificats. |
| **Pilotage communauté** | Canonique : `/back-office/ressources/training` (tableau de bord, catalogue, inscriptions, rapports, certificats, journal). Studio : `/back-office/ressources/training/studio`. Les **GET** `/admin/training/…` ne font que **rediriger** vers ces chemins ; les écritures (POST) passent par le back-office. |
| **API JSON** | Préfixe `/api/training/*` — catalogue, inscription, progression, quiz, certificats, endpoints admin cours / assignation. |

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

- `GET /equipment`, `GET /equipment/{slug}` — catalogue / fiches équipement ([`EquipmentController`](../app/Controllers/Web/EquipmentController.php)).

---

## Modpacks

- Consultation : `/modpacks`, détail par slug, images, téléchargement.
- Administration : `/admin/modpacks/*` (CRUD).

---

## Recrutement (enlistment)

- Public : `GET/POST /enlistment`, pages succès / erreur.
- Pages vitrine : `/recrutement`, `/equipement` (HomeController).
- Admin : liste des candidatures `/admin/recruitments`.

---

## ATAK, TACMAP, Overwatch et API C2

| Zone | Description |
|------|-------------|
| **Pages web** | `/atak`, `/atak/setup`, `/atak/tuto`, téléchargement mod ATAK ; `/tacmap` ; `/overwatch` (vue commandement / santé / couches). |
| **Config admin** | `/admin/atak-config`, `/admin/atak-mod`. |
| **API REST** | Nombreux endpoints sous `/api/*` : marqueurs, unités temps réel, chat, pings, nine-line, CAS, reconnaissance, formes carte, codes laser, manifeste vol, designator, SIGINT, intel photos, fire-support, danger zones, logistics, intel fusion, replay, IFF, health. |

Certaines routes sont utilisées par le client carte (JS) et/ou par le mod Arma ; le tenant peut être forcé via session ou variables d’environnement pour l’ATAK.

---

## Déploiement et exploitation

- Installation, `.env`, document root : [`README-ATHENA.md`](../README-ATHENA.md).
- Maintenance, unités ORBAT côté config : [`CONFIG.md`](../CONFIG.md).

---

## Synthèse : fonctionnalités distinctives

Athena combine **gestion RH / milsim** (personnel, ORBAT, grades référentiels, recrutement), **LMS**, **forum**, **documents avancés**, **courrier officiel**, **modpacks**, et une **couche C2 / ATAK** importante (API + pages tactiques). Les modules type « événements calendrier », « campagnes », « analytics de présence », « alertes globales », « intégration Discord » ou « rangs entièrement graphiques par communauté » **ne sont pas couverts par ce inventaire** comme modules dédiés équivalents au guide de référence communautaire — voir le guide et la vision premium pour le positionnement produit.

---

*Document généré pour refléter la structure du dépôt ; en cas de divergence, `routes/web.php` fait foi.*
