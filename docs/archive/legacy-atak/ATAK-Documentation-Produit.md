# COMSPEC ATAK ÔÇö Documentation produit

**Version** : 1.0  
**Public** : Utilisateurs, administrateurs, responsables technique  
**Style** : Pr├®sentation produit, configuration et technique op├®rationnelle (sans d├®tail dÔÇÖimpl├®mentation)

---

## 1. Pr├®sentation du produit

### 1.1 QuÔÇÖest-ce que COMSPEC ATAK ?

**COMSPEC ATAK** (ou **ATAK / Tacmap**) est le module de **carte tactique temps r├®el** et de **liaison terrainÔÇôcommandement** de la plateforme COMSPEC. Il permet de visualiser sur un navigateur la situation sur le th├®├ótre dÔÇÖop├®rations (positions des unit├®s, marqueurs, messages, appuis air, etc.) et de rester synchronis├® avec les op├®rateurs en jeu (Arma 3) gr├óce au mod **COMSPEC Overwatch**.

En r├®sum├® :

- **C├┤t├® navigateur** : une **carte tactique** (Tacmap) affiche unit├®s, marqueurs, tchat, pings, flux photos, demandes CAS (9-Line), d├®signateurs, rapports SIGINT et assets a├®riens.
- **C├┤t├® jeu** : le **mod Arma COMSPEC Overwatch** envoie la position des joueurs, les marqueurs, les photos (type CTAB) et dÔÇÖautres renseignements vers le serveur, qui les redistribue ├á lÔÇÖoverlay.

LÔÇÖensemble forme un **syst├¿me de commandement et contr├┤le (C2)** l├®ger : le commandement suit la situation sur lÔÇÖoverlay ; les ├®quipes en mission voient leurs actions refl├®t├®es en temps r├®el sur la carte.

### 1.2 Objectifs op├®rationnels

- **Situations tactiques partag├®es** : une seule vue carte pour tous les acteurs autoris├®s (m├¬me th├®├ótre, m├¬me mission).
- **Liaison Arma Ôåö site** : les positions et ├®v├®nements issus du jeu remontent automatiquement vers lÔÇÖoverlay apr├¿s configuration du mod.
- **Coordination** : tchat, pings, photos intel, 9-Line CAS, codes laser, d├®signateurs et rapports SIGINT pour coordonner les appuis et le renseignement.
- **Multi-th├®├ótres** : plusieurs cartes (ex. Altis, Tanoa) et contextes (serveurs / missions) peuvent ├¬tre propos├®s selon la configuration de lÔÇÖ├®quipe.

### 1.3 Utilisateurs types

| R├┤le | Usage principal |
|------|------------------|
| **Op├®rateur terrain** | Joue avec le mod Arma ; sa position et ses actions (marqueurs, intel) apparaissent sur la Tacmap. |
| **Commandement / overwatch** | Consulte la carte ATAK (ou Overwatch) pour suivre les unit├®s, le tchat, les pings et les demandes CAS. |
| **JTAC / contr├┤leur a├®rien** | Cr├®e des 9-Line, g├¿re les codes laser et les cibles d├®signateur ; les pilotes d├®clarent leurs assets (Flight Manifest). |
| **Administrateur dÔÇÖ├®quipe** | Configure lÔÇÖadresse du serveur C2, la carte par d├®faut, les identifiants mod, les instructions et le pack mod ├á t├®l├®charger. |

### 1.4 Fonctionnalit├®s principales (c├┤t├® overlay)

- **Carte tactique** : fond de carte type th├®├ótre Arma (ex. Altis), avec coordonn├®es align├®es sur le monde jeu. Zoom, pan, changement de carte ou de contexte (serveur / mission) selon configuration.
- **Unit├®s (contacts)** : liste des contacts connect├®s avec indicatif ; affichage sur la carte (position en temps r├®el). Filtres ┬½ Live ┬╗ / ┬½ All ┬╗ et recherche par indicatif.
- **Cams / Intel photos** : flux des photos envoy├®es depuis le jeu (ex. captures type CTAB) ; consultation dans lÔÇÖonglet d├®di├®.
- **Tchat** : messagerie partag├®e sur le th├®├ótre actif ; ├®change entre overwatch et terrain.
- **Pings** : alertes ou marqueurs rapides partag├®s (position + message) ; liste dans un onglet d├®di├®.
- **JTAC** : cr├®ation et suivi des demandes **9-Line CAS** (type, position, ├®l├®vation, cible, marqueur, ami/ennemi, retrait, autres, remarques) ; gestion des **codes laser** ; liste des demandes et statut.
- **Air Support Assets** : liste des a├®ronefs d├®clar├®s par les pilotes (Flight Manifest depuis le menu Arma) ; statut pilote et liaison avec les 9-Line si lÔÇÖ├®quipe lÔÇÖutilise.
- **Marqueurs et formes** : marqueurs tactiques sur la carte ; formes (zones, axes) selon les capacit├®s d├®ploy├®es.
- **D├®signateur** : position des cibles d├®sign├®es au laser (JTAC) pour visualisation commandement.
- **SIGINT** : rapports de renseignement dÔÇÖorigine ├®lectromagn├®tique (zones, ├®missions) ; affichage selon configuration.
- **Heure Zulu** : affichage de lÔÇÖheure UTC (Z) dans lÔÇÖen-t├¬te.
- **├ëtat de sant├®** : section d├®pliable pour v├®rifier la disponibilit├® des services (connexion, derni├¿re activit├® Arma, nombre dÔÇÖunit├®s, erreurs ├®ventuelles).

### 1.5 Mod Arma COMSPEC Overwatch

Le mod fournit la **liaison jeu ÔåÆ serveur** :

- **Connexion** : au chargement de la mission, le mod se connecte au serveur C2 (adresse configur├®e dans les param├¿tres CBA ÔåÆ COMSPEC Overwatch).
- **Position** : envoi p├®riodique de la position du joueur pour affichage sur la Tacmap.
- **Marqueurs** : synchronisation des marqueurs (cr├®ation, modification, suppression) entre le jeu et lÔÇÖoverlay.
- **Intel / photos** : envoi de captures (ex. type CTAB) vers lÔÇÖoverlay pour partage avec le commandement.
- **Rapports intel (Intel.Report)** : envoi de rapports structur├®s (contact infanterie, v├®hicule, blind├®, d├®fense AA) vers le C2. Disponible via le menu ACE (Self Actions ÔåÆ COMSPEC Overwatch) ou depuis nÔÇÖimporte quel script mission (voir ciÔÇædessous).
- **Autres** : selon version du mod et configuration (9-Line, d├®signateur, Flight Manifest, etc.).

**Appel des rapports intel depuis un script mission (JTAC / contact)** : pour d├®clencher un rapport depuis un script SQF sans passer par le menu ACE, r├®cup├®rer lÔÇÖidentifiant mission puis appeler `sendIntel` avec le type `REPORT` :

```sqf
private _missionId = missionNamespace getVariable ["comspec_overwatch_mission_id", "DEFAULT_MISSION"];
[player, "REPORT", ["INFANTRY", getPosATL player, _missionId, name player]] call comspec_overwatch_connect_fnc_sendIntel;
```

Pour un contact observ├® ├á une position diff├®rente (ex. ennemi sous le r├®ticule) :

```sqf
private _missionId = missionNamespace getVariable ["comspec_overwatch_mission_id", "DEFAULT_MISSION"];
private _enemyPos = getPosATL cursorObject;
[player, "REPORT", ["VEHICLE", _enemyPos, _missionId, name player]] call comspec_overwatch_connect_fnc_sendIntel;
```

Types de cible support├®s : `INFANTRY`, `VEHICLE`, `ARMOR`, `AIR_DEFENSE`. LÔÇÖidentifiant mission est configurable dans les param├¿tres CBA (COMSPEC Overwatch ÔåÆ Mission ID).

**Pr├®requis** : Arma 3 ├á jour, **CBA A3** (Community Base Addons). Le mod est fourni en pack t├®l├®chargeable (ex. lien sur le tableau de bord ou depuis lÔÇÖadmin) et doit ├¬tre extrait puis activ├® dans le launcher.

---

## 2. Configuration

### 2.1 Vue dÔÇÖensemble

La configuration ATAK est **par ├®quipe** (tenant). Elle couvre :

- La **carte par d├®faut** affich├®e sur lÔÇÖoverlay ATAK.
- LÔÇÖ**URL de base du serveur C2** (optionnel ; si vide, le site courant est utilis├®).
- Le **secret JWT** (optionnel) pour la signature des jetons dÔÇÖacc├¿s.
- Les **informations serveur Arma** (adresse, port) affich├®es aux utilisateurs.
- Les **identifiants ou param├¿tres mod** (texte libre) ├á communiquer aux op├®rateurs pour configurer le mod dans Arma.
- Les **instructions ├®quipe** (proc├®dures, liens, rappels).

Seuls les **administrateurs** acc├¿dent ├á lÔÇÖ├®cran **Configuration ATAK / Arma**. Les op├®rateurs voient uniquement les informations que lÔÇÖadmin a choisies (ex. dans la section ┬½ Configuration pour le jeu ┬╗ sur la page ATAK).

### 2.2 Carte par d├®faut

- LÔÇÖadministrateur choisit la **carte de lÔÇÖoverlay** pour lÔÇÖ├®quipe (ex. Altis, Tanoa).
- Cette carte sÔÇÖaffiche par d├®faut ├á lÔÇÖouverture de la page ATAK ; lÔÇÖutilisateur peut en changer si plusieurs cartes sont propos├®es.

### 2.3 URL de base et secret JWT

- **URL de base API ATAK** : en g├®n├®ral, le C2 est servi par le m├¬me site (m├¬me origine). On ne renseigne une URL d├®di├®e que si lÔÇÖ├®quipe utilise un domaine ou un port sp├®cifique (ex. pour la DLL du mod Arma).
- Pour le mod Arma, on configure en pratique **lÔÇÖURL du site** (ex. `https://votre-domaine.fr`) dans les param├¿tres du mod (Param├¿tres ÔåÆ Addons ÔåÆ COMSPEC Overwatch ÔåÆ Connexion), pas une URL de ┬½ n┼ôud ┬╗ s├®par├®e, lorsque tout passe par le site.
- **Secret JWT** : optionnel ; si renseign├®, les jetons de cette ├®quipe sont sign├®s avec ce secret (sinon avec le secret global). ├Ç utiliser si lÔÇÖ├®quipe a besoin dÔÇÖune cl├® d├®di├®e.

### 2.4 Serveur Arma 3

- **Adresse du serveur** : hostname ou IP du serveur de jeu Arma 3 (affich├®e aux op├®rateurs pour information).
- **Port** : port du serveur (ex. 2302). Ces informations permettent ├á lÔÇÖ├®quipe dÔÇÖidentifier le bon serveur et de v├®rifier la coh├®rence avec le mod.

### 2.5 Identifiants / liaison mod Arma

- Champ **texte libre** (identifiants, cl├®, param├¿tres ├á coller dans le mod).
- Affich├® aux op├®rateurs sur la page ATAK (section ┬½ Configuration pour le jeu ┬╗) pour quÔÇÖils saisissent les m├¬mes valeurs dans Arma (Options ÔåÆ Jeu ÔåÆ Configurer les mods ÔåÆ COMSPEC Overwatch ÔåÆ Connexion).

### 2.6 Instructions ├®quipe

- **Instructions** : texte libre pour proc├®dures de connexion, liens utiles, rappels (ex. ┬½ Toujours v├®rifier lÔÇÖindicatif Arma dans les pr├®f├®rences du compte ┬╗).
- Visible sur la page ATAK selon la mise en page (ex. dans la zone ┬½ Configuration pour le jeu ┬╗ ou ┬½ Instructions ┬╗).

### 2.7 Mod ATAK (pack t├®l├®chargeable)

- LÔÇÖadministrateur peut **d├®poser une version du mod** (fichier .zip, ex. COMSPEC Overwatch) depuis **Admin ÔåÆ Mod ATAK (upload)**.
- Une fois le pack en place, un **lien de t├®l├®chargement** est propos├® aux utilisateurs (tableau de bord, page ATAK ou assistant dÔÇÖinstallation), pour quÔÇÖils r├®cup├¿rent toujours la version valid├®e par lÔÇÖ├®quipe.

### 2.8 Pr├®f├®rences utilisateur (liaison compte Ôåö jeu)

Pour que lÔÇÖoverlay affiche correctement lÔÇÖindicatif et le lien avec le compte :

- **Indicatif** : renseign├® dans le profil ou les pr├®f├®rences du compte.
- **Liaison Steam** : optionnel ; identifiant Steam si utilis├® pour lÔÇÖauthentification ou la corr├®lation.
- **Indicatif Arma** : doit correspondre ├á lÔÇÖindicatif utilis├® en jeu pour que la liste des contacts et la carte associent la bonne identit├®.

Ces r├®glages se font dans **Mon compte** / **Pr├®f├®rences**, pas dans la configuration ATAK admin.

---

## 3. Utilisation op├®rationnelle

### 3.1 Acc├®der ├á la carte ATAK

- Depuis le **tableau de bord** : lien ┬½ ATAK / Tacmap ┬╗.
- Depuis le menu principal : lien **ATAK**.
- URL directe : `/atak` (apr├¿s connexion).

LÔÇÖutilisateur doit ├¬tre **connect├®** et, le cas ├®ch├®ant, rattach├® ├á une **├®quipe** pour voir la carte et les donn├®es du th├®├ótre correspondant.

### 3.2 Interface principale

- **En-t├¬te** : logo COMSPEC Overwatch, heure Zulu, indicateur ┬½ R├®seau actif ┬╗ (ou perte de connexion), s├®lecteur de serveur/mission, s├®lecteur de carte, liens Overwatch / Dashboard, bouton **Param├¿tres** (donn├®es compte, liaison Steam/Arma, lien vers config jeu).
- **Panneau gauche** (onglets) :
  - **Cams** : flux des photos intel envoy├®es depuis Arma.
  - **Tchat** : messages partag├®s ; saisie et envoi de messages.
  - **Pings** : liste des pings avec position et message.
  - **JTAC** : bouton ┬½ Nouvelle 9-Line CAS ┬╗, formulaire 9 lignes, liste des 9-Line, codes laser.
- **Carte** : zone centrale ; affichage des unit├®s, marqueurs, formes, d├®signateur, etc. Interaction au clic (info, d├®placement de vue).
- **Panneau droit** :
  - **Air Support Assets** : liste des a├®ronefs d├®clar├®s (Flight Manifest).
  - **Contacts (All Workspaces)** : liste des unit├®s avec filtre et mode Live / All.

Les panneaux peuvent ├¬tre **r├®duits** (boutons ÔùÇ / ÔûÂ) pour agrandir la carte.

### 3.3 Configuration pour le jeu (affich├®e ├á lÔÇÖutilisateur)

Section d├®pliable en bas de page :

- **URL du n┼ôud** (si configur├®e) : ├á saisir dans le mod (Param├¿tres ÔåÆ Addons ÔåÆ COMSPEC Overwatch). Bouton **Copier** pour coller dans Arma.
- **Votre IP (visiteur)** : utile pour d├®pannage ou r├¿gles pare-feu.
- **Lien de t├®l├®chargement du mod** COMSPEC Overwatch (si lÔÇÖadmin a d├®pos├® un pack).
- **Serveur Arma** et **Identifiants / config mod** (si renseign├®s par lÔÇÖadmin).
- **Instructions** (si renseign├®es).
- Liens vers **Assistant Mod Arma** (installation, config, v├®rification) et **Guide complet ÔÇö Tuto mod Arma**.

### 3.4 Assistant Mod Arma et tutoriel

- **Assistant** (`/atak/setup`) : ├®tapes 1) Installation (pr├®requis, t├®l├®chargement, extraction, activation, v├®rification DLL), 2) Configuration (URL n┼ôud, identifiants dans Arma), 3) V├®rification (test connexion n┼ôud, test en jeu).
- **Tutoriel** (`/atak/tuto`) : pr├®requis, t├®l├®chargement, installation, configuration (URL serveur, cl├®), connexion, fonctions disponibles (position, marqueurs, photos intel).

Ces pages aident les op├®rateurs ├á installer et configurer le mod sans entrer dans les ├®crans dÔÇÖadministration.

### 3.5 ├ëtat de sant├®

Section d├®pliable **├ëtat de sant├®** (en bas de page) :

- **Noeuds API** : URL utilis├®e et statut (OK / Erreur / Timeout).
- **Connexion** : ├®tat de la connexion (ex. ┬½ Connect├® ┬╗, ┬½ API PHP (polling) ┬╗).
- **Base de donn├®es** : disponibilit├® du stockage (OK / Erreur).
- **Mod / DLL** : derni├¿re activit├® enregistr├®e depuis Arma (temps ├®coul├® ou ┬½ Jamais ┬╗).
- **Liaisons actives** : nombre dÔÇÖunit├®s connect├®es et liste des indicatifs (aper├ºu).
- **Erreurs** : derni├¿res erreurs tchat ou pings si pertinent.

Bouton **Actualiser** pour rafra├«chir les indicateurs. Utile pour v├®rifier que le serveur et la liaison Arma r├®pondent correctement.

---

## 4. Aspects techniques (op├®rationnels)

### 4.1 Architecture g├®n├®rale

- **Site web (navigateur)** : lÔÇÖoverlay ATAK est une page web qui affiche la carte et les panneaux (unit├®s, tchat, pings, JTAC, etc.). Les donn├®es sont **r├®cup├®r├®es p├®riodiquement** depuis le serveur (polling) ; il nÔÇÖest pas n├®cessaire dÔÇÖouvrir une connexion temps r├®el persistante (type WebSocket) pour le fonctionnement de base.
- **Serveur** : le m├¬me site qui h├®berge COMSPEC expose les services C2 (r├®ception des positions, marqueurs, tchat, pings, 9-Line, photos intel, d├®signateur, SIGINT, air assets, etc.). Les informations sont **stock├®es et servies par th├®├ótre / mission** (contexte par carte ou par ┬½ workspace ┬╗).
- **Mod Arma** : la DLL du mod communique avec le serveur (adresse configur├®e = URL du site ou URL d├®di├®e). Elle envoie positions, marqueurs, intel ; selon les versions, elle peut aussi interroger le serveur pour recevoir des mises ├á jour (marqueurs, ordres).

Ainsi, **terrain (Arma) et commandement (navigateur)** partagent la m├¬me source de v├®rit├® via le serveur.

### 4.2 Donn├®es et synchronisation

- **Unit├®s** : positions mises ├á jour r├®guli├¿rement par le mod ; liste et carte mises ├á jour ├á intervalle court (quelques secondes).
- **Marqueurs** : cr├®├®s ou modifi├®s en jeu ou depuis lÔÇÖoverlay ; synchronisation dans les deux sens selon les capacit├®s d├®ploy├®es.
- **Tchat et pings** : saisis c├┤t├® overlay ou, pour partie, issus du jeu ; visibles par tous les utilisateurs du m├¬me th├®├ótre.
- **9-Line, codes laser, d├®signateur, SIGINT, air assets** : cr├®├®s ou mis ├á jour c├┤t├® overlay ou via le mod ; utilis├®s pour la coordination CAS et le renseignement.

Les donn├®es sont **isol├®es par ├®quipe** (tenant) et par **contexte de carte / mission** pour ├®viter les m├®langes entre th├®├ótres.

### 4.3 Cartes et coordonn├®es

- Les **cartes tactiques** utilisent le m├¬me syst├¿me de coordonn├®es que le monde Arma (m├¿tres, rep├¿re cart├®sien du th├®├ótre). Les positions re├ºues du jeu sont affich├®es **sans conversion** sur la carte (alignement Altis, Tanoa, etc.).
- Plusieurs **fonds de carte** peuvent ├¬tre propos├®s (Altis, autres th├®├ótres) ; la **carte par d├®faut** est d├®finie par lÔÇÖadministrateur pour lÔÇÖ├®quipe.
- Le **changement de carte** ou de **workspace** (serveur / mission) permet de basculer le contexte et dÔÇÖafficher les bonnes unit├®s et marqueurs pour ce contexte.

### 4.4 S├®curit├® et acc├¿s

- Acc├¿s ├á la page ATAK **r├®serv├® aux utilisateurs connect├®s**.
- Les **jetons dÔÇÖacc├¿s** (JWT) peuvent ├¬tre sign├®s avec un secret global ou un **secret propre ├á lÔÇÖ├®quipe** (param├¿tre admin).
- La **configuration ATAK** (carte par d├®faut, URL, secret, serveur Arma, identifiants mod, instructions) est **r├®serv├®e aux administrateurs** ; les op├®rateurs ne voient que les ├®l├®ments publi├®s (section ┬½ Configuration pour le jeu ┬╗, instructions, lien de t├®l├®chargement du mod).

### 4.5 Sous-domaine et d├®ploiement

- Si lÔÇÖ├®quipe utilise un **sous-domaine d├®di├®** (ex. `atak.domaine.fr`) pour exposer le C2 ou un service s├®par├®, la cr├®ation du sous-domaine se fait **au niveau DNS / h├®bergement** (enregistrement A ou CNAME pointant vers le serveur). Ce nÔÇÖest pas g├®r├® par lÔÇÖapplication elle-m├¬me.
- Le **reverse proxy** (Nginx, Apache, etc.) peut ├¬tre configur├® pour faire pointer ce sous-domaine vers le m├¬me site ou vers un port d├®di├®, selon lÔÇÖarchitecture retenue.

### 4.6 V├®rifications en cas de probl├¿me

- **├ëtat de sant├®** : v├®rifier que ┬½ Noeuds API ┬╗ et ┬½ Base de donn├®es ┬╗ sont OK, que ┬½ Mod / DLL ┬╗ montre une activit├® r├®cente si des joueurs sont en mission, et que les ┬½ Liaisons actives ┬╗ correspondent aux connexions attendues.
- **Op├®rateur non visible** : v├®rifier que le mod est activ├®, que lÔÇÖURL (et la cl├®) sont correctes dans Arma, que lÔÇÖ**indicatif Arma** est renseign├® dans les pr├®f├®rences du compte, et que le pare-feu nÔÇÖbloque pas la liaison.
- **Carte vide** : v├®rifier le choix de carte et de **workspace** (serveur / mission) ; sÔÇÖassurer que des unit├®s sont bien connect├®es pour ce contexte.

---

## 5. R├®sum├® des ├®crans et chemins

| ├ëcran | Chemin / acc├¿s |
|-------|-----------------|
| Carte ATAK (overlay) | `/atak` (apr├¿s connexion) |
| Assistant Mod Arma | `/atak/setup` |
| Tutoriel mod Arma | `/atak/tuto` |
| T├®l├®chargement du mod | `/atak/mod/download` (si pack d├®pos├® par lÔÇÖadmin) |
| Configuration ATAK / Arma (admin) | `/admin/atak-config` |
| Upload / gestion du mod (admin) | `/admin/atak-mod` |
| Overwatch (C2) | Lien depuis ATAK ou menu ┬½ Overwatch ┬╗ |
| Pr├®f├®rences compte (indicatif, Steam, Arma) | Compte ÔåÆ Pr├®f├®rences |

---

## 6. ├ëvolutions pr├®vues (r├®f├®rence produit)

Les ├®volutions envisag├®es pour le C2 COMSPEC (BFT enrichi, d├®signateur temps r├®el, synchronisation bi-directionnelle marqueurs, gestion des photos CTAB par fichier, queue en cas de perte de r├®seau, int├®gration radio TFAR/ACRE, SIGINT avec zones dÔÇÖincertitude) sont d├®crites dans un document de roadmap interne. Elles restent sans impact sur la pr├®sente documentation produit tant quÔÇÖelles ne sont pas livr├®es en standard.

---

*Document r├®dig├® pour COMSPEC MILSIM ÔÇö Usage interne et pr├®sentation produit.*
