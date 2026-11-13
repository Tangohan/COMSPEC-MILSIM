# Contrôle d'identité (COMSPEC SSE 1.3.1)

Papiers d'identité **fictifs** (pays imaginaires, mention SIMULATION sur chaque document) et contrôle sur le terrain,
par le menu ACE et l'app **Contrôle ID** du téléphone ATAK. Fonctionne entièrement hors ligne ; avec COMSPEC Link 2.0.118
ou plus (pack ATAK 3.0.0), les documents d'Athena sont chargés et la base en ligne complète le contrôle.

## Les documents

| Document | Bande MRZ | Puce |
|----------|-----------|------|
| Carte d'identité | 3 lignes (TD1, « I< ») | oui |
| Passeport | 2 lignes (TD3) | oui |
| Permis de conduire | 3 lignes (« D< ») | non |

Pays : **République d'Altis** (ALT, Altis et Stratis), **Fédération de Tanoa** (TNA), **République de Livonie** (LVN,
Livonia), **République de Malden** (MLD). Réglage « Pays des papiers » pour l'imposer.

### Pays réels stylisés — univers réaliste-simulation (1.3.1)

Réservés à une communauté qui joue un univers **réaliste-simulation** et l'a choisi explicitement : réglage
« Univers réaliste-simulation (pays réels stylisés) » (décoché par défaut), ou pays ajoutés sur Athena (Identités &
documents › Pays émetteurs › « Ajouter des pays réels (simulation) », arrivés avec le paquet). Jamais imposés.

| Pays | Code | Bande MRZ | Numéros | Zone du gabarit |
|------|------|-----------|---------|-----------------|
| Royaume-Uni | GBR | GBR | GB@###### | Europe (bordeaux) |
| Grèce | GRC | GRC | GR@###### | Europe |
| États-Unis | USA | USA | US@###### | Amériques (bleu) |
| Allemagne | DEU | **D<<** (code OACI) | DE@###### | Europe |
| Autriche | AUT | AUT | AT@###### | Europe |
| Colombie | COL | COL | CO@###### | Amériques |
| France, Belgique, Espagne, Pologne | FRA, BEL, ESP, POL | idem | FR…, BE…, ES…, PL… | Europe |
| Ukraine, Russie, Irak, Syrie, Afghanistan | UKR, RUS, IRQ, SYR, AFG | idem | UA…, RU…, IQ…, SY…, AF… | Autres (vert / noir) |

Garde-fous : **gabarit générique par zone** (aucune reproduction de vrai modèle, armoiries, emblème officiel,
hologramme, motif de sécurité, police ou mise en page officielle ; emblème « SIM » inventé), mention **« SIMULATION —
DOCUMENT DE JEU »** sur chaque face, numéros au **format générique du jeu** (code du pays + une lettre + six chiffres,
jamais le format d'un vrai titre), photos de personnages du jeu uniquement. Noms tirés des réservoirs de la langue du
pays (anglais, grec, allemand, espagnol de Colombie, polonais, ukrainien, russe avec noms au féminin, arabe, dari…),
mêmes tirages que le site (`fn_idRealCountries`, généré par `tools/gen_id_real_countries.php`).

Qui a des papiers :
- **Joueurs** : rien par défaut. Trois moyens d'en donner : attribut Eden « COMSPEC SSE — Papiers », objet du kit
  (une carte, un passeport ou un permis pris à l'Arsenal devient à l'apparition un document à son nom), module Zeus
  « Donner des papiers ».
- **IA** : selon leur camp (réglage « Papiers des IA ») : les civils ont presque toujours une carte, parfois un permis ;
  les ennemis ont souvent de faux papiers ; une HVT (biométrie : watchlist tier 1) porte une couverture préparée ;
  un recherché (tier 2) porte ses vrais papiers.
- Les papiers d'une IA ne deviennent des objets qu'au besoin (fouille, mort, remise) : aucun coût tant qu'on ne la
  contrôle pas.

Nature des papiers (jamais visible par les joueurs, seulement par le Zeus) : vrais papiers, couverture préparée (passe
tous les contrôles sauf la biométrie), faux papiers (contrefaçon, vrai document modifié, photo substituée, vierge volé),
avec une qualité de 0 à 100.

## Contrôler quelqu'un

Menu ACE sur la personne › **Contrôle d'identité** :
1. **Demander les papiers** (2 s). Une IA répond (elle tend ses papiers, refuse, fuit…). Un joueur reçoit la demande
   sur son ATAK et répond par son menu ACE personnel › **Mes papiers** : *Présenter* (on peut contrôler le document
   pendant 2 minutes), *Remettre* (l'objet passe dans votre inventaire) ou *Refuser*.
2. **Contrôler l'identité** › le document présenté › **Examiner** (2 s) : la visionneuse s'ouvre (recto / verso, zoom).
   C'est le contrôle visuel **N0** : un faux mal fait se voit (hologramme absent, police différente, photo rapportée).
   La nuit, éclairez le document.
3. **Scanner avec l'ATAK** : l'app Contrôle ID reprend le document :
   - **N1 · MRZ** (3 s) : chiffres de contrôle ICAO et concordance avec le recto ;
   - **N2 · puce NFC** (4 s, à 1,5 m du document) : signature et données de la puce ;
   - **N3 · base** (3 s) : base du pays (numéro délivré, volé, révoqué, avis de recherche) ; en ligne, Athena.
4. Bandeau de verdict : RAS, doute, faux, volé, révoqué, personne signalée, recherchée, HVT. Une personne signalée fait
   vibrer le téléphone (sans son en mode discrétion).
5. **Transmettre à Athena** (file d'attente sans liaison) et **Joindre à une FRM** (fiche pré-remplie dans l'app FRS).

### Photo, FRM, SALUTE et SPOTREP (1.2.0)

- **Photographier** (visionneuse du document, ou app Contrôle ID) : cliché du document, joint aux pièces de la fiche FRM
  en préparation (4 au plus, envoyées avec la fiche comme une capture de l'app FRS). Si la personne contrôlée a un dossier
  SSE, la photo s'inscrit aussi à son dossier (photos de preuve, journal de la pièce).
- **Joindre à une FRM** : la fiche reprend l'identité imprimée, le document, le verdict, l'examen visuel, la lecture MRZ,
  la puce NFC, la base, la position (carroyage et coordonnées), la date et l'heure du contrôle, le contrôleur et la photo
  du document ; l'en-tête de la fiche (carroyage, date, lieu) est rempli.
- **SALUTE** et **SPOTREP** (app Contrôle ID) : compte rendu pré-rempli avec le contrôle (personne, verdict, attitude,
  carroyage de la personne, heure, tenue, armes visibles, papiers), ouvert dans l'app SALUTE ou Comptes rendus. Le joueur
  relit, complète et envoie : rien ne part tout seul.
- Avec l'**ATAK 3.0.2** ou plus : **Partager** (fiche du contrôle en Bluetooth à un équipier appairé), **NFC** (tendue
  au téléphone le plus proche, 2 m, sans appairage), **Sur mon téléphone** (QR code : vos contrôles sur votre vrai
  téléphone) ; la fiche FRM envoyée et sa photo sont rattachées au contrôle sur Athena ; l'alerte d'une personne
  signalée passe en vibreur seul.
- **Compte rendu AAR** (module Zeus « Compte rendu SSE ») : une ligne par contrôle notable (détection d'un faux, d'un
  document volé ou révoqué, d'une personne signalée ou recherchée, HVT, saisie, interpellation, fuite, menace, reddition,
  renforts, issues des checkpoints).

Sans accord :
- **Fouiller les papiers** (6 s, 4 s sur un corps) : mort, menotté, rendu ou inconscient. Un joueur fouillé est prévenu
  sur son ATAK. Les documents trouvés s'examinent ou se **saisissent** (2 s, chaîne de possession SSE) ; **Rendre un
  document** (1 s) les restitue.

Véhicule : menu ACE du véhicule arrêté › Contrôle d'identité › **Contrôler les occupants** (chaque IA répond, chaque
joueur reçoit la demande) ou **Faire descendre**.

Onglet **HISTORIQUE** de l'app : les 50 derniers contrôles du joueur.

## IA réalistes (1.2.0)

Une IA contrôlée n'a plus une réponse figée : une **machine à états CBA** la fait réagir et évoluer pendant tout le
contrôle. Elle ne traite qu'une IA contrôlée par image (les autres IA ne coûtent rien).

| État | Ce qu'on voit |
|------|---------------|
| calme | elle vaque à ses occupations (avant et après le contrôle) |
| interpellé | elle s'arrête, se tourne vers vous (« Oui ? ») |
| coopère | elle tend ses papiers, hoche la tête |
| ment | elle présente sa couverture ou ses faux avec aplomb et raconte une histoire qui colle au document |
| nerveux | elle hésite avant de répondre, regarde autour d'elle, ses mains tremblent |
| refuse | elle secoue la tête, refuse ses papiers |
| fuit | elle détale (150 à 250 m) |
| agressif | elle recule, menace ; un insurgé caché parmi les civils passe dans un camp hostile et tire |
| se rend | mains en l'air (reddition ACE) : il reste à la menotter |
| appelle des renforts | elle crie ou téléphone : jusqu'à 6 IA proches de son camp (ou de sa cellule) convergent, armées elles deviennent hostiles |

La réaction dépend :
- du **profil** : civil, insurgé, HVT, agent sous couverture, policier (attribut Eden « Profil au contrôle », sinon
  biométrie, nature des papiers et camp) ;
- de l'**état** : menottée ou rendue, blessée, inconsciente, armée ;
- du **contexte** : nombre de soldats armés autour, **armes pointées sur elle** (une IA qu'on met en joue ne fuit pas,
  elle finit par se rendre), zone de checkpoint, couvre-feu ;
- de la **tension**, qui monte avec tout cela, quand on la redemande, quand on découvre son faux, et avec le réglage
  « Agressivité des IA ».

Les phrases courtes de l'IA arrivent en notification ATAK aux joueurs à moins de 15 m ; en difficulté arcade, l'état
est rappelé entre crochets.

## Checkpoints IA (1.2.0)

Module **Eden** « Checkpoint IA (contrôle d'identité) » (COMSPEC SSE · 2. Personnes et liens) ou module **Zeus**
« COMSPEC SSE · Identité › Checkpoint IA » : rayon de la zone, camp du poste, équipement (**visuel seul** N0, **scanner
MRZ** N1, **lecteur NFC** N2, **liaison base** N3), sévérité (laxiste, normale, stricte), nom. Gardes : unités
synchronisées (et leur groupe), sinon IA du camp dans la zone.

Déroulé pour un joueur qui entre dans la zone (ni du camp du poste, ni ennemi déclaré) :
1. **Halte** : « Papiers, s'il vous plaît » sur l'ATAK, le garde vient à lui. Il a 45 s (réglage) pour présenter ses
   papiers par le menu ACE personnel › Mes papiers (à moins de 3 m du garde).
2. **Examen** : le garde applique les niveaux de son équipement, comme un joueur (sa compétence compte pour l'examen
   visuel). Sans liaison base, il compare le nom à la liste des recherchés du poste ; il reconnaît parfois le visage
   d'une personne fichée.
3. **Suspicion** : papiers, équipement visible (arme, tenue militaire, gilet, casque), comportement (courir, arme à la
   main, arme pointée sur un garde, véhicule qui ne s'arrête pas, refus, fuite).
4. **Issue** selon la sévérité : **laisser passer** · **fouiller** (8 s : armes, papiers à plusieurs noms) · **retenir**
   (3 minutes, le Zeus est prévenu) · **alerter** (le poste et les groupes voisins passent en combat) · **ouvrir le feu**
   (menace armée, poste normal ou strict : le joueur devient hostile).

Les checkpoints contrôlent aussi, de temps en temps, une IA qui passe (réglage) : elle s'arrête, réagit (automate des
IA réalistes), le garde lit ses papiers, puis la laisse partir, la fait se rendre ou donne l'alerte.

Une seule boucle serveur d'une seconde pour tous les checkpoints ; rien chez les clients.

## Couvertures et infiltrés (1.3.0)

Un joueur (ou une IA) **infiltré** vit sous une identité de couverture : papiers « couverture » et **légende**.
Comment l'infiltrer :
- module Zeus **Couverture** › Infiltrer (avec, au choix, une carte de couverture à un nom saisi ou tiré) ;
- attribut Eden **Infiltré (couverture)** ;
- sur Athena, un **brief de couverture** (Préparation de mission › briefs) ou un papier « couverture » dans son kit :
  l'infiltration s'ouvre à son arrivée.

Tant que la couverture tient, il est **captif** (les ennemis ne le prennent pas pour cible ; réglage) et les
checkpoints IA le contrôlent comme un civil, même d'un camp ennemi. La couverture **saute** (« brûlée ») sur :
- une arme à la main vue par un ennemi (distance des témoins : réglage, 50 m) ;
- des soupçons accumulés : arme longue dans le dos, tenue militaire, gilet, casque, JVN vus de près ; zone interdite
  (module Zeus **Zone interdite (couvertures)** ou marqueurs de mission nommés `comspec_id_interdit…`) ; coup de feu
  entendu ; contrôle raté ; mauvaises réponses aux questions ; être retenu à un checkpoint ;
- immédiatement : coup de feu près d'un ennemi, menottes, alerte d'un checkpoint, arme trouvée à la fouille.

Brûlée : les groupes ennemis à 400 m passent en combat et connaissent sa position ; le joueur est prévenu
discrètement (vibreur seul avec l'ATAK 3.0.2) ; le Zeus et le compte rendu AAR aussi. Le module Zeus Couverture
permet aussi de **brûler** ou de **lever** une couverture.

**Cohérence** de la couverture (0 à 100) : papiers (une pièce de couverture, un seul nom, sa préparation), légende
(réponses justes), équipement (rien de militaire en vue), comportement (soupçons). Elle pèse sur l'examen visuel d'un
contrôleur et sur la suspicion des checkpoints.

### App « Ma couverture »

Téléphone ATAK › Renseignement › **Ma couverture** : votre fiche, visible de vous seul (et du Zeus) : état, cohérence et
ce qui vous trahit, identité, adresse, employeur, profession, téléphone, contacts, documents complémentaires, historique,
biographie, famille, langues, phrases types, sujets sensibles, secrets, brief et consignes d'Athena. Le Zeus a un onglet
**ZEUS** : tous les infiltrés, leur cohérence, leur fiche avec le vrai nom.

### Questions de légende

- **Checkpoints IA** : après la lecture des papiers, le garde pose 2 questions (3 au poste strict) : « D'où venez-vous ? »,
  « Pour qui travaillez-vous ? », « Raison du déplacement ? », « Quel est votre métier ? », « Où êtes-vous né ? »,
  « Qui peut confirmer ce que vous dites ? ». Réponse au choix (votre vraie légende et deux leurres) dans l'app Ma
  couverture ou par le menu ACE personnel › **Mes papiers › Répondre**. Délai : réglage (20 s, 10 s de plus par
  question). Une mauvaise réponse ou un silence éveille les soupçons. Tout joueur a une légende tirée de ses papiers.
- **Interroger une IA** : menu ACE sur la personne › Contrôle d'identité › **Poser une question**. Un civil répond
  juste ; un porteur de couverture selon sa préparation ; un porteur de faux papiers se trompe plus souvent (comparez
  avec ses papiers). Les réponses s'affichent dans l'app Contrôle ID et partent dans la FRM.

## HVT et BOLO (1.3.0)

### N4 · comparaison du visage

App Contrôle ID › **Comparer le visage (N4)** (8 s, téléphone ATAK ou SEEK, la personne à 3 m) : le visage est comparé à
la base biométrique (complète avec la liaison Athena, sinon base locale) et à la photo du document. Résultat avec un
**taux de confiance** : visage reconnu (dossier, alias, watchlist), correspondance possible (à confirmer par empreintes
ou iris au SEEK), aucune. Une **HVT est reconnue même sous un faux nom** ; un porteur qui n'est pas le titulaire du
document est signalé. Cagoule, foulard, nuit et distance dégradent le cliché.

### Chaîne HVT

1. **Désigner HVT** (Zeus) : niveau (HVT ou recherché), alias, vrai nom, papiers connus, notes, couverture à un faux
   nom. La personne est au fichier (visage, empreintes), sur les avis (N3) et sur le BOLO ; la chasse aux HVT de
   l'app Tinder de l'ATAK commence (FIND).
2. **Contrôle → identification** : base N3 (nom, numéro connu) ou visage N4.
3. **Confirmer le PID** (bouton de l'app Contrôle ID quand le verdict est HVT ou recherché) : tuyaux confirmés pour
   votre groupe dans la chasse HVT (le dossier passe en **FIX**), Zeus, compte rendu, Athena.
4. **Capture** (menottes ACE) ou **neutralisation** : **JACKPOT** ; données SSE de la personne générées et chaîne de
   possession ouverte (exploitation SSE), puis transmission à Athena comme d'habitude.

### BOLO

Module Zeus **BOLO** : nom, prénom, alias, niveau (surveillance, recherché, HVT), papiers connus, signalement ; posé sur
une personne, son visage est joint. Le BOLO est diffusé :
- aux **checkpoints IA** (liste papier : noms, alias, numéros ; visage reconnu de temps en temps) ;
- aux **contrôleurs** : onglet **BOLO** de l'app Contrôle ID, contrôle N3 (« BOLO : document à surveiller »), visage N4 ;
- à l'app **Recherchés** (liste déjà chargée) et sur **Athena** (liste de surveillance de la communauté, renvoyée aux
  parties suivantes).
**Lever un BOLO** le retire (et le lève sur Athena).

### Préparation de mission sur Athena (Lot 6)

Avec la liaison, le paquet de la mission apporte aussi : des **identités préparées pour les IA** (camp, catégorie,
zone = nom de marqueur) remises à leur premier contrôle, les **recherchés de l'opération** (visibles seulement des
camps choisis sur Athena) et les **briefs de couverture** des joueurs (app Ma couverture).

## Zeus

Catégorie « COMSPEC SSE · Identité » :
- **Donner des papiers** : type, pays, nature, procédé et qualité du faux, nom imprimé, ou numéro d'un document Athena.
- **Signaler volé / révoquer** : visible au contrôle N3.
- **Voir les vrais papiers** : fenêtre Papiers de l'IA (Zeus seulement) : le document tel que le voit un joueur et, à côté, vraie identité, nature, qualité expliquée, défauts, ce que trouvera chaque contrôle.
- **Checkpoint IA** / **Retirer un checkpoint IA**.
- **Déclencher un contrôle** sur une personne : par le checkpoint qui la couvre, sinon par l'IA armée la plus proche
  (80 m, poste provisoire, équipement et sévérité au choix).
- **Forcer un état IA** : impose un état (ordres réels) ; l'IA réagit de nouveau d'elle-même 30 s plus tard.
- **Couvre-feu** : en vigueur ou levé (les civils dehors sont plus nerveux).
- **Couverture** (infiltrer, brûler, lever) · **Zone interdite (couvertures)** · **Désigner HVT** · **BOLO** ·
  **Lever un BOLO** (1.3.0, voir plus haut). « Voir les vrais papiers » montre aussi l'infiltration et la HVT.

## Réglages (CBA › COMSPEC SSE › Identité)

Contrôle d'identité · Difficulté (arcade / standard / réaliste) · **Univers réaliste-simulation (pays réels stylisés)**
(non) · Pays des papiers · Papiers des IA · Civils qui
présentent leurs papiers (90 %) · Insurgés porteurs de faux papiers (60 %) · Durée d'une présentation (120 s) ·
Transmission automatique à Athena (non) · Documents et avis d'Athena · Opération Athena · **IA réalistes au contrôle**
(oui) · **Agressivité des IA** (50 %) · **Distance des checkpoints** (25 m) · **Délai pour présenter ses papiers** (45 s) ·
**Les checkpoints contrôlent aussi les IA** (oui) · **Couvre-feu** (non) · **Infiltré captif tant que sa couverture
tient** (oui) · **Soupçons avant que la couverture saute** (100) · **Distance des témoins** (50 m) · **Questions de
légende** (oui) · **Délai pour répondre** (20 s) · **Comparaison du visage (N4)** (oui) · **Durée de la comparaison du
visage** (8 s) · **Notifier les BOLO** (oui).

Joueur formé au contrôle (meilleur examen visuel) : `_unit setVariable ["COMSPEC_ID_Expert", true, true]`.

## Pour les scripts

- `["aiAsk", [ia, joueur]] call comspec_sse_fnc_idServer` (serveur) : une IA demande ses papiers à un joueur.
- `["create", [position, réglages]] call comspec_sse_fnc_idCheckpoint` (serveur) : checkpoint IA ; réglages = HashMap
  `radius`, `side` ("auto", "west", "east", "guer", "civ"), `equip` 0-3, `severity` 0-2, `name`, `guards`.
  `["trigger", [personne, réglages]]` : contrôle forcé ; `["remove", [position]]`.
- `["force", [ia, "fuit"]] call comspec_sse_fnc_idAiFsm` (serveur) : impose un état ; `["state", [ia]]` : état courant ;
  `["engage", [ia, contrôleur]]` : interpelle sans demander les papiers.
- `_unit setVariable ["COMSPEC_ID_Profile", "insurge"]` (serveur) : profil au contrôle (civil, insurge, hvt, agent,
  policier).
- Évènements CBA serveur : `comspec_sse_idAsked`, `comspec_sse_idPresented` [porteur, docId, contrôleur],
  `comspec_sse_idRefused`, `comspec_sse_idChecked` [contrôleur, docId, niveau, résultat].
- `[_doc, _ia, niveau] call comspec_sse_fnc_idEvaluate` (serveur) : contrôle par une IA.
- `["start", [unité, createHashMap]] call comspec_sse_fnc_idCover` (serveur) : infiltrer ; `["burn", [unité, motif]]`,
  `["stop", [unité]]`, `["score", [unité]]` -> [cohérence, motifs] ; `_unit setVariable ["COMSPEC_ID_Undercover", true]`
  avant l'apparition (serveur) : infiltré dès le début.
- `["ask", [ia, joueur, 2, ""]] call comspec_sse_fnc_idQuestion` (serveur) : l'IA pose des questions de légende.
- `["designate", [unité, réglages, objNull]] call comspec_sse_fnc_idHvt` (serveur) : désigner une HVT (réglages :
  `alias`, `last`, `first`, `docs`, `level`, `cover`) ; `["bolo", [réglages, objNull, unité | objNull]]` : BOLO.
- Évènements CBA serveur : `comspec_sse_idAnswered` [joueur, demandeur, juste, clé, contexte, évasif],
  `comspec_sse_idCoverBurnt` [unité, motif] ; client : `comspec_sse_idBoloNew` [texte].

### Papiers reliés à Athena (API pour les mods et les scripts)

Chaque membre reçoit **sa** carte : celle de son identité Athena principale, ou de sa couverture si une fiche de
couverture la lui attribue (kit composé sur Athena, sinon kit automatique). Un autre joueur peut la ramasser sur le corps
ou au sol : au contrôle, c'est l'identité Athena liée à la carte qui s'affiche (photo : le visage du propriétaire), jamais
celle du porteur. Les papiers volés, perdus ou révoqués sur Athena sont signalés au contrôle (base locale, N3).

- `[classe | docId] call comspec_sse_fnc_idPaperOf` (serveur) -> HashMap : imprimé (`last`, `first`, `dob`, `pob`,
  `number`…), `athenaDocId`, `athenaIdentityId`, `athenaRef` (« IDT-000001 »), `ownerUid`, `ownerName`, `holder`,
  `holderUid`, `foreign` (porté par un autre que le propriétaire), `report`, `validity`, `kind` (vérité, serveur seulement),
  `custody` (chaîne de possession).
- `[classe | docId] call comspec_sse_fnc_idPaperOwner` (serveur) -> `[ownerUid, ownerName, holder, holderUid, foreign]`.
- `[classe | docId, HashMap] call comspec_sse_fnc_idPaperLink` (serveur) : relie un papier à une identité Athena et à un
  membre (`athenaDocId` : reprend l'imprimé du papier Athena de même type ; `athenaIdentity` ; `owner` (unité) ou
  `ownerUid` / `ownerName`).
- `[unité, id Athena | numéro] call comspec_sse_fnc_idPaperGive` (serveur) : remet à une personne son papier Athena.
- Évènements CBA globaux : `comspec_sse_identity_paperPicked` / `comspec_sse_identity_paperDropped` [unité, classe,
  conteneur], `comspec_sse_identity_paperLinked` [classe, docId].

**Méthode et limites.** Arma n'a pas de variable par objet d'inventaire (un objet d'un sac n'est qu'un nom de classe).
Chaque exemplaire est donc une **classe libre** (`COMSPEC_ID_Card_001`…, `CfgWeapons_instances.hpp` : 512 cartes,
256 passeports, permis et cartes militaires) reliée au document par le registre du serveur ; la classe suit l'objet
partout (inventaire, corps, sol, caisse) sans rien poser sur l'objet. Limites : stock fini (au-delà, le papier reste
virtuel, journalisé) ; une classe du catalogue prise à l'Arsenal est vierge (aucun lien) ; le porteur est connu par les
évènements Take / Put du joueur et par les gestes du contrôle (saisie, remise) : un transfert fait par un autre script
(addItem / removeItem directs) n'est pas vu tant qu'il n'appelle pas `["take", [unité, classe, conteneur]] call
comspec_sse_fnc_idPaperTrack` ; le registre vit le temps de la mission (rien n'est gardé d'une partie à l'autre, le kit
Athena est remis à chaque arrivée).

### Identité Athena ↔ jeu

Sans papier Athena remis, l'app « Ma couverture » montre une identité tirée par le mod (clé biométrique) : elle dit
pourquoi (paquet non chargé, aucun papier pour ce compte Steam, papiers du kit absents, papier signalé). Si la
transmission automatique est permise (réglage, celui de la communauté l'emporte), l'identité utilisée en jeu remonte à
Athena ; la fiche membre la compare champ par champ et ne l'écrit qu'au choix du responsable (valeur écartée historisée,
restaurable).

## Vérifier la MRZ

`python tools/check_id_mrz.py` (hors jeu) et `tools/test_id_mrz.sqf` (console de débogage) : spécimen ICAO
(L898902C3 → 6, 740812 → 2, 120415 → 9, ZE184226B → 1, global → 0), même écriture que le site.
