# TOC : données et saisies sans téléphone (ATAK natif 4.11.0)

Numéro : la feuille de route prévoyait 4.9.0 pour ce lot ; la refonte de la console du poste a pris la 4.9.0, ce lot
sort en 4.11.0 (la 4.10.0 est prise par la branche « salle tenue », PR #1561).

Lot 1 de `docs/audit/2026-11-toc-feuille-de-route.md`. Principe posé par TTRD : **tout le TOC doit être utilisable
sans téléphone**. Cette page est le contrat entre la couche données (cette branche) et ceux qui affichent : la
console du PC du poste (`fn_tocLaptop`, `fn_tocBoard`) et les écrans (`fn_tocScreen`, `fn_tocSat`, `fn_tocDraw`).

Règle pour qui affiche : **ne plus lire l'état du téléphone** (`COMSPEC_ATAK_State`, `fn_hasDevice`, `fn_bftOrbat`,
`COMSPEC_ATAK_Data`). Tout passe par `comspec_atak_native_fnc_tocPanels`.

Fichiers, tous dans `mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/toc/` :

| Fichier | Rôle | Où il tourne |
|---|---|---|
| `fn_tocPanels.sqf` | Registre des panneaux : une seule entrée pour lire | client |
| `fn_tocTracks.sqf` | Pistes du jeu, fusion avec les téléphones, ORBAT du jeu | serveur (ORBAT : partout) |
| `fn_tocNodeStatus.sqf` | État d'une piste (fonction pure) | partout |
| `fn_tocEntry.sqf` | Saisies du poste : formulaires, validation, écriture | client puis serveur |
| `fn_tocInbox.sqf` | Messages, ordres, accusés, retours sans téléphone | client (tableau des ordres : serveur) |
| `fn_tocDisplayName.sqf` | Nom affiché d'une unité : indicatif Athena, jamais le pseudo Steam | partout |
| `fn_tocRoster.sqf` | Tableau EFFECTIF : indicatif, rôle, nom et prénom du personnage | client |
| `fn_tocFeed.sqf`, `fn_tocRoom.sqf`, `fn_tocCam.sqf` | Existants, étendus | serveur, serveur, client |

## 1. Lire : le registre des panneaux

```sqf
(["data", ["ORBAT", "WEST"]] call comspec_atak_native_fnc_tocPanels) params ["_titre", "_lignes", "_etat", "_texte", "_note"];
```

- `_etat` : `"OK"`, `"EMPTY"` (rien à montrer : afficher `_texte`), `"WAIT"` (le serveur n'a encore rien publié),
  `"LATE"` (dernier envoi vieux de plus de 15 s).
- `_note` : à montrer en pied de tableau quand elle n'est pas vide (« 150 pistes affichées sur 212 »).
- `_titre`, `_texte`, `_note` sont en français : les passer par `comspec_atak_native_fnc_tr` à l'affichage.

| Panneau | Lignes |
|---|---|
| `TRACKS` | nœuds, voir 2 |
| `STRENGTH` | `[[libellé, valeur]...]` : joueurs, IA, part sur le réseau ATAK, pistes affichées et en tout, source, brouilleurs, relais |
| `ORBAT` | groupes du jeu, voir 3, plus `13` état du meilleur nœud du groupe (0 à 4) et `14` `[x, y]` ou `[]` |
| `FEED` | flux, le plus récent d'abord : `[heure, sorte, priorité, auteur, grille, texte, [x, y] ou [], serverTime, partie précédente]` |
| `ORDERS` | ordres du camp, voir 5 |
| `INBOX` | ma boîte : `[id, genre, heure, de, priorité, texte, lu, référence, instant]` |
| `MYORDERS` | mes ordres : `[id, heure, type, émetteur, cible, consigne, priorité, mon statut, [x, y], instant]` |
| `MYREQ` | mes demandes au TOC (lignes de la salle) |
| `LOG`, `REQ`, `KILLS`, `EXCHECK`, `TIMERS`, `POSTS` | tranches de l'état de la salle (`fn_tocRoom`, formats inchangés) |
| `CAMS` | `[[libellé, netId, genre]...]`, caméra de casque comprise |
| `FEEDBACK` | `[[texte, type, âge en s]]` du dernier retour d'action, vide après 8 s |

Autres modes : `["list"]`, `["has", id]`, `["feed", camp]` (flux complet, nœuds complétés aux 16 champs même si le
serveur est plus ancien), `["orbat", camp]` (`[groupes, ordres]`).

Ajouter un panneau (lots suivants, mission) sans toucher aux gros fichiers :

```sqf
["register", ["METEO", "MÉTÉO", { params ["_side", "_args"]; [[["Vent", windStr]], ""] }, "Pas de relevé"]] call comspec_atak_native_fnc_tocPanels;
```

## 2. Nœud d'une piste

`COMSPEC_ATAK_TocFeed_<camp> = [serverTime, nœuds, flux, état]`. Champs 0 à 8 inchangés depuis la 4.4, 9 à 15 ajoutés.

| N° | Champ | Valeurs |
|---|---|---|
| 0 | clé | netId de l'unité ; pour un véhicule, celui du chef de bord |
| 1 | indicatif | |
| 2 | `[x, y]` | |
| 3 | cap | degrés |
| 4 | état | 0 en direct, 1 ancien, 2 perdu, 3 hors ligne, 4 source inconnue (neutre) |
| 5 | genre | `"inf"`, `"veh"`, `"armor"`, `"air"`, `"boat"` |
| 6 | IA | booléen |
| 7 | groupe | nom du groupe |
| 8 | occupants | équipage d'un véhicule regroupé en un nœud |
| 9 | source | `"J"` jeu, `"A"` réseau ATAK, `"M"` les deux |
| 10 | santé | 0 opérationnel, 1 blessé, 2 inconscient, 3 tué (piste gardée 5 minutes à l'endroit de la mort, état 2 « perdu ») |
| 11 | `[altitude sol m, vitesse km/h]` | `[]` à pied |
| 12 | intégrité | 0 à 100 ; -1 inconnue |
| 13 | munitions | 0 inconnues, 1 rouge, 2 ambre, 3 vert (arme principale, à pied) |
| 14 | téléphone | `[]` sans ATAK, sinon `[état de sa liaison 0 à 4]` |
| 15 | `[sous-genre, nom du véhicule]` | sous-genre `""`, `"heli"`, `"plane"`, `"uav"` |

État du flux : `[brouilleurs, relais, joueurs du camp, IA du camp, [joueurs sur le réseau ATAK, IA sur le réseau ATAK,
pistes affichées, pistes en tout, réglage "MIX" | "GAME" | "ATAK", plafond]]`. Les compteurs 2 et 3 sont maintenant
l'effectif réel du camp.

### Règle d'état (`fn_tocNodeStatus`)

L'état vient de la meilleure source vivante.

| Source | Cas | État |
|---|---|---|
| Jeu ou mixte | unité simulée sur la machine qui calcule (IA du serveur, joueur hôte) | 0 en direct |
| Jeu ou mixte | relevé vieux de moins de 2 intervalles + 3 s | 0 en direct |
| Jeu ou mixte | moins de 6 intervalles + 10 s | 1 ancien |
| Jeu ou mixte | au-delà | 2 perdu |
| Réseau ATAK | balise éteinte | 3 hors ligne |
| Réseau ATAK | unité locale | 0 en direct |
| Réseau ATAK | aucun point reçu | 4 source inconnue |
| Réseau ATAK | âge du point : moins de 1,6 intervalle + 2 s, puis moins de 7,8 intervalles + 10 s, puis au-delà | 0, 1, 2 |
| Vide ou inconnue | | 4 source inconnue |

```sqf
["node", ["J", false, 4, 3, true, false]] call comspec_atak_native_fnc_tocNodeStatus;   // 0
["label", 4] call comspec_atak_native_fnc_tocNodeStatus;                               // ["source inconnue", "text2"]
["life", 1] call comspec_atak_native_fnc_tocNodeStatus;                                // ["blessé", "amber"]
["source", "M"] call comspec_atak_native_fnc_tocNodeStatus;                            // "jeu et ATAK"
```

Pour un téléphone seul, `fn_tocNodeStatus` appelle la règle de `fn_tocStatus` (4.8.0, branche des écrans) sans la
recopier : une seule table de seuils pour le téléphone, mêmes codes 0 à 4, mêmes libellés à l'écran.

### Réglages (serveur)

| Réglage | Défaut | Effet |
|---|---|---|
| `comspec_atak_native_toc_source` | `mix` | `mix` : tout le monde est vu, un porteur garde en plus l'état de sa liaison. `game` : tout le monde est vu, les téléphones ne sont pas lus. `atak` : comme avant la 4.11.0 |
| `comspec_atak_native_toc_tracks_ai` | `leaders` | IA suivies hors groupes de joueurs : chefs et véhicules, toutes, aucune |
| `comspec_atak_native_toc_tracks_max` | 150 | plafond de pistes par camp (20 à 400) |
| `comspec_atak_native_toc_headcam` | coché | caméra de casque d'un joueur sans téléphone |

Mission : `_unite setVariable ["COMSPEC_ATAK_TocTrack", true, true]` (ou sur un groupe) force le suivi ; `false`
cache l'unité. `player setVariable ["COMSPEC_ATAK_TocHeadCamOff", true, true]` refuse la caméra de casque.

### Coût serveur

- Aucune boucle nouvelle : le relevé est demandé par le tour de `fn_tocFeed` (3 s), qui sort tout de suite s'il n'y
  a ni écran ni PC ouvert, et ne calcule que les camps qui ont un lecteur.
- Relevé gardé entre deux tours : 3 s jusqu'à 50 pistes, 5 s jusqu'à 100, 8 s jusqu'à 200, 12 s au-delà.
- Plafond de pistes ; priorité : joueurs, leurs groupes, unités désignées, véhicules, chefs d'IA, autres IA.
- Munitions : 25 unités par relevé au plus, une fois toutes les 30 s par unité.
- ORBAT relu toutes les 15 s, envoyé seulement quand il change, aux seuls lecteurs.
- Pertes : une écriture de la salle toutes les 5 s au plus par camp, et seulement pour un camp qui a un TOC.

## 3. ORBAT du jeu

`["orbat", camp] call comspec_atak_native_fnc_tocTracks` (partout) ou le panneau `ORBAT`. Un groupe :

`[nom, netId, chef, chef joueur, vivants, effectif le plus haut vu, joueurs vivants, genre, véhicule, blessés,
munitions les plus basses (0 à 3), santé la plus grave (0 à 3), membres [[nom, rôle, joueur, santé]...] (14 au plus)]`

L'ORBAT du site (`fn_bftOrbat`) devient un calque en plus : il n'est plus nécessaire pour remplir le tableau.

## 4. Saisir au poste (`fn_tocEntry`)

```sqf
private _forms = ["forms"] call comspec_atak_native_fnc_tocEntry;   // [[genre, titre, champs]...] pour dessiner le formulaire
(["submit", ["MEDEVAC", [["el", "ALPHA 1"], ["grid", "043127"], ["a", 1], ["sec", "P"]], []]] call comspec_atak_native_fnc_tocEntry) params ["_ok", "_erreurs"];
```

Champ d'un formulaire : `[clé, libellé, type, obligatoire, maximum, choix]`, types `text`, `grid`, `int`, `list`.
Le troisième argument de `submit` est la position cliquée sur la carte du poste (`[x, y]`), sinon `[]` : le serveur
prend alors le centre de la case de la grille tapée.

| Genre | Effet |
|---|---|
| `MEDEVAC` | demande (9 lignes gardées dans le détail), ligne du flux, main courante |
| `FIRE` | demande « APPUI FEU », ligne du flux |
| `RESUPPLY` | demande « RAVITAILLEMENT », ligne du flux |
| `CONTACT`, `SITREP`, `SALUTE` | ligne du flux, main courante |
| `CASUALTY` | tableau des pertes, main courante |
| `ORDER` | ordre du camp (`fn_orderLocal`), émetteur « TOC · indicatif » |
| `MESSAGE` | message du poste au camp |
| `ACK` | accusé d'un ordre au nom d'un élément (compte rendu radio) |

Une demande du même genre, du même élément, encore ouverte et vieille de 3 minutes au plus n'est pas créée deux
fois : la saisie du poste complète celle du téléphone. `["validate", [genre, saisie]]` rend `[valide, erreurs,
saisie nettoyée]` sans rien envoyer ; `submit` écrit la première erreur dans la ligne d'état.

## 5. Messages, ordres et accusés (`fn_tocInbox`)

- Joueur sans téléphone : action ACE sur soi « Messages et ordres du TOC » (le nombre de non-lus est dans le nom de
  l'action), ou une touche à choisir dans Options > Commandes > COMSPEC ATAK. Aucune alerte du jeu.
- `["ack", [id, "ACK" | "EXEC" | "DONE" | "FAILED"]]` : j'accuse un ordre, sans l'app Tâches.
- Tableau des ordres du camp (panneau `ORDERS`) :
  `[id, heure, type, cible, émetteur, consigne, priorité, [x, y] ou [], accusés [[élément ou nom, statut, heure, saisi par]...], instant]`.
- Retour d'une action : `["feedback", [type, texte]]`. Il s'écrit dans la ligne d'état du PC du poste s'il est
  ouvert, dans une ligne en bas de l'interface Zeus, et dans le journal de la boîte. `fn_notify` y renvoie tout seul
  quand le téléphone est rangé ou absent : les appels existants n'ont pas à changer.
- Événements CBA locaux : `comspec_atak_native_tocInbox` `[id, genre]`, `comspec_atak_native_tocFeedback` `[texte, type]`.

## 6. Contrôle par le serveur

`["send", [action, args]] call comspec_atak_native_fnc_tocRoom` ne change pas pour l'appelant. Dessous, la demande
part en `remoteExecCall` vers le mode `req` : le serveur lit `remoteExecutedOwner`, retrouve le joueur, son camp
(`side group`), son nom et son UID (`getPlayerUID`). Ce que le client écrivait (camp, libellé, UID) n'est plus lu.

- L'ancien événement `comspec_atak_native_tocRoom` n'est plus écouté par un serveur 4.11.0. Un client plus ancien que
  le serveur ne peut plus écrire dans la salle.
- Serveur plus ancien que le client : le client garde l'ancien envoi (le serveur publie `COMSPEC_ATAK_TocSrvAuth`).
- Un `remoteExec` direct d'un client vers `fn_tocRoom` « do », `fn_tocEntry` « apply », `fn_tocInbox` « ackFor »,
  `fn_tocFeed` ou les modes serveur de `fn_tocTracks` est refusé.
- Pas encore couvert (lot 2) : `fn_tocHall` (salles), l'événement de camp `comspec_atak_native_sideEvent`.

## 7. Branchements laissés aux autres branches

Rien n'a été changé dans leurs fichiers. À faire chez elles, une fois chacune :

| Fichier | Branchement |
|---|---|
| `fn_tocLaptop.sqf`, `fn_tocBoard.sqf` | lire `ORBAT`, `STRENGTH`, `ORDERS` par le registre ; dessiner les formulaires de `["forms"]` et appeler `submit` ; onglet ORBAT sans `fn_bftOrbat` (D14, D18) |
| `fn_tocScreen.sqf` | affichage « ORDRES » ; pied d'écran avec `["lastFeedback"]` ; source et santé d'un nœud ; note de plafond (D8, D13) |
| `fn_tocScreen.sqf` l. 1719 | « Diffuser mes diapositives » sans téléphone (D12) |
| `fn_moduleTocScreen.sqf` l. 79, `fn_tocConfig.sqf` l. 379 | escouades proposées : tous les groupes, `["orbat", camp]` (D11) |
| `fn_tocSat.sqf` | rien à changer : les pistes amies viennent du flux, qui contient maintenant tout le camp (D10) |
| `fn_tocDraw.sqf` | état de dessin encore lu dans `COMSPEC_ATAK_State` : à vérifier en jeu sans téléphone (D15) |

Ajouts d'une ligne dans des fichiers d'autres branches (les seuls) :

- `functions/ui/fn_notify.sqf` : téléphone rangé ou absent, le retour part à `fn_tocInbox` « feedback ».
- `functions/toc/fn_tocAppData.sqf` (console refaite, 4.9.0) : un nœud du flux de source « J » garde le libellé
  « source directe » de la console au lieu d'être pris pour un téléphone. La console lisait déjà le jeu sur le poste
  de l'opérateur ; depuis ce lot le serveur publie les mêmes unités à tous les lecteurs, écrans compris, et la lecture
  locale de la console ne sert plus que de repli (serveur plus ancien, réglage « Réseau ATAK »).

D16, COMSPEC Link : `XEH_postInitClient.sqf` l. 296-304 règle `comspec_overwatch_require_item` sur `fn_hasDevice`.
Sans téléphone, Link garde ses boucles en attente (« Pas de terminal ATAK »). Rien de ce lot ne lit ces boucles. À
changer côté Link, hors `fn_radioGuard` : un opérateur assis à un poste (`COMSPEC_ATAK_TocView` posé sur le joueur, ou
`fn_vehicleTerminal` qui rend `"toc"`) doit compter comme terminal, et la ligne « Pas de terminal » ne doit plus
s'écrire au journal pour un joueur sans téléphone.

## 8. Qui est qui : nom affiché et tableau EFFECTIF

### Nom affiché (`fn_tocDisplayName`)

```sqf
private _nom = [_unite] call comspec_atak_native_fnc_tocDisplayName;   // « VIPER 2 », « Alpha 1-1 · 2 (non lié) »
```

Seule fonction à appeler pour écrire qui est qui. Ordre pour un joueur : indicatif Athena (ATAK, nœud, C2 / Groupe,
compte relié) ; sinon groupe et rang du jeu, avec « (non lié) » si le compte n'est pas relié ; sinon rôle ; le pseudo
Steam seulement en dernier recours et seulement si le réglage `comspec_atak_native_toc_show_steam` est coché (décoché
par défaut ; coché, il est ajouté entre parenthèses). IA : indicatif, sinon groupe et rang, jamais la classe. Aucune
requête : tout vient de variables déjà publiées sur l'unité.

Déjà appliqué dans ce lot : nœuds et ORBAT (`fn_tocTracks`), nœuds des téléphones (`fn_tocFeed`), auteur des lignes de
la salle (`fn_tocRoom` « me » et « req »), caméra de casque (`fn_tocCam`), EFFECTIF. À basculer dans les autres branches
(`name _unité`, `name player`) :

| Fichier | Lignes | Affichage |
|---|---|---|
| `fn_tocLaptop.sqf` | 245, 1584, 2035, 2066 | opérateur du PC, « poste pris par », « Chef de … », pied de la console |
| `fn_tocAppData.sqf` | 53, 99 | repli du nom d'un nœud, fiche d'une unité (« name ») |
| `fn_pageToc.sqf` | 109, 110 | app Poste TOC du téléphone |

Recherche à relancer : `grep -nE "name (player|_[a-zA-Z]+)|profileName" functions/toc/*.sqf`, hors `fn_tocDisplayName.sqf`.
`fn_tocRoom.sqf` (recherche d'un élément par son nom) et `fn_tocInbox.sqf` « ackFor » comparent ou transmettent un nom
sans l'afficher : à laisser.

### Tableau EFFECTIF (`fn_tocRoster`)

Ligne : `[clé, indicatif, rôle, nom, prénom, unité, état, grille, mise à jour, IA, compte relié, a un ATAK, [x, y]]`.

- Rôle : fonction de l'ORBAT ou rôle du slot (`fn_displayRole`), sinon ACE (médecin, infirmier, EOD, sapeur), sinon
  poste à bord, sinon rôle ATAK, sinon « Chef de groupe » ; le poste à bord est ajouté quand il dit autre chose.
- Nom et prénom : ceux du personnage, identité SSE active du joueur (`COMSPEC_SSE_LastName`, `COMSPEC_SSE_FirstName`,
  publiées sur l'unité par le mod SSE, donc lisibles pour un joueur distant). Jamais la vraie vie, jamais le pseudo.
  Aucune identité : « — ».
- État : `MORT`, `BLESSÉ`, `EN VÉHICULE`, `SANS TÉLÉPHONE`, `EN LIGNE`, dans cet ordre de priorité.
- Réglage `comspec_atak_native_toc_roster_names` : `console` (défaut : nom et prénom au PC seulement), `off`, `all`.

```sqf
// Écran : colonnes selon le réglage, lignes groupées par unité (titre ["#", unité, effectif, pertes]), pages.
(["wall", ["WEST", _page, 12]] call comspec_atak_native_fnc_tocRoster) params ["_cols", "_lignes", "_page", "_pages", "_total"];
// PC du poste : recherche, tri ("cs", "role", "last", "first", "unit", "state"), sens, IA comprises ou non.
private _lignes = ["console", ["WEST", "alpha", "unit", false, false]] call comspec_atak_native_fnc_tocRoster;
private _texte = ["export", "WEST"] call comspec_atak_native_fnc_tocRoster;                  // à copier
["action", [_cle, "follow"]] call comspec_atak_native_fnc_tocRoster;                        // suivi au satellite 1
(["action", [_cle, "order"]] call comspec_atak_native_fnc_tocRoster) params ["_genre", "_saisie"];   // à ouvrir dans fn_tocEntry
```

Panneaux du registre : `ROSTER` (args `[recherche, tri, décroissant, avec les IA]`), `ROSTERWALL` (args `[page, lignes]`).

**Ce lot fournit les données, pas les deux vues.** Il n'existe pas de registre de vues dans le tableau des écrans
(4.8.0) : un affichage est une entrée écrite en dur dans une dizaine d'endroits. Pour l'affichage « EFFECTIF » :

| Fichier | Ajout |
|---|---|
| `fn_tocScreen.sqf` | `"ROSTER"` dans `_views` et `_boards` (l. 90, 107), son titre (l. 1568) |
| `fn_tocBoard.sqf` | dessin du tableau depuis `["wall", …]` : une ligne par entrée, largeur de colonne = part de `_cols`, page suivante toutes les 10 s |
| `fn_tocTheme.sqf`, `docs/TOC-THEME.md` | régions et colonnes de `ROSTER` (tables `_lay`, `_cols`) |
| `fn_tocMenu.sqf`, `fn_tocManager.sqf` | entrée « Effectif » dans la catégorie DONNÉES |
| `config.cpp`, `stringtable.xml`, `fn_tocHallUi.sqf`, `fn_moduleTocScreen.sqf` | choix « Effectif » des listes Eden et Zeus (une ligne chacun, réclamée par `AtakTocScreenConfigAssetTest`) |
| `fn_tocApp.sqf` ou `fn_tocLaptop.sqf` | « Situation › EFFECTIF » : liste depuis `["console", …]`, champ de recherche, tri au clic sur l'en-tête, boutons Suivre, Message, Ordre (`["action", …]`), Exporter (`["export", …]`) |

## 9. Essais sans le jeu

- `tests/Unit/AtakTocAutonomyAssetTest.php` rejoue en PHP les tables de cas écrites dans le SQF (états, fusion,
  plafond, intervalles, validation, doublons).
- En jeu, console de débogage : `["selfTest"] call comspec_atak_native_fnc_tocNodeStatus`, `…_tocTracks`,
  `…_tocEntry`, `…_tocDisplayName`, `…_tocRoster` rendent `[réussis, cas en échec]`.

## 10. Liste d'essai « mission à zéro téléphone » (à dérouler par TTRD)

Mission : réglage « objet obligatoire » actif, **aucun téléphone** dans les inventaires ni les caisses, Athena
coupé, COMSPEC Link absent du serveur. Une salle TOC moyenne posée par le module, deux groupes de joueurs, un groupe
d'IA, un véhicule, un hélicoptère, un drone. Réglage « Source des positions du TOC » sur Mixte.

« Données » : couvert par ce lot. « Affichage » : demande aussi le branchement de la console ou des écrans (7).

- [ ] 1. Le PC du poste s'ouvre par ACE et par la molette ; aucun message ne parle de téléphone ou de terminal.
- [ ] 2. Carte du PC et écrans : tous les groupes, le véhicule, l'hélicoptère et le drone sont vus à leur vraie
      position, aucun en « perdu » ni « hors ligne ». (Données ; le drone est un nœud « air », sous-genre « uav ».)
- [ ] 3. GROUPES, ORBAT, RÉSEAU : remplis ; l'ORBAT montre les groupes du jeu. (Affichage pour l'onglet ORBAT.)
- [ ] 4. Un joueur est blessé puis tué : la santé de sa piste passe à blessé puis tué, le tableau des pertes compte
      le mort dans les 5 secondes, une ligne « PERTE » entre dans le flux.
- [ ] 5. L'opérateur saisit un contact, une MEDEVAC et une demande de tir : ils paraissent dans le flux, la main
      courante et les demandes, sur tous les écrans. (Affichage pour les formulaires ; en attendant, console de
      débogage : `["submit", ["CONTACT", [["el", "ALPHA 1"], ["enemy", "3 fantassins"]], []]] call comspec_atak_native_fnc_tocEntry`.)
- [ ] 6. L'opérateur émet un ordre à un groupe de joueurs : le chef le lit par ACE sur soi (« Messages et ordres du
      TOC »), clique « Reçu » ; l'accusé remonte au tableau des ordres. (Affichage pour le tableau au mur.)
- [ ] 7. L'opérateur envoie un message au camp : lu sans téléphone dans la même fenêtre, et dans le flux des écrans.
- [ ] 8. Dessin d'un trait, d'un marqueur et d'un élément de plan à la console : vus sur le mur. (D15, à vérifier.)
- [ ] 9. Caméra du drone et caméra de casque d'un joueur sur un écran et en mosaïque.
- [ ] 10. Satellite : pistes amies affichées.
- [ ] 11. Chaque action ACE d'un écran donne un retour : ligne d'état du PC s'il est ouvert, ligne du Zeus, journal
      de la fenêtre « Messages et ordres du TOC ». (Affichage pour le pied d'écran.)
- [ ] 12. Journal du jeu : aucune erreur de script. (« Pas de terminal » : voir D16, côté Link.)
- [ ] 13. Même mission avec un téléphone donné à un joueur : sa piste passe en source « jeu et ATAK » et gagne
      l'état de sa liaison ; rien d'autre ne change. (La batterie n'est pas publiée par le téléphone : reportée.)
- [ ] 14. Redémarrage du serveur : salle, registres et saisies revenus. (Le tableau des ordres du camp, lui, repart
      vide : sa sauvegarde va avec le lot 2.)

À regarder aussi, serveur dédié puis partie hébergée : la piste de l'hôte est « en direct » ; un joueur du camp
adverse qui appelle `fn_tocRoom` ne change rien à la salle ; avec 100 puis 300 IA, images par seconde du serveur
avant et après l'ouverture d'un PC du poste.
