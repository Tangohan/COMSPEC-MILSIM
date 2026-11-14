# Document 4 : parcours actuels et proposés

Douze parcours, plus deux parcours complémentaires. Pour chacun : les étapes actuelles (FAIT, tirées des audits), les étapes proposées (PROPOSITION, sur l'architecture du document 3), et le gain.

Règles de comptage :
- Une **étape** = une page ouverte ou un formulaire validé. Les champs d'un même formulaire comptent pour une étape.
- Un **écran distinct** = une adresse différente (les onglets d'une même page comptent pour un écran).
- Une **rupture** = changement de groupe de menu, sortie du shell du back-office, ou passage par une autre personne.
- Une **ressaisie** = une information déjà donnée qu'il faut saisir de nouveau.
- Les comptes « avant » sont faits sur le code (audits). Les comptes « après » sont des estimations sur la cible. **Aucun n'est mesuré** : ce sont des HYPOTHÈSES de charge, utiles pour comparer, pas des durées.

## Synthèse

| N° | Parcours | Étapes avant → après | Écrans distincts avant → après | Ruptures avant → après | Ressaisies avant → après | Source |
|---|---|---|---|---|---|---|
| 1 | Créer une organisation | 14 → 9 | 6 → 3 | 3 → 1 | 8 champs → 0 | 04 §4.1, §4.2 |
| 2 | Structurer l'organisation | 9 → 6 | 7 → 3 | 4 → 1 | 1 → 0 | 01 F14-F16, F42 ; 05 §4.3 |
| 3 | Gérer une unité | 7 → 3 | 7 → 1 | 3 → 0 | 0 → 0 | 01 F13-F15, F32 ; 03 J-02 |
| 4 | Recruter un membre | 8 → 5 | 6 → 3 | 3 → 1 | 4 → 0 | 01 P1 |
| 5 | Donner un grade, une fonction, une affectation | 7 → 4 | 3 → 1 | 2 → 0 | 0 → 0 | 01 P2 |
| 6 | Configurer les droits d'un membre | 8 → 4 | 5 → 1 | 4 → 0 (1 si validation voulue) | 0 → 0 | 02 §4.1 |
| 7 | Personnaliser le système de grades | 7 → 5 | 5 → 1 | 3 → 0 | 0 → 0 | 01 P3 |
| 8 | Constituer les groupes opérationnels | 6 → 3 | 5 → 1 | 3 → 0 | 2 → 0 | 03 O-02, O-11, T-06, A-10 ; 01 F16 |
| 9 | Préparer une opération | 18 → 11 | 13 → 4 | 6 → 1 | 3 → 0 | 03 P1 |
| 10 | Configurer ATAK et Overwatch | 15 → 10 | 12 → 3 | 5 → 1 | 1 → 0 | 03 P2 |
| 11 | Administrer l'équipement et la dotation | 9 → 7 | 8 → 3 | 4 → 1 | 1 → 0 | 03 P3 |
| 12 | Consulter l'historique d'un membre | 9 → 1 | 9 → 1 | 3 → 0 | 0 → 0 | 01 P4 |
| C1 | Connecter Discord | 7 → 3 | 5 → 1 | 3 → 0 | 1 → 0 | 04 §4.3 |
| C2 | Traiter une demande d'avis (Mon service) | 4 → 3 | 4 → 2 | 2 → 0 | 0 → 0 | 02 §4.2 |

---

## 1. Créer une organisation

### Actuel (FAIT, 04 §4.1 et §4.2)

| # | Étape | Écran | Rupture ou ressaisie |
|---|---|---|---|
| 1 | Lire le guide (facultatif) | `/communities/create/guide` | — |
| 2 | Se connecter | `login` | — |
| 3-7 | Assistant en 5 étapes : identité, usage, organisation, grades, accès et offre (~30 champs) | `/communities/create` | — |
| 8 | Aperçu (facultatif) | `/communities/create/preview` | — |
| 9 | Paiement si formule payante | `/paiement/{ref}` | rupture : sortie vers le paiement |
| 10 | Configuration initiale : logo, contact, inscription, modules publics, rôle invité | `BO/configuration-initiale` | ressaisie : `welcome_text`, `registration_mode`, verrous, accusé IA, refus d'autres communautés |
| 11 | Première visite : redirection possible vers les nouveautés | `BO/mise-a-niveau?onglet=nouveautes` | rupture non demandée |
| 12 | Paramètres : identité, vitrine, inscription, portail (4 onglets utiles) | `BO/community` | ressaisie : textes publics, contact Discord, image du registre |
| 13 | Page d'accueil publique (~110 champs) | `BO/community/presentation` | ressaisie : image du registre, images de menu, contact |
| 14 | Organigramme, rôles, invitations | voir parcours 2 et 4 | — |

Bilan : 14 étapes, 6 écrans, 3 ruptures, 8 champs saisis deux à quatre fois (RD-12, RD-43, RD-44).

### Proposé

| # | Étape | Emplacement cible |
|---|---|---|
| 1-5 | Assistant en 5 étapes, inchangé dans son contenu | `/communities/create` |
| 6 | Paiement si formule payante | `/paiement/{ref}` |
| 7 | Accueil avec la checklist de démarrage (une seule, qui fusionne configuration initiale et rattrapage) | Accueil › bandeau Démarrage |
| 8 | Chaque point de la checklist ouvre la section de Configuration › Organisation où le champ vit, déjà rempli par l'assistant | Configuration › Organisation |
| 9 | Organigramme, puis invitations | Unités › Organigramme ; Ressources humaines › Recrutement |

Gains : 14 → 9 étapes ; aucune ressaisie, parce que chaque champ n'a plus qu'un formulaire ; la redirection vers les nouveautés est différée tant que la checklist n'est pas terminée (PROPOSITION) ; `/c/{slug}/setup` disparaît (S5).

## 2. Structurer l'organisation

### Actuel (FAIT)

| # | Étape | Écran | Rupture |
|---|---|---|---|
| 1 | Ouvrir l'organigramme | `/orbat` (rendu hors du shell, `PersonnelController.php:1753-1767`) | sortie du shell |
| 2 | Créer les unités, déplacer, archiver | API `/api/orbat/structure` | — |
| 3 | Désigner les chefs (ou dans la chaîne) | ORBAT `updateUnit` ou `EFF/chaine` | deux endroits (RD-22) |
| 4 | Créer les postes d'une unité | `BO/organisation/unites/{id}/postes` | — |
| 5 | Régler les types d'entité | `BO/organisation/types-entite` | autre sous-menu |
| 6 | Groupes et équipes | `BO/groups`, `BO/teams` (création redirigée vers l'ORBAT) | aller-retour |
| 7 | Codes d'unité | `EFF/affectations` (codes) | autre groupe (Membres) |
| 8 | Règles de matricule et d'indicatifs | `BO/organisation/matricules`, `/indicatifs` | — |
| 9 | Adéquation par échelon | `EFF/chaine/adequation` (hors menu) | lien interne seulement |

Bilan : 9 étapes, 7 écrans, 4 ruptures, 1 ressaisie (chef d'unité selon l'écran).

### Proposé

| # | Étape | Emplacement cible |
|---|---|---|
| 1 | Ouvrir l'organigramme, dans le shell | Unités › Organigramme |
| 2 | Créer les unités ; panneau latéral de l'unité : chef, type, code | Unités › Organigramme |
| 3 | Ouvrir l'espace d'une unité : postes, équipes | Espace d'unité › Postes, Équipes |
| 4 | Types d'entité et adéquation (une fois) | Configuration › Référentiels › Organisation type |
| 5 | Règles d'identification (une fois) | Configuration › Référentiels › Identification |
| 6 | Vérifier la chaîne | Unités › Organigramme (vue chaîne) |

Gains : 9 → 6 étapes ; plus de sortie du shell ; un seul éditeur du chef d'unité.

## 3. Gérer une unité

### Actuel (FAIT)

| # | Étape | Écran |
|---|---|---|
| 1 | Composition | `EFF?unite=…` (tableur filtré) |
| 2 | Postes et titulaires | `BO/organisation/unites/{id}/postes` ou `/orbat` |
| 3 | Affectations et mouvements | `EFF/affectations?unite=…` |
| 4 | Chef et adjoints | `EFF/chaine` |
| 5 | Qualifications de l'unité | `EFF/qualifications` (filtre) |
| 6 | Disponibilité | `BO/organisation/disponibilite` ou bloc de l'accueil |
| 7 | Page d'unité pour les membres | `/jnet/unite`, `/jnet/u/{id}/personnaliser` |

Bilan : 7 étapes, 7 écrans, 3 ruptures (Membres, Organisation, Extranet).

### Proposé

| # | Étape | Emplacement cible |
|---|---|---|
| 1 | Ouvrir l'espace d'unité depuis l'organigramme, la recherche ou la fiche d'un membre | Espace d'unité › Vue d'ensemble |
| 2 | Passer d'un onglet à l'autre : Membres, Postes, Chaîne, Qualifications, Dotation type | Espace d'unité |
| 3 | Ouvrir l'extranet de l'unité | Espace d'unité › Extranet ↗ |

Gains : 7 écrans → 1 ; le chef d'unité (menu cadre) y accède sans droit global.

## 4. Recruter un membre

### Actuel (FAIT, 01 P1, variante par candidature)

| # | Étape | Écran | Rupture ou ressaisie |
|---|---|---|---|
| 1 | Le candidat dépose sa candidature | `/c/{slug}/enlistment` | — |
| 2 | Le staff ouvre la liste | `BO/recruitments` | menu opérateur pour un recruteur sans droit global (ACC-F23) |
| 3 | Ouvrir la candidature | `BO/recruitments/{id}` | — |
| 4 | Décider | `POST /decision` | — |
| 5 | Onboarding : identité, unité, emploi, rôles, niveau, grade, position, référent, état civil | `BO/recruitments/{id}/onboarding` | ressaisie partielle de l'offre (identité, unité, emploi, niveau) |
| 6 | Compléter le dossier | `EFF/membres/{id}` | rupture : autre groupe |
| 7 | Étapes et rendez-vous d'intégration | `BO/integration-membres/{id}` | rupture ; référent ressaisi |
| 8 | Vérifier les droits accordés | registre | sans contrôle fin à l'onboarding (S12) |

Bilan : 8 étapes, 6 écrans, 3 ruptures, 4 ressaisies.

### Proposé

| # | Étape | Emplacement cible |
|---|---|---|
| 1 | Candidature déposée | `/c/{slug}/enlistment` |
| 2 | La candidature arrive dans « À traiter » | Accueil › À traiter |
| 3 | Décider depuis la candidature | Ressources humaines › Recrutement › Candidatures |
| 4 | Onboarding pré-rempli par l'offre ; grade et niveau proposés seulement si le recruteur a les droits correspondants | même page |
| 5 | La fiche membre s'ouvre, avec l'intégration en carte de la Synthèse (étapes, référent, rendez-vous) | Fiche membre › Synthèse |

Gains : 8 → 5 étapes ; plus de ressaisie ; le recruteur voit son menu (menu cadre) ; droits alignés (S12).

## 5. Donner un grade, une fonction, une affectation

### Actuel (FAIT, 01 P2)

| # | Étape | Écran | Remarque |
|---|---|---|---|
| 1 | Ouvrir la fiche | `EFF/membres/{id}` | — |
| 2 | Unité : « Changer d'unité » | carte Poste › Unité | — |
| 3 | Rôle dans l'unité | **absent de la fiche** : fenêtre du tableur `EFF` | rupture |
| 4 | Emplois | fenêtre « Modifier les fonctions » | — |
| 5 | Niveau d'accès | carte Poste › Niveau d'accès | — |
| 6 | Grade | carte Carrière › Grades | absent du tableur |
| 7 | Poste dans l'organigramme | `/orbat` (occupation de billet), sans lien avec l'affectation | rupture, sortie du shell |

Bilan : 7 étapes, 3 écrans, 2 ruptures.

### Proposé

| # | Étape | Emplacement cible |
|---|---|---|
| 1 | Ouvrir la fiche | Fiche membre |
| 2 | Onglet Affectation et poste : unité, poste tenu (choisi parmi les postes vacants de l'unité), intitulé, emplois, en un formulaire | Fiche membre › Affectation et poste |
| 3 | Onglet Carrière : grade | Fiche membre › Carrière |
| 4 | (si le droit manque) « Demander » crée une demande RH au lieu d'une modification | Membres › Demandes RH |

Le niveau d'accès sort de ce parcours : c'est un droit, pas un poste (parcours 6). Gains : 7 → 4 étapes ; affectation et poste ORBAT synchronisés (RD-21).

## 6. Configurer les droits d'un membre

### Actuel (FAIT, 02 §4.1)

| # | Étape | Écran | Remarque |
|---|---|---|---|
| 1 | Trouver le membre | `EFF` ou Ctrl+K | — |
| 2 | Changer son niveau | Fiche › Niveau d'accès | sans dates ni unité |
| 3 | Niveau temporaire ou limité à une unité | `EFF/registre-droits` › Attribuer | seul écran qui le permet |
| 4 | Vérifier ce que le niveau permet | `EFF/roles` | modifie pour tous les titulaires |
| 5 | Donner un droit précis | « Demander une élévation » puis un **second responsable** décide dans `EFF/elevations` | aucun écran direct |
| 6 | Position administrative | Fiche › Statut du compte | droit requis différent du registre |
| 7 | Accès au renseignement | `BO/renseignement/acces` | hors registre |
| 8 | Droits liés aux qualifications | Référentiels › Qualifications › droits | sans effet (S8) |
| 9 | Vérifier | Registre filtré | plusieurs lignes par membre (traité par PR #1600, #1601, #1602) |

Bilan : 8 étapes utiles (la 8 est sans effet), 5 écrans, 4 ruptures (dont un second responsable), aucun écran des droits effectifs.

### Proposé

| # | Étape | Emplacement cible |
|---|---|---|
| 1 | Ouvrir la fiche | Fiche membre |
| 2 | Onglet Accès et habilitations : ajouter un niveau avec dates et périmètre (même service que le registre) | Fiche membre › Accès et habilitations |
| 3 | Ajouter un droit individuel (direct pour `canManageRoles`, journalisé ; circuit d'élévation conservé si la communauté l'active) et une habilitation SSE | même onglet |
| 4 | Lire les droits effectifs calculés | même onglet |

La position administrative quitte ce parcours (onglet Position et statut). Gains : 8 → 4 étapes ; une vue des droits effectifs ; le profil RH ne peut plus accorder `admin.organization` par la décision d'élévation (S4).

## 7. Personnaliser le système de grades

### Actuel (FAIT, 01 P3)

| # | Étape | Écran |
|---|---|---|
| 1 | Ouvrir le référentiel (exige un droit global ; `personnel.grades.manage` ne suffit pas) | `BO/referentiels/grades` |
| 2 | Choisir un système officiel ou « aucun » | fenêtre « Systèmes de grades » |
| 3 | Ou créer un système sur mesure, catégories, grades, « utiliser » | `?tab=custom`, `/sur-mesure/*` |
| 4 | Grades propres : archivage par un autre contrôleur | `?tab=perso` → `/organisation/grades/{id}/archive` |
| 5 | Ordre et filières de l'avancement | `BO/organisation/grades` (hors menu) |
| 6 | Contrôle OTAN | `BO/referentiels/grades/catalogue` (hors menu) |
| 7 | Critères d'élévation | `EFF/elevations/criteres`, lié **seulement depuis une fiche membre** |

Bilan : 7 étapes, 5 écrans, 3 contrôleurs, 2 préfixes d'adresse, 3 ruptures.

### Proposé

| # | Étape | Emplacement cible |
|---|---|---|
| 1 | Ouvrir Configuration › Référentiels › Grades (ouvert à `personnel.grades.manage`) | — |
| 2 | Onglet Système : officiel, aucun ou sur mesure | même page |
| 3 | Onglet Grades de la communauté (création, archivage) | même page |
| 4 | Onglet Ordre et filières | même page |
| 5 | Onglets Contrôle OTAN et Critères d'élévation | même page |

Gains : 5 écrans → 1 ; le droit qui permet de changer un grade permet d'ouvrir le référentiel.

## 8. Constituer les groupes opérationnels d'une opération

### Actuel (FAIT)

| # | Étape | Écran | Remarque |
|---|---|---|---|
| 1 | Importer l'ORBAT dans le plan | `POST BO/planification/{id}/organigramme` | instantané copié |
| 2 | Importer les inscrits de l'événement | `POST /inscrits-evenement` | instantané copié |
| 3 | Affecter les postes du plan | `BO/planification/{id}?vue=organisation` | — |
| 4 | (variante) Construire l'ORBAT de l'espace opérationnel | `/operations/{uuid}` | ressaisie, hors back-office |
| 5 | (variante) Éléments du plan TOC | `/atak/toc/plans/{id}` | ressaisie, hors back-office |
| 6 | Équipes de feu en jeu | `BO/atak/escouades?onglet=fire-teams`, rangé sous Indicateurs | troisième source, sans lien avec les postes du plan |

Bilan : 6 étapes, 5 écrans, 3 ruptures, 2 ressaisies.

### Proposé

| # | Étape | Emplacement cible |
|---|---|---|
| 1 | Dossier d'opération › Organisation et postes : partir de l'ORBAT et des inscrits (import gardé) | Dossier d'opération |
| 2 | Créer les groupes opérationnels et affecter les postes | même onglet |
| 3 | En exécution, comparer prévu et détecté (équipes de feu remontées par les téléphones) | Dossier d'opération › Exécution |

Le TOC et `/operations` lisent l'organisation du dossier (lot 3). Gains : 6 → 3 étapes ; une organisation par opération ; l'unité permanente reste intacte (glossaire, document 3 §2).

## 9. Préparer une opération

### Actuel (FAIT, 03 P1)

| # | Étape | Écran | Rupture ou ressaisie |
|---|---|---|---|
| 1 | Créer le créneau | `BO/events#nouveau` | — |
| 2 | Publier au mur | bouton du portail → `POST BO/tableau-operationnel/publier-lie` | portail → BO ; copie |
| 3 | Créer le plan | `BO/missions?onglet=planification` | autre famille ; ressaisie titre, DTG, carte |
| 4 | Importer ORBAT et inscrits | POST du plan | copie |
| 5 | (variante) Espace opérationnel | `/operations/{uuid}` | hors BO ; ressaisie |
| 6 | (variante) Plan TOC | `/atak/toc/plans/{id}` | hors BO ; ressaisie |
| 7 | Diapositives | `BO/atak/briefing-slides` | rattachées à `operations`, pas au plan |
| 8 | Consignes et kit | `BO/ressources/atak-consignes` | famille Configuration ATAK |
| 9 | Kits et dotation | `BO/kits-roles`, `BO/referentiels/dotation` | deux familles |
| 10 | Conditions de jeu | `BO/atak/controle-serveur`, `BO/atak/roleplay` | trois écrans pour le réseau |
| 11 | Ouvrir le cycle | `BO/atak/cycle-mission` | cycle relié ni au plan ni à l'événement |
| 12 | Passer le plan en « live » | onglet Planification | retour |
| 13 | Exécution | `/atak`, `BO/atak`, `/9-line`, `/m/*` | 3 à 4 fenêtres |
| 14 | Clôturer le cycle, rattacher la partie | `BO/atak/cycle-mission` | rattachement manuel |
| 15 | CR des joueurs | `BO/atak/comptes-rendus?onglet=suivi` | — |
| 16 | Clôturer le plan, éditer l'AAR | Planification puis Comptes rendus | lien AAR ↔ événement par titre |
| 17 | Remontées | `BO/remontees` | autre middleware |
| 18 | Les membres consultent | `/jnet/operations`, `/tableau-operationnel` | JNET ne montre que le mur |

Bilan : 18 étapes, 13 écrans, au moins 6 familles de menu, 3 copies, 2 à 3 ressaisies.

### Proposé

| # | Étape | Emplacement cible |
|---|---|---|
| 1 | Créer le créneau ; « Créer le dossier d'opération » reprend titre, date et carte | Opérations › Agenda |
| 2 | Organisation et postes (parcours 8) | Dossier › Organisation et postes |
| 3 | Briefing : diapositives, documents, ordres, consignes, kits par emploi | Dossier › Briefing |
| 4 | Vérifier les conditions de jeu (lecture) ; ajuster si besoin | Dossier › Conditions de jeu → Configuration › ATAK |
| 5 | Publier au mur (lien vivant) | Dossier › Publication |
| 6 | Passer en live : ouvre le cycle de mission rattaché | Dossier › Synthèse |
| 7 | Exécution : outils tactiques ouverts depuis le dossier | Dossier › Exécution ↗ |
| 8 | Clôturer : la partie jouée se rattache au dossier | Dossier › Exécution |
| 9 | CR des joueurs | Dossier › Après-action |
| 10 | AAR créé par la clôture, lié au dossier (et donc à l'événement) | Dossier › Après-action |
| 11 | Remontées rattachées, visibles selon l'habilitation | Dossier › Renseignement |

Gains : 18 → 11 étapes ; 6 familles → 1 (plus les outils tactiques) ; plus de copie ni de lien par titre (RD-18, RD-19, RD-20).

## 10. Configurer ATAK et Overwatch

### Actuel (FAIT, 03 P2)

| # | Étape | Écran | Remarque |
|---|---|---|---|
| 1 | Clé, serveur, carte par défaut | `BO/ressources/atak-config#acces`, `#serveur` | pas de liste des cartes |
| 2 | Connexion en jeu, version, logo | `#overwatch-game-experience` | — |
| 3 | Liaison obligatoire | `POST /first-login-link` | stockée ailleurs |
| 4 | Modules et flux | `#bridge-modules`, `#data-flows` | fichiers de cache |
| 5 | Mods et restrictions | `BO/ressources/atak-mod`, `/atak-mod-blocks` | — |
| 6 | Serveurs de jeu | `BO/ressources/serveurs-de-jeu` | — |
| 7 | Règles en jeu | `BO/atak/controle-serveur` | mêmes réglages qu'à l'étape 4 |
| 8 | Réalisme | `BO/atak/realisme?onglet=reglages`, `BO/atak/roleplay` | deux familles, triple stockage |
| 9 | Icônes | `#marker-icons` | doublon de symbologie |
| 10 | Détection des marqueurs | `BO/atak/detection-marqueurs` | droit ORG au lieu de RES |
| 11 | Diffusion des rapports | `BO/ressources/atak-diffusion-rapports` | — |
| 12 | Consignes et kit | `BO/ressources/atak-consignes` | — |
| 13 | Tuiles, cartes perso, MNT | aucun écran | outil en ligne de commande, `/overwatch`, mod |
| 14 | Côté membres | `/atak/premiere-liaison`, `/connect` | appairages invisibles |
| 15 | Vérifier | 5 écrans de supervision | — |

Bilan : 15 étapes, 12 écrans, 5 ruptures, 1 ressaisie (règles en double).

### Proposé

| # | Étape | Emplacement cible |
|---|---|---|
| 1 | Configuration › ATAK › Démarrage (checklist) | page unique |
| 2 | Connexion et serveur (y compris liaison obligatoire et version) | section |
| 3 | Expérience et règles en jeu | section |
| 4 | Réalisme et réseau (une source versionnée) | section |
| 5 | Cartes et terrain (liste des cartes, carte par défaut, cartes perso, tuiles disponibles) | section |
| 6 | Symboles et marqueurs (icônes et détection) | section |
| 7 | Mods et serveurs | section |
| 8 | Diffusion des rapports ; consignes et matériel conseillé | sections |
| 9 | Côté membres : première liaison ; appairages listés et révocables | Mon espace ; Terrain ATAK › Appareils |
| 10 | Vérifier | Terrain ATAK › Supervision (contrôle de mission en lecture) |

Gains : 12 écrans → 3 ; une garde ATAK `manage` partout ; plus de réglage sans interface, sauf la production des tuiles, qui reste un outil en ligne de commande (sa liste devient visible).

## 11. Administrer l'équipement et la dotation

### Actuel (FAIT, 03 P3)

| # | Étape | Écran | Remarque |
|---|---|---|---|
| 1 | Voir le porté réel | `BO/equipement-joueurs` | — |
| 2 | Enregistrer un kit porté comme tenue | POST `kits/{steam}/tenue` | — |
| 3 | Créer un kit par rôle | `BO/kits-roles/nouveau` ou `/equipment?espace=kits` ou configurateur ou assistant | droits différents |
| 4 | Publier le kit | POST `/publier` | lien de vue vers une route JSON |
| 5 | Ajuster dans le configurateur | `/equipment/configurateur` | portail |
| 6 | Articles de dotation et attributions | `BO/referentiels/dotation` | famille Référentiels |
| 7 | Tenues mises en avant | `BO/dashboard-tenues` | libellé différent du titre |
| 8 | Kit obligatoire ATAK | `BO/ressources/atak-consignes` | ressaisie depuis le catalogue |
| 9 | Le membre consulte | `/equipment`, `BO/ma-situation/dotation` | — |

Bilan : 9 étapes, 8 écrans, 4 ruptures (ATAK › Matériel, Référentiels, Configuration, portail), 1 ressaisie.

### Proposé

| # | Étape | Emplacement cible |
|---|---|---|
| 1 | Porté en jeu | Équipement et dotation › Porté en jeu |
| 2 | Enregistrer comme tenue | même page |
| 3 | Créer et publier un kit par emploi (éditeur unique, configurateur ouvert depuis l'éditeur) | Équipement et dotation › Kits par emploi |
| 4 | Dotation : articles, dotation type issue des kits, attributions | Équipement et dotation › Dotation |
| 5 | Tenues mises en avant | Équipement et dotation |
| 6 | Liste de matériel conseillé ATAK : choisie depuis les kits ou le catalogue | Configuration › ATAK › Consignes et matériel conseillé |
| 7 | Le membre consulte | Mon espace › Ma situation › Dotation ; `/equipment` |

Gains : 4 familles → 1 (plus la configuration ATAK) ; droits identiques sur les kits (RD-32) ; dotation visible en profil ATAK (à arbitrer, lot 5).

## 12. Consulter l'historique d'un membre

### Actuel (FAIT, 01 P4)

| # | Source | Où |
|---|---|---|
| 1 | Frise des grades | Fiche › Carrière |
| 2 | Élévations (10 dernières) | Fiche › Élévation |
| 3 | Historique organisation, service, bilans | Fiche › Historique |
| 4 | Dernier départ | Fiche › Départ |
| 5 | Historique d'avancement | non affiché sur la fiche (HYPOTHÈSE) |
| 6 | Historique des droits | `EFF/registre-droits?vue=historique` |
| 7 | Mouvements d'affectation | `EFF/affectations` (30 derniers, tous membres) |
| 8 | Journal d'audit | `BO/audit` |
| 9 | Papiers, sans compte, décorations, intégration | `/papiers/historique`, `EFF/sans-comptes`, barrette, `BO/integration-membres/{id}` |

Bilan : 9 consultations, au moins 6 sources dont 3 hors de la fiche, aucune vue chronologique.

### Proposé

| # | Étape | Emplacement cible |
|---|---|---|
| 1 | Onglet Historique : chronologie unique filtrable par type (grade, affectation, droits, papiers, intégration, distinctions, discipline, départ, audit) | Fiche membre › Historique |

Gains : 9 → 1. Condition : réunir les sources en lecture, sans changer les tables (lot 2) ; les deux historiques de grade restent lisibles jusqu'à l'unification (lot 3).

---

## Parcours complémentaires

### C1. Connecter Discord

| Avant (FAIT, 04 §4.3) | Après (PROPOSITION) |
|---|---|
| 1. Lien d'invitation : Paramètres › Inscription, ou configuration initiale, ou page publique | 1. Configuration › Intégrations › Discord : lien d'invitation, salon principal, routage par type d'événement, salons supplémentaires, modèle d'invitation |
| 2. Intégrations : salon principal, routage, salons, test, modèle (menu affiché seulement si tenant > 1 et droit global) | 2. Même page : jeton du bot et appairage du compagnon |
| 3. Événements : jeton du bot, appairage du compagnon | 3. Dossier d'opération › Créneau : destination propre à un événement, si elle diffère du routage |
| 4. Fiche événement : destination par événement | — |
| 5. Recrutement : questions Discord | (reste dans Recrutement › Réglages) |
| 6. Membres : rôles liés, connexion | (inchangé, côté membre) |
| 7. Vitrine Discord de la plateforme | (Configuration › Organisation › Page publique) |
| 7 étapes, 5 écrans, 3 ruptures | 3 étapes, 1 écran principal |

### C2. Traiter une demande d'avis (Mon service)

| Avant (FAIT, 02 §4.2) | Après (PROPOSITION) |
|---|---|
| 1. Notification « Avis de chef d'unité demandé » (cloche, boîte de réception) | 1. La demande apparaît dans Accueil › À traiter et dans Mon service (menu, déjà engagé) |
| 2. Ouvrir `/mon-service` (hors du back-office, aucune entrée de menu à la date de l'audit) | 2. Donner l'avis depuis la ligne |
| 3. Avis favorable ou défavorable | 3. Le décideur voit l'avis dans Membres › Demandes RH |
| 4. Le décideur ouvre Demandes d'accès élevé | — |
| 4 étapes, 2 ruptures (sortie du shell) | 3 étapes, aucune rupture ; statuts affichés en clair au lieu de `open`, `normal` (`action_center.php:108-111`) |
