# COMSPEC SSE : modules, tutoriels et matériel

Guide du chef de mission et du Zeus. Version anglaise : [Modules_and_equipment_SSE.en.md](Modules_and_equipment_SSE.en.md).

Le même contenu, en plus court, est dans le jeu : **Échap › Manuel de terrain › COMSPEC SSE**, ou dans Eden le menu **COMSPEC SSE** de la barre du haut. Chaque champ d'un module affiche une bulle d'aide quand on le survole.

---

## Sommaire

1. [Le vocabulaire en une page](#1-le-vocabulaire-en-une-page)
2. [Où trouver chaque information](#2-où-trouver-chaque-information)
3. [Tutoriel 1 : une première mission SSE](#3-tutoriel-1--une-première-mission-sse)
4. [Tutoriel 2 : jouer le dataset FALCON](#4-tutoriel-2--jouer-le-dataset-falcon)
5. [Tutoriel 3 : ranger les fiches dans une affaire Athena](#5-tutoriel-3--ranger-les-fiches-dans-une-affaire-athena)
6. [Tutoriel 4 : une campagne sur plusieurs missions](#6-tutoriel-4--une-campagne-sur-plusieurs-missions)
7. [Tutoriel 5 : un ordinateur à pirater (DOMEX et DL-7)](#7-tutoriel-5--un-ordinateur-à-pirater-domex-et-dl-7)
8. [Les modules, un par un](#8-les-modules-un-par-un)
9. [Les attributs Eden des personnes et des objets](#9-les-attributs-eden-des-personnes-et-des-objets)
10. [Le matériel SSE](#10-le-matériel-sse)
11. [Les actions des joueurs (menu ACE)](#11-les-actions-des-joueurs-menu-ace)
12. [Les réglages CBA](#12-les-réglages-cba)
13. [Pièges et dépannage](#13-pièges-et-dépannage)

---

## 1. Le vocabulaire en une page

| Mot | Ce que c'est | Qui le choisit |
|---|---|---|
| **SSE** | *Sensitive Site Exploitation* : exploiter tout ce qu'on trouve sur un objectif (personnes, corps, documents, téléphones, ordinateurs, caches). | — |
| **Dataset** | Un **scénario prêt à jouer** : une liste de rôles nommés (chef, artificier, courrier...), les liens entre eux et une graine fixe. Les mêmes rôles donnent toujours les mêmes noms, alias, téléphones, documents et empreintes. Aujourd'hui : **FALCON**. | Vous le choisissez dans une liste. Vous ne le créez pas. |
| **Niveau de révélation** | 0 à 3. Décide combien de rôles d'un dataset sont distribués, donc jusqu'où les joueurs peuvent remonter le réseau. | Vous, dans le module Directeur de scénario. |
| **Modèle** | Une **recette de génération** : profil, région, thème, alias, messages, documents. Donne un *style*, pas des personnages fixes. 24 modèles intégrés, plus ceux que vous enregistrez au Zeus. | Vous, dans une liste ; ou vous en créez un au Zeus. |
| **Campagne** | Ce qui survit d'une mission à l'autre : graine biométrique, personnages récurrents, organisations, ce que les joueurs savent, registre des scellés. | **Vous inventez son identifiant** (ex. `OP-SABLE`). Rien à demander à personne. |
| **Référence de dossier** | Le numéro d'une **affaire ouverte sur Athena** (ex. `SSE-2026-0007`). Les fiches transmises par les joueurs y sont rangées. | **Athena l'attribue** quand vous ouvrez l'affaire. Vous la recopiez dans la mission. |
| **Graine** | Un nombre ou un texte qui rend la génération reproductible : même graine, mêmes résultats. 0 ou vide = automatique. | Rarement à toucher. |
| **Palier d'accès (DOMEX)** | Où en est l'accès à un support numérique : non identifié, découvert, accès en cours, accès établi, exploité. | Vous, ou le Zeus pendant la partie. |
| **Bureau SSE** | Le portail SSE d'Athena (`/atak/sse`) : dossiers, campagnes, exploitation numérique, carte. | — |

**Dataset ou modèle ?** Un modèle dit « fais-moi un financier irakien crédible ». Un dataset dit « cet homme est Omar Saadi, dit LE CHANGEUR, et il finance ABU KARIM ». Le dataset est fixe et identique pour tout le monde ; le modèle varie.

---

## 2. Où trouver chaque information

| Je cherche... | En jeu | Sur Athena |
|---|---|---|
| **La liste des datasets** | Liste du champ *Dataset* du module **Directeur de scénario** ; au Zeus, ce module avec l'action *Lister les datasets* ; Manuel de terrain, chapitre *Datasets et niveaux*. | Bureau SSE › **23 Modèles de mission** › *Kits mission (datasets)*. |
| **Les rôles d'un dataset** | Liste *Rôle dans le dataset* des attributs d'une personne ; Manuel de terrain. | Même page, tableau des rôles. |
| **Une référence de dossier** (`SSE-2026-0007`) | Elle n'existe pas en jeu tant que vous ne l'avez pas recopiée. | Bureau SSE › **04 Dossiers validés** : sur chaque ligne et en tête de la page de l'affaire. Pour en créer une : bouton **Ouvrir une affaire** (droit de gestion du Bureau SSE). |
| **Les identifiants de campagne** | Au Zeus : *Campagne : charger ou créer* (liste des sauvegardes du serveur), *Campagne : récupérer depuis Athena* (liste d'Athena). Menu ACE personnel › *Campagne en cours* pour celle chargée. | Bureau SSE › **28 Campagnes** : sous le nom, l'identifiant global `CODE:CLÉ`. **En jeu, utilisez seulement la partie après les deux-points** (`OP-SABLE` pour `COMSPEC:OP-SABLE`). |
| **Les identifiants des modèles** | Liste *Modèle intégré* du module **Appliquer un modèle SSE** ; au Zeus : module **Lister les modèles SSE** (montre aussi vos modèles enregistrés). | — |
| **Les niveaux de révélation** | Bulle d'aide du champ *Niveau de révélation* ; Manuel de terrain. | — |
| **Ce que les joueurs ont transmis** | — | Bureau SSE › Dossiers, la page de l'affaire. |
| **Les supports numériques scénarisés** | — | Bureau SSE › **17 Exploitation numérique**. |

---

## 3. Tutoriel 1 : une première mission SSE

But : une cellule de trois hommes dans une maison, un ordinateur et des papiers à exploiter. Dix minutes dans Eden.

1. **Posez le décor** : une maison, trois IA ennemies dedans, un ordinateur (`Land_Laptop_unfolded_F`) et une caisse.
2. **Rendez les joueurs capables d'exploiter** : rien à faire, les actions ACE sont toujours là. Pour le terminal biométrique, posez le module **Doter en terminal SEEK** (catégorie *3. Mission et scénario*) près du point de départ des joueurs, rayon 25 m.
3. **Générez le site** : posez le module **Créer un site SSE complet** (catégorie *1. Sites et objets*) au centre de la maison. Rayon 35 m, type *Cellule insurgée*, richesse *Détaillée*. Tout ce qui est dans le rayon reçoit identités, téléphones, documents et liens.
4. **Choisissez l'apparence des fiches** (facultatif) : module **Présentation des documents SSE**, modèle *Terrain — feuille tachée*.
5. **Lancez** : en jeu, menu ACE sur un prisonnier › **SSE** › *Inspecter*, *Fouiller*, *Biométrie › Capture complète* ; sur l'ordinateur › *Exploitation numérique* ; sur la caisse › *Fouiller*, *Collecter*, *Mettre sous scellé*. Menu ACE personnel › **COMSPEC SSE** › *Tableau des liens* pour voir le réseau apparaître.

Variante sans préparation : au Zeus, **COMSPEC SSE · Outils › Tutoriel : poser un exercice ici** pose une cellule complète prête à exploiter.

---

## 4. Tutoriel 2 : jouer le dataset FALCON

FALCON est une cellule irakienne de 2012 (graine `FALCON-IQ-2012-A`) :

| Rôle | Nom, alias | Apparaît à partir du niveau |
|---|---|---|
| Chef de secteur (HVT) | Karim Al-Rashid, **ABU KARIM** | 0 |
| Civil (bruit de fond) | Ahmed Saleh, commerçant | 0 |
| Technicien IED | Yusuf Mahdi, **L'INGÉNIEUR** | 1 |
| Courrier frontière | Hassan Qadir, **LE CHAUFFEUR** | 1 |
| Relais financier | Omar Saadi, **LE CHANGEUR** | 2 |
| Gardien de planque | Bilal Nuri, **LE LOCATAIRE** | 2 |

Liens : le chef donne des ordres au technicien, missionne le courrier, finance le relais financier ; le courrier dépose chez le gardien de planque ; le technicien y stocke son matériel.

**Méthode A : laisser le module distribuer les rôles**

1. Posez au moins **4 personnes** (niveau 1) ou **6** (niveau 2), groupées, **loin des joueurs**.
2. Posez le module **Directeur de scénario** au milieu du groupe.
3. *Action* : Appliquer le dataset. *Dataset* : FALCON. *Niveau de révélation* : 1 — Tactique. *Rayon* : assez pour couvrir le groupe (50 m).
4. Au lancement, la personne **la plus proche du module** devient ABU KARIM, puis les suivantes dans l'ordre du tableau.

**Méthode B : placer chaque rôle à la main** (plus précis)

1. Double-clic sur une personne › catégorie **COMSPEC SSE — Renseignement**.
2. *Dataset mission* : FALCON. *Rôle dans le dataset* : par exemple *FALCON · Chef de secteur ABU KARIM (HVT)*.
3. Recommencez pour chaque rôle, une seule fois chacun.

**Les niveaux**

| Niveau | Nom | Ce qui est distribué | Pour quoi |
|---|---|---|---|
| 0 | Surface | Le chef (sous son alias) et le civil | Une patrouille de reconnaissance |
| 1 | Tactique | + technicien et courrier | La mission standard (conseillé) |
| 2 | Terrain | + financier et planque | Une opération de démantèlement |
| 3 | Vérité complète | Tout | Débriefing, Zeus |

Au Zeus, le même module avec l'action **Changer le niveau seulement** change le niveau en cours de partie sans toucher aux personnes.

> Les joueurs dans le rayon comptent comme des personnes : si un joueur est plus proche du module que les IA, c'est lui qui devient le chef.

---

## 5. Tutoriel 3 : ranger les fiches dans une affaire Athena

Quand un joueur contrôle une personne au SEEK ou transmet une fiche, elle part sur Athena. La référence de dossier dit **dans quelle affaire** la ranger.

1. **Sur Athena** : ouvrez le Bureau SSE (bouton *Portail SSE* de la page ATAK, ou Back-office › Renseignement — codes d'accès pour l'encadrement).
2. Menu **04 Dossiers validés** › **Ouvrir une affaire**. Donnez un titre (« Opération Sable, phase 1 »). Il faut le droit de gestion du Bureau SSE.
3. Athena attribue la référence : `SSE-` + l'année + un numéro, par exemple **SSE-2026-0007**. Elle s'affiche en tête de l'affaire et dans la liste.
4. **Dans Eden** : module **Référence de dossier SSE** (catégorie *3. Mission et scénario*), champ *Référence du dossier* : `SSE-2026-0007`. Posez-le n'importe où.
5. **Au Zeus**, le même module change d'affaire en cours de partie (vide = plus d'affaire).

Autre moyen, pour tout un serveur : réglage CBA **COMSPEC SSE › Réseau › Référence dossier SSE**.

**Sans référence**, Athena range chaque fiche dans la seule affaire ouverte s'il n'y en a qu'une, sinon dans une affaire du jour « Contrôles du jj/mm/aaaa (à trier) ».

> **Attention à la frappe.** Une référence inconnue (`SSE-2026-007` au lieu de `SSE-2026-0007`) crée une nouvelle affaire à ce nom.

---

## 6. Tutoriel 4 : une campagne sur plusieurs missions

Une campagne garde d'une mission à l'autre : les personnages récurrents (mêmes noms, mêmes empreintes), les organisations, ce que les joueurs ont découvert et le registre des scellés.

**L'identifiant, c'est vous qui l'inventez.** Un nom court, par exemple `OP-SABLE`. Il est mis en majuscules, les espaces et la ponctuation deviennent des tirets (`op sable` devient `OP-SABLE`).

1. **Mission 1** : module **Campagne SSE (charger ou créer)** (catégorie *3. Mission et scénario*).
   - *Identifiant* : `OP-SABLE`. *Nom* : « Opération Sable ».
   - *Type* : **Permanente** (tout est gardé), **Récurrente** (bootcamp : mêmes personnages, tout redevient inconnu à chaque drill), **Temporaire** (drill jetable, rien n'est gardé).
   - *Sauvegarde automatique* : toutes les 15 minutes.
   - *Sauvegarder à la fin de la mission* : coché.
2. Au premier lancement, la campagne est **créée** dans le profil du serveur.
3. **Personnages récurrents** : sur une personne, attributs **COMSPEC SSE — Biométrie** › *Identifiant biométrique (campagne)* : un identifiant fixe (`KARIM-01`). Elle aura la même identité et les mêmes empreintes dans toutes les missions.
4. **Sauvegarde à un moment précis** : module **Campagne SSE : sauvegarder**, synchronisé à un déclencheur (clic droit › Connecter › Synchroniser à).
5. **Mission 2** : même module, **même identifiant** `OP-SABLE` : la campagne est rechargée.
6. **Sur Athena** : au Zeus, *Campagne : envoyer à Athena* ; elle apparaît dans Bureau SSE › **28 Campagnes** (copie pour un drill, remise à zéro, partage avec d'autres communautés). *Campagne : récupérer depuis Athena* la ramène sur un serveur.

**Faire un drill sur le réseau de la vraie campagne** : une seconde mission avec *Type* Temporaire et *Copier la structure de* : `OP-SABLE`. Les joueurs s'entraînent sur les vrais personnages sans rien abîmer.

> Un serveur arrêté brutalement ne déclenche pas la sauvegarde de fin : gardez la sauvegarde automatique.

---

## 7. Tutoriel 5 : un ordinateur à pirater (DOMEX et DL-7)

Deux outils complémentaires :

- **DOMEX** (*Intelligence numérique*) : vous écrivez ce que contient le support ; Athena (Bureau SSE › Exploitation numérique) reçoit exactement ce texte quand l'accès est établi.
- **DL-7** (avec l'**ATAK natif**) : l'opérateur branche son téléphone ATAK au câble DL-7 et télécharge les fichiers, avec mot de passe, essais, forçage, alarme.

**Un ordinateur protégé par mot de passe (DL-7)**

1. Posez un ordinateur. Posez le module **DL-7 · Données à extraire au câble** (catégorie *4. Numérique*) et synchronisez-le avec l'ordinateur.
2. *Nom du support* : `PC-KESTREL`. *Écran de connexion* : Windows. *Mot de passe* : `sable2012`. *Indice* : « année de la cellule ». *Essais permis* : 3. *Trop d'essais* : verrouillage. *Forçage possible* : coché, 90 s.
3. *Fichiers* : un bloc par fichier, séparés par `---`. Première ligne `nom | type`, puis le contenu :
   ```
   ordre_mission.txt | texte
   Rendez-vous au puits ouest, jeudi 21h.
   ---
   cache.coord | coord
   045123
   ```
4. Cachez le mot de passe ailleurs : sur un document, un autre téléphone, ou dans les réponses d'un prisonnier.
5. Ajoutez des fichiers avec le module **DL-7 · Ajouter un fichier**, posé **après** le premier.

Sans module : double-clic sur l'objet › **COMSPEC SSE — Extraction DL-7** › *Extractible au câble DL-7*, contenu *Données SSE* (ce que le générateur a mis dans l'appareil) ou *Fichiers saisis*.

**Un téléphone dont le contenu remonte au Bureau SSE (DOMEX)**

1. Module **Intelligence numérique (DOMEX)** synchronisé avec le téléphone.
2. *Type de support* : téléphone. *Propriétaire apparent* : « ABU KARIM ». *Sécurité* : moyenne. *Durée d'accès* : 3 min.
3. *Paquet — type* : Message. *Paquet — texte* : « Livraison reportée, le changeur attend ». *Qualité* : Fragment (à croiser).
4. Au Zeus, **Fixer le palier d'accès** › *Accès établi* quand les joueurs ont fini : le paquet part sur Athena.

---

## 8. Les modules, un par un

Dans Eden : panneau de droite › **Systèmes (F5) › Modules › COMSPEC SSE**. Au Zeus : onglet Modules (ZEN). Règle générale : **en Eden, synchronisez le module avec ses cibles** (clic droit › Connecter › Synchroniser à) ; **au Zeus, posez-le sur la cible**, un formulaire s'ouvre.

### 1. Sites et objets

| Module | À quoi il sert | Champs importants |
|---|---|---|
| **Générer un profil SSE** | Donne identité, téléphone, documents et biométrie aux personnes ou objets ciblés. | *Profil* (insurgé, civil, militaire, chef / HVT, courrier, financier, technicien, renseignement, logistique, aléatoire), *Richesse* (légère à haute valeur), cases Identité réelle / Téléphone / Documents / Biométrie / Lier les cibles, *Données inutiles* (0 à 75 % de messages sans intérêt pour noyer les indices). |
| **Créer un site SSE complet** | Relie au même réseau tout ce qui est dans le rayon. Posez le décor d'abord, le module au centre. | *Rayon* (15 à 150 m), *Type de site*, *Richesse*, *Objets exploitables (max)*, Supports numériques, Documents, Liens réseau. |
| **Rendre exploitable (SSE)** | Marque un objet comme fouillable ; son contenu est généré au premier examen (léger pour le serveur). | *Nature de l'objet* (automatique, document, téléphone, ordinateur, radio, caisse, arme, véhicule), *Profil*, *Richesse*. |
| **Poser un objet de renseignement** | Crée l'objet à l'emplacement du module et le rend exploitable. | *Objet* (ordinateur, smartphone, téléphone basique, téléphone satellite, radio, dossier papier, photos, valise, caisse d'armes), *Profil*, *Richesse*, *Générer le contenu au lancement*. |
| **Appliquer un modèle SSE** | Applique un modèle (recette toute prête) aux cibles. | *Modèle intégré* (liste des 24 modèles, ou « Choisir au Zeus »), *Autre modèle (identifiant)* pour un modèle que vous avez enregistré. |
| **Site d'entraînement SSE** | Un site complet généré pour s'exercer sans préparation. | *Scénario* (aléatoire, cellule, contrebande, dépôt, PC, planque, atelier IED, nœud financier, cellule renseignement), *Rayon*. |

### 2. Personnes et liens

| Module | À quoi il sert | Champs importants |
|---|---|---|
| **Identité d'un sujet (requête SEEK)** | Ce que le terminal SEEK répondra sur ce sujet. | *Ce que la base doit répondre* (automatique, inconnu, signalé, recherché), *Nom*, *Prénom*, *Alias*, *Nationalité déclarée*, *Langue*, *Référence de dossier antérieur*, *Graine*. Vides = générés. |
| **Organisation (réseau, cellule, clan...)** | Déclare une organisation. Synchronisez ses membres (ou le chef d'un groupe pour tout le groupe) et les objets qui lui appartiennent. | *Nom*, *Nature*, *Rattachée à* (organisation mère), *Menace* (couleur au tableau), *Description*, *Rôles des personnes* (saisis sur chacune, ou générés en cellules), *Graine*, *Au départ, les joueurs savent*. |
| **Lier des entités SSE** | Trace des liens au tableau des liens. | *Relation* (associé, contact, propriétaire de, fait référence à), *Confiance* (0,3 à 0,9), *Disposition* (chaîne ou étoile). |

### 3. Mission et scénario

| Module | À quoi il sert | Champs importants |
|---|---|---|
| **Campagne SSE (charger ou créer)** | Charge ou crée la campagne au lancement. Un par mission. | Voir [tutoriel 4](#6-tutoriel-4--une-campagne-sur-plusieurs-missions). *Sauvegarde automatique* : jamais, 5, 10, 15 (conseillé), 30 ou 60 minutes. |
| **Campagne SSE : sauvegarder** | Sauvegarde quand un déclencheur synchronisé s'active. | *Action* (sauvegarder maintenant, ou fin de mission), *Motif* (texte du journal de campagne). |
| **Directeur de scénario (dataset / niveau)** | Applique un dataset et règle le niveau de révélation. | Voir [tutoriel 2](#4-tutoriel-2--jouer-le-dataset-falcon). *Dataset* est maintenant une liste. |
| **Doter en terminal SEEK** | Donne le SEEK II (sac, sinon gilet, sinon uniforme). | Unités synchronisées, ou *Rayon de dotation* (25 m) autour du module. |
| **Générer depuis un brief / scénario** | Génère un réseau autour du module à partir d'un texte libre ou d'un pack. | *Brief narratif* (« chef et courrier », « atelier IED »), *Pack scénario* (prioritaire sur le brief, dont *Dataset FALCON*), *Rayon*. |
| **Présentation des documents SSE** | Apparence des fiches en jeu. Un seul pour la mission. | *Modèle préfait* puis, seulement pour changer un texte : papier, bandeau, titres, pied de page, boutons. Voir ci-dessous. |
| **Référence de dossier SSE** | Affaire Athena où ranger les fiches. | Voir [tutoriel 3](#5-tutoriel-3--ranger-les-fiches-dans-une-affaire-athena). |

**Les modèles de présentation**

| Modèle | Papier | Bandeau | Titre fiche personne | Boutons |
|---|---|---|---|---|
| Standard — diffusion restreinte | propre | DIFFUSION RESTREINTE — EXPLOITATION TERRAIN | DOSSIER SSE | FEUILLE · TRANSMETTRE · FERMER |
| Terrain — feuille tachée | taché | SAISIE TERRAIN — EXPLOITATION | FICHE D'EXPLOITATION | DÉTAIL · ENVOYER · FERMER |
| Brouillon — papier froissé | froissé | BROUILLON — NE PAS DIFFUSER | NOTES D'EXPLOITATION | LIRE · TRANSMETTRE · FERMER |
| Archives — papier jauni | jauni | ARCHIVES — CONSULTATION CONTRÔLÉE | FICHE D'ARCHIVES | FEUILLE · TRANSMETTRE · FERMER |
| Bureau — papier impeccable | propre | DIFFUSION CONTRÔLÉE — BUREAU SSE | FICHE D'IDENTITÉ | FEUILLE · TRANSMETTRE · FERMER |

### 4. Numérique (DOMEX, DL-7)

| Module | À quoi il sert | Champs importants |
|---|---|---|
| **Intelligence numérique (DOMEX)** | Support numérique scénarisé : vous écrivez son contenu. | *Identifiant* (`PC-KESTREL-04`, vide = auto), *Type de support*, *Propriétaire apparent*, *Organisation*, *Réseau fictif*, *Sécurité*, *Profil de contenu*, *Durée d'accès* (1 à 15 min), *Accès distant*, puis un paquet : *type* (message, document, coordonnée, contact, fréquence), *texte*, *qualité* (complet, fragment, leurre), *entités* (`Nom | type`). |
| **Fixer le palier d'accès** | Change le palier d'un support. À *Accès établi*, les renseignements prévus partent vers Athena. | *Palier* : non identifié, découvert, accès en cours, accès établi, exploité. |
| **Poser un point carte** | Point sur la carte d'Athena, invisible des joueurs. | *Libellé*. |
| **DL-7 · Données à extraire au câble** (ATAK natif) | Fichiers téléchargeables au câble DL-7. | Voir [tutoriel 5](#7-tutoriel-5--un-ordinateur-à-pirater-domex-et-dl-7). *Support à créer* : si rien n'est synchronisé, crée un objet sur place. *Port* : USB-C, RJ45 (plus rapide), série (plus lent). *Alarme* : sirène, Zeus prévenu, position révélée aux ennemis à 400 m. |
| **DL-7 · Ajouter un fichier** (ATAK natif) | Ajoute un fichier à un support DL-7. | *Nom du fichier*, *Type* (texte, document, identifiants, coord, image, audio), *Contenu*. |

### 5. Outils du Zeus

Posés pendant la partie : **Effacer les données SSE** (aussi en Eden, synchronisé), **Réinitialiser un site SSE** (tout un rayon), **Panneau Zeus SSE**, **Consulter les données SSE**, **Connu des joueurs / vérité**, **Gestionnaire de site SSE**, **Compte rendu SSE (AAR) + graphe**, **Enregistrer comme modèle SSE**, **Lister les modèles SSE**, **Inspecteur SSE (debug)**, **Ajouter un renseignement** (DOMEX en cours de partie).

Modules ZEN supplémentaires : **COMSPEC SSE · Mission** (campagne : charger, sauvegarder, état, remise à zéro, fin, exporter, importer, envoyer à Athena, récupérer depuis Athena ; dossier actif), **COMSPEC SSE · Outils** (Tutoriel : manuel du Zeus, **Tutoriel : choisir un chapitre**, Tutoriel : poser un exercice ici).

### Les modèles intégrés

| Identifiant | Nom |
|---|---|
| `builtin_cellule_insurgee_irak` | Cellule insurgée — Irak |
| `builtin_chef_hvt` | Chef HVT |
| `builtin_reseau_courriers` | Réseau courriers |
| `builtin_financier` | Financier |
| `builtin_technicien_ied` | Technicien IED |
| `builtin_safehouse` | Safehouse urbain |
| `builtin_contrebande_sahel` | Contrebande frontière |
| `builtin_civil_bruit` | Civil non pertinent (bruit) |
| `builtin_drone_isr` | Cellule drone / ISR |
| `builtin_propagande` | Propagande / média |
| `builtin_iq_2010_2020_cache_armes` | Irak 2010-2020 — Cache d'armes |
| `builtin_iq_2010_2020_ied` | Irak 2010-2020 — Cellule IED |
| `builtin_iq_2010_2020_hvt` | Irak 2010-2020 — Chef de secteur |
| `builtin_iq_2010_2020_courrier` | Irak 2010-2020 — Courrier frontière |
| `builtin_iq_2010_2020_financier` | Irak 2010-2020 — Relais financier |
| `builtin_iq_2010_2020_safehouse` | Irak 2010-2020 — Planque urbaine |
| `builtin_ru_2020_2024_recon` | Russie 2020-2024 — Reconnaissance |
| `builtin_ru_2020_2024_logistics` | Russie 2020-2024 — Logistique |
| `builtin_ru_2020_2024_command` | Russie 2020-2024 — Poste de commandement |
| `builtin_ru_2020_2024_drone` | Russie 2020-2024 — Cellule drone |
| `builtin_ru_2020_2024_ew` | Russie 2020-2024 — Radio / EW |
| `builtin_ru_2020_2024_infoops` | Russie 2020-2024 — Info ops |
| `builtin_ru_2020_2024_courier` | Russie 2020-2024 — Courrier civil |
| `builtin_ru_2020_2024_civil` | Russie 2020-2024 — Civil couverture |

---

## 9. Les attributs Eden des personnes et des objets

Double-clic sur une unité ou un objet. Les catégories SSE :

| Catégorie | Pour | Ce qu'on y règle |
|---|---|---|
| **COMSPEC SSE — Renseignement** | Tout | Activer le renseignement SSE, nature de l'objet, profil, moment de la génération, richesse, identité (automatique, inventer un nom, forcer le nom Eden), ce que la base doit répondre, nom, prénom, alias, indice de confiance, graine, rôle déclaré, nationalité, langue, numéro connu, biométrie (générée ou aucune), notes Zeus, ID réseau (même nom = même réseau), modèle SSE, **dataset et rôle dans le dataset (listes)**, région. |
| **COMSPEC SSE — Documents** | Personnes | Jusqu'à 3 pièces écrites : intitulé, contenu, grille, mot de code. |
| **COMSPEC SSE — Téléphone** | Personnes | Numéro, modèle, contacts, messages (`Expéditeur : texte`), notes, lieux (`Libellé | grille`). |
| **COMSPEC SSE — Ordinateur** | Personnes | Nom de l'ordinateur, compte, fichiers, courrier, réseau, indice d'accès. |
| **COMSPEC SSE — Biométrie** | Personnes | Fichier biométrique (absent, enrôlé, watchlist 1 à 4), motif de signalement, faux papiers, vraie identité, **identifiant biométrique (campagne)** pour un personnage récurrent, parenté, doigts manquants, iris. Sur les objets : empreintes latentes de qui. |
| **COMSPEC SSE — Organisation** | Personnes, objets | Organisation, cellule, rôle (chef à sympathisant), nom de guerre, ce que les joueurs savent au départ ; sur un objet : rattaché à quelle organisation. |
| **Athena — Intelligence numérique** | Objets | Mêmes champs que le module DOMEX. |
| **COMSPEC SSE — Extraction DL-7** | Objets | Mêmes champs que le module DL-7, plus *Contenu* : données SSE générées, fichiers saisis, ou les deux. |

---

## 10. Le matériel SSE

« **Équiper le kit SSE** » (menu ACE personnel › COMSPEC SSE) donne le kit de base. Avec le réglage *Exiger le matériel SSE* décoché, aucun objet n'est demandé.

| Objet | Classe | Sert à | Dans le kit |
|---|---|---|---|
| **Terminal SEEK II** | `COMSPEC_SSE_SEEKII` | Ouvrir SEEK, iris, capture complète. Remplace aussi le kit d'empreintes, le kit ADN et l'appareil photo. Donné aussi par le module *Doter en terminal SEEK*. | oui |
| **Kit d'empreintes SSE** | `COMSPEC_SSE_FingerprintKit` | Empreintes, empreintes latentes sur objets et armes au sol. | oui |
| **Kit ADN SSE** | `COMSPEC_SSE_DNKit` | Prélèvement ADN ; le résultat revient du laboratoire après un délai (15 min par défaut). | oui |
| **Appareil photo SSE** | `COMSPEC_SSE_Camera` | Photographier, photo faciale. | oui |
| **Sachet de preuve SSE** | `COMSPEC_SSE_EvidenceBag` | Collecter, mettre sous scellé, collecter un support USB/SD, révéler une cache. **N'est pas consommé.** | oui |
| **Gants d'examen SSE** | `COMSPEC_SSE_Gloves` | Ambiance : aucun geste ne les exige. | oui |
| **Pochette documents SSE** | `COMSPEC_SSE_DocumentBag` | Ambiance. | oui |
| **Terminal SSE terrain** | `COMSPEC_SSE_Terminal` | Ambiance : le terminal SSE s'ouvre sans lui. | oui |
| **Sac de tête (capture)** | `COMSPEC_SSE_HeadBag` | Posé sur un prisonnier menotté, rendu, inconscient ou mort : il ne voit plus, son visage et ses iris ne peuvent plus être relevés. Rendu quand on le retire. | non |
| **Clé USB saisie**, **Carte mémoire saisie** | `COMSPEC_SSE_USB`, `COMSPEC_SSE_SDCard` | Apparaissent dans l'inventaire en collectant un support. | non |
| **Disque dur, ordinateur portable, téléphone, smartphone, téléphone satellite, carnet saisis** | `COMSPEC_SSE_HardDrive`, `_Laptop`, `_Phone`, `_Smartphone`, `_SatPhone`, `_Notebook` | Objets d'inventaire pour le roleplay et l'Arsenal ; aucune action ne les lit. | non |
| **Câble de données DL-7** (ATAK natif) | `COMSPEC_ATAK_DataCable` | Brancher l'ATAK sur un support. Obligatoire seulement si le réglage ATAK *Câble DL-7 obligatoire* est coché. | non |

Les objets d'autres mods sont acceptés en substitut quand le réglage *Accepter les items d'autres mods* est coché (par exemple un kit médical ACE pour le sachet).

---

## 11. Les actions des joueurs (menu ACE)

**Sur une personne** (touche Windows › SSE) : Inspecter (5 s), Photographier (3 s, appareil photo), Fouiller (5 s), Marquer comme exploité, Lire documents (6 s), **Ouvrir le terminal SSE (dossiers)**, Lire la plaque (si ACE), Approfondir l'exploitation, Chronologie SSE.
Sous-menu **Biométrie** : Ouvrir SEEK II, Empreintes (8 s), Iris (5 s), Photo faciale (3 s), ADN (12 s), Capture complète (25 s), Identifier (8 s).
Sous-menu **Exploitation numérique** : identifier l'appareil, contacts, messages, appels, photos, coordonnées, extraction complète, informations système, utilisateurs, documents, navigateur, messagerie, supports connectés, identifiants, collecter un support USB/SD.

**Sur un objet** : Examiner, Fouiller (15 s), Collecter (8 s, sachet), Lire documents, Exploiter radio (radios), Ouvrir le terminal SSE, Approfondir, Mettre sous scellé (sachet), Relever des empreintes latentes.

**Autres** : Mettre / retirer un sac de tête ; Remettre mes pièces scellées (sur un équipier) ; avec l'ATAK natif, Brancher / Débrancher l'ATAK (câble DL-7).

**Menu personnel** (Ctrl+Windows › COMSPEC SSE) : Journal SSE, Journal technique, Ouvrir le terminal SSE, Équiper le kit SSE, Tableau des liens, Registre des scellés, Pièces que je détiens, Campagne en cours, Guide SSE (manuel). Raccourci : Ctrl+Maj+S ouvre le terminal SEEK.

---

## 12. Les réglages CBA

Échap › Options › Réglages de l'addon › **COMSPEC SSE** :

- **Matériel** : exiger le matériel SSE, accepter les items d'autres mods, alias matériels additionnels.
- **Durées** : inspecter, fouilles, photo, empreintes, téléphone, ordinateur.
- **Génération** : probabilité de données inutiles, de fausse piste.
- **Réseau** : identifiant de mission, **référence dossier SSE**, identifiant carte, préférer l'extension COMSPEC.
- **Intel** : mode entraînement, niveau opérateur, triage et pivot automatiques.
- **Biométrie** et **Biométrie — population** : seuils, qualité, délai ADN, empreintes latentes, avis de recherche Athena, modèle du sac de tête, proportions d'enrôlés et de watchlist par camp.
- **Organisations et scellés** : rôles et natures en plus, noms de code, contacts révélés par exploitation, préfixes des pièces et des scellés.
- **Campagnes** : copier les sauvegardes dans Athena.
- **Compatibilité** : plaque ACE vers identité SSE, passerelle BII Identifi.

Côté ATAK natif, catégorie **COMSPEC › ATAK · Extraction de données** : câble DL-7 obligatoire, longueur du câble, vitesse des transferts.

---

## 13. Pièges et dépannage

| Symptôme | Cause | Solution |
|---|---|---|
| Le Directeur de scénario n'a donné aucun rôle | Personne dans le rayon, ou moins de personnes que de rôles | Agrandissez le rayon, ajoutez des personnes (FALCON niveau 1 : 4). |
| Un joueur est devenu ABU KARIM | Les joueurs comptent ; le plus proche devient le chef | Éloignez le module des joueurs. |
| Les fiches arrivent dans « Contrôles du ... (à trier) » | Pas de référence de dossier, ou plusieurs affaires ouvertes | Module Référence de dossier SSE. |
| Une affaire inconnue est apparue sur Athena | Faute de frappe dans la référence | Corrigez la référence ; supprimez l'affaire en trop. |
| La campagne ne se recharge pas | Identifiant différent d'une mission à l'autre, ou serveur différent | Même identifiant ; pour un autre serveur, passez par Athena (envoyer puis récupérer). |
| L'identifiant vu sur Athena ne marche pas | Athena affiche `CODE:CLÉ` | N'utilisez que `CLÉ`. |
| Des fichiers DL-7 ont disparu | Le module *Données à extraire* et la case *Extractible au câble DL-7* sur le même objet : le dernier remplace l'autre | Un seul des deux, puis *Ajouter un fichier* après. |
| Aucune action biométrique | Matériel exigé et absent | Équiper le kit SSE, ou décocher *Exiger le matériel SSE*. |
