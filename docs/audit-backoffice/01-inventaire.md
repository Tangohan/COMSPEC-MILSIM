# Document 1 : inventaire consolidé des pages

Ce document recense toutes les pages relevées par les cinq audits et par `orphans.txt`. Il donne pour chacune : le nom, les routes, la place dans le menu (ou « hors menu »), l'objectif, les droits et les dépendances. Le détail (données, actions, personnalisation, complexité) reste dans la fiche de l'audit source, citée en dernière colonne.

Conventions :
- Les routes sont sans le préfixe de domaine. `BO` = `/back-office`. `EFF` = `/back-office/ressources/effectifs`.
- « Menu » désigne la barre latérale du back-office (`views/partials/ath_sidebar_nav.php`, abrégé `NAV:ligne`). « Opérateur » = menu des membres et cadres délégués (`NAV:421-529`). « Méga-menu » = menu du portail (`config/navigation.php`). « Plateforme » = barre `/admin` (`views/partials/platform_admin_sidebar.php`, abrégé `PA:ligne`).
- Abréviations des droits : voir le [README](README.md#conventions). Toutes les colonnes sont des FAIT tirés des audits, sauf mention HYPOTHÈSE.
- Fréquences : non mesurées (voir les audits).
- Sources : `01` = `audit/01-personnel.md`, `02` = `02-acces.md`, `03` = `03-operations.md`, `04` = `04-organisation.md`, `05` = `05-navigation-ui.md`.

## Volumétrie

| Domaine | Pages ou familles inventoriées |
|---|---|
| 1. Personnel et effectifs | 45 fiches (PER-F01 à F45) + 10 écrans morts (PER-H1 à H10) |
| 2. Accès et gouvernance | 26 fiches (ACC-F01 à F26), dont 13 renvoient à une fiche d'un autre domaine |
| 3. Opérations, ATAK, terrain, JNET, renseignement, identité, équipement | 86 fiches + 18 écrans secondaires et alias (O-S, T-S, E-S, J-A, I-A, A-16e) + 11 domaines d'API sans écran |
| 4. Organisation, communications, plateforme | 44 fiches de communauté (A, B, C, D, E, F, G, I, J, K) + 35 écrans plateforme (H1 à H35) |
| 5. Pages relevées par la navigation seule | 41 entrées (NAV-01 à NAV-41) |
| Contrôle `orphans.txt` | 102 chemins, tous rattachés (annexe A) |

Selon l'audit 05 (§1.3, §2.2) : 137 liens de page dans le menu de gestion, 22 dans le menu opérateur, 51 dans la barre plateforme ; 268 pages GET statiques sous `/back-office` et `/admin`, dont 193 dans un menu et 75 hors menu (71 liées depuis une vue, 4 sans lien).

---

## 1. Personnel et effectifs (audit 01)

### 1.1 Liste et fiche des membres

| ID | Nom | Route(s) | Menu | Objectif | Droits | Dépendances | Fiche |
|---|---|---|---|---|---|---|---|
| PER-F01 | Tableur des effectifs (liste des membres) | `GET EFF` ; `/back-office/users` → 301 | Effectifs › Liste des membres (`NAV:124`) ; onglet « Tableur » | Registre filtrable de tous les membres, corrections sur place (affectation, ancienneté, élévation, qualification, emplois, rôle dans l'unité, niveau d'accès, date de création de l'organisation), sélection multiple | ORG puis `denyUnlessAccess` ; chaque correction a son droit ELA (`EWC 425-431`) ; grade non modifiable ici | Grades, emplois, ORBAT, RBAC, matricules (écrits au GET, `EWC 205-206`), doublons (`EWC 434`), codes d'unité (`EWC 440`) | 01 F01 |
| PER-F02 | Fiche membre (dossier RH complet) | `GET EFF/membres/{id}` et `/modifier` ; `/back-office/users/{id}` → 301 | Hors menu (depuis le tableur, Ctrl+K, liens) | Point d'entrée RH unique : identité, poste, carrière, statut du compte, suivi RH, papiers, historique ; une vingtaine d'actions POST | Une carte par droit ELA (`EWC 1597-1635`) ; postes ORBAT : `organization.orbat.manage` | Presque tout le domaine ; portrait envoyé au front `/personnel/{id}/portrait` | 01 F02 ; 02 F06 |
| PER-F03 | Ajouter un membre (assistant en 5 volets) | `GET/POST EFF/nouveau`, `/nouveau/verifier` ; `POST /back-office/users/store` (encore utilisé par l'ORBAT) | Hors menu latéral (bouton du bandeau Effectifs) | Créer la fiche et le compte, envoyer le lien « compléter son profil » | `canCreateMembers`, puis un droit par volet (`UserAdminController 600-604`) | `NewMemberDossierService`, matricule, rôles, intégration | 01 F03 |
| PER-F04 | Recherche du bureau (Ctrl+K) | `GET EFF/recherche` | Bouton « Rechercher » du bureau | Trouver un membre, une unité, un emploi | ORG ; contrôle interne HYPOTHÈSE | Remplace le Ctrl+K global sur ces pages (`eff-workspace-shell.js:198-205`) | 01 F04 ; 05 §3.2 |
| PER-F05 | Exports et modèle de tableur | `GET EFF/export`, `/export.pdf`, `/modele-tableur` | Bouton « Exporter » ; modèle hors menu | Export CSV, PDF (module `pdf_exports`), modèle d'import | `denyUnlessAccess` | Le CSV ignore 5 filtres du tableur (`EWC 665-678`) | 01 F05 |
| PER-F06 | Import du tableur (profil sans comptes) | `POST EFF/importer` | Formulaire du bandeau, profil `rosterWithoutAccounts` | Créer des fiches sans compte en lot | `canEditProfiles`, quota | `RosterOnlyMemberService` | 01 F06 |
| PER-F07 | Changements en masse (statut, affectation) | `POST EFF/bulk/statut`, `/bulk/affectation` | Barre de sélection du tableur | Statut ou unité pour 200 membres | `canManageStatus`, `canManageAssignments` | Pas de notification en masse (`EWC 1713-1796`) | 01 F07 |
| PER-F08 | Personnes sans compte | `GET EFF/sans-comptes` ; POST `creer`, `importer`, `{id}/modifier`, `{id}/rattacher` | Effectifs › Personnes sans compte (`NAV:133`) | Fiches sans compte : inviter, rattacher à un compte | `ncCanCreate`, `ncCanEdit`, `ncCanInvite` | `RosterFicheEventRepository` | 01 F08 |
| PER-F09 | Invitations « compléter son profil » | `GET EFF/invitations` ; POST `membres/{id}/invitations`, `invitations/{id}/{action}` | Onglet du bureau ; menu seulement hors profil Complet (`NAV:137-140`) | Lien envoyé à un membre pour compléter sa fiche | `canInviteMembers` | `MemberProfileInviteService` | 01 F09 |
| PER-F10 | Tableaux de données (constructeur) | `GET EFF/tableaux`, `/tableaux/export.csv` ; POST `enregistrer`, `tableur`, `{id}/supprimer` | Effectifs › Tableaux de données (`NAV:134`) | Vues enregistrées (colonnes, filtres, tri), export, envoi vers un tableur | Accès effectifs | Seul endroit où une vue se sauvegarde | 01 F10 |
| PER-F11 | Fiches jumelles (doublons) | `GET/POST EFF/doublons` | Effectifs › Comptes en double (`NAV:132`) | Détecter les doublons, régler les champs comparés | Accès effectifs | Aucune action de fusion | 01 F11 |
| PER-F12 | Départs et anciens membres | `GET EFF/departs` ; POST `departs/{id}/archiver|reintegrer`, `membres/{id}/depart` | Effectifs › Départs (`NAV:131`) | Enregistrer un départ, archiver, réintégrer | `canManageStatus` pour toute la page (menu visible sans ce droit) | `MemberOffboardingService` | 01 F12 |

### 1.2 Affectations, ORBAT, chaîne de commandement

| ID | Nom | Route(s) | Menu | Objectif | Droits | Dépendances | Fiche |
|---|---|---|---|---|---|---|---|
| PER-F13 | Affectations (matrice) | `GET EFF/affectations` (`?vue=membres`) ; POST `codes/communaute`, `codes/unites/{id}` | Effectifs › Affectations (`NAV:125`) | Matrice par unité ou par membre, derniers mouvements, codes d'unité | `canManageAssignments` ; code communauté : `admin.organization|admin.access` | `AffectationsMatrixService` ; raccourci vers une ancienne adresse ORBAT (`shell.php:76`) | 01 F13 |
| PER-F14 | ORBAT, organigramme, fiche d'unité, postes | `GET /orbat` ; API `/api/orbat/structure`, `/api/orbat/unit` ; `GET/POST BO/organisation/unites/{id}` et `/postes` ; anciennes routes redirigées | Organisation › Structure › Organigramme (`NAV:182`), page rendue hors du shell BO | Structure des unités, chef d'unité, postes (`orbat_billets`) et titulaires | `/orbat` : Auth + contrôle `organization.orbat.view|manage` ; API et fiche d'unité : `organization.orbat.manage` ou `admin.*` | `units`, `orbat_billets` ; titulaire de poste distinct de l'affectation | 01 F14 ; 05 §4.3 |
| PER-F15 | Chaîne de commandement et adéquation | `GET/POST EFF/chaine`, `EFF/chaine/adequation` | Effectifs › Chaîne de commandement (`NAV:126`) ; adéquation hors menu | Chefs manquants, désignation en lot, référentiel d'adéquation par échelon | `canManageAssignments` | `CommandChainAdvisor` ; étape de configuration `PERSONNEL_COMMAND_CHAIN_V1` | 01 F15 |
| PER-F16 | Groupes et équipes (anciens écrans) | `/back-office/groups*`, `/back-office/teams*` (création → ORBAT) | Organisation › Structure › Groupes, Équipes (`NAV:183-184`) | Index, fiche, modification et suppression hors ORBAT | ORG sans exception | Chevauche l'ORBAT (non lu en détail) | 01 F16 |

### 1.3 Emplois, fonctions, postes

| ID | Nom | Route(s) | Menu | Objectif | Droits | Dépendances | Fiche |
|---|---|---|---|---|---|---|---|
| PER-F17 | Emplois et fonctions (espace unifié) | `GET EFF/fonctions` (`?vue=catalogue|categories|site|historique|recuperation|reglages|attributions`) + ~20 POST ; `EFF/fonctions/catalogue` | Droits et permissions › Fonctions et emplois (`NAV:224`) ; onglet « Emplois » | Référentiel des emplois, catégories, catalogue Athena, prérequis, dérogations, attributions en lot | `canManageAssignments` ou `canManageRoles` (`EWC 3397`) | `personnel_job_roles`, `personnel_profile_job_roles`, `personnel_job_role_permissions` | 01 F17 ; 02 F07 |
| PER-F18 | Anciens écrans emplois et kits d'accès | `/back-office/personnel-job-roles*` : GET redirigés ; POST `roles/save`, `categories/*`, `assignments/save|settings` actifs ; `GET assignments/member-permissions` (JSON) ; **`/kits`** page réelle + POST `kits/save|assign|unassign` | Hors menu (`/kits` lié seulement par une action rapide) | Kits d'accès : lots de droits attribuables (ajoutent un rôle) | `canManageJobRoles` ; kits : `admin.organization|personnel.assignments.manage|admin.roles.manage` | `PersonnelFunctionKitService` | 01 F18 ; 02 F08 |
| PER-F19 | Doctrine des fonctions | `/back-office/roles-functions*` : GET redirigés ; POST `definitions/store`, `relations/store`, `required/save`, `quick-assign-role` | Hors menu | Définitions doctrinales, fonctions obligatoires, attribution rapide d'un rôle | ORG + `guardBackOfficeAccess` | `role_definitions` | 01 F19 ; 02 F12 |
| PER-F20 | Postes organisationnels | `GET/POST /back-office/positions`, `/positions/{id}/delete` ; POST `/users/{id}/assign-position`, `/apply-role-set` | Hors menu (lien depuis Configuration) | Intitulés de postes, catégories, jeux de rôles | ORG (exception `organization.job_roles.referential.manage|admin.roles.manage`) puis `admin.organization|admin.roles.manage` | `positions`, `user_positions`, `role_sets` ; formulaires d'attribution devenus inaccessibles | 01 F20 ; 02 F13 |

### 1.4 Recrutement, enrôlement, intégration

| ID | Nom | Route(s) | Menu | Objectif | Droits | Dépendances | Fiche |
|---|---|---|---|---|---|---|---|
| PER-F21 | Candidatures et onboarding de la recrue | `/back-office/recruitments`, `/{id}`, `/{id}/onboarding`, `/decision`, `/finalize-membership`, `/annuler`, `/referent` | Recrutement › Candidatures (`NAV:144`) | Décider, puis créer compte, affectation, grade, matricule, intégration | ORG (`organization.recruitment.manage`) ; pas de contrôle fin sur grade et accès (HYPOTHÈSE forte) | `EnlistmentAcceptanceProvisioningService` | 01 F21 |
| PER-F22 | Recrutement : vue d'ensemble, statistiques, offres, codes et liens | `BO/ressources/recrutement`, `/analyses` ; `/recruitment/offers*` ; `/recruitments/codes-invitation*`, `/liens-invitation*` | Recrutement › Vue d'ensemble, Offres publiées, Statistiques, Codes d'invitation prioritaires (`NAV:145-152`) | Pilotage du recrutement ; une offre pré-remplit l'onboarding | ORG (`organization.recruitment.manage`) | `RecruitmentWorkspaceController` | 01 F22 |
| PER-F23 | Enrôlement public | `/enlistment`, `/c/{slug}/enlistment` | Site public | Dépôt de candidature | Public | `enlistments` | 01 F23 |
| PER-F24 | Intégration des nouveaux membres | `BO/integration-membres`, `/modeles*`, `/reprise`, `/ouvrir`, `/{id}` (+ note, étape, référents, groupe, rendez-vous, annuler, rouvrir) | Recrutement › Intégration des recrues (`NAV:150`) | Parcours d'arrivée, référents, rendez-vous | ORG (`member_integration.*`) | Le passage en service actif dépend de la clôture du parcours | 01 F24 |
| PER-F25 | Nouvelle invitation (back-office) | `BO/invitations`, `/envoyees` ; POST store, revoke, renvoyer, lien | Recrutement › Invitations (profil Complet) ou Effectifs › Invitations (autres profils) | Inviter par e-mail avec unité d'arrivée | `InvitationSenderMiddleware` | Deuxième mécanisme d'invitation | 01 F25 |

### 1.5 Dossier RH et suivi

| ID | Nom | Route(s) | Menu | Objectif | Droits | Dépendances | Fiche |
|---|---|---|---|---|---|---|---|
| PER-F26 | À traiter et alertes | `GET EFF/a-traiter` (`?onglet=alertes`) via `BackOfficeTabs` ; `EFF/alertes` → 301 | Dossiers RH › À traiter et alertes (`NAV:159`) ; l'entrée de groupe vise `?onglet=surveiller`, valeur inconnue | Boîte RH : ce qui attend une action | Accès effectifs | `RhActionInboxService` ; routes absentes de `web.php` | 01 F26 |
| PER-F27 | Documents RH | `GET/POST EFF/documents-rh`, `/etablir`, `/{id}/fichier` | Dossiers RH › Documents RH (`NAV:162`) | Établir et ranger les documents RH | Écriture `canEditProfiles` ; téléchargement ouvert à tout porteur de l'accès effectifs (HYPOTHÈSE forte) | Sous-vue papiers d'identité | 01 F27 |
| PER-F28 | Mobilité, relève et succession | `EFF/mobilite`, `/{id}/statut` ; `EFF/vivier`, `/{id}/retirer` | Dossiers RH › Carrière › Mobilité, Relève et succession (`NAV:170-171`) | Demandes de mobilité ; vivier de relève | Mobilité : assignments ou status ; vivier : grades, rôles ou statut | Une mobilité acceptée change l'affectation (HYPOTHÈSE) | 01 F28 |
| PER-F29 | Papiers d'identité et couverture (fiche) | `EFF/membres/{id}/papiers/*` ; jumeau `/atak/sse/papiers/membres/{id}/*` | Rubrique de la fiche membre | Identité principale, alias, photo, documents, kit, synchronisation jeu, couverture | Habilitation SSE, `sseManage`, `canEditProfiles` | `MemberIdentityPapersController` ; même stockage que I-01 à I-05 | 01 F29 |
| PER-F30 | Réglages RH et avancement automatique | `EFF/reglages`, `/reglages/avancements` | Dossiers RH › Réglages RH (`NAV:174`) | Réglages RH, lancement de l'avancement automatique | Accès effectifs (détail non lu) | `PersonnelAutoAdvancementService` → `ElevationApprovalService` | 01 F30 |
| PER-F31 | Décorations (référentiel et attribution) | `BO/referentiels/decorations` (index, store, attribuer, lot, motifs) | Référentiels › Grades et distinctions › Décorations (`NAV:206`) | Définir et décerner les décorations | ORG (exception `personnel.awards.manage|badges.manage`) ; appartenance au tenant non vérifiée (HYPOTHÈSE) | `personnel_awards` ; aucune attribution depuis la fiche | 01 F31 |
| PER-F32 | Qualifications des membres (matrice) | `GET EFF/qualifications` ; POST `BO/referentiels/qualifications/matrice/case`, `/academy/rattacher` | Effectifs › Qualifications des membres (`NAV:127`) | Matrice membres × qualifications, échéances | Accès effectifs | 6 chemins d'écriture d'une qualification | 01 F32 |
| PER-F33 | Élévations (demandes d'accès élevé) et critères | `GET EFF/elevations`, `/{id}/statut` ; `GET/POST EFF/elevations/criteres` ; `POST EFF/membres/{id}/elevation` ; côté membre `/personnel/mon-espace-rh/elevation` | Dossiers RH › Demandes d'accès élevé (`NAV:161`) ; critères hors menu | Demander et décider un changement de grade, emploi, unité, niveau ou droit précis | Décision : `canManageRoles` **ou** `canManageStatus` (voir S4) ; critères : `canManageGrades` | Seul chemin de création d'un droit individuel | 01 F33 ; 02 F05 |
| PER-F34 | Circuits de validation | `GET/POST EFF/circuits` | Effectifs › Circuits de validation (`NAV:136`) | Étapes d'avis avant décision par type de demande | Lecture : accès effectifs + module `workflow_circuits` (Pro) ; écriture : `canManageRoles` | `WorkflowEngine`, `ApproverResolver` | 01 F34 ; 02 F04 |
| PER-F35 | Corrections de fiches | `BO/personnel/corrections`, `/appliquer`, `/lot`, `/{id}/decide` ; côté membre `/personnel/{id}/correction` | Dossiers RH › Corrections de fiches (`NAV:160`) | File des changements demandés depuis la fiche front | Auth + `canStaffManage`, `canBatch` dans le contrôleur | `PersonnelCorrectionRequestService` | 01 F35 |
| PER-F36 | Sanctions et absences | `BO/moderation` ; `EFF/membres/{id}/sanctions` et `/lever` ; côté membre `/personnel/mon-espace-rh/absences` | Effectifs › Sanctions & absences si `admin.members.moderate` (`NAV:128-130`) | Discipline et absences | `TenantMemberModerationMiddleware` ; `canManageSanctions` | Deux écrans pour la discipline | 01 F36 |

### 1.6 Grades et carrière

| ID | Nom | Route(s) | Menu | Objectif | Droits | Dépendances | Fiche |
|---|---|---|---|---|---|---|---|
| PER-F37 | Référentiel des grades (systèmes officiels, perso, sur mesure) | `GET BO/referentiels/grades` (`?tab=perso|custom`) ; POST `systeme`, `create`, `store`, `{id}/edit|update|deactivate|reactivate`, `sur-mesure/*` ; `BO/referentiels/grades/create` | Référentiels › Grades et distinctions › Grades (`NAV:205`) | Choisir le système de grades, créer des grades propres | ORG sans exception `personnel.grades.manage` ; aucun contrôle interne | `completeForTenant` écrit au GET | 01 F37 |
| PER-F38 | Catalogue et audit OTAN des grades | `GET BO/referentiels/grades/catalogue`, `POST /catalogue/audit` | Hors menu (lien depuis PER-F37) | Contrôle des codes OTAN | ORG | `rank_catalog`, `rank_migration_audit` | 01 F38 |
| PER-F39 | Grades de l'avancement, campagnes, commission | `BO/organisation/grades*` (importer, filières, ordre, initial, create, edit, archive) ; `BO/rh/avancement*` (campagnes, assistant, candidatures, propositions, commission, publier ; `create`) ; API `/api/avancement/*` | Dossiers RH › Carrière › Avancement (`NAV:168`) ; `/organisation/grades` hors menu | Campagnes d'avancement formelles | ORG (exception `personnel.advancement.manage|grades.manage`) | Publie `personnel_grade_history` sans changer `grade_id` | 01 F39 |
| PER-F40 | Fiche membre front-office | `/personnel/{id}`, `/edit`, `/update`, `/visibility`, `/portrait`, `/generate-matricule`, `/member-number*`, `/sync-steam`, `/notes`, `/temps-par-role/*`, `/bilans`, `/phase` ; annuaire `/personnel` ; `/personnel/me` | Portail ; lien « Voir la fiche » de PER-F02 | Fiche publique du membre et second formulaire d'édition | Soi-même ou `personnel.profile.update` ; changements de structure directs avec `canApplyOrbatImmediately`, sinon correction | Concurrence PER-F02 | 01 F40 |
| PER-F41 | Ancienneté (règles) | `BO/organisation/anciennete` + POST `initialiser`, `synchroniser-effectifs`, `completer-depuis-dossier`, `date-creation-entite` | Dossiers RH › Carrière › Ancienneté (`NAV:169`) | Règles d'ancienneté, date de création de l'organisation | ORG sans exception | Date de création réglable aussi depuis le tableur | 01 F41 |
| PER-F42 | Identification : matricules, noms et trigrammes, indicatifs, PASS RH | `BO/organisation/matricules*`, `/noms*`, `/indicatifs*`, `/passes*` | Organisation › Identification (`NAV:190-196`) | Numérotation, noms, indicatifs radio, passes RH | ORG (exceptions `personnel.member_number.manage`, `personnel.callsign.manage`) | Matricule attribué par 5 chemins | 01 F42 |

### 1.7 Accès (pages portées par le bureau des effectifs)

| ID | Nom | Route(s) | Menu | Objectif | Droits | Dépendances | Fiche |
|---|---|---|---|---|---|---|---|
| PER-F43 | Niveaux d'accès (onglet « Accès ») et « Droits par membre » | `GET/POST EFF/roles` + `nouveau`, `retablir`, `supprimer`, `reafficher` ; `GET EFF/droits` → redirection | Droits et permissions › Rôles (`NAV:222`) et › Droits par membre (`NAV:223`) : deux entrées, une page | Ce que permet chaque niveau (Membre, RH, Gestionnaire, personnalisés) | Lecture : accès effectifs ; écriture : `canManageRoles` | `RolePermissionService`, `CommunityAccessProfiles` | 01 F43 ; 02 F01, F02 |
| PER-F44 | Registre des droits | `GET EFF/registre-droits` (`?vue=vigueur|historique`), `/export.csv` ; POST `attribuer`, `modifier`, `retirer`, `revue` | Effectifs › Registre des droits (`NAV:135`) | Qui détient quel droit, bornes, revue, historique | Accès effectifs + module `access_register` ; écriture `canManageRoles` + CSRF | Balayage des échus à l'affichage ; déjà engagé : une ligne par membre (PR #1600, #1601, #1602) | 01 F44 ; 02 F03 |
| PER-F45 | Position administrative | `POST EFF/membres/{id}/position-service` ; ancien `POST /back-office/users/{id}/duty-position` ; `POST /users/duty-positions/backfill` ; automatique à l'arrivée | Carte « Statut du compte » de la fiche | Situation de service : en formation, actif, inactif, renfort | Fiche : `canManageStatus` ; ancienne route : ORG seul (S9) | Stockée comme rôle RBAC `status_*` ; déjà engagé : positions personnalisées, page Effectifs › Positions administratives (PR #1600, #1601, #1602) | 01 F45 ; 02 §3.3-3.4 |

### 1.8 Écrans non liés ou morts (audit 01, §H)

| ID | Route ou vue | État | Preuve |
|---|---|---|---|
| PER-H1 | `/back-office/users`, `/users/create`, `/users/{id}`, `/users/{id}/edit` (GET) | 301 vers Effectifs | `LegacyPersonnelRedirectController.php:13-33` |
| PER-H2 | `views/admin/organization/users/{index,show,edit}.php` et leurs POST (`update`, `assign-position`, `apply-role-set`, `duty-position`, `duty-positions/backfill`, `operator-photo`, `deactivate`, `request-purge`, `notify-profile`, `resend-verification`) | Vues orphelines, POST actifs | `routes/web.php:1834-1844, 2014-2015` ; 02 F21 |
| PER-H3 | `views/admin/effectifs_workspace/droits.php` | Jamais rendue | `EWC 3385-3390` |
| PER-H4 | `/back-office/personnel-job-roles` (sauf `/kits`), `/roles-functions*` | GET redirigés, POST actifs | Voir PER-F18, PER-F19 |
| PER-H5 | `BO/organisation/structure`, `/units*`, `/groups/create`, `/teams/create` | Redirection vers l'ORBAT | `routes/web.php:1772, 2193, 2201-2204, 2224` |
| PER-H6 | `EFF/roleplay` | 301 vers `roleplay-followup?onglet=dossiers`, encore onglet du bureau | `BackOfficeTabs.php:74` ; `shell.php:32` |
| PER-H7 | `EFF/alertes` | 301 vers `a-traiter?onglet=alertes` | `BackOfficeTabs.php:134` |
| PER-H8 | `/jnet/effectifs`, `/jnet/personnel` | Autre liste des membres (voir J-04) | `routes/web.php:502` |
| PER-H9 | `BO/positions` | Hors menu (voir PER-F20) | grep `NAV` |
| PER-H10 | `BO/referentiels/grades/catalogue`, `BO/organisation/grades`, `EFF/chaine/adequation`, `EFF/elevations/criteres` | Accessibles seulement par un lien interne | grep `NAV` |

---

## 2. Accès et gouvernance (audit 02)

Les fiches de l'audit 02 qui décrivent une page déjà inventoriée sont rattachées à l'identifiant existant. Elles sont listées pour que rien ne manque.

| ID | Nom | Route(s) | Menu | Objectif | Droits | Dépendances | Fiche |
|---|---|---|---|---|---|---|---|
| ACC-F01 | Niveaux d'accès | voir PER-F43 | Rôles (`NAV:221-222`) ; onglet « Plus › Accès » ; méga-menu « Gestion › Accès » ; lien depuis la fiche membre | voir PER-F43 | voir PER-F43 | Les ~30 rôles semés n'y apparaissent pas mais donnent encore leurs droits | 02 F01 |
| ACC-F02 | Droits par membre | `GET EFF/droits` → `EFF/roles` | `NAV:223` (jamais marquée active) | Entrée sans page propre | Règle de menu héritée | Vue `droits.php` orpheline (PER-H3) | 02 F02 |
| ACC-F03 | Registre des droits | voir PER-F44 | Effectifs › Registre ; onglet « Plus › Organisation » ; lien depuis PER-F43 | voir PER-F44 | voir PER-F44 | — | 02 F03 |
| ACC-F04 | Circuits de validation | voir PER-F34 | — | — | — | Avis consommés dans Mon service (ACC-F17) | 02 F04 |
| ACC-F05 | Demandes d'élévation et critères | voir PER-F33 | — | — | Voir S4 | Demandes aussi émises depuis « Mes démarches » et `/operateur/acces/demander` | 02 F05 |
| ACC-F06 | Cartes Niveau d'accès, Statut du compte / Position, Élévation de la fiche | `POST EFF/membres/{id}/roles`, `/position-service`, `/statut`, `/elevation` | Fiche membre (PER-F02) | Changer le niveau, la position, l'état du compte ; demander une élévation | Niveau : `canManageRoles` ; position et état : `canManageStatus` ; élévation : accès effectifs | Un seul niveau affiché ; pas de dates ni de périmètre unité | 02 F06 |
| ACC-F07 | Fonctions et emplois | voir PER-F17 | — | — | — | Rangé sous « Droits » alors qu'un emploi ne donne aucun droit | 02 F07 |
| ACC-F08 | Kits d'accès | voir PER-F18 | Hors menu (`$navFunctionKitsActive` calculé, jamais utilisé, `NAV:59`) | — | — | Troisième manière de donner un rôle | 02 F08 |
| ACC-F09 | Anciennes pages Rôles, Profils de rôles, fiche et droits d'un rôle | `GET BO/roles`, `/roles/presets` → redirection ; vivantes : `GET /roles/{id}`, `/roles/{id}/permissions`, `/roles/{id}/edit-presentation`, `/roles/presets/preview` ; POST `presets/apply`, `presets/kits/save|delete`, `/roles/{id}/permissions` | Hors menu (liens depuis Configuration, catalogue, configuration initiale, `ath_roles_table.php`) | Éditer les permissions de n'importe quel rôle du tenant | ORG puis `admin.organization|admin.roles.manage|admin.permissions.manage` | `RoleAdminController.php:623-710` ; `TenantRolePermissionPresetService` | 02 F09 |
| ACC-F10 | Matrice rôles et droits (ancienne) | `GET BO/roles-permissions` → redirection ; POST `save`, `assign`, `revue` ; GET `export` ; API `/api/back-office/roles-permissions*` | Hors menu (lien « Rôles & permissions » de Configuration) | Matrice par module, attribution d'un rôle | ORG (exception `admin.roles.manage|admin.permissions.manage`) + `guard()` | `RolePermissionMatrixCatalog` | 02 F10 |
| ACC-F11 | Gestion des accès (règles avancées) | `GET BO/access-management` → redirection ; POST `roles/save` (refus), `rules/save` ; GET `simulate` ; API `/api/access-control/*` (Auth) | Hors menu (guides, documentation) | Règles d'accès avancées | API écriture : `admin.*` + CSRF ; API GET : tout membre | `AccessControlMiddleware` non branché (S7) | 02 F11 |
| ACC-F12 | Doctrine des fonctions | voir PER-F19 | — | — | — | Cinquième chemin d'attribution d'un rôle | 02 F12 |
| ACC-F13 | Postes organisationnels | voir PER-F20 | — | — | — | Catégorie « Administratif » homonyme des positions administratives | 02 F13 |
| ACC-F14 | Accès au renseignement | voir R-01 | Rôles et droits › Accès au renseignement (`NAV:225`) | Codes d'accès SSE | Route Auth ; contrôleur `atak.sse.grant|admin.*` ; menu `admin.*` seulement | Hors registre des droits | 02 F14 |
| ACC-F15 | Sécurité et blocages | `GET BO/security-indicators`, `POST /revoke` | Droits et permissions › Sécurité et blocages (`NAV:610`) | Comptes et indicateurs bloqués, levée | ORG (exception `organization.recruitment.manage|admin.members.moderate`) ; menu `admin.*` | `BlockedIndicatorRepository` | 02 F15 |
| ACC-F16 | Journal d'audit (communauté) | voir ORG-B9 | Droits et permissions › Journal d'audit (`NAV:611`) ; méga-menu ; lien de Configuration | Filtrer le journal de la communauté | ORG (exception `admin.audit.view`) ; menu `admin.organization|admin.access` | `TenantAdminAuditMiddleware` non branché (S7) | 02 F16 |
| ACC-F17 | Mon service (« Centre d'actions », « Aujourd'hui ») | `GET /mon-service`, `/centre-actions`, `/aujourdhui` ; POST `rsvp`, `tache/{id}/accuser|demarrer|terminer`, `circuit/{id}/avis` | Absent du menu à la date de l'audit ; déjà engagé : ajouté au menu (PR #1600, #1601, #1602) | Tâches de mon poste, avis de circuit, agenda | Auth + appartenance dans le contrôleur | `work_tasks`, permanences, billets ORBAT, circuits ; statut affiché en code brut | 02 F17 |
| ACC-F18 | Choix d'espace après connexion (TBA / JNET) | `GET/POST /login/choisir-espace` ; bascule `/jnet/systeme/bascule` | Après connexion | Choisir back-office ou extranet | Auth ; TBA seulement avec `admin.organization|admin.access|site.support` | `PortalAccessChoice.php:21-28, 218-233` ; JNET mène à `/dashboard` | 02 F18 |
| ACC-F19 | Compte › Accès | `GET /account/acces` | Portail (centre de compte) | Rôle, grade, élévations, quitter la communauté | Auth | `AccountController.php:240-325` | 02 F19 |
| ACC-F20 | Compte › Sécurité, mot de passe, appareils | `GET/POST /account/security`, `/account/security/devices` + `revoke`, `mobile/revoke` ; `GET/POST /account/password` | Portail | Double vérification, codes de secours, appareils de confiance | Auth | Même révocation qu'en T-04 | 02 F20 |
| ACC-F21 | Anciennes routes « Utilisateurs » | voir PER-H2 | — | — | — | — | 02 F21 |
| ACC-F22 | Qualifications › droits liés et émetteurs habilités | POST `BO/referentiels/qualifications/{id}/droits`, `/droits/{grantId}/supprimer`, `/emetteurs/{issuerId}/habilites` | Depuis la fiche d'une qualification (NAV-07) | Donner une permission à l'obtention d'une qualification | ORG | Tables jamais lues (S8) | 02 F22 |
| ACC-F23 | Navigation « Espace opérateur » | `/back-office`, `/back-office/ma-situation/*` | Menu opérateur (`NAV:421-529`) | Menu des membres et des cadres délégués | Ouvert à tout membre (`OrganizationAdminMiddleware.php:27-29`) | `isOperatorBoNav` (`back_office_sidebar.php:13-15`, revérifié) | 02 F23 |
| ACC-F24 | Catalogues et presets de droits (sans écran) | — | — | 3 profils, 7 rôles morts (`FunctionBasedAccessCatalog`), ~30 rôles semés, presets, kits, matrice | — | Migration « member » → « Opérateur » contredite par le code | 02 F24 |
| ACC-F25 | Plateforme : rôles du site, journal plateforme | voir ORG-H17, ORG-H34 | Plateforme | — | SYS | — | 02 F25 |
| ACC-F26 | Configuration › section Accès | voir ORG-B7 | Réglages › Configuration | Cartes vers Postes, Rôles & permissions, Profils de permissions, Journal | — | Deux cartes mènent au même écran | 02 F26 |

---

## 3. Opérations, ATAK, terrain, renseignement, équipement (audit 03)

### 3.1 Opérations : missions, planification, événements, comptes rendus, tableau opérationnel

| ID | Nom | Route(s) | Menu | Objectif | Droits | Dépendances | Fiche |
|---|---|---|---|---|---|---|---|
| O-01 | Portail missions | `GET BO/missions` (`BackOfficeTabs`), `BO/missions/{id}` (`?vue=recapitulatif|participants|atak|liaisons`) | Opérations › Missions › Portail missions (`NAV:266`) | Vue consolidée des missions | ORG | `mission_plans`, cycles, données ATAK ; aucune table propre | 03 O-01 |
| O-02 | Planification de mission et espace de plan | `GET BO/missions?onglet=planification` ; `BO/planification/{id}` (`?vue=planning|organisation|documents`), `/paquet.pdf` ; POST store, `planning`, `statut`, `organigramme`, `inscrits-evenement`, `documents`, `postes/{slotId}/*` | Hors menu propre (onglet du portail) ; méga-menu vers l'ancienne adresse | Qui fait quoi pendant la mission ; passage live, clôture qui crée l'AAR | ORG + CSRF | `mission_plans`, `mission_to_*` ; lu par le téléphone et Overwatch | 03 O-02 |
| O-03 | Agenda / Événements | `GET BO/events` (`?vue=…`, `?export=csv`), `BO/events/{id}`, `/{id}/export-presences` ; POST store, `companion/pair`, `discord/bot`, `{id}/discord`, `series/stop`, `details`, participants, postes ; alias `BO/evenements` | Opérations › Événements › Inscriptions en cours, Calendrier (`NAV:253-254`) | Programmer les activités, inscriptions, présences, relais Discord | ORG + module `events` | `community_events*`, Discord, compagnon | 03 O-03 ; 04 D1, D2 |
| O-04 | Bilan de participation | `GET BO/events/insights` ; alias | Opérations › Événements › Bilan de participation (`NAV:255`) | Qui vient, annule, ne répond pas | ORG + `events` | `community_event_rsvps*` | 03 O-04 ; 04 D2 |
| O-05 | Réponses nominatives d'un événement | `GET BO/events/{id}/reponses-nominatives`, `/export` ; API `/api/events/{id}/reponses-nominatives*` | Hors menu (fiche événement) | Suivi nom par nom | ORG | API jumelle | 03 O-05 ; 04 D2 |
| O-06 | Événements côté membre (4 surfaces) | `/evenements` ; `BO/ma-situation/evenements` ; `/pointage`, `/manoeuvres` ; `/aujourdhui` ; flux `/calendrier/abonnement/{token}` | Portail ; Opérateur › Terrain › Événements (`NAV:475`) | Répondre, pointer, s'abonner | Auth ; ICS par jeton seul | Bouton « publier au mur » | 03 O-06 |
| O-07 | Cycle de mission et parties jouées | `GET BO/atak/cycle-mission` ; POST store, `{id}/ouvrir`, `{id}/cloturer` ; `BO/atak/parties/*` ; API `/api/mission-cycle*` | Opérations › Missions › Cycle de mission (`NAV:267`) | Briefing, exécution, après-action d'une session ; rattacher une partie jouée à plan, cycle, événement | RES + ATAK `manage` | `theatre_mission_cycles`, `atak_debrief_ops` | 03 O-07 |
| O-08 | Comptes rendus post-opération (AAR) et synthèses | `GET BO/atak/comptes-rendus` (`?vue=missions|actions`), `/nouveau`, `/export.csv`, `/{id}`, `/{id}/edit`, `/{id}/pdf` ; `/syntheses`, `/syntheses/nouvelle`, `/syntheses/{id}` | Opérations › Comptes rendus › Comptes rendus post-op (`NAV:259`) ; synthèses hors menu | Relecture, validation, suivi des actions | RES + ATAK `manage` ; synthèses : Auth + contrôle interne | `aar_reports` (FK cycle, pas d'`event_id`) | 03 O-08 |
| O-09 | CR des joueurs (onglet « suivi ») | `GET BO/atak/comptes-rendus?onglet=suivi` ; `suivi-cr/{id}/export.csv` ; POST `imposer`, `reglages`, `renommer`, `retirer-session`, `effacer-historique`, `{id}/supprimer`, `relancer`, `cloturer` | Onglet de O-08 ; méga-menu vers l'ancienne adresse | Qui a reçu, remis, lu le CR imposé | RES + ATAK `manage` | `atak_debrief*` ; gestionnaire de parties partagé avec O-07 | 03 O-09 |
| O-10 | Modèles de debriefing | `GET BO/atak/comptes-rendus/modeles`, `/nouveau`, `/{id}` ; POST store, update, archiver, reactiver, dupliquer | Opérations › Comptes rendus › Modèles de debriefing (`NAV:261`) | Questionnaires de compte rendu | RES | `aar_report_templates` | 03 O-10 |
| O-11 | Espaces opérationnels `/operations` | `GET /operations`, `/operations/{uuid}`, `/snapshot`, `/tactical/{uuid}` ; POST statut, phase, objets, calques, tâches, objectifs, ordres | Hors menu BO (méga-menu « Opérations ») | Dossier de mission collaboratif : phases, ORBAT, ordres, calques, renseignement | Auth ; `canPlan`, `canIntel`, `canOrders`… dans le service ; index sans `guardView` | `operations*`, `planning_*` ; colonnes `mission_plan_id`, `cycle_id` jamais remplies | 03 O-11 |
| O-12 | Diapositives de briefing | `GET BO/atak/briefing-slides` ; POST store, `google-url`, `{id}/update|comment|delete|move|toggle-publish` | Opérations › Missions › Diapositives (`NAV:268`) | Images du briefing, ordre, visibilité en jeu | RES | `tactical_briefing_slides.operation_id` ; téléphone | 03 O-12 |
| O-13 | Tableau opérationnel (pilotage) et mur | `GET BO/tableau-operationnel`, `/fiche/nouvelle[/{type}]`, `/fiche/{id}`, `/stream` ; membre `/tableau-operationnel` ; public `/tableau-operationnel/p/{token}` ; ~20 POST | Tableau de bord et suivi › Tableau opérationnel (`NAV:548`) ; méga-menu « Mur opérationnel » | Préparer et publier les fiches lues par les membres | `OperationalBoardEdit` / `View` | `planning_entries` ; copie d'événement par `publier-lie` ; lu par J-03 | 03 O-13 |
| O-14 | Coopérations inter-unités | `GET BO/cooperation/missions*` ; `BO/cooperation/catalog*` ; `BO/cooperation/announcements*` ; API de recherche ; alias `/admin/interteam-missions/*`, `BO/ressources/interteam-missions/*` | Opérations › Coopérations (4 entrées, `NAV:272-275`) ; menu opérateur | Opérations avec d'autres communautés : négociation, consentement OTP, réunions, sitrep, REX | `InterteamMissionsAccess` ; catalogue : formule `cooperation` (Pro) | `interteam_*`, catalogue plateforme | 03 O-14 ; 04 I1, I2 |

Écrans secondaires (audit 03, « Opérations ») :

| ID | Nom | Route | Menu | Constat |
|---|---|---|---|---|
| O-S1 | Salle de guerre | `/salle-de-guerre` | Méga-menu | Vue statique sans données |
| O-S2 | C2 | `/c2` ; doublon `/connect/{token}/c2` | — | Portail statique de 4 liens |
| O-S3 | Transmission | `/transmission`, `/{id}`, `/{id}/poe` | — | Journal distinct de `/atak/sse/transmissions` |
| O-S4 | Rétrospective | `/retrospective[/{year}[/unite]]` | — | Bilan annuel |
| O-S5 | Companion / Discord des événements | `POST BO/events/companion`, `/discord` | — | Aucune page GET |

### 3.2 ATAK : configuration, supervision, terminaux (back-office)

| ID | Nom | Route(s) | Menu | Objectif | Droits | Dépendances | Fiche |
|---|---|---|---|---|---|---|---|
| A-01 | Poste de situation (hub ATAK) | `GET BO/atak` ; `POST BO/atak/localisation-telephone` | ATAK › Poste de situation (`NAV:666`) | Qui est connecté, où ; dossiers SSE en cours | RES + `guardAtak('view')` ; placement `manage` | `/api/atak/ingest-traffic` | 03 A-01 |
| A-02 | Carte tactique (éditeur Athena) | `GET BO/operations/carte-tactique` ; API `/api/athena/tactical/markers` (GET, POST, PATCH, DELETE), `/sync` | Opérations › Carte tactique (`NAV:630`) | Dessiner des marqueurs sur la carte | Page : RES + `manage` ; API : aucune garde (S2) | `athena_tactical_markers` | 03 A-02 |
| A-03 | Contrôle de mission | `GET BO/atak/controle-serveur` ; POST `regles`, `fonctions`, `apps`, `reseau-adopter`, `relais` | ATAK › Contrôle de mission (`NAV:667`) | Réglages en jeu expliqués, état du serveur | RES + `manage` | Mêmes données que A-14 | 03 A-03 |
| A-04 | Réseau de relais | `GET BO/atak/relays-network` ; API `/api/atak/relays/{mapId}/{uid}` | ATAK › Supervision › Réseau de relais (`NAV:310`) | État et portée des relais radio | RES | `atak_relays` | 03 A-04 |
| A-05 | Mode roleplay ATAK | `GET BO/atak/roleplay` (`?legacy=1`) ; POST update ; **GET `/reset`** ; `/intel-scramble` ; AJAX `/api/atak/roleplay/server-tests` ; alias `/admin/atak/roleplay*` | Roleplay › Mode roleplay ATAK (`NAV:658`) | Contraintes réalistes imposées aux téléphones | RES ; reset en GET sans CSRF (S3) | `tenant_atak_config.roleplay_*`, `atak_crypto_domains` | 03 A-05 |
| A-06 | Parc de terminaux et réglages réalisme | `GET BO/atak/realisme` (`?onglet=reglages`), `/realisme/terminaux/{id}/journal` ; POST terminaux, sélection, sessions web, dissocier, supprimer ; téléphones `enregistrer`, `regenerer` ; réglages `history`, `version/{id}`, restaurer, `verify` | ATAK › Terminaux › Parc › Parc de terminaux (`NAV:319`) | Appareils reliés, identités téléphone en jeu, réglages versionnés (11 domaines) | RES + `guardAtak` | `atak_terminals`, `atak_realism_config` ; GET qui écrivent | 03 A-06 |
| A-07 | Certificats et réseaux de chiffrement | `GET BO/atak/certificats` ; POST sous deux adresses, `reseaux-chiffrement`, `{id}/revoquer|supprimer` | ATAK › Terminaux › Parc › Certificats | Certificats des terminaux | RES + `guardAtak` | `atak_certificates`, `atak_crypto_domains` | 03 A-07 |
| A-08 | Sessions et connexions, fiche opérateur | `GET BO/atak/operateurs`, `/operateurs/export`, `/fiche-operateur` | ATAK › Terminaux › Opérateurs (`NAV:327-328`) | Qui est connecté, historique, fiche d'un opérateur | RES + `view` | `atak_units`, `atak_device_logs` | 03 A-08 |
| A-09 | Parc ATAK des membres (communauté) | `GET BO/atak/parc-atak`, `/fiche` ; POST bloquer, debloquer, deconnecter, message, compromission, certificat, note, remplacer, reparer | ATAK › Terminaux › Parc › Parc ATAK des membres (`NAV:320`) | Statistiques de jeu, compromissions, messages imposés, blocages | RES | Version plateforme `/admin/parc-atak*` (ORG-H35) | 03 A-09 |
| A-10 | Escouades en jeu et équipes de feu | `GET BO/atak/escouades` (`?onglet=fire-teams`), `BO/atak/fire-teams/create`, `/{id}` ; POST store, update, dissolve, delete | Indicateurs › Métriques joueurs › Escouades en jeu (`NAV:234`) | Escouades et équipes de feu envoyées par les téléphones | RES ; masqué en profil Effectifs | `atak_squads`, `fire_teams` | 03 A-10 |
| A-11 | Métriques joueurs, temps d'écran, heures de jeu | `GET BO/metriques-joueurs` (`?onglet=temps-ecran`), `BO/atak/heures` | Indicateurs › Métriques joueurs (`NAV:232-233`) | Distances, temps pilote, temps d'écran, heures | RES | `atak_distance_stats`, `atak_screen_time`… | 03 A-11 |
| A-12a | Files d'envoi Athena | `GET BO/atak/files-envoi` | ATAK › Supervision (`NAV:312`) | Historique des files d'envoi | RES | masqué en profils Effectifs et ATAK | 03 A-12 |
| A-12b | Data Inspector | `GET BO/atak/data-inspector` ; API `/api/athena/inspector/metrics`, `/api/athena/v2/inspector`, `POST /api/athena/v2/dev/seed` | ATAK › Supervision (`NAV:311`) | Diagnostic des remontées du jeu | RES ; seed exposé (S14) | toutes les remontées | 03 A-12 |
| A-13 | Détection des marqueurs | `GET/POST BO/atak/detection-marqueurs` | ATAK › Terminaux › Détection des marqueurs (`NAV:331`) | Règles lues par le mod | ORG (et non RES) | `atak_marker_detection_rules` | 03 A-13 |
| A-14 | Configuration ATAK / Tacmap | `GET BO/ressources/atak-config`, `/export` ; POST store, `access-key`, `maintenance`, `modules`, `data-flows`, `experience`, `game-experience`, `first-login-link`, `photo-hud`, `marker-icons*`, `purge` ; alias `/admin/atak-config` | ATAK › Configuration et mods › Configuration ATAK (`NAV:343`) | Clé d'accès, serveur, carte par défaut, connexion en jeu, expérience, icônes, export, purge | RES seul, aucun contrôle interne | `tenant_atak_config` ; modules et flux en fichiers de cache | 03 A-14 |
| A-15 | Consignes et kit ATAK | `GET BO/ressources/atak-consignes`, `/catalogue` ; POST notes, `notes/supprimer|deplacer`, `kit`, `kit/supprimer` | ATAK › Configuration et mods › Consignes et kit (`NAV:345`) | Consignes et kit obligatoire lus par le téléphone | RES | `community_notes`, `community_kit` | 03 A-15 |
| A-16a | Mods (Steam Workshop) | `GET/POST BO/ressources/atak-mod` (+ `/steam`) | Configuration et mods › Mods | Collection de mods, annonces | RES | fichier, `tenant_alerts`, Discord | 03 A-16 |
| A-16b | Restrictions d'accès au mod | `GET BO/ressources/atak-mod-blocks` ; `/api/back-office/atak-mod-blocks/members` | Configuration et mods › Mods | Bloquer des membres | RES | `blocked_indicators` | 03 A-16 |
| A-16c | Serveurs de jeu | `GET BO/ressources/serveurs-de-jeu` | Configuration et mods | Serveurs et présence | RES | `tenant_game_servers` | 03 A-16 |
| A-16d | Diffusion des rapports | `GET BO/ressources/atak-diffusion-rapports` ; POST store, etat, ordre, simuler, dupliquer, supprimer | Configuration et mods | Règles de routage des rapports | RES + `guardAtak` | `atak_report_routing_*` | 03 A-16 |
| A-16e | Alias historiques ATAK | `/admin/modpacks*`, `BO/ressources/modpacks*`, `/admin/atak-mod*`, `/admin/atak-diffusion-rapports`, `/admin/atak/realism/*` | — | Redirections (une à deux sauts) | Auth | — | 03 A-16 |
| A-17 | Espace aérien | `GET BO/espace-aerien` | Référentiels › Compétences › Espace aérien (`NAV:214`) | Vue de l'aviation : carnets, flotte, qualifications pilotes | ORG + formule ; module personnel (invisible en profil ATAK) | `Pilot*`, `AirspaceOverviewService` | 03 A-17 |

Écrans plateforme du périmètre ATAK : voir ORG-H35 (`/admin/atak-beta`, `/admin/atak-mod-reports`, `/admin/parc-atak`, `/admin/system/alertes-mod`) ; `/admin/ops-center` est un alias de `/admin` (03).

### 3.3 ATAK côté terrain

| ID | Nom | Route(s) | Menu | Objectif | Droits | Dépendances | Fiche |
|---|---|---|---|---|---|---|---|
| T-01 | Overwatch (situation tactique web) | `GET /atak` ; alias 301 ; POST `game-link`, `game-link/confirm-pair`, `demande-acces` | ATAK › Overwatch (`NAV:665`, sort du shell) ; Opérateur › Terrain ; méga-menu | Carte en direct et outils de commandement (16 espaces) | `$mwAtakWeb` + `can()` internes ; formule `atak` | Toutes les API `/api/atak/*`, relais Node | 03 T-01 |
| T-02 | Overwatch ancien et Tacmap | `GET /overwatch`, `/tacmap` | Méga-menu | Ancienne carte : seule interface web de l'appui-feu, de la logistique, des zones et des cartes perso | Auth ; pas de contrôle de formule | `fire_units`, `danger_zones`, `tenant_custom_maps` | 03 T-02 |
| T-03 | Première liaison ATAK | `GET /atak/premiere-liaison` et `BO/ma-situation/premiere-liaison` ; `/atak/setup` → redirection | Méga-menu ; depuis « Ma liaison ATAK » | Mise en service guidée (Steam, identité, code de liaison) | `$mwAtakMemberOnly` + formule | `tactical_game_link_codes` | 03 T-03 |
| T-04 | Ma liaison ATAK, Mes appareils, appareils du compte, journal de liaison | `BO/ma-situation/liaison-atak`, `BO/ma-situation/appareils` (+ `retirer`), `/account/security/devices`, `/atak/liaison` | Opérateur › Terrain › Ma liaison ATAK (`NAV:473`) | Liaison et appareils du membre | Exception ma-situation ; Auth | Même code de révocation à deux endroits | 03 T-04 |
| T-05 | Installation des mods (membre) | `GET /atak/mod`, `/mod/guide`, `/mod/formation`, `/mod/download`, `/atak/tuto` | Méga-menu | Installer les mods | `$mwAtakMemberOnly` | Versant membre de A-16a | 03 T-05 |
| T-06 | Poste TOC et plans d'opération | `GET /atak/toc`, `/atak/toc/plans`, `/aide`, `/{id}`, `/lien/{token}`, exports ; API `/atak/toc/api/*` | Hors menu BO ; méga-menu | Console de centre d'opérations, éditeur de plans | `atak.toc.use|manage` ; ACL par plan | `atak_toc_*`, `atak_plan*`, relief | 03 T-06 |
| T-07 | Passerelle de cartes | `GET /atak/passerelle` ; POST creer, rejoindre, valider, annuler | Hors menu | Partager une carte entre communautés | `$mwAtakMemberOnly` ; gestion `admin.*` | `atak_map_gateways` | 03 T-07 |
| T-08 | Applications web du téléphone | `/m`, `/m/{token}`, `/m/cr`, `/m/radio*`, `/m/musique`, `/m/compte-rendu`, `/m/carte`, `/m/briefs`, `/m/controle`, `/m/admin*` | Aucune (QR en jeu) | Applications du téléphone en jeu | Jeton mobile ; `/m/admin` : rôle calculé | `atak_mobile_links` | 03 T-08 |
| T-09 | Connexion téléphone par code | `/connect`, `/connect/code`, `/connect/{token}/*` ; alias `/atak/connect*` ; `/atak/mobile[/{module}]` | Aucune | Ouvrir la carte sur un vrai téléphone | Sans auth au départ ; session d'appairage | `tactical_phone_pairings` ; aucun écran ne liste ni ne révoque | 03 T-09 |
| T-10 | 9-LINE / 5-LINE (JTAC) | `GET /9-line`, `/feed`, `/appareils`, `/equipages`, `/qr.png` ; POST store, indicatif, supprimer, vider | ATAK › Supervision › 9-LINE (`NAV:309`, sort du shell) | Demandes d'appui aérien | `AtakPlanAccess` ; gestion `atak.*|admin.*` | `atak_nine_line*`, `atak_air_assets` | 03 T-10 |

Écrans secondaires « terrain » :

| ID | Nom | Route | Constat |
|---|---|---|---|
| T-S1 | Télécommande drone | `/telecommande-drone`, `/qr.png` | `AtakPlanAccess` |
| T-S2 | Opérateur terrain | `/operateur/terrain` | Raccourcis épinglés |
| T-S3 | Wiki ATAK | `/wiki/atak*` | Public |
| T-S4 | Bibliothèque de marqueurs | `/documentation/marqueurs` | Auth |
| T-S5 | Visionneuse d'images de reconnaissance | `/recon/images/{id}` | Sans middleware de route |
| T-S6 | Page ATAK natif | `/atak-natif` | Statique |

### 3.4 Données tactiques sans écran de gestion (audit 03, §1.C-API)

| ID | Domaine | API | Écran de consultation | Écran de gestion |
|---|---|---|---|---|
| API-1 | Appui-feu | `/api/fire-support/units`, `/calculate` | `/overwatch` | aucun |
| API-2 | IFF | `/api/iff/*` | `/atak`, `/overwatch` | aucun |
| API-3 | Logistique | `/api/logistics/*` ; doublon `/api/operations/logistics/*` | `/overwatch` | aucun |
| API-4 | Ravitaillement | `/api/atak/logistics*` | télémétrie Overwatch | aucun |
| API-5 | Zones dangereuses | `/api/danger-zones` | `/overwatch` | aucun |
| API-6 | Relecture | `/api/replay/*` | `/atak`, `/overwatch` | aucun (lien depuis le cycle) |
| API-7 | QRF, MEDEVAC | `/api/atak/qrf`, `/api/atak/medevac` | `/atak` | aucun |
| API-8 | Cartes personnalisées | `/api/custom-maps` | `/overwatch` | aucun |
| API-9 | Terrain (MNT) | `/api/atak/terrain/*` (sans middleware) | `/atak`, TOC | aucun (mod) |
| API-10 | Tuiles | `/map-data/*`, `/map-photo/*`, `/api/atak/map-photo/upload` | toutes les cartes | aucun (outil en ligne de commande) |
| API-11 | Catalogue des cartes | — (`atak_maps`, étape de migration) | sélecteurs de carte | aucun |

### 3.5 JNET : extranet d'unité

Toutes les routes JNET sont en Auth seul ; aucun droit vérifié (voir S1). Menu : Opérations › Extranet d'unité (`NAV:278-304`), masqué en profils Effectifs et ATAK ; menu opérateur.

| ID | Nom | Route(s) | Menu | Objectif | Droits | Dépendances | Fiche |
|---|---|---|---|---|---|---|---|
| J-01 | Espace commun | `GET /jnet` | Extranet › Espace commun | Tableau d'unité | Auth | `planning_entries`, `sse_intel_events`, `sse_field_notes` | 03 J-01 |
| J-02 | Fiche et espace d'unité | `GET /jnet/unite`, `/jnet/u/{id}`, `/entree`, `/personnaliser` ; POST `personnaliser`, `/jnet/u/{id}/echanges`, `/jnet/echanges/{id}/lu` | Extranet › Unité › Fiche d'unité | Page de l'unité, échanges | Personnaliser : `organization.orbat.manage`, TBA ou commandement | `jnet_unit_spaces`, `jnet_exchanges` | 03 J-02 |
| J-03 | Opérations | `GET /jnet/operations`, `/{id}` | Extranet › Opérations | Miroir en lecture du tableau opérationnel | Auth | `planning_entries`, `operational_postures` | 03 J-03 |
| J-04 | Personnel | `GET /jnet/personnel`, `/{id}` | Extranet › Unité › Personnel | Annuaire (120 membres actifs) | Auth | même requête qu'Effectifs | 03 J-04 |
| J-05 | Renseignement | `GET /jnet/renseignement` | Extranet › Opérations › Renseignement | Flux de renseignement | Auth (S1) | `sse_intel_events`, `sse_field_notes` | 03 J-05 |
| J-06 | Cibles prioritaires | `GET /jnet/cibles`, `/{id}` | Extranet › Opérations › Cibles | Cibles | Auth, sans ACL (S1) | `sse_interest_cases` | 03 J-06 |
| J-07 | Exploitation | `GET /jnet/exploitation` | Extranet › Opérations › Exploitation | Compteurs | Auth (S1) | `sse_*` | 03 J-07 |
| J-08 | Bibliothèque | `GET /jnet/bibliotheque` | Extranet › Ressources › Bibliothèque | Documents publiés, articles, formations | Auth | `documents`, `tenant_mini_articles` | 03 J-08 |
| J-09 | Messagerie d'unité | `GET /jnet/courrier`, `/nouveau` ; POST nouveau, lu, réponse | Extranet › Ressources › Messagerie | Fils de discussion | Auth + module `messages` | mêmes tables que `/messages` | 03 J-09 |
| J-10 | Système | `GET /jnet/systeme` ; `POST /bascule` | Extranet › Ressources › Système | Basculer d'espace | Auth | `PortalAccessChoice` | 03 J-10 |
| J-A | Alias obsolètes | `/jnet/theatre`, `/sources`, `/intentions`, `/effectifs`, `/applications`, `/sync` | — | Redirections ; 6 vues orphelines | — | — | 03 §1.D |

### 3.6 Renseignement : back-office et portail SSE

| ID | Nom | Route(s) | Menu | Objectif | Droits | Dépendances | Fiche |
|---|---|---|---|---|---|---|---|
| R-01 | Accès au renseignement (codes SSE) | `GET/POST BO/renseignement/acces`, `/{id}/revoquer` ; `BO/renseignement` → redirection | Rôles et droits › Accès au renseignement (`NAV:225`) | Codes d'accès, journal | Auth + `atak.sse.grant|admin.*` | Doublon de R-05a | 03 R-01 ; 02 F14 |
| R-02 | Entrée « commandement » SSE | `GET BO/renseignement/codes`, `/atak/sse/commandement` | Méga-menu seulement | Entrer sans code pour le personnel habilité | — | Deux URL | 03 R-02 |
| R-03 | Fichiers SSE (administration) | `GET BO/renseignement/fichiers`, `/{tab}` (fiches, extractions, journal, reglages) ; POST fusion, extractions, reglages | Droits et permissions › Fichiers SSE (`NAV:226`) | Fiches, doublons, extractions de téléphones | Auth + `atak.sse.files.admin|admin.*` | `sse_identities`, `sse_file_*` | 03 R-03 |
| R-04 | Remontées (CR et fiches terrain) | `GET BO/remontees`, `/joueurs`, `/joueurs/{key}`, `/{source}/{id}` ; POST `/{action}` | Opérations › Comptes rendus › Remontées des joueurs (`NAV:260`) | Suivi, attribution, exploitation | Auth + `atak.sse.case.manage|grant|admin.*` | `atak_tactical_reports`, `sse_field_notes`, `intel_intake_*` | 03 R-04 |

Portail SSE `/atak/sse/*` (303 routes ; `$mwSsePortal` ; méga-menu « Fichiers et renseignement » ; aucune entrée dans le menu BO) :

| ID | Famille | Routes principales | Objectif |
|---|---|---|---|
| R-05a | Sas et accès | `/atak/sse`, `commandement`, `confidentialite`, `quitter`, `acces` | Entrer, sortir, gérer les codes |
| R-05b | Accueil, opérations, espace de travail, recherche | `accueil`, `operations`, `workspace`, `recherche` | Tour de contrôle du renseignement |
| R-05c | Identités | `identites`, `identites/{id}` | Annuaire des identités |
| R-05d | Papiers (registre) | `papiers`, `papiers/identites*`, `documents*`, `pays*`, `controles*`, `tableau`, `membres*` | Registre des papiers, contrôles du jeu |
| R-05e | Papiers (atelier) | `papiers/atelier*`, `kits-types*`, `profils-ia*`, `preparation`, `briefs`, `echanges` | Fabriquer papiers et kits de couverture |
| R-05f | Fichiers | `fichiers`, `requete`, `journal`, `seek`, `mentions`, `sujet/{id}`, `photos`, `comparer`, `telephones*`, `lore*` | Consultation des fichiers |
| R-05g | Objets | `objets`, `objets/nouveau`, `objets/{type}` | Registre des objets |
| R-05h | Chronologie, anomalies, rapprochements | `chronologie`, `anomalies`, `rapprochements` | Analyse |
| R-05i | Campagnes | `campagnes`, `partagees`, `{id}` | Campagnes de renseignement |
| R-05j | Fiches terrain | `fiches`, `nouvelle`, `{id}` | Fiches terrain |
| R-05k | Collecte, validation, rapports | `collecte`, `validation`, `rapports` | Files de traitement |
| R-05l | Documents, bibliothèque, éditeur, présentation | `documents*`, `bibliotheque`, `editeur`, `presentation` | Production documentaire SSE |
| R-05m | Dossiers | `dossiers*`, `{id}/*` ; `sceau/{token}` public | Dossiers d'enquête |
| R-05n | Intérêt et transmissions | `interet*`, `transmissions*` (+ relais `transmissions/relais*`) | Cibles prioritaires avec ACL |
| R-05o | Personnes, sites, croisements, maître du jeu, toiles | `personnes`, `sites*`, `croisements`, `maitre-jeu`, `toiles*` | Réseaux et relations |
| R-05p | Exploitation numérique | 21 routes (`supports`, `telephone`, `ordinateur`…) | Laboratoire numérique |
| R-05q | Développement Arma | `dev`, `dev/modeles*` | Modèles 3D d'objets |
| R-05r | Guide | `guide`, `documentation` | Aide |

### 3.7 Identité et papiers des membres

| ID | Nom | Route(s) | Menu | Objectif | Droits | Fiche |
|---|---|---|---|---|---|---|
| I-01 | Hub opérateur | `GET /operateur` ; POST favoris, `acces/demander`, `acces/annuler` | Lien depuis `/c2` | Outils de l'opérateur, demande d'accès | Auth | 03 I-01 |
| I-02 | Mes identités | `GET /operateur/identite` ; POST identites, photo, archiver, activer, principale | Hors menu | Gérer ses identités | Auth (limité à soi, HYPOTHÈSE) | 03 I-02 |
| I-03 | Mes papiers (opérateur) | `GET /operateur/identite/documents`, `{id}/specimen` | Hors menu | Demander des papiers | Auth | 03 I-03 |
| I-04 | Mes papiers (coque BO) | `GET BO/ma-situation/mes-papiers`, `couverture/{exportId}/telecharger` ; POST couverture | Opérateur › Ma situation › Mes papiers (`NAV:463`) | Papiers et couverture | Exception ma-situation | 03 I-04 |
| I-05 | Papiers des membres (personnel SSE) | `/atak/sse/papiers/membres*` | Méga-menu, recherche | Créer et éditer les documents des membres | `$mwSsePortal` + classification + `sseManage` | 03 I-05 |
| I-A | Couverture (ancienne) | `/operateur/identite/couverture*` | — | Redirection systématique | Auth | 03 §1.F |

### 3.8 Équipement, dotations, kits, tenues

| ID | Nom | Route(s) | Menu | Objectif | Droits | Dépendances | Fiche |
|---|---|---|---|---|---|---|---|
| E-01 | Équipement (hub « Arsenal et tenues ») | `GET /equipment` (`?espace=…`, `?tab=…`) ; JSON tenues, collections, fiches, dotation ; `ace-export` ; POST collections, tenues, `mon-kit/tenue`, kits, fiches, dotation ; doublons `/equipment/wardrobes/*` | ATAK › Matériel › Arsenal et tenues (`NAV:338`, sort du shell) ; méga-menu | Tenues, kits, catalogue du matériel | Auth + formule ; `canManageKits`, `canManageCatalog`, `canManageDotation` | Mod Arma ; `destroyKit` sans `KitsAccess` | 03 E-01 |
| E-02 | Configurateur d'équipement | `GET /equipment/configurateur` (+ catalogue, script SQF, sources, versions, membre) ; POST import, analyser, enregistrer, restaurer, favori, pré-kits | Hors menu (depuis E-01) | Composer tenues, modèles, kits | Auth + formule ; `canManageKits` | `script-export.sqf` lu par le mod | 03 E-02 |
| E-03 | Assistant de tenue | `GET /equipment/assistant` ; POST enregistrer | Hors menu | Créer une tenue ou un kit guidé | Auth ; `canManageKits` pour un kit | `KitsAccess` | 03 E-03 |
| E-04 | Équipement des joueurs | `GET BO/equipement-joueurs`, `/icones/{file}` ; POST `kits/{steam}/tenue` | ATAK › Matériel › Équipement des joueurs (`NAV:336`) | Ce que les joueurs portent réellement | ORG + `admin.organization|admin.access` | `game_item_*` | 03 E-04 |
| E-05 | Kits par rôle | `GET BO/kits-roles`, `/nouveau`, `/{id}` ; POST store, update, supprimer, publier | ATAK › Matériel › Kits par rôle (`NAV:337`) | Kits prêts à porter par fonction, publiés comme tenues | ORG + `admin.*` (refuse `personnel.equipment.manage`, que E-01 accepte) | `game_role_kits` | 03 E-05 |
| E-06 | Dotation (référentiel et attributions) | `GET BO/referentiels/dotation`, `/{id}` ; POST store, attribuer, objet-jeu, archive, statut | Référentiels › Dotation (`NAV:218`) | Dotation type par fonction, suivi nominatif | ORG (exception `personnel.equipment.manage|assignments.manage`) ; aucun Gate ; module personnel | `personnel_equipment_assignments` | 03 E-06 |
| E-07 | Ma dotation | `GET BO/ma-situation/dotation` | Opérateur › Ma situation › Ma dotation (`NAV:455`) | Dotation du membre | Exception ma-situation | — | 03 E-07 |
| E-08 | Tenues mises en avant | `GET BO/dashboard-tenues`, `/create`, `/{id}/edit` ; POST store, update, delete, move | ATAK › Matériel › Tenues mises en avant (`NAV:339`) | Tenues affichées au tableau de bord membre | Auth + `dashboard.pins.manage|admin.*` | `tenant_dashboard_wardrobe_pins` | 03 E-08 |
| E-S1 | Fiche d'une classe d'équipement | `/equipment/{slug}` | — | Lecture seule | Auth | — | 03 |
| E-S2 | Page « équipement » publique | `/equipement` | — | Maquette statique « FOG » sans données | Public | — | 03 |
| E-S3 | « Ressources » | `BO/ressources` | — | En-tête déclaré, aucune route GET | — | — | 03 |

---

## 4. Organisation, communications, plateforme (audit 04)

### 4.1 Création et démarrage

| ID | Nom | Route(s) | Menu | Objectif | Droits | Dépendances | Fiche |
|---|---|---|---|---|---|---|---|
| ORG-A1 | Guide de création | `GET /communities/create/guide` | Site public | Expliquer la création | Public | — | 04 A1 |
| ORG-A2 | Assistant de création de communauté | `GET/POST /communities/create`, `/create/preview`, `/create/pay` (lien mort), `/create/complete` | Site public | Créer tenant, fondateur, rôles, ORBAT, grades ; choisir une formule | Auth + CSRF | Paiement, `WizardOrbatPresets`, `GradeSystemCatalog` | 04 A2 |
| ORG-A3 | Ancien assistant post-création | `GET/POST /c/{slug}/setup` | Hors menu (aucun lien) | Fuseau horaire, fin d'onboarding | Auth seul (S5) | `tenants.settings` | 04 A3 |
| ORG-A4 | Configuration initiale | `GET BO/configuration-initiale` ; POST save, dismiss, complete | Communauté › Configuration initiale (`NAV:732`) ; bandeau d'accueil | Checklist des réglages essentiels | ORG, sans contrôle interne | Logo écrit dans deux colonnes | 04 A4 |
| ORG-A5 | Besoin d'aide pour démarrer ? | `GET BO/onboarding-recovery`, `POST /apply` | Hors menu (action rapide, Configuration) | Rattraper les anciennes communautés | ORG | Troisième assistant | 04 A5 |
| ORG-A6 | Mises à jour de configuration et Nouveautés | `GET BO/mise-a-niveau` (`?onglet=nouveautes`) ; POST demarrer, ignorer, terminer, rouvrir, continuer | Réglages › Mises à jour (`NAV:743`) ; redirection forcée une fois par session | Nouveaux modules ou réglages publiés par la plateforme | `tenant.configuration.manage`, `admin.settings.manage`, `admin.*`, `site.support` | `ConfigurationUpdateIntroMiddleware` | 04 A6 |
| ORG-A7 | Premiers pas (membre) | `GET /onboarding` ; POST persona, step, complete | Portail | Accueil du membre par persona | Auth | — | 04 A7 |

### 4.2 Paramètres, identité visuelle, page publique

| ID | Nom | Route(s) | Menu | Objectif | Droits | Dépendances | Fiche |
|---|---|---|---|---|---|---|---|
| ORG-B1 | Paramètres de la communauté (6 onglets) | `GET/POST BO/community` ; `BO/community/inscription` ; `POST BO/organisation/parametres`, `/organisation/profil`, `accueil-connexion` (5) | Communauté › Vitrine et portail › Paramètres (`NAV:367`) | Identité, vitrine, inscription, accueil de connexion, portail, profil | ORG, aucun contrôle interne (S6) | `TenantTypeSwitchService` retire des droits | 04 B1 |
| ORG-B2 | Page d'accueil publique (« Fiche registre & contact ») | `GET/POST BO/community/presentation` | Vitrine et portail › Page d'accueil publique (`NAV:368`) | Vitrine `/c/{slug}` : ~110 champs et dossier de candidature | ORG, aucun contrôle interne | `TenantCommunityProfileService` | 04 B2 |
| ORG-B3 | Éditeur de page en place | POST `/c/{slug}/page-editor/draft|publish|discard|block|image` | Aucun écran BO (depuis `/c/{slug}`) | Blocs libres sur la vitrine | `admin.organization|admin.access` | `PublicPageBuilder` | 04 B3 |
| ORG-B4 | Annoncer sur Discord | `GET BO/community/discord` ; POST demander, annuler, retirer | Vitrine et portail › Annoncer sur Discord (`NAV:370`) | Demander une mise en avant sur le Discord de la plateforme | ORG ; actions `admin.organization` | Modération en ORG-H10 | 04 B4 |
| ORG-B5 | Médias de la communauté | `GET BO/media`, `/{id}` + 6 POST | Vitrine et portail › Médias (`NAV:373`) | Médias et collections | ORG (exception `media.*`) | `/c/{slug}/medias` | 04 B5 |
| ORG-B6 | Raccourcis du portail | `GET BO/dashboard-pins`, `/create`, `/{id}/edit` + POST | Vitrine et portail › Raccourcis du portail (`NAV:369`) | Épingles du tableau de bord membre | Auth + `DashboardPinsAccess` | — | 04 B6 |
| ORG-B7 | Configuration (hub de liens) | `GET BO/configuration` ; POST `member-role-display`, `debug-recruit-sync` | Réglages › Configuration (`NAV:739`) | Liens, affichage des rôles, historique, outil de développement | ORG ; affichage des rôles `admin.*` | — | 04 B7 ; 02 F26 |
| ORG-B8 | Indicateurs d'usage et Conversion | `GET BO/analytics`, `/analytics/conversion` | Indicateurs (`NAV:238-239`) | Usage et entonnoir du tenant | ORG + formule `analytics` | même méthode pour 2 routes | 04 B8 |
| ORG-B9 | Journal d'audit (organisation) | `GET BO/audit`, `/{id}` | Droits et permissions › Journal d'audit (`NAV:611`) | Filtrer le journal | ORG (exception `admin.audit.view`) | `AuditLogRepository` | 04 B9 ; 02 F16 |
| ORG-B10 | Messages de la plateforme | `GET BO/messages-plateforme`, `/{id}/lien` ; POST fermer, acquitter | Réglages › Messages de la plateforme (`NAV:744`) ; cartes de l'accueil | Recevoir les messages de la plateforme | ORG ; acquittement `admin.*` | `platform_bo_messages*` | 04 B10 |
| ORG-B11 | Musique de l'organisation | `GET BO/ressources/musique` + 4 POST | Communauté › Musique (`NAV:731`) | Bibliothèque musicale | RES | Même vue que `/admin/musique` | 04 B11 |

### 4.3 Communications et événements côté communauté

| ID | Nom | Route(s) | Menu | Objectif | Droits | Dépendances | Fiche |
|---|---|---|---|---|---|---|---|
| ORG-C1 | Centre de diffusion (« Annonces ») | `GET BO/alerts` ; POST apercu, diffuser, centre/modifier, centre/retirer | Communication › Annonces (`NAV:378`) | Diffusion multicanal, liste en cours | ORG (exception `atak.command.alert|mission_cycle.manage`) + formule `alerts` ; filtre par canal | — | 04 C1 |
| ORG-C2 | Nouvelle annonce / bandeau | `GET BO/alerts/create`, `/{id}/edit` ; POST store, update, delete, `affichage-a-venir` | Hors menu (accueil, C1) | Bandeau avec image, notification Discord | ORG, aucun Gate | `tenant_alerts` | 04 C2 |
| ORG-C3 | Mini-articles | `GET BO/articles`, `/create`, `/{id}/edit` + 3 POST | Communication › Mini-articles (`NAV:379`) | Articles courts | ORG | `tenant_mini_articles` | 04 C3 |
| ORG-C4 | E-mails aux membres | `GET BO/communications`, `/history`, `/templates*`, `/groups*` + POST | Communication › E-mails (`NAV:383-386`) | Campagnes, modèles, listes de diffusion | ORG (exception `comms.*`) ; modèles `comms.email_templates.manage` | `tenant_email_*` | 04 C4 |
| ORG-C5a | Messagerie interne | `/messages`, `/messages/{id}` | Portail | Fils de discussion | Auth + formule `messages` | `tenant_message_*` | 04 C5 |
| ORG-C5b | Boîte de réception | `/boite-reception` | Opérateur › Administratif (`NAV:500`) | Messages, notifications, courrier | Auth + `courrier.view` | — | 04 C5 |
| ORG-C5c | Alertes membre | `/alertes` | Portail | Lire les annonces | Auth | — | 04 C5 |
| ORG-C6 | Courrier (correspondance signée) | `/courrier`, `signature`, `editor`, `read/{id}`, `notifications`, `templates`, `presets`, `history`, `archives`, `traceabilite`, `my-signatures`, `verify` ; `BO/courrier/traceabilite` | Réglages › Traçabilité du courrier (`NAV:745`) seulement | Courrier signé | Formule `courrier` (Standard) ; deux middlewares pour une page | — | 04 C6 |
| ORG-C7 | Préférences de notification | `GET /account/preferences` ; API `/api/me/preferences` | Opérateur › Paramètres (`NAV:526`) | Préférences du membre | Auth | aucun réglage d'organisation par défaut | 04 C7 |
| ORG-D1 | Événements (côté communauté) | voir O-03 | — | Inclut le jeton du bot Discord et l'appairage du compagnon | — | `settings.integrations.event_discord_bot_token` | 04 D1 |
| ORG-D2 | Fiche événement, réponses nominatives, bilan | voir O-03, O-05, O-04 | — | Destination Discord par événement | — | — | 04 D2 |

### 4.4 Documents, bibliothèque, partages, académie

| ID | Nom | Route(s) | Menu | Objectif | Droits | Fiche |
|---|---|---|---|---|---|---|
| ORG-E1 | Gestion documentaire | `/documents/gestion*` | Formations et documents › Gestion documentaire (`NAV:362`) | Documents, arborescence, historique, accès | `$mwDocuments` + Gate interne | 04 E1 |
| ORG-E2 | Documents (lecture membre) | `/documents`, `/documents/{slug}`, `/documents/collections` | Portail | Lire | `documents.view` | 04 E2 |
| ORG-E3 | Mes documents | `/documents/mes-documents` | Portail | Documents reçus | `documents.view|doctrine.view` | 04 E3 |
| ORG-E4 | Fichiers partagés | `/partage` et `BO/partage` | Formations et documents › Fichiers partagés (`NAV:361`) | Seconde bibliothèque de fichiers | Auth / RES selon l'URL | 04 E4 |
| ORG-E5 | Doctrine et SOP | `BO/doctrine` (onglets conformité, nomenclature, types) ; `BO/doctrine/referentiel` | Formations et documents › Doctrine et SOP, Référentiel doctrinal (`NAV:359-360`) | Doctrine | ORG | 04 E5 |
| ORG-E6 | Académie et formations | `/academy`, `/academy/{space}`, `/academy/catalogue/assistant` ; écrans réels `/formation/*` ; anciens `/admin/training/*`, `/formations*` | Formations et documents › Formations (`NAV:698`, si `$canTraining`) | Formations | Formule `training` | 04 E6 |

### 4.5 Formules, intégrations, modération

| ID | Nom | Route(s) | Menu | Objectif | Droits | Fiche |
|---|---|---|---|---|---|---|
| ORG-F1 | Monter en gamme | `GET /platform/upgrade`, `POST /checkout`, `GET /complete` ; `GET /paiement/{ref}` (+ `qr.png`, `declarer`) ; `POST /compte/formule-offerte/{id}/vu` ; webhooks Stripe, PayPal | Hors menu (vue de refus) | Changer de formule, payer | `canManageBilling` | 04 F1 |
| ORG-F2 | Aperçu d'une formule (super-admin) | `POST /admin/apercu-formule`, `/vue`, `/quitter` | Hors menu | Voir une communauté avec une autre formule | SYS | 04 F2 |
| ORG-G1 | Intégrations externes | `GET BO/integrations` + 18 POST (Discord, salons, e-mails, relais SSE, jetons) ; `BO/integrations/emails` | Réglages › Intégrations (`NAV:741`, si tenant > 1 et `admin.*`) | Discord, SMTP, relais SSE, clés d'API | ORG + `admin.organization` à chaque action ; clés : formule `advanced_integrations` | 04 G1, G2 |
| ORG-J1 | Signalements membres | `BO/signalements` + 5 POST | Communauté › Modération (`NAV:535`) | Traiter les signalements, sanctionner | `ModerationConsoleMiddleware` | 04 J |
| ORG-J2 | Fichiers en quarantaine | `BO/content-moderation` + 4 POST | Communauté › Modération (`NAV:538`) | Fichiers signalés, avertir ou restreindre | `ModerationConsoleMiddleware` | 04 J |
| ORG-I3 | Hub inter-communautés, bourse | `/hub`, `/hub/comparer`, `/hub/c/{slug}`, `/hub/bourse` | Portail | Comparer, échanger | Auth ; avis `CooperationAccess` | 04 I3 |
| ORG-I4 | Fédération | `/federation` | Hors menu | Page statique | Auth | 04 I4 |

### 4.6 Accueil du back-office

| ID | Nom | Route(s) | Menu | Objectif | Droits | Dépendances | Fiche |
|---|---|---|---|---|---|---|---|
| ORG-K1 | Accueil gestionnaire | `GET BO` ; `BO/accueil/bloc*` ; `BO/tableaux/*` (feuilles épinglées) | Tableau de bord (`NAV:543`) | Bandeaux, salutation, grille de 15 blocs personnalisables | ORG, ouvert à tout membre sur ce chemin ; variante si `admin.*|site.support` | Calcul de tous les blocs même masqués ; déjà engagé : un titre par carte, sans surtitre (PR #1600, #1601, #1602) | 04 §2 |
| ORG-K2 | Accueil opérateur | `GET BO` (variante) | Tableau de bord du menu opérateur | Synthèse personnelle | Tout membre | `operatorOverview()` | 04 §2.1 |

### 4.7 Super-administration de la plateforme `/admin/*` (SYS)

| ID | Écran | Route GET | Menu | Objectif |
|---|---|---|---|---|
| ORG-H1 | Pilotage du site et console d'exploitation | `/admin` (`?onglet=…`) | `PA:71` | Santé, chiffres, activité, migrations, sauvegardes |
| ORG-H2 | Recherche | `/admin/recherche` | `PA:72` | Comptes et communautés |
| ORG-H3 | Indicateurs transverses | `/admin/analytics` | `PA:73` | Indicateurs plateforme |
| ORG-H4 | Retours interface, avis et traductions | `/admin/system/retours-interface`, `/admin/system/avis-plateforme` | `PA:74-75` | Retours des membres |
| ORG-H5 | Annuaire et fiche d'une communauté | `/admin/tenants`, `/admin/tenants/{id}/edit` | `PA:78` | Identité, profil, formule, logo |
| ORG-H6 | Retrait d'une communauté | `/admin/tenants/{id}/retrait` | Hors menu | Archiver, restaurer, supprimer |
| ORG-H7 | Comptes et intervention dans une communauté | `/admin/tenants/{id}/comptes`, `/admin/system/tenants/{id}/intervention` (+ journal) | Hors menu | Intervenir avec journal et annulation |
| ORG-H8 | Organisations à supprimer | `/admin/organisations-a-supprimer` | `PA:82` | Communautés de 1 ou 2 membres |
| ORG-H9 | Restauration d'une communauté | `/admin/system/tenant-recovery` | `PA:83` | Restaurer depuis une sauvegarde |
| ORG-H10 | Annuaire public et vitrine Discord | `/admin/system/annuaire`, `/annuaire/discord` | `PA:79-80` | Épingles, modération de la vitrine |
| ORG-H11 | Contributeurs | `/admin/system/contributeurs` | `PA:81` | Rôles de contributeur |
| ORG-H12 | Formules | `/admin/system/subscription-plans` (+ edit) | `PA:84` | Paliers, modules, plafonds |
| ORG-H13 | Paiements | `/admin/paiements` (`?vue=…`) ; `/admin/paiements/membres` (JSON) | `PA:85` | Paiements, abonnements, dons |
| ORG-H14 | Formules offertes | `/admin/formules-offertes` | `PA:86` | Offrir une formule |
| ORG-H15 | Démo et NDA | `/admin/system/demo-nda` | `PA:87` | Codes de démo |
| ORG-H16 | Comptes | `/admin/users` (+ `person`, `{id}/edit`, `donnees`, export) | `PA:90` | Tous les comptes |
| ORG-H17 | Administrateurs et rôles du site | `/admin/system/administrateurs-site`, `/admin/site-roles`, `/admin/roles*`, `/admin/system/advanced-fiche-edit` | `PA:91-94` | Rôles de site |
| ORG-H18 | Outils du portail de candidatures | `/admin/system/recruitment-portal-tools` | `PA:95` | Diagnostic |
| ORG-H19 | Restrictions de la plateforme | `/admin/system/blocklist`, `/member-sanctions`, `/noms-inappropries` | `PA:98-100` | Blocages, sanctions, noms |
| ORG-H20 | Doublons Steam | `/admin/steam-doublons` | Hors barre (lien depuis le parc) | Arbitrer les comptes Steam en double |
| ORG-H21 | Bandeaux plateforme | `/admin/system/alerts` (+ create, edit) | `PA:103` | Bandeaux et e-mails |
| ORG-H22 | Messages aux communautés | `/admin/system/messages-communautes` (+ create, edit) | `PA:104` | Messages reçus en ORG-B10 |
| ORG-H23 | E-mails, rétrospective, lettre | `/admin/system/e-mails`, `/retrospective`, `/admin/newsletter` | `PA:105-107` | Quota, bilan annuel, inscrits |
| ORG-H24 | Coopération plateforme | `/admin/system/cooperation/catalog` (+ create), `/announcements` (+ edit) | `PA:108-109` | Modèles par défaut de coopération |
| ORG-H25 | Musique du site | `/admin/musique` | `PA:110` | Bibliothèque |
| ORG-H26 | Réglages du site | `/admin/settings` | `PA:113` | Configuration, version minimale du mod |
| ORG-H27 | Référentiel militaire | `/admin/system/military-referential` (+ create) | `PA:114` | Organisations, commandements |
| ORG-H28 | Intégrations de la plateforme | `/admin/system/integrations` | `PA:115` | Discord, compagnon, rôles liés, SMTP |
| ORG-H29 | Tâches automatiques | `/admin/system/cron` | `PA:116` | Cron |
| ORG-H30 | Mises à jour et déploiement | `/admin/system/updates`, `/admin/system/deployment*` (campagnes, nouveau, communautés) | `PA:119-122` | Paquets, campagnes |
| ORG-H31 | Registre des données | `/admin/system/donnees` (+ api, outils, correlations) | `PA:131` | Registre |
| ORG-H32 | Charge et stockage | `/admin/system/surcharge`, `/admin/system/storage` (+ donnees, ligne) | `PA:132-133` | Charge, disque, navigateur de base |
| ORG-H33 | Maintenance | `/admin/maintenance` (+ create, edit, apercu, audit) | `PA:134` | Fenêtres de maintenance |
| ORG-H34 | Journal d'audit plateforme | `/admin/audit` (+ `{id}`, rollback, alert) | `PA:135` | Journal de toute la plateforme |
| ORG-H35 | Mod Overwatch | `/admin/parc-atak*` (+ fiche), `/admin/atak-beta`, `/admin/atak-mod-reports`, `/admin/system/alertes-mod` | `PA:125-128` | Parc complet, bêta, rapports du mod |

---

## 5. Pages relevées par la navigation seule (audit 05 et `orphans.txt`)

Ces pages figurent dans le menu ou dans `orphans.txt` sans fiche détaillée dans les audits 01 à 04. Leur contenu n'a pas été audité ; seules leur place et leur route sont des FAIT.

| ID | Nom | Route(s) | Menu | Remarque | Source |
|---|---|---|---|---|---|
| NAV-01 | Qualité des données | `BO/organisation/qualite-donnees` | Indicateurs › Qualité des données | Visée par le bandeau des photos perdues de l'accueil | 05 §1.3 ; 04 §2.3 |
| NAV-02 | Catalogue de l'organisation (+ modèle, aperçu, historique) | `BO/organisation/catalogue`, `/modele`, `/apercu` | Organisation › Structure › Catalogue | Modèles d'organisation | 05 §1.3 ; orphans 1731, 1733 |
| NAV-03 | Types d'entité | `BO/organisation/types-entite` | Organisation › Structure › Types d'entité | — | 05 §1.3 |
| NAV-04 | Disponibilité | `BO/organisation/disponibilite` | Organisation › Disponibilité | Masqué en profil Effectifs | 05 §1.3 |
| NAV-05 | Référentiel des compétences | `BO/referentiels/competences` | Référentiels › Compétences › Référentiel des compétences | — | 05 §1.3 |
| NAV-06 | Cursus pilote | `BO/referentiels/qualifications/cursus-pilote` | Référentiels › Compétences › Cursus pilote | Chemin d'écriture d'une qualification | 05 §1.3 ; 01 F32 |
| NAV-07 | Référentiel des qualifications (+ création, émetteurs, catalogue, fiche, attribuer, prérequis détectés, matrice, droits) | `BO/referentiels/qualifications`, `/create`, `/emetteurs`, `/catalogue`, `/catalogue/fiche`, `/attribuer`, `/prerequis-detectes`, `/matrice/case` | Référentiels › Compétences › Qualifications | Sous-écrans hors menu ; droits liés sans effet (S8) | 05 §2.3 ; orphans 2091-2145 |
| NAV-08 | Suivi roleplay : bureau de suivi | `BO/roleplay-followup` (`?onglet=dossiers|immersion|phases`) ; anciennes `BO/roleplay/immersion`, `/regles-phases` | Roleplay › Suivi roleplay › Bureau de suivi | Parcours d'immersion RP et phases RH | 05 §1.3 ; 01 F24 |
| NAV-09 | Suivi roleplay : échéances | `BO/roleplay-followup/echeances` | Roleplay › Échéances | — | 05 §1.3 |
| NAV-10 | Sessions Arma | `BO/roleplay/sessions` | Roleplay › Sessions Arma | Point de création de sessions de jeu (voir D17) | 05 §1.3 ; 03 D17 |
| NAV-11 | Catégories (+ création) | `BO/categories`, `/create` | Communauté › Vitrine et portail › Catégories | — | 05 §1.3 ; orphans 2017 |
| NAV-12 | Export pour contrôle | `BO/conformite/export-dossier` | Réglages › Export pour contrôle | Fil d'Ariane « Opérations » | 05 §1.3, §4.1 |
| NAV-13 | Favoris du menu | `BO/menu/favoris` | Groupe FAVORIS (12 au plus) | `BackOfficeMenuController` | 05 §1.3 ; 04 B6 |
| NAV-14 | Formations : écrans de gestion | `/formation/*` ; 27 anciennes adresses `BO/ressources/training/*` (charte RH, studio, versions, import, cours, inscriptions, rapports, retours, certificats, gabarit, audit, publications, compétences commandement, instructeur, formateur, bureau du personnel, pôle formation, validation, sections) et `BO/ressources/training` | Formations et documents › Formations | Les anciennes adresses ne font que rediriger | 05 §2.1 ; orphans 2906-2952, 2942 |
| NAV-15 | Doctrine : export des lectures, publication | `BO/doctrine/export-lectures`, `BO/documents/publier` ; ancienne `BO/documents/compliance` | Hors menu (depuis Doctrine) | — | orphans 1913, 1919 ; 05 §2.5 |
| NAV-16 | Réglages du recrutement | `BO/recruitments/settings`, `/messages-prefaits`, `/discord-questions`, `/equipe`, `/liens-invitation` (+ `/creer`), `/codes-invitation/creer` ; `BO/recruitment/reference-format`, `/offers/create` | Hors menu (Configuration, pages recrutement) | Aucun réglage dans le bloc Recrutement | 05 §2.3 ; orphans 2403-2461 |
| NAV-17 | Ma situation : ma fiche (+ modifier, onglet suivi) | `BO/ma-situation/ma-fiche`, `/ma-fiche/modifier` | Opérateur › Ma situation › Ma fiche, Mon suivi | — | 05 §1.4 ; orphans 1709 |
| NAV-18 | Ma situation : mon unité | `BO/ma-situation/unite` | Opérateur › Ma situation › Mon unité | Recoupe J-02 | 05 §1.4 ; 03 J-02 |
| NAV-19 | Ma situation : mes qualifications | `BO/ma-situation/qualifications` | Opérateur › Ma situation (pastille) | Cible de la puce « QUALIF. » de la barre supérieure | 05 §1.4, §4.3 |
| NAV-20 | Ma situation : carnet de vol | `BO/ma-situation/carnet-de-vol` (HYPOTHÈSE sur le chemin exact) | Opérateur › Ma situation | — | 05 §1.4 |
| NAV-21 | Ma situation : avancement, dossier de carrière, décorations | `BO/ma-situation/*` | Opérateur › Ma situation | — | 05 §1.4 |
| NAV-22 | Ma situation : mon coffre, musique, mon compte | `BO/ma-situation/*`, `/account` | Opérateur › Ma situation | `account` sort du shell | 05 §1.4 |
| NAV-23 | Mes démarches | `BO/ma-situation/mes-demarches` | Opérateur › Administratif (pastille) | Demandes du membre (élévation, absence, correction) | 05 §1.4 ; 02 F17 |
| NAV-24 | Tableau opérationnel : nouvelle fiche | `BO/tableau-operationnel/fiche/nouvelle` | Hors menu | Sous-écran de O-13 | orphans 546 |
| NAV-25 | Intégration : nouveau modèle | `BO/integration-membres/modeles/nouveau` | Hors menu | Sous-écran de PER-F24 | orphans 1815 |
| NAV-26 | Raccourcis : création | `BO/dashboard-pins/create` | Hors menu | Sous-écran de ORG-B6 | orphans 1852 |
| NAV-27 | Communications : nouveaux modèle et liste | `BO/communications/templates/create`, `/groups/create` | Hors menu | Sous-écrans de ORG-C4 | orphans 2212, 2218 |
| NAV-28 | Coopération : création de type, édition d'annonce | `BO/cooperation/catalog/create`, `/announcements/edit` | Hors menu | Sous-écrans de O-14 | orphans 2748, 2754 |
| NAV-29 | CR et équipes : nouveaux | `BO/atak/comptes-rendus/nouveau`, `/syntheses/nouvelle`, `BO/atak/fire-teams/create` | Hors menu | Sous-écrans de O-08, A-10 | orphans 2641, 2653, 2595 |
| NAV-30 | Remontées par joueur | `BO/remontees/joueurs` | Hors menu | Sous-écran de R-04 | orphans 2591 |
| NAV-31 | Consignes ATAK : catalogue | `BO/ressources/atak-consignes/catalogue` | Hors menu | Recherche d'objets pour le kit (A-15) | orphans 2530 |
| NAV-32 | Mode roleplay : brouillage du renseignement | `BO/atak/roleplay/intel-scramble` | Hors menu | Redirige vers une ancre de A-05 | orphans 2561 |
| NAV-33 | Réglages réalisme : historique et vérification | `BO/ressources/atak-realism/history`, `/verify` | Hors menu ; `history` sans lien | `verify` en Bootstrap, liens sans `url()` | 05 §2.4 ; orphans 2569, 2572 |
| NAV-34 | Parc ATAK : fiche | `BO/atak/parc-atak/fiche` | Hors menu | Sous-écran de A-09 | orphans 2710 |
| NAV-35 | Gestion des accès : simulation | `BO/access-management/simulate` | Sans lien | Mécanisme non appliqué (S7) | 05 §2.4 |
| NAV-36 | Profils de rôles | `BO/roles/presets` | Hors menu (Configuration, `droits.php`) | Voir ACC-F09 | 05 §2.3 |
| NAV-37 | Plateforme : sous-écrans de création et d'outils | `/admin/system/donnees/outils`, `/correlations`, `/alerts/create`, `/messages-communautes/create`, `/cooperation/catalog/create`, `/military-referential/create`, `/cooperation/announcements/edit`, `/deployment/campaigns/nouveau`, `/admin/maintenance/create`, `/apercu`, `/admin/system/storage/donnees`, `/admin/users/person`, `/admin/users/donnees`, `/admin/parc-atak/fiche` | Hors barre | Sous-écrans de ORG-H | orphans 1428-1623, 2692 |
| NAV-38 | Accueil : bloc personnalisé | `BO/accueil/bloc` | Hors menu | Sous-écran de ORG-K1 | orphans 1658 |
| NAV-39 | Grades : création et avancement | `BO/referentiels/grades/create`, `BO/organisation/grades/create`, `BO/rh/avancement/create` | Hors menu | Sous-écrans de PER-F37, PER-F39 | orphans 2025, 2047, 2059 |
| NAV-40 | Emplois anciens : création, attributions, droits du membre | `BO/personnel-job-roles/roles/create`, `/assignments`, `/assignments/member-permissions` | Hors menu | Voir PER-F18 | orphans 2182-2186 |
| NAV-41 | Effectifs : sous-écrans | `EFF/nouveau`, `/invitations`, `/modele-tableur`, `/fonctions/catalogue`, `/recherche`, `/chaine/adequation`, `/elevations/criteres` | Hors menu | Voir PER-F03, F09, F05, F17, F04, F15, F33 | orphans 2278-2386 |

---

## Annexe A : correspondance `orphans.txt` → inventaire

Chaque ligne de `orphans.txt` (numéro de ligne de `routes/web.php` au commit audité, puis chemin) est rattachée à un identifiant ci-dessus.

| Ligne `web.php` | Chemin | ID |
|---|---|---|
| 1046 | `back-office/renseignement/codes` | R-02 |
| 1548 | `admin/paiements/membres` | ORG-H13 |
| 2005 | `back-office/access-management/simulate` | NAV-35 (ACC-F11) |
| 2422 | `back-office/recruitments/liens-invitation/creer` | NAV-16 |
| 2447 | `back-office/recruitments/codes-invitation/creer` | NAV-16 |
| 2530 | `back-office/ressources/atak-consignes/catalogue` | NAV-31 (A-15) |
| 2561 | `back-office/atak/roleplay/intel-scramble` | NAV-32 (A-05) |
| 2569 | `back-office/ressources/atak-realism/history` | NAV-33 (A-06) |
| 2572 | `back-office/ressources/atak-realism/verify` | NAV-33 (A-06) |
| 2652 | `back-office/atak/comptes-rendus/syntheses` | O-08 |
| 2710 | `back-office/atak/parc-atak/fiche` | NAV-34 (A-09) |
| 2906, 2908, 2910, 2911, 2926, 2929, 2930, 2931, 2932, 2934, 2936, 2938, 2939, 2944, 2946, 2947, 2949, 2950, 2951, 2952 | `back-office/ressources/training/*` (charte-rh, studio, studio/versions, studio/echange/importer, courses, enrollments, reports, feedback, certificates, gabarit/exemple-pdf, gabarit, audit, publications, competences/commandement, instructeur, formateur, bureau-personnel, pole-formation, validation, sections) | NAV-14 (ORG-E6) |
| 546 | `back-office/tableau-operationnel/fiche/nouvelle` | NAV-24 (O-13) |
| 1428 | `admin/system/donnees/outils` | NAV-37 (ORG-H31) |
| 1429 | `admin/system/donnees/correlations` | NAV-37 (ORG-H31) |
| 1461 | `admin/system/alerts/create` | NAV-37 (ORG-H21) |
| 1469 | `admin/system/messages-communautes/create` | NAV-37 (ORG-H22) |
| 1477 | `admin/system/cooperation/catalog/create` | NAV-37 (ORG-H24) |
| 1483 | `admin/system/military-referential/create` | NAV-37 (ORG-H27) |
| 1491 | `admin/system/cooperation/announcements/edit` | NAV-37 (ORG-H24) |
| 1498 | `admin/system/deployment/campaigns/nouveau` | NAV-37 (ORG-H30) |
| 1582 | `admin/maintenance/create` | NAV-37 (ORG-H33) |
| 1583 | `admin/maintenance/apercu` | NAV-37 (ORG-H33) |
| 1612 | `admin/system/storage/donnees` | NAV-37 (ORG-H32) |
| 1622 | `admin/users/person` | NAV-37 (ORG-H16) |
| 1623 | `admin/users/donnees` | NAV-37 (ORG-H16) |
| 1658 | `back-office/accueil/bloc` | NAV-38 (ORG-K1) |
| 1680 | `back-office/ma-situation/appareils` | T-04 |
| 1707 | `back-office/ma-situation/premiere-liaison` | T-03 |
| 1709 | `back-office/ma-situation/ma-fiche/modifier` | NAV-17 |
| 1731 | `back-office/organisation/catalogue/modele` | NAV-02 |
| 1733 | `back-office/organisation/catalogue/apercu` | NAV-02 |
| 1786 | `back-office/community/inscription` | ORG-B1 |
| 1815 | `back-office/integration-membres/modeles/nouveau` | NAV-25 (PER-F24) |
| 1852 | `back-office/dashboard-pins/create` | NAV-26 (ORG-B6) |
| 1913 | `back-office/doctrine/export-lectures` | NAV-15 (ORG-E5) |
| 1919 | `back-office/documents/publier` | NAV-15 (ORG-E5) |
| 1992 | `back-office/roles/presets` | NAV-36 (ACC-F09) |
| 2002 | `back-office/access-management` | ACC-F11 |
| 2006 | `back-office/positions` | PER-F20 |
| 2017 | `back-office/categories/create` | NAV-11 |
| 2022 | `back-office/referentiels/grades/catalogue` | PER-F38 |
| 2025 | `back-office/referentiels/grades/create` | NAV-39 (PER-F37) |
| 2046 | `back-office/organisation/grades` | PER-F39 |
| 2047 | `back-office/organisation/grades/create` | NAV-39 (PER-F39) |
| 2059 | `back-office/rh/avancement/create` | NAV-39 (PER-F39) |
| 2091 | `back-office/referentiels/qualifications/create` | NAV-07 |
| 2093 | `back-office/referentiels/qualifications/emetteurs` | NAV-07 |
| 2103 | `back-office/referentiels/qualifications/catalogue` | NAV-07 |
| 2104 | `back-office/referentiels/qualifications/catalogue/fiche` | NAV-07 |
| 2122 | `back-office/referentiels/qualifications/attribuer` | NAV-07 (PER-F32) |
| 2145 | `back-office/referentiels/qualifications/prerequis-detectes` | NAV-07 |
| 2177 | `back-office/personnel-job-roles` | PER-F18 |
| 2178 | `back-office/personnel-job-roles/kits` | PER-F18 (ACC-F08) |
| 2182 | `back-office/personnel-job-roles/assignments` | NAV-40 (PER-F18) |
| 2183 | `back-office/personnel-job-roles/assignments/member-permissions` | NAV-40 (PER-F18) |
| 2186 | `back-office/personnel-job-roles/roles/create` | NAV-40 (PER-F18) |
| 2212 | `back-office/communications/templates/create` | NAV-27 (ORG-C4) |
| 2218 | `back-office/communications/groups/create` | NAV-27 (ORG-C4) |
| 2278 | `back-office/ressources/effectifs/nouveau` | PER-F03 |
| 2281 | `back-office/ressources/effectifs/invitations` | PER-F09 |
| 2298 | `back-office/ressources/effectifs/modele-tableur` | PER-F05 |
| 2328 | `back-office/ressources/effectifs/fonctions/catalogue` | PER-F17 |
| 2338 | `back-office/ressources/effectifs/recherche` | PER-F04 |
| 2343 | `back-office/ressources/effectifs/chaine/adequation` | PER-F15 |
| 2386 | `back-office/ressources/effectifs/elevations/criteres` | PER-F33 |
| 2403 | `back-office/recruitments/equipe` | NAV-16 |
| 2405 | `back-office/recruitments/settings` | NAV-16 |
| 2407 | `back-office/recruitments/messages-prefaits` | NAV-16 |
| 2411 | `back-office/recruitments/discord-questions` | NAV-16 |
| 2421 | `back-office/recruitments/liens-invitation` | NAV-16 (PER-F22) |
| 2455 | `back-office/recruitment/offers/create` | NAV-16 (PER-F22) |
| 2461 | `back-office/recruitment/reference-format` | NAV-16 |
| 2563 | `back-office/atak/roleplay/reset` | A-05 (S3) |
| 2591 | `back-office/remontees/joueurs` | NAV-30 (R-04) |
| 2595 | `back-office/atak/fire-teams/create` | NAV-29 (A-10) |
| 2641 | `back-office/atak/comptes-rendus/nouveau` | NAV-29 (O-08) |
| 2653 | `back-office/atak/comptes-rendus/syntheses/nouvelle` | NAV-29 (O-08) |
| 2690 | `admin/steam-doublons` | ORG-H20 |
| 2692 | `admin/parc-atak/fiche` | NAV-37 (ORG-H35) |
| 2748 | `back-office/cooperation/catalog/create` | NAV-28 (O-14) |
| 2754 | `back-office/cooperation/announcements/edit` | NAV-28 (O-14) |
| 2942 | `back-office/ressources/training` | NAV-14 |

Contrôle : les 31 chemins « sans lien de vue » (11 + 20 anciennes adresses de formation) et les 71 chemins « liés depuis une vue » de `orphans.txt` sont tous rattachés, soit les 102 pages hors menu annoncées en tête du fichier.
