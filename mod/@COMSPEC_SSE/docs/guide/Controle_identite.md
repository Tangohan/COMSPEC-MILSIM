# Contrôle d'identité (COMSPEC SSE 1.2.0)

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

## Zeus

Catégorie « COMSPEC SSE · Identité » :
- **Donner des papiers** : type, pays, nature, procédé et qualité du faux, nom imprimé, ou numéro d'un document Athena.
- **Signaler volé / révoquer** : visible au contrôle N3.
- **Voir les vrais papiers** : vraie identité, nature, qualité, défauts (Zeus seulement).
- **Checkpoint IA** / **Retirer un checkpoint IA**.
- **Déclencher un contrôle** sur une personne : par le checkpoint qui la couvre, sinon par l'IA armée la plus proche
  (80 m, poste provisoire, équipement et sévérité au choix).
- **Forcer un état IA** : impose un état (ordres réels) ; l'IA réagit de nouveau d'elle-même 30 s plus tard.
- **Couvre-feu** : en vigueur ou levé (les civils dehors sont plus nerveux).

## Réglages (CBA › COMSPEC SSE › Identité)

Contrôle d'identité · Difficulté (arcade / standard / réaliste) · Pays des papiers · Papiers des IA · Civils qui
présentent leurs papiers (90 %) · Insurgés porteurs de faux papiers (60 %) · Durée d'une présentation (120 s) ·
Transmission automatique à Athena (non) · Documents et avis d'Athena · Opération Athena · **IA réalistes au contrôle**
(oui) · **Agressivité des IA** (50 %) · **Distance des checkpoints** (25 m) · **Délai pour présenter ses papiers** (45 s) ·
**Les checkpoints contrôlent aussi les IA** (oui) · **Couvre-feu** (non).

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

## Vérifier la MRZ

`python tools/check_id_mrz.py` (hors jeu) et `tools/test_id_mrz.sqf` (console de débogage) : spécimen ICAO
(L898902C3 → 6, 740812 → 2, 120415 → 9, ZE184226B → 1, global → 0), même écriture que le site.
