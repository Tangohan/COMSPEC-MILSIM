# Document 3 : architecture cible du back-office

Ce document décrit l'arborescence actuelle en résumé, puis l'arborescence cible complète. Tout ce qui est cible est une **PROPOSITION**. Les constats qui la motivent sont des **FAIT** tirés des audits (références entre parenthèses). Les fréquences d'usage qui ont servi au classement en trois niveaux sont des **HYPOTHÈSES** : elles doivent être validées par les indicateurs d'usage par page avant la mise en œuvre.

## 1. Principes

| N° | Principe (PROPOSITION) | Constat qui le motive (FAIT) |
|---|---|---|
| P1 | **Une entité, une page centrale.** Le membre, l'unité, l'opération et l'organisation ont chacun une page avec des onglets. Les listes servent à trouver l'entité ; les actions se font sur l'entité. | Une affectation s'écrit par 11 chemins (RD-05) ; un grade par 8 (RD-04) ; une opération se prépare dans 6 familles de menu (03 P1). |
| P2 | **Trois niveaux d'usage.** Courant (ce qu'on fait chaque semaine), gestion approfondie (ce qu'on fait chaque mois ou par dossier), configuration avancée (ce qu'on règle à l'installation puis rarement). Le niveau 3 est replié par défaut. | 137 liens, 4 niveaux, tout ouvert par défaut (05 §4.5). |
| P3 | **Le menu se construit à partir des droits, pas de trois droits globaux.** Un cadre délégué voit les pages qu'il peut ouvrir. | Un RH, un recruteur, un responsable des accès voient le menu d'un simple membre (`back_office_sidebar.php:13-15`, revérifié ; 02 §2.3 ; 05 §1.1). |
| P4 | **Une déclaration d'accès par page**, lue par la route, le menu, la recherche et le fil d'Ariane. | Les exceptions du middleware et les règles du menu sont deux listes séparées qui divergent (RD-37). |
| P5 | **Un service par écriture métier.** Chaque donnée (affectation, emploi, grade, niveau d'accès, qualification, sanction) a un seul service d'écriture, quel que soit l'écran. | Prérequis contournables, notifications perdues, droits différents selon le chemin (RD-02, RD-05, RD-06). |
| P6 | **Ne pas fusionner des concepts distincts.** On regroupe des écrans, jamais des notions. Voir le glossaire (§2). | Le mot « fonction » couvre 7 notions et « poste » 3 sens (RD-24) ; la position administrative est stockée comme un droit (RD-07). |
| P7 | **Les adresses existantes sont gardées** quand c'est possible. Une page fusionnée répond par une redirection 301 vers son nouvel emplacement (mécanisme existant `BackOfficeLegacyRedirects`, `BackOfficeTabs`). | Les favoris, les liens de documentation, le mod et le téléphone utilisent ces adresses (03 §0.2). |
| P8 | **Rester dans le shell du back-office.** Un lien qui sort du back-office l'indique. | 6 liens de gestion sortent du shell sans prévenir, dont l'organigramme (05 §1.3, §4.3). |
| P9 | **Pas de surtitre au-dessus des titres.** Un en-tête de page = un titre et, au besoin, une phrase. | 141 pages ont un kicker (05 §4.4). Déjà engagé sur l'accueil (PR #1600, #1601, #1602). |
| P10 | **La profondeur roleplay est un atout à préserver.** Identités, papiers, couvertures, position administrative, chaîne de commandement, suivi RP gardent une place visible, avec leur vocabulaire militaire. | Le domaine SSE compte 303 routes ; le suivi RP, l'immersion et les phases RH existent (03 §1.E, 01 F24). |

## 2. Glossaire des concepts à garder distincts

| Concept | Définition retenue | Stockage actuel (FAIT) | Où il se gère dans la cible | Ne pas confondre avec |
|---|---|---|---|---|
| Statut du compte | État technique du compte : actif, suspendu, désactivé | `users.status` | Fiche membre › Position et statut | Position administrative |
| Position administrative | Situation de service RH : en formation, en service actif, inactif, renfort, et positions propres à la communauté | Rôles RBAC `status_*` (`AdministrativePositionCatalog.php:16-19`) ; positions personnalisées en cours (PR #1600, #1601, #1602) | Fiche membre › Position et statut ; référentiel dans Configuration › Référentiels › Positions administratives | Niveau d'accès (elle ne donne aucun droit, 02 §3.3) ; « Postes » de `positions` (catégorie « Administratif » homonyme) |
| Grade | Rang hiérarchique dans l'échelle de la communauté | `users.grade_id` ; historiques `member_grade_changes` et `personnel_grade_history` | Fiche membre › Carrière ; échelle dans Configuration › Référentiels › Grades | Emploi, qualification |
| Emploi | Métier du référentiel (fusilier, infirmier, chef de groupe…). Ne donne aucun droit. | `personnel_job_roles`, lien `personnel_profile_job_roles` | Fiche membre › Affectation et poste ; référentiel dans Configuration › Référentiels › Emplois | Niveau d'accès ; l'interface actuelle l'appelle parfois « fonction » |
| Fonction (poste tenu) | Place occupée dans une unité : un poste de l'organigramme et son intitulé | `orbat_billets` (titulaire) ; `personnel_assignments.role_name` (texte libre) | Espace d'unité › Postes ; Fiche membre › Affectation et poste | Emploi (un poste attend un emploi, il n'est pas un emploi) |
| Qualification | Compétence attestée, datée, avec échéance | qualifications des membres, cursus pilote, académie | Fiche membre › Qualifications et distinctions ; référentiel dans Configuration › Référentiels › Qualifications | Habilitation |
| Habilitation | Accès spécifique soumis à une classification ou à une désignation : codes SSE, émetteur de qualification habilité | `sse_access_codes`, émetteurs habilités | Fiche membre › Accès et habilitations ; Configuration › Droits et accès › Habilitations | Permission RBAC |
| Niveau d'accès | Rôle RBAC qui regroupe des permissions : Membre, RH, Gestionnaire, niveaux personnalisés | `roles`, `tenant_user_roles`, `user_roles` | Fiche membre › Accès et habilitations ; définition dans Configuration › Droits et accès › Niveaux d'accès | Position administrative, emploi |
| Permission | Droit unitaire (slug). Un droit individuel est une permission donnée ou retirée à une personne. | `permissions`, `role_permissions`, `user_permission_overrides` | Définition par niveau ; droit individuel sur la fiche | — |
| Unité | Élément permanent de l'organigramme (section, groupe de combat, compagnie…) | `units` | Unités › Organigramme, Espace d'unité | Groupe opérationnel |
| Groupe | Regroupement transversal hors chaîne de commandement (HYPOTHÈSE sur l'usage actuel : les écrans `/back-office/groups` n'ont pas été lus en détail, 01 F16) | `groups` | Espace d'unité si c'est un élément d'organigramme ; sinon Membres › Listes de diffusion (à arbitrer après lecture) | Unité, liste de diffusion e-mail |
| Équipe | Sous-ensemble d'une unité (binôme, équipe de feu permanente) | `teams` ; `fire_teams` pour les équipes en jeu | Espace d'unité › Composition (équipe permanente) ; Dossier d'opération › Exécution (équipe en jeu) | Groupe opérationnel |
| Groupe opérationnel | Organisation temporaire pour une opération (task organisation, éléments, postes de mission) | `mission_to_elements`, `mission_to_slots`, `operation_elements`, `atak_plan_elements` | Dossier d'opération › Organisation et postes | Unité |
| Ressource | Mot réservé aux bibliothèques de documents et de fichiers. N'est plus un nom de rubrique d'administration. | préfixe d'URL `ressources/` (sans lien avec le menu, 03 §2.2) | Communauté › Documents | Équipement, dotation |
| Équipement | Objets du jeu, tenues, collections, fiches matériel, kits | `game_item_*`, `arsenal_*`, `game_role_kits` | Équipement et dotation | Dotation |
| Dotation | Attribution nominative et réglementaire d'articles à un membre, avec historique ; dotation type par emploi | `equipment_item_definitions`, `personnel_equipment_assignments` | Équipement et dotation › Dotation ; Fiche membre › Équipement et dotation | Kit, tenue |
| Kit | Ensemble prêt à porter lié à un emploi (kit par emploi). Le « kit obligatoire ATAK » est une liste conseillée lue par le téléphone ; les « kits d'accès » sont des lots de droits ; les « kits de papiers » sont des lots de documents SSE. | `game_role_kits` ; `community_kit` ; `tenant_function_kit_state` ; `sse_id_kits` | Kit par emploi : Équipement › Kits ; kit ATAK : Configuration ATAK › Consignes et kit ; lots de droits : Droits et accès ; kits de papiers : portail SSE | Les trois autres « kits » (renommer : « Kit par emploi », « Liste de matériel conseillé », « Lot de droits », « Kit de couverture ») |
| Mission | Mot à réserver à l'opération elle-même. Les autres sens reçoivent un nom propre. | `mission_plans` (plan), `interteam_missions` (coopération), `planning_entries.linked_type='mission'`, `operations`, `theatre_mission_cycles` (cycle) | Dossier d'opération | « Coopération », « Cycle de session », « Fiche du mur » |

## 3. Arborescence actuelle (résumé)

Source : audit 05 §1.3 et §1.4 (`views/partials/ath_sidebar_nav.php:421-749`).

### 3.1 Menu de gestion (gestionnaire, assistance, administrateur plateforme)

| Groupe | Entrées | Liens | Problèmes principaux (FAIT) |
|---|---|---|---|
| Tableau de bord et suivi | Tableau de bord, Tableau opérationnel, Indicateurs | 9 | Métriques joueurs et escouades rangées sous « Indicateurs » |
| Membres et suivi RH | Effectifs (12), Recrutement (7), Dossiers RH (9) | 28 | Registre et Circuits sous « Effectifs » ; Élévations sous « Dossiers RH » ; réglages du recrutement absents |
| Unités et référentiels | Organisation (10), Référentiels (7) | 17 | Organigramme hors shell ; deux pages « Grades » ; Dotation et Espace aérien rangés en référentiels |
| Droits et permissions | Rôles et droits (5), Sécurité, Journal | 7 | Deux entrées pour une page ; « Fonctions et emplois » et « Fichiers SSE » ne sont pas des droits |
| Opérations | Événements (3), Missions (3), Carte tactique, Comptes rendus (3), Coopérations (4), Extranet (10) | 24 | Planification sans entrée ; TOC et `/operations` absents |
| Roleplay | Suivi roleplay (3), Mode roleplay ATAK | 4 | Mode roleplay ATAK séparé des autres réglages réseau |
| ATAK · carte et terminaux | Overwatch, Poste, Contrôle, Supervision (4), Terminaux (6), Matériel (4), Configuration et mods (6) | 23 | Matériel et dotation séparés ; trois écrans pour le réseau |
| Formations et documents | Formations, Doctrine et documents (4) | 5 | — |
| Communauté | Vitrine et portail (6), Communication (6), Modération (2), Musique, Configuration initiale | 16 | Modération coupée en deux ; « Configuration initiale » loin de « Configuration » |
| Réglages et intégrations | 6 entrées | 6 | Trois « Configuration » dans trois groupes |
| **Total** | 39 entrées de premier niveau | **137** | 4 niveaux, tout ouvert par défaut |

### 3.2 Menu opérateur (membre et tout cadre délégué)

6 groupes, 22 liens au plus : Tableau de bord ; Ma situation (13) ; Terrain (3) ; Administratif (2) ; Unités et référentiels (Coopérations, Extranet) ; Réglages (Paramètres). Aucune page de gestion, quels que soient les droits fins (02 F23, 05 §1.4).

### 3.3 Barre plateforme

9 rubriques, 50 pages, à droite de l'écran (05 §1.5). Hors du périmètre de la refonte, sauf alignement (§9).

## 4. Arborescence cible

### 4.1 Vue d'ensemble

| Niveau | Groupe | Entrées | Pour qui (HYPOTHÈSE d'usage) |
|---|---|---|---|
| 1. Au quotidien | Accueil | À traiter, Prochaine opération, Mon service | Tous les gestionnaires et cadres |
| 1 | Membres | Liste des membres, Ajouter un membre, Demandes RH ; **Fiche membre** (entité) | RH, gestionnaire, chefs |
| 1 | Unités | Organigramme, Affectations ; **Espace d'unité** (entité) | Gestionnaire, chefs d'unité |
| 1 | Opérations | Agenda, Dossiers d'opération, Mur opérationnel, Situation tactique ; **Dossier d'opération** (entité) | Officiers des opérations |
| 1 | Mon espace | Ma situation, Mes démarches, Boîte de réception, Mon service | Tout membre |
| 2. Gestion approfondie | Ressources humaines | Recrutement, Carrière, Qualifications des membres, Documents RH, Départs, Discipline, Qualité des fiches, Tableaux et exports | RH, recruteurs, modérateurs |
| 2 | Roleplay | Bureau de suivi, Échéances, Sessions Arma | Responsables RP |
| 2 | Après-action et renseignement | Comptes rendus, CR des joueurs, Remontées, Renseignement (portail SSE, fichiers SSE) | État-major, cellule renseignement |
| 2 | Coopérations | Coopérations inter-unités, Extranet d'unité | État-major |
| 2 | Terrain ATAK | Supervision, Appareils et liaisons, Équipes en jeu, Aviation | Responsables ATAK |
| 2 | Équipement et dotation | Matériel, Tenues, Kits par emploi, Dotation, Porté en jeu | Logisticiens |
| 2 | Communauté | Publier, Documents et doctrine, Formations, Médias et musique, Indicateurs | Communication, formation |
| 3. Configuration avancée (replié) | Configuration | Organisation, Référentiels, Droits et accès, ATAK, Intégrations, Réglages RH | Gestionnaire, responsables désignés |

Volumétrie cible (PROPOSITION) : 13 groupes, 55 entrées sous les groupes (dont 6 dans Configuration, qui ouvrent chacune une page à sections), 3 niveaux au plus (groupe, entrée, page ou section), contre 10 groupes, 137 liens et 4 niveaux aujourd'hui. Les pages ne disparaissent pas : elles deviennent des onglets d'une entité ou d'une rubrique (voir document 6).

### 4.2 Arborescence détaillée

Notation : `[N1]`, `[N2]`, `[N3]` = niveau d'usage. Entre crochets droits, les identifiants de l'inventaire regroupés à cet endroit.

```
ACCUEIL [N1]                                      /back-office
├── À traiter (file unique, filtrée par périmètre)       [PER-F26, ORG-K1 À décider + Tâches, ACC-F17 avis]
├── Prochaine opération                                   [ORG-K1 bloc operation]
└── Mon service                                           [ACC-F17] (déjà engagé : PR #1600, #1601, #1602)

MEMBRES [N1]
├── Liste des membres                                     [PER-F01, PER-F04, PER-F05, PER-F07]
├── Ajouter un membre                                     [PER-F03, PER-F06, PER-F08 créer]
├── Demandes RH (élévations, corrections, mobilité, absences, avis)  [PER-F33, PER-F35, PER-F28 mobilité, PER-F36 absences]
└── Fiche membre (entité, hors menu, onglets selon les droits)        [PER-F02 et voir §5]

UNITÉS [N1]
├── Organigramme (dans le shell)                          [PER-F14, PER-F16, PER-H5]
├── Affectations (matrice, mouvements, codes d'unité)     [PER-F13]
└── Espace d'unité (entité, hors menu, onglets)          [voir §6]

OPÉRATIONS [N1]
├── Agenda (calendrier, inscriptions, bilan)              [O-03, O-04, O-05]
├── Dossiers d'opération (liste)                          [O-01, O-02, O-11]
├── Mur opérationnel                                      [O-13]
├── Situation tactique ↗ (Overwatch, Poste de situation, Poste TOC, 9-LINE)  [T-01, A-01, T-06, T-10, A-02]
└── Dossier d'opération (entité, hors menu, onglets)     [voir §7]

MON ESPACE [N1] (tout membre)
├── Ma situation (fiche, unité, qualifications, carnet, avancement, carrière, décorations, dotation, suivi, coffre, papiers, musique)  [NAV-17 à NAV-22, E-07, I-04]
├── Ma liaison ATAK et mes appareils                      [T-03, T-04]
├── Mes démarches                                         [NAV-23]
├── Boîte de réception                                    [ORG-C5b]
└── Mon service                                           [ACC-F17]

RESSOURCES HUMAINES [N2]
├── Recrutement : Candidatures, Offres, Invitations et codes, Intégration des recrues, Statistiques, Réglages du recrutement
│                                                         [PER-F21, PER-F22, PER-F25, PER-F09, PER-F24, NAV-16]
├── Carrière : Campagnes d'avancement, Ancienneté, Relève et succession  [PER-F39, PER-F41, PER-F28 vivier]
├── Qualifications des membres (matrice)                  [PER-F32]
├── Documents RH                                          [PER-F27]
├── Départs et anciens membres                            [PER-F12]
├── Discipline : Sanctions et absences, Signalements, Fichiers en quarantaine  [PER-F36, ORG-J1, ORG-J2]
├── Qualité des fiches : Doublons, Personnes sans compte, Dossiers à compléter, Qualité des données  [PER-F11, PER-F08, ORG-K1 gaps, NAV-01]
└── Tableaux et exports                                   [PER-F10]

ROLEPLAY [N2]
├── Bureau de suivi (dossiers, immersion, phases)         [NAV-08]
├── Échéances                                             [NAV-09]
└── Sessions Arma                                         [NAV-10]

APRÈS-ACTION ET RENSEIGNEMENT [N2]
├── Comptes rendus (AAR, synthèses, suivi des actions)    [O-08]
├── CR des joueurs                                        [O-09]
├── Remontées des joueurs                                 [R-04, NAV-30]
└── Renseignement ↗ : Portail SSE, Fichiers SSE (administration)  [R-05a à R-05r, R-03, R-02]

COOPÉRATIONS [N2]
├── Coopérations inter-unités                             [O-14]
└── Extranet d'unité ↗                                    [J-01 à J-10]

TERRAIN ATAK [N2]
├── Supervision : Contrôle de mission (état en jeu), Opérateurs connectés, Réseau de relais, Files d'envoi, Data Inspector  [A-03, A-08, A-04, A-12a, A-12b]
├── Appareils et liaisons : Parc (terminaux et membres), Certificats, Appairages téléphone  [A-06 parc, A-09, A-07, T-09]
├── Équipes en jeu : Escouades, Équipes de feu            [A-10]
└── Aviation : Espace aérien, Appareils et équipages 9-LINE  [A-17, T-10 appareils]

ÉQUIPEMENT ET DOTATION [N2]
├── Matériel (catalogue et fiches)                        [E-01 matériel, E-S1]
├── Tenues et collections ↗                               [E-01, E-02, E-03]
├── Kits par emploi (éditeur unique)                      [E-05, E-01 kits]
├── Dotation (articles, dotation type, attributions)      [E-06]
├── Porté en jeu                                          [E-04]
└── Tenues mises en avant                                 [E-08]

COMMUNAUTÉ [N2]
├── Publier : Annonces et bandeaux, Mini-articles, E-mails (envoi, historique, listes, modèles)  [ORG-C1, ORG-C2, ORG-C3, ORG-C4]
├── Documents et doctrine : Doctrine et SOP, Référentiel doctrinal, Gestion documentaire, Fichiers partagés  [ORG-E5, ORG-E1, ORG-E4, NAV-15]
├── Formations                                            [ORG-E6, NAV-14]
├── Médias et musique                                     [ORG-B5, ORG-B11]
└── Indicateurs : Usage, Participation, Métriques joueurs  [ORG-B8, O-04 lien, A-11]

CONFIGURATION [N3] (replié par défaut)
├── Organisation
│   ├── Identité et image (nom, adresse, logo, couleurs, bannière, favicon)   [ORG-B1 identité + portail]
│   ├── Page publique (contenu, éditeur en place, visibilité, Annoncer sur Discord)  [ORG-B2, ORG-B3, ORG-B1 vitrine, ORG-B4]
│   ├── Inscription et contact (formulaire unique)       [ORG-B1 inscription, ORG-A4 champs, ORG-B2 contact_*]
│   ├── Portail des membres (raccourcis, catégories, accueil de connexion)  [ORG-B6, NAV-11, ORG-B1 accueil]
│   ├── Profil de communauté et formule                  [ORG-B1 profil, ORG-F1]
│   ├── Démarrage (checklist unique)                     [ORG-A4, ORG-A5]
│   ├── Mises à jour et nouveautés                       [ORG-A6]
│   ├── Messages de la plateforme                        [ORG-B10]
│   └── Export pour contrôle, Traçabilité du courrier    [NAV-12, ORG-C6]
├── Référentiels
│   ├── Grades (système, grades de la communauté, ordre et filières, contrôle OTAN, critères d'élévation)  [PER-F37, PER-F38, PER-F39 grades, PER-F33 critères]
│   ├── Emplois (catalogue, catégories, prérequis, dérogations, historique)  [PER-F17, PER-F18, PER-F19]
│   ├── Qualifications et compétences (référentiel, émetteurs, cursus pilote, compétences)  [NAV-07, NAV-05, NAV-06]
│   ├── Décorations                                       [PER-F31]
│   ├── Positions administratives                         [PER-F45 référentiel] (déjà engagé : PR #1600, #1601, #1602)
│   ├── Organisation type (types d'entité, catalogue, adéquation par échelon)  [NAV-03, NAV-02, PER-F15 adéquation]
│   ├── Identification (matricules, indicatifs, noms et trigrammes, PASS RH)  [PER-F42]
│   └── Disponibilité                                     [NAV-04]
├── Droits et accès
│   ├── Niveaux d'accès                                   [PER-F43, ACC-F09, ACC-F10]
│   ├── Registre des droits                               [PER-F44]
│   ├── Lots de droits (ex-kits d'accès)                  [PER-F18 kits]
│   ├── Habilitations (renseignement, émetteurs)          [R-01, ACC-F22]
│   ├── Circuits de validation                            [PER-F34]
│   ├── Sécurité et blocages                              [ACC-F15]
│   └── Journal d'audit                                   [ORG-B9]
├── ATAK (page unique à sections, voir §8)                [A-14, A-03 règles, A-05, A-06 réglages, A-07 réseaux, A-13, A-15, A-16a-d, API-8 à API-11]
├── Intégrations : Discord, E-mail, Clés d'API, Relais SSE  [ORG-G1, ORG-D1 bot, ORG-D2 destination par défaut]
└── Réglages RH : réglages, avancement automatique, cycle des recrues  [PER-F30, ORG-B1 profil cycle]
```

`↗` = l'entrée ouvre une autre application (portail SSE, extranet, Overwatch, Arsenal) ; une icône le signale (P8).

### 4.3 Justification des grands choix

| Choix | Justification | Alternative écartée |
|---|---|---|
| Membres, Unités, Opérations en niveau 1 | Ce sont les trois entités que les besoins quotidiens citent (04 §2.4 : décisions, prochaine activité, dossiers incomplets, présences). HYPOTHÈSE d'usage. | Garder « Effectifs » comme bureau à 20 onglets : il mélange quotidien et configuration (shell.php:16-37). |
| Demandes RH en niveau 1 | Une file unique remplace 4 files (RD-13) et alimente « À traiter ». | Garder élévations, corrections, mobilité séparées : un responsable doit ouvrir 3 pages. |
| Recrutement en niveau 2 | Il ne concerne pas tous les profils et il est masqué en profils Effectifs et ATAK (`TenantTypeConfig.php:477-513`). Sa file entre quand même dans « À traiter ». | Niveau 1 : alourdit le menu pour les communautés qui ne recrutent pas en continu. |
| Roleplay comme groupe propre | Profondeur RP voulue (P10) ; le mode roleplay ATAK part en Configuration ATAK parce qu'il règle les mêmes paramètres réseau que le réalisme (RD-31). | Fondre le suivi RP dans RH : on perd la lisibilité du travail RP. |
| Configuration regroupe tout le niveau 3 | Cinq entrées « Configuration » ou « Paramètres » dans trois groupes aujourd'hui (RD-41). | Un groupe par domaine : recrée la dispersion. |
| Référentiels en configuration | Grades, emplois, qualifications, décorations, positions se définissent rarement ; leur **attribution** se fait sur la fiche membre. | Garder « Référentiels » en niveau 1 : mélange définition et attribution (01 F31 : décorations attribuées seulement depuis le référentiel). |
| Emplois sortent de « Droits et permissions » | Un emploi ne donne aucun droit (`config/back_office_pages.php:189`). | — |
| Fichiers SSE sortent de « Droits et permissions » | C'est du renseignement (03 R-03). | — |
| Dotation sort des référentiels | Elle va avec le matériel et les kits (RD-33) ; le profil ATAK la retrouve. | — |
| Escouades sortent des indicateurs | Ce sont des équipes en jeu, pas des statistiques (05 §4.1). | — |
| Carte tactique dans Situation tactique | C'est une surface de dessin ATAK (03 A-02), pas une page d'opérations. | — |
| `/operations` et TOC visibles | Ils existent mais n'ont aucune entrée dans le menu du back-office (03 §2.2). Le dossier d'opération y donne accès. | — |

## 5. Fiche membre centrale

Une seule page, `EFF/membres/{id}` (adresse gardée), avec des onglets. Chaque onglet n'apparaît qu'avec le droit qui permet au moins de le lire. Les actions d'écriture n'apparaissent qu'avec le droit d'écrire. Le contenu vient des cartes actuelles (`views/admin/effectifs_workspace/member.php`) et des pages externes listées.

| Onglet | Contenu (origine) | Lecture | Écriture | Concepts |
|---|---|---|---|---|
| Synthèse | Portrait, unité, poste tenu, grade, emplois, position, statut du compte, complétude, « à traiter » pour ce membre, raccourcis (`member.php:394-458, 1346-1420`) | Accès effectifs (`ELA::allows`) | — | — |
| Identité et papiers | État civil RP, langues, invitations (`member.php:472-583`) ; papiers, alias, couverture (PER-F29, I-05) | Accès effectifs ; papiers : habilitation SSE | `canEditProfiles` ; papiers : `sseManage` | Identité RP ≠ identité civile |
| Affectation et poste | Unité, **poste tenu** (poste ORBAT + intitulé), emplois et prérequis, chaîne de commandement, adéquation (`member.php:587-799` ; PER-F15 ; PER-F14 occupation) | Accès effectifs | `canManageAssignments` ; poste ORBAT : `organization.orbat.manage` | Unité, fonction, emploi distincts |
| Carrière | Grade et **frise unique** (fiche, élévations, campagnes), ancienneté, mobilité, vivier, élévations du membre (`member.php:801-1056`) | Accès effectifs | Grade : `canManageGrades` ; ancienneté : `canEditProfiles` | Grade |
| Qualifications et distinctions | Toutes les qualifications avec leur origine (matrice, attribution, cursus, académie), brevets, **décerner une décoration** | Accès effectifs | Droits des qualifications ; décorations : `personnel.awards.manage` | Qualification ≠ habilitation |
| Accès et habilitations | Niveaux détenus **avec dates et périmètre** (comme au registre), droits individuels, habilitations SSE, **droits effectifs calculés** (union) | `canManageRoles`, `admin.access` ; lecture RH | `canManageRoles` ; habilitations : `atak.sse.grant` | Niveau d'accès, permission, habilitation |
| Position et statut | Position administrative, passage en service actif, statut du compte, départ (`member.php:1057-1139`) | Accès effectifs | `canManageStatus` | Position ≠ statut du compte |
| Suivi RH | Présence, absences, sanctions, documents RH du membre, notes (`member.php:1141-1311` ; PER-F27 filtré) | Accès effectifs | Sanctions : `canManageSanctions` ; documents : `canEditProfiles` | — |
| Équipement et dotation | Dotation nominative, kit de son emploi, porté en jeu (E-06, E-04 filtrés) | `personnel.equipment.manage` ou accès effectifs | `canManageDotation` | Dotation ≠ kit |
| Terrain ATAK | Terminaux, liaison, certificat, temps de jeu, carnet de vol (A-08 fiche opérateur, A-09 fiche, T-04) | ATAK `view` | ATAK `manage` (blocage, message, révocation) | — |
| Roleplay | Suivi RP, immersion, phases, bilans de stage, tuteur (NAV-08 filtré ; `personnel_service_history`) | Droits du suivi RP | idem | — |
| Historique | **Chronologie unique** filtrable : organisation, service, grades (deux historiques), droits (`role_assignments_log`), affectations, papiers, intégration, décorations, départs, journal d'audit | Accès effectifs | — | — |

Justification : la fiche est déjà annoncée comme « point d'entrée RH unique » (EWC 1474-1475) mais c'est une page longue à ancres sans vrais onglets (`member.php:461-468`), qui renvoie vers 6 autres pages pour les documents, la mobilité, les qualifications, les décorations, la fiche front et l'ORBAT (01 F02). Le grade manque au tableur, le rôle dans l'unité manque à la fiche (01 P2) ; l'historique est réparti entre au moins 6 sources (01 P4).

## 6. Espace d'unité

Une page par unité, construite sur la fiche d'unité existante (`BO/organisation/unites/{id}`, `UnitSheetController`), dans le shell.

| Onglet | Contenu (origine) | Droits |
|---|---|---|
| Vue d'ensemble | Chef, échelon, effectif, disponibilité (score de l'accueil, `PilotageDashboardService::unitReadiness`), chefs manquants (PER-F15) | `organization.orbat.view` |
| Membres | Liste des membres de l'unité et sous-unités (PER-F01 filtré `unite`) | Accès effectifs |
| Postes | Postes ORBAT, emploi attendu, titulaire, vacances (PER-F14 `/postes`) | Écriture `organization.orbat.manage` |
| Chaîne de commandement | Chef et adjoints de l'unité (PER-F15 filtré) | `canManageAssignments` |
| Équipes | Équipes permanentes de l'unité (PER-F16) | `organization.orbat.manage` |
| Qualifications | Matrice filtrée sur l'unité (PER-F32), adéquation | Accès effectifs |
| Dotation type | Kits et dotation attendus par emploi de l'unité (E-05, E-06) | `personnel.equipment.manage` |
| Opérations | Dossiers d'opération où l'unité est engagée | `operations.*` |
| Extranet ↗ | Lien vers l'espace JNET de l'unité et sa personnalisation (J-02) | `canCustomize` actuel |
| Identification | Code d'unité, indicatifs (PER-F13 codes, PER-F42) | `admin.*` pour le code communauté |

Concepts gardés distincts : l'espace concerne une **unité** permanente. Les **groupes opérationnels** d'une opération restent dans le dossier d'opération. Les équipes de feu **en jeu** (A-10) restent dans Terrain ATAK et dans l'onglet Exécution du dossier.

## 7. Dossier d'opération

Une page par opération qui relie les outils existants sans les réécrire. L'identifiant commun est le plan de mission (`mission_plans.id`), parce que c'est le seul objet qui se rattache déjà à un événement, à une carte, à un ORBAT et à un AAR (03 O-02). Les colonnes prévues mais jamais remplies sont utilisées : `operations.mission_plan_id`, `mission_plans.cycle_id` (03 O-02, O-11).

| Onglet | Contenu (origine) | Droits |
|---|---|---|
| Synthèse | En-tête unique : nom, code, task force, DTG, carte, statut (brouillon, publié, live, clos) ; indicateurs du portail missions (O-01) | ORG actuel ; cible : `operations.missions.manage` |
| Créneau et inscriptions | Événement lié, inscriptions, réponses nominatives, présences, destination Discord (O-03, O-05) | module `events` |
| Organisation et postes | Groupes opérationnels, postes, affectations, rapprochement prévu / détecté, import ORBAT et inscrits (O-02 `?vue=organisation`) | `operations.planning.edit` |
| Briefing | Diapositives (O-12), documents (O-02), ordres et phases (O-11), consignes et liste de matériel conseillé (A-15), kits par emploi (E-05) | selon l'outil |
| Conditions de jeu | Résumé en lecture des réglages ATAK appliqués (A-03) et lien vers Configuration ATAK | lecture : ATAK `view` |
| Exécution | Cycle de mission et parties jouées (O-07), sessions Arma (NAV-10), liens Overwatch, TOC, 9-LINE ; équipes en jeu (A-10) | ATAK `manage` pour le cycle |
| Après-action | CR des joueurs (O-09), AAR et synthèse (O-08), REX de coopération (O-14) | ATAK `manage` |
| Renseignement | Remontées rattachées (R-04) ; dossiers SSE liés, visibles selon l'habilitation | habilitation SSE |
| Publication | Fiche du mur (O-13) en lien vivant ; vue JNET (J-03) | `operational.board.edit` |

Justification : le parcours actuel traverse au moins 6 familles de menu, fait 3 copies d'instantanés et 2 à 3 ressaisies ; le seul lien plan ↔ cycle ↔ événement passe par le rattachement manuel des parties jouées (03 P1). Les trois éditeurs de plan (O-02, O-11, T-06) restent des outils distincts dans un premier temps ; le dossier les relie (lot 3 du plan).

## 8. Configuration ATAK unifiée

Une page `BO/ressources/atak-config` (adresse gardée) à sections, avec une navigation latérale interne. Chaque réglage a **une** source de vérité. Toutes les sections exigent ATAK `manage` (`guardAtak`), alors qu'aujourd'hui A-14 et A-05 ne l'exigent pas (03 §1.B).

| Section | Contenu (origine) | Source de vérité retenue (PROPOSITION) |
|---|---|---|
| Démarrage | Checklist de mise en service | — |
| Connexion et serveur | Clé d'accès, serveur Arma, identifiants du mod, lien avancé, maintenance, liaison obligatoire à la première connexion, version minimale du mod, logo (A-14) | `tenant_atak_config` ; `atak_defaults.require_game_link_*` déplacé dans `tenant_atak_config` (lot 3) |
| Expérience et règles en jeu | Expérience de mission, modules du pont, applications, flux de données (A-14 et A-03) | `experience_config` ; modules et flux sortis du cache fichier vers la base (lot 3, 03 N7) |
| Réalisme et réseau | Simulation réseau, relais, zones, coupures, capteurs, chiffrement du renseignement, versions et restauration (A-06 réglages, A-05, A-03 `reseau-adopter`) | `atak_realism_config` versionnée ; colonnes `roleplay_*` lues en repli puis migrées (lot 3, 03 N6) |
| Chiffrement et certificats | Réseaux de chiffrement (A-07) ; la liste des certificats reste dans Terrain › Appareils | `atak_crypto_domains` |
| Cartes et terrain | Carte par défaut, catalogue des cartes, cartes personnalisées, tuiles disponibles, relief, passerelles (A-14, API-8 à API-11, T-07) | `atak_maps`, `tenant_custom_maps` |
| Symboles et marqueurs | Icônes, apparence des symboles, détection des marqueurs (A-14 `marker-icons`, A-06 `symbology_map`, A-13) | `marker_icons_config` ; `symbology_map` fusionné après lecture du mod |
| Mods et serveurs | Mods Steam Workshop, restrictions d'accès, serveurs de jeu (A-16a à c) | inchangée |
| Diffusion des rapports | Règles de routage (A-16d) | inchangée |
| Consignes et matériel conseillé | Consignes, ordres, astuces, liste de matériel conseillé (A-15) | `community_notes`, `community_kit` |
| Bandeau photo terrain | (A-14 `photo-hud`) | inchangée |
| Données | Export, purge (phrase « EFFACER »), déliaison Steam (A-14) | inclure modules et flux dans l'export |

Le « Contrôle de mission » (A-03) reste visible dans Terrain ATAK › Supervision comme **tableau de bord en lecture** de l'état du serveur et de l'effet en jeu ; ses boutons d'édition renvoient vers la section correspondante.

## 9. Menus par profil

### 9.1 Règle de construction

| Étape | Règle (PROPOSITION) | Remplace (FAIT) |
|---|---|---|
| 1 | Chaque page déclare son droit de lecture dans un registre unique (`config/back_office_pages.php` enrichi, ou équivalent) | Exceptions du middleware et règles de menu séparées (RD-37) |
| 2 | Le menu affiche toute page dont l'utilisateur détient le droit de lecture | Aiguillage sur `admin.organization|admin.access|site.support` (`back_office_sidebar.php:13-15`) |
| 3 | Un utilisateur qui a au moins un droit de gestion voit « Mon périmètre » au-dessus de « Mon espace » | Menu opérateur pour tout cadre délégué |
| 4 | Le choix d'espace TBA est proposé à tout porteur d'un droit de gestion | TBA réservé aux trois droits globaux (`PortalAccessChoice.php:21-28`) |
| 5 | Le profil de communauté et la formule masquent toujours les modules concernés | inchangé (`TenantTypeConfig`, `FeatureGateService`) |

### 9.2 Menu « cadre » (profils délégués)

Le menu cadre n'est pas une seconde arborescence : c'est l'arborescence cible filtrée par les droits du cadre, présentée sous un groupe « Mon périmètre ». Exemples, établis à partir des droits des profils décrits par l'audit 02 (§2.3) :

| Profil | Droits types (FAIT, 02 §2.3) | Mon périmètre (PROPOSITION) |
|---|---|---|
| RH | `organization.effectifs.hub.view`, `personnel.*.manage`, `admin.members.*`, recrutement | Accueil (À traiter RH), Membres (liste, ajouter, demandes RH, fiche sans l'onglet Accès en écriture), Unités › Affectations, Ressources humaines (tout), Roleplay si droit |
| Recruteur | `organization.recruitment.manage` | Accueil (candidatures), Ressources humaines › Recrutement, Configuration › Droits et accès › Sécurité et blocages (lecture) |
| Responsable des accès | `admin.roles.manage` | Membres (liste, fiche onglet Accès), Configuration › Droits et accès (niveaux, registre, circuits, lots de droits) |
| Auditeur | `admin.audit.view` | Configuration › Droits et accès › Journal d'audit |
| Modérateur | `admin.members.moderate` | Ressources humaines › Discipline |
| Communication | `comms.*`, `media.*` | Communauté › Publier, Médias |
| Chef d'unité | Membre + place dans la chaîne (`ApproverResolver.php:28-89`) | Mon service (avis), Espace de **son** unité en lecture, Demandes RH de ses subordonnés (avis) |
| Officier des opérations | `operations.missions.manage`, `operational.board.edit`, `atak.command.alert` | Opérations (tout), Après-action, Communauté › Publier (canaux ATAK) |
| Cellule renseignement | `atak.sse.case.manage`, `atak.sse.grant` | Après-action et renseignement › Remontées, Renseignement ↗, Configuration › Droits et accès › Habilitations (si `grant`) |
| Logisticien | `personnel.equipment.manage` | Équipement et dotation, onglet Équipement de la fiche |

Les droits « chef d'unité », « officier des opérations » et « logisticien » existent déjà en partie dans le code (`atak.toc.manage`, `operations.planning.edit`, `personnel.equipment.manage`, 03 O-11, T-06, E-01). La création de profils prêts à l'emploi correspondants est une PROPOSITION du lot 5.

### 9.3 Menu opérateur (membre)

| Groupe | Entrées | Changement |
|---|---|---|
| Accueil | Tableau de bord personnel | Libellé « Accueil » |
| Mon espace | Ma situation (13 pages en onglets d'une même page), Ma liaison ATAK, Mes démarches, Boîte de réception, Mon service | Mon service ajouté (déjà engagé) ; « Ma situation » devient une page à onglets |
| Terrain | Overwatch ↗, Événements | Overwatch signalé comme sortie |
| Mon unité | Mon unité, Extranet ↗, Coopérations (si droit) | Remplace le groupe mal nommé « Unités et référentiels » (05 §4.3 n°15) |
| Compte | Paramètres ↗ | — |

## 10. Table des déplacements, regroupements et maintiens

Chaque entrée du menu actuel (05 §1.3) avec sa destination. « Maintenu » = même place logique, même page. « Déplacé » = même page, autre groupe. « Regroupé » = devient un onglet ou une section d'une autre page, l'adresse répond en 301 ou garde son onglet.

| Entrée actuelle | Destination cible | Décision | Justification |
|---|---|---|---|
| Tableau de bord | Accueil | Maintenu, contenu réduit | Blocs centrés sur « à traiter » (04 §2.3) ; déjà engagé (PR #1600, #1601, #1602) |
| Tableau opérationnel | Opérations › Mur opérationnel | Déplacé | C'est un outil d'opération, pas un indicateur |
| Indicateurs d'usage, Conversion | Communauté › Indicateurs | Déplacé, fusion des deux routes | Une méthode pour deux routes (04 B8) |
| Métriques joueurs, Heures de jeu | Communauté › Indicateurs | Regroupé | Statistiques |
| Escouades en jeu | Terrain ATAK › Équipes en jeu | Déplacé | Ce sont des équipes en jeu (05 §4.1) |
| Qualité des données | Ressources humaines › Qualité des fiches | Regroupé | Avec doublons et sans compte |
| Effectifs › Liste des membres | Membres › Liste des membres | Maintenu, renommé partout | Un seul libellé (RD-40) |
| Effectifs › Affectations | Unités › Affectations | Déplacé | Objet = unité |
| Effectifs › Chaîne de commandement | Espace d'unité › Chaîne ; vue globale dans Unités › Organigramme | Regroupé | Objet = unité ; chef désigné à un seul endroit (RD-22) |
| Effectifs › Qualifications des membres | Ressources humaines › Qualifications des membres ; filtrée dans l'espace d'unité | Déplacé | Gestion approfondie |
| Effectifs › Sanctions & absences | Ressources humaines › Discipline (sanctions) ; absences dans Demandes RH | Regroupé | Modération coupée en deux aujourd'hui (05 §4.3 n°13) |
| Effectifs › Départs | Ressources humaines › Départs | Déplacé | Visible seulement avec `canManageStatus` (01 F12) |
| Effectifs › Comptes en double, Personnes sans compte | Ressources humaines › Qualité des fiches | Regroupé | Doublon et rattachement ensemble (RD-58) |
| Effectifs › Tableaux de données | Ressources humaines › Tableaux et exports | Déplacé | Gestion approfondie |
| Effectifs › Registre des droits | Configuration › Droits et accès › Registre | Déplacé | Objet = droits |
| Effectifs › Circuits de validation | Configuration › Droits et accès › Circuits | Déplacé | Paramétrage rare (02 F04) |
| Effectifs › Invitations | Ressources humaines › Recrutement › Invitations et codes (onglet « Compléter un profil ») | Regroupé | Deux pages « Invitations » (RD-26) |
| Recrutement › Candidatures, Vue d'ensemble, Offres, Statistiques, Codes, Invitations | Ressources humaines › Recrutement | Maintenu dans un sous-groupe | — |
| Recrutement › Intégration des recrues | Ressources humaines › Recrutement › Intégration | Maintenu | Parcours d'arrivée ; ses étapes RP renvoient au Roleplay (RD-55) |
| (hors menu) Réglages du recrutement | Ressources humaines › Recrutement › Réglages | Ajouté au menu | Absents du bloc aujourd'hui (05 §4.3 n°5) |
| Dossiers RH › À traiter et alertes | Accueil › À traiter | Regroupé | File unique (RD-14) |
| Dossiers RH › Corrections, Demandes d'accès élevé | Membres › Demandes RH | Regroupé | File unique (RD-13) ; « accès élevé » ne dit pas qu'il s'agit aussi de grade et d'unité (01 F33) |
| Dossiers RH › Documents RH | Ressources humaines › Documents RH ; filtré dans la fiche | Maintenu | — |
| Dossiers RH › Avancement | Ressources humaines › Carrière | Maintenu | — |
| Dossiers RH › Ancienneté | Ressources humaines › Carrière (règles) | Maintenu | Date de création réglée ici seulement (RD-53) |
| Dossiers RH › Mobilité | Membres › Demandes RH (type mobilité) | Regroupé | File unique |
| Dossiers RH › Relève et succession | Ressources humaines › Carrière | Maintenu | — |
| Dossiers RH › Réglages RH | Configuration › Réglages RH | Déplacé | Paramétrage |
| Organisation › Organigramme | Unités › Organigramme (dans le shell) | Maintenu, rendu dans le shell | Rupture de contexte (05 §4.3 n°1) |
| Organisation › Groupes, Équipes | Espace d'unité › Équipes ; création déjà dans l'ORBAT | Regroupé | Chevauchement avec l'ORBAT (01 F16) ; concepts gardés (§2) |
| Organisation › Catalogue, Types d'entité | Configuration › Référentiels › Organisation type | Déplacé | Paramétrage |
| Organisation › Indicatifs, Matricules, Noms, PASS RH | Configuration › Référentiels › Identification ; valeurs individuelles sur la fiche | Déplacé | Règles rares, valeurs sur l'entité |
| Organisation › Disponibilité | Configuration › Référentiels › Disponibilité | Déplacé | Paramétrage |
| Référentiels › Grades | Configuration › Référentiels › Grades (avec les grades d'avancement, le catalogue OTAN et les critères) | Regroupé | Deux pages « Grades », trois contrôleurs (01 P3) |
| Référentiels › Décorations | Configuration › Référentiels › Décorations ; « Décerner » sur la fiche | Déplacé | Attribution sur l'entité (RD-54) |
| Référentiels › Qualifications, Compétences, Cursus pilote | Configuration › Référentiels › Qualifications et compétences | Regroupé | — |
| Référentiels › Espace aérien | Terrain ATAK › Aviation | Déplacé | Vue opérationnelle de l'aviation ; avec les appareils 9-LINE (03 T-10) |
| Référentiels › Dotation | Équipement et dotation › Dotation | Déplacé | Avec le matériel (RD-33) ; visible en profil ATAK après revue du module (lot 5) |
| Rôles et droits › Rôles | Configuration › Droits et accès › Niveaux d'accès | Déplacé, renommé | Un seul nom (« Rôles » / « Accès » aujourd'hui) |
| Rôles et droits › Droits par membre | Fiche membre › Accès et habilitations | Retiré du menu | Redirige vers « Rôles » (02 F02) |
| Rôles et droits › Fonctions et emplois | Configuration › Référentiels › Emplois | Déplacé | Ne donne aucun droit |
| Rôles et droits › Accès au renseignement | Configuration › Droits et accès › Habilitations | Déplacé | Habilitation, à côté des droits |
| Rôles et droits › Fichiers SSE | Après-action et renseignement › Renseignement | Déplacé | Renseignement |
| Sécurité et blocages | Configuration › Droits et accès | Maintenu | Visible pour recruteurs et modérateurs (02 F15) |
| Journal d'audit | Configuration › Droits et accès | Maintenu | Visible pour `admin.audit.view` (02 F16) |
| Événements › Inscriptions en cours, Calendrier | Opérations › Agenda | Regroupé | Même page (03 O-03) |
| Événements › Bilan de participation | Opérations › Agenda (onglet Bilan) ; lien dans Indicateurs | Regroupé | — |
| Missions › Portail missions | Opérations › Dossiers d'opération | Regroupé | Liste des dossiers |
| Missions › Cycle de mission | Dossier d'opération › Exécution ; liste transverse conservée en onglet | Regroupé | Le cycle n'est relié ni au plan ni à l'événement (03 P1) |
| Missions › Diapositives de briefing | Dossier d'opération › Briefing ; bibliothèque transverse en onglet | Regroupé | — |
| Carte tactique | Opérations › Situation tactique | Déplacé | Contrôleur ATAK (03 A-02) |
| Comptes rendus › CR post-op, Modèles | Après-action et renseignement › Comptes rendus (onglet Modèles) | Déplacé | — |
| Comptes rendus › Remontées des joueurs | Après-action et renseignement › Remontées | Déplacé | Fil d'Ariane déjà « Renseignement » |
| Coopérations › 4 entrées | Coopérations › Coopérations inter-unités (onglets Toutes, Types et modèles, Messages d'annonce ; bouton Nouvelle) | Regroupé | 4 liens pour une rubrique |
| Extranet d'unité › 10 entrées | Coopérations › Extranet d'unité ↗ (une entrée) ; Espace d'unité › Extranet | Regroupé | Pages destinées aux membres, dans leur propre espace |
| Suivi roleplay › 3 entrées | Roleplay | Maintenu | P10 |
| Mode roleplay ATAK | Configuration › ATAK › Réalisme et réseau | Regroupé | Trois stockages pour le réseau (RD-31) |
| Overwatch | Opérations › Situation tactique ↗ | Déplacé | Outil d'opération |
| Poste de situation | Opérations › Situation tactique | Déplacé | idem |
| Contrôle de mission | Terrain ATAK › Supervision (lecture) ; édition dans Configuration › ATAK | Regroupé | Même stockage que A-14 (RD-10) |
| Supervision › 9-LINE | Opérations › Situation tactique ↗ ; appareils dans Terrain ATAK › Aviation | Déplacé | — |
| Supervision › Relais, Data Inspector, Files d'envoi | Terrain ATAK › Supervision | Maintenu | — |
| Terminaux › Parc de terminaux, Parc ATAK des membres, Certificats | Terrain ATAK › Appareils et liaisons | Regroupé | Mêmes appareils (RD-34) |
| Terminaux › Sessions et connexions, Fiche opérateur | Terrain ATAK › Supervision › Opérateurs ; fiche dans l'onglet Terrain de la fiche membre | Regroupé | — |
| Terminaux › Détection des marqueurs | Configuration › ATAK › Symboles et marqueurs | Déplacé | Réglage lu par le mod |
| Matériel › Équipement des joueurs | Équipement et dotation › Porté en jeu | Déplacé | — |
| Matériel › Kits par rôle | Équipement et dotation › Kits par emploi | Déplacé, éditeur unique | RD-32 |
| Matériel › Arsenal et tenues | Équipement et dotation › Matériel, Tenues ↗ | Maintenu | — |
| Matériel › Tenues mises en avant | Équipement et dotation | Maintenu | Libellé aligné sur le titre (03 E-08) |
| Configuration et mods › 6 entrées | Configuration › ATAK (sections) | Regroupé | §8 |
| Formations | Communauté › Formations | Maintenu | — |
| Doctrine et documents › 4 entrées | Communauté › Documents et doctrine | Maintenu | Libellés clarifiés (RD-68) |
| Vitrine et portail › Paramètres | Configuration › Organisation (Identité et image, Inscription et contact, Portail, Profil) | Regroupé | RD-12, RD-44 |
| Vitrine et portail › Page d'accueil publique | Configuration › Organisation › Page publique | Déplacé, libellé aligné | Titre interne différent du menu (04 B2) |
| Vitrine et portail › Raccourcis du portail, Catégories | Configuration › Organisation › Portail des membres | Regroupé | — |
| Vitrine et portail › Annoncer sur Discord | Configuration › Organisation › Page publique (visibilité) | Regroupé | Mise en avant de la vitrine |
| Vitrine et portail › Médias | Communauté › Médias et musique | Déplacé | — |
| Communication › Annonces, Mini-articles, E-mails (4) | Communauté › Publier | Regroupé | Un point d'entrée « Publier » (RD-29) |
| Modération › Signalements, Quarantaine | Ressources humaines › Discipline | Regroupé | RD-28 |
| Musique | Communauté › Médias et musique | Regroupé | — |
| Configuration initiale | Configuration › Organisation › Démarrage | Regroupé | Avec le rattrapage (RD-42) |
| Réglages › Configuration | Supprimé comme page de liens ; « Affichage des rôles » va dans Configuration › Droits et accès › Niveaux d'accès | Regroupé | Page de liens vers des écrans déjà au menu (04 B7) |
| Réglages › Intégrations | Configuration › Intégrations | Maintenu, élargi au bot Discord | RD-30 |
| Réglages › Mises à jour, Messages de la plateforme | Configuration › Organisation | Maintenu | — |
| Réglages › Traçabilité du courrier, Export pour contrôle | Configuration › Organisation | Maintenu | Une seule route pour la traçabilité (RD-63) |

## 11. Plateforme `/admin`

| Point | PROPOSITION | Motif (FAIT) |
|---|---|---|
| Position de la barre | À gauche, comme le back-office | Habitudes inversées (05 §3.2) |
| En-tête de page | En-tête partagé sans surtitre | 40 vues avec leur propre `pa-head` (05 §1.5) |
| Pages hors barre | Ajouter « Doublons Steam » sous Comptes et accès | `admin/steam-doublons` hors barre (05 §2.3) |
| Contenu | Inchangé | Hors périmètre |
