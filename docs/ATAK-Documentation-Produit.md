# COMSPEC ATAK — Documentation produit

**Version** : 1.0  
**Public** : Utilisateurs, administrateurs, responsables technique  
**Style** : Présentation produit, configuration et technique opérationnelle (sans détail d’implémentation)

---

## 1. Présentation du produit

### 1.1 Qu’est-ce que COMSPEC ATAK ?

**COMSPEC ATAK** (ou **ATAK / Tacmap**) est le module de **carte tactique temps réel** et de **liaison terrain–commandement** de la plateforme COMSPEC. Il permet de visualiser sur un navigateur la situation sur le théâtre d’opérations (positions des unités, marqueurs, messages, appuis air, etc.) et de rester synchronisé avec les opérateurs en jeu (Arma 3) grâce au mod **COMSPEC Overwatch**.

En résumé :

- **Côté navigateur** : une **carte tactique** (Tacmap) affiche unités, marqueurs, tchat, pings, flux photos, demandes CAS (9-Line), désignateurs, rapports SIGINT et assets aériens.
- **Côté jeu** : le **mod Arma COMSPEC Overwatch** envoie la position des joueurs, les marqueurs, les photos (type CTAB) et d’autres renseignements vers le serveur, qui les redistribue à l’overlay.

L’ensemble forme un **système de commandement et contrôle (C2)** léger : le commandement suit la situation sur l’overlay ; les équipes en mission voient leurs actions reflétées en temps réel sur la carte.

### 1.2 Objectifs opérationnels

- **Situations tactiques partagées** : une seule vue carte pour tous les acteurs autorisés (même théâtre, même mission).
- **Liaison Arma ↔ site** : les positions et événements issus du jeu remontent automatiquement vers l’overlay après configuration du mod.
- **Coordination** : tchat, pings, photos intel, 9-Line CAS, codes laser, désignateurs et rapports SIGINT pour coordonner les appuis et le renseignement.
- **Multi-théâtres** : plusieurs cartes (ex. Altis, Tanoa) et contextes (serveurs / missions) peuvent être proposés selon la configuration de l’équipe.

### 1.3 Utilisateurs types

| Rôle | Usage principal |
|------|------------------|
| **Opérateur terrain** | Joue avec le mod Arma ; sa position et ses actions (marqueurs, intel) apparaissent sur la Tacmap. |
| **Commandement / overwatch** | Consulte la carte ATAK (ou Overwatch) pour suivre les unités, le tchat, les pings et les demandes CAS. |
| **JTAC / contrôleur aérien** | Crée des 9-Line, gère les codes laser et les cibles désignateur ; les pilotes déclarent leurs assets (Flight Manifest). |
| **Administrateur d’équipe** | Configure l’adresse du serveur C2, la carte par défaut, les identifiants mod, les instructions et le pack mod à télécharger. |

### 1.4 Fonctionnalités principales (côté overlay)

- **Carte tactique** : fond de carte type théâtre Arma (ex. Altis), avec coordonnées alignées sur le monde jeu. Zoom, pan, changement de carte ou de contexte (serveur / mission) selon configuration.
- **Unités (contacts)** : liste des contacts connectés avec indicatif ; affichage sur la carte (position en temps réel). Filtres « Live » / « All » et recherche par indicatif.
- **Cams / Intel photos** : flux des photos envoyées depuis le jeu (ex. captures type CTAB) ; consultation dans l’onglet dédié.
- **Tchat** : messagerie partagée sur le théâtre actif ; échange entre overwatch et terrain.
- **Pings** : alertes ou marqueurs rapides partagés (position + message) ; liste dans un onglet dédié.
- **JTAC** : création et suivi des demandes **9-Line CAS** (type, position, élévation, cible, marqueur, ami/ennemi, retrait, autres, remarques) ; gestion des **codes laser** ; liste des demandes et statut.
- **Air Support Assets** : liste des aéronefs déclarés par les pilotes (Flight Manifest depuis le menu Arma) ; statut pilote et liaison avec les 9-Line si l’équipe l’utilise.
- **Marqueurs et formes** : marqueurs tactiques sur la carte ; formes (zones, axes) selon les capacités déployées.
- **Désignateur** : position des cibles désignées au laser (JTAC) pour visualisation commandement.
- **SIGINT** : rapports de renseignement d’origine électromagnétique (zones, émissions) ; affichage selon configuration.
- **Heure Zulu** : affichage de l’heure UTC (Z) dans l’en-tête.
- **État de santé** : section dépliable pour vérifier la disponibilité des services (connexion, dernière activité Arma, nombre d’unités, erreurs éventuelles).

### 1.5 Mod Arma COMSPEC Overwatch

Le mod fournit la **liaison jeu → serveur** :

- **Connexion** : au chargement de la mission, le mod se connecte au serveur C2 (adresse configurée dans les paramètres CBA → COMSPEC Overwatch).
- **Position** : envoi périodique de la position du joueur pour affichage sur la Tacmap.
- **Marqueurs** : synchronisation des marqueurs (création, modification, suppression) entre le jeu et l’overlay.
- **Intel / photos** : envoi de captures (ex. type CTAB) vers l’overlay pour partage avec le commandement.
- **Rapports intel (Intel.Report)** : envoi de rapports structurés (contact infanterie, véhicule, blindé, défense AA) vers le C2. Disponible via le menu ACE (Self Actions → COMSPEC Overwatch) ou depuis n’importe quel script mission (voir ci‑dessous).
- **Autres** : selon version du mod et configuration (9-Line, désignateur, Flight Manifest, etc.).

**Appel des rapports intel depuis un script mission (JTAC / contact)** : pour déclencher un rapport depuis un script SQF sans passer par le menu ACE, récupérer l’identifiant mission puis appeler `sendIntel` avec le type `REPORT` :

```sqf
private _missionId = missionNamespace getVariable ["comspec_overwatch_mission_id", "DEFAULT_MISSION"];
[player, "REPORT", ["INFANTRY", getPosATL player, _missionId, name player]] call comspec_overwatch_connect_fnc_sendIntel;
```

Pour un contact observé à une position différente (ex. ennemi sous le réticule) :

```sqf
private _missionId = missionNamespace getVariable ["comspec_overwatch_mission_id", "DEFAULT_MISSION"];
private _enemyPos = getPosATL cursorObject;
[player, "REPORT", ["VEHICLE", _enemyPos, _missionId, name player]] call comspec_overwatch_connect_fnc_sendIntel;
```

Types de cible supportés : `INFANTRY`, `VEHICLE`, `ARMOR`, `AIR_DEFENSE`. L’identifiant mission est configurable dans les paramètres CBA (COMSPEC Overwatch → Mission ID).

**Prérequis** : Arma 3 à jour, **CBA A3** (Community Base Addons). Le mod est fourni en pack téléchargeable (ex. lien sur le tableau de bord ou depuis l’admin) et doit être extrait puis activé dans le launcher.

---

## 2. Configuration

### 2.1 Vue d’ensemble

La configuration ATAK est **par équipe** (tenant). Elle couvre :

- La **carte par défaut** affichée sur l’overlay ATAK.
- L’**URL de base du serveur C2** (optionnel ; si vide, le site courant est utilisé).
- Le **secret JWT** (optionnel) pour la signature des jetons d’accès.
- Les **informations serveur Arma** (adresse, port) affichées aux utilisateurs.
- Les **identifiants ou paramètres mod** (texte libre) à communiquer aux opérateurs pour configurer le mod dans Arma.
- Les **instructions équipe** (procédures, liens, rappels).

Seuls les **administrateurs** accèdent à l’écran **Configuration ATAK / Arma**. Les opérateurs voient uniquement les informations que l’admin a choisies (ex. dans la section « Configuration pour le jeu » sur la page ATAK).

### 2.2 Carte par défaut

- L’administrateur choisit la **carte de l’overlay** pour l’équipe (ex. Altis, Tanoa).
- Cette carte s’affiche par défaut à l’ouverture de la page ATAK ; l’utilisateur peut en changer si plusieurs cartes sont proposées.

### 2.3 URL de base et secret JWT

- **URL de base API ATAK** : en général, le C2 est servi par le même site (même origine). On ne renseigne une URL dédiée que si l’équipe utilise un domaine ou un port spécifique (ex. pour la DLL du mod Arma).
- Pour le mod Arma, on configure en pratique **l’URL du site** (ex. `https://votre-domaine.fr`) dans les paramètres du mod (Paramètres → Addons → COMSPEC Overwatch → Connexion), pas une URL de « nœud » séparée, lorsque tout passe par le site.
- **Secret JWT** : optionnel ; si renseigné, les jetons de cette équipe sont signés avec ce secret (sinon avec le secret global). À utiliser si l’équipe a besoin d’une clé dédiée.

### 2.4 Serveur Arma 3

- **Adresse du serveur** : hostname ou IP du serveur de jeu Arma 3 (affichée aux opérateurs pour information).
- **Port** : port du serveur (ex. 2302). Ces informations permettent à l’équipe d’identifier le bon serveur et de vérifier la cohérence avec le mod.

### 2.5 Identifiants / liaison mod Arma

- Champ **texte libre** (identifiants, clé, paramètres à coller dans le mod).
- Affiché aux opérateurs sur la page ATAK (section « Configuration pour le jeu ») pour qu’ils saisissent les mêmes valeurs dans Arma (Options → Jeu → Configurer les mods → COMSPEC Overwatch → Connexion).

### 2.6 Instructions équipe

- **Instructions** : texte libre pour procédures de connexion, liens utiles, rappels (ex. « Toujours vérifier l’indicatif Arma dans les préférences du compte »).
- Visible sur la page ATAK selon la mise en page (ex. dans la zone « Configuration pour le jeu » ou « Instructions »).

### 2.7 Mod ATAK (pack téléchargeable)

- L’administrateur peut **déposer une version du mod** (fichier .zip, ex. COMSPEC Overwatch) depuis **Admin → Mod ATAK (upload)**.
- Une fois le pack en place, un **lien de téléchargement** est proposé aux utilisateurs (tableau de bord, page ATAK ou assistant d’installation), pour qu’ils récupèrent toujours la version validée par l’équipe.

### 2.8 Préférences utilisateur (liaison compte ↔ jeu)

Pour que l’overlay affiche correctement l’indicatif et le lien avec le compte :

- **Indicatif** : renseigné dans le profil ou les préférences du compte.
- **Liaison Steam** : optionnel ; identifiant Steam si utilisé pour l’authentification ou la corrélation.
- **Indicatif Arma** : doit correspondre à l’indicatif utilisé en jeu pour que la liste des contacts et la carte associent la bonne identité.

Ces réglages se font dans **Mon compte** / **Préférences**, pas dans la configuration ATAK admin.

---

## 3. Utilisation opérationnelle

### 3.1 Accéder à la carte ATAK

- Depuis le **tableau de bord** : lien « ATAK / Tacmap ».
- Depuis le menu principal : lien **ATAK**.
- URL directe : `/atak` (après connexion).

L’utilisateur doit être **connecté** et, le cas échéant, rattaché à une **équipe** pour voir la carte et les données du théâtre correspondant.

### 3.2 Interface principale

- **En-tête** : logo COMSPEC Overwatch, heure Zulu, indicateur « Réseau actif » (ou perte de connexion), sélecteur de serveur/mission, sélecteur de carte, liens Overwatch / Dashboard, bouton **Paramètres** (données compte, liaison Steam/Arma, lien vers config jeu).
- **Panneau gauche** (onglets) :
  - **Cams** : flux des photos intel envoyées depuis Arma.
  - **Tchat** : messages partagés ; saisie et envoi de messages.
  - **Pings** : liste des pings avec position et message.
  - **JTAC** : bouton « Nouvelle 9-Line CAS », formulaire 9 lignes, liste des 9-Line, codes laser.
- **Carte** : zone centrale ; affichage des unités, marqueurs, formes, désignateur, etc. Interaction au clic (info, déplacement de vue).
- **Panneau droit** :
  - **Air Support Assets** : liste des aéronefs déclarés (Flight Manifest).
  - **Contacts (All Workspaces)** : liste des unités avec filtre et mode Live / All.

Les panneaux peuvent être **réduits** (boutons ◀ / ▶) pour agrandir la carte.

### 3.3 Configuration pour le jeu (affichée à l’utilisateur)

Section dépliable en bas de page :

- **URL du nœud** (si configurée) : à saisir dans le mod (Paramètres → Addons → COMSPEC Overwatch). Bouton **Copier** pour coller dans Arma.
- **Votre IP (visiteur)** : utile pour dépannage ou règles pare-feu.
- **Lien de téléchargement du mod** COMSPEC Overwatch (si l’admin a déposé un pack).
- **Serveur Arma** et **Identifiants / config mod** (si renseignés par l’admin).
- **Instructions** (si renseignées).
- Liens vers **Assistant Mod Arma** (installation, config, vérification) et **Guide complet — Tuto mod Arma**.

### 3.4 Assistant Mod Arma et tutoriel

- **Assistant** (`/atak/setup`) : étapes 1) Installation (prérequis, téléchargement, extraction, activation, vérification DLL), 2) Configuration (URL nœud, identifiants dans Arma), 3) Vérification (test connexion nœud, test en jeu).
- **Tutoriel** (`/atak/tuto`) : prérequis, téléchargement, installation, configuration (URL serveur, clé), connexion, fonctions disponibles (position, marqueurs, photos intel).

Ces pages aident les opérateurs à installer et configurer le mod sans entrer dans les écrans d’administration.

### 3.5 État de santé

Section dépliable **État de santé** (en bas de page) :

- **Noeuds API** : URL utilisée et statut (OK / Erreur / Timeout).
- **Connexion** : état de la connexion (ex. « Connecté », « API PHP (polling) »).
- **Base de données** : disponibilité du stockage (OK / Erreur).
- **Mod / DLL** : dernière activité enregistrée depuis Arma (temps écoulé ou « Jamais »).
- **Liaisons actives** : nombre d’unités connectées et liste des indicatifs (aperçu).
- **Erreurs** : dernières erreurs tchat ou pings si pertinent.

Bouton **Actualiser** pour rafraîchir les indicateurs. Utile pour vérifier que le serveur et la liaison Arma répondent correctement.

---

## 4. Aspects techniques (opérationnels)

### 4.1 Architecture générale

- **Site web (navigateur)** : l’overlay ATAK est une page web qui affiche la carte et les panneaux (unités, tchat, pings, JTAC, etc.). Les données sont **récupérées périodiquement** depuis le serveur (polling) ; il n’est pas nécessaire d’ouvrir une connexion temps réel persistante (type WebSocket) pour le fonctionnement de base.
- **Serveur** : le même site qui héberge COMSPEC expose les services C2 (réception des positions, marqueurs, tchat, pings, 9-Line, photos intel, désignateur, SIGINT, air assets, etc.). Les informations sont **stockées et servies par théâtre / mission** (contexte par carte ou par « workspace »).
- **Mod Arma** : la DLL du mod communique avec le serveur (adresse configurée = URL du site ou URL dédiée). Elle envoie positions, marqueurs, intel ; selon les versions, elle peut aussi interroger le serveur pour recevoir des mises à jour (marqueurs, ordres).

Ainsi, **terrain (Arma) et commandement (navigateur)** partagent la même source de vérité via le serveur.

### 4.2 Données et synchronisation

- **Unités** : positions mises à jour régulièrement par le mod ; liste et carte mises à jour à intervalle court (quelques secondes).
- **Marqueurs** : créés ou modifiés en jeu ou depuis l’overlay ; synchronisation dans les deux sens selon les capacités déployées.
- **Tchat et pings** : saisis côté overlay ou, pour partie, issus du jeu ; visibles par tous les utilisateurs du même théâtre.
- **9-Line, codes laser, désignateur, SIGINT, air assets** : créés ou mis à jour côté overlay ou via le mod ; utilisés pour la coordination CAS et le renseignement.

Les données sont **isolées par équipe** (tenant) et par **contexte de carte / mission** pour éviter les mélanges entre théâtres.

### 4.3 Cartes et coordonnées

- Les **cartes tactiques** utilisent le même système de coordonnées que le monde Arma (mètres, repère cartésien du théâtre). Les positions reçues du jeu sont affichées **sans conversion** sur la carte (alignement Altis, Tanoa, etc.).
- Plusieurs **fonds de carte** peuvent être proposés (Altis, autres théâtres) ; la **carte par défaut** est définie par l’administrateur pour l’équipe.
- Le **changement de carte** ou de **workspace** (serveur / mission) permet de basculer le contexte et d’afficher les bonnes unités et marqueurs pour ce contexte.

### 4.4 Sécurité et accès

- Accès à la page ATAK **réservé aux utilisateurs connectés**.
- Les **jetons d’accès** (JWT) peuvent être signés avec un secret global ou un **secret propre à l’équipe** (paramètre admin).
- La **configuration ATAK** (carte par défaut, URL, secret, serveur Arma, identifiants mod, instructions) est **réservée aux administrateurs** ; les opérateurs ne voient que les éléments publiés (section « Configuration pour le jeu », instructions, lien de téléchargement du mod).

### 4.5 Sous-domaine et déploiement

- Si l’équipe utilise un **sous-domaine dédié** (ex. `atak.domaine.fr`) pour exposer le C2 ou un service séparé, la création du sous-domaine se fait **au niveau DNS / hébergement** (enregistrement A ou CNAME pointant vers le serveur). Ce n’est pas géré par l’application elle-même.
- Le **reverse proxy** (Nginx, Apache, etc.) peut être configuré pour faire pointer ce sous-domaine vers le même site ou vers un port dédié, selon l’architecture retenue.

### 4.6 Vérifications en cas de problème

- **État de santé** : vérifier que « Noeuds API » et « Base de données » sont OK, que « Mod / DLL » montre une activité récente si des joueurs sont en mission, et que les « Liaisons actives » correspondent aux connexions attendues.
- **Opérateur non visible** : vérifier que le mod est activé, que l’URL (et la clé) sont correctes dans Arma, que l’**indicatif Arma** est renseigné dans les préférences du compte, et que le pare-feu n’bloque pas la liaison.
- **Carte vide** : vérifier le choix de carte et de **workspace** (serveur / mission) ; s’assurer que des unités sont bien connectées pour ce contexte.

---

## 5. Résumé des écrans et chemins

| Écran | Chemin / accès |
|-------|-----------------|
| Carte ATAK (overlay) | `/atak` (après connexion) |
| Assistant Mod Arma | `/atak/setup` |
| Tutoriel mod Arma | `/atak/tuto` |
| Téléchargement du mod | `/atak/mod/download` (si pack déposé par l’admin) |
| Configuration ATAK / Arma (admin) | `/admin/atak-config` |
| Upload / gestion du mod (admin) | `/admin/atak-mod` |
| Overwatch (C2) | Lien depuis ATAK ou menu « Overwatch » |
| Préférences compte (indicatif, Steam, Arma) | Compte → Préférences |

---

## 6. Évolutions prévues (référence produit)

Les évolutions envisagées pour le C2 COMSPEC (BFT enrichi, désignateur temps réel, synchronisation bi-directionnelle marqueurs, gestion des photos CTAB par fichier, queue en cas de perte de réseau, intégration radio TFAR/ACRE, SIGINT avec zones d’incertitude) sont décrites dans un document de roadmap interne. Elles restent sans impact sur la présente documentation produit tant qu’elles ne sont pas livrées en standard.

---

*Document rédigé pour COMSPEC MILSIM — Usage interne et présentation produit.*
