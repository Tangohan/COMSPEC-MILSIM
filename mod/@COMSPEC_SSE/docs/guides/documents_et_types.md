# Documents d'identité et types de documents

Version anglaise : [documents_et_types.en.md](documents_et_types.en.md). Sommaire : [index.md](index.md).

## À quoi ça sert

Les papiers d'identité du SSE sont des **documents fictifs** (pays imaginaires, mention SIMULATION sur chaque face) que les joueurs et les IA portent, présentent, se font fouiller et saisir. Un document a un **type** (carte d'identité, passeport, permis, carte militaire, et ceux qu'ajoutent les extensions : carte de presse, laissez-passer…), un **pays émetteur**, une **nature** (vrai, couverture, faux), un titulaire imprimé et, pour certains, une bande MRZ et une puce.

Les types sont déclarés dans le registre de configuration **`CfgComspecDocuments`** ; le mod en livre quatre et toute extension peut en ajouter (voir [extensions_documents.md](extensions_documents.md)).

## Les types livrés

| Type (classe) | Nom | Bande MRZ | Puce | Format | Faces |
|---|---|---|---|---|---|
| `id_card` | Carte d'identité | 3 lignes (TD1, « I< ») | oui | carte ID-1 (ratio 1,585) | recto / verso |
| `passport` | Passeport | 2 lignes (TD3) | oui | page de passeport (1,42) | une page |
| `driving_license` | Permis de conduire | 3 lignes (« D< ») | non | carte ID-1 | recto / verso |
| `military_id` | Carte militaire (hérite de la carte d'identité) | 3 lignes | oui | carte ID-1 | recto / verso |
| `residence_permit` | Titre de séjour (pays réels) | 3 lignes (TD1, « IR », « AR » en Allemagne) | oui | carte ID-1 | recto / verso |
| `vehicle_registration` | Certificat d'immatriculation (pays réels) | aucune | non | feuillet A6 (1,414) | une face |

Pays fictifs (réglage « Pays des papiers », par défaut selon la carte) :

| Code | Pays | Numéro | Autorité |
|---|---|---|---|
| ALT | République d'Altis | AL@###### | Préfecture de Kavala |
| TNA | Fédération de Tanoa | T######## | Bureau de l'état civil de Georgetown |
| LVN | République de Livonie | LV####### | Mairie de Lubjansk |
| MLD | République de Malden | M@@###### | Préfecture du Port |

(# un chiffre, @ une lettre ; validité 10 ans.) **Pays réels** (proposés d'office, en tête des listes) : France, Allemagne, États-Unis, Royaume-Uni, Belgique, Autriche, Suisse, Pays-Bas, Italie, Espagne, Pologne (complets), puis 34 autres pays (génériques). Papiers, formats, MRZ et numéros de chaque pays : [pays_des_papiers.md](pays_des_papiers.md).

## Nature, procédé, qualité

| Nature | Ce que ça change |
|---|---|
| **Vrais papiers** (civil) | Délivrés par le pays : passent tous les contrôles |
| **Couverture préparée** (cover) | Faits pour un infiltré : passent N0 à N3, seule la biométrie (N4, SEEK) les trahit |
| **Faux papiers** (forged) | Détectables selon le **procédé** et la **qualité** |

Procédé du faux : **Au hasard (selon la qualité)** · **Contrefaçon complète** · **Vrai document modifié** · **Photo substituée** · **Vierge volé**. Qualité de 0 à 100 (« 0 : grossier, vu au premier coup d'œil. 100 : excellent, seule la base ou la biométrie le trahit. »).

La nature n'est **jamais visible des joueurs** : seuls le Zeus (« Voir les vrais papiers ») et les contrôles la révèlent.

## Où donner des papiers

| Contexte | Chemin | Ce qui s'ouvre |
|---|---|---|
| Zeus (ZEN) | **COMSPEC · Identité › Donner des papiers** sur une personne | Fenêtre **Choisir un document** |
| Eden | Barre de menus › **COMSPEC SSE › Donner un document** (une unité sélectionnée) | Fenêtre **Choisir un document** |
| Eden, attribut | Attributs de l'unité › COMSPEC SSE › **Papiers** : combo avec tous les types (dont ceux des extensions) | Papiers créés à l'apparition |
| Éditeur SSE | Type **Papier** ou **Kit**, « Sur quelqu'un » / « Au sol » | Voir [editeur_sse.md](editeur_sse.md) |
| Arsenal | Une carte, un passeport, un permis du kit pris à l'Arsenal | Devient à l'apparition un document au nom du joueur |
| Athena | Kit composé sur Athena (identité principale ou couverture) | Remis à chaque arrivée |
| IA | Automatique selon le camp (réglage « Papiers des IA ») ou page [IA de la zone](ia_de_la_zone.md) | Fabriqués à la première demande |

### La fenêtre « Choisir un document »

De gauche à droite :

1. **Pays** : pays réels d'abord, puis les pays fictifs d'Arma. Un type peut restreindre ses pays (`countries[]`) ; un pays ne propose que les papiers qu'il délivre (Royaume-Uni : pas de carte nationale). L'aperçu montre le titre et les étiquettes dans la langue du pays.
2. **Types** : regroupés par catégorie (identité, voyage, permis, professionnel, laissez-passer, autre) ; les types d'une extension portent son nom entre parenthèses, ex. « Carte de presse (Documents d'exemple) ».
3. **Aperçu** du document choisi : recto (et verso si le type en a un), avec les champs imprimés tels qu'ils seront vus.

Puis les réglages :

| Champ | Valeurs | Exemple |
|---|---|---|
| **Nature** | Vrais papiers · Couverture préparée · Faux papiers | Couverture pour un infiltré |
| **Procédé du faux** | Au hasard · Contrefaçon complète · Vrai document modifié · Photo substituée · Vierge volé | Photo substituée : la puce révèle que la photo enregistrée n'est pas celle imprimée |
| **Qualité du faux** | 0 à 100 (50) | 20 pour un faux que l'examen visuel doit trahir |
| **Nom et prénom imprimés** | Texte ; vide : ceux de la personne, ou tirés | « PETRAKIS Yannis » |
| **Champs propres au document** | Déclarés par le type (`input = 1`) | « Média » pour une carte de presse ; « Zone autorisée » pour un laissez-passer |
| **Cible** | La personne visée, ou **Poser au sol** | Poser au sol pour un papier abandonné dans une maison |

Validez : le document est créé, l'état s'affiche dans la ligne d'état de la fenêtre (plus aucune alerte Arma ; détail au RPT si besoin).

## Pas à pas : un passeport volé dans une boîte à gants

Situation : l'équipe fouille un pick-up ; vous voulez qu'elle y trouve un passeport déclaré volé, pour qu'un contrôle N3 l'apprenne.

1. Zeus › **Donner des papiers** sur le conducteur (ou n'importe où, cible **Poser au sol** à côté du véhicule).
2. Pays : **Fédération de Tanoa**. Type : **Passeport**. Vérifiez l'aperçu.
3. Nature : **Vrais papiers**. Nom imprimé : « KALANI Moana ».
4. Cible : **Poser au sol** › Valider.
5. Zeus › **Signaler volé / révoquer** sur ce document : au contrôle N3, « Document signalé VOLÉ ».

## Ce que le joueur en fait

Un joueur voit ses papiers par le menu ACE personnel › **Mes papiers** › **Ouvrir mes papiers** (visionneuse : RECTO / VERSO, ZOOM, SCANNER (ATAK), RELEVER, FERMER). Il les **présente** ou les **remet** à qui les demande. Le contrôle lui-même est décrit dans [controle_identite.md](controle_identite.md).

## Photo d'identité

La photo du titulaire est prise **en direct par la caméra de rendu du jeu**, dans une scène dédiée éclairée de façon neutre (identique de jour et de nuit) : le visage du personnage, tel quel. Réglage **Photo d'identité en direct** (COMSPEC SSE › Identité, oui par défaut) ; décoché, un portrait de repli est utilisé. Une « photo substituée » s'affiche décalée et de travers.

## Pièges fréquents

- **Stock fini de classes** : chaque exemplaire est une classe d'objet libre (512 cartes, 256 passeports, permis et cartes militaires ; les extensions déclarent leur propre objet `item` et ses instances). Au-delà, le papier reste virtuel et journalisé.
- Un objet du catalogue pris à l'Arsenal est **vierge** (« Document vierge : aucun titulaire, aucun numéro délivré ») : seul le kit Athena ou l'attribut Eden le personnalise.
- Le module Zeus de l'ancienne version ne proposait que les pays fictifs ; la fenêtre **Choisir un document** propose tous les pays et tous les types chargés.
- Un transfert fait par un script direct (addItem / removeItem) n'est pas suivi tant qu'il n'appelle pas `comspec_sse_fnc_idPaperTrack`.
- Le registre des papiers vit le temps de la mission : rien n'est gardé d'une partie à l'autre, sauf les kits Athena (remis à chaque arrivée).
- Les types d'une extension **désactivée** (menu Extensions) ne sont plus proposés : les documents déjà créés restent lisibles.

## Athena

- Chaque membre reçoit **sa** carte (identité Athena principale, ou couverture si une fiche de couverture la lui attribue) ; au contrôle, c'est l'identité Athena liée à la carte qui s'affiche, jamais celle du porteur.
- Les papiers volés, perdus ou révoqués sur Athena sont signalés au contrôle N3 (base locale) ; « Documents et avis d'Athena » (réglage) charge ces listes.
- Un document créé depuis « Choisir un document » peut reprendre le **numéro d'un document Athena** (champ de l'ancien module « Donner des papiers »).
