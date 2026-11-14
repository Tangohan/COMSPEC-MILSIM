# Audit du back-office ATHENA : synthèse et restructuration

Ce dossier rassemble la synthèse de cinq audits de domaine du back-office ATHENA (site PHP sur mesure, interface en français, communautés qui utilisent COMSPEC ATAK et Overwatch). C'est un travail d'audit seulement : aucun fichier applicatif, aucune route, aucune base et aucune migration n'a été modifié.

## Sommaire

| N° | Document | Contenu | Fichier |
|---|---|---|---|
| 0 | Lisez-moi | Méthode, conventions, points de sécurité à traiter en premier | `README.md` |
| 1 | Inventaire consolidé | Toutes les pages relevées, par domaine : nom, routes, place dans le menu, objectif, droits, dépendances | [`01-inventaire.md`](01-inventaire.md) |
| 2 | Matrice des redondances | Un tableau unique, numéroté et trié par intérêt | [`02-matrice-redondances.md`](02-matrice-redondances.md) |
| 3 | Architecture cible | Arborescence actuelle résumée, puis arborescence cible en trois niveaux d'usage, menu « cadre », justification de chaque choix | [`03-architecture-cible.md`](03-architecture-cible.md) |
| 4 | Parcours | Douze parcours, avant et après, avec le nombre d'étapes | [`04-parcours.md`](04-parcours.md) |
| 5 | Plan de restructuration | Feuille de route par lots : sécurité, améliorations rapides, regroupements, refontes, mutualisations, droits, migration, tests | [`05-plan-restructuration.md`](05-plan-restructuration.md) |
| 6 | Matrice de conservation | Chaque fonctionnalité existante reliée à son emplacement cible | [`06-matrice-conservation.md`](06-matrice-conservation.md) |
| 7 | Maquettes textuelles | Menus par profil, fiche membre, espace unité, dossier d'opération, configuration ATAK, accueil | [`07-maquettes.md`](07-maquettes.md) |

## Méthode

| Point | Détail |
|---|---|
| Sources | Cinq audits de domaine, écrits en lecture du code : 01 Personnel et effectifs, 02 Accès et permissions, 03 Opérations et outils tactiques, 04 Organisation et plateforme, 05 Navigation et interface. Plus la liste des pages hors menu (`orphans.txt`, produite par l'audit 05). |
| Code lu | Checkout détaché de `main` au commit `9f2ce9033` (dossier `.worktrees/audit-main`). Les vérifications ponctuelles de cette synthèse ont été faites sur `main` au commit `5fd92b968` (dossier `.worktrees/audit-docs`). |
| Ce qui n'a pas été fait | Aucun test en ligne, aucune exécution du site, aucune requête en base, aucune lecture de journaux d'accès. |
| Fréquences d'usage | Aucune mesure d'usage n'a été lue. **Toute fréquence citée (« quotidien », « rare »…) est une HYPOTHÈSE.** Les nombres d'étapes des parcours sont comptés sur le code, pas chronométrés. |
| Numéros de ligne | Ils renvoient au commit audité `9f2ce9033`. Sur `main` `5fd92b968`, deux fichiers ont bougé : `routes/web.php` a une ligne de plus après la ligne 2328 (donc `web.php:2563` devient 2564), et `app/Controllers/Admin/EffectifsWorkspaceController.php` a 2 lignes de plus après la ligne 3496 et 50 après la ligne 3502 (donc 4193 devient 4243 et 4284 devient 4334). |
| Identifiants | Chaque page reçoit un identifiant stable repris dans tous les documents : `PER-Fxx` (audit 01), `ACC-Fxx` (audit 02), `O-`, `A-`, `T-`, `J-`, `R-`, `I-`, `E-` (audit 03), `ORG-xx` (audit 04), `NAV-xx` (pages relevées seulement par l'audit 05 ou par `orphans.txt`). |

## Conventions

| Étiquette | Sens | Exemple |
|---|---|---|
| **FAIT** | Lu dans le code, avec le fichier et la ligne. Repris d'un audit de domaine, ou vérifié de nouveau pour cette synthèse (mention « revérifié »). | `views/partials/back_office_sidebar.php:13-15` : le menu de gestion n'apparaît qu'avec `admin.organization`, `admin.access` ou `site.support`. |
| **HYPOTHÈSE** | Déduit, non mesuré, non exécuté. | « Un recruteur n'atteint ses pages que par URL directe. » |
| **PROPOSITION** | Choix de conception proposé par cette synthèse. À arbitrer par le produit. | « Une fiche membre centrale à onglets. » |

Abréviations des contrôles d'accès, reprises des audits :

| Abréviation | Contrôle | Référence |
|---|---|---|
| ORG | `OrganizationAdminMiddleware` : `admin.organization`, `admin.access` ou `site.support`, plus des exceptions par préfixe de chemin | `app/Middleware/OrganizationAdminMiddleware.php:22-156` |
| RES | `TenantResourceAdminMiddleware` : `admin.system`, `admin.organization` ou `admin.access` | `app/Middleware/TenantResourceAdminMiddleware.php:27` |
| SYS | `SystemAdminMiddleware` : `admin.system` | `app/Middleware/SystemAdminMiddleware.php:21-27` |
| Auth | `AuthMiddleware` seul : tout membre connecté | `app/Middleware/AuthMiddleware.php` |
| ELA | `EffectifsLmsAccess::*` : contrôles fins du bureau des effectifs | `app/Support/EffectifsLmsAccess.php:14-111` |

## Points de sécurité à vérifier en priorité

> Ces points ont été **tracés dans le code, pas exécutés**. Aucun n'a été reproduit sur un site en ligne. Chacun doit être confirmé par un test (compte de test, tenant de test) avant correction. Le lot 0 du plan ([`05-plan-restructuration.md`](05-plan-restructuration.md)) les reprend un par un.

| N° | Faille | Ce que dit le code | Référence (source) | Statut | Gravité supposée |
|---|---|---|---|---|---|
| S1 | **JNET montre les dossiers de renseignement SSE sans filtre** | Les pages Renseignement, Cibles et Exploitation de l'extranet lisent `sse_interest_cases`, la liste de surveillance, les événements de renseignement et les fiches terrain avec `listForTenant` (aucune ACL, aucune classification). Le portail SSE filtre au contraire avec `listVisibleForUser`. Toutes les routes JNET sont en Auth seul, et `ensureAuth()` ne vérifie aucun droit. | `app/Services/Jnet/JnetDashboardService.php:700` (revérifié), `:208-216`, `:1015`, `:1031` ; `routes/web.php:473-503` ; `app/Controllers/Web/SsePortalController.php:421` (audit 03, J-05 à J-07, N1) | FAIT dans le code ; impact réel selon les données | Critique |
| S2 | **API de la carte tactique en écriture pour tout membre** | `GET/POST/PATCH/DELETE /api/athena/tactical/markers` n'ont aucun middleware de route. `isWeb()` considère comme client web toute session qui a un `tenant_id`. Tout membre connecté peut donc créer, modifier et supprimer des marqueurs. CSRF non vu. | `routes/web.php:3121-3125` (revérifié) ; `app/Controllers/Api/AthenaTacticalApiController.php:163-166` (revérifié) (audit 03, A-02, N2) | FAIT pour l'absence de garde ; HYPOTHÈSE pour le CSRF | Élevée |
| S3 | **`GET /back-office/atak/roleplay/reset` sans CSRF** | Une route GET remet à zéro la configuration du mode roleplay ATAK. Un simple lien, une image ou un préchargement du navigateur suffit à la déclencher pour un administrateur connecté. | `routes/web.php:2563` (2564 sur `5fd92b968`, revérifié) ; `app/Controllers/Admin/AdminAtakRoleplayController.php:307` (revérifié) ; appelée depuis `views/admin/atak/roleplay.php:15` (audit 03, A-05, N4 ; audit 05, U6) | FAIT | Moyenne |
| S4 | **Élévation de droits par le profil RH, via l'approbation d'une élévation** | La décision sur une demande d'élévation accepte `canManageRoles` **ou** `canManageStatus`. Le profil RH a `personnel.status.manage`. L'approbation applique n'importe quel niveau attribuable et n'importe quelle permission non réservée à la plateforme, y compris `admin.organization` ou `admin.roles.manage`. Le registre et l'éditeur des niveaux exigent au contraire `canManageRoles`. Seule l'auto-attribution est bloquée. | `EffectifsWorkspaceController.php:4193, 4284` (4243 et 4334 sur `5fd92b968`, revérifié) ; `app/Support/EffectifsLmsAccess.php:38-43` (revérifié) ; `app/Services/Rbac/CommunityAccessProfiles.php:383` (revérifié) ; `app/Services/Effectifs/ElevationApprovalService.php:360-478, 366-372` (audit 02, F05, constat 5.1) | FAIT pour le chemin ; HYPOTHÈSE forte pour l'effet (non exécuté) | Élevée |
| S5 | **`/c/{slug}/setup` sans contrôle de droit** | Ancien assistant post-création : il suffit que `switchToTenant` réussisse. N'importe quel membre peut changer le fuseau horaire de la communauté et le drapeau `onboarding_completed_at`. Page sans lien entrant. | `routes/web.php:389-390` (revérifié, Auth seul) ; `app/Controllers/Web/CommunityController.php:1807-1892`, `:1862`, `:1871-1875` (audit 04, A3) | FAIT | Moyenne |
| S6 | **Paramètres de la communauté sans contrôle interne** | `OrganizationSettingsController` (1 148 lignes) n'appelle jamais `allows()`, y compris `updateType`, qui change le profil de communauté et retire des droits aux niveaux Membre et RH. Seul ORG protège, et ORG laisse passer `site.support`. Même constat pour `TenantInitialSetupController`, `OrganizationCommunityController`, `TenantAlertsController`, `TenantMiniArticlesController`. | `app/Controllers/Admin/Organization/OrganizationSettingsController.php:191-253` (0 occurrence de `allows(` revérifiée) ; `app/Middleware/OrganizationAdminMiddleware.php:22-23` (audit 04, §0, B1, R6) | FAIT pour l'absence de contrôle ; HYPOTHÈSE pour l'usage par un compte d'assistance | Moyenne |
| S7 | **Middlewares d'audit et `AccessControlMiddleware` non branchés** | `TenantAdminAuditMiddleware` (journal systématique des POST) et `AccessControlMiddleware` (règles d'accès avancées) ne sont attachés à aucune route ni à la pile globale. Les règles enregistrées dans « Gestion des accès » ne sont jamais appliquées, et l'audit dépend de chaque contrôleur. L'API `/api/access-control/*` est en Auth seul ; ses GET répondent à tout membre connecté. | 0 occurrence des deux noms dans `routes/web.php` (revérifié) ; absents de `app/Core/Application.php:42-91` (revérifié) ; `routes/web.php:3031-3042` ; `AccessControlApiController.php:50-58, 70-77` (audit 02, F11, F16) | FAIT | Moyenne (fausse impression de sécurité) |
| S8 | **Droits de qualification jamais lus** | À l'obtention d'une qualification, la permission liée est écrite dans `user_direct_permissions` ou `tenant_user_permissions`. Aucune de ces tables n'est lue par `RbacService` ni créée par une migration. Le réglage « droits liés » n'a aucun effet. | `app/Services/Personnel/QualificationPermissionGrantService.php:95-128` (seul fichier qui cite ces tables, revérifié) ; `RbacService.php` ne lit que `user_permission_overrides` (`:358-365`, revérifié) (audit 02, F22) | FAIT | Faible en sécurité, élevée en confiance (un administrateur croit avoir donné un droit) |

Points de sécurité secondaires, relevés par les audits et repris dans le lot 0 :

| N° | Point | Référence | Statut |
|---|---|---|---|
| S9 | Ancienne route `POST /back-office/users/{id}/duty-position` sans contrôle fin (seul ORG, donc `site.support`) | `UserAdminController.php:1386-1441` (audit 01, F45) | FAIT |
| S10 | Téléchargement des documents RH ouvert à tout porteur de l'accès effectifs, pour tout document du tenant | `RhDossierWorkspaceController` 238-253 (audit 01, F27, rapport délégué) | HYPOTHÈSE forte |
| S11 | `AwardReferentielController::grant` : pas de vérification que le membre et la décoration appartiennent au tenant avant insertion | `AwardReferentielController.php:291-334` (audit 01, F31) ; relu pour cette synthèse : aucune vérification visible entre 293 et 314 | HYPOTHÈSE |
| S12 | Onboarding d'une recrue : `organization.recruitment.manage` suffit pour fixer grade, unité et niveau d'accès | `AdminRecruitmentsController` 941, 1066, 1093-1097 (audit 01, F21) | HYPOTHÈSE forte |
| S13 | API tactiques (appui-feu, logistique, renseignement, relecture, zones dangereuses, terrain) sans middleware de route ; seul le périmètre du tenant est vérifié | `routes/web.php:3603-3657` ; `TacticalMissionScope` (audit 03, N3) | HYPOTHÈSE |
| S14 | `POST /api/athena/v2/dev/seed` exposé à tout administrateur de communauté | `routes/web.php:3086` sur `5fd92b968` (revérifié) (audit 03, N16) | FAIT pour l'exposition ; effet non lu |
| S15 | Flux d'agenda ICS et lien public du tableau opérationnel sans authentification (jeton seul) | `routes/web.php:516, 542` (audit 03, O-06, O-13) | FAIT ; probablement voulu |
| S16 | GET qui écrivent en base : matricules et codes d'unité au rendu du tableur, `completeForTenant` des grades, index réalisme et onglet réglages | `EffectifsWorkspaceController.php:205-206, 440` ; `GradeReferentielController` ; `AdminAtakRealismController` (audits 01 et 03, N5) | FAIT |

## Travaux déjà engagés

Depuis la fin des audits, quatre changements sont en cours de revue (PR non fusionnées). Ils sont signalés comme « déjà engagé (PR #1600, #1601, #1602) » dans le plan et dans les matrices.

| Changement | Effet sur les constats |
|---|---|
| Registre des droits : une ligne par membre ; la position administrative n'est plus une ligne en double | Traite la cause d'affichage du doublon (audit 01 F44, audit 02 §3.2). Le stockage de la position comme rôle RBAC reste à traiter (lot 3). |
| Positions administratives personnalisées par communauté (nouvelle page Effectifs › Positions administratives) | Rend caduc le constat « une communauté ne peut pas créer ses positions » (audit 02 §3.4). La page devient un référentiel dans l'architecture cible. |
| Accueil du back-office : un titre par carte, plus de surtitres | Applique la règle « pas de surtitre » sur l'accueil (audit 05 §4.4). Le reste du shell garde ses kickers (141 pages). |
| « Mon service » ajouté au menu du back-office | Traite le constat « Mon service n'est pas accessible depuis le back-office » (audit 02 F17, §4.2). |

## Limites

| Limite | Conséquence |
|---|---|
| Rendu non exécuté | Les doubles titres, débordements mobiles et doubles messages flash sont des HYPOTHÈSES (audit 05, U2 à U4). |
| Contrôles internes non relus partout | Plusieurs POST n'ont été lus que par leur route (liste dans l'audit 01 §4 et l'audit 02 §5). |
| Mod et application téléphone non lus | On ne sait pas quel réglage le jeu applique quand trois stockages se contredisent (audit 03, N6). |
| Pas de données d'usage | Le tri « courant / approfondi / avancé » de l'architecture cible repose sur la nature des tâches. Il faut le valider avec les indicateurs d'usage par page (`back-office/analytics`) et la table `platform_usage`. |
