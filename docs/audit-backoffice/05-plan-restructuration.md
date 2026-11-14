# Document 5 : plan de restructuration

Feuille de route progressive, par lots. Chaque lot donne son contenu, ses dépendances et ses risques. L'ordre suit la règle : **sécurité d'abord, puis ce qui ne change rien aux données, puis les regroupements d'écrans, puis les changements de modèle**. Tout ce document est PROPOSITION ; les constats cités sont des FAIT des audits.

Règles qui s'appliquent à tous les lots :
- Une PR par sujet, petite, avec ses tests (voir lot 7).
- Aucune adresse supprimée : une page déplacée répond en 301 (`BackOfficeLegacyRedirects`, `BackOfficeTabs`).
- Aucune donnée supprimée avant que la nouvelle source soit en production et vérifiée.
- Les contrats lus par le mod et le téléphone (`/api/atak/*`, `/api/game/v1/*`, `/api/sse/*`, `/m/*`, `/connect/*`) ne changent pas dans les lots 0 à 4.
- Chaque nouvelle fonctionnalité suit `docs/VERSIONING.md`.

## Vue d'ensemble

| Lot | Objet | Change les données ? | Change les droits ? | Dépend de |
|---|---|---|---|---|
| 0 | Sécurité | Non (sauf S8 : nettoyage) | Oui, en les restreignant | — |
| 1 | Améliorations rapides | Non | Non (sauf ouverture du référentiel des grades) | — |
| 2 | Regroupements prioritaires (écrans) | Non | Le menu suit les droits | 1 ; 5a pour le menu cadre |
| 3 | Refontes structurelles (modèle) | Oui | Non | 2 |
| 4 | Mutualisations techniques | Non | Non | peut avancer en parallèle de 2 et 3 |
| 5 | Adaptation des permissions | Non | Oui | 0 ; 5a avant 2 |
| 6 | Risques de migration | — | — | transverse |
| 7 | Tests de non-régression | — | — | transverse |

## Travaux déjà engagés

| Changement | État | Lot concerné |
|---|---|---|
| Registre des droits : une ligne par membre, la position administrative n'est plus une ligne en double | déjà engagé (PR #1600, #1601, #1602) | Lot 1 (affichage) ; le lot 3.2 traite le stockage |
| Positions administratives personnalisées par communauté, page Effectifs › Positions administratives | déjà engagé (PR #1600, #1601, #1602) | Lot 3.2 (sortie du RBAC) doit partir de ce nouveau référentiel |
| Accueil du back-office : un titre par carte, plus de surtitres | déjà engagé (PR #1600, #1601, #1602) | Lot 1.12 (surtitres du reste du shell) ; lot 2.2 (accueil « À traiter ») |
| « Mon service » dans le menu du back-office | déjà engagé (PR #1600, #1601, #1602) | Lot 2.2 (file « À traiter » unique) |

Ces PR doivent être fusionnées avant les lots 2 et 3, pour éviter des conflits sur `ath_sidebar_nav.php`, `AccessRegisterService.php`, `PersonnelDutyPositionService.php` et la vue de l'accueil.

---

## Lot 0 : sécurité

À traiter avant tout le reste. Chaque correction commence par un test qui reproduit le défaut sur un tenant de test (les failles ont été tracées dans le code, pas exécutées).

| N° | Contenu | Référence | Dépendances | Risques de la correction |
|---|---|---|---|---|
| 0.1 | JNET : lire les dossiers d'intérêt avec `listVisibleForUser` ; filtrer événements, fiches terrain et compteurs par classification (`SseClearanceService`) ; masquer Renseignement, Cibles et Exploitation sans habilitation | S1 ; `JnetDashboardService.php:208-216, 700, 1015, 1031` | — | Des membres perdent la vue des cibles : prévenir les communautés ; vérifier que les compteurs d'Exploitation ne révèlent pas l'existence de dossiers |
| 0.2 | API de la carte tactique : middleware Auth + droit (ATAK `manage` pour écrire, `view` pour lire) + CSRF sur POST, PATCH, DELETE ; `isWeb()` ne suffit plus | S2 ; `routes/web.php:3121-3125` ; `AthenaTacticalApiController.php:163-166` | — | L'éditeur JS (`athena-tactical-editor.js`) doit envoyer le jeton CSRF ; vérifier que le mod n'utilise pas cette API (sinon, chemin par clé) |
| 0.3 | `roleplay/reset` en POST avec CSRF ; garder la route GET en redirection vers la page, sans effet | S3 ; `web.php:2563` ; `AdminAtakRoleplayController.php:307` | — | Le bouton de `views/admin/atak/roleplay.php:15` et de `roleplay_enhanced.php` devient un formulaire |
| 0.4 | Décision d'élévation : exiger `canManageRoles` dès que la demande accorde un niveau ou une permission ; `canManageStatus` suffit pour grade, emploi, unité ; refuser toute permission que le décideur ne détient pas lui-même | S4 ; `EffectifsWorkspaceController.php:4193, 4284` ; `ElevationApprovalService.php:360-478` | — | Les RH ne peuvent plus valider des demandes de droits : prévoir l'affichage « en attente d'un responsable des accès » |
| 0.5 | `/c/{slug}/setup` : exiger `admin.organization` ou retirer la route (aucun lien entrant) en redirigeant vers Configuration | S5 ; `web.php:389-390` | — | Aucun, sauf favoris personnels |
| 0.6 | Paramètres et pages de communauté : contrôle interne `admin.organization|admin.access` (ou droit dédié, lot 5) dans `OrganizationSettingsController`, `TenantInitialSetupController`, `OrganizationCommunityController`, `TenantAlertsController`, `TenantMiniArticlesController` ; `updateType` réservé à `admin.organization` | S6 | 5a (déclaration des droits) souhaitable, pas bloquante | Les comptes `site.support` perdent l'écriture : décider avec le produit si l'assistance garde la lecture |
| 0.7 | Brancher `TenantAdminAuditMiddleware` sur les routes POST du back-office, ou le supprimer ; retirer ou brancher `AccessControlMiddleware` ; restreindre les GET de `/api/access-control/*` à `admin.*` | S7 | — | Brancher l'audit sur 700 routes peut doubler des écritures déjà journalisées par les contrôleurs : dédoublonner |
| 0.8 | Droits par qualification : soit les rendre lisibles par `RbacService` (table créée par migration), soit retirer l'écran et le dire dans l'interface | S8 ; `QualificationPermissionGrantService.php:95-128` | décision produit | Si on les active, des membres gagnent des droits d'un coup : prévoir un rapport « droits qui seront accordés » |
| 0.9 | Ancienne route `POST /users/{id}/duty-position` : même contrôle que la fiche (`canManageStatus`) ou suppression | S9 | — | Vérifier que rien ne l'appelle (vues orphelines, `backfill`) |
| 0.10 | Documents RH : filtrer le téléchargement par droit sur le membre | S10 | lecture de `RhDossierWorkspaceController` 238-253 | — |
| 0.11 | Décorations : vérifier l'appartenance au tenant du membre et de la décoration avant insertion | S11 | — | — |
| 0.12 | Onboarding : proposer grade et niveau seulement si le recruteur a `canManageGrades` / `canManageRoles` | S12 | — | Les recruteurs doivent passer par une demande RH |
| 0.13 | API tactiques sans middleware : lire `TacticalMissionScope` et chaque écriture ; ajouter le droit manquant | S13 | lecture du mod | Le mod écrit peut-être par ces routes : tester avec le mod |
| 0.14 | `POST /api/athena/v2/dev/seed` : réservé à `admin.system` ou retiré hors développement | S14 | — | — |
| 0.15 | GET qui écrivent (matricules, codes d'unité, `completeForTenant`, réalisme) : passer en tâche ou en bouton | S16 | — | Les matricules manquants ne s'attribuent plus « tout seuls » à l'affichage : prévoir une tâche planifiée |

Critère de sortie du lot : chaque point a un test automatique qui échouait avant la correction.

---

## Lot 1 : améliorations rapides

Sans changement de modèle ni de droits. Chaque ligne est une petite PR.

| N° | Contenu | Référence | Dépendances | Risques |
|---|---|---|---|---|
| 1.1 | Fusionner « Rôles » et « Droits par membre » ; supprimer `droits.php` | ACC-F02, PER-H3 | — | Aucun |
| 1.2 | Aligner les libellés entre menu, onglets du bureau, recherche (14 pages) | RD-40 ; 05 §4.2 | — | Documentation et tutoriels à mettre à jour |
| 1.3 | Corriger les liens vers d'anciennes adresses : 6 dans `config/navigation.php`, raccourci ORBAT de `shell.php:76`, lien `board_pilotage.php:170` vers `back-office/cooperation` sans route | 05 §2.5 ; 03 N11 | — | — |
| 1.4 | Masquer « Départs » sans `canManageStatus` ; Overwatch marqué actif ; supprimer la condition dupliquée `NAV:73` | 01 F12 ; 05 §4.3 n°10-11 | — | — |
| 1.5 | Lier les critères d'élévation depuis la page Élévations ; l'adéquation depuis la Chaîne | 01 H10 | — | — |
| 1.6 | Ouvrir `/referentiels/grades` à `personnel.grades.manage` (exception d'OAM) | 01 F37, P3 | — | Élargit un accès en lecture et écriture aux porteurs du droit grade : vérifier que c'est voulu |
| 1.7 | Export CSV : reprendre tous les filtres du tableur | 01 F05 | — | Les exports deviennent plus courts quand un filtre était ignoré |
| 1.8 | Affectation en masse : envoyer la notification de changement de structure | 01 F07 | — | Volume de notifications |
| 1.9 | Bouton « Publier une annonce » de l'accueil vers le centre de diffusion | 04 §2.3 | — | — |
| 1.10 | Mon service : statut et priorité en clair au lieu de `open`, `normal` ; lien retour vers le back-office | 02 F17 | PR « Mon service » fusionnée | — |
| 1.11 | Rendre `/orbat` dans le shell (ajouter `orbat` à `chromePathPrefixes`) ; icône « sortie » sur Overwatch, 9-LINE, Arsenal, Compte | 05 §4.3 n°1-3 | — | Mise en page de l'ORBAT dans un conteneur plus étroit |
| 1.12 | Retirer les surtitres de `config/back_office_pages.php` (141) et de la barre mobile ; garder titre et sous-titre | 05 §4.4 | PR accueil fusionnée | Aucun fonctionnel |
| 1.13 | Libellés « Accueil » (BO) et « Portail » (`dashboard`) pour les trois liens « Tableau de bord » | 05 §3.2 | — | — |
| 1.14 | Pastilles : un seul format (99+) ; ne pas compter deux fois candidatures et signalements | 05 §3.1 | — | — |
| 1.15 | Mode nuit : régénérer `back-office-dark.generated.css`, ajouter les feuilles manquantes à `SOURCES`, contrôle en CI | 05 §3.3 | — | Diff CSS important ; revue visuelle de 10 pages |
| 1.16 | Recherche : charger `back-office-search.js` dans le shell plateforme ou masquer le champ ; afficher Ctrl ou ⌘ selon le système | 05 §3.4 | — | — |
| 1.17 | Fil d'Ariane dérivé de l'arbre du menu (`BackOfficeNavTree::resolve`) | RD-39 | — | Les groupes de `back_office_pages.php` deviennent inutiles |
| 1.18 | Page « Tenues mises en avant » : même libellé dans le menu et dans le titre ; « Page d'accueil publique » idem | 03 E-08 ; 04 B2 | — | — |
| 1.19 | Page plateforme « Doublons Steam » dans la barre plateforme | 05 §2.3 | — | — |
| 1.20 | Supprimer le bandeau « photos perdues lors de la migration » de l'accueil (scan à chaque affichage), ou le limiter à une vérification planifiée | 04 §2.1 bloc 3 | — | — |

Critère de sortie : aucune page n'a changé d'adresse ; la carte des routes du lot 7 est identique.

---

## Lot 2 : regroupements prioritaires

Regrouper des écrans existants sans toucher aux tables. Les vues et contrôleurs actuels sont réutilisés comme onglets ou sections.

| N° | Contenu | Référence | Dépendances | Risques |
|---|---|---|---|---|
| 2.1 | **Menu cadre** : le menu se construit à partir des droits (document 3 §9) ; groupe « Mon périmètre » ; TBA proposé à tout porteur d'un droit de gestion | RD-79, RD-37 ; ACC-F18, F23 | 5a (déclaration unique) | Des pages jusqu'ici cachées deviennent visibles : c'est voulu, mais il faut d'abord le lot 0 (S4, S6) |
| 2.2 | **Accueil « À traiter »** unique : file RH, candidatures, demandes, avis de circuit, signalements, filtrée par périmètre ; un seul calcul de compteurs pour le menu et l'accueil ; calcul des blocs masqués supprimé | RD-14 ; 04 §2.2 | PR accueil et Mon service fusionnées | Les chiffres changent (dédoublonnage) |
| 2.3 | **Demandes RH** : une page à filtres par type (élévation, correction, mobilité, absence) ; avis de circuit affiché dans la ligne ; les pages actuelles deviennent des filtres (301) | RD-13 | 0.4 | Les droits de décision restent ceux de chaque type |
| 2.4 | **Fiche membre à onglets** (document 3 §5), phase 1 : réorganiser les cartes en onglets ; ajouter le rôle dans l'unité, « Décerner une décoration », la chronologie unique en lecture ; documents RH, mobilité, papiers dans la fiche | RD-15, RD-25, RD-54 ; 01 P2, P4 | 2.1 pour l'affichage par droit | Ancres actuelles (`#invitations`, etc.) à rediriger vers les onglets |
| 2.5 | **Espace d'unité** (document 3 §6), phase 1 : sur la fiche d'unité, onglets qui embarquent les vues filtrées (membres, postes, chaîne, qualifications) | parcours 3 | 1.11 | Performance des vues filtrées sur une grosse unité |
| 2.6 | **Groupe Configuration** (niveau 3), replié : y ranger Organisation, Référentiels, Droits et accès, ATAK, Intégrations, Réglages RH ; supprimer la page de liens « Configuration » | RD-41 ; 04 B7 | 1.2 | Habitudes des gestionnaires : prévoir un message « où est passé… » |
| 2.7 | **Configuration ATAK unifiée** (document 3 §8), phase 1 : une page à sections qui lit et écrit les stockages actuels ; A-03 devient un tableau de bord en lecture ; `guardAtak('manage')` sur toutes les sections | RD-10, RD-31 (affichage) | 0.3 | Les sections lisent encore trois stockages pour le réseau : afficher la source utilisée, à confirmer avec le mod (03 N6) |
| 2.8 | **Organisation** : un formulaire « Inscription et contact », un formulaire « Identité et image », une « Page publique » (contenu, éditeur en place, visibilité) ; checklist de démarrage unique (fusion A4 et A5) | RD-12, RD-42, RD-43, RD-44, RD-45 | 0.6 | Écrasement de clés par `normalizeFromRequest` (04 N1) : lire le service avant de déplacer les champs |
| 2.9 | **Discord** : une section dans Intégrations avec lien d'invitation, salons, routage, bot, compagnon | RD-30 | 2.8 | Le jeton du bot change d'écran, pas de clé de stockage |
| 2.10 | **Publier** : un point d'entrée qui choisit le format (annonce, bandeau, article, e-mail) et applique le même filtre de canaux | RD-29 | — | C2 hérite du filtre de canaux de C1 : un utilisateur peut perdre un canal |
| 2.11 | **Équipement et dotation** : un groupe ; un éditeur de kit ; dotation sortie des référentiels | RD-32, RD-33 | 5.4 (droits des kits) | Profil ATAK et module personnel (lot 5) |
| 2.12 | **Discipline** : sanctions, signalements, quarantaine dans un groupe | RD-28 | — | — |
| 2.13 | **Référentiel des grades** à onglets (système, grades, ordre et filières, OTAN, critères) | RD-36 ; parcours 7 | 1.6 | Deux contrôleurs derrière une page |
| 2.14 | **Terrain ATAK › Appareils** : parc, certificats et appairages sur une page ; liste et révocation des `tactical_phone_pairings` | RD-34 ; 03 T-09 | — | Révoquer un appairage coupe un téléphone en jeu : confirmation explicite |
| 2.15 | **Dossier d'opération**, phase 1 : page qui affiche, pour un plan, l'événement, les diapositives, le cycle, les CR et l'AAR déjà liés, et propose de lier ce qui ne l'est pas | RD-09, RD-18 | — | Liens par titre existants (AAR ↔ événement) affichés comme « à confirmer » |

Critère de sortie : toutes les anciennes adresses répondent (200 ou 301) ; menus par profil conformes aux captures du lot 7.

---

## Lot 3 : refontes structurelles

Changements de modèle de données. Chaque point a un plan de migration réversible.

| N° | Contenu | Référence | Dépendances | Risques |
|---|---|---|---|---|
| 3.1 | **Dossier d'opération**, phase 2 : identifiant commun (`mission_plans.id`) ; écrire `operations.mission_plan_id`, `mission_plans.cycle_id`, `aar_reports.event_id` (nouvelle colonne) ; lien vivant du mur (`linked_id`) ; le cycle s'ouvre au passage en live | RD-09, RD-19, RD-20 ; 03 O-02, O-11 | 2.15 | Rapprochement des données existantes : par titre et date, avec revue manuelle des cas douteux |
| 3.2 | **Position administrative hors RBAC** : table propre (partant du référentiel personnalisé déjà engagé) ; lecture double pendant la transition ; suppression des rôles `status_*` après vérification ; un seul droit `personnel.status.manage` | RD-07 ; 02 §3.3 | PR positions fusionnée ; 2.4 | Règles métier liées (durée minimale en formation, engagement en mission, effectif opérationnel, `AdministrativePositionCatalog.php:36-84`) à reporter à l'identique |
| 3.3 | **Grade, source unique** : la publication d'une campagne met à jour `grade_id` via `UserRepository::update` ; frise unique (fusion de `member_grade_changes` et `personnel_grade_history` en lecture, puis en écriture) | RD-04 | 2.4 | Grades déjà publiés par campagne mais non appliqués : rapport avant correction, pas de mise à jour silencieuse |
| 3.4 | **Poste ORBAT et affectation** : occuper un poste met à jour l'affectation principale (ou l'inverse), dans un service ; `role_name` relié au poste quand il existe | RD-21, RD-24 | 4.1 | Membres affectés sans poste et postes occupés sans affectation : rapport de cohérence avant bascule |
| 3.5 | **Réalisme, une source** : `atak_realism_config` versionnée fait foi ; colonnes `roleplay_*` et `net_*` migrées ; modules et flux de données sortis du cache fichier vers la base, inclus dans l'export et la purge | RD-31 ; 03 N6, N7 | 2.7 ; lecture du mod | **Élevé** : comportement en jeu. Tester avec le mod sur un serveur de test avant toute bascule ; garder la lecture des anciennes colonnes en repli une version |
| 3.6 | **Overwatch ancien** : porter appui-feu, logistique, zones dangereuses et cartes perso dans `/atak` ; puis rediriger `/overwatch` et `/tacmap` | RD-17 ; 03 T-02 | — | Fonctions utilisées en opération : à faire hors période d'opérations ; contrôle de formule `atak` appliqué au passage (03 N9) |
| 3.7 | **Kits** : un éditeur ; renommer les homonymes (« Lot de droits », « Liste de matériel conseillé », « Kit de couverture ») | RD-32 | 2.11 | Libellés dans le mod et le téléphone (« kit ») : vérifier les textes côté jeu |
| 3.8 | **Accès sur la fiche** : niveaux avec dates et périmètre (même service que le registre), droit individuel direct pour `canManageRoles`, droits effectifs calculés | RD-02, RD-08 | 0.4 ; 4.3 | Calcul des droits effectifs : doit refléter `RbacService` exactement (implications, droits site) |
| 3.9 | **Rôles semés et anciens éditeurs** : migrer les ~30 rôles semés vers des niveaux visibles ou les retirer ; supprimer `FunctionBasedAccessCatalog` (code mort), les éditeurs F09 et F10 après redirection | RD-03, RD-70 | 3.8 | Des membres perdent des droits portés par un rôle invisible : rapport par communauté avant suppression |
| 3.10 | **Logo et images** : une colonne source ; un composant de téléversement | RD-44, RD-45 | 2.8 | Désynchronisation existante : choisir la valeur la plus récente |
| 3.11 | **Identité RP, une édition staff** : la fiche front renvoie vers la fiche BO pour le staff ; `display_name` et `character_name` clarifiés | RD-15 | 2.4 | Les membres gardent l'édition de leur propre fiche |
| 3.12 | **Création d'un membre, un service** : assistant, sans compte, import, onboarding, invitation, ORBAT passent par le même provisioning | RD-16 ; RD-60 | 4.1 | Les deux imports CSV ont peut-être des formats différents (01 §4 n°6) |
| 3.13 | **Équipes en jeu et groupes opérationnels** : les équipes de feu remontées se rattachent au dossier d'opération ; les équipes permanentes restent dans l'ORBAT | RD-50 | 3.1 | API `/api/atak/fire-teams` inchangée |

---

## Lot 4 : mutualisations techniques

Sans effet visible pour l'utilisateur, ou seulement de cohérence. Peut avancer en parallèle des lots 2 et 3.

| N° | Contenu | Référence | Dépendances | Risques |
|---|---|---|---|---|
| 4.1 | Services d'écriture uniques : affectation (`syncPrimaryAssignmentFromDossier`), emplois (`updateMemberJobRoles`, avec prérequis), grade, qualification, sanction, matricule (hors GET) | RD-05, RD-06, RD-23, RD-28, RD-52 | — | Les chemins qui contournaient les prérequis deviennent bloquants : prévoir les dérogations |
| 4.2 | Service d'attribution d'un niveau d'accès unique (fiche, registre, élévation, kits) ; suppression des POST sans écran (`assign`, `quick-assign-role`, `apply-role-set`, `users/{id}/update`) après vérification des journaux | RD-02 ; PER-H2, PER-H4 | 0.4 | Un outil externe appelle peut-être ces routes : lire les journaux d'accès d'abord |
| 4.3 | Service « droits effectifs » qui expose l'union calculée par `RbacService` | RD-08 | — | — |
| 4.4 | Service « module ouvert ? » qui combine type de communauté et formule et donne la raison du refus | RD-51 | — | — |
| 4.5 | Composants d'interface : bouton (`ath-btn`), tableau avec filtres (`ath_table.php`, `ath-filters`), confirmation (`data-ui-confirm` à la place de 84 `confirm()`), onglets, flash du shell seul, état vide, en-tête de page | RD-74 à RD-78 | — | Changements visuels sur de nombreuses pages : par lots de modules |
| 4.6 | Recherche unique (globale et bureau) avec filtre de périmètre ; un seul raccourci | RD-71 | — | — |
| 4.7 | Couleurs en jetons (`--ath-*`) au lieu de 1 140 couleurs codées en dur ; points de rupture limités à une échelle | 05 §3.3, §3.4 | 1.15 | Travail long : faire au fil des modules touchés |
| 4.8 | Composant RSVP unique pour les 5 surfaces de réponse | RD-27 | — | — |
| 4.9 | Une route canonique par page (traçabilité, partage, analytics, première liaison, révocation d'appareil, certificats) | RD-63 | 5a | Middlewares différents selon l'URL : retenir le plus strict |
| 4.10 | Retirer le code mort après vérification : vues orphelines (`users/*`, `droits.php`, `views/atak.php`, `views/equipment/index.php`…, JNET), `PortalNextStepsService::forHub`, rail au survol, API logistique sans consommateur | 01 H ; 02 §5 ; 03 E-01, J-A, N17 ; 05 P10 | lot 7 (carte des routes) | Une vue « morte » peut être incluse dynamiquement : recherche exhaustive avant suppression |

---

## Lot 5 : adaptation des permissions

| N° | Contenu | Référence | Dépendances | Risques |
|---|---|---|---|---|
| 5a | **Déclaration unique** : chaque page déclare son droit de lecture et d'écriture ; route, menu, recherche et fil d'Ariane la lisent ; les exceptions d'`OrganizationAdminMiddleware` sont générées depuis cette déclaration | RD-37 ; P4 du document 3 | — | Point le plus risqué du plan : générer la nouvelle table, la comparer à l'ancienne page par page, et n'écarter aucune différence sans décision |
| 5.1 | Profils prêts à l'emploi pour les cadres : RH (existant), Recruteur, Responsable des accès, Officier des opérations, Cellule renseignement, Logisticien, Communication, Chef d'unité (dérivé de la chaîne) | document 3 §9.2 | 5a | Ne pas multiplier les niveaux : partir des permissions existantes |
| 5.2 | Droit de lecture des pages RH par défaut pour le profil RH, sans `admin.*` | 02 §2.3 | 5a | — |
| 5.3 | Pages de configuration d'organisation : droit dédié (par exemple `tenant.configuration.manage`, déjà lu par `ConfigurationUpdateController.php:199-203`) au lieu d'ORG qui laisse passer `site.support` | S6 ; 04 §0 | 0.6 | Rôle de l'assistance à décider |
| 5.4 | Kits : même droit (`personnel.equipment.manage`) sur tous les écrans | RD-32 | — | Élargit E-05 ou restreint E-01 et E-02 : décision produit |
| 5.5 | Dotation : rattacher au module équipement (et non personnel) pour qu'elle reste visible en profil ATAK, si le produit le souhaite | RD-33 ; 03 E-06 | — | Changement de périmètre des formules |
| 5.6 | Habilitations SSE visibles dans le registre (lecture) et sur la fiche | RD-08 | 3.8 | — |
| 5.7 | Pages ATAK : `guardAtak` partout où seul RES s'applique aujourd'hui (11 contrôleurs) ; détection des marqueurs en RES et non ORG | 03 §1.B, A-13 | — | Des administrateurs sans le module ATAK `manage` perdent l'accès : vérifier la matrice du module |
| 5.8 | Libellé du socle : « Membre » partout (le code), ou « Opérateur » (la migration) ; une seule décision | 02 F24 | — | Textes et traductions |

---

## Lot 6 : risques de migration

| Risque | Où | Mesure |
|---|---|---|
| Liens cassés (favoris, documentation, messages Discord, tutoriels) | toutes les pages déplacées | 301 systématiques ; carte des redirections testée (lot 7) ; aucune suppression de route dans les lots 1 à 4 |
| Favoris du menu enregistrés par chemin | `bo_user_nav_prefs`, groupe FAVORIS | Réécrire les chemins enregistrés vers la nouvelle adresse lors de la mise à jour |
| Disposition de l'accueil enregistrée | `bo_user_nav_prefs.home_json` | Garder les identifiants de blocs ; ignorer les blocs retirés |
| Perte d'accès d'un cadre | lots 0, 5 | Rapport « qui perd quoi » par communauté avant chaque changement de droit ; message dans « Messages de la plateforme » (ORG-B10) |
| Gain d'accès non voulu | menu cadre, ouverture du référentiel des grades, droits par qualification | Même rapport, sens inverse |
| Comportement en jeu | lot 3.5 (réalisme), 3.6 (Overwatch), 0.2 (carte tactique) | Serveur de test avec le mod ; bascule hors période d'opérations ; repli sur l'ancienne source pendant une version |
| Contrats du mod et du téléphone | `/api/atak/*`, `/api/game/v1/*`, `/api/sse/*`, `/m/*` | Aucun changement de contrat ; tests de contrat (lot 7) |
| Données incohérentes déjà présentes | grades de campagne non appliqués, affectations sans poste, logos désynchronisés, liens AAR par titre | Rapports de cohérence avant migration ; corrections proposées, jamais silencieuses |
| Fichiers de cache (modules, flux, mods) | `storage/cache/atak-modules`, `atak-data-flows` | Migration vers la base avec relecture de contrôle ; inclure dans l'export |
| Traductions | `docs/I18N_GLOSSARY.md`, version anglaise | Glossaire du document 3 §2 ajouté au glossaire de traduction |
| Formules et profils de communauté | `FeatureGateService`, `TenantTypeConfig` | Une page déplacée garde sa clé de formule et son module |
| Conflits entre PR | `ath_sidebar_nav.php`, `config/back_office_pages.php`, `routes/web.php` | Fusionner les PR déjà engagées d'abord ; une PR à la fois sur ces fichiers ; aligner les têtes de branche |
| Performance | accueil, fiche à onglets, espace d'unité | Calcul paresseux par onglet ; ne plus calculer les blocs masqués |

---

## Lot 7 : tests de non-régression

| N° | Test | Contenu | Lots couverts |
|---|---|---|---|
| 7.1 | Carte des routes | Pour chaque page de l'inventaire (document 1), statut attendu par profil (200, 301, 403, redirection) ; généré depuis la déclaration unique (5a) | tous |
| 7.2 | Matrice d'accès par profil | 10 profils (admin plateforme, gestionnaire, assistance, RH, recruteur, responsable des accès, auditeur, modérateur, chef d'unité, membre) × pages du back-office | 0, 2.1, 5 |
| 7.3 | Tests de sécurité | Un test par point S1 à S16, écrit avant la correction | 0 |
| 7.4 | Menus par profil | Instantané de l'arbre du menu calculé pour chaque profil ; comparaison à la maquette du document 7 | 2.1, 2.6 |
| 7.5 | Redirections | Chaque ancienne adresse répond 301 vers la bonne cible, en un seul saut | 1, 2, 3 |
| 7.6 | Services d'écriture | Affectation : notification et audit identiques par tous les chemins ; emplois : prérequis appliqués partout ; grade : la campagne met à jour `grade_id` ; position : règles de durée | 3, 4 |
| 7.7 | Contrats externes | Réponses de `/api/atak/client-init`, `/api/game/v1/config`, `/api/atak/realism/config`, `/api/atak/mission-plan*`, `/api/sse/identity/pack` comparées avant et après | 2.7, 3.5 |
| 7.8 | Données | Rapports de cohérence (grades, affectations, logos, liens AAR) sans écart après migration | 3 |
| 7.9 | Interface | Mode nuit à jour (CI) ; 10 pages à 375 px ; un seul `<h1>` par page ; aucun `confirm()` natif dans les pages touchées | 1.15, 4.5 |
| 7.10 | Exports | Colonnes et filtres des exports CSV et PDF inchangés ou documentés | 1.7, 2 |
| 7.11 | Recette manuelle | Les 14 parcours du document 4, par un gestionnaire et un cadre délégué, sur un tenant de test | 2, 3 |

---

## Ordre recommandé

| Étape | Contenu |
|---|---|
| 1 | Fusionner les PR déjà engagées (#1600, #1601, #1602) |
| 2 | Lot 0, points 0.1 à 0.8 (failles principales), avec leurs tests 7.3 |
| 3 | Lot 0, points 0.9 à 0.15, dès que la lecture du code les confirme ; en parallèle, lot 1 en petites PR et 7.1 (carte des routes) |
| 4 | Lot 5a (déclaration unique), puis 2.1 (menu cadre) et 2.2 (À traiter) |
| 5 | Lot 2, points 2.3 à 2.15, un regroupement par PR |
| 6 | Lot 4 au fil de l'eau sur les modules touchés |
| 7 | Lot 3, un changement de modèle à la fois, chacun après son rapport de cohérence |
