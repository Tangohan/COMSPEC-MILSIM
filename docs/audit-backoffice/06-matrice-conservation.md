# Document 6 : matrice de conservation des fonctionnalités

Chaque fonctionnalité relevée par les audits est reliée à son emplacement dans l'architecture cible (document 3). Le but est de garantir qu'**aucune fonctionnalité ne se perd** pendant la restructuration. Chaque identifiant de l'inventaire (document 1) apparaît au moins une fois ; les pages riches sont détaillées fonction par fonction (lignes `.a`, `.b`…). Le contrôle de complétude est en fin de document.

## Conventions

| Statut | Sens |
|---|---|
| **Conservé** | Même page, même rôle ; au plus un changement de libellé ou de groupe parent sans effet sur la page |
| **Déplacé** | Même page, autre place dans le menu ; l'adresse est gardée |
| **Réorganisé** | Devient un onglet ou une section d'une page cible ; l'ancienne adresse ouvre l'onglet (301 ou paramètre `?onglet=`) |
| **Mutualisé** | La fonction passe par un service, un composant ou une page commune avec d'autres chemins ; l'ancien point d'entrée redirige ou appelle le service commun |

Abréviations des emplacements cibles : `Accueil`, `Membres`, `Unités`, `Opérations`, `Mon espace` (niveau 1) ; `RH` = Ressources humaines, `Roleplay`, `AAR-R` = Après-action et renseignement, `Coopérations`, `Terrain` = Terrain ATAK, `Équip.` = Équipement et dotation, `Communauté` (niveau 2) ; `Cfg` = Configuration (niveau 3). Entités : `Fiche` = fiche membre, `EU` = espace d'unité, `Dossier` = dossier d'opération. `↗` = autre application.

Les conditions de préservation sont des exigences de recette : elles deviennent des tests du lot 7 (document 5).

---

## 1. Personnel et effectifs

| ID | Fonctionnalité | Emplacement actuel | Emplacement cible | Statut | Dépendances | Conditions de préservation |
|---|---|---|---|---|---|---|
| PER-F01 | Tableur des effectifs | Effectifs › Liste des membres ; onglet Tableur | Membres › Liste des membres | Conservé | Grades, emplois, ORBAT, RBAC | Adresse `EFF` gardée ; ancienne `/back-office/users` en 301 |
| PER-F01.a | Filtres (10), tris (9), regroupements (3), vues générale, médicale, aviation, pagination 80 | Tableur | Membres › Liste | Conservé | `EWC 208-250, 406-416` | Mêmes paramètres d'URL ; mêmes résultats |
| PER-F01.b | Bandeau de composition par unité | Tableur | Membres › Liste ; EU › Vue d'ensemble | Conservé | `EWC 494-537` | Même calcul, sous-unités incluses |
| PER-F01.c | Fenêtres sur place : affectation, ancienneté, élévation, qualification, emplois, rôle dans l'unité, niveau d'accès | Tableur | Membres › Liste (fenêtres gardées) | Mutualisé | Services du lot 4.1 et 4.2 | Chaque fenêtre appelle le même service que la fiche ; mêmes droits ELA |
| PER-F01.d | Grade depuis le tableur | absent | Membres › Liste (fenêtre ajoutée) | Mutualisé | `canManageGrades` | Ajout (01 P2) ; même service que la fiche |
| PER-F01.e | Date de création de l'organisation depuis le tableur | Tableur (`/anciennete-entite`) | RH › Carrière › Ancienneté (lien depuis la liste) | Mutualisé | PER-F41 | Une seule écriture ; ancienne route redirige |
| PER-F01.f | Attribution des matricules manquants à l'affichage | GET du tableur | Tâche planifiée et bouton de la page Matricules | Réorganisé | `NewMemberNumbers::assignMissing` | Aucun membre actif sans matricule après la tâche |
| PER-F01.g | Détection des doublons à l'affichage (pastille) | GET du tableur | Calcul planifié ; pastille de RH › Qualité des fiches | Réorganisé | `PersonnelDuplicateDetectionService` | Même nombre de groupes affiché |
| PER-F01.h | Synchronisation des codes d'unité au rendu | GET du tableur et de la création | Tâche ou écriture à la modification d'une unité | Réorganisé | `UnitCodeService::sync` | Codes identiques avant et après |
| PER-F02 | Fiche membre (dossier RH) | Hors menu, page à ancres | Fiche (onglets selon les droits) | Réorganisé | Presque tout le domaine | Adresses `EFF/membres/{id}` et `/modifier` gardées ; ancres redirigées vers les onglets |
| PER-F02.a | Changer d'unité | Carte Poste › Unité | Fiche › Affectation et poste | Mutualisé | Service d'affectation (4.1) | Notification et audit envoyés comme aujourd'hui |
| PER-F02.b | Réparer l'affectation | Carte Poste › Unité | Fiche › Affectation et poste | Conservé | Deux stockages de l'unité | Gardé tant que le lot 3.4 n'a pas unifié ; ensuite contrôle de cohérence |
| PER-F02.c | Emplois du membre, prérequis, dérogations | Fenêtre « Modifier les fonctions » | Fiche › Affectation et poste | Conservé | `updateMemberJobRoles` | Prérequis et dérogations appliqués (`EWC 2009-2031`) |
| PER-F02.d | Rôle dans l'unité (texte libre) | absent de la fiche (tableur seul) | Fiche › Affectation et poste | Mutualisé | `/membres/{id}/role-unite` | Même route, même droit |
| PER-F02.e | Sièges de l'équipe et postes ORBAT | Carte Poste › Unité | Fiche › Affectation et poste ; EU › Postes | Réorganisé | `orbat_billets`, `organization.orbat.manage` | Édition réservée au même droit |
| PER-F02.f | Niveau d'accès | Carte Poste › Niveau d'accès | Fiche › Accès et habilitations | Réorganisé | Service d'attribution (4.2) | Remplacement du niveau sans perte des autres rôles (`EWC 2414-2446`) ; dates et périmètre ajoutés (3.8) |
| PER-F02.g | Grade, frise, éligibilité | Carte Carrière › Grades | Fiche › Carrière | Réorganisé | `canManageGrades` ; `MemberFileInsightsService` | Frise inchangée tant que 3.3 n'est pas fait ; puis frise unique |
| PER-F02.h | Ancienneté (dates individuelles) | Carte Carrière › Ancienneté | Fiche › Carrière | Réorganisé | `canEditProfiles` | — |
| PER-F02.i | Qualifications : suggestions, brevets, transitions, progression, bouton Ajouter | Carte Carrière › Qualifications | Fiche › Qualifications et distinctions | Réorganisé | NAV-07 | Toutes les origines affichées ; « Ajouter » ouvre l'attribution pré-remplie |
| PER-F02.j | Élévation : historique, demande | Carte Carrière › Élévation | Fiche › Carrière (historique, bouton Demander) ; Membres › Demandes RH | Réorganisé | PER-F33 | La demande crée la même ligne `elevation_requests` |
| PER-F02.k | Position administrative, passage en service actif | Carte Statut du compte | Fiche › Position et statut | Réorganisé | `canManageStatus` ; PER-F45 | Règle de durée minimale (`PersonnelDutyPositionService 134-136`) |
| PER-F02.l | État du compte | Carte Statut du compte | Fiche › Position et statut | Réorganisé | `canManageStatus` | — |
| PER-F02.m | Départ (fenêtre) | Carte Départ | Fiche › Position et statut | Réorganisé | `MemberOffboardingService` | Retrait d'accès réservé à `canManageRoles` |
| PER-F02.n | État civil du personnage, langues | Rubrique Identité | Fiche › Identité et papiers | Réorganisé | `canEditProfiles` | — |
| PER-F02.o | Invitations du membre | Rubrique Identité (panneau) | Fiche › Identité et papiers | Réorganisé | PER-F09 | Ancre `#invitations` redirigée |
| PER-F02.p | Papiers d'identité | Rubrique Identité et papiers | Fiche › Identité et papiers | Conservé | PER-F29 | Affichée seulement avec l'habilitation SSE |
| PER-F02.q | Présence, absences, sanctions (déclarer, lever) | Rubrique Suivi RH | Fiche › Suivi RH | Réorganisé | `canManageSanctions` | — |
| PER-F02.r | Documents RH et mobilité | Liens sortants | Fiche › Suivi RH (documents filtrés) ; Fiche › Carrière (mobilité) | Réorganisé | PER-F27, PER-F28 | Téléchargement filtré par droit (0.10) |
| PER-F02.s | Décorations (barrette) | Rubrique Identité | Fiche › Qualifications et distinctions, avec « Décerner » | Mutualisé | PER-F31 | Même table `personnel_awards` |
| PER-F02.t | Historique (organisation, service, bilans) | Rubrique Historique | Fiche › Historique (chronologie unique) | Réorganisé | 9 sources (01 P4) | Aucune entrée existante perdue |
| PER-F02.u | Rail : À traiter, place dans l'organisation (adéquation), chaîne, raccourcis | Rail latéral | Fiche › Synthèse | Réorganisé | PER-F15 | — |
| PER-F02.v | Indicateurs : complétude, présence 90 jours, dernière connexion | En-tête de la fiche | Fiche › Synthèse | Réorganisé | — | Mêmes calculs |
| PER-F02.w | Portrait | Fenêtre, route front `/personnel/{id}/portrait` | Fiche › Synthèse | Conservé | PER-F40 | Même route |
| PER-F02.x | Lien « Voir la fiche » front | Rail | Fiche › Synthèse | Conservé | PER-F40 | — |
| PER-F03 | Ajouter un membre (5 volets) | Bouton du bandeau | Membres › Ajouter un membre | Mutualisé | Service de création (3.12) | Volets affichés par droit ; envoi du lien « compléter son profil » |
| PER-F03.a | Création depuis l'ORBAT (`POST /users/store`) | Formulaire de l'ORBAT | Unités › Organigramme (même formulaire, service commun) | Mutualisé | `orbat_canvas.php:146` | Route gardée tant que le formulaire l'utilise |
| PER-F04 | Recherche du bureau | Ctrl+K des effectifs | Recherche unique (4.6) | Mutualisé | `EffectifsDirectoryController::search` | Membres, unités, emplois trouvables |
| PER-F05 | Export CSV, PDF, modèle de tableur | Bouton Exporter | Membres › Liste (Exporter) ; RH › Tableaux et exports | Conservé | module `pdf_exports` | Tous les filtres du tableur (1.7) ; mêmes colonnes |
| PER-F06 | Import du tableur (sans comptes) | Bandeau, profil sans comptes | Membres › Ajouter un membre › Importer | Mutualisé | PER-F08 import | Un import ; quota de membres ; message pour unités et grades inconnus |
| PER-F07 | Statut et affectation en masse | Barre de sélection | Membres › Liste | Conservé | Service d'affectation | Notification en masse ajoutée (1.8) ; limite de 200 |
| PER-F08 | Personnes sans compte : liste, panneau historique, rattacher, inviter, modifier | Effectifs › Personnes sans compte | RH › Qualité des fiches › Personnes sans compte | Déplacé | `RosterFicheEventRepository` | Liste de comptes candidats au rattachement |
| PER-F08.a | Créer une fiche sans compte | même page | Membres › Ajouter un membre (option « sans compte ») | Mutualisé | Service de création | — |
| PER-F09 | Invitations « compléter son profil » | Onglet du bureau ; menu hors profil Complet | RH › Recrutement › Invitations et codes (onglet « Compléter un profil ») ; Fiche › Identité et papiers | Réorganisé | `MemberProfileInviteService` | Durée, envoi Discord ou e-mail |
| PER-F10 | Tableaux de données (vues enregistrées) | Effectifs › Tableaux de données | RH › Tableaux et exports | Déplacé | `EffectifsTablesController` | Vues existantes conservées ; envoi vers un tableur |
| PER-F11 | Détection des doublons et réglage des champs | Effectifs › Comptes en double | RH › Qualité des fiches › Doublons | Déplacé | `tenant_admin_settings.personnel_duplicates` | Ajout de « Rattacher » (RD-58) |
| PER-F12 | Départs : liste, archiver, réintégrer | Effectifs › Départs | RH › Départs et anciens membres | Déplacé | `member_departures` | Menu visible seulement avec `canManageStatus` |
| PER-F13 | Matrice des affectations, derniers mouvements | Effectifs › Affectations | Unités › Affectations | Déplacé | `AffectationsMatrixService` | Vues par unité et par membre |
| PER-F13.a | Codes d'unité (communauté, segment par unité) | Affectations | EU › Identification ; Unités › Affectations | Réorganisé | `EffectifsDirectoryController` 66, 95 | Code communauté réservé à `admin.*` |
| PER-F14 | ORBAT : structure, déplacer, archiver, qualité des données, instantané, historique d'unité | Organisation › Organigramme (`/orbat`, hors shell) | Unités › Organigramme (dans le shell) | Conservé | API `/api/orbat/*` | API inchangée |
| PER-F14.a | Fiche d'unité et postes (`orbat_billets`) | `BO/organisation/unites/{id}`, `/postes` | EU › Postes | Réorganisé | `UnitSheetController` | Adresses gardées ; titulaire synchronisé avec l'affectation (3.4) |
| PER-F14.b | Chef d'unité (`commander_user_id`) | ORBAT `updateUnit` | Unités › Organigramme (panneau) ; EU › Chaîne | Mutualisé | PER-F15 | Un service commun (RD-22) |
| PER-F15 | Chaîne de commandement : chefs manquants, conseils, désignation en lot | Effectifs › Chaîne | Unités › Organigramme (vue chaîne) ; EU › Chaîne | Réorganisé | `CommandChainAdvisor` | Transaction en lot ; marque l'étape `PERSONNEL_COMMAND_CHAIN_V1` |
| PER-F15.a | Référentiel d'adéquation par échelon | `EFF/chaine/adequation` (hors menu) | Cfg › Référentiels › Organisation type ; Fiche › Synthèse (place dans l'organisation) | Déplacé | `tenant_admin_settings.org_adequacy` | — |
| PER-F16 | Groupes et équipes : index, fiche, modifier, supprimer | Organisation › Groupes, Équipes | EU › Équipes (équipes de l'unité) ; groupes hors ORBAT à arbitrer | Réorganisé | `GroupAdminController`, `TeamAdminController` | Lire les écrans avant de les déplacer (non audités en détail) |
| PER-F17 | Emplois : catalogue, fiche, catégories, catalogue Athena, masquage, historique, récupération, réglages, étiquettes, prérequis, dérogations | Droits › Fonctions et emplois ; onglet Emplois | Cfg › Référentiels › Emplois | Déplacé | `personnel_job_roles*` | Toutes les vues `?vue=` gardées |
| PER-F17.a | Attributions en lot et export | `?vue=attributions` | Cfg › Référentiels › Emplois (onglet Attributions) | Mutualisé | `updateMemberJobRoles` | Prérequis appliqués aussi en lot |
| PER-F18 | Anciens POST des emplois | `/back-office/personnel-job-roles/*` | Services d'emplois (4.1) | Mutualisé | — | Retrait après lecture des journaux |
| PER-F18.a | Kits d'accès (lots de droits) : créer, attribuer, retirer | `/personnel-job-roles/kits` (hors menu) | Cfg › Droits et accès › Lots de droits | Déplacé | `PersonnelFunctionKitService` | Même effet (ajout d'un rôle) ; visible au registre |
| PER-F19 | Doctrine des fonctions : définitions, relations, fonctions obligatoires | POST `/roles-functions/*` | Cfg › Référentiels › Emplois (onglet Doctrine) | Réorganisé | `role_definitions` | Les définitions existantes restent lisibles |
| PER-F19.a | Attribution rapide d'un rôle | `quick-assign-role` | Service d'attribution (4.2) | Mutualisé | — | — |
| PER-F20 | Postes organisationnels et jeux de rôles | `BO/positions` (hors menu) ; POST `assign-position`, `apply-role-set` | Postes : rattachés à EU › Postes ; jeux de rôles : Cfg › Droits et accès › Lots de droits | Réorganisé | `positions`, `user_positions`, `role_sets` | Décision produit avant migration ; aucun `user_positions` perdu |
| PER-F21 | Candidatures, décision, onboarding, finalisation, annulation, référent | Recrutement › Candidatures | RH › Recrutement › Candidatures | Déplacé | `EnlistmentAcceptanceProvisioningService` | Pré-remplissage par l'offre ; droits sur grade et niveau (0.12) |
| PER-F22 | Vue d'ensemble, statistiques, offres, codes et liens d'invitation | Recrutement (5 entrées) | RH › Recrutement | Déplacé | `RecruitmentWorkspaceController` | — |
| PER-F23 | Enrôlement public | `/enlistment`, `/c/{slug}/enlistment` | Inchangé | Conservé | — | — |
| PER-F24 | Intégration des recrues : modèles, reprise, étapes, référents, groupe, rendez-vous | Recrutement › Intégration | RH › Recrutement › Intégration ; carte dans Fiche › Synthèse | Conservé | `member_integration.*` | Le passage en service actif dépend toujours de la clôture |
| PER-F25 | Nouvelle invitation par e-mail avec unité d'arrivée | Recrutement › Invitations ou Effectifs › Invitations | RH › Recrutement › Invitations et codes (onglet « Recrue ») | Réorganisé | `InvitationSenderMiddleware` | Envoyées, révoquer, renvoyer, lien |
| PER-F26 | À traiter et alertes RH | Dossiers RH › À traiter | Accueil › À traiter (filtre RH) | Mutualisé | `RhActionInboxService` | Toutes les catégories actuelles présentes ; onglet Alertes gardé en filtre |
| PER-F27 | Documents RH : établir, ranger, télécharger | Dossiers RH › Documents RH | RH › Documents RH ; Fiche › Suivi RH | Conservé | `canEditProfiles` | Téléchargement filtré (0.10) |
| PER-F28 | Demandes de mobilité et décision | Dossiers RH › Carrière › Mobilité | Membres › Demandes RH (type Mobilité) | Mutualisé | — | Effet sur l'affectation par le service unique |
| PER-F28.a | Vivier de relève et succession | Dossiers RH › Carrière › Relève | RH › Carrière › Relève et succession | Conservé | — | — |
| PER-F29 | Papiers d'identité, alias, photo, documents, kit, synchronisation jeu, historique, envoi, couverture | Fiche › Identité et papiers ; jumeau `/atak/sse/papiers/membres/*` | Fiche › Identité et papiers ; portail SSE ↗ | Conservé | `MemberIdentityPapersController` | Classification et `sseManage` inchangés |
| PER-F30 | Réglages RH et lancement de l'avancement automatique | Dossiers RH › Réglages RH | Cfg › Réglages RH | Déplacé | `PersonnelAutoAdvancementService` | — |
| PER-F31 | Décorations : définitions, motifs | Référentiels › Décorations | Cfg › Référentiels › Décorations | Déplacé | `personnel_awards` | — |
| PER-F31.a | Attribuer une décoration (unité et lot) | Référentiel seulement | Fiche › Qualifications et distinctions (Décerner) ; attribution en lot gardée au référentiel | Mutualisé | Contrôle du tenant (0.11) | Historique de service écrit comme aujourd'hui |
| PER-F32 | Matrice des qualifications, échéances | Effectifs › Qualifications des membres | RH › Qualifications des membres ; EU › Qualifications | Déplacé | NAV-07 | Écriture par case et rattachement académie |
| PER-F33 | Demandes d'élévation : liste, décision, différentiel de droits | Dossiers RH › Demandes d'accès élevé | Membres › Demandes RH (type Élévation) | Mutualisé | `ElevationApprovalService` | Auto-attribution interdite ; décision sur droits réservée (0.4) |
| PER-F33.a | Critères d'élévation | `EFF/elevations/criteres` (lien depuis la fiche) | Cfg › Référentiels › Grades (onglet Critères) | Déplacé | `canManageGrades` | — |
| PER-F33.b | Demande par le membre | `/personnel/mon-espace-rh/elevation` | Mon espace › Mes démarches | Conservé | — | — |
| PER-F34 | Circuits de validation | Effectifs › Circuits | Cfg › Droits et accès › Circuits | Déplacé | module `workflow_circuits` | Défauts (avis du chef pour élévation et mobilité) |
| PER-F35 | Corrections de fiches : file, appliquer, lot, décider | Dossiers RH › Corrections | Membres › Demandes RH (type Correction) | Mutualisé | `PersonnelCorrectionRequestService` | Grade appliqué avec la source `correction` |
| PER-F35.a | Demande de correction par le membre | `/personnel/{id}/correction` | Inchangé | Conservé | — | — |
| PER-F36 | Restrictions et sanctions de modération | Effectifs › Sanctions & absences (`BO/moderation`) | RH › Discipline | Mutualisé | Service de sanction (4.1) | `TenantMemberModerationMiddleware` |
| PER-F36.a | Absences déclarées par le membre | `/personnel/mon-espace-rh/absences` | Mon espace › Mes démarches ; Membres › Demandes RH (type Absence) | Mutualisé | — | — |
| PER-F37 | Référentiel des grades : système officiel, aucun, sur mesure, grades propres, activer, désactiver | Référentiels › Grades | Cfg › Référentiels › Grades (onglets Système, Grades) | Réorganisé | `GradeReferentielController`, `CustomGradeSystemController` | Ouvert à `personnel.grades.manage` (1.6) ; plus d'écriture au GET |
| PER-F38 | Catalogue et audit OTAN | Hors menu | Cfg › Référentiels › Grades (onglet Contrôle OTAN) | Réorganisé | `rank_catalog` | — |
| PER-F39 | Grades de l'avancement : importer, filières, ordre, initial, archiver | `BO/organisation/grades` (hors menu) | Cfg › Référentiels › Grades (onglet Ordre et filières) | Réorganisé | `grade_definitions` | — |
| PER-F39.a | Campagnes, assistant, candidatures, propositions, commission, publication ; API | Dossiers RH › Carrière › Avancement | RH › Carrière › Campagnes d'avancement | Conservé | `/api/avancement/*` | La publication met à jour `grade_id` (3.3) |
| PER-F40 | Fiche front : vue, édition, visibilité, matricule, Steam, notes, temps par rôle, bilans, phase ; annuaire ; `/personnel/me` | Portail | Inchangé pour le membre ; le staff est renvoyé vers la Fiche (3.11) | Conservé | `canApplyOrbatImmediately` | Le membre garde l'édition de sa fiche |
| PER-F41 | Ancienneté : règles, initialiser, synchroniser, compléter, date de création | Dossiers RH › Carrière › Ancienneté | RH › Carrière › Ancienneté | Conservé | — | Seul endroit de la date de création |
| PER-F42 | Matricules (import, export, prochain, recaler, renuméroter, rétablir), noms et trigrammes, indicatifs, PASS RH | Organisation › Identification | Cfg › Référentiels › Identification ; valeurs sur la Fiche | Déplacé | exceptions d'OAM | Exceptions `personnel.member_number.manage`, `personnel.callsign.manage` gardées |
| PER-F43 | Niveaux d'accès : créer (copie), renommer, rétablir, masquer, supprimer, droits verrouillés du Gestionnaire, titulaires | Droits › Rôles ; onglet Accès | Cfg › Droits et accès › Niveaux d'accès | Déplacé | `CommunityAccessProfiles` | Suppression rebascule les titulaires sur Membre |
| PER-F44 | Registre des droits : vigueur, historique, KPI, colonnes, attribuer, modifier, retirer, revue, export, balayage, garde-fous | Effectifs › Registre | Cfg › Droits et accès › Registre | Déplacé | `AccessRegisterService` | Une ligne par membre (déjà engagé, PR #1600, #1601, #1602) ; dernier Gestionnaire protégé |
| PER-F45 | Position administrative : fiche, ancienne route, rattrapage, automatique à l'arrivée | Fiche › Statut du compte | Fiche › Position et statut ; Cfg › Référentiels › Positions administratives | Réorganisé | `PersonnelDutyPositionService` ; positions personnalisées (déjà engagé) | Sortie du RBAC (3.2) sans perte d'effet métier |
| PER-H1 | Anciennes adresses `/back-office/users*` | 301 | 301 | Conservé | — | Un seul saut |
| PER-H2 | POST sans écran : `update`, `assign-position`, `apply-role-set`, `duty-position`, `duty-positions/backfill`, `deactivate` | Vues orphelines | Services du lot 4 (affectation, attribution, position, départ) | Mutualisé | lecture des journaux | Retrait seulement après vérification des appelants |
| PER-H2.a | Photo opérateur (`operator-photo`) | POST orphelin | Fiche › Synthèse (portrait) | Mutualisé | — | Même stockage de la photo |
| PER-H2.b | Demande de purge (`request-purge`) | POST orphelin | Fiche › Position et statut (action « Demander la purge ») | Réorganisé | — | Même effet |
| PER-H2.c | Relance du profil (`notify-profile`) et renvoi de vérification (`resend-verification`) | POST orphelins | Fiche › Identité et papiers (invitations) ; Fiche › Synthèse (compte) | Réorganisé | PER-F09 | — |
| PER-H3 | Vue `droits.php` | jamais rendue | aucune (fonction couverte par PER-F43 et la Fiche) | Mutualisé | — | Retrait de la vue |
| PER-H4 | GET redirigés des anciens emplois et de la doctrine | 301 | 301 | Conservé | PER-F18, F19 | — |
| PER-H5 | Anciennes routes d'organigramme | 301 vers l'ORBAT | 301 vers Unités › Organigramme | Conservé | — | Un seul saut |
| PER-H6 | Onglet « Roleplay » du bureau (`EFF/roleplay`) | onglet qui sort du bureau | Roleplay › Bureau de suivi | Déplacé | NAV-08 | 301 gardée |
| PER-H7 | `EFF/alertes` | 301 | 301 vers Accueil › À traiter (filtre Alertes) | Conservé | — | — |
| PER-H8 | Liste JNET des membres | Extranet | voir J-04 | Conservé | — | — |
| PER-H9 | Postes hors menu | — | voir PER-F20 | Réorganisé | — | — |
| PER-H10 | Catalogue OTAN, grades d'avancement, adéquation, critères (hors menu) | liens internes | voir PER-F38, F39, F15.a, F33.a | Réorganisé | — | Tous atteignables depuis le menu cible |

## 2. Accès et gouvernance

| ID | Fonctionnalité | Emplacement actuel | Emplacement cible | Statut | Dépendances | Conditions de préservation |
|---|---|---|---|---|---|---|
| ACC-F01 | Niveaux d'accès | voir PER-F43 | Cfg › Droits et accès › Niveaux d'accès | Déplacé | — | — |
| ACC-F02 | « Droits par membre » | Entrée de menu → redirection | Fiche › Accès et habilitations | Mutualisé | 2.4 | Retrait de l'entrée (1.1) |
| ACC-F03 | Registre des droits | voir PER-F44 | Cfg › Droits et accès › Registre | Déplacé | — | — |
| ACC-F04 | Circuits | voir PER-F34 | Cfg › Droits et accès › Circuits | Déplacé | — | — |
| ACC-F05 | Élévations et critères | voir PER-F33 | Membres › Demandes RH ; Cfg › Référentiels › Grades | Mutualisé | — | — |
| ACC-F05.a | Demande d'accès opérateur | `/operateur/acces/demander` | Mon espace › Mes démarches | Mutualisé | `OperatorAccessRequestService` | Même table de demandes |
| ACC-F06 | Cartes niveau, position, statut, élévation de la fiche | Fiche | Fiche › Accès et habilitations, Position et statut, Carrière | Réorganisé | voir PER-F02.f, .k, .l, .j | — |
| ACC-F07 | Emplois | voir PER-F17 | Cfg › Référentiels › Emplois | Déplacé | — | — |
| ACC-F08 | Kits d'accès | voir PER-F18.a | Cfg › Droits et accès › Lots de droits | Déplacé | — | — |
| ACC-F09 | Ancien éditeur de rôle, profils de rôles (presets), aperçu, application, kits de presets, présentation d'un rôle | `/back-office/roles/*` (hors menu) | Cfg › Droits et accès › Niveaux d'accès (presets en « Modèles ») | Mutualisé | `TenantRolePermissionPresetService` | Les rôles semés restent éditables jusqu'au lot 3.9 |
| ACC-F10 | Matrice rôles et droits par module, attribution, revue, export, API | `/back-office/roles-permissions/*` (hors menu) | Cfg › Droits et accès › Niveaux d'accès (vue matrice) ; attribution par le service unique | Mutualisé | `RolePermissionMatrixCatalog` | Export gardé |
| ACC-F11 | Règles d'accès avancées, simulation, API access-control | `/back-office/access-management/*` | Retrait ou branchement (0.7) | Mutualisé | `AccessControlMiddleware` | Décision avant suppression ; aucune règle n'est appliquée aujourd'hui |
| ACC-F12 | Doctrine des fonctions | voir PER-F19 | Cfg › Référentiels › Emplois | Réorganisé | — | — |
| ACC-F13 | Postes organisationnels | voir PER-F20 | EU › Postes ; Lots de droits | Réorganisé | — | — |
| ACC-F14 | Accès au renseignement | voir R-01 | Cfg › Droits et accès › Habilitations | Déplacé | — | Visible pour `atak.sse.grant` |
| ACC-F15 | Sécurité et blocages | Droits › Sécurité et blocages | Cfg › Droits et accès › Sécurité et blocages | Conservé | `BlockedIndicatorRepository` | Visible pour recruteurs et modérateurs (menu cadre) |
| ACC-F16 | Journal d'audit | voir ORG-B9 | Cfg › Droits et accès › Journal d'audit | Conservé | — | Visible pour `admin.audit.view` |
| ACC-F17 | Mon service : tâches, avis de circuit, RSVP, agenda | `/mon-service` (menu : déjà engagé) | Accueil › Mon service ; Mon espace › Mon service | Conservé | `work_tasks`, `WorkflowEngine` | Avis réservé à la personne désignée ; motif obligatoire si défavorable |
| ACC-F18 | Choix d'espace TBA / JNET | `/login/choisir-espace` | Inchangé ; TBA proposé aux cadres | Conservé | `PortalAccessChoice` | Choix mémorisé 90 jours ; audit du choix |
| ACC-F19 | Compte › Accès (rôle, grade, élévations, quitter) | `/account/acces` | Inchangé | Conservé | — | — |
| ACC-F20 | Compte › Sécurité (double vérification, codes, appareils, mot de passe) | `/account/security*` | Inchangé ; révocation par le service commun | Conservé | T-04 | — |
| ACC-F21 | Anciennes routes utilisateurs | voir PER-H2 | — | Mutualisé | — | — |
| ACC-F22 | Droits par qualification, émetteurs habilités | Fiche d'une qualification | Cfg › Référentiels › Qualifications (droits) ; Cfg › Droits et accès › Habilitations (émetteurs) | Réorganisé | 0.8 | Effet réel ou retrait explicite |
| ACC-F23 | Menu opérateur | `NAV:421-529` | Menu opérateur cible (document 3 §9.3) + « Mon périmètre » pour les cadres | Réorganisé | 5a | Aucune entrée actuelle perdue |
| ACC-F24 | Catalogues et presets sans écran | code | Profils cibles (5.1) ; code mort retiré (4.10) | Mutualisé | 3.9 | Rapport des droits portés par les rôles semés |
| ACC-F25 | Rôles du site, journal plateforme | voir ORG-H17, H34 | Plateforme | Conservé | — | — |
| ACC-F26 | Configuration › section Accès | voir ORG-B7 | Menu Cfg › Droits et accès | Mutualisé | — | — |

## 3. Opérations, ATAK, terrain, renseignement, équipement

### 3.1 Opérations

| ID | Fonctionnalité | Emplacement actuel | Emplacement cible | Statut | Dépendances | Conditions de préservation |
|---|---|---|---|---|---|---|
| O-01 | Portail missions : KPI, liste des plans, cycles, plan live, communications, carte ; fiche `?vue=recapitulatif|participants|atak|liaisons` | Opérations › Missions › Portail | Opérations › Dossiers d'opération (liste) ; Dossier › Synthèse | Réorganisé | `MissionsPortalService` | Vues du détail en onglets ; `?carte=` gardé |
| O-02 | Planification : création, statuts, import ORBAT et inscrits, postes (affecter, déplacer, rapprocher), documents, PDF, live, clôture → AAR | Onglet du portail ; `BO/planification/{id}` | Dossier › Synthèse, Organisation et postes, Briefing | Réorganisé | `/api/atak/mission-plan*` | API inchangée ; passage live et clôture identiques |
| O-03 | Agenda : calendrier fusionné avec les sessions RP, séries, inscriptions, fiche événement (consignes, détails, membres, postes, présence, annulation), export | Opérations › Événements | Opérations › Agenda ; Dossier › Créneau et inscriptions | Conservé | module `events` | Quota de la formule gratuite ; deux entrées de menu fusionnées |
| O-03.a | Jeton du bot Discord des événements, appairage du compagnon | Page Événements | Cfg › Intégrations › Discord | Déplacé | `settings.integrations.event_discord_bot_token` | Même clé chiffrée |
| O-03.b | Destination Discord d'un événement | Fiche événement | Dossier › Créneau (et fiche événement) | Conservé | `EventAutomationRepository` | — |
| O-04 | Bilan de participation | Événements › Bilan | Opérations › Agenda (onglet Bilan) ; lien dans Communauté › Indicateurs | Réorganisé | — | Même période et mêmes chiffres |
| O-05 | Réponses nominatives, export, méta ; API jumelle | Fiche événement | Fiche événement ; Dossier › Créneau | Conservé | `EventRsvpNominativeApiController` | API gardée |
| O-06 | Répondre à un événement (portail, coque BO, pointage, manœuvres, aujourd'hui), flux ICS | 5 surfaces | Mêmes routes, composant RSVP unique (4.8) | Mutualisé | — | Check-in du pointage ; jeton ICS |
| O-07 | Cycle de mission : créer, ouvrir, clôturer ; API | Missions › Cycle | Dossier › Exécution ; liste des cycles en onglet de Dossiers d'opération | Réorganisé | `theatre_mission_cycles` | Badge `mission-cycle-badge.js` |
| O-07.a | Parties jouées : enregistrer, rattacher, ignorer, compter, supprimer, restaurer, groupe | Cycle et Suivi CR | Dossier › Exécution | Mutualisé | `atak_debrief_ops` | Rattachement à plan, cycle, événement |
| O-07.b | Types, catégories, règles d'exclusion des parties | Cycle | AAR-R › Comptes rendus (onglet Réglages des parties) | Réorganisé | `atak_op_*` | — |
| O-08 | AAR : statuts, actions correctives, annexes, export PDF et CSV, missions sans retour | Comptes rendus › CR post-op | AAR-R › Comptes rendus ; Dossier › Après-action | Déplacé | `aar_reports` | `mission_cycle_id` ; ajout `event_id` (3.1) |
| O-08.a | Synthèses multi-CR | `…/syntheses` (hors menu, Auth) | AAR-R › Comptes rendus (onglet Synthèses) | Réorganisé | — | Garde de route alignée sur RES |
| O-09 | CR des joueurs : imposer, réglages, renommer, retirer, effacer, relancer, clôturer, export | Onglet Suivi | AAR-R › CR des joueurs ; Dossier › Après-action | Déplacé | téléphone | — |
| O-10 | Modèles de debriefing : créer, archiver, réactiver, dupliquer | Comptes rendus › Modèles | AAR-R › Comptes rendus (onglet Modèles) | Réorganisé | `aar_report_templates` | — |
| O-11 | Espaces opérationnels : phases, ORBAT, ordres, tâches, objectifs, calques et versions, renseignement, vue tactique, instantané | `/operations` (hors menu BO) | Outil ↗ ouvert depuis Dossier › Briefing et Organisation | Conservé | `/api/game/v1/operations*` | Lien `mission_plan_id` écrit (3.1) ; ACL du service inchangée |
| O-12 | Diapositives : ajouter, URL Google, modifier, commenter, déplacer, publier | Missions › Diapositives | Dossier › Briefing ; bibliothèque en onglet de Dossiers d'opération | Réorganisé | `/api/atak/briefing-slides*` | `operation_id` gardé |
| O-13 | Tableau opérationnel : fiches typées, rubriques, modèles, posture, FRAGO, checklists, validation, lien public, retrait | Tableau de bord › Tableau opérationnel | Opérations › Mur opérationnel ; Dossier › Publication | Déplacé | `planning_entries` ; J-03 | Lien public renouvelable ; `publier-lie` devient un lien vivant (3.1) |
| O-14 | Coopérations : proposition, négociation, consentement OTP, réunions, sitrep, REX, chronologie, ORBAT, archivage | Opérations › Coopérations (4 entrées) | Coopérations › Coopérations inter-unités | Réorganisé | `interteam_*` | 25 POST gardés ; alias historiques en 301 |
| O-14.a | Catalogue des types et modèles ; messages d'annonce | Coopérations › Types, Messages | Coopérations (onglets) | Réorganisé | formule `cooperation` | Priorité plateforme → organisation documentée |
| O-S1 | Salle de guerre (statique) | `/salle-de-guerre` | Inchangé (méga-menu) | Conservé | — | — |
| O-S2 | C2 (portail de liens) | `/c2`, `/connect/{token}/c2` | Inchangé | Conservé | — | — |
| O-S3 | Journal de transmissions | `/transmission*` | Inchangé ; lien depuis AAR-R | Conservé | — | — |
| O-S4 | Rétrospective annuelle | `/retrospective*` | Inchangé | Conservé | — | — |
| O-S5 | Companion et Discord des événements (POST) | `BO/events/companion`, `/discord` | Cfg › Intégrations › Discord | Déplacé | O-03.a | Mêmes routes POST |

### 3.2 ATAK (back-office)

| ID | Fonctionnalité | Emplacement actuel | Emplacement cible | Statut | Dépendances | Conditions de préservation |
|---|---|---|---|---|---|---|
| A-01 | Poste de situation : dossiers SSE, présents sur la carte, santé de synchronisation, placement d'un téléphone | ATAK › Poste de situation | Opérations › Situation tactique | Déplacé | `/api/atak/ingest-traffic` | Dossiers SSE filtrés par habilitation |
| A-02 | Carte tactique : marqueurs, synchronisation, sélecteur de monde | Opérations › Carte tactique | Opérations › Situation tactique | Déplacé | API sécurisée (0.2) | Mondes sans doublon |
| A-03 | Contrôle de mission : conditions en jeu, apps, état du serveur, journal | ATAK › Contrôle de mission | Terrain › Supervision (lecture) ; édition dans Cfg › ATAK | Réorganisé | `experience_config` | Journal `atak.server_control` gardé |
| A-03.a | Adopter les conditions réseau réelles | `POST /reseau-adopter` | Cfg › ATAK › Réalisme et réseau | Déplacé | — | — |
| A-04 | Réseau de relais : état, portée, places ; suppression d'un relais | ATAK › Supervision | Terrain › Supervision | Conservé | `atak_relays` | — |
| A-05 | Mode roleplay ATAK : latence, pertes, coupures, capteurs, zones, chiffrement du renseignement, tests serveur, vue ancienne | Roleplay › Mode roleplay ATAK | Cfg › ATAK › Réalisme et réseau | Réorganisé | `tenant_atak_config.roleplay_*` | Remise à zéro en POST (0.3) ; une source (3.5) |
| A-06 | Parc de terminaux : appareils, journal, dissocier, supprimer, sessions web ; identités téléphone en jeu | ATAK › Terminaux › Parc | Terrain › Appareils et liaisons | Réorganisé | `atak_terminals`, `game_phone_identities` | Plus d'écriture au GET (0.15) |
| A-06.a | Réglages réalisme versionnés : historique, version, restaurer, vérifier | Onglet Réglages | Cfg › ATAK › Réalisme et réseau | Réorganisé | `atak_realism_config` | Versions existantes gardées |
| A-07 | Certificats : émettre, révoquer, supprimer | ATAK › Terminaux › Certificats | Terrain › Appareils et liaisons › Certificats | Réorganisé | `atak_certificates` | Une adresse POST (4.9) |
| A-07.a | Réseaux de chiffrement | même page | Cfg › ATAK › Chiffrement et certificats | Déplacé | `atak_crypto_domains` | — |
| A-08 | Sessions et connexions, export, fiche opérateur | ATAK › Terminaux › Opérateurs | Terrain › Supervision › Opérateurs ; Fiche › Terrain ATAK | Réorganisé | — | Export CSV gardé |
| A-09 | Parc ATAK des membres : bloquer, débloquer, déconnecter, messages, compromission, certificat, note, remplacer, réparer | ATAK › Terminaux › Parc ATAK des membres | Terrain › Appareils et liaisons ; Fiche › Terrain ATAK | Réorganisé | `BackOfficeAtakFleetController` | Restriction au tenant (`scopeTenantId`) |
| A-10 | Escouades en jeu, équipes de feu (créer, modifier, dissoudre, supprimer) | Indicateurs › Métriques › Escouades | Terrain › Équipes en jeu ; Dossier › Exécution | Déplacé | `/api/atak/fire-teams` | API inchangée |
| A-11 | Métriques joueurs, temps d'écran, heures de jeu | Indicateurs › Métriques joueurs | Communauté › Indicateurs › Métriques joueurs | Déplacé | — | — |
| A-12a | Files d'envoi | ATAK › Supervision | Terrain › Supervision | Conservé | — | Masquage par profil gardé |
| A-12b | Data Inspector et ses API | ATAK › Supervision | Terrain › Supervision | Conservé | — | `dev/seed` restreint (0.14) |
| A-13 | Détection des marqueurs | ATAK › Terminaux | Cfg › ATAK › Symboles et marqueurs | Déplacé | `/api/atak/marker-detection-rules` | Garde RES et ATAK `manage` (5.7) |
| A-14 | Configuration ATAK | ATAK › Configuration et mods | Cfg › ATAK (page à sections) | Réorganisé | `tenant_atak_config` | Adresse et ancres gardées |
| A-14.a | Serveur Arma, identifiants du mod | `#serveur` | Cfg › ATAK › Connexion et serveur | Réorganisé | — | — |
| A-14.b | Carte par défaut | `#serveur` | Cfg › ATAK › Cartes et terrain | Réorganisé | `atak_maps` | Vérification contre `atak_maps` |
| A-14.c | Lien avancé (`node_url`, `jwt_secret`) | `#avance` | Cfg › ATAK › Connexion et serveur (avancé) | Réorganisé | — | — |
| A-14.d | Clé d'accès et son journal | `#acces` | Cfg › ATAK › Connexion et serveur | Réorganisé | — | — |
| A-14.e | Maintenance | POST `maintenance` | Cfg › ATAK › Connexion et serveur ; affichée en Supervision | Réorganisé | — | Contournement par les mêmes droits |
| A-14.f | Modules ATAK Enhanced et cTab | `#bridge-modules` | Cfg › ATAK › Expérience et règles en jeu | Réorganisé | fichier de cache | Migration en base et inclusion dans l'export (3.5) |
| A-14.g | Flux de données remontés | `#data-flows` | Cfg › ATAK › Expérience et règles en jeu | Réorganisé | fichier de cache | idem |
| A-14.h | Expérience de mission | `#experience` | Cfg › ATAK › Expérience et règles en jeu | Mutualisé | A-03 | Un seul écran d'édition (RD-10) |
| A-14.i | Connexion en jeu : mot de passe, OTP, Steam, synchronisation, version minimale, logo | `#overwatch-game-experience` | Cfg › ATAK › Connexion et serveur | Réorganisé | `overwatch_game_experience` | — |
| A-14.j | Liaison jeu obligatoire à la première connexion | POST `first-login-link` | Cfg › ATAK › Connexion et serveur | Réorganisé | `atak_defaults.require_game_link_*` | — |
| A-14.k | Bandeau photo terrain | `#photo-hud` | Cfg › ATAK › Bandeau photo terrain | Réorganisé | `photo_hud_config` | — |
| A-14.l | Icônes de marqueurs (téléverser, supprimer, choisir) | `#marker-icons` | Cfg › ATAK › Symboles et marqueurs | Réorganisé | `marker_icons_config` | — |
| A-14.m | Export et purge (« EFFACER »), déliaison Steam | `#donnees` | Cfg › ATAK › Données | Réorganisé | `AtakTenantDataService` | Phrase de confirmation gardée |
| A-15 | Consignes, ordres, astuces ; kit obligatoire ; recherche dans le catalogue | ATAK › Configuration et mods › Consignes et kit | Cfg › ATAK › Consignes et matériel conseillé ; lecture dans Dossier › Briefing | Réorganisé | `community_notes`, `community_kit` | Lu par l'application Communauté du téléphone sans changement |
| A-16a | Mods Steam Workshop, annonces, réglage Steam | Configuration et mods › Mods | Cfg › ATAK › Mods et serveurs | Réorganisé | `SteamWorkshop::saveTenant` | — |
| A-16b | Restrictions d'accès au mod | Configuration et mods › Mods | Cfg › ATAK › Mods et serveurs | Réorganisé | `blocked_indicators` | — |
| A-16c | Serveurs de jeu | Configuration et mods | Cfg › ATAK › Mods et serveurs | Réorganisé | `tenant_game_servers` | — |
| A-16d | Diffusion des rapports : règles, état, ordre, simuler, dupliquer, supprimer | Configuration et mods | Cfg › ATAK › Diffusion des rapports | Réorganisé | `atak_report_routing_*` | — |
| A-16e | Alias historiques ATAK | redirections | redirections en un saut | Conservé | — | Double saut de `/admin/atak/realism/config` corrigé |
| A-17 | Espace aérien : carnets, flotte, qualifications pilotes | Référentiels › Compétences › Espace aérien | Terrain › Aviation | Déplacé | FeatureGate ; module personnel | Visibilité par profil à décider (5.5) |

### 3.3 Terrain et données tactiques

| ID | Fonctionnalité | Emplacement actuel | Emplacement cible | Statut | Dépendances | Conditions de préservation |
|---|---|---|---|---|---|---|
| T-01 | Overwatch : 16 espaces, relecture, tchat, palette, 3D, effectifs ; liaison jeu ; demande d'accès | ATAK › Overwatch ; Terrain ; méga-menu | Opérations › Situation tactique ↗ | Conservé | API `/api/atak/*` | Contrôles de formule et de maintenance gardés |
| T-02 | Ancien Overwatch et Tacmap : appui-feu, zones, logistique, situation, relecture, identification, tchat, urgences, cartes perso, santé | `/overwatch`, `/tacmap` | Fonctions portées dans `/atak` (3.6), puis 301 | Mutualisé | API-1, 3, 5, 8 | Aucune fonction retirée avant son portage |
| T-03 | Première liaison ATAK | `/atak/premiere-liaison` et `BO/ma-situation/premiere-liaison` | Mon espace › Ma liaison ATAK (une adresse canonique) | Mutualisé | `tactical_game_link_codes` | Code valable 15 min ; `/atak/setup` redirige |
| T-04 | Ma liaison ATAK, mes appareils, appareils du compte, journal de liaison | Terrain › Ma liaison ATAK ; compte | Mon espace › Ma liaison ATAK ; compte inchangé | Mutualisé | `OperatorDeviceActivityService` | Une révocation, deux points d'entrée |
| T-05 | Installation des mods, guide, formation, tutoriel | `/atak/mod*`, `/atak/tuto` | Inchangé ; lien depuis Mon espace | Conservé | A-16a | — |
| T-06 | Poste TOC et plans : console, éditeur, exports, partage, relief, import jeu | `/atak/toc*` (hors menu BO) | Opérations › Situation tactique ↗ ; plans liés au Dossier | Conservé | `atak_toc_*`, `atak_plan*` | ACL par plan inchangées |
| T-07 | Passerelle de cartes entre communautés | `/atak/passerelle` | Cfg › ATAK › Cartes et terrain (passerelles) ; outil ↗ | Réorganisé | `atak_map_gateways` | Gestion réservée à `admin.*` |
| T-08 | Applications du téléphone `/m/*` | QR en jeu | Inchangé | Conservé | `atak_mobile_links` | Contrats inchangés ; sessions de `/m/admin` rattachées au Dossier (RD-49) |
| T-09 | Connexion téléphone par code | `/connect*` | Inchangé ; liste et révocation dans Terrain › Appareils et liaisons › Appairages | Conservé | `tactical_phone_pairings` | Ajout d'une interface de révocation (2.14) |
| T-10 | 9-LINE / 5-LINE : demandes, flux, envoi, indicatifs | ATAK › Supervision › 9-LINE | Opérations › Situation tactique ↗ | Conservé | `/api/nine-line` | — |
| T-10.a | Appareils aériens et équipages | `/9-line/appareils`, `/equipages` | Terrain › Aviation | Déplacé | `atak_air_assets` | — |
| T-S1 | Télécommande drone | `/telecommande-drone` | Inchangé | Conservé | — | — |
| T-S2 | Opérateur terrain (raccourcis) | `/operateur/terrain` | Inchangé | Conservé | — | — |
| T-S3 | Wiki ATAK | `/wiki/atak*` | Inchangé | Conservé | — | — |
| T-S4 | Bibliothèque de marqueurs | `/documentation/marqueurs` | Inchangé ; lien depuis Cfg › ATAK › Symboles | Conservé | — | — |
| T-S5 | Visionneuse d'images de reconnaissance | `/recon/images/{id}` | Inchangé | Conservé | — | Contrôle du jeton vérifié (lecture) |
| T-S6 | Page ATAK natif | `/atak-natif` | Inchangé | Conservé | — | — |
| API-1 | Appui-feu | `/overwatch` | `/atak` (3.6) | Mutualisé | — | API inchangée |
| API-2 | IFF | `/atak`, `/overwatch` | `/atak` | Conservé | — | — |
| API-3 | Logistique (et doublon d'API) | `/overwatch` | `/atak` ; doublon retiré (4.10) | Mutualisé | — | Retrait après lecture des journaux |
| API-4 | Ravitaillement | télémétrie Overwatch | `/atak` | Conservé | — | — |
| API-5 | Zones dangereuses | `/overwatch` | `/atak` | Mutualisé | — | — |
| API-6 | Relecture | `/atak`, `/overwatch` | `/atak` ; lien depuis Dossier › Exécution | Conservé | — | — |
| API-7 | QRF, MEDEVAC | `/atak` | `/atak` | Conservé | — | — |
| API-8 | Cartes personnalisées | formulaire de `/overwatch` | Cfg › ATAK › Cartes et terrain | Réorganisé | — | Envoi gardé |
| API-9 | Terrain (MNT) | mod | Cfg › ATAK › Cartes et terrain (liste en lecture) | Réorganisé | — | Alimentation par le mod inchangée |
| API-10 | Tuiles | outil en ligne de commande | Cfg › ATAK › Cartes et terrain (liste en lecture) | Réorganisé | `tools/map_stitch.py` | Production inchangée |
| API-11 | Catalogue des cartes | étape de migration | Cfg › ATAK › Cartes et terrain (liste) | Réorganisé | `atak_maps` | — |

### 3.4 JNET, renseignement, identité

| ID | Fonctionnalité | Emplacement actuel | Emplacement cible | Statut | Dépendances | Conditions de préservation |
|---|---|---|---|---|---|---|
| J-01 | Espace commun | Extranet | Extranet ↗ (Coopérations › Extranet d'unité) | Conservé | — | Renseignement filtré (0.1) |
| J-02 | Fiche et espace d'unité, entrée, personnalisation, échanges | Extranet › Unité | Extranet ↗ ; lien depuis EU › Extranet | Conservé | `jnet_unit_spaces` | `canCustomize` inchangé |
| J-03 | Opérations (miroir du mur) | Extranet › Opérations | Extranet ↗ ; lit Dossier › Publication | Conservé | O-13 | — |
| J-04 | Personnel (annuaire) | Extranet › Unité › Personnel | Extranet ↗ | Conservé | — | — |
| J-05 | Renseignement | Extranet | Extranet ↗, filtré par habilitation | Conservé | 0.1 | Rien d'affiché sans habilitation |
| J-06 | Cibles prioritaires | Extranet | Extranet ↗, avec ACL | Conservé | 0.1 | `listVisibleForUser` |
| J-07 | Exploitation | Extranet | Extranet ↗, filtré | Conservé | 0.1 | Compteurs limités au visible |
| J-08 | Bibliothèque | Extranet | Extranet ↗ | Conservé | — | — |
| J-09 | Messagerie d'unité | Extranet | Extranet ↗ | Conservé | module `messages` | Mêmes tables que `/messages` |
| J-10 | Système, bascule d'espace | Extranet | Extranet ↗ | Conservé | `PortalAccessChoice` | — |
| J-A | Alias JNET obsolètes | redirections | redirections ; vues orphelines retirées (4.10) | Conservé | — | — |
| R-01 | Codes d'accès SSE, journal des émissions, révocation | Droits › Accès au renseignement | Cfg › Droits et accès › Habilitations | Mutualisé | R-05a | Visible pour `atak.sse.grant` |
| R-02 | Entrée « commandement » | deux URL | une adresse canonique (`/atak/sse/commandement`), l'autre en 301 | Mutualisé | — | — |
| R-03 | Fichiers SSE : fiches, doublons et fusion, extractions, journal, réglages | Droits › Fichiers SSE | AAR-R › Renseignement › Fichiers SSE | Déplacé | `sse_file_*` | `atak.sse.files.admin` |
| R-04 | Remontées : fil, par joueur, fiche, actions d'exploitation | Opérations › Comptes rendus › Remontées | AAR-R › Remontées ; Dossier › Renseignement | Déplacé | `intel_intake_*` | Sous-contrôles du contrôleur gardés |
| R-05a | Sas, entrée, confidentialité, sortie, gestion des codes | Portail SSE | Portail SSE ↗ ; gestion des codes mutualisée avec R-01 | Mutualisé | — | — |
| R-05b | Accueil, opérations, espace de travail, recherche | Portail SSE | Portail SSE ↗ | Conservé | `/api/sse/v1/*` | — |
| R-05c | Identités | Portail SSE | Portail SSE ↗ | Conservé | — | — |
| R-05d | Papiers (registre, contrôles du jeu) | Portail SSE | Portail SSE ↗ | Conservé | `/api/sse/identity/*` | — |
| R-05e | Atelier de papiers, kits de couverture, profils IA | Portail SSE | Portail SSE ↗ (« kits de couverture » renommés, 3.7) | Conservé | — | — |
| R-05f | Fichiers : requête, journal, sujets, photos, comparer, téléphones, lore | Portail SSE | Portail SSE ↗ | Conservé | — | — |
| R-05g | Objets | Portail SSE | Portail SSE ↗ | Conservé | — | — |
| R-05h | Chronologie, anomalies, rapprochements | Portail SSE | Portail SSE ↗ | Conservé | — | — |
| R-05i | Campagnes | Portail SSE | Portail SSE ↗ | Conservé | `/api/sse/campaigns` | — |
| R-05j | Fiches terrain | Portail SSE | Portail SSE ↗ | Conservé | `/api/sse/notes` | — |
| R-05k | Collecte, validation, rapports | Portail SSE | Portail SSE ↗ | Conservé | — | — |
| R-05l | Documents, bibliothèque, éditeur, présentation | Portail SSE | Portail SSE ↗ | Conservé | — | — |
| R-05m | Dossiers d'enquête, export, sceau public | Portail SSE | Portail SSE ↗ ; lien depuis Dossier › Renseignement | Conservé | `canExport` | — |
| R-05n | Intérêt et transmissions | Portail SSE | Portail SSE ↗ | Conservé | — | ACL gardées |
| R-05n.a | Relais de transmissions | Portail SSE et Intégrations | Cfg › Intégrations › Relais SSE (portail en lien) | Mutualisé | `integrations.sse_transmissions` | Même clé |
| R-05o | Personnes, sites, croisements, maître du jeu, toiles | Portail SSE | Portail SSE ↗ | Conservé | — | — |
| R-05p | Exploitation numérique | Portail SSE | Portail SSE ↗ | Conservé | `/api/sse/digital-acquisitions` | — |
| R-05q | Développement Arma (modèles 3D) | Portail SSE | Portail SSE ↗ | Conservé | — | — |
| R-05r | Guide | Portail SSE (deux alias) | Portail SSE ↗ (une adresse) | Mutualisé | — | — |
| I-01 | Hub opérateur : favoris, demande d'accès | `/operateur` | Inchangé ; lien depuis Mon espace | Conservé | — | — |
| I-02 | Mes identités | `/operateur/identite` | Mon espace › Ma situation › Mes papiers (page unique) | Mutualisé | `OperatorIdentityService` | Routes gardées ou 301 |
| I-03 | Mes papiers (opérateur), spécimen | `/operateur/identite/documents` | Mon espace › Ma situation › Mes papiers | Mutualisé | — | idem |
| I-04 | Mes papiers (coque BO), couverture | Ma situation › Mes papiers | Mon espace › Ma situation › Mes papiers | Conservé | `MemberCoverExportController` | Génération de couverture en un seul endroit |
| I-05 | Papiers des membres (personnel SSE) | `/atak/sse/papiers/membres*` | Fiche › Identité et papiers ; portail SSE ↗ | Conservé | classification | — |
| I-A | Anciennes routes de couverture | redirections | redirections | Conservé | — | — |

### 3.5 Équipement et dotation

| ID | Fonctionnalité | Emplacement actuel | Emplacement cible | Statut | Dépendances | Conditions de préservation |
|---|---|---|---|---|---|---|
| E-01 | Arsenal : mes tenues, favoris, collections, communauté, matériel (catalogue, fiches, dotation), export ACE, icônes | ATAK › Matériel › Arsenal et tenues | Équip. › Matériel ; Équip. › Tenues et collections ↗ | Conservé | mod Arma | Espaces `?espace=` gardés |
| E-01.a | Kits depuis l'arsenal (créer, copier, destinataire, supprimer) | `?espace=kits` | Équip. › Kits par emploi (éditeur unique) | Mutualisé | `KitsAccess` | `destroyKit` vérifie `KitsAccess` |
| E-01.b | Onglet dotation de l'arsenal | `?tab=dotation` | Lecture ; édition dans Équip. › Dotation | Mutualisé | E-06 | — |
| E-02 | Configurateur : catalogue, import, analyse, enregistrement, versions, pré-kits, script SQF | `/equipment/configurateur` | Outil ouvert depuis Équip. › Kits et Tenues | Conservé | `script-export.sqf` | Script lu par le mod inchangé |
| E-03 | Assistant de tenue | `/equipment/assistant` | Outil ouvert depuis Équip. › Tenues | Conservé | — | — |
| E-04 | Équipement des joueurs : top des objets, mods détectés, kits portés, enregistrer comme tenue | ATAK › Matériel | Équip. › Porté en jeu | Déplacé | `game_item_*` | — |
| E-05 | Kits par rôle : créer, modifier, supprimer, publier comme tenue | ATAK › Matériel › Kits par rôle | Équip. › Kits par emploi | Déplacé | `game_role_kits` | Mêmes droits sur tous les écrans (5.4) |
| E-06 | Dotation : articles, objet du jeu, archivage, attributions, statut, dotation type contre porté | Référentiels › Dotation | Équip. › Dotation ; Fiche › Équipement et dotation | Déplacé | `personnel_equipment_assignments` | Historique nominatif gardé |
| E-07 | Ma dotation | Ma situation › Ma dotation | Mon espace › Ma situation | Conservé | — | — |
| E-08 | Tenues mises en avant (créer, modifier, supprimer, ordonner) | ATAK › Matériel | Équip. › Tenues mises en avant | Conservé | `tenant_dashboard_wardrobe_pins` | Libellé aligné sur le titre |
| E-S1 | Fiche d'une classe d'équipement | `/equipment/{slug}` | Inchangé | Conservé | — | — |
| E-S2 | Page publique « équipement » (maquette sans données) | `/equipement` | Inchangé, à arbitrer (page morte) | Conservé | — | Décision produit avant retrait |
| E-S3 | En-tête « Ressources » sans route | `back_office_pages.php:312` | Métadonnée retirée | Réorganisé | — | Aucune fonction attachée |

## 4. Organisation, communications, plateforme

| ID | Fonctionnalité | Emplacement actuel | Emplacement cible | Statut | Dépendances | Conditions de préservation |
|---|---|---|---|---|---|---|
| ORG-A1 | Guide de création | site public | Inchangé | Conservé | — | — |
| ORG-A2 | Assistant de création : 5 étapes, aperçu, paiement, e-mail checklist | site public | Inchangé ; cartes d'offre générées depuis `subscription_plans` | Conservé | `PlanPaymentService` | Création en formule `free` ; redirection vers la checklist de démarrage |
| ORG-A3 | Fuseau horaire et fin d'onboarding (ancien assistant) | `/c/{slug}/setup` | Cfg › Organisation › Identité et image (fuseau) ; un drapeau de fin | Mutualisé | 0.5 | Fuseau déjà réglable en Paramètres |
| ORG-A4 | Configuration initiale : 7 ou 8 critères, formulaire unique, Terminer, Plus tard | Communauté › Configuration initiale | Cfg › Organisation › Démarrage (checklist) ; champs dans leurs formulaires uniques | Réorganisé | `TenantInitialSetupService` | Mêmes critères de calcul ; bandeau de l'accueil gardé |
| ORG-A4.a | Modules publics (documents, événements, effectifs, formations, analytics) | Configuration initiale | Cfg › Organisation › Page publique | Déplacé | — | — |
| ORG-A4.b | Rôle invité par défaut | Configuration initiale | Cfg › Organisation › Inscription et contact | Déplacé | — | — |
| ORG-A5 | Rattrapage « Besoin d'aide ? » | hors menu | Cfg › Organisation › Démarrage | Mutualisé | — | — |
| ORG-A6 | Mises à jour et nouveautés | Réglages › Mises à jour | Cfg › Organisation › Mises à jour et nouveautés | Conservé | `ConfigurationUpdateIntroMiddleware` | Redirection forcée différée pendant le démarrage |
| ORG-A7 | Premiers pas (membre) | portail | Inchangé | Conservé | — | — |
| ORG-B1 | Paramètres de la communauté (6 onglets) | Vitrine et portail › Paramètres | Cfg › Organisation (sections) | Réorganisé | `OrganizationSettingsController` | Contrôle interne ajouté (0.6) |
| ORG-B1.a | Identité : nom, slug, code, logo, image du registre, message d'accueil, unité représentée, pays, langues | onglet Identité | Cfg › Organisation › Identité et image | Réorganisé | une colonne de logo (3.10) | — |
| ORG-B1.b | Vitrine : textes publics, visibilité de l'ORBAT, blocs affichés | onglet Vitrine | Cfg › Organisation › Page publique | Mutualisé | ORG-B2 | Un seul formulaire pour ces textes |
| ORG-B1.c | Fuseau horaire | onglet Vitrine | Cfg › Organisation › Identité et image | Déplacé | — | — |
| ORG-B1.d | Inscription : mode, contact, intro, rôle invité, dossier d'enrôlement | onglet Inscription | Cfg › Organisation › Inscription et contact | Mutualisé | ORG-B2, ORG-A4 | Écrasement par `normalizeFromRequest` vérifié (04 N1) |
| ORG-B1.e | Accueil de connexion : images, ordre, diaporama | onglet Accueil | Cfg › Organisation › Portail des membres | Réorganisé | `tenant_login_accueil_images` | — |
| ORG-B1.f | Portail : images des menus, couleurs, bannière, favicon | onglet Portail | Couleurs, bannière, favicon : Identité et image ; images des menus : Page publique | Réorganisé | `tenant_branding` | Un composant de téléversement (RD-45) |
| ORG-B1.g | Profil : type de communauté (avec confirmation) | onglet Profil | Cfg › Organisation › Profil et formule | Réorganisé | `TenantTypeSwitchService` | Réservé à `admin.organization` (0.6) |
| ORG-B1.h | Cycle administratif des recrues (durées) | onglet Profil | Cfg › Réglages RH | Déplacé | `PersonnelLifecycleSettings` | — |
| ORG-B2 | Page d'accueil publique : ~110 champs, sections, modale de vérification | Vitrine et portail › Page d'accueil publique | Cfg › Organisation › Page publique | Réorganisé | `TenantCommunityProfileService` | Modale « Vérifier avant d'enregistrer » gardée |
| ORG-B2.a | Dossier de candidature (`em_*`, `em_fields_json`) | Page publique et Paramètres › Inscription | Cfg › Organisation › Inscription et contact | Mutualisé | — | Un seul éditeur |
| ORG-B3 | Éditeur de page en place (brouillon, publier, annuler, blocs, images) | `/c/{slug}` en mode édition | Cfg › Organisation › Page publique (bouton « Éditer en place ») | Conservé | `PublicPageBuilder` | Droit `admin.organization|admin.access` |
| ORG-B4 | Annoncer sur Discord (demande, annulation, retrait) | Vitrine et portail | Cfg › Organisation › Page publique (Visibilité) | Déplacé | ORG-H10 | — |
| ORG-B5 | Médias et collections | Vitrine et portail › Médias | Communauté › Médias et musique | Déplacé | `media.*` | — |
| ORG-B6 | Raccourcis du portail | Vitrine et portail | Cfg › Organisation › Portail des membres | Déplacé | `DashboardPinsAccess` | — |
| ORG-B7 | Page Configuration : vue d'ensemble, liens, historique, outil de développement | Réglages › Configuration | Page retirée ; contenu réparti | Mutualisé | — | Chaque lien existe dans le menu cible |
| ORG-B7.a | Affichage des rôles (rôle affiché, badges, langue des intitulés) | Configuration | Cfg › Droits et accès › Niveaux d'accès (onglet Affichage) | Déplacé | `admin.*` | — |
| ORG-B7.b | Santé de l'onboarding, compteurs de candidatures | Configuration | Cfg › Organisation › Démarrage ; Accueil | Réorganisé | — | — |
| ORG-B7.c | Historique des changements (8 entrées) | Configuration | Cfg › Droits et accès › Journal d'audit | Mutualisé | — | — |
| ORG-B7.d | Outil de développement (si `APP_DEBUG`), synchronisation de recrutement de débogage | Configuration | Cfg › Organisation (section visible en débogage seulement) | Déplacé | — | Jamais visible en production |
| ORG-B8 | Indicateurs d'usage et conversion | Indicateurs | Communauté › Indicateurs (une route, l'autre en 301) | Mutualisé | formule `analytics` | — |
| ORG-B9 | Journal d'audit (filtres, détail) | Droits › Journal d'audit | Cfg › Droits et accès › Journal d'audit | Conservé | `AuditLogRepository` | — |
| ORG-B10 | Messages de la plateforme (lire, fermer, acquitter) | Réglages ; cartes de l'accueil | Cfg › Organisation › Messages de la plateforme ; cartes de l'accueil | Conservé | `platform_bo_messages*` | — |
| ORG-B11 | Musique de l'organisation | Communauté › Musique | Communauté › Médias et musique | Déplacé | même vue que `/admin/musique` | — |
| ORG-C1 | Centre de diffusion : aperçu, diffuser, en cours, modifier, retirer, filtres, canaux (dont ordres ATAK) | Communication › Annonces | Communauté › Publier | Réorganisé | formule `alerts` | Filtre `allowedChannels` appliqué à tous les formats |
| ORG-C2 | Bandeau avec image, affichage à venir, notification Discord | hors menu | Communauté › Publier (format Bandeau) | Mutualisé | `tenant_alerts` | — |
| ORG-C3 | Mini-articles | Communication › Mini-articles | Communauté › Publier (format Article) | Réorganisé | `tenant_mini_articles` | — |
| ORG-C4 | E-mails : composer, historique, modèles, listes de diffusion | Communication › E-mails | Communauté › Publier › E-mails | Réorganisé | `tenant_email_*` | Envoi « depuis Intégrations » renvoyé ici (RD-64) |
| ORG-C5a | Messagerie interne | portail ; bloc de l'accueil | Inchangé | Conservé | module `messages` | — |
| ORG-C5b | Boîte de réception | Opérateur › Administratif | Mon espace › Boîte de réception | Conservé | `courrier.view` | — |
| ORG-C5c | Alertes membre | portail | Inchangé | Conservé | — | — |
| ORG-C6 | Courrier signé (signature, éditeur, modèles, historique, archives, vérification publique) | module `/courrier` | Inchangé | Conservé | formule `courrier` | — |
| ORG-C6.a | Traçabilité du courrier | deux routes | Cfg › Organisation › Traçabilité (une route) | Mutualisé | — | Middleware le plus strict |
| ORG-C7 | Préférences de notification | compte | Inchangé | Conservé | — | — |
| ORG-D1 | Événements côté communauté, bot, compagnon | voir O-03, O-03.a | — | Déplacé | — | — |
| ORG-D2 | Fiche événement, réponses nominatives, bilan, destination Discord | voir O-03.b, O-04, O-05 | — | Conservé | — | — |
| ORG-E1 | Gestion documentaire | Formations et documents | Communauté › Documents et doctrine | Conservé | `$mwDocuments` | — |
| ORG-E2 | Documents (lecture membre), collections | portail | Inchangé | Conservé | — | — |
| ORG-E3 | Mes documents | portail | Inchangé | Conservé | — | — |
| ORG-E4 | Fichiers partagés | deux routes | Communauté › Documents et doctrine (une route) | Mutualisé | — | Droit de création à la racine gardé |
| ORG-E5 | Doctrine et SOP (conformité, nomenclature, types), référentiel doctrinal | Formations et documents | Communauté › Documents et doctrine | Conservé | — | — |
| ORG-E6 | Académie et formations | Formations | Communauté › Formations | Conservé | formule `training` | Anciennes adresses en 301 |
| ORG-F1 | Monter en gamme, paiement, déclaration, formule offerte vue, webhooks | vue de refus | Inchangé ; lien depuis Cfg › Organisation › Profil et formule | Conservé | `canManageBilling` | — |
| ORG-F2 | Aperçu d'une formule (super-admin) | POST plateforme | Inchangé | Conservé | — | Audit gardé |
| ORG-G1 | Intégrations | Réglages › Intégrations | Cfg › Intégrations | Réorganisé | `admin.organization` | Contrôle à chaque action gardé |
| ORG-G1.a | Discord : salon principal, test, routage par usage, salons supplémentaires, modèle d'invitation | `#relais-discord` | Cfg › Intégrations › Discord (avec bot, compagnon, lien d'invitation) | Mutualisé | RD-30 | Limite de 10 par 60 s ; routage initial vers le salon principal |
| ORG-G1.b | E-mails de l'organisation (SMTP, essai) | `#emails-communaute` | Cfg › Intégrations › E-mail | Conservé | `settings.mail_sender` | — |
| ORG-G1.c | Relais SSE | `#relais-transmissions` | Cfg › Intégrations › Relais SSE | Mutualisé | R-05n.a | — |
| ORG-G1.d | Clés d'API (créer, modifier, renouveler, révoquer) | `#jetons` | Cfg › Intégrations › Clés d'API | Conservé | formule `advanced_integrations` | `/integrations/v1/evenements` inchangé |
| ORG-G1.e | Information Arma / Overwatch | `#arma-overwatch` | Lien vers Cfg › ATAK | Réorganisé | — | — |
| ORG-I3 | Hub inter-communautés, comparer, bourse | portail | Inchangé | Conservé | — | — |
| ORG-I4 | Fédération (statique) | `/federation` | Inchangé | Conservé | — | — |
| ORG-J1 | Signalements : traiter, sanctionner | Communauté › Modération | RH › Discipline | Déplacé | service de sanction (4.1) | — |
| ORG-J2 | Fichiers en quarantaine : avertir, restreindre | Communauté › Modération | RH › Discipline | Déplacé | idem | — |
| ORG-K1 | Accueil gestionnaire | Tableau de bord | Accueil | Réorganisé | `HomeLayout`, `bo_user_nav_prefs.home_json` | Personnalisation par personne gardée ; blocs masqués non calculés |
| ORG-K1.a | Cartes « Messages de la plateforme » | Accueil | Accueil | Conservé | ORG-B10 | — |
| ORG-K1.b | Bandeau « Discord manquant » | Accueil | Accueil › Démarrage (critère de la checklist) | Réorganisé | — | — |
| ORG-K1.c | Bandeau « photos perdues lors de la migration » | Accueil | RH › Qualité des fiches › Qualité des données (vérification planifiée) | Réorganisé | `MissingUserMediaScanner` | Plus de scan à chaque affichage (1.20) |
| ORG-K1.d | Bandeaux configuration initiale et nouveautés | Accueil | Accueil (bandeau Démarrage, puis Nouveautés) | Conservé | ORG-A4, A6 | — |
| ORG-K1.e | Tuiles « Carte ATAK » (profil ATAK) | Accueil | Accueil | Conservé | `bo_atak_light_kit.php` | — |
| ORG-K1.f | Salutation (membres actifs, prochaine activité, décisions) | Accueil | Accueil | Conservé | `PilotageDashboardPresenter::hello` | Déjà engagé : sans surtitre |
| ORG-K1.g | Personnaliser (ordre, visibilité, largeur) | Accueil | Accueil | Conservé | `_home_customize.php` | — |
| ORG-K1.h | Bloc Prochaine opération | Accueil | Accueil › Prochaine opération | Conservé | — | — |
| ORG-K1.i | Blocs À décider et Tâches en attente | Accueil | Accueil › À traiter (fusion) | Mutualisé | RD-14 | Toutes les catégories gardées ; compteurs calculés une fois |
| ORG-K1.j | Bloc Raccourcis (favoris du menu) | Accueil | Accueil | Conservé | NAV-13 | — |
| ORG-K1.k | Blocs Chiffres clés, Participation, Connexions | Accueil | Accueil (repliés) ; détail dans Communauté › Indicateurs | Conservé | `PilotageDashboardService` | — |
| ORG-K1.l | Bloc État des unités | Accueil | Accueil ; EU › Vue d'ensemble | Conservé | `unitReadiness` | — |
| ORG-K1.m | Bloc Recrutement | Accueil | Accueil (si droit) | Conservé | — | — |
| ORG-K1.n | Bloc Agenda | Accueil | Accueil | Conservé | — | — |
| ORG-K1.o | Bloc Ce qui a bougé | Accueil | Accueil (lien vers le journal) | Conservé | ORG-B9 | — |
| ORG-K1.p | Bloc Derniers messages | Accueil | Accueil | Conservé | module `messages` | — |
| ORG-K1.q | Bloc Dossiers à compléter | Accueil | Accueil › À traiter (filtre) ; RH › Qualité des fiches | Mutualisé | `PersonnelProfileGapScanService` | — |
| ORG-K1.r | Bloc personnalisé (blocs libres) et édition | Accueil ; `BO/accueil/bloc*` | Accueil | Conservé | `settings.bo_home_blocks` | — |
| ORG-K1.s | Tableaux épinglés (6 au plus) | Accueil ; `BO/tableaux/*` | Accueil | Conservé | `bo_sheets` | — |
| ORG-K1.t | Action d'en-tête « Publier une annonce » | Accueil → formulaire bandeau | Accueil → Communauté › Publier | Mutualisé | 1.9 | — |
| ORG-K2 | Accueil opérateur (synthèse personnelle) | Tableau de bord opérateur | Accueil du membre | Conservé | `operatorOverview()` | Mêmes rubriques |

### 4.1 Plateforme `/admin`

Toutes les pages plateforme sont **conservées** dans la barre plateforme, avec leurs garde-fous (phrases de confirmation, sauvegarde obligatoire, vérification d'identité renforcée). Seuls changent la position de la barre et l'en-tête de page (document 3 §11).

| ID | Fonctionnalité | Emplacement cible | Statut | Conditions de préservation |
|---|---|---|---|---|
| ORG-H1 | Pilotage du site, console d'exploitation | Plateforme › Pilotage | Conservé | `OpsConsoleStepUp`, « RESTAURER », « SUPPRIMER » |
| ORG-H2 | Recherche | Plateforme › Pilotage | Conservé | Drapeau de shell plateforme vérifié (05 U8) |
| ORG-H3 | Indicateurs transverses | Plateforme › Pilotage | Conservé | — |
| ORG-H4 | Retours interface, avis, traductions | Plateforme › Pilotage | Conservé | — |
| ORG-H5 | Annuaire et fiche d'une communauté | Plateforme › Communautés | Conservé | `confirm_type_change`, `confirm_logo_removal` ; logo par le service commun (3.10) |
| ORG-H6 | Retrait d'une communauté | Plateforme › Communautés (depuis la fiche) | Conservé | Saisie du slug, sauvegarde obligatoire, tenant 1 protégé |
| ORG-H7 | Comptes et intervention | Plateforme › Communautés (depuis la fiche) | Conservé | Journal et annulation d'action |
| ORG-H8 | Organisations à supprimer | Plateforme › Communautés | Conservé | — |
| ORG-H9 | Restauration | Plateforme › Communautés | Conservé | Saisie de l'identifiant et case |
| ORG-H10 | Annuaire public, vitrine Discord | Plateforme › Communautés | Conservé | — |
| ORG-H11 | Contributeurs | Plateforme › Communautés | Conservé | — |
| ORG-H12 | Formules | Plateforme › Communautés | Conservé | Source des cartes d'offre de la création (RD-69) |
| ORG-H13 | Paiements, abonnements, dons ; recherche de membres (JSON) | Plateforme › Communautés | Conservé | Export CSV |
| ORG-H14 | Formules offertes | Plateforme › Communautés | Conservé | — |
| ORG-H15 | Démo et NDA | Plateforme › Communautés | Conservé | — |
| ORG-H16 | Comptes | Plateforme › Comptes et accès | Conservé | Redirection morte `/admin/users → back-office/users` retirée |
| ORG-H17 | Administrateurs et rôles du site, autorisations d'édition de fiche | Plateforme › Comptes et accès | Conservé | — |
| ORG-H18 | Outils du portail de candidatures | Plateforme › Comptes et accès | Conservé | — |
| ORG-H19 | Restrictions de la plateforme | Plateforme › Modération et sécurité | Conservé | — |
| ORG-H20 | Doublons Steam | Plateforme › Comptes et accès (ajouté à la barre) | Déplacé | Côté membre `/account/steam-doublon` inchangé |
| ORG-H21 | Bandeaux plateforme | Plateforme › Communication | Conservé | Cas `site.support` à clarifier (04 N3) |
| ORG-H22 | Messages aux communautés | Plateforme › Communication | Conservé | — |
| ORG-H23 | E-mails, rétrospective, lettre | Plateforme › Communication | Conservé | — |
| ORG-H24 | Coopération plateforme | Plateforme › Communication | Conservé | Confirmation de suppression à ajouter |
| ORG-H25 | Musique du site | Plateforme › Communication | Conservé | — |
| ORG-H26 | Réglages du site, version minimale du mod | Plateforme › Configuration | Conservé | `GameClientVersionGate` |
| ORG-H27 | Référentiel militaire | Plateforme › Configuration | Conservé | — |
| ORG-H28 | Intégrations de la plateforme | Plateforme › Configuration | Conservé | — |
| ORG-H29 | Tâches automatiques | Plateforme › Configuration | Conservé | Accueille les tâches nées du lot 0.15 |
| ORG-H30 | Mises à jour et déploiement | Plateforme › Versions et publication | Conservé | — |
| ORG-H31 | Registre des données | Plateforme › Exploitation | Conservé | Saisie de confirmation |
| ORG-H32 | Charge et stockage, navigateur de base | Plateforme › Exploitation | Conservé | « VIDER », saisie du nom de table |
| ORG-H33 | Maintenance | Plateforme › Exploitation | Conservé | — |
| ORG-H34 | Journal d'audit plateforme | Plateforme › Exploitation | Conservé | Rollback, alerte |
| ORG-H35 | Mod Overwatch : parc, bêta, rapports, alertes | Plateforme › Mod Overwatch | Conservé | — |

## 5. Pages relevées par la navigation seule

| ID | Fonctionnalité | Emplacement actuel | Emplacement cible | Statut | Dépendances | Conditions de préservation |
|---|---|---|---|---|---|---|
| NAV-01 | Qualité des données | Indicateurs | RH › Qualité des fiches | Déplacé | — | — |
| NAV-02 | Catalogue de l'organisation, modèle, aperçu, historique | Organisation › Structure | Cfg › Référentiels › Organisation type | Déplacé | — | — |
| NAV-03 | Types d'entité | Organisation › Structure | Cfg › Référentiels › Organisation type | Déplacé | — | — |
| NAV-04 | Disponibilité | Organisation | Cfg › Référentiels › Disponibilité | Déplacé | — | Masquage en profil Effectifs gardé |
| NAV-05 | Référentiel des compétences | Référentiels › Compétences | Cfg › Référentiels › Qualifications et compétences | Déplacé | — | — |
| NAV-06 | Cursus pilote | Référentiels › Compétences | Cfg › Référentiels › Qualifications et compétences | Déplacé | — | Chemin d'écriture d'une qualification par le service commun |
| NAV-07 | Référentiel des qualifications : créer, émetteurs, catalogue, fiche, attribuer, prérequis détectés, matrice, droits | Référentiels › Qualifications | Cfg › Référentiels › Qualifications et compétences ; attribution aussi depuis la Fiche | Réorganisé | ACC-F22 | `?user_id=` pré-rempli depuis la fiche |
| NAV-08 | Bureau de suivi roleplay : dossiers, immersion, phases | Roleplay | Roleplay › Bureau de suivi ; Fiche › Roleplay | Conservé | `BackOfficeTabs` | Anciennes adresses en 301 |
| NAV-09 | Échéances roleplay | Roleplay | Roleplay › Échéances | Conservé | — | — |
| NAV-10 | Sessions Arma | Roleplay | Roleplay › Sessions Arma ; Dossier › Exécution | Conservé | RD-49 | — |
| NAV-11 | Catégories | Vitrine et portail | Cfg › Organisation › Portail des membres | Déplacé | — | — |
| NAV-12 | Export pour contrôle | Réglages | Cfg › Organisation | Conservé | — | — |
| NAV-13 | Favoris du menu | groupe FAVORIS | Inchangé (12 au plus) | Conservé | `BackOfficeMenuController` | Chemins enregistrés réécrits lors des déplacements |
| NAV-14 | Formations : écrans `/formation/*` ; 27 anciennes adresses | Formations | Communauté › Formations | Conservé | — | Redirections gardées |
| NAV-15 | Export des lectures de doctrine, publication de documents | depuis Doctrine | Communauté › Documents et doctrine | Conservé | — | — |
| NAV-16 | Réglages du recrutement : paramètres, messages préfaits, questions Discord, équipe, liens et codes (créer), format de référence, nouvelle offre | Configuration, pages recrutement | RH › Recrutement › Réglages ; Invitations et codes ; Offres | Réorganisé | — | Ajout au menu |
| NAV-17 | Ma fiche, modification, onglet suivi | Ma situation | Mon espace › Ma situation | Conservé | — | — |
| NAV-18 | Mon unité | Ma situation | Mon espace › Ma situation ; lien vers l'extranet | Conservé | J-02 | — |
| NAV-19 | Mes qualifications | Ma situation | Mon espace › Ma situation | Conservé | — | La puce « QUALIF. » mène ici pour le membre seulement |
| NAV-20 | Mon carnet de vol | Ma situation | Mon espace › Ma situation | Conservé | — | — |
| NAV-21 | Mon avancement, dossier de carrière, décorations | Ma situation | Mon espace › Ma situation | Conservé | — | — |
| NAV-22 | Mon coffre, musique, mon compte | Ma situation | Mon espace › Ma situation ; compte ↗ | Conservé | — | — |
| NAV-23 | Mes démarches | Administratif | Mon espace › Mes démarches | Conservé | — | — |
| NAV-24 | Nouvelle fiche du tableau opérationnel | depuis le mur | Opérations › Mur opérationnel | Conservé | O-13 | — |
| NAV-25 | Nouveau modèle d'intégration | depuis Intégration | RH › Recrutement › Intégration | Conservé | PER-F24 | — |
| NAV-26 | Nouveau raccourci du portail | depuis Raccourcis | Cfg › Organisation › Portail des membres | Conservé | ORG-B6 | — |
| NAV-27 | Nouveau modèle d'e-mail, nouvelle liste | depuis E-mails | Communauté › Publier › E-mails | Conservé | ORG-C4 | — |
| NAV-28 | Nouveau type de coopération, édition d'annonce | depuis Coopérations | Coopérations | Conservé | O-14 | — |
| NAV-29 | Nouveau CR, nouvelle synthèse, nouvelle équipe de feu | depuis CR et Escouades | AAR-R › Comptes rendus ; Terrain › Équipes en jeu | Conservé | O-08, A-10 | — |
| NAV-30 | Remontées par joueur | depuis Remontées | AAR-R › Remontées | Conservé | R-04 | — |
| NAV-31 | Catalogue des consignes ATAK | depuis Consignes | Cfg › ATAK › Consignes et matériel conseillé | Conservé | A-15 | — |
| NAV-32 | Brouillage du renseignement (ancre) | redirection | Cfg › ATAK › Réalisme et réseau | Conservé | A-05 | — |
| NAV-33 | Historique et vérification des réglages réalisme | sans lien / action rapide | Cfg › ATAK › Réalisme et réseau | Réorganisé | A-06.a | Vue de vérification passée aux composants du shell (Bootstrap retiré) |
| NAV-34 | Fiche du parc ATAK | depuis le parc | Fiche › Terrain ATAK ; Terrain › Appareils | Réorganisé | A-09 | — |
| NAV-35 | Simulation des règles d'accès | sans lien | Retrait ou branchement avec ACC-F11 | Mutualisé | 0.7 | — |
| NAV-36 | Profils de rôles (presets) | hors menu | Cfg › Droits et accès › Niveaux d'accès (Modèles) | Mutualisé | ACC-F09 | — |
| NAV-37 | Sous-écrans plateforme (création, outils, données) | barre plateforme | Plateforme (inchangé) | Conservé | ORG-H | — |
| NAV-38 | Bloc personnalisé de l'accueil | depuis l'accueil | Accueil | Conservé | ORG-K1.r | — |
| NAV-39 | Création de grade, de grade d'avancement, de campagne | depuis Grades et Avancement | Cfg › Référentiels › Grades ; RH › Carrière | Conservé | PER-F37, F39 | — |
| NAV-40 | Anciens écrans d'emplois : création, attributions, droits du membre | hors menu | Cfg › Référentiels › Emplois | Mutualisé | PER-F18 | — |
| NAV-41 | Sous-écrans des effectifs | hors menu | Voir PER-F03, F09, F05, F17, F04, F15, F33 | Conservé | — | — |

---

## Contrôle de complétude

| Domaine | Identifiants de l'inventaire | Présents dans cette matrice |
|---|---|---|
| Personnel | PER-F01 à PER-F45, PER-H1 à PER-H10 | tous (55), plus 51 lignes de détail |
| Accès | ACC-F01 à ACC-F26 | tous (26), plus 1 ligne de détail |
| Opérations | O-01 à O-14, O-S1 à O-S5 | tous (19), plus 6 lignes de détail |
| ATAK back-office | A-01 à A-17 (dont A-12a, A-12b, A-16a à A-16e) | tous (22), plus 16 lignes de détail |
| Terrain et API | T-01 à T-10, T-S1 à T-S6, API-1 à API-11 | tous (27), plus 1 ligne de détail |
| JNET, renseignement, identité | J-01 à J-10, J-A, R-01 à R-04, R-05a à R-05r, I-01 à I-05, I-A | tous (39), plus 1 ligne de détail |
| Équipement | E-01 à E-08, E-S1 à E-S3 | tous (11), plus 2 lignes de détail |
| Organisation | ORG-A1 à A7, B1 à B11, C1 à C7 (C5a à c), D1, D2, E1 à E6, F1, F2, G1, I3, I4, J1, J2, K1, K2 | tous (44), plus 41 lignes de détail |
| Plateforme | ORG-H1 à ORG-H35 | tous (35) |
| Navigation | NAV-01 à NAV-41 | tous (41) |

Une vérification automatique (comparaison des identifiants des documents 1 et 6) est décrite en lot 7 (test 7.1) ; elle a été faite pour cette version : 319 identifiants dans chacun des deux documents, aucun manquant, aucun en trop.

Fonctionnalités ajoutées par la cible (aucune n'existait sous cette forme) : grade depuis le tableur (PER-F01.d), rôle dans l'unité sur la fiche (PER-F02.d), « Décerner » sur la fiche (PER-F31.a), « Rattacher » depuis les doublons (PER-F11), chronologie unique (PER-F02.t), droits effectifs calculés (Fiche › Accès), liste et révocation des appairages (T-09), liste des cartes et tuiles (API-9 à API-11), réglages du recrutement au menu (NAV-16).
