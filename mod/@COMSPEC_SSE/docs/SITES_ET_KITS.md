# Sites et kits SSE — guide du mission maker

Ce guide décrit les sites préfabriqués, les pièces de preuve posables et les caisses de kit du SSE.
Tout est créé **sur le serveur**. Les fiches sont donc écrites par le greffier, les liens sont tenus par le serveur, et les clients reçoivent fiches et menus, y compris ceux qui rejoignent en cours de partie (JIP).

## Modules (catégorie « SSE · Sites et kits », Zeus et Eden)

| Module | Effet |
|---|---|
| Poser un site SSE | Pose un site préfabriqué à l'endroit du module. En Zeus, le site suit l'orientation de la caméra. |
| Peupler un bâtiment | Transforme en site le bâtiment habitable sous le module (15 m au plus). Le mobilier est réparti dans les pièces (`buildingPos`), ce qui le rend utilisable sur toutes les cartes. |
| Semer des preuves | Pose de 1 à 20 pièces dans les bâtiments du rayon, ou au sol à défaut. Elles sont reliées aux personnes non joueurs du rayon : la plus proche du centre devient le chef. |
| Réseau multi-sites | Pose de 3 à 6 sites près de villes et villages réels, avec un PC de cellule en bout de chaîne. Chaque site cite le suivant. |
| Poser une caisse de kit | Pose un kit d'équipe, un kit biométrie, un kit d'exploitation numérique ou une valise DOMEX. |

En Zeus, un formulaire s'ouvre à la pose. Dans Eden, on règle les mêmes champs dans les attributs du module, qui s'exécute au lancement de la mission.
Le Zeus reçoit un compte rendu (grid, nombre de pièces, d'occupants et de pièges).
Seul un Zeus ou un administrateur peut faire la demande (`comspec_sse_fnc_siteRequest`, déclaré dans `CfgRemoteExec`).

Options communes aux sites :

- **Occupants** : nombre de personnes présentes. La valeur -1 reprend celle du site.
- **Camp** : indépendants, OPFOR ou civils. On peut aussi choisir des occupants sans arme.
- **Richesse** : quantité d'indices dans les fiches.
- **Pièges** : mine à fil (`APERSTripMine`) à l'entrée, plus un engin piégé (`IEDUrbanSmall_F`) près de l'établi dans un labo d'IED. Les mines sont connues du camp des occupants.
- **Bâtiment** : le site cherche un bâtiment habitable à moins de 40 m. S'il n'en trouve pas, il est posé au sol.

Dans un bâtiment, les occupants ne quittent pas leurs pièces (`disableAI "PATH"`).

## Les sept sites

| Type | Thème du générateur | Mobilier et preuves | Occupants par défaut |
|---|---|---|---|
| `SAFEHOUSE` — Planque | `safehouse` | Table, réchaud, couchages, étagère. Deux téléphones, documents, carnet, clé USB, valise. | 3 |
| `IED_LAB` — Labo d'IED | `ied_cell` | Établi (batterie, fil, ruban adhésif, outils), table (chalumeau, perceuse), jerricans, sacs d'engrais. Deux téléphones déclencheurs, carnet de schémas, photos, ordinateur. | 2 |
| `FORGERY` — Faux papiers | `smuggling` | Bureaux, PC, imprimante, armoire. Unité centrale, registre, photos d'identité, documents, carte mémoire. | 2 |
| `WEAPONS_CACHE` — Cache d'armes | `weapons_cache` | Bâche, sacs, pelle. Deux caisses FIA, registre de distribution, radio. | 0 |
| `CELL_HQ` — PC de cellule | `meeting_alpha` | Grande table, carte murale, bureau, radio, groupe électrogène. Carte annotée, téléphone satellite, carnet, ordinateur, disque dur, radio, photos. | 4 |
| `DEAD_DROP` — Boîte aux lettres morte | `courier_run` | Bidon et déchets. Clé USB et lettre. | 0 |
| `HAWALA` — Nœud hawala | `finance_drop` | Comptoir, bureau, armoire, coffret. Registre de comptes, billets, téléphone, ordinateur, carnet. | 2 |

Un site partage un seul **cluster** :

- Le premier occupant traité devient le chef. Les autres occupants lui sont subordonnés (`SUBORDINATE`).
- Les pièces sont reliées au chef (`OWNER` ou `REFERENCES`).
- Un site sans occupant relie ses pièces à la première d'entre elles.

Le thème narratif est imposé par le type de site : documents, SMS et fichiers racontent la même histoire.

**Dossier** : la première pièce papier du site reçoit des notes manuscrites, des lieux et des renseignements qui citent d'autres sites, avec une fenêtre de passage.

- Site isolé : il cite deux lieux réels de la carte, un point de rendez-vous et un dépôt.
- Réseau : chaque site cite le suivant et le précédent, et le PC les cite tous. Le thème de chaque site renvoie aussi au site suivant. Les chefs sont reliés entre eux (`CONTACT`) et au chef du PC (`SUBORDINATE`).

Les compositions sont décrites en données dans `generator/functions/fn_compositionData.sqf`. Pour ajouter un site, il suffit d'y ajouter un `case`, sans toucher au moteur.

## Pièces de preuve posables (catégorie « COMSPEC SSE - Preuves »)

Les classes `COMSPEC_SSE_Obj_*` reprennent un objet vanilla et lui ajoutent un type SSE. Le serveur marque chaque pièce à l'init : fiche légère, dont le contenu est généré au premier examen.

| Classe | Objet vanilla | Type SSE |
|---|---|---|
| `_Phone`, `_Smartphone`, `_SatPhone` | `Land_MobilePhone_old_F`, `Land_MobilePhone_smart_F`, `Land_SatellitePhone_F` | PHONE |
| `_Radio` | `Land_PortableLongRangeRadio_F` | RADIO |
| `_Laptop`, `_Tablet`, `_PCTower` | `Land_Laptop_unfolded_F`, `Land_Tablet_01_F`, `Land_PCSet_01_case_F` | COMPUTER |
| `_HardDrive`, `_USB`, `_SDCard` | modèles vanilla d'alimentation, de dongle USB et de carte | MEDIA |
| `_Notebook`, `_Documents`, `_Ledger`, `_Photos`, `_Map` | `Land_Notepad_F`, `Land_File1_F`, `Land_File2_F`, `Land_FilePhotos_F`, `Land_Map_unfolded_F` | DOCUMENT |
| `_Cash`, `_Suitcase` | `Land_Money_F`, `Land_Suitcase_F` | OBJECT, CONTAINER |

Les variables `comspec_sse_profile` et `comspec_sse_complexity`, posées sur l'objet, règlent le profil et la richesse de sa fiche.

## Kits (catégorie « COMSPEC SSE - Kits »)

| Caisse | Contenu |
|---|---|
| `COMSPEC_SSE_Box_Team` — kit SSE d'équipe | 20 sachets à scellés, 8 paires de gants, appareil photo, 2 jeux de cônes, 6 sacs à documents, 4 sacs de Faraday, 4 sacs de tête |
| `COMSPEC_SSE_Box_Biometrics` — kit biométrie | SEEK II, 2 kits empreintes, 6 écouvillons, kit ADN, 6 paires de gants, 6 sachets |
| `COMSPEC_SSE_Box_Digital` — kit exploitation numérique | 8 sacs de Faraday, terminal, appareil photo, 4 paires de gants, 8 sachets |
| `COMSPEC_SSE_DomexCase` — valise DOMEX de terrain | Valise posable près du support saisi, avec 4 sacs de Faraday et 4 sachets |

Chaque outil existe aussi posé au sol, à ramasser (`Item_COMSPEC_SSE_*`).

Les nouveaux objets de kit n'ont ici que leur configuration. Leur usage passe par le terminal et des interactions physiques, et il est câblé ailleurs. Ces objets sont :

- `COMSPEC_SSE_FaradayBag` ;
- `COMSPEC_SSE_SwabKit` ;
- `COMSPEC_SSE_EvidenceMarkers` ;
- les consommables de `COMSPEC_SSE_FingerprintKit` ;
- `COMSPEC_SSE_DomexCase`.

L'ancien « Équiper le kit SSE » (`comspec_sse_fnc_equipSseKit`) est conservé pour les scripts existants. Il est remplacé par ces caisses.

## Par script (serveur)

```sqf
["SAFEHOUSE", getPosATL _logic, 90, [["occupants", 3], ["traps", true]]] call comspec_sse_fnc_spawnComposition;
[getPosATL _logic, 4, 3000, [["side", "EAST"]]] call comspec_sse_fnc_spawnSiteNetwork;
[getPosATL _logic, 40, 8, [["profile", "COURIER"]]] call comspec_sse_fnc_seedEvidence;
["BIO", getPosATL _logic, 0] call comspec_sse_fnc_spawnKitBox;
```

`spawnComposition` renvoie un HashMap avec les clés `id`, `grid`, `objects`, `persons`, `evidence`, `mines` et `readyIn`. Le chef (`chief`) et l'état `ready` sont remplis quand les fiches sont générées.
Sur le serveur, `comspec_sse_siteRegistry` garde les sites posés.

## Usage des kits sur le terrain

Le SSE n'a plus de menu ACE personnel. Le travail passe par l'app **SSE terrain** du téléphone ATAK, sauf les gestes physiques au contact :

| Geste (interaction ACE sur la cible) | Kit porté | Consommation |
|---|---|---|
| Fouiller (personne, objet) | aucun | - |
| Mettre sous scellé (collecte + scellé) | Sachet de preuve | 1 sachet |
| Relever les empreintes (personne) | Kit empreintes (sans SEEK II) | 10 relevés par kit |
| Relever des empreintes latentes (objet, arme au sol, trace de sang) | Kit empreintes | 10 relevés par kit |
| Prélèvement ADN (personne, trace de sang) | Kit ADN ou kit d'écouvillons | 12 par kit |
| Test de résidus de tir | Kit d'écouvillons | 12 par kit |
| Enrôlement biométrique | SEEK II | - |
| Mettre en sac de Faraday (support numérique) | Sac de Faraday | 1 sac |
| Poser un cône de preuve (numéroté, marqueur carte) | Jeu de cônes | 12 cônes par jeu |
| Examiner une trace de sang (action personnelle près de la trace) | aucun | - |

- **Numérique** : brancher le câble DL-7 du téléphone (app SSE terrain, bouton « Brancher le DL-7 »). Une **valise DOMEX** posée à moins de 10 m divise par deux le téléchargement et le forçage.
- **Effacement à distance** : un téléphone saisi sur trois est effacé 5 à 8 minutes après l'arrivée des joueurs, sauf s'il est dans un sac de Faraday ou déjà extrait.
- **Photos de preuve** : app Photo de l'ATAK (les pièces cadrées sont inscrites au dossier) ou bouton « Photo de preuve » de l'app SSE terrain.
- **Qualité de collecte** : gants, lumière, pluie, blessure, compétence, nombre de tentatives, et doctrine (pièce photographiée en place, cône posé avant le déplacement).
- **Test de résidus de tir** : positif si la personne a tiré dans les 20 dernières minutes, faible jusqu'à 45 minutes ou sous forte pluie, négatif au-delà.
