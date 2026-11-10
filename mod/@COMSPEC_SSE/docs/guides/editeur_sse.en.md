# SSE editor

French version: [editeur_sse.md](editeur_sse.md). Guides index: [index.en.md](index.en.md).

## What it is for

The SSE editor is the window Zeus (and the mission maker in Eden) uses to **build game content and hand it to the players** without writing a single line of script: a note found on a body, the full identity of a contact, a passport to leave in a glovebox, an "infiltration" kit (identity + papers) for a player, or the mission's active case file.

Five content types, in the order of the cards in the window:

| Type | Card « 1. QUE CRÉER ? » (1. WHAT TO CREATE?) | What it is | What players see |
|---|---|---|---|
| Intelligence | **Renseignement** (Intelligence) — « Note, rapport, carnet trouvé » (Note, report, found notebook) | A text document with a title, a classification and a sheet of paper | A "Documents" item to pick up, a paper found in a search, or a message in ATAK |
| Identity | **Identité** (Identity) — « Une personne et sa légende » (A person and their cover story) | A person, real or cover, with their cover story | Papers in their name; the cover story is printed on the person's record |
| Paper | **Papier** (Paper) — « Carte, passeport, permis » (Card, passport, licence) | A single document attached to an identity | The document itself (card, passport, licence, military ID) |
| File | **Dossier** (File) — « Suivi d'une cible ou d'un site » (Tracking a target or a site) | The reference of the ongoing case | Nothing directly: subsequent items and records are attached to it (Athena) |
| Kit | **Kit** (Kit) — « Lot prêt pour une mission » (Set ready for a mission) | Identity and set of papers for one person | Two consistent papers in the same name (e.g. card + passport) |

Everything you create is kept in a **library** (on your machine, and on Athena if the server is linked): you come back to it, duplicate, and place again in another session.

## Where to find it

| Context | Path |
|---|---|
| Zeus without ZEN | Modules › COMSPEC SSE › **Éditeur SSE** (SSE editor) (module placed anywhere; placed on a living AI or with AI units selected, it opens directly on the « IA de la zone » (Area AI) page) |
| Zeus with ZEN | Category **COMSPEC · SSE** › **Ouvrir l'éditeur** (Open the editor); **Configurer une IA** (Configure an AI) opens the « IA de la zone » page |
| Eden | Menu bar › **COMSPEC SSE** › **Éditeur SSE** (the « IA de la zone » page is not available in Eden) |
| Script | `['open', [[], objNull]] call comspec_sse_fnc_editor` |

There is no access through the ACE menu or the phone: the editor is a game-master tool, not a player tool.

## The window

Common header: logo, page title (« SSE / ÉDITEUR » (SSE / EDITOR), « SSE / BIBLIOTHÈQUE » (SSE / LIBRARY), « SSE / IA DE LA ZONE » (SSE / AREA AI)), **status line** (« Pas encore enregistré » (Not saved yet), « Brouillon sur ce poste » (Draft on this machine), « Enregistré sur Athena » (Saved on Athena), « Publié dans cette partie » (Published in this session), « Brouillon enregistré à HH:MM » (Draft saved at HH:MM)) and three buttons: **IA DE LA ZONE** (AREA AI), **BIBLIOTHÈQUE** (LIBRARY) (which becomes **NOUVEAU** (NEW) from the other pages) and **FERMER** (CLOSE).

On first launch, a three-step guide (« Bienvenue dans l'éditeur SSE » (Welcome to the SSE editor), « Voyez ce que verront les joueurs » (See what the players will see), « Placez dans le monde » (Place in the world)) is shown once; buttons **SUIVANT** (NEXT) then **COMMENCER** (START).

### 1. QUE CRÉER ? (1. WHAT TO CREATE?)

Click one of the five cards. Below the cards, the **PARTIR DE** (START FROM) line offers three templates per type that pre-fill the fields:

- Intelligence: **Rapport de source** (Source report) · **Carnet manuscrit** (Handwritten notebook) · **Ordre intercepté** (Intercepted order)
- Identity: **Résident** (Resident) · **Couverture** (Cover) · **Cible** (Target)
- Paper: **Papier civil** (Civilian paper) · **Papier de couverture** (Cover paper) · **Faux grossier** (Crude forgery)
- File: **Cible** (Target) (ref. CIBLE-01) · **Site** (Site) (SITE-01) · **Réseau** (Network) (RESEAU-01)
- Kit: **Infiltration** (Infiltration) · **Militaire** (Military) · **Journaliste** (Journalist)

### The fields

| Field | For | Example |
|---|---|---|
| **Titre** (Title) | Intelligence | « Liste de paie, cellule de Kavala » (Payroll list, Kavala cell) |
| **Prénom et nom** (First and last name) | Identity, paper, kit | « Alexandros Nikolaidis » (required: « Donnez d'abord un prénom et un nom. » (Give a first and last name first.)) |
| **Référence du dossier** (File reference) | File | « SSE-2026-0007 » (the Athena reference of the case, or your internal code) |
| **Classification** (Classification) | Intelligence | NON PROTÉGÉ (UNCLASSIFIED) · DIFFUSION RESTREINTE (RESTRICTED) · CONFIDENTIEL (CONFIDENTIAL) (banner printed on the sheet) |
| **Type de papier** (Paper type) | Paper | Carte d'identité (Identity card) · Passeport (Passport) · Permis de conduire (Driving licence) · Carte militaire (Military ID) (+ the types of loaded extensions) |
| **Lot de papiers** (Set of papers) | Kit | Carte et passeport (Card and passport) · Carte militaire et carte (Military ID and card) · Carte et permis (Card and licence) |
| **Nature** (Kind) | Identity, paper, kit | **Vraie** (Real) (official papers), **Couverture** (Cover) (prepared, passes everything except biometrics), **Faux** (Forged) (detectable depending on quality) |
| **Papier** (Paper) | Intelligence | **Automatique (selon la pièce)** (Automatic (based on the item)), a sheet from the list (thumbnail), or **Feuille unie (ancien rendu)** (Plain sheet (legacy rendering)) |
| **Contenu** (Content) | Intelligence | The text the player reads |
| **Légende (imprimée sur la fiche de la personne)** (Cover story (printed on the person's record)) | Identity, kit | « Né à Charkia, mécanicien au port, cousin de Stavros » (Born in Charkia, mechanic at the port, Stavros's cousin) |
| **Notes (bibliothèque seulement, non envoyées)** (Notes (library only, not sent)) | File, paper | Your game-master notes, never seen by players |

The right-hand panel **APERÇU EN JEU** (IN-GAME PREVIEW) (« Vu comme : le joueur qui le trouve » (Seen as: the player who finds it)) shows the sheet as the player will open it: « RÉF. SSE-<id> » (or « NOUVEAU » (NEW)), stamp, title, text. Empty fields: a greyed-out example is shown.

### 2. COMMENT LES JOUEURS L'OBTIENNENT (2. HOW THE PLAYERS GET IT)

Three cards, depending on the type (none for a file: « Devient le dossier actif : les pièces et fiches suivantes y sont rattachées. » (Becomes the active case: subsequent items and records are attached to it.)).

Intelligence:

| Card | Help text | Effect |
|---|---|---|
| **À ramasser** (To pick up) | Sur un objet choisi dans le monde (On an object chosen in the world) | A "Documents" item is placed at the clicked point; players pick it up |
| **En fouille** (In a search) | Sur une personne ou dans un véhicule (On a person or in a vehicle) | The document appears in the search of the clicked person or vehicle |
| **Au téléphone** (By phone) | À un groupe ou à un joueur (To a group or a player) | Arrives directly in the recipients' ATAK (targeted group, otherwise the Zeus's side) |

Identity, paper, kit:

| Card | Help text | Effect |
|---|---|---|
| **Au sol** (On the ground) | Posés à l'endroit choisi (Placed at the chosen spot) | The papers are placed on the ground at the clicked point |
| **Sur quelqu'un** (On someone) | Une personne ou un véhicule à cliquer (A person or a vehicle to click) | Handed into the person's inventory, or into the vehicle |
| **Annonce** (Notice) | Message au téléphone, aucun papier (Phone message, no paper) | An ATAK message announces these papers; no object is created |

### Footer

« Rien n'est envoyé aux joueurs avant « Placer et publier ». Le contenu reste enregistré sur ce poste. » (Nothing is sent to the players before "Place and publish". The content stays saved on this machine.) (with Athena: « …est aussi enregistré sur Athena. » (…is also saved on Athena.))

- **ENREGISTRER** (SAVE): versions the content in the library (title or name required).
- **PLACER ET PUBLIER** (PLACE AND PUBLISH): saves, closes the window, then waits for a **click in the world** (in Zeus: under the mouse; on foot: at the centre of the screen; **Esc cancels**). If the Zeus module already provided a position, placement is immediate. For a file, the button becomes **DEVENIR LE DOSSIER ACTIF** (MAKE IT THE ACTIVE CASE).

## Step by step: a notebook on the leader's body

Situation: the team is about to assault a house; you want them to find, on the leader's body, a notebook that names the next meeting place.

1. Zeus › **Ouvrir l'éditeur** (or the SSE editor module).
2. Card **Renseignement**, template **Carnet manuscrit**.
3. Title: « Carnet, pages du 3 au 7 » (Notebook, pages 3 to 7). Classification: NON PROTÉGÉ. Paper: a crumpled sheet from the list.
4. Content: « Mardi, livraison à la ferme de Sofia, 2 camions. Demander Yannis. » (Tuesday, delivery at Sofia's farm, 2 trucks. Ask for Yannis.). Check the preview on the right.
5. Card **En fouille**.
6. **PLACER ET PUBLIER** › click the leader (alive or dead). Status line: « Publié dans cette partie ».
7. When a player searches the body and reads the notebook, you get a notification and the library records **« Trouvé par un joueur »** (Found by a player).

Kit variant: card **Kit**, template **Infiltration**, the infiltrated player's « Prénom et nom », set **Carte et passeport**, kind **Couverture**, cover story, card **Sur quelqu'un** › click the player. They receive their papers and their cover story appears in their **Ma couverture** (My cover) app.

## The library

Button **BIBLIOTHÈQUE**.

- Filters: **Tout · Renseignements · Identités · Papiers · Dossiers · Kits · Modèles · Archives** (All · Intelligence · Identities · Papers · Files · Kits · Templates · Archives).
- Search: « Rechercher : titre, personne, opération, numéro de papier » (Search: title, person, operation, paper number) (covers the title, the operation, the content and the link).
- List: status icon, title, type, operation, status (**Brouillon · Modèle · Placé en mission · Archivé** (Draft · Template · Placed in mission · Archived)). Empty: « Rien ici pour le moment. » (Nothing here for now.)
- **SÉLECTION** (SELECTION) panel: **Modifier · Dupliquer · Placer dans le monde · Archiver** (Edit · Duplicate · Place in the world · Archive); **HISTORIQUE** (HISTORY) (versions, author, date, and « Trouvé par un joueur » at the top); **LIÉ À** (LINKED TO) (free text: case reference, target name).
- Three templates are created on first launch: « Kit infiltration, papiers et légende » (Infiltration kit, papers and cover story), « Rapport de source » (Source report), « Dossier cible » (Target file). Editing a template makes a copy: the original never changes.

## Common pitfalls

- **Nothing goes out before PLACER ET PUBLIER.** Saving only keeps a draft.
- **Esc during the placement click cancels**: the content stays « Brouillon » (Draft), nothing is placed.
- **Only a Zeus or an administrator can place** (the server checks; otherwise "placement refused" in the RPT).
- **Eden**: only an intelligence item « À ramasser » or « En fouille » can be placed (Init field of the object; for a search, select a person or a vehicle first). Everything else shows « Gardé dans la bibliothèque : à placer en mission, depuis le module Zeus » (Kept in the library: to be placed in mission, from the Zeus module).
- **Au téléphone / Annonce** go through native ATAK; without it, it is an ordinary SSE notification. A phone delivery is never replayed from one session to the next.
- **The library lives in your profile** (profileNamespace); Athena is only its mirror. Offline, the content stays marked as pending and is sent again at the next opening. In Eden, after an Athena failure, no retry for 60 s.
- **Classification** only has an effect on an intelligence item.
- **« Garder pour les prochaines parties »** (Keep for the next sessions) (Area AI page) requires the Athena link and the Confidential clearance; otherwise « Enregistrement sur Athena impossible (liaison, ou habilitation Confidentiel) : rien n'est gardé. » (Cannot save on Athena (link, or Confidential clearance): nothing is kept.)
- Debugging: `comspec_sse_id_editorDebug = true` (mission variable) writes the details to the RPT.

## Athena

- The library is synchronised with **Athena › Bureau SSE** (SSE Desk) when the server is linked (sent through COMSPEC Link); drafts, templates and placed content are found there, with the history.
- A **file** made active stores the items and records sent afterwards in the Athena case with the same reference.
- « Trouvé par un joueur » is also recorded on Athena (offline queue).
- The AI profiles of the SSE Desk (Profils IA (AI profiles)) feed the « IA de la zone » page: see [ia_de_la_zone.en.md](ia_de_la_zone.en.md).
