# Identity documents and document types

French version: [documents_et_types.md](documents_et_types.md). Guides index: [index.en.md](index.en.md).

## What it is for

SSE identity papers are **fictional documents** (imaginary countries, SIMULATION marking on every side) that players and AI carry, present, have searched and seized. A document has a **type** (identity card, passport, licence, military ID, plus those added by extensions: press card, pass…), an **issuing country**, a **kind** (real, cover, forged), a printed holder and, for some, an MRZ strip and a chip.

Types are declared in the **`CfgComspecDocuments`** config registry; the mod ships four and any extension can add more (see [extensions_documents.en.md](extensions_documents.en.md)).

## Shipped types

| Type (class) | Name | MRZ strip | Chip | Format | Sides |
|---|---|---|---|---|---|
| `id_card` | Carte d'identité (Identity card) | 3 lines (TD1, « I< ») | yes | ID-1 card (ratio 1.585) | front / back |
| `passport` | Passeport (Passport) | 2 lines (TD3) | yes | passport page (1.42) | one page |
| `driving_license` | Permis de conduire (Driving licence) | 3 lines (« D< ») | no | ID-1 card | front / back |
| `military_id` | Carte militaire (Military ID) (inherits from the identity card) | 3 lines | yes | ID-1 card | front / back |

Fictional countries (setting « Pays des papiers » (Country of the papers), by default according to the map):

| Code | Country | Number | Authority |
|---|---|---|---|
| ALT | République d'Altis (Republic of Altis) | AL@###### | Préfecture de Kavala (Kavala Prefecture) |
| TNA | Fédération de Tanoa (Federation of Tanoa) | T######## | Bureau de l'état civil de Georgetown (Georgetown Civil Registry Office) |
| LVN | République de Livonie (Republic of Livonia) | LV####### | Mairie de Lubjansk (Lubjansk Town Hall) |
| MLD | République de Malden (Republic of Malden) | M@@###### | Préfecture du Port (Port Prefecture) |

(# a digit, @ a letter; valid for 10 years.) With the setting **Univers réaliste-simulation (pays réels stylisés)** (Realistic-simulation universe (stylised real countries)), stylised real countries are added (generic template per region, « SIMULATION — DOCUMENT DE JEU » (SIMULATION — GAME DOCUMENT), game numbers): see [controle_identite.en.md](controle_identite.en.md).

## Kind, method, quality

| Kind | What it changes |
|---|---|
| **Vrais papiers** (Real papers) (civil) | Issued by the country: pass every check |
| **Couverture préparée** (Prepared cover) (cover) | Made for an infiltrator: pass N0 to N3, only biometrics (N4, SEEK) give them away |
| **Faux papiers** (Forged papers) (forged) | Detectable depending on the **method** and the **quality** |

Forgery method: **Au hasard (selon la qualité)** (Random (based on quality)) · **Contrefaçon complète** (Full counterfeit) · **Vrai document modifié** (Altered genuine document) · **Photo substituée** (Substituted photo) · **Vierge volé** (Stolen blank). Quality from 0 to 100 (« 0 : grossier, vu au premier coup d'œil. 100 : excellent, seule la base ou la biométrie le trahit. » (0: crude, spotted at first glance. 100: excellent, only the database or biometrics give it away.)).

The kind is **never visible to players**: only Zeus (« Voir les vrais papiers » (See the real papers)) and checks reveal it.

## Where to hand out papers

| Context | Path | What opens |
|---|---|---|
| Zeus (ZEN) | **COMSPEC · Identité › Donner des papiers** (COMSPEC · Identity › Give papers) on a person | Window **Choisir un document** (Choose a document) |
| Eden | Menu bar › **COMSPEC SSE › Donner un document** (Give a document) (one unit selected) | Window **Choisir un document** |
| Eden, attribute | Unit attributes › COMSPEC SSE › **Papiers** (Papers): combo with all types (including those from extensions) | Papers created on spawn |
| SSE editor | Type **Papier** (Paper) or **Kit**, « Sur quelqu'un » (On someone) / « Au sol » (On the ground) | See [editeur_sse.en.md](editeur_sse.en.md) |
| Arsenal | A card, a passport, a licence from the kit taken from the Arsenal | Becomes on spawn a document in the player's name |
| Athena | Kit composed on Athena (main identity or cover) | Handed out on every arrival |
| AI | Automatic by side (setting « Papiers des IA » (AI papers)) or the [Area AI](ia_de_la_zone.en.md) page | Made on first request |

### The « Choisir un document » window

From left to right:

1. **Pays** (Country): list of issuing countries (fictional, plus stylised real countries if the setting is on). A type can restrict its countries (`countries[]`): it disappears from the middle list for a country it does not cover.
2. **Types** (Types): grouped by category (identity, travel, permit, professional, pass, other); an extension's types carry its name in parentheses, e.g. « Carte de presse (Documents d'exemple) » (Press card (Example documents)).
3. **Aperçu** (Preview) of the chosen document: front (and back if the type has one), with the printed fields as they will be seen.

Then the settings:

| Field | Values | Example |
|---|---|---|
| **Nature** (Kind) | Vrais papiers · Couverture préparée · Faux papiers | Cover for an infiltrator |
| **Procédé du faux** (Forgery method) | Au hasard · Contrefaçon complète · Vrai document modifié · Photo substituée · Vierge volé | Photo substituée: the chip reveals that the stored photo is not the printed one |
| **Qualité du faux** (Forgery quality) | 0 to 100 (50) | 20 for a forgery that visual inspection must give away |
| **Nom et prénom imprimés** (Printed last and first name) | Text; empty: the person's, or drawn at random | « PETRAKIS Yannis » |
| **Document-specific fields** | Declared by the type (`input = 1`) | « Média » (Media) for a press card; « Zone autorisée » (Authorised area) for a pass |
| **Cible** (Target) | The targeted person, or **Poser au sol** (Place on the ground) | Place on the ground for a paper left behind in a house |

Confirm: the document is created, the status is shown in the window's status line (no more Arma hints; details in the RPT if needed).

## Step by step: a stolen passport in a glovebox

Situation: the team searches a pickup; you want them to find a passport reported stolen in it, so that an N3 check reveals it.

1. Zeus › **Donner des papiers** on the driver (or anywhere, target **Poser au sol** next to the vehicle).
2. Country: **Fédération de Tanoa**. Type: **Passeport**. Check the preview.
3. Kind: **Vrais papiers**. Printed name: « KALANI Moana ».
4. Target: **Poser au sol** › Confirm.
5. Zeus › **Signaler volé / révoquer** (Report stolen / revoke) on this document: at an N3 check, « Document signalé VOLÉ » (Document reported STOLEN).

## What the player does with it

A player sees their papers through the ACE self menu › **Mes papiers** (My papers) › **Ouvrir mes papiers** (Open my papers) (viewer: RECTO / VERSO (FRONT / BACK), ZOOM, SCANNER (ATAK) (SCAN (ATAK)), RELEVER (RECORD), FERMER (CLOSE)). They **present** them or **hand them over** to whoever asks. The check itself is described in [controle_identite.en.md](controle_identite.en.md).

## ID photo

The holder's photo is taken **live by the game's render camera**, in a dedicated scene with neutral lighting (identical by day and by night): the character's face, as is. Setting **Photo d'identité en direct** (Live ID photo) (COMSPEC SSE › Identité, on by default); unticked, a fallback portrait is used. A "substituted photo" is shown offset and skewed.

## Common pitfalls

- **Finite stock of classes**: each copy is a free object class (512 cards, 256 passports, licences and military IDs; extensions declare their own `item` object and its instances). Beyond that, the paper stays virtual and is logged.
- A catalogue object taken from the Arsenal is **blank** (« Document vierge : aucun titulaire, aucun numéro délivré » (Blank document: no holder, no number issued)): only the Athena kit or the Eden attribute personalises it.
- The Zeus module of the old version only offered the fictional countries; the **Choisir un document** window offers every country and every loaded type.
- A transfer made by a direct script (addItem / removeItem) is not tracked until it calls `comspec_sse_fnc_idPaperTrack`.
- The paper registry lives for the duration of the mission: nothing is kept from one session to the next, except Athena kits (handed out on every arrival).
- The types of a **disabled** extension (Extensions menu) are no longer offered: documents already created remain readable.

## Athena

- Each member receives **their own** card (main Athena identity, or cover if a cover record assigns one to them); at a check, it is the Athena identity linked to the card that is shown, never the holder's.
- Papers stolen, lost or revoked on Athena are flagged at an N3 check (local database); « Documents et avis d'Athena » (Athena documents and notices) (setting) loads these lists.
- A document created from « Choisir un document » can take over the **number of an Athena document** (field of the old « Donner des papiers » module).
